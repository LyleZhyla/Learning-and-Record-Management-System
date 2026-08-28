# Smart NSTP Management and AI-Integrated Platform

Laravel 12 and MySQL/MariaDB foundation for the Smart NSTP platform.

## Implemented module

- Super Administrator login with rate limiting and CSRF protection
- Role- and status-protected administration routes
- Super Administrator dashboard
- Profile information management
- Secure password change with current-password verification
- Session invalidation on logout
- Environment-driven Super Administrator seeder
- User account creation and editing across five roles
- Account search, role/status filtering, activation, deactivation, and password reset
- Protection against self-deactivation and removal of the last active Super Admin
- Role-aware login for Super Admin and NSTP Admin accounts
- Protected NSTP Admin dashboard with operational metrics and profile security
- Configurable CWTS, LTS, and ROTC component records
- Term-based section management with capacity and facilitator assignments
- Student component enrollment and automated capacity-based section generation
- English-only user interface, feedback, warnings, and validation messages
- Shared NSTP structure management access for Super Admin and NSTP Admin

## Local requirements

- PHP 8.2 or newer
- Composer 2
- MySQL 8 or MariaDB 10.4+

The normal database configuration uses MySQL on port `3306` as shown in `.env.example`. This workstation also has an isolated development MariaDB instance on port `3307` because the existing global XAMPP InnoDB store is unhealthy. Start it with:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/start-local-mysql.ps1
```

Then run the application:

```powershell
php artisan serve --port=8765
```

Open `http://127.0.0.1:8765/login`.

Before shutting down Windows or when development is finished, stop the isolated database cleanly with:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/stop-local-mysql.ps1
```

Use `Ctrl+C` separately to stop the Laravel development server.

## Initial Super Administrator

The local credentials are stored only in the ignored `.env` file. After the first login, the dashboard requires the temporary password to be changed.

For a fresh environment, copy `.env.example` to `.env`, set `SUPER_ADMIN_EMAIL` and `SUPER_ADMIN_PASSWORD`, then run:

```powershell
php artisan key:generate
php artisan migrate --seed
```

## Temporary sample accounts

Populate one temporary account for each non-Super-Admin role with:

```powershell
php artisan db:seed --class=SampleAccountsSeeder
```

These demo accounts use the password `Demo!Account2026` and require a password change at first login. Do not run this seeder in production.

Remove only these temporary demo accounts later with:

```powershell
php artisan demo:remove-accounts
```
