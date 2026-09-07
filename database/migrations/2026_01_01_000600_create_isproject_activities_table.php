<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What people did, as opposed to what changed.
 *
 * The audit trail answers "who edited this invoice". This answers "who signed
 * in, from where, and what did they fail to sign in as" — the questions asked
 * after an account is misused rather than after a record is wrong. Keeping them
 * apart means neither list is buried in the other.
 *
 * user_id is nullable because the rows that matter most have no user: a failed
 * sign-in is an attempt on an account by somebody who is not in it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('isproject_activities', function (Blueprint $table) {
            $table->id();

            $table->string('event', 40);
            $table->string('description');

            // Stored twice, as in the audit trail: the id to link back while
            // the account exists, the label as a snapshot of who they were.
            // "user #7 signed in" is useless once user 7 has been deleted.
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_label')->nullable();

            // Optional link to whatever the activity was about.
            $table->string('subject_type')->nullable();
            $table->string('subject_id', 191)->nullable();

            $table->json('properties')->nullable();

            $table->string('url', 1000)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();

            $table->timestamp('created_at')->nullable();

            // The three questions asked of this table: what has this person
            // done, how often does this event happen, and what happened
            // recently. The last one also drives the dashboard charts.
            $table->index('user_id');
            $table->index('event');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('isproject_activities');
    }
};
