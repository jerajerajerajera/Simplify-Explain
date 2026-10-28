<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Takes over the choices and API keys saved by Explain in PelicanSimplify (storage/app/simplify/error-explainer.json),
 * so a panel that used it keeps working. The old file stays where it is.
 */
return new class extends Migration
{
    public function up(): void
    {
        $disk = Storage::disk('local');
        if (DB::table('simplify_explain_settings')->exists() || ! $disk->exists('simplify/error-explainer.json')) {
            return;
        }
        $old = json_decode((string) $disk->get('simplify/error-explainer.json'), true);
        if (! is_array($old)) {
            return;
        }
        $encrypted = array_filter((array) ($old['keys'] ?? []), 'is_string');
        // Before there was a choice of service, the one key belonged to Anthropic.
        if (is_string($old['key'] ?? null) && ! isset($encrypted['anthropic'])) {
            $encrypted['anthropic'] = $old['key'];
        }
        $keys = [];
        foreach (array_intersect_key($encrypted, ['anthropic' => 1, 'openai' => 1, 'gemini' => 1]) as $provider => $value) {
            try {
                $keys[$provider] = (string) decrypt($value);
            } catch (Throwable) {
                // Saved with another APP_KEY: the key has to be entered again.
            }
        }
        DB::table('simplify_explain_settings')->insert([
            'id' => 1,
            'provider' => (string) ($old['provider'] ?? 'anthropic'),
            'model' => (string) ($old['model'] ?? ''),
            'audience' => (string) ($old['audience'] ?? 'everyone'),
            'api_keys' => $keys === [] ? null : Crypt::encryptString(json_encode($keys, JSON_THROW_ON_ERROR)),
            'updated_at' => now(),
        ]);
    }

    public function down(): void {}
};
