<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The sidebar menu, once somebody wants to manage it from the browser.
 *
 * An empty table means "not managed here": the sidebar keeps reading
 * config('isproject.menu'), exactly as before. The screen offers to copy that
 * config in as a starting point, and deleting every row hands control back. So
 * installing this migration changes nothing until a choice is made.
 *
 * Depth is capped at two levels by the application, not by the schema: a third
 * level of nesting is unusable in a 264px rail, and parent_id is enough to
 * describe the tree either way.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('isproject_menu_items', function (Blueprint $table) {
            $table->id();

            // A deleted parent takes its children with it. The alternative —
            // orphans silently promoted to the top level — is worse: rows
            // reappear somewhere unexpected instead of going where they were sent.
            $table->foreignId('parent_id')->nullable()
                ->constrained('isproject_menu_items')->cascadeOnDelete();

            // heading | route | internal | external
            $table->string('type', 20)->default('route');

            $table->string('label');
            $table->string('icon', 50)->nullable();

            // A named route, resolved at render time. Kept as a string rather
            // than a foreign key to anything: the route may not exist yet, and
            // an entry naming a module nobody has generated is simply skipped.
            $table->string('route_name', 191)->nullable();

            // For internal ("/reports") and external ("https://...") links.
            $table->string('url')->nullable();

            // The permission that must be held for this row to appear, as a
            // route name — the same identifier RBAC uses.
            $table->string('permission', 191)->nullable();

            $table->string('badge', 30)->nullable();
            $table->boolean('opens_in_new_tab')->default(false);

            // Switched off rather than deleted: keeps the row, its icon and its
            // place while a module is being worked on.
            $table->boolean('is_active')->default(true);

            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['parent_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('isproject_menu_items');
    }
};
