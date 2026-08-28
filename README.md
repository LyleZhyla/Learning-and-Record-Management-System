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
php artisan serve
```

Open `http://127.0.0.1:8000`.

## Initial Super Administrator

The local credentials are stored only in the ignored `.env` file. After the first login, the dashboard requires the temporary password to be changed.

For a fresh environment, copy `.env.example` to `.env`, set `SUPER_ADMIN_EMAIL` and `SUPER_ADMIN_PASSWORD`, then run:

```powershell
php artisan key:generate
php artisan migrate --seed
```
