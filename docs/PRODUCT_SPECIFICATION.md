# DataHomeGH — Product Specification & Build Guide

Canonical project notes: **Part B** (product) and **Part C** (build & deployment).  
Live domain: **datahomegh.shop**

---

## Part B — Product Specification

### 2. Project Overview

DataHomeGH is a professional, web-based data bundle reselling platform serving the Ghanaian mobile data market. It enables a hierarchical network of suppliers, agents, and buyers to transact data bundles across **MTN**, **Telecel**, and **AirtelTigo** networks.

#### 2.1 Platform Mission

Provide a structured, wallet-driven marketplace where agents operate branded sub-stores under the main supplier’s umbrella, buyers purchase data from agents or the main store, and the supplier fulfills all orders **manually** — with full traceability, role-based control, and real-time notifications throughout.

#### 2.2 Key Differentiators

- No automated API delivery — supplier manually fulfills all orders
- Multi-tier role system: Supplier → Agent → Buyer
- Each approved Agent receives a unique branded shop link (e.g. `datahomegh.shop/agentshopname`)
- Agents manage their own buyers independently; Supplier manages agents
- Wallet-first financial model with immutable ledger
- Paystack payment gateway integration for wallet top-ups
- Push + in-app notification system for all roles
- Full REST API (native PHP) for third-party integrations
- Admin-approved password resets with time-limited codes
- Profile pictures, logos, WhatsApp links, and channel links per user

#### 2.3 Technology Stack

| Layer | Technology | Notes |
|--------|------------|--------|
| Backend Framework | Laravel (PHP 8.2+) | MVC, Blade, Artisan |
| API Layer | Native PHP 8.2 (RESTful JSON) | `public_html/api/v1/` — no Node.js |
| Frontend | Blade + Alpine.js + Tailwind CSS | Mobile-first; build locally, commit assets |
| Database | MySQL 8.x | Hostinger hPanel |
| Payments | Paystack PHP SDK (Guzzle/cURL) | Card + Ghana MoMo top-ups |
| Push Notifications | Firebase FCM (PHP Admin SDK) | In-app + browser push (service worker) |
| File Storage | Local disk — `public_html/storage/` | Profile pics & logos |
| Authentication | Laravel Sanctum + PHP sessions | RBAC, CSRF, Bearer tokens for API |
| Hosting | Hostinger Shared Hosting | Apache + `.htaccess`; SSL (Let’s Encrypt) |
| Version Control | GitHub (Private) | Protected branches; GitHub Actions CI |

---

### 3. System Architecture

#### 3.1 High-Level Architecture

**CLIENT → APPLICATION → SERVICE → DATA**

Browser/Mobile → Hostinger Apache (`public_html/`) → Laravel + Native PHP API → Paystack, FCM, SMTP → MySQL (Hostinger)

**Hosting note:** The application lives on Hostinger Shared Hosting inside `public_html/`. Laravel’s `/public` maps to the Apache web root. The REST API is served via native PHP under `public_html/api/v1/` with `.htaccess` rewriting. No Node.js, Redis, or separate app server.

#### 3.2 Application Layers

| Layer | Components |
|--------|------------|
| Web root | `public_html/` → Laravel `public/`; `.htaccess` → `index.php` |
| Routing | `web.php` + `api.php` (Laravel) + `public_html/api/v1/*.php` (native API) |
| Middleware | Auth, RoleCheck, WalletCheck, CSRF, ThrottleRequests |
| Controllers | Auth, Order, Wallet, Bundle, Notification, Admin, Agent, Api |
| Models | User, Role, Permission, Wallet, WalletLedger, Order, OrderHistory, Bundle, BundlePackage, Notification, PasswordResetCode, AgentShop (and related) |
| Services | WalletService, OrderService, NotificationService, PaystackService, ReferralService |
| Native PHP API | `public_html/api/v1/index.php` — lightweight router; JSON responses |
| Jobs / Queues | DB queue: SendNotificationJob, ProcessRefundJob |

#### 3.3 URL Structure

| URL pattern | Purpose |
|-------------|---------|
| `datahomegh.shop` | Main homepage & buyer registration |
| `datahomegh.shop/login` | Login (all roles) |
| `datahomegh.shop/{agentSlug}` | Agent branded shop |
| `datahomegh.shop/admin/*` | Supplier super admin |
| `datahomegh.shop/agent/*` | Agent dashboard |
| `datahomegh.shop/buyer/*` | Buyer dashboard |
| `datahomegh.shop/api/v1/*` | REST API (native PHP) |

---

### 4. User Roles & Permissions

