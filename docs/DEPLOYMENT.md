# DataHomeGH — Deployment guide (local → Hostinger shared hosting)

This guide takes the project from a finished local branch to production on **Hostinger** shared hosting, with **GitHub** as the source of truth. Replace placeholders (`u123456789`, `datahomegh.shop`, repo URL, passwords) with your real values.

---

## Prerequisites

- GitHub repository (example: `https://github.com/danysoftdev/datahomegh.git`).
- Hostinger account with **hPanel**, **SSH**, and a domain (e.g. `datahomegh.shop`) pointed at Hostinger.
- Local machine: **Node.js**, **PHP 8.2+**, **Composer**, **Git**.

**Note on the REST API:** This repo includes a **pure PHP API** under `public_html/api/v1/` (document root–relative path `/api/v1/…`). Laravel’s web root is **`public/`**. For production with document root `~/datahomegh/public`, either:

- Copy or symlink `public_html/api/v1` → `public/api/v1`, **or**
- Deploy the API folder separately and map `/api/v1` in the panel.

Ensure `.htaccess` inside `api/v1` rewrites to `index.php` if you rely on Apache. The **curl** in Part D assumes login is available at `https://datahomegh.shop/api/v1/auth/login` from that layout.

---

## PART A — Final local steps

1. **Compile frontend assets**

   ```bash
   npm ci
   npm run build
   ```

   This compiles **Tailwind CSS** and **Alpine.js** (and other Vite inputs) into `public/build/` (manifest + hashed assets).

2. **Commit built assets** (so production does not require `npm` on the server unless you prefer building in CI only).

   ```bash
   git add public/build
   git commit -m "chore: add compiled frontend assets"
   ```

3. **Run tests**

   ```bash
   php artisan test
   ```

   Confirm **all tests pass** before pushing.

4. **Push feature branch**

   ```bash
   git push origin develop
   ```

5. **Open a Pull Request on GitHub:** `develop` → `main`.

6. **Merge** after **CI passes** (and after any required reviews).

---

## PART B — Hostinger hPanel setup (in order)

### 1. MySQL database

1. **hPanel → Databases → MySQL Databases**.
2. Create database: e.g. `u123456789_datahomegh`.
3. Create MySQL user with a strong password.
4. **Add user to database** with **All privileges**.

Use these values in production `.env` as `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` (and `DB_HOST` / `DB_PORT` as hPanel documents — often `127.0.0.1` or a host like `mysql.hostinger.com`).

### 2. PHP configuration

1. **hPanel → Advanced → PHP Configuration** (or **Select PHP Version**).
2. Select **PHP 8.2** (or newer if offered and compatible with your Laravel version).
3. Enable extensions (minimum for Laravel + Paystack + images):

   - `pdo_mysql`
   - `mbstring`
   - `openssl`
   - `curl`
   - `zip`
   - `gd`
   - `fileinfo`

Save / apply.

### 3. Document root

1. **hPanel → Domains** (or **Websites → Manage**).
2. Set the site’s **document root** to the Laravel **`public`** directory, e.g.:

   `~/datahomegh/public`

   Not the repo root — **must** be `public` so `index.php`, `.htaccess`, and `build/` assets are served correctly.

### 4. SSL

1. **hPanel → SSL** (or **Security → SSL**).
2. Install **Let’s Encrypt** for:

   - `datahomegh.shop`
   - `www.datahomegh.shop` (optional but recommended; redirect one to the canonical host in Laravel or `.htaccess` if desired).

### 5. SSH access

1. **hPanel → Advanced → SSH Access** — enable SSH.
2. Note **port** (Hostinger often uses a non-default port, e.g. **65002**).
3. Use SFTP/SSH user (often same prefix as DB user, e.g. `u123456789`).

### 6. Cron jobs (Laravel scheduler + queue)

**Scheduler** (required for `schedule:run`):

