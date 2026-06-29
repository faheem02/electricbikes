# AGENTS.md — electricbikes

## Stack
- **PHP 8+** (no framework), **PDO** (no MySQLi anywhere)
- Bootstrap 5, jQuery, DataTables, Chart.js, SweetAlert2, Bootstrap Icons, Font Awesome
- DB `electricbikes`: **auto-created + tables migrated on every request** via `includes/database.php` (CREATE TABLE IF NOT EXISTS + ALTER TABLE for backward compat). Never import `database.sql` manually — it is legacy.
- Auth: plain text passwords (`===` comparison), session-based. Roles: super_admin, admin, salesman, cashier, service_manager. Default: `admin` / `admin@123`

## Entrypoint / path rules
- **Root files** (`login.php`, `index.php`): `$base_path = ''`. **`pages/` files**: `$base_path = '../'` — set before including header/sidebar.
- **`logout.php`**: standalone — does `session_start()` + `session_destroy()` with no `database.php`/`auth.php` includes. Redirects to `login.php`.
- **`login.php`**: includes `database.php` + `auth.php` but does NOT call `requireLogin()`. No `$base_path`, no `$showSidebar`, no sidebar.
- `requireLogin()` redirects to `../login.php` from `pages/`, `login.php` from root. `requireRole([...])` redirects to `index.php` on failure.
- Every `pages/` file starts with: `require_once '../includes/database.php'; require_once '../includes/auth.php'; requireLogin(); $showSidebar = true; $base_path = '../';`
- Footer always: `require_once '../includes/footer.php';`

## Conventions & behaviors
- **No `TIMESTAMP`** — use `DATE` for date columns, `DATETIME` only for `last_login` and `activity_logs.created_at`
- **PDO prepared statements only** — no string interpolation in SQL
- **CSRF forms**: `csrfField()` is emitted on all POST forms, but **`verifyCsrf()` is never called** in any page handler — tokens are generated but never validated server-side.
- **XSS**: `e($str)` (wraps `htmlspecialchars`) for all output
- **Sidebar nav**: `basename($_SERVER['PHP_SELF'])` — from `pages/` this is just `sales.php` (no `pages/` prefix)
- **`StrLimit($str, 50)`** helper in auth.php
- **DataTables**: auto-initialized on all `.table` with `<thead>` in footer. pageLength: 25, skip with `data-skip-dt`
- **Dark/light theme**: `data-theme` on `<html>`, persisted in localStorage via `toggleTheme()`
- **Duplicate UNIQUE entries** (`bike_stock` serials): caught as `PDOException 23000`, shown as inline error, not a crash
- **`config/` directory is empty** — no config files live there.

## Dual inventory system
- **`products` table** + `products.php`, `product_add.php`, `product_view.php`, `product_edit.php` — generic CRM-style products (name, model, brand, price, stock count). Simple CRUD, no serial tracking.
- **`bike_brands` → `bike_models` → `bike_variants` → `bike_stock`** — bike inventory with individual serial tracking (chassis_no, motor_no, battery_serial, charger_serial). All four serial columns are UNIQUE; empty values stored as NULL.

## Key business flow
- **Stock status lifecycle**: `in_stock` → `sold` / `booked` / `damaged`
- **Purchase**: creates `purchases` + `purchase_items`, no `bike_stock` rows yet. Supplier ledger credited with total. Stock received later via `receive_stock.php` (one row per bike). Purchase status: `ordered` → `partial` → `completed`.
- **Direct stock entry** (`stock_entry.php`): two forms — Add Variant (creates `bike_variants` row) and Add Stock Unit (creates `bike_stock` with serials). Used for stock arriving without a purchase order.
- **`bike_stock` has `purchase_price` / `sale_price` columns** (added via migration) — can override variant-level prices per unit.
- **Sale**: `cash` or `booking` (UI dropdown only offers these two). `cash`/`installment` (DB-level) marks stock `sold`; `booking` marks stock `booked`. Use **Complete Delivery** button on `sale_list.php` to mark booked stock as `sold`. Creates customer ledger debit entry. Invoice print at `?print=id`.
- **Customer ledger**: sale = `debit=totalAmt` (customer owes), down payment = `credit=downPay`. Balance = opening + sum(debit − credit).
- **Supplier ledger**: purchase order = `credit=total` (we owe). Payment = `debit=amount`. Balance = opening + sum(debit − credit).
- **Cash/Bank book**: auto-inserted when `sales.php`/`customer_ledger.php`/`supplier_ledger.php` POST with `payment_method=cash` or `payment_method=bank`. **`INSERT` always stores `balance=0`**; running balance is computed at display time by iterating all rows chronologically.
- **`cash_book.php`** / **`bank_book.php`**: date-filtered listing with running balance, opening/closing balance display. No CRUD — entries are only created as side effects of sales/ledger actions.
- **`stock_ledger.php`**: searchable audit trail of every bike_stock unit with purchase invoice and sale invoice references.
- **`installments.php`** and **`installment_payments.php`** are dead redirect stubs (to `sales.php`). The installments system exists at DB level but has no active UI.

## Miscellaneous
- No build tooling, no package manager, no test runner — pure PHP via XAMPP/Apache.
- `logActivity($pdo, $action, $description)` writes to `activity_logs` — call after significant mutations.
