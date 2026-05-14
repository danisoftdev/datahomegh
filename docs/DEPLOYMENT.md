# DataHomeGH — Deployment guide (local → Hostinger shared hosting)

This guide takes the project from a finished local branch to production on **Hostinger** shared hosting, with **GitHub** as the source of truth. Replace placeholders (`u123456789`, `datahomegh.shop`, repo URL, passwords) with your real values.

---

## Prerequisites

- GitHub repository (example: `https://github.com/danysoftdev/datahomegh.git`).
- Hostinger account with **hPanel**, **SSH**, and a domain (e.g. `datahomegh.shop`) pointed at Hostinger.
- Local machine: **Node.js**, **PHP 8.2+**, **Composer**, **Git**.

**Note on the REST API:** This repo includes a **pure PHP API** under `public_html/api/v1/` in the repo (path `/api/v1/…` from the web root). Laravel’s web root is **`public/`**. On the server, after **`public_html`** points at Laravel’s `public` (Part B.3 symlink), either:

- Copy or symlink `public_html/api/v1` → `~/datahomegh/public/api/v1` (from the repo’s `public_html/api/v1`), **or**
- Deploy the API folder separately.

Ensure `.htaccess` inside `api/v1` rewrites to `index.php` if you rely on Apache. The **curl** in Part D assumes login is available at `https://datahomegh.shop/api/v1/auth/login` from that layout.

---

## Directory layout on Hostinger (correct vs wrong)

**Correct (recommended):**

| Path | Contents |
|------|-----------|
| `~/datahomegh/` | Full Laravel repo (`app/`, `routes/`, `vendor/`, `.env`, `storage/`, …) — **outside** the fixed web root |
| `~/datahomegh/public/` | Laravel web root (`index.php`, `build/`, …) |
| `~/domains/<domain>/public_html` **or** `~/public_html` | **Symlink** → `~/datahomegh/public` (Hostinger still “serves” `public_html`, but files are Laravel’s `public`) |

**Wrong:** `git clone` **into** `public_html` so `app/`, `.env`, and `vendor/` are web-accessible — exposes the application and breaks updates.

**Recovery:** If you already cloned or copied the full project into `public_html`, see **Recovery** at the end of this document.

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
   - `tokenizer`
   - `xml`
   - `ctype`

Save / apply.

### 3. Web root → Laravel `public` (Hostinger shared hosting)

On **Web / WordPress / Cloud** shared plans, Hostinger usually **fixes** the site directory to something like:

- `/home/u123456789/domains/datahomegh.shop/public_html`, or  
- `/home/u123456789/public_html`

The panel **often cannot change** that path to `~/datahomegh/public` (Hostinger documents this). After **Part C** has created `~/datahomegh` and `~/datahomegh/public/index.php`, use the **symbolic link** commands in **Part C (after `composer install`)** so `public_html` points at Laravel’s `public` folder.

If `ln -sfn` fails, use **hPanel → Advanced → Fix File Ownership**, or ask Hostinger support to allow symlinks.

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

### Link `public_html` → Laravel `public` (required on most Hostinger shared plans)

Find which `public_html` your domain uses, then replace it with a symlink to `~/datahomegh/public`:

```bash
ls -la ~/domains/
ls -la ~/domains/datahomegh.shop/ 2>/dev/null || true
ls -la ~/public_html
```

**If** `~/domains/datahomegh.shop/public_html` **exists:**

```bash
cd ~/domains/datahomegh.shop
mv public_html "public_html.bak.$(date +%Y%m%d)"
ln -sfn ~/datahomegh/public public_html
ls -la public_html
```

**Else if only** `~/public_html` **is used:**

```bash
cd ~
mv public_html "public_html.bak.$(date +%Y%m%d)"
ln -sfn ~/datahomegh/public public_html
ls -la public_html
```

Use your real domain folder name under `domains/` if it differs from `datahomegh.shop`. See **Part B §3** for why this is needed.

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

**If `php artisan storage:link` fails** (Hostinger often disables `exec()` / `symlink()` from PHP CLI), create the link manually from the project root:

```bash
cd ~/datahomegh
rm -rf public/storage
ln -sfn "$(pwd)/storage/app/public" "$(pwd)/public/storage"
```

Then run **`php artisan optimize`** again if you skipped it above.

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

If you get **404**, check the **`public_html` → `~/datahomegh/public` symlink**, API path under `public`, and rewrites. If **500**, check `storage/logs/laravel.log` and PHP error logs in hPanel.

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

## Hostinger deployment checklist (canonical)

Use this instead of any older step that says “set document root to `~/datahomegh/public`” in hPanel — on **shared Web hosting** Hostinger usually **cannot** change the web root; use the **`public_html` → symlink** flow in **Part C**.

### 23.1 — hPanel pre-setup

- Create **MySQL** database in hPanel → note `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` / `DB_HOST`.
- **PHP 8.2+** in hPanel → **Advanced** → **PHP Configuration**.
- Enable extensions: `pdo_mysql`, `mbstring`, `openssl`, `curl`, `zip`, `gd`, `fileinfo`, `tokenizer`, `xml`, `ctype`.
- **Web root:** Do **not** put the Laravel repo inside `public_html`. Keep the app in **`~/datahomegh`**, then make **`public_html` a symlink** to **`~/datahomegh/public`** (see **Part C** after `composer install`). Hostinger still serves `public_html`; it resolves to Laravel’s `public`.
- **SSL:** hPanel → SSL → Let’s Encrypt → `datahomegh.shop` + `www.datahomegh.shop` (optional).
- **SSH:** hPanel → **Advanced** → **SSH Access** (note port, e.g. `65002`).
- **Cron:** hPanel → **Advanced** → **Cron Jobs** — scheduler + queue worker (see **Part B §6**).

