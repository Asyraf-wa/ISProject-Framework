<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Links an external identity (currently Google) to a local account.
 *
 * Kept in its own table rather than as a google_id column on users: this
 * package does not own the application's users table, and a table takes another
 * provider later without a second migration against somebody else's schema.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('isproject_social_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 40);

            // Google's "sub" claim. Stable for the life of the account, unlike
            // the email address, so this is what the link is keyed on.
            $table->string('provider_id', 191);

            $table->unsignedBigInteger('user_id')->index();
            $table->string('email')->nullable();
            $table->timestamps();

            // One identity cannot be attached to two local accounts.
            $table->unique(['provider', 'provider_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('isproject_social_accounts');
    }
};
