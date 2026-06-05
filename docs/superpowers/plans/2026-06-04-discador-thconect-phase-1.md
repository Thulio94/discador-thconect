# Discador THCONECT Phase 1 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the independent Laravel SaaS foundation for Discador THCONECT with Docker, THCONECT design system, tenants, licensing, users with extensions, RBAC, flexible teams, premium notifications, and modal-first admin screens.

**Architecture:** Create a new Laravel app in `DISCADOR THCONECT` while preserving existing docs. Use MySQL/MariaDB, Redis, queues, Reverb-ready frontend assets, Blade/Tailwind/Alpine, and a SaaS data model isolated by `company_id`. Keep telephony and campaign runtime out of Phase 1 except for fields needed by licensing and navigation.

**Tech Stack:** PHP 8.3, Laravel 13, Laravel Breeze, Laravel Reverb, Livewire, Tailwind CSS, Alpine.js, MySQL/MariaDB, Redis, Docker Compose, PHPUnit.

---

## File Structure

Create or modify these files inside `C:\Users\Thulio\Documents\TRABALHO\PROJETOS\CRM_TELECOM\DISCADOR THCONECT`.

- Create: `.docker/app/Dockerfile`
- Create: `.docker/nginx/default.conf`
- Create: `.docker/entrypoint.sh`
- Create: `docker-compose.yml`
- Create: `.env.example`
- Create: Laravel app scaffold files from `composer create-project`
- Modify: `composer.json`
- Modify: `package.json`
- Modify: `routes/web.php`
- Modify: `app/Models/User.php`
- Create: `app/Models/Company.php`
- Create: `app/Models/CompanyLicense.php`
- Create: `app/Models/Role.php`
- Create: `app/Models/Permission.php`
- Create: `app/Models/Team.php`
- Create: `app/Models/AppNotification.php`
- Create: `app/Http/Middleware/EnsureTenantIsActive.php`
- Create: `app/Http/Middleware/EnsureTenantUser.php`
- Create: `app/Http/Middleware/EnsureUserIsSuperAdmin.php`
- Create: `app/Http/Middleware/ApplySecurityHeaders.php`
- Modify: `bootstrap/app.php`
- Create: `app/Support/Tenant/PermissionMatrix.php`
- Create: `app/Support/Notifications/AppNotificationService.php`
- Create: `app/Events/AppNotificationCreated.php`
- Create: `database/migrations/*_create_companies_table.php`
- Create: `database/migrations/*_create_company_licenses_table.php`
- Create: `database/migrations/*_create_roles_table.php`
- Create: `database/migrations/*_create_permissions_table.php`
- Create: `database/migrations/*_create_role_permissions_table.php`
- Create: `database/migrations/*_add_tenant_fields_to_users_table.php`
- Create: `database/migrations/*_create_teams_table.php`
- Create: `database/migrations/*_create_team_user_table.php`
- Create: `database/migrations/*_create_app_notifications_table.php`
- Create: `database/seeders/PermissionSeeder.php`
- Create: `database/seeders/SuperAdminSeeder.php`
- Create: `database/seeders/DemoTenantSeeder.php`
- Create: `resources/css/thconect-tokens.css`
- Create: `resources/css/thconect-components.css`
- Modify: `resources/css/app.css`
- Modify: `resources/js/app.js`
- Create: `resources/js/notifications.js`
- Create: `resources/views/layouts/app.blade.php`
- Create: `resources/views/components/ui/button.blade.php`
- Create: `resources/views/components/ui/card.blade.php`
- Create: `resources/views/components/ui/action.blade.php`
- Create: `resources/views/components/ui/icon.blade.php`
- Create: `resources/views/components/ui/modal.blade.php`
- Create: `resources/views/components/ui/toast-stack.blade.php`
- Create: `resources/views/components/ui/notification-panel.blade.php`
- Create: `resources/views/dashboard.blade.php`
- Create: `resources/views/super-admin/companies/index.blade.php`
- Create: `resources/views/super-admin/companies/_form.blade.php`
- Create: `resources/views/tenant/users/index.blade.php`
- Create: `resources/views/tenant/users/_form.blade.php`
- Create: `resources/views/tenant/teams/index.blade.php`
- Create: `resources/views/tenant/teams/_form.blade.php`
- Create: `resources/views/tenant/roles/index.blade.php`
- Create: `resources/views/tenant/roles/_form.blade.php`
- Create: `resources/views/tenant/coming-soon/integrations.blade.php`
- Create: controllers under `app/Http/Controllers/SuperAdmin` and `app/Http/Controllers/Tenant`
- Create: tests under `tests/Feature`

---

### Task 1: Bootstrap Laravel Without Losing Docs

**Files:**
- Create: Laravel framework files in project root
- Preserve: `docs/**`

- [ ] **Step 1: Confirm target directory**

Run:

```powershell
Set-Location 'C:\Users\Thulio\Documents\TRABALHO\PROJETOS\CRM_TELECOM\DISCADOR THCONECT'
Get-ChildItem -Force
```

Expected: directory contains `docs`.

- [ ] **Step 2: Create Laravel in temporary scaffold directory**

Run:

```powershell
composer create-project laravel/laravel .scaffold-laravel
```

Expected: `.scaffold-laravel` exists with `artisan`, `composer.json`, `app`, `routes`, `resources`, and `database`.

