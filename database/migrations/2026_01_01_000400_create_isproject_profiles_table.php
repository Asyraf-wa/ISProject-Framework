<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-user profile extras this package owns.
 *
 * A table of its own rather than a column added to the application's users
 * table: that table belongs to the project, may not even be called "users"
 * (config('auth.providers.users.model') can point anywhere), and a package
 * reaching in to alter it is the kind of surprise that is hard to undo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('isproject_profiles', function (Blueprint $table) {
            // One row per user, so the id is the key.
            $table->unsignedBigInteger('user_id')->primary();
            $table->string('avatar')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('isproject_profiles');
    }
};
