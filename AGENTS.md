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
- **`index.php`**: includes header + sidebar with `$showSidebar = true`, `$base_path = ''`.
- `requireLogin()` redirects to `../login.php` from `pages/`, `login.php` from root. `requireRole([...])` redirects to `index.php` on failure.
- Every `pages/` file starts with: `require_once '../includes/database.php'; require_once '../includes/auth.php'; requireLogin(); $showSidebar = true; $base_path = '../';`
- Footer always: `require_once '../includes/footer.php';`
- **Exceptions** (no header/sidebar/footer, no `$base_path`, no `$showSidebar`): `purchase_details.php` (inline modal fragment), `installments.php` and `installment_payments.php` (dead redirect stubs → `sales.php`).

## Conventions & behaviors
- **No `TIMESTAMP`** — `DATE` for date columns, `DATETIME` only for `last_login` and `activity_logs.created_at`
- **PDO prepared statements only** — no string interpolation in SQL
- **DB creds**: hardcoded in `includes/database.php` — `root` / empty, `localhost`
- **CSRF forms**: `csrfField()` emitted on all POST forms, but **`verifyCsrf()` is never called** server-side.
- **XSS**: `e($str)` (wraps `htmlspecialchars`) for all output
- **Helpers in auth.php**: `formatMoney($n)`, `formatDate($d)`, `getSetting($pdo, $key)`, `StrLimit($str, 50)`, `logActivity($pdo, $action, $desc)`
- **Sidebar nav**: `basename($_SERVER['PHP_SELF'])` — from `pages/` this is just `sales.php` (no `pages/` prefix). Sidebar hrefs go to `$base_path . 'pages/...'`.
- **DataTables**: auto-initialized on all `.table` with `<thead>` in footer. pageLength: 25, skip with `data-skip-dt` attribute.
- **Dark/light theme**: `data-theme` on `<html>`, persisted in localStorage via `toggleTheme()`. CSS uses `[data-theme="dark"]` custom properties in `assets/style.css`.
- **Duplicate UNIQUE entries** (`bike_stock` serials): caught as `PDOException 23000`, shown as inline error, not a crash.
- **`config/` and `uploads/` directories are empty** — no config files live there.

## Inventory system
- **`bike_brands` → `bike_models` → `bike_variants` → `bike_stock`** — bike inventory with individual serial tracking (chassis_no, motor_no, battery_serial, charger_serial). All four serial columns are UNIQUE; empty values stored as NULL.
- **`products` table dropped** (legacy — replaced by bike_stock tracking).

## Pages special cases
- **`customers_get.php`, `suppliers_get.php`**: AJAX JSON endpoints — return `json_encode`, no header/sidebar/footer.
- **`settings.php`**: uses `ON DUPLICATE KEY UPDATE`, no explicit `$base_path` usage pattern.
- **`purchases.php`**: supports `?redirect=view` to go to `purchase_view.php` after save.
- **`expenses.php`**: has `paid_by` field.
- **`purchase_details.php`**: modal fragment — no header/sidebar/footer, no `$base_path`.

## Key business flow
- **Stock status lifecycle**: `ordered` → `in_stock` → `sold` / `booked` / `damaged`
- **Purchase**: creates `purchases` + `bike_stock` rows directly (each row = 1 bike with serials), status = `ordered`. Supplier ledger credited with total. No separate "receive stock" step needed for new purchases.
- **Receive bikes**: On `purchase_view.php`, click **Receive** (per bike or Receive All) → `bike_stock.status` changes from `ordered` → `in_stock`. Purchase status auto-updates: `ordered` → `partial` → `completed`.
- **`receive_stock.php`** still exists for legacy purchases (pre-change) but is removed from sidebar.
- **Direct stock entry** (`stock_entry.php`): two forms — Add Variant (creates `bike_variants` row) and Add Stock Unit (creates `bike_stock` with serials). Used for stock arriving without a purchase order. New stock gets status `in_stock`.
- **`bike_stock` has `purchase_price` / `sale_price` columns** — can override variant-level prices per unit.
- **Sale**: `cash` or `booking` (UI dropdown). DB-level: `cash`/`installment` marks stock `sold`; `booking` marks stock `booked`. Use **Complete Delivery** button on `sale_list.php` to mark booked stock as `sold` (blocked if `remaining_amount > 0`). Creates customer ledger debit entry.
- **Invoice print**: rendered inline on `sales.php?print=id` (redirects after save, or link from `sale_list.php` with `target="_blank"`). Invoice format: `INV-YYYYMMDD-NNN`.
- **Customer ledger**: sale = `debit=totalAmt` (customer owes), down payment = `credit=downPay`. Balance = opening + sum(debit − credit). Manual "Add Entry" linked to a sale (via `link_sale_id`) auto-updates `sales.remaining_amount` and `payment_status`.
- **Supplier ledger**: purchase order = `credit=total` (we owe). Payment = `debit=amount`. Balance = opening + sum(credit − debit).
- **Cash/Bank book**: auto-inserted when `sales.php`, `customer_ledger.php`, or `supplier_ledger.php` POST with `payment_method=cash` or `payment_method=bank`. INSERT always stores `balance=0`; running balance computed at display time.
- **`cash_book.php` / `bank_book.php`**: date-filtered listing with running balance. No CRUD — entries are side effects of sales/ledger actions.
- **`stock_ledger.php`**: searchable audit trail of every bike_stock unit with purchase invoice and sale invoice references.
- **`installments.php` / `installment_payments.php`**: dead redirect stubs to `sales.php`. Installment DB tables exist but have no active UI.

## Miscellaneous
- No build tooling, no package manager, no test runner — pure PHP via XAMPP/Apache.
- `logActivity($pdo, $action, $description)` writes to `activity_logs` — call after significant mutations.