- [ ] **Step 3: Copy scaffold into root while preserving docs**

Run:

```powershell
Get-ChildItem -Force '.scaffold-laravel' | ForEach-Object {
    Copy-Item -Path $_.FullName -Destination . -Recurse -Force
}
Remove-Item -Recurse -Force '.scaffold-laravel'
```

Expected: project root contains `artisan`, `composer.json`, `app`, `routes`, `resources`, `database`, and existing `docs`.

- [ ] **Step 4: Initialize git repository**

Run:

```powershell
git init
git add docs composer.json package.json artisan app bootstrap config database public resources routes tests
git commit -m "chore: scaffold Laravel discador app"
```

Expected: commit succeeds. If Git user identity is not configured, set it locally and rerun:

```powershell
git config user.name "Thulio"
git config user.email "thulio@example.local"
git commit -m "chore: scaffold Laravel discador app"
```

---

### Task 2: Install Laravel Packages

**Files:**
- Modify: `composer.json`
- Modify: `package.json`

- [ ] **Step 1: Install backend packages**

Run:

```powershell
composer require laravel/reverb livewire/livewire phpoffice/phpspreadsheet predis/predis
composer require --dev laravel/breeze laravel/pint
php artisan breeze:install blade
```

Expected: Composer installs packages and Breeze publishes auth views/routes.

- [ ] **Step 2: Install frontend packages**

Run:

```powershell
npm install
npm install axios laravel-echo pusher-js
```

Expected: `node_modules` exists and `package-lock.json` is updated.

- [ ] **Step 3: Commit dependencies**

Run:

```powershell
git add composer.json composer.lock package.json package-lock.json resources routes app
git commit -m "chore: install Laravel SaaS dependencies"
```

Expected: commit succeeds.

---

### Task 3: Add Docker Desktop Environment

**Files:**
- Create: `.docker/app/Dockerfile`
- Create: `.docker/nginx/default.conf`
- Create: `.docker/entrypoint.sh`
- Create: `docker-compose.yml`
- Modify: `.env.example`

- [ ] **Step 1: Write Dockerfile**

Create `.docker/app/Dockerfile`:

```dockerfile
FROM php:8.3-fpm

RUN apt-get update \
    && apt-get install -y git curl unzip libzip-dev libpng-dev libonig-dev libxml2-dev \
    && docker-php-ext-install pdo_mysql mbstring zip exif pcntl bcmath gd \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY .docker/entrypoint.sh /usr/local/bin/discador-entrypoint
RUN chmod +x /usr/local/bin/discador-entrypoint

ENTRYPOINT ["discador-entrypoint"]
CMD ["php-fpm"]
```

- [ ] **Step 2: Write entrypoint**

Create `.docker/entrypoint.sh`:

```sh
#!/usr/bin/env sh
set -e

if [ ! -f .env ] && [ -f .env.example ]; then
    cp .env.example .env
fi

if [ -f artisan ] && [ -z "$APP_KEY" ]; then
    php artisan key:generate --force >/dev/null 2>&1 || true
fi

exec "$@"
```

- [ ] **Step 3: Write nginx config**

Create `.docker/nginx/default.conf`:

```nginx
server {
    listen 80;
    index index.php index.html;
    root /var/www/html/public;

    client_max_body_size 25M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass app:9000;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT $realpath_root;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

- [ ] **Step 4: Write docker-compose**

Create `docker-compose.yml`:

```yaml
services:
  app:
    build:
      context: .
      dockerfile: .docker/app/Dockerfile
    container_name: discador_thconect_app
    working_dir: /var/www/html
    volumes:
      - .:/var/www/html
    depends_on:
      - mysql
      - redis

  nginx:
    image: nginx:1.27-alpine
    container_name: discador_thconect_nginx
    ports:
      - "${APP_PORT:-8087}:80"
    volumes:
      - .:/var/www/html
      - ./.docker/nginx/default.conf:/etc/nginx/conf.d/default.conf:ro
    depends_on:
      - app

  mysql:
    image: mariadb:11.4
    container_name: discador_thconect_mysql
    ports:
      - "${MYSQL_FORWARD_PORT:-3317}:3306"
    environment:
      MYSQL_DATABASE: "${DB_DATABASE:-discador_thconect}"
      MYSQL_USER: "${DB_USERNAME:-discador}"
      MYSQL_PASSWORD: "${DB_PASSWORD:-discador_secret}"
      MYSQL_ROOT_PASSWORD: "${DB_ROOT_PASSWORD:-root_secret}"
    volumes:
      - discador_mysql:/var/lib/mysql

  redis:
    image: redis:7-alpine
    container_name: discador_thconect_redis
    ports:
      - "${REDIS_FORWARD_PORT:-6387}:6379"
    volumes:
      - discador_redis:/data

  queue:
    build:
      context: .
      dockerfile: .docker/app/Dockerfile
    container_name: discador_thconect_queue
    command: php artisan queue:work --tries=3 --timeout=90
    working_dir: /var/www/html
    volumes:
      - .:/var/www/html
    depends_on:
      - app
      - redis

  reverb:
    build:
      context: .
      dockerfile: .docker/app/Dockerfile
    container_name: discador_thconect_reverb
    command: php artisan reverb:start --host=0.0.0.0 --port=8080
    working_dir: /var/www/html
    ports:
      - "${REVERB_PORT:-8097}:8080"
    volumes:
      - .:/var/www/html
    depends_on:
      - app
      - redis

