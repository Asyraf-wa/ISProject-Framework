<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Role-based access control.
 *
 * A permission is one named route. They are not written by hand: the route
 * collection is scanned and synced, so generating a module with
 * `isproject:crud` makes its permissions appear by itself.
 *
 * The user side is a pivot against whatever config('auth.providers.users.model')
 * points at, so the column is a plain unsigned big integer rather than a
 * foreign key to a table this package does not own.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('isproject_roles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('description')->nullable();

            // The escape hatch. A super admin passes every check without
            // holding any permission row, so a misconfigured matrix can never
            // lock the last administrator out of the screen that fixes it.
            $table->boolean('is_super_admin')->default(false);
            $table->timestamps();
        });

        Schema::create('isproject_permissions', function (Blueprint $table) {
            $table->id();
            // The route name, e.g. "products.index".
            $table->string('name', 191)->unique();
            $table->timestamps();
        });

        Schema::create('isproject_permission_role', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained('isproject_roles')->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('isproject_permissions')->cascadeOnDelete();
            $table->primary(['role_id', 'permission_id']);
        });

        Schema::create('isproject_role_user', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained('isproject_roles')->cascadeOnDelete();
            $table->unsignedBigInteger('user_id')->index();
            $table->primary(['role_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('isproject_role_user');
        Schema::dropIfExists('isproject_permission_role');
        Schema::dropIfExists('isproject_permissions');
        Schema::dropIfExists('isproject_roles');
    }
};