### 23.2 — Code deployment (SSH)

- `git clone` into **`~/datahomegh`** (repo root **next to** `public_html`, not inside it).
- `composer install --optimize-autoloader --no-dev`
- **Symlink** `public_html` → `~/datahomegh/public` (Part C — run after `composer install`).
- `cp .env.example .env` → fill production values (see **23.3**).
- `php artisan key:generate`
- `php artisan migrate --force`
- `php artisan db:seed --class=RolesAndPermissionsSeeder`
- `php artisan db:seed --class=SupplierAdminSeeder`
- `php artisan storage:link` (or manual `ln` if Artisan fails — Part C)
- `php artisan optimize`
- **Native API:** copy or symlink `public_html/api/v1` (from repo) → `~/datahomegh/public/api/v1` (Part C).

### 23.3 — Production `.env` checklist

- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL=https://datahomegh.shop`
- `QUEUE_CONNECTION=database` (no Redis on typical shared hosting)
- `CACHE_STORE=file` (Laravel 11+; legacy `CACHE_DRIVER=file` if your `.env` still uses it)
- `SESSION_DRIVER=file` (unless you migrated a `sessions` table and prefer `database`)
- `PAYSTACK_PUBLIC_KEY` / `PAYSTACK_SECRET_KEY` — test keys first, then live
- `FIREBASE_CREDENTIALS=/home/u123456789/datahomegh/storage/app/firebase-credentials.json` (upload JSON via SFTP; path must exist on server)

### 23.4 — Post-deploy verification

- Visit `https://datahomegh.shop` — SSL padlock, site loads.
- Change **superadmin** password immediately (seed default is dangerous).
- Paystack: **test** end-to-end, then switch to **live** keys.
- FCM browser push smoke test.
- Flow: agent register → approve → shop link → buyer via link → order → admin status → refund.
- Native API: `POST /api/v1/auth/login` returns JSON with token.
- Wallet ledger: no ad-hoc `UPDATE`/`DELETE` on `wallet_ledger` (app + DB discipline).
- hPanel **Backups** (weekly minimum).

### 23.5 — Security hardening

- `APP_DEBUG=false` in production.
- Permissions: `storage/`, `bootstrap/cache/` writable by web user (often `755` or `775` per host); **`.env` → `600`**.
- **Options -Indexes** in `.htaccess` (disable directory listing) where applicable.
- CORS: production origins only (e.g. `https://datahomegh.shop`) — not `*` (see `config/cors.php` + native `API_CORS_ORIGIN`).
- Enable **OPcache** in PHP configuration if available.
- Paystack webhook: signature validation on before going live.

---

## Recovery: wrong layout (everything inside `public_html`)

If you **cloned or uploaded the full Laravel repo into** `~/domains/.../public_html` (or `~/public_html`) so visitors could hit `app/`, `.env`, `vendor/`:

1. **SSH** into the account.
2. **Back up** the broken tree (keep a copy until the new site works):

   ```bash
   cd ~/domains/datahomegh.shop   # or: cd ~
   mv public_html "public_html_full_repo_backup_$(date +%Y%m%d%H%M)"
   ```

3. **Clone clean** next to it (not inside web root):

   ```bash
   cd ~
   git clone https://github.com/YOUR_ORG/datahomegh.git datahomegh
   cd datahomegh
   composer install --optimize-autoloader --no-dev
   ```

4. **Copy** your production `.env` from the backup into `~/datahomegh/.env` (or recreate from `.env.example`), then `php artisan key:generate` only if needed, `migrate`, etc.

5. **Recreate** `public_html` as a **symlink** only:

   ```bash
   cd ~/domains/datahomegh.shop   # or: cd ~
   ln -sfn ~/datahomegh/public public_html
   ls -la public_html
   ```

6. **hPanel → Fix File Ownership** if permissions break.

---

## Quick reference — URLs

| Item | Example |
|------|--------|
| Site | `https://datahomegh.shop` |
| Laravel `public` (real files) | `~/datahomegh/public` |
| Web root Hostinger serves | `public_html` → symlink → `~/datahomegh/public` (Part C, after `composer install`) |
| API v1 (if under `public`) | `https://datahomegh.shop/api/v1/...` |

---

## Troubleshooting

| Symptom | Things to check |
|--------|------------------|
| 500 / white screen | `APP_DEBUG` temporarily `true` in `.env` (revert after), `storage/logs/laravel.log`, PHP version & extensions |
| 404 on all routes | **`public_html`** → symlink to **`~/datahomegh/public`** (Part C); `mod_rewrite` / `.htaccess` present |
| DB connection errors | `DB_HOST`, credentials, user attached to DB with privileges |
| Assets 404 | Run `npm run build`, commit `public/build`, or build on server; `APP_URL` must match HTTPS |
| API 404 | API files under `public/api/v1` and Apache/nginx rules |

---

*Last updated for DataHomeGH deployment workflow (GitHub + Hostinger shared + Laravel + optional native PHP API under `/api/v1`).*