volumes:
  discador_mysql:
  discador_redis:
```

- [ ] **Step 5: Update `.env.example` Docker defaults**

Add/update:

```env
APP_NAME="Discador THCONECT"
APP_PORT=8087
VITE_PORT=5187
MYSQL_FORWARD_PORT=3317
REDIS_FORWARD_PORT=6387
REVERB_PORT=8097

DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=discador_thconect
DB_USERNAME=discador
DB_PASSWORD=discador_secret
DB_ROOT_PASSWORD=root_secret

CACHE_STORE=redis
QUEUE_CONNECTION=redis
REDIS_CLIENT=predis
REDIS_HOST=redis
REDIS_PASSWORD=null
REDIS_PORT=6379

BROADCAST_CONNECTION=reverb
REVERB_APP_ID=discador-local
REVERB_APP_KEY=discador-local-key
REVERB_APP_SECRET=discador-local-secret
REVERB_HOST=localhost
REVERB_PORT=8097
REVERB_SCHEME=http
VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

- [ ] **Step 6: Check ports before first up**

Run:

```powershell
$ports = 8087,5187,3317,6387,8097
foreach ($port in $ports) {
    $busy = Get-NetTCPConnection -LocalPort $port -ErrorAction SilentlyContinue
    if ($busy) { Write-Host "BUSY $port" } else { Write-Host "FREE $port" }
}
```

Expected: all ports show `FREE`. If any port is busy, edit `.env` after copying `.env.example` and choose another free port.

- [ ] **Step 7: Build Docker**

Run:

```powershell
copy .env.example .env
docker compose build
docker compose up -d
docker compose exec app composer install
docker compose exec app php artisan key:generate
```

Expected: containers run in Docker Desktop.

- [ ] **Step 8: Commit Docker environment**

Run:

```powershell
git add .docker docker-compose.yml .env.example
git commit -m "chore: add Docker Desktop environment"
```

Expected: commit succeeds.

---

### Task 4: Create SaaS Migrations and Models

**Files:**
- Create: models and migrations listed in File Structure
- Modify: `app/Models/User.php`

- [ ] **Step 1: Create migration files**

Run:

```powershell
php artisan make:model Company -m
php artisan make:model CompanyLicense -m
php artisan make:model Role -m
php artisan make:model Permission -m
php artisan make:model Team -m
php artisan make:model AppNotification -m
php artisan make:migration create_role_permissions_table
php artisan make:migration add_tenant_fields_to_users_table
php artisan make:migration create_team_user_table
```

Expected: migrations and models exist.

- [ ] **Step 2: Implement table schemas**

Use these fields:

```php
// companies
$table->id();
$table->string('name');
$table->string('document')->nullable();
$table->string('status')->default('active');
$table->timestamps();

// company_licenses
$table->id();
$table->foreignId('company_id')->constrained()->cascadeOnDelete();
$table->string('status')->default('active');
$table->date('starts_at');
$table->date('ends_at');
$table->unsignedInteger('max_users')->default(10);
$table->unsignedInteger('max_concurrent_agents')->nullable();
$table->unsignedInteger('max_calls_per_available_agent')->default(1);
$table->unsignedInteger('max_concurrent_calls')->nullable();
$table->boolean('power_enabled')->default(true);
$table->boolean('predictive_enabled')->default(false);
$table->boolean('preview_enabled')->default(true);
$table->boolean('amd_enabled')->default(false);
$table->timestamps();

// roles
$table->id();
$table->foreignId('company_id')->nullable()->constrained()->cascadeOnDelete();
$table->string('name');
$table->string('slug');
$table->boolean('is_admin')->default(false);
$table->timestamps();
$table->unique(['company_id', 'slug']);

// permissions
$table->id();
$table->string('key')->unique();
$table->string('name');
$table->string('module');
$table->timestamps();

// role_permissions
$table->id();
$table->foreignId('role_id')->constrained()->cascadeOnDelete();
$table->foreignId('permission_id')->constrained()->cascadeOnDelete();
$table->timestamps();
$table->unique(['role_id', 'permission_id']);

// users tenant fields
$table->foreignId('company_id')->nullable()->after('id')->constrained()->nullOnDelete();
$table->foreignId('role_id')->nullable()->after('company_id')->constrained()->nullOnDelete();
$table->string('user_type')->default('agent')->after('role_id');
$table->string('extension')->nullable()->after('phone');
$table->string('status')->default('active')->after('extension');
$table->boolean('is_super_admin')->default(false)->after('status');
$table->string('theme_preference')->nullable()->after('is_super_admin');
$table->unsignedInteger('items_per_page')->default(15)->after('theme_preference');
$table->unique(['company_id', 'extension']);

// teams
$table->id();
$table->foreignId('company_id')->constrained()->cascadeOnDelete();
$table->string('name');
$table->string('slug');
$table->string('status')->default('active');
$table->timestamps();
$table->unique(['company_id', 'slug']);

// team_user
$table->id();
$table->foreignId('team_id')->constrained()->cascadeOnDelete();
$table->foreignId('user_id')->constrained()->cascadeOnDelete();
$table->string('membership_type');
$table->timestamps();
$table->unique(['team_id', 'user_id', 'membership_type']);

// app_notifications
$table->id();
$table->foreignId('company_id')->nullable()->constrained()->cascadeOnDelete();
$table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
$table->string('type')->default('system');
$table->string('severity')->default('info');
$table->string('title');
$table->text('message');
$table->string('action_url')->nullable();
$table->timestamp('read_at')->nullable();
$table->timestamps();
$table->index(['company_id', 'user_id', 'read_at', 'created_at']);
```