#### 4.1 Role Overview

Dynamic **RBAC**: Super Admin can create/edit/enable/disable roles and assign granular permissions.

#### 4.2 Role Definitions

**Supplier (Super Admin)**

- Single system-level account — not created via public registration
- Full platform control
- Approves agents and buyers (as per rules)
- Approves password resets via time-limited codes
- Sets pricing per role, daily order limits, bundle availability
- Credits / debits / freezes wallets
- Sees all agents; does **not** see individual agent buyer lists directly

**Agent**

- Registers; awaits Supplier approval
- On approval: unique shop link `datahomegh.shop/{shopName}`
- Manages own bundle packages and resale prices
- Sees **only** own buyers; can approve/reject/delete buyers from their store
- Own wallet and ledger visibility

**Buyer**

- Registers via main site or agent link; approval by Agent (agent link) or Supplier
- Places orders, wallet, order tracking
- Cannot edit or cancel placed orders

#### 4.3 Dynamic Permission Matrix (Summary)

| Permission | Supplier | Agent | Buyer |
|------------|----------|-------|-------|
| Create/Edit/Delete Roles | ✅ | ❌ | ❌ |
| Approve Agent Accounts | ✅ | ❌ | ❌ |
| Approve Buyer Accounts | ✅ | ✅ (own) | ❌ |
| View All Agents | ✅ | ❌ | ❌ |
| View All Buyers | ✅ (global) | ✅ (own) | ❌ |
| Create Bundle Packages | ✅ | ✅ (own) | ❌ |
| Set Role-Based Prices | ✅ | ✅ (buyers) | ❌ |
| Place Orders | ✅ | ✅ | ✅ |
| Update Order Status | ✅ | ✅ | ❌ |
| Bulk Update Orders | ✅ | ✅ | ❌ |
| Credit/Debit Wallets | ✅ | ❌ | ❌ |
| Freeze/Unfreeze Wallets | ✅ | ❌ | ❌ |
| Set Daily Order Limits | ✅ | ❌ | ❌ |
| Issue Password Reset Codes | ✅ | ❌ | ❌ |
| Post System Notifications | ✅ | ❌ | ❌ |
| Manage Profile & Branding | ✅ | ✅ | ✅ |
| Set WhatsApp / Channel Links | ✅ | ✅ | ✅ |

---

### 5. Profile & Branding

**Fields (summary):** profile picture (all); business logo (supplier, agent); display/shop name; WhatsApp; channel link (supplier, agent); phone; business description (supplier, agent); location/region (optional).

**Logos:** Supplier on main header/login; agent on shop page `{slug}`; default DataHomeGH placeholder if missing.

---

### 6. Agent Shop Links & Referral

- **Slug:** lowercase, spaces → hyphens, strip specials; unique (append `-2`, etc. if collision)
- **When:** auto on agent approval
- **Editable:** agent may request change; supplier approves
- **Shop page:** logo, name, description, WhatsApp, bundle list, registration (buyers tied to agent permanently)

---

### 7. Wallet System

#### 7.1 Overview

One wallet per user; sole payment method for orders; atomic operations; **immutable** ledger.

#### 7.2 Top-Up (Paystack)

1. User clicks “Add Funds”, enters amount  
2. Initialize Paystack transaction  
3. Redirect to Paystack checkout  
4. Webhook on success  
5. Verify **HMAC SHA-512** signature  
6. Credit wallet; ledger `CREDIT`, source `PAYSTACK`

#### 7.3 Debit (Order)

1. Submit order  
2. Balance ≥ amount  
3. Debit in DB transaction  
4. Order `PENDING`  
5. Ledger `DEBIT`, source `ORDER`, ref `order_id`

#### 7.4 Refund

Supplier/Agent marks **FAILED** → prompt refund → on confirm, credit wallet → status **REFUNDED** (final).

#### 7.5 Wallet Ledger Schema

| Field | Type | Description |
|--------|------|-------------|
| id | BIGINT PK | Auto-increment |
| user_id | BIGINT FK | Wallet owner |
| type | ENUM | CREDIT, DEBIT, REFUND |
| amount | DECIMAL(12,2) | GH₵ |
| balance_before | DECIMAL(12,2) | Snapshot before |
| balance_after | DECIMAL(12,2) | Snapshot after |
| source | VARCHAR | PAYSTACK / ORDER / ORDER_REFUND / ADMIN_* |
| reference | VARCHAR | Paystack ref or order id |
| note | TEXT | Optional |
| created_at | TIMESTAMP | Immutable |

---

### 8. Bundle Packages & Orders

