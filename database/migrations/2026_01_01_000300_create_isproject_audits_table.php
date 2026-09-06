<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per recorded change.
 *
 * The actor is stored twice on purpose: user_id to link back while the account
 * exists, and user_label as a snapshot of who they were at the time. An audit
 * trail that reads "user #7 deleted the invoice" after user 7 has been removed
 * has lost the thing it was kept for.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('isproject_audits', function (Blueprint $table) {
            $table->id();

            $table->string('event', 20);
            $table->string('auditable_type');
            $table->string('auditable_id', 191);

            // A short human description of the record, taken at write time, so
            // the list can say "Product: Blue Mug" without loading a row that
            // may since have been deleted.
            $table->string('auditable_label')->nullable();

            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_label')->nullable();

            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();

            $table->string('url', 1000)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();

            $table->timestamp('created_at')->nullable();

            // The three questions this table gets asked: what happened to this
            // record, what has this person done, and what happened recently.
            $table->index(['auditable_type', 'auditable_id'], 'isproject_audits_subject_index');
            $table->index('user_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('isproject_audits');
    }
};