- [ ] **Step 3: Implement model relationships**

Use these relationship names exactly:

```php
// User
public const TYPES = ['agent', 'supervisor', 'company_admin', 'super_admin'];
public const THEME_PREFERENCES = ['thconectclaro', 'thconectescuro'];

public function company(): BelongsTo { return $this->belongsTo(Company::class); }
public function role(): BelongsTo { return $this->belongsTo(Role::class); }
public function teams(): BelongsToMany { return $this->belongsToMany(Team::class)->withPivot('membership_type')->withTimestamps(); }

// Company
public function license(): HasOne { return $this->hasOne(CompanyLicense::class)->latestOfMany(); }
public function users(): HasMany { return $this->hasMany(User::class); }
public function teams(): HasMany { return $this->hasMany(Team::class); }

// Role
public function permissions(): BelongsToMany { return $this->belongsToMany(Permission::class, 'role_permissions')->withTimestamps(); }

// Team
public function users(): BelongsToMany { return $this->belongsToMany(User::class)->withPivot('membership_type')->withTimestamps(); }
public function agents(): BelongsToMany { return $this->users()->wherePivot('membership_type', 'agent'); }
public function supervisors(): BelongsToMany { return $this->users()->wherePivot('membership_type', 'supervisor'); }
```

- [ ] **Step 4: Add permission helper to User**

Add:

```php
public function hasPermission(string $permissionKey): bool
{
    if ($this->is_super_admin) {
        return true;
    }

    $role = $this->role;

    if (! $role || $role->company_id !== $this->company_id) {
        return false;
    }

    $role->loadMissing('permissions');

    return $role->permissions->contains('key', $permissionKey);
}
```

- [ ] **Step 5: Run migration test**

Run:

```powershell
php artisan migrate:fresh
```

Expected: all migrations pass.

- [ ] **Step 6: Commit SaaS data model**

Run:

```powershell
git add app/Models database/migrations
git commit -m "feat: add SaaS tenant data model"
```

Expected: commit succeeds.

---

### Task 5: Add RBAC Matrix, Gates, and Seeders

**Files:**
- Create: `app/Support/Tenant/PermissionMatrix.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Create: seeders

- [ ] **Step 1: Create PermissionMatrix**

Create `app/Support/Tenant/PermissionMatrix.php`:

```php
<?php

namespace App\Support\Tenant;

class PermissionMatrix
{
    public static function modules(): array
    {
        return [
            'dashboard' => ['label' => 'Dashboard', 'permissions' => self::onlyRead('dashboard')],
            'users' => ['label' => 'Usuarios', 'permissions' => self::crud('users')],
            'roles' => ['label' => 'Perfis', 'permissions' => self::crud('roles')],
            'teams' => ['label' => 'Equipes', 'permissions' => self::crud('teams')],
            'licenses' => ['label' => 'Licencas', 'permissions' => self::crud('licenses')],
            'notifications' => ['label' => 'Notificacoes', 'permissions' => self::onlyRead('notifications')],
            'integrations' => ['label' => 'Integracoes', 'permissions' => self::onlyRead('integrations')],
        ];
    }

    public static function keys(): array
    {
        return collect(self::modules())->flatMap(fn (array $module) => collect($module['permissions'])->pluck('key'))->values()->all();
    }

    private static function crud(string $module): array
    {
        return [
            'read' => ['key' => "{$module}.read", 'label' => 'Ler'],
            'create' => ['key' => "{$module}.create", 'label' => 'Criar'],
            'update' => ['key' => "{$module}.update", 'label' => 'Editar'],
            'delete' => ['key' => "{$module}.delete", 'label' => 'Excluir/Inativar'],
        ];
    }

    private static function onlyRead(string $module): array
    {
        return ['read' => ['key' => "{$module}.read", 'label' => 'Ler']];
    }
}
```

- [ ] **Step 2: Define gates**

In `AppServiceProvider::boot()`, define:

```php
foreach (\App\Support\Tenant\PermissionMatrix::keys() as $permissionKey) {
    \Illuminate\Support\Facades\Gate::define(
        'tenant.'.str_replace('.', '.', $permissionKey),
        fn (\App\Models\User $user): bool => $user->hasPermission($permissionKey),
    );
}
```

Also define:

```php
\Illuminate\Support\Facades\Gate::define('super-admin', fn (\App\Models\User $user): bool => (bool) $user->is_super_admin);
```

- [ ] **Step 3: Create PermissionSeeder**

Create permissions from `PermissionMatrix::modules()` using `updateOrCreate(['key' => ...], ...)`.

- [ ] **Step 4: Create SuperAdminSeeder**

Create `admin@discador.local` with password `password`, `is_super_admin = true`, `user_type = super_admin`, `extension = 1000`, `status = active`.

- [ ] **Step 5: Create DemoTenantSeeder**

Create a demo company, active license with `max_calls_per_available_agent = 2`, admin role with all tenant permissions, company admin, supervisor, two agents, and one team linking supervisor and agents.

- [ ] **Step 6: Run seeders**

Run:

```powershell
php artisan db:seed --class=PermissionSeeder
php artisan db:seed --class=SuperAdminSeeder
php artisan db:seed --class=DemoTenantSeeder
```

Expected: users and permissions exist.

- [ ] **Step 7: Commit RBAC**

Run:

```powershell
git add app/Support app/Providers database/seeders
git commit -m "feat: add RBAC matrix and seed data"
```

Expected: commit succeeds.

---

### Task 6: Add Tenant and Security Middleware

**Files:**
- Create: middleware files
- Modify: `bootstrap/app.php`

- [ ] **Step 1: Create middleware**

Create:

```php
// EnsureUserIsSuperAdmin
abort_unless($request->user()?->is_super_admin, 403);
return $next($request);