- **Hierarchy:** Base packages (agent + supplier); resale plans (agent prices for buyers)
- **Stock:** Supplier sets units; decrement on PENDING; restore on REFUNDED; hide at 0
- **Order form:** network (required), Ghana phone format, bundle dropdown, total shown, mandatory confirmation checkbox (no edit/cancel/refund after SENT)

---

### 9. Order Status Lifecycle

```
PENDING → PROCESSING → SENT (final)
              ↘ FAILED → [refund?] → REFUNDED (final)
```

Only Supplier and Agent change status; buyers read-only.

| Status | Wallet | Final? |
|--------|--------|--------|
| PENDING | Debited on submit | No |
| PROCESSING | No change | No |
| SENT | No change | Yes |
| FAILED | No change yet | No |
| REFUNDED | Credited back | Yes |

**History:** `order_status_history` — order_id, old_status, new_status, changed_by, note, visible_to_buyer, timestamp (immutable).

---

### 10–12. Dashboards (Summary)

- **Supplier:** KPI cards, all orders (filters, bulk update, CSV export), agents (approve/hold/delete, reset codes, limits, pricing), wallet ops, RBAC UI  
- **Agent:** Today’s metrics, shop link, orders (own buyers), bundles, buyers, profile/branding, share/QR  
- **Buyer:** Balance + Add Funds, stepped order form, repeat last order, status badges + history, wallet history  

---

### 13. Password Reset Flow

1. Forgot password → username  
2. Message: contact admin (WhatsApp/phone); code expires in 20 minutes  
3. Supplier notified  
4. Supplier generates **8-char A–Z, 0–9** code (shown once); shares off-system  
5. User submits username + code + new password  
6. Validate: match, not expired, unused, attempts &lt; 3  
7. Success: update password, invalidate code, auto-login, notification  

**Rules:** SHA-256 hash in DB; 20 min expiry; single use; new code invalidates old; 3 failures → lock, contact admin again.

---

### 14. Notifications

- **Channels:** In-app (bell, unread); browser push (FCM + service worker)  
- **Automated:** order lifecycle, wallet credit, reset request to supplier, account approved  
- **Broadcast:** Supplier to all or by role; critical → full-screen modal when online  

---

### 15. Paystack

- Env: `PAYSTACK_PUBLIC_KEY`, `PAYSTACK_SECRET_KEY`, `PAYSTACK_PAYMENT_URL`  
- Channels: card, MTN MoMo, Telecel Cash, AirtelTigo Money, bank transfer  
- Webhook: **HMAC SHA-512** on raw body; 401 on failure; idempotent by reference  
- Amounts: Paystack pesewas; DB **DECIMAL(12,2)** in GH₵  

---

### 16. Native PHP REST API

- **Location:** `public_html/api/v1/`  
- **Router:** `.htaccess` → `index.php`  
- **Auth:** `Authorization: Bearer {token}` (`api_tokens`)  
- **Structure:** `config/` (db, cors), `middleware/` (Auth, RoleCheck), `controllers/`, `helpers/` (Response, Validator)  

**Endpoints (summary):** `/auth/*`, `/wallet`, `/wallet/ledger`, `/wallet/topup/*`, `/bundles`, `/orders`, `/orders/{id}/status`, `/admin/*` (supplier-only), etc.  

**Response shape:**

```json
{ "success": true, "data": { }, "message": "OK" }
{ "success": false, "error": "Unauthorized", "code": 401 }
```

---

### 17. Security Requirements

| Requirement | Implementation |
|-------------|----------------|
| RBAC | Middleware + Gates/Policies |
| CSRF | Laravel forms; webhooks use signatures |
| SQLi | Eloquent / PDO prepared statements only |
| Sessions | Encrypted cookies, HTTPS, ~2h idle |
| Passwords | bcrypt cost ≥ 12 |
| API rate limits | Throttle per token; login throttling; native `rate_limits` table |
| Validation | FormRequest + htmlspecialchars / PDO |
| Admin routes | Supplier middleware |
| Reset codes | Hashed, expiry, single use, attempt lockout |
| Paystack | HMAC before process |
| Uploads | MIME check, max 2MB, under `storage/` |
| XSS | Blade escaping + CSP |
| Git | Branch protection + pre-push hook (no direct push to main/develop) |

---

### 18. UI/UX Guidelines

