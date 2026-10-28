<?php

declare(strict_types=1);

namespace Simplify\Explain\Http\Controllers;

use App\Models\Node;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Simplify\Explain\Services\EmergencyAccess;
use Simplify\Explain\Services\Explainer;
use Simplify\Explain\Services\LogCheck;
use Throwable;

/**
 * The emergency page: the panel log and the Wings logs explained when the panel's own pages break, for example with
 * error 500. Plain HTML from this class only (no Filament, Livewire or Blade), so it keeps working when those fail.
 * Root admins open it with their normal session; when even that fails, `php artisan simplify-explain:check --url`
 * prints a temporary address that needs no session at all. Every error is caught, so this page never answers 500.
 */
final class EmergencyController
{
    /** /simplify-explain/emergency: signed in through the normal session. */
    public function session(Request $request): Response
    {
        try {
            $user = user();
            if (! $user) {
                return $this->page(401, '<p>'.$this->t('sign_in').'</p>'.$this->terminalHint());
            }
            if (! $user->isRootAdmin()) {
                return $this->page(403, '<p>'.$this->t('root_only').'</p>');
            }

            return $this->handle($request, 'simplify-explain.user.'.$user->id, csrf_token());
        } catch (Throwable $error) {
            return $this->broken($error);
        }
    }

    /** /simplify-explain/emergency/{token}: the temporary address from the terminal, no session needed. */
    public function token(Request $request, string $token): Response
    {
        try {
            if (! EmergencyAccess::valid($token)) {
                return $this->page(403, '<p>'.$this->t('expired').'</p>'.$this->terminalHint());
            }

            return $this->handle($request, 'simplify-explain.emergency', null);
        } catch (Throwable $error) {
            return $this->broken($error);
        }
    }

    private function handle(Request $request, string $personal, ?string $csrf): Response
    {
        [$nodes, $nodeError] = $this->nodes();
        $answer = '';
        if ($request->isMethod('POST')) {
            $node = $request->filled('node') ? ($nodes[(int) $request->input('node')] ?? null) : null;
            if ($request->filled('node') && ! $node) {
                $answer = $this->answer($this->t('wings_log'), ['ok' => false, 'error' => $this->t('choose_node')]);
            } else {
                $source = $node ? 'wings' : 'panel';
                $result = Explainer::ask($source, null, $node, $personal, Explainer::ASKED_FROM['emergency']);
                $answer = $this->answer($node ? $this->t('wings_log_of', ['node' => $node->name]) : $this->t('panel_log'), $result);
            }
        }

        return $this->page(200, $this->form($nodes, $nodeError, $csrf).$answer);
    }

    /**
     * Nodes by id; an empty list and a message when the database cannot be reached.
     *
     * @return array{0: array<int, Node>, 1: ?string}
     */
    private function nodes(): array
    {
        try {
            return [Node::query()->orderBy('name')->get()->keyBy('id')->all(), null];
        } catch (Throwable $error) {
            report($error);

            return [[], $this->t('no_database')];
        }
    }

    /** @param  array<int, Node>  $nodes */
    private function form(array $nodes, ?string $nodeError, ?string $csrf): string
    {
        $html = '<p class="lead">'.$this->t('lead').'</p>';
        $html .= '<form method="post" class="ask" onsubmit="this.classList.add(\'busy\')">';
        $html .= $csrf ? '<input type="hidden" name="_token" value="'.self::e($csrf).'">' : '';
        $html .= '<button type="submit" name="source" value="panel">'.$this->t('check_panel').'</button>';
        foreach ($nodes as $id => $node) {
            $html .= '<button type="submit" name="node" value="'.(int) $id.'" class="secondary">'
                .$this->t('check_wings', ['node' => (string) $node->name]).'</button>';
        }
        $html .= '<p class="wait">'.$this->t('wait').'</p></form>';
        if ($nodeError) {
            $html .= '<p class="note">'.$nodeError.'</p>';
        }

        return $html;
    }

    /** @param  array<string, mixed>  $result */
    private function answer(string $asked, array $result): string
    {
        if (! $result['ok']) {
            return '<section class="result bad" role="alert"><h2>'.$asked.'</h2><p>'.self::e((string) $result['error']).'</p></section>';
        }
        $found = (bool) $result['found'];
        $html = '<section class="result '.($found ? 'bad' : 'good').'"><h2>'.$asked.': '
            .self::e(__('simplify-explain::explain.'.($found ? 'found' : 'fine'))).'</h2>';
        $html .= '<p>'.self::e((string) $result['summary']).'</p>';
        if ($result['issues'] !== [] && ! $found) {
            $html .= '<p class="note">'.self::e(__('simplify-explain::explain.noticed')).'</p>';
        }
        foreach ($result['issues'] as $issue) {
            $html .= '<details'.($found ? ' open' : '').'><summary>'.self::e($issue['title']).'</summary>';
            if ($issue['cause'] !== '') {
                $html .= '<p>'.self::e($issue['cause']).'</p>';
            }
            if ($issue['steps'] !== []) {
                $html .= '<ol>'.implode('', array_map(fn (string $step) => '<li>'.self::e($step).'</li>', $issue['steps'])).'</ol>';
            }
            $html .= '</details>';
        }
        if ($result['model']) {
            $html .= '<p class="note">'.self::e(trans_choice('simplify-explain::explain.checked_by', $result['lines'], [
                'model' => $result['model'],
                'lines' => $result['lines'],
            ])).'</p>';
        }

        return $html.'</section>';
    }

