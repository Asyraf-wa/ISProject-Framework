<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Site configuration, one row per setting.
 *
 * Prefixed "isproject_" so it never collides with a settings table of the
 * student's own. Values are stored as plain strings and cast back on read from
 * the field definitions in config/isproject.php — the schema lives in one
 * place, so there is no type column to keep in step.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('isproject_settings', function (Blueprint $table) {
            // 191, not the default 255: keeps the index inside the limit on
            // older MySQL builds using utf8mb4 without an index prefix.
            $table->string('key', 191)->primary();
            $table->text('value')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('isproject_settings');
    }
};