```text
* * * * * cd /home/u123456789/datahomegh && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

Adjust paths and PHP binary (`which php` over SSH).

**Queue worker** — on shared hosting, long-lived `queue:work` may be restricted. Practical options:

**Option A — short worker each minute** (simple, works on many shared plans):

```text
* * * * * cd /home/u123456789/datahomegh && /usr/bin/php artisan queue:work --stop-when-empty --max-time=50 >> /dev/null 2>&1
```

**Option B — database sync driver** — if you use `sync`, you may not need a worker for mail/notifications; still keep **scheduler** for scheduled tasks.

Tune `--max-time` to stay under Hostinger’s cron execution limits.

---

## PART C — First deploy via SSH

```bash
ssh u123456789@datahomegh.shop -p 65002
```

```bash
cd ~
git clone https://github.com/danysoftdev/datahomegh.git datahomegh
cd datahomegh
```

Install PHP dependencies (production, no dev packages):

```bash
composer install --optimize-autoloader --no-dev
```

Environment file:

```bash
cp .env.example .env
nano .env
```

Fill at least:

- `APP_NAME`, `APP_ENV=production`, `APP_DEBUG=false`
- `APP_URL=https://datahomegh.shop`
- `APP_KEY` — generate next step
- Database: `DB_*` from Part B
- Mail / queue if used
- `PAYSTACK_*` and any other integrations

Generate key, migrate, seed, optimize:

```bash
php artisan key:generate
php artisan migrate --force
php artisan db:seed --class=RolesAndPermissionsSeeder
php artisan db:seed --class=SupplierAdminSeeder
php artisan storage:link
php artisan optimize
```

**Native API:** If you serve it from `public/api/v1`, copy or link before or after `optimize`:

```bash
mkdir -p public/api
cp -r public_html/api/v1 public/api/v1
```

Verify `public/api/v1/.htaccess` exists and rewrites to `index.php`.

Set permissions if needed (Hostinger varies):

```bash
chmod -R ug+rwx storage bootstrap/cache
```

---

## PART D — Verify API (login)

Replace credentials with a real user (seeded supplier or test account). Example:

```bash
curl -sS -X POST "https://datahomegh.shop/api/v1/auth/login" \
  -H "Content-Type: application/json" \
  -d '{"username":"superadmin","password":"Admin@12345"}'
```

Expect **HTTP 200** and JSON containing at least a **Bearer token** (and user payload), e.g. `"token":"..."`.

If you get **404**, check document root, API path under `public`, and rewrites. If **500**, check `storage/logs/laravel.log` and PHP error logs in hPanel.

---

## PART E — Future deploys (`git pull`)

After merging to `main` and pulling on the server:

```bash
cd ~/datahomegh
git pull origin main
composer install --optimize-autoloader --no-dev
php artisan migrate --force
php artisan optimize
```

If you committed new frontend builds:

```bash
# usually nothing; assets already in public/build from git
```

If you **do not** commit `public/build` and build on the server instead:

```bash
npm ci && npm run build
```

Then clear caches if config/routes/views changed:

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

(Use only what you need; `optimize` runs several of these.)

---

## PART F — Post-deploy checklist

1. **Change default passwords** — especially any **superadmin** / seeded supplier account (`SupplierAdminSeeder`). Log in via web or admin panel and set a new strong password immediately.
2. **Paystack** — start with **test keys**; run a small **wallet top-up** and callback; switch to live keys only when ready.
3. **Agent flow** — register an agent → supplier **approves** in admin → confirm **shop URL** `https://datahomegh.shop/{shop_slug}` loads and registration links work.
4. **SSL** — browser shows a **padlock**; fix mixed content if any asset still uses `http://`.
5. **Cron** — confirm Laravel scheduler entries exist in hPanel and run (check `storage/logs` or a test scheduled command).
6. **Backups** — enable Hostinger backups / export DB periodically.

---

## Quick reference — URLs

| Item | Example |
|------|--------|
| Site | `https://datahomegh.shop` |
| Laravel `public` | `~/datahomegh/public` |
| API v1 (if under `public`) | `https://datahomegh.shop/api/v1/...` |

---

## Troubleshooting

| Symptom | Things to check |
|--------|------------------|
| 500 / white screen | `APP_DEBUG` temporarily `true` in `.env` (revert after), `storage/logs/laravel.log`, PHP version & extensions |
| 404 on all routes | Document root must be **`public/`**, `mod_rewrite` / `.htaccess` present |
| DB connection errors | `DB_HOST`, credentials, user attached to DB with privileges |
| Assets 404 | Run `npm run build`, commit `public/build`, or build on server; `APP_URL` must match HTTPS |
| API 404 | API files under `public/api/v1` and Apache/nginx rules |

---

*Last updated for DataHomeGH deployment workflow (GitHub + Hostinger shared + Laravel + optional native PHP API under `/api/v1`).*