    private function terminalHint(): string
    {
        return '<p>'.$this->t('terminal', ['command' => '<code>php artisan simplify-explain:check</code>', 'url' => '<code>--url</code>']).'</p>';
    }

    /** This page failed too: show what happened instead of a 500. */
    private function broken(Throwable $error): Response
    {
        try {
            report($error);
        } catch (Throwable) {
            // Even reporting failed; the message below is all there is.
        }
        $message = LogCheck::sanitize([get_class($error).': '.$error->getMessage()])[0] ?? get_class($error);

        return $this->page(200, '<section class="result bad" role="alert"><h2>Explain could not finish this page</h2><p>'
            .self::e($message).'</p></section><p>Run <code>php artisan simplify-explain:check</code> on the panel server.</p>');
    }

    /**
     * A translated text, escaped. Placeholders are escaped too, except the ones this class fills with markup.
     *
     * @param  array<string, string>  $replace
     */
    private function t(string $key, array $replace = []): string
    {
        $markup = ['command', 'url'];
        $escaped = [];
        foreach ($replace as $name => $value) {
            $escaped[$name] = in_array($name, $markup, true) ? $value : self::e($value);
        }
        $text = self::e(__('simplify-explain::emergency.'.$key));
        foreach ($escaped as $name => $value) {
            $text = str_replace(':'.$name, $value, $text);
        }

        return $text;
    }

    private function page(int $status, string $body): Response
    {
        $title = self::e((string) __('simplify-explain::emergency.title'));
        $html = '<!doctype html><html lang="'.self::e(str_replace('_', '-', app()->getLocale())).'"><head><meta charset="utf-8">'
            .'<meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex">'
            .'<title>'.$title.'</title><style>'.self::CSS.'</style></head><body><main><h1>'.$title.'</h1>'.$body.'</main></body></html>';

        return response($html, $status, [
            'Content-Type' => 'text/html; charset=utf-8',
            'Cache-Control' => 'no-store',
            // The temporary address carries its token, so it must not leak through a Referer header or a frame.
            'Referrer-Policy' => 'no-referrer',
            'X-Frame-Options' => 'DENY',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; script-src-attr 'unsafe-inline'; form-action 'self'; frame-ancestors 'none'",
        ]);
    }

    private static function e(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private const CSS = <<<'CSS'
:root{color-scheme:light dark;--bg:#f4f5f7;--card:#fff;--text:#1d2129;--muted:#5d6472;--line:#dde0e6;--accent:#2563eb;--on-accent:#fff;--good:#15803d;--bad:#b45309}
@media (prefers-color-scheme:dark){:root{--bg:#111318;--card:#1a1d24;--text:#e8eaef;--muted:#9aa1ae;--line:#2c313b;--accent:#60a5fa;--on-accent:#0b1220;--good:#4ade80;--bad:#fbbf24}}
*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--text);font:15px/1.5 system-ui,-apple-system,"Segoe UI",sans-serif}
main{max-width:720px;margin:0 auto;padding:32px 16px}h1{font-size:22px;margin:0 0 8px}h2{font-size:16px;margin:0 0 6px}
p{margin:0 0 10px}.lead,.note{color:var(--muted)}.note{font-size:13px}code{font:13px ui-monospace,monospace;background:var(--card);border:1px solid var(--line);border-radius:6px;padding:1px 5px}
.ask{display:flex;flex-wrap:wrap;gap:8px;margin:16px 0}button{font:inherit;font-weight:600;border:1px solid var(--accent);background:var(--accent);color:var(--on-accent);border-radius:8px;padding:9px 14px;cursor:pointer}
button.secondary{background:var(--card);color:var(--text);border-color:var(--line)}.wait{display:none;width:100%;color:var(--muted)}.busy .wait{display:block}.busy button{opacity:.5;pointer-events:none}
.result{background:var(--card);border:1px solid var(--line);border-left:4px solid var(--good);border-radius:8px;padding:16px;margin-top:16px}.result.bad{border-left-color:var(--bad)}
details{border-top:1px solid var(--line);padding:8px 0}summary{font-weight:600;cursor:pointer}ol{margin:6px 0 0;padding-left:20px}
CSS;
}