// EnsureTenantUser
abort_unless($request->user()?->company_id, 403);
return $next($request);

// EnsureTenantIsActive
$user = $request->user();
abort_unless($user && ($user->is_super_admin || $user->hasValidTenantAccess()), 403);
return $next($request);

// ApplySecurityHeaders
$response = $next($request);
$response->headers->set('X-Frame-Options', 'SAMEORIGIN');
$response->headers->set('X-Content-Type-Options', 'nosniff');
$response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
return $response;
```

- [ ] **Step 2: Add User::hasValidTenantAccess**

Add:

```php
public function hasValidTenantAccess(): bool
{
    if ($this->is_super_admin) {
        return true;
    }

    if ($this->status !== 'active' || ! $this->company_id) {
        return false;
    }

    $this->loadMissing('company.license');
    $license = $this->company?->license;

    return $this->company?->status === 'active'
        && $license
        && $license->status === 'active'
        && ! $license->starts_at->isAfter(today())
        && ! $license->ends_at->isBefore(today());
}
```

- [ ] **Step 3: Register aliases in `bootstrap/app.php`**

Add aliases:

```php
'tenant.active' => \App\Http\Middleware\EnsureTenantIsActive::class,
'tenant.user' => \App\Http\Middleware\EnsureTenantUser::class,
'super.admin' => \App\Http\Middleware\EnsureUserIsSuperAdmin::class,
'security.headers' => \App\Http\Middleware\ApplySecurityHeaders::class,
```

- [ ] **Step 4: Commit middleware**

Run:

```powershell
git add app/Http/Middleware app/Models/User.php bootstrap/app.php
git commit -m "feat: add tenant access middleware"
```

Expected: commit succeeds.

---

### Task 7: Copy THCONECT Design System

**Files:**
- Create: `resources/css/thconect-tokens.css`
- Create: `resources/css/thconect-components.css`
- Modify: `resources/css/app.css`
- Create: UI components

- [ ] **Step 1: Copy tokens and components from CRM**

Run:

```powershell
Copy-Item '..\CRMTELECOM - CODEX\crm-thconect\resources\css\thconect-tokens.css' 'resources\css\thconect-tokens.css' -Force
Copy-Item '..\CRMTELECOM - CODEX\crm-thconect\resources\css\thconect-components.css' 'resources\css\thconect-components.css' -Force
```

Expected: files exist in Discador project.

- [ ] **Step 2: Update `resources/css/app.css`**

Ensure top of file is:

```css
@import './thconect-tokens.css';
@import './thconect-components.css';

@tailwind base;
@tailwind components;
@tailwind utilities;

[x-cloak] {
    display: none !important;
}
```

- [ ] **Step 3: Copy UI components**

Copy from CRM:

```powershell
New-Item -ItemType Directory -Force -Path 'resources\views\components\ui' | Out-Null
Copy-Item '..\CRMTELECOM - CODEX\crm-thconect\resources\views\components\ui\button.blade.php' 'resources\views\components\ui\button.blade.php' -Force
Copy-Item '..\CRMTELECOM - CODEX\crm-thconect\resources\views\components\ui\card.blade.php' 'resources\views\components\ui\card.blade.php' -Force
Copy-Item '..\CRMTELECOM - CODEX\crm-thconect\resources\views\components\ui\action.blade.php' 'resources\views\components\ui\action.blade.php' -Force
Copy-Item '..\CRMTELECOM - CODEX\crm-thconect\resources\views\components\ui\icon.blade.php' 'resources\views\components\ui\icon.blade.php' -Force
```

- [ ] **Step 4: Create toast and modal components**

Create `resources/views/components/ui/toast-stack.blade.php`:

```blade
<div x-data="toastStack()" x-on:toast.window="push($event.detail)" class="fixed right-4 top-4 z-[90] grid w-[min(360px,calc(100vw-2rem))] gap-3">
    <template x-for="toast in toasts" :key="toast.id">
        <div class="rounded-xl border p-4 text-sm shadow-lg" style="background: var(--surface); border-color: var(--border); color: var(--fg);">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="font-semibold" x-text="toast.title"></p>
                    <p class="mt-1 text-xs" style="color: var(--fg-muted);" x-text="toast.message"></p>
                </div>
                <button type="button" class="h-7 w-7 rounded-lg border" style="border-color: var(--border);" x-on:click="remove(toast.id)" aria-label="Fechar">x</button>
            </div>
        </div>
    </template>
