<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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

    public function down(): void
    {
        Schema::dropIfExists('simplify_explain_settings');
    }
};
