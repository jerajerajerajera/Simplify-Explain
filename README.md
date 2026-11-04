<img src="docs/logo.png" alt="" width="96">

# Simplify Explain

A plugin for the [Pelican panel](https://pelican.dev) that explains logs in plain words. Press **Explain** on a
server's console and it first checks whether something is wrong. When it is, you get the likely cause and steps to
fix it. Root admins can also have the panel log and the Wings log of a node explained. Wherever Simplify, its add-ons
or another plugin show that something failed, a small question button right after the message explains that error.

It works on its own. With [Simplify](https://github.com/jerajerajerajera/Simplify) installed, it also shows up in
Simplify's add-on list, and Simplify's setup asks for the service, the key and who may use it. With Simplify Hosting
installed and a support link set in Simplify's settings, customers also get **Open a ticket** under a problem.

## Setup

1. Install the plugin from the Pelican Hub.
2. Open **Simplify settings › Add-ons › Explain** (with Simplify), or **Admin → Explain** without it.
3. Choose Claude, GPT or Gemini and paste your own API key, or choose Ollama and fill in the address and model of
   your own Ollama server. Press **Test**.
4. Choose who can use it: everyone who can see a console, or administrators only.

## When the panel shows an error

When the panel itself breaks, for example with a 500 error, its normal pages may not open. Explain has three ways
around that, all for root admins:

- The plain page **`/simplify-explain/emergency`** checks the panel log or the Wings log of a node. It works while
  you are still signed in, and does not need Filament or the add-on list.
- On the panel server, run `php artisan simplify-explain:check`. It checks the panel log and prints the answer.
  Use `--source=wings --node=<id>` for a Wings log or `--source=server=<id>` for a server console. Without a saved
  key it asks for one, uses it once and does not save it.
- `php artisan simplify-explain:check --url` prints a temporary address to the emergency page that works for 60
  minutes without signing in. A new address replaces the old one; only a hash of it is kept, in
  `simplify-explain/emergency-access.json` on the panel's local storage disk.

## Explain your plugin's errors

Any Pelican plugin can put Explain's question button after its own error messages. Your plugin does not need Explain
or Simplify for this: without Explain nothing renders and nothing is called.

**1. Mark the error in your view**, right after the error text, while the error is shown:

```blade
{{ \Filament\Support\Facades\FilamentView::renderHook('simplify::problem.actions', scopes: ['simplify-problem', 'simplify-problem:'.$problemId]) }}
```

`$problemId` is `<your-plugin-id>:<kind>:<reference>`, for example `my-plugin:backup:42`: two to five parts of letters,
digits, `.`, `-` or `_`, at most 128 characters.

Explain renders a small plain button there (`data-simplify-explain="<id>"`) and opens its window from one helper per
page, so the button holds no state. When your page draws its errors in the browser, render the hook once with
`__ID__` as the reference (`my-plugin:backup:__ID__`) and replace `__ID__` in that HTML with the real reference
wherever the error shows. Explain then shows the button whenever Explain is set up for the user, and your source
checks the real id on the click.

**2. Give the log that belongs to it.** Tag a class in your service provider's `register()`:

```php
$this->app->tag([MyPluginProblems::class], 'simplify.problems');
```

The class has two public methods (no interface to implement):

```php
/** Called on every render of the spot, so keep it cheap: is this id yours, and may this user see the error? */
public function canExplainProblem(string $id, \App\Models\User $user): bool
{
    $backup = str_starts_with($id, 'my-plugin:backup:') ? MyBackup::find((int) substr($id, 17)) : null;

    return $backup?->failed() === true && $user->can('view', $backup);
}

/**
 * Called only after a click.
 *
 * @return array{title: string, context: string, lines: list<string>}|null
 */
public function explainProblem(string $id, \App\Models\User $user): ?array
{
    if (! $this->canExplainProblem($id, $user)) {
        return null;
    }

    return [
        'title' => __('my-plugin::backup.explain_title'),          // the window's heading, translated
        'context' => 'A backup of a game server failed to upload.', // one or two English sentences for the AI
        'lines' => MyBackup::find((int) substr($id, 17))->logLines(), // the log, oldest first, at most 100 lines
    ];
}
```

- Read the lines on the panel, never from the browser, and check the user in both methods. Give panel or Wings log
  lines only to root admins.
- Explain adds its own checks: a saved key, the "who may use it" setting and the limits per person and per panel.
  Before anything is sent it removes addresses, e-mail addresses, tokens and passwords.
- A Filament notification strips the hook's HTML. Add Explain's button as a notification action instead; it is
  `null` when the user cannot have the error explained:

  ```php
  $explain = class_exists(\Simplify\Explain\Problems\Problems::class)
      ? \Simplify\Explain\Problems\Problems::notificationAction($problemId)
      : null;
  Notification::make()->danger()->title($message)->actions(array_filter([$explain]))->send();
  ```
- Simplify's Health page uses this with ids like `simplify:health:queue`.

## Routes

| Route | Use |
| --- | --- |
| `/simplify-explain/emergency` | Emergency page, signed-in root admins |
| `/simplify-explain/emergency/{token}` | Emergency page with a temporary address from the terminal |

The Explain buttons themselves sit on Pelican's own Console, Nodes and Logs pages and add no routes.

## Outside services

Explain only contacts a service after a root admin saved an API key or an Ollama address, and only when someone
presses Explain or Test. It then sends the last log lines (at most 100) to the chosen service:

| Service | Address |
| --- | --- |
| Claude by Anthropic | `api.anthropic.com` |
| GPT by OpenAI | `api.openai.com` (with `store: false`) |
| Gemini by Google | `generativelanguage.googleapis.com` |
| Ollama | the address of your own Ollama server (no key) |

Before sending, IP addresses, e-mail addresses, passwords, tokens and long secrets are replaced with `[hidden]`.
The API key is stored encrypted with the panel's `APP_KEY` and never shown again. There is no telemetry.

## Updates

Updates come from the Pelican Hub only.

## Development

- `bash tests/run.sh` runs the checks that need no panel, including `tests/hub-check.py`.
- `pint` formats the code (Laravel preset).
- `python3 scripts/build-package.py` builds the zip for the Hub.

## License

Source available, see [LICENSE.md](LICENSE.md). Copyright (c) 2026 Jeroen (jerajerajerajera).