- Mobile-first from **375px**; breakpoints sm/md/lg/xl as standard Tailwind  
- Minimal taps; spinners + toasts for feedback  
- **Colors:** primary `#FFD700`, dark `#1A1A2E`, navy `#16213E`, accent `#0F3460`, success `#1E8449`, warning `#D4AC0D`, danger `#C0392B`, info `#1A5276`, refund `#7D3C98`  
- **Badges:** PENDING yellow, PROCESSING blue, SENT green, FAILED red, REFUNDED purple  
- **Buyer order steps:** network cards (brand colors) → phone (Ghana flag, `050*******`) → bundle cards → confirm + checkbox + double-submit guard  

---

### 19. Database Schema (Core Tables)

| Table | Key ideas |
|-------|-----------|
| users | username, email nullable, phone, password, role_id, agent_id, shop_slug, shop_name, pictures, WhatsApp fields, business_description, status enum, wallet_frozen, daily_order_limit, soft deletes |
| roles / permissions / role_permission | RBAC pivot |
| wallets | user_id unique, balance, is_frozen |
| wallet_ledger | type CREDIT/DEBIT/REFUND, amounts, snapshots, source, reference, immutable created_at |
| bundle_packages | agent_id nullable, network enum, size, cost, stock, availability |
| resale_plans | bundle_package_id, agent_id, price, label, active |
| role_prices | role_id, bundle_package_id, price |
| orders | user_id, agent_id, network, phone, bundle_package_id, amount, status enum |
| order_status_history | order_id, statuses, changed_by, note, visible_to_buyer |
| notifications | user_id nullable (broadcast), title, message, type, read flag |
| password_reset_codes | user_id, code_hash, expires_at, used_at, attempts |
| fcm_tokens | user_id, token, device_type |
| api_tokens | user_id, token_hash, name, expires_at, last_used_at |
| paystack_transactions | user_id, reference unique, amount, status, channel, paid_at, metadata |

---

## Part C — Build & Deployment Guide

### 20. Laravel & Hostinger

#### 20.1 Directory layout

- **`public_html/`** — Apache document root: Laravel `public/` files (`index.php`, `.htaccess`), native **`api/v1/`**, symlink **`storage/`** → `storage/app/public/`  
- **`datahomegh/`** (example) — Laravel root **above** `public_html`: `app/`, `config/`, `database/`, `resources/`, `routes/`, `storage/`, `vendor/`, `.env`  

#### 20.2 Installation (SSH)

```bash
cd ~
composer create-project laravel/laravel datahomegh
cd datahomegh && cp .env.example .env
php artisan key:generate
```

#### 20.3 Database `.env` (example)

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=u123456789_datahomegh
DB_USERNAME=u123456789_admin
DB_PASSWORD=your_db_password
QUEUE_CONNECTION=database
```

> **Laravel 12+:** use `CACHE_STORE=file` (not `CACHE_DRIVER`) for file cache.

#### 20.4 Packages (planned)

```bash
composer require laravel/sanctum unicodeveloper/laravel-paystack
composer require kreait/laravel-firebase intervention/image
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
npm install && npm install -D tailwindcss alpinejs
npm run build   # commit public/build/ for Hostinger (no Node on server)
```

#### 20.5 Useful Artisan commands

`make:model -m`, `make:controller --resource`, `make:middleware`, `make:request`, `make:seeder`, `make:job`, `make:notification`, `make:policy`, `migrate`, `migrate:fresh --seed` (dev only), `storage:link`, `queue:work`, `schedule:run`, `route:list`, `config:cache` / `route:cache` / `view:cache`, `test --stop-on-failure`

#### 20.6 Cron (Hostinger)

```cron
* * * * * /usr/bin/php /home/u123456789/datahomegh/artisan schedule:run >> /dev/null 2>&1
* * * * * /usr/bin/php /home/u123456789/datahomegh/artisan queue:work --max-time=55 --stop-when-empty >> /dev/null 2>&1
```

No Redis/Supervisor on shared hosting — **`QUEUE_CONNECTION=database`** and cron-driven `queue:work`.

#### 20.7 Route groups (conceptual)

- Supplier: `auth` + `supplier` middleware, `admin` prefix, resources for orders, users, bundles, wallet credit, etc.  
- Agent: `auth` + `role:agent`, `agent` prefix, orders, buyers, bundles  
- **Public agent shop last:** `Route::get('/{agentSlug}', [ShopController::class, 'show']);`  

#### 20.8 PHP extensions (hPanel)

Enable: **PDO**, **pdo_mysql**, **mbstring**, **openssl**, **curl**, **zip**, **gd**, **fileinfo**, **tokenizer**, **xml**, **ctype**. PHP **8.2+**.

---

*This document is the single in-repo reference for product scope and hosting constraints. Update it when requirements change.*