</div>
```

Create `resources/views/components/ui/modal.blade.php` using Breeze modal as base, styled with `var(--surface)`, `var(--border)`, and no native alerts.

- [ ] **Step 5: Commit design system**

Run:

```powershell
git add resources/css resources/views/components
git commit -m "feat: add THCONECT design system"
```

Expected: commit succeeds.

---

### Task 8: Build App Layout With Sidebar and Notification Panel

**Files:**
- Create/modify: `resources/views/layouts/app.blade.php`
- Create: `resources/views/components/ui/notification-panel.blade.php`
- Modify: `resources/js/app.js`
- Create: `resources/js/notifications.js`

- [ ] **Step 1: Implement notification JavaScript**

Create `resources/js/notifications.js`:

```js
export function toastStack() {
    return {
        toasts: [],
        push(payload) {
            const id = Date.now() + Math.random();
            this.toasts.push({
                id,
                title: payload.title || 'Notificacao',
                message: payload.message || '',
                severity: payload.severity || 'info',
            });
            window.setTimeout(() => this.remove(id), payload.timeout || 4500);
        },
        remove(id) {
            this.toasts = this.toasts.filter((toast) => toast.id !== id);
        },
    };
}
```

In `resources/js/app.js`:

```js
import './bootstrap';
import Alpine from 'alpinejs';
import { toastStack } from './notifications';

window.Alpine = Alpine;
window.toastStack = toastStack;
Alpine.start();
```

- [ ] **Step 2: Implement layout**

`layouts/app.blade.php` must include:

- `<html data-theme="{{ Auth::user()?->theme_preference ?? 'thconectclaro' }}">`
- left sidebar with Dashboard, Campanhas, Agente, Supervisao, Relatorios, Cadastros, Telefonia, Configuracoes, Integracoes "Em breve"
- right notification panel
- `<x-ui.toast-stack />`
- no `alert()` or `confirm()`

- [ ] **Step 3: Create notification panel**

Create `resources/views/components/ui/notification-panel.blade.php`:

```blade
@props(['notifications' => collect(), 'unreadCount' => 0])

<aside class="hidden border-l p-4 lg:grid lg:w-80" style="background: color-mix(in srgb, var(--surface) 94%, transparent); border-color: var(--border);">
    <div class="flex items-center justify-between">
        <div>
            <p class="text-[11px] font-semibold uppercase tracking-[0.06em]" style="color: var(--fg-muted);">Central</p>
            <h2 class="text-sm font-semibold" style="color: var(--fg);">Notificacoes</h2>
        </div>
        <span class="rounded-full px-2 py-1 text-xs" style="background: var(--primary-soft); color: var(--fg);">{{ $unreadCount }}</span>
    </div>
    <div class="mt-4 grid gap-2">
        @forelse ($notifications as $notification)
            <a href="{{ $notification->action_url ?: '#' }}" class="rounded-xl border p-3 text-sm" style="border-color: var(--border); background: var(--bg-subtle); color: var(--fg);">
                <span class="block font-semibold">{{ $notification->title }}</span>
                <span class="mt-1 block text-xs" style="color: var(--fg-muted);">{{ $notification->message }}</span>
            </a>
        @empty
            <p class="rounded-xl border p-3 text-sm" style="border-color: var(--border); color: var(--fg-muted);">Nenhuma notificacao.</p>
        @endforelse
    </div>
</aside>
```

- [ ] **Step 4: Commit shell**

Run:

```powershell
git add resources/views/layouts resources/views/components/ui/notification-panel.blade.php resources/js
git commit -m "feat: add Discador application shell"
```

Expected: commit succeeds.

---

### Task 9: Add Core Routes and Base Screens

**Files:**
- Modify: `routes/web.php`
- Create: `app/Http/Controllers/DashboardController.php`
- Create: `app/Http/Controllers/Tenant/ComingSoonController.php`
- Create: views

- [ ] **Step 1: Define routes**

In `routes/web.php`:

```php
Route::get('/', fn () => auth()->check() ? redirect()->route('dashboard') : redirect()->route('login'));

Route::get('/dashboard', \App\Http\Controllers\DashboardController::class)
    ->middleware(['auth', 'verified', 'tenant.active'])
    ->name('dashboard');

Route::middleware(['auth', 'super.admin'])->prefix('super-admin')->name('super-admin.')->group(function () {
    Route::resource('companies', \App\Http\Controllers\SuperAdmin\CompanyController::class)->except(['show']);
});

