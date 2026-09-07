#!/usr/bin/env bash
#
# Creates ./sandbox — a real Laravel application that consumes this package
# through a Composer path repository, so editing src/ or stubs/ is immediately
# reflected in the running app.
#
#   docker compose exec app bash /var/www/docker/bin/init-sandbox.sh
#   docker compose exec app bash /var/www/docker/bin/init-sandbox.sh --demo
#
# --demo also creates categories/products tables and generates CRUD for them,
# which is the fastest way to see the generator working end to end.

set -euo pipefail

ROOT=/var/www
APP="$ROOT/sandbox"
DEMO=false

for arg in "$@"; do
    [ "$arg" = "--demo" ] && DEMO=true
done

# Set (or replace) a key in the sandbox .env, whether or not it is commented.
set_env() {
    local key="$1" value="$2" file="$APP/.env"
    sed -i "/^#\? \?${key}=/d" "$file"
    echo "${key}=${value}" >> "$file"
}

# ---------------------------------------------------------------- application

CREATED=false

if [ ! -f "$APP/artisan" ]; then
    echo "==> Creating a fresh Laravel application in ./sandbox"
    composer create-project laravel/laravel "$APP" --no-interaction
    CREATED=true
else
    echo "==> ./sandbox already exists, reusing it"
fi

cd "$APP"

echo "==> Wiring the path repository to ../ (isproject/framework)"
composer config repositories.isproject --json '{"type":"path","url":"../","options":{"symlink":true}}'
composer require isproject/framework:@dev --no-interaction -W

# ------------------------------------------------------------------ .env

echo "==> Pointing .env at the container services"
set_env APP_NAME "IsProject"
set_env APP_URL "http://localhost:8080"
set_env DB_CONNECTION mysql
set_env DB_HOST db
set_env DB_PORT 3306
set_env DB_DATABASE "${DB_DATABASE:-isproject}"
set_env DB_USERNAME "${DB_USERNAME:-isproject}"
set_env DB_PASSWORD "${DB_PASSWORD:-secret}"
set_env REDIS_HOST redis
set_env MAIL_MAILER smtp
set_env MAIL_HOST mailpit
set_env MAIL_PORT 1025

php artisan key:generate --force

# ------------------------------------------------------------------ dashboard
# Authentication itself comes from the framework — sign in, sign out, forgotten
# passwords, profile and the optional Google button are all registered by the
# package. Only the landing page is the application's own.

if ! grep -q "'/', 'dashboard'" routes/web.php; then
    echo "==> Adding the dashboard route"

    cat >> routes/web.php <<'PHP'

Route::view('/', 'dashboard')->middleware('auth');
PHP


    cat > resources/views/dashboard.blade.php <<'BLADE'
@extends('isproject::layouts.app')

@section('title', 'Dashboard')

@section('content')
    <div class="is-page-header">
        <div>
            <span class="is-page-eyebrow">Overview</span>
            <h2 class="is-page-title">Dashboard</h2>
        </div>
    </div>

    @include('isproject::partials.alerts')

    <div class="card">
        <div class="card-body">
            <h6 class="mb-2">Welcome to the IsProject framework</h6>
            <p class="text-body-secondary mb-3">
                Write a migration, run <code>php artisan migrate</code>, then
                <code>php artisan isproject:crud YourModel</code>. The generator prints the
                line to paste into the sidebar menu in <code>config/isproject.php</code>.
            </p>
            <div class="d-flex flex-wrap gap-2">
                <span class="badge badge-soft-primary">Responsive sidebar</span>
                <span class="badge badge-soft-success">Dark mode</span>
                <span class="badge badge-soft-secondary">Self-hosted assets</span>
            </div>
        </div>
    </div>
@endsection
BLADE
fi

# ---------------------------------------------------------------- database

if [ "$CREATED" = true ]; then
    # A brand-new sandbox owns the schema. Dropping first keeps re-runs
    # repeatable when ./sandbox was deleted but the database volume kept.
    echo "==> Preparing a clean schema"
    php artisan migrate:fresh --force
else
    echo "==> Running migrations"
    php artisan migrate --force
fi

echo "==> Publishing the framework assets and config"
# Views and stubs stay unpublished: a published copy is a frozen snapshot, and
# the sandbox exists to exercise the package as you edit it. Config is the
# exception — the sidebar menu lives there and the demo below edits it — so it
# is re-published with --force on every run to stay in step with the package.
php artisan isproject:install --force

# --------------------------------------------------------------------- demo

if [ "$DEMO" = true ]; then
    echo "==> Creating demo tables and generating CRUD"

    STAMP=$(date +%Y_%m_%d_%H%M%S)

    cat > "database/migrations/${STAMP}_create_categories_table.php" <<'PHP'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
PHP

    sleep 1
    STAMP=$(date +%Y_%m_%d_%H%M%S)

    cat > "database/migrations/${STAMP}_create_products_table.php" <<'PHP'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained();
            $table->string('sku', 32)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2);
            $table->unsignedInteger('stock')->default(0);
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->date('available_from')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
PHP

    php artisan migrate --force

    php artisan isproject:crud Category --force
    php artisan isproject:crud Product --force

    echo "==> Adding the demo modules to the sidebar menu"

    cat > /tmp/isproject-menu.php <<'PHP'
<?php
// Inserts the demo modules under the "Manage" heading in the published config.
$file = 'config/isproject.php';
$config = file_get_contents($file);
$anchor = "['heading' => 'Manage'],";

if (! str_contains($config, 'categories.index') && str_contains($config, $anchor)) {
    $entries = $anchor."\n"
        ."        ['label' => 'Categories', 'icon' => 'list', 'route' => 'categories.index'],\n"
        ."        ['label' => 'Products', 'icon' => 'box', 'route' => 'products.index'],";

    file_put_contents($file, str_replace($anchor, $entries, $config));
    echo "    menu updated\n";
}
PHP

    php /tmp/isproject-menu.php
    rm -f /tmp/isproject-menu.php
fi

# ------------------------------------------------------------------ account

echo "==> Ensuring a demo login exists"
php artisan tinker --execute="
    \App\Models\User::updateOrCreate(
        ['email' => 'admin@example.test'],
        ['name' => 'Administrator', 'password' => bcrypt('password')]
    );
"

# ---------------------------------------------------------------------- rbac

# The trait is what gives the application's own User model roles. Added here
# rather than left as a manual step, so the sandbox has working RBAC out of
# the box; a real project does the same edit once by hand.
if ! grep -q 'HasRoles' app/Models/User.php; then
    echo "==> Adding HasRoles to App\\Models\\User"
    perl -0pi -e "s/use Illuminate\\\\Notifications\\\\Notifiable;/use Illuminate\\\\Notifications\\\\Notifiable;\nuse IsProject\\\\Framework\\\\Concerns\\\\HasRoles;/" app/Models/User.php
    perl -0pi -e "s/use HasFactory, Notifiable;/use HasFactory, HasRoles, Notifiable;/" app/Models/User.php
fi

echo "==> Scanning routes into the permission matrix"
php artisan isproject:permissions --admin=Administrator --user=admin@example.test 2>&1 | tail -4

php artisan optimize:clear

cat <<'DONE'

  Ready.

    URL       http://localhost:8080
    Login     admin@example.test / password  (Administrator, full access)
    Mailpit   http://localhost:8025

  Generate more CRUD with:

    docker compose exec app php artisan isproject:crud YourModel
    docker compose exec app php artisan isproject:crud-all

DONE
