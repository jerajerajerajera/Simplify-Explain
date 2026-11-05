<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Kept by an uninstall (see down()) or left by an install that stopped halfway: keep it as it is.
        if (Schema::hasTable('simplify_explain_settings')) {
            return;
        }
        Schema::create('simplify_explain_settings', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 32);
            $table->string('model', 64);
            $table->string('audience', 16);
            // JSON of service id => API key, encrypted with the panel's APP_KEY.
            $table->text('api_keys')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    /**
     * Uninstalling keeps the settings, like every Simplify plugin (decided 4 Nov 2026): Pelican rolls back a plugin's
     * migrations on uninstall, and installing again should bring everything back. The table stays until someone drops
     * it by hand.
     */
    public function down(): void {}
};