Route::middleware(['auth', 'verified', 'tenant.active', 'tenant.user'])->prefix('tenant')->name('tenant.')->group(function () {
    Route::resource('users', \App\Http\Controllers\Tenant\UserController::class)->except(['show']);
    Route::resource('teams', \App\Http\Controllers\Tenant\TeamController::class)->except(['show']);
    Route::resource('roles', \App\Http\Controllers\Tenant\RoleController::class)->except(['show']);
    Route::get('integrations', \App\Http\Controllers\Tenant\ComingSoonController::class)->name('integrations.index');
});
```

- [ ] **Step 2: Create DashboardController**

Return counts for companies, users, teams, unread notifications, and license summary for tenant users.

- [ ] **Step 3: Create coming soon integrations view**

Use a `x-ui.card` with title `Integracoes` and text `Modulo em breve`.

- [ ] **Step 4: Commit routes**

Run:

```powershell
git add routes app/Http/Controllers resources/views
git commit -m "feat: add core navigation routes"
```

Expected: commit succeeds.

---

### Task 10: Implement Super Admin Companies Modal CRUD

**Files:**
- Create: `app/Http/Controllers/SuperAdmin/CompanyController.php`
- Create: `resources/views/super-admin/companies/index.blade.php`
- Create: `resources/views/super-admin/companies/_form.blade.php`

- [ ] **Step 1: Controller validation**

Use validation:

```php
'name' => ['required', 'string', 'max:255'],
'document' => ['nullable', 'string', 'max:30'],
'status' => ['required', Rule::in(['active', 'inactive'])],
'license_starts_at' => ['required', 'date'],
'license_ends_at' => ['required', 'date', 'after_or_equal:license_starts_at'],
'max_users' => ['required', 'integer', 'min:1', 'max:5000'],
'max_concurrent_agents' => ['nullable', 'integer', 'min:1', 'max:5000'],
'max_calls_per_available_agent' => ['required', 'integer', 'min:1', 'max:20'],
'max_concurrent_calls' => ['nullable', 'integer', 'min:1', 'max:100000'],
'power_enabled' => ['boolean'],
'predictive_enabled' => ['boolean'],
'preview_enabled' => ['boolean'],
'amd_enabled' => ['boolean'],
```

- [ ] **Step 2: Store/update license with company**

On create and update, persist company and latest license. Use transaction.

- [ ] **Step 3: Index view**

Show companies in cards/table. Create/edit actions open modals. Delete action is "Inativar" modal, implemented as status update to `inactive`.

- [ ] **Step 4: Toast feedback**

After redirect with `session('status')`, layout dispatches toast instead of alert.

- [ ] **Step 5: Commit companies CRUD**

Run:

```powershell
git add app/Http/Controllers/SuperAdmin resources/views/super-admin
git commit -m "feat: add super admin company licensing CRUD"
```

Expected: commit succeeds.

---

### Task 11: Implement Tenant Users With Mandatory Extensions

**Files:**
- Create: `app/Http/Controllers/Tenant/UserController.php`
- Create: `resources/views/tenant/users/index.blade.php`
- Create: `resources/views/tenant/users/_form.blade.php`

- [ ] **Step 1: Add validation**

Use:

```php
'name' => ['required', 'string', 'max:255'],
'email' => ['required', 'email', Rule::unique('users')->ignore($user ?? null)],
'phone' => ['nullable', 'string', 'max:30'],
'user_type' => ['required', Rule::in(['agent', 'supervisor', 'company_admin'])],
'extension' => [
    'required',
    'string',
    'max:20',
    Rule::unique('users', 'extension')->where('company_id', $companyId)->ignore($user ?? null),
],
'role_id' => ['required', Rule::exists('roles', 'id')->where('company_id', $companyId)],
'status' => ['required', Rule::in(['active', 'inactive'])],
'team_ids' => ['array'],
'team_ids.*' => [Rule::exists('teams', 'id')->where('company_id', $companyId)],
```

- [ ] **Step 2: Enforce license max users**

Before creating active user, compare active tenant user count with `$company->license->max_users`. If exceeded, return validation error `Limite de usuarios da licenca atingido.`

- [ ] **Step 3: Sync team membership**

For `agent`, attach selected teams with `membership_type = agent`. For `supervisor`, attach selected teams with `membership_type = supervisor`. For `company_admin`, do not require teams.

- [ ] **Step 4: Build modal-first view**

Index shows users and opens create/edit modals. Inactivation uses custom confirmation modal.

- [ ] **Step 5: Commit users**

Run:

```powershell
git add app/Http/Controllers/Tenant/UserController.php resources/views/tenant/users
git commit -m "feat: add tenant users with extensions"
```

Expected: commit succeeds.

---

### Task 12: Implement Teams and Roles Modal CRUD

**Files:**
- Create: `app/Http/Controllers/Tenant/TeamController.php`
- Create: `app/Http/Controllers/Tenant/RoleController.php`
- Create: team/role views

- [ ] **Step 1: TeamController**

Validation:

```php
'name' => ['required', 'string', 'max:255'],
'status' => ['required', Rule::in(['active', 'inactive'])],
'agent_ids' => ['array'],
'agent_ids.*' => [Rule::exists('users', 'id')->where('company_id', $companyId)->where('user_type', 'agent')],
'supervisor_ids' => ['array'],
'supervisor_ids.*' => [Rule::exists('users', 'id')->where('company_id', $companyId)->where('user_type', 'supervisor')],
```

Sync agents and supervisors into `team_user`.

- [ ] **Step 2: RoleController**

Validation:

```php
'name' => ['required', 'string', 'max:255'],
'permissions' => ['array'],
'permissions.*' => [Rule::exists('permissions', 'key')],
```

Persist role and sync permissions by key.

- [ ] **Step 3: Views**

Index pages use modal-first create/edit/delete confirmation. Permission matrix is a grouped checkbox grid.

- [ ] **Step 4: Commit teams and roles**

Run:

```powershell
git add app/Http/Controllers/Tenant/TeamController.php app/Http/Controllers/Tenant/RoleController.php resources/views/tenant/teams resources/views/tenant/roles
git commit -m "feat: add tenant teams and roles"
```

Expected: commit succeeds.

---

### Task 13: Add Premium Notifications Backend

**Files:**
- Create: `app/Support/Notifications/AppNotificationService.php`
- Create: `app/Events/AppNotificationCreated.php`
- Create: notification routes/controller methods if needed

- [ ] **Step 1: Create service**

Service method:

```php
public function createForUser(User $user, string $type, string $severity, string $title, string $message, ?string $actionUrl = null): AppNotification
{
    $notification = AppNotification::query()->create([
        'company_id' => $user->company_id,
        'user_id' => $user->id,
        'type' => $type,
        'severity' => $severity,
        'title' => $title,
        'message' => $message,
        'action_url' => $actionUrl,
    ]);

    event(new AppNotificationCreated($notification));

    return $notification;
}
```

- [ ] **Step 2: Create event**

Broadcast on private channel:

```php
return new PrivateChannel("tenant.{$this->notification->company_id}.user.{$this->notification->user_id}");
```

- [ ] **Step 3: Add mark-read routes**

Add routes:

```php
Route::patch('notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
Route::patch('notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
```

- [ ] **Step 4: Commit notifications**

Run:

```powershell
git add app/Support/Notifications app/Events app/Http/Controllers/Tenant/NotificationController.php routes resources/views/components/ui/notification-panel.blade.php
git commit -m "feat: add premium notification backend"
```

Expected: commit succeeds.

---

### Task 14: Add Feature Tests

**Files:**
- Create: `tests/Feature/SaaSAccessTest.php`
- Create: `tests/Feature/CompanyLicenseTest.php`
- Create: `tests/Feature/TenantUserTest.php`
- Create: `tests/Feature/TeamMembershipTest.php`
- Create: `tests/Feature/NotificationTest.php`

- [ ] **Step 1: SaaS access tests**

Cover:

```php
public function test_super_admin_can_view_companies(): void
public function test_tenant_user_cannot_view_super_admin_companies(): void
public function test_inactive_license_blocks_tenant_access(): void
```

- [ ] **Step 2: License tests**

Cover:

```php
public function test_company_license_stores_calls_per_available_agent(): void
public function test_active_user_creation_respects_max_users(): void
```

- [ ] **Step 3: User extension tests**

Cover:

```php
public function test_extension_is_required_for_all_tenant_users(): void
public function test_extension_is_unique_inside_company(): void
public function test_same_extension_can_exist_in_different_companies(): void
```

- [ ] **Step 4: Team membership tests**

Cover:

```php
public function test_agent_can_belong_to_multiple_teams(): void
public function test_supervisor_can_belong_to_multiple_teams(): void
public function test_company_admin_is_not_attached_as_campaign_agent_membership(): void
```

- [ ] **Step 5: Notification tests**

Cover:

```php
public function test_notification_is_persisted_for_user(): void
public function test_notification_can_be_marked_as_read(): void
```

- [ ] **Step 6: Run tests**

Run:

```powershell
php artisan test
```

Expected: all tests pass.

- [ ] **Step 7: Commit tests**

Run:

```powershell
git add tests
git commit -m "test: cover SaaS foundation rules"
```

Expected: commit succeeds.

---

### Task 15: Verify Docker and Browser Smoke Test

**Files:**
- Modify only if verification finds issues.

- [ ] **Step 1: Run format and tests**

Run:

```powershell
vendor\bin\pint
php artisan test
npm run build
```

Expected: Pint completes, tests pass, Vite build succeeds.

- [ ] **Step 2: Run through Docker**

Run:

```powershell
docker compose up -d --build
docker compose exec app php artisan migrate:fresh --seed
```

Expected: containers are healthy, migrations and seeders pass.

- [ ] **Step 3: Open local app**

Open:

```text
http://localhost:8087
```

Login:

```text
admin@discador.local
password
```

Expected: dashboard loads with THCONECT visual, sidebar, notification panel, no native alerts.

- [ ] **Step 4: Manual smoke checklist**

Verify:

- Super admin can open companies page.
- Company create/edit opens modal.
- License fields include calls per available agent.
- Tenant user create/edit opens modal.
- User extension is required.
- Teams allow multiple agents and supervisors.
- Roles show grouped permission matrix.
- Integrations page shows "Modulo em breve".
- Toast appears on successful action in the right corner.

- [ ] **Step 5: Commit final verification fixes**

If any fixes were made:

```powershell
git add .
git commit -m "fix: polish SaaS foundation verification"
```

Expected: working tree is clean.

---

## Plan Self-Review

- Spec coverage: covers Phase 1 foundation, Docker, design system, SaaS tenants, licenses, mandatory extensions, flexible teams, modal-first CRUD, premium notifications, and "Integracoes Em Breve".
- Deferred by design: campaigns, mailing, agent panel, supervision, telephony gateways, Go Bridge, and real webhooks are Phase 2+ plans.
- Red-flag scan: no incomplete implementation markers remain. Code snippets identify exact fields, routes, commands, and expected behavior.
- Type consistency: uses `company_id`, `user_type`, `extension`, `max_calls_per_available_agent`, `team_user.membership_type`, and permission keys consistently.
