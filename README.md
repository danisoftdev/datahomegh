# DataHomeGH

Professional data bundle reselling platform for Ghana (**datahomegh.shop**). This repository runs **Laravel 12** on **PHP 8.2+** (the product brief referenced Laravel 10; behavior and deployment model are the same).

## Repository layout vs Hostinger

| Location on server | Purpose |
|--------------------|---------|
| `~/datahomegh/` | Laravel root: `app/`, `bootstrap/`, `config/`, `database/`, `resources/`, `routes/`, `storage/`, `vendor/`, `.env`, `artisan` |
| `~/public_html/` | Apache document root: front controller, `.htaccess`, compiled front-end (`build/`), optional `storage` symlink, native `api/v1/` (see product spec) |

Laravel’s built-in `public/` folder is **not** the web root on production. Instead, copy the Hostinger templates from `deploy/hosting/public_html/` into `public_html/` and keep the Laravel tree **one level above** `public_html/`.

## Composer packages (DataHomeGH stack)

| Package | Role |
|---------|------|
| `laravel/framework` | Application core |
| `laravel/sanctum` | API token authentication |
| `devrabiul/laravel-paystack` | Paystack integration for Laravel 11–12 (drop-in successor to `unicodeveloper/laravel-paystack`, which does not declare Laravel 12 support) |
| `kreait/laravel-firebase` | Firebase Admin SDK (FCM, etc.) |
| `intervention/image` | Image processing (avatars, logos) |

Install / update:

```bash
composer install --no-dev --optimize-autoloader   # production
composer install                                  # local / CI
```

**PHP extensions (Hostinger hPanel → PHP configuration):** enable `pdo_mysql`, `mbstring`, `openssl`, `curl`, `zip`, `gd`, `fileinfo`, `tokenizer`, `xml`, `ctype`, and **`sodium`** (required by Firebase JWT stack). On local Windows/XAMPP, enable `extension=sodium` in `php.ini` or run Composer with `--ignore-platform-req=ext-sodium` only when you must.

## NPM packages (front end)

| Package | Role |
|---------|------|
| `tailwindcss` | Utility-first CSS (with `@tailwindcss/vite`) |
| `alpinejs` | Lightweight interactivity |
| `vite`, `laravel-vite-plugin`, `axios` | Build pipeline & HTTP defaults |

```bash
npm install
npm run build
```

Commit `public/build/` so Hostinger can serve assets **without** Node.js on the server.

## Environment file

Copy `.env.example` to `.env` and set real values (never commit `.env`). Key placeholders:

- `APP_NAME`, `APP_URL=https://datahomegh.shop`
- MySQL: `DB_*` (Hostinger database names look like `u123456789_datahomegh`)
- `QUEUE_CONNECTION=database` (no Redis on shared hosting)
- `CACHE_STORE=file` and `CACHE_DRIVER=file` (Laravel reads `CACHE_STORE` in v11+; both are set for parity with older docs)
- `SESSION_DRIVER=file` (simplest on shared hosting; use `database` if you prefer)
- `PAYSTACK_PUBLIC_KEY`, `PAYSTACK_SECRET_KEY`, `PAYSTACK_PAYMENT_URL`
- `FIREBASE_CREDENTIALS` — absolute path to the service account JSON (keep the file out of git; `.gitignore` already excludes common credential paths)

```bash
cp .env.example .env
php artisan key:generate
```

## Hostinger deployment checklist

1. **SSH / SFTP**  
   Upload or clone the project so `~/datahomegh/` contains the full Laravel tree.

2. **Web root**  
   - Copy `deploy/hosting/public_html/.htaccess` and `deploy/hosting/public_html/index.php` into `~/public_html/`.  
   - If your home directory name is not `datahomegh`, edit `index.php` and set `$laravelRoot` to the correct path (still typically `dirname(__DIR__).'/your-folder-name'`).

3. **Dependencies & env**  
   ```bash
   cd ~/datahomegh
   composer install --no-dev --optimize-autoloader
   cp .env.example .env
   # edit .env with production DB, keys, APP_DEBUG=false, etc.
   php artisan key:generate
   php artisan migrate --force
   ```

4. **Storage & uploads**  
   ```bash
   php artisan storage:link
   ```  
   Because the web root is `public_html/`, create a symlink there so uploaded files are reachable, for example:  
   `ln -s ~/datahomegh/storage/app/public ~/public_html/storage`  
   (Adjust paths if Hostinger uses different naming.)

5. **Front-end assets**  
   Build on your PC (`npm run build`), commit `public/build/`, deploy, then ensure `public_html/build/` matches `datahomegh/public/build/` (copy or rsync after each release).

6. **Optimize**  
   ```bash
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```

7. **Cron (hPanel → Cron Jobs)**  
   ```cron
   * * * * * /usr/bin/php /home/USER/datahomegh/artisan schedule:run >> /dev/null 2>&1
   * * * * * /usr/bin/php /home/USER/datahomegh/artisan queue:work --max-time=55 --stop-when-empty >> /dev/null 2>&1
   ```  
   Replace `USER` and path with your account paths. Use **`QUEUE_CONNECTION=database`** (no Supervisor on shared hosting).

8. **SSL**  
   Enable Let’s Encrypt in hPanel for `datahomegh.shop`.

## Local development

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan serve
npm run dev
```

## Product documentation

See `docs/PRODUCT_SPECIFICATION.md` for roles, wallet rules, Paystack webhooks, native PHP API layout, and UI guidelines.

## License

The Laravel framework is open-source under the [MIT license](https://opensource.org/licenses/MIT). Application-specific licensing is defined by the project owner.
