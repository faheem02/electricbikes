# AGENTS.md — electricbikes

## Stack
- **PHP 8+** (plain, no framework), **PDO** (no MySQLi anywhere)
- Bootstrap 5, jQuery, DataTables, Chart.js, SweetAlert2, **Bootstrap Icons** (`bi-*`), Font Awesome (`fa-*`)
- DB `electricbikes`: **auto-created + tables migrated on every request** via `includes/database.php` (lines 16–306 create tables, lines 312–369 ALTER TABLE for backward compat). No manual import needed.
- **`database.sql`** is a legacy/outdated schema — never import it manually.
- Auth: plain text passwords (direct `===` comparison), session-based, roles: super_admin, admin, salesman, cashier, service_manager
- Default login: `admin` / `admin@123`

## Entrypoint / path rules
- **Root files** (`login.php`, `index.php`, `logout.php`): `$base_path = ''`
- **`pages/` files**: `$base_path = '../'` — set before including header/sidebar
- `requireLogin()` redirects to `../login.php` from `pages/` and `login.php` from root
- `requireRole([...])` redirects to `index.php` on failure, not login
- Every page starts with: `require_once '../includes/database.php'; require_once '../includes/auth.php'; requireLogin(); $showSidebar = true; $base_path = '../';`
- Footer always: `require_once '../includes/footer.php';`

## Conventions
- **No TIMESTAMP** — use `DATE` for date columns, `DATETIME` only for `last_login` and `activity_logs.created_at`
- **PDO prepared statements only** — no `mysqli_*`, no string interpolation in SQL
- **CSRF** on all POST forms: `csrfField()` in form, `verifyCsrf()` at top of handler
- **XSS**: `e($str)` (wraps `htmlspecialchars`) for all output
- **Sidebar nav**: uses `basename($_SERVER['PHP_SELF'])` to match active page — from `pages/` this is just `sales.php` (no `pages/` prefix)
- **`StrLimit($str, 50)`** helper in auth.php for truncating long strings
- **DataTables**: auto-initialized on all `.table` elements with `<thead>` in footer (pageLength: 25, skip with `data-skip-dt`)
- **Dark/light theme**: persisted in localStorage via `toggleTheme()`, `data-theme` on `<html>`
- **`cash_book` table** exists in DB but has **no UI page** — only populated programmatically
- **Duplicate UNIQUE entries** (`bike_stock` serials): caught as `PDOException 23000`, shown as inline error, not a crash

## Key behaviors
- DB auto-creates **all tables + defaults** every request (`CREATE TABLE IF NOT EXISTS` loop) — no migration tool
- Stock status lifecycle: `in_stock` → `sold`/`booked`/`damaged`
- **`bike_stock`**: `chassis_no`, `motor_no`, `battery_serial`, `charger_serial` are all UNIQUE. Empty values stored as NULL (MySQL treats multiple NULLs as distinct).
- **Purchase**: creates purchase order (`purchases` + `purchase_items`), no `bike_stock` rows yet. Stock received later via `receive_stock.php` (in batches). Purchase status: `ordered` → `partial` → `completed`. Supplier ledger credited on order.
- **Sale**: `cash`/`installment` marks stock `sold`; `booking` marks stock `booked`. Use **Complete Delivery** button on `sale_list.php` to mark booked stock as `sold`. Creates customer ledger debit entry. Invoice print at `?print=id`.
- **`installments.php`** and **`installment_payments.php`** are dead pages (single-line `header('Location: sales.php')` redirect).
- No build tooling, no package manager, no test runner — pure PHP via XAMPP/Apache
