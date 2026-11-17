# TOP CRM Dashboard + Monthly Report

Two parts in one PHP app:

- **Dashboard** (`index.php`, `export.php`) — live monthly counts/totals from EspoCRM.
- **Monthly report** (`input.php`, `mapping.php`, `report.php`) — combines API sales/purchases with figures that only exist outside EspoCRM (target, bank, inventory, some costs, production rolls).

No Composer, no framework. PHP 8.1+. MySQL in production; SQLite for local dev and tests.

## Run locally

```bash
php -S 127.0.0.1:8000 router.php
```

`.htaccess` handles clean URLs on Apache/LiteSpeed. PHP's built-in server ignores it, so `router.php` provides the same routes locally.

## Setup

1. Copy the local config:

   ```bash
   cp config.local.example.php config.local.php
   ```

2. Generate a password hash and put it in `config.local.php`:

   ```bash
   php -r 'echo password_hash("your-password", PASSWORD_DEFAULT), "\n";'
   ```

3. Storage:
   - **Production (MySQL):** set `APP_ENV` to `production` and uncomment `DB_DSN`, `DB_USER`, `DB_PASS` in `config.local.php`, pointing them at the hPanel database. The tables are created automatically on the first report page load. Credentials stay in `config.local.php`, which is gitignored. If production is missing its DB settings (with `APP_ENV` set to `production`, or unset), the app now fails loudly instead of silently falling back to SQLite.
   - **Local dev / tests (SQLite):** set `APP_ENV` to `local` and leave the `DB_*` lines commented out. The app creates `data/app.sqlite`; make sure `data/` is writable. `data/.htaccess` denies web access on Apache. On Nginx or a custom setup, move `DATA_DIR` outside the document root or add an equivalent deny rule.

4. Open `/login` and sign in. The dashboard itself remains public; the monthly report pages require login.

## Deploy checklist

- Upload the tracked `.htaccess` file (hidden file) to the document root. It provides clean URLs and blocks private files. Verify:
  - `/input.php` → `301 /input`
  - `/README.md` and `/config.php` → `404`
- Keep the production `config.local.php` on the server. Set `APP_ENV` to `production` and fill in the MySQL credentials. Do not overwrite it with the local copy.
- Make `data/` and `cache/` writable.
- Purge the Hostinger CDN after a deploy. CSS and JS links are versioned by file modification time, so a deploy gets fresh assets without changing the URLs manually.
- Run the live smoke test:
  ```bash
  BASE_URL=https://dashboard.trinitioptimapackindo.com bash tests/live_smoke.sh
  ```
  Add `COOKIE='PHPSESSID=...'` to also check a signed-in `/input`.
- If a page returns 500, check `data/error.log` (private) or the hPanel error log. Uncaught errors now log there instead of only showing a blank 500 page.

## Monthly workflow

1. **Mappings** (`mapping.php`) — choose the instance and month, then map each customer/supplier to PT TOP, CV MMP, or CV SAE. Empty rows use the fallback name rule:
   - site1 → PT TOP
   - site2 account containing `LAKBAN`/`SAE`/`MMP` → CV SAE, otherwise CV MMP
   - RIZKI MOTOR → CV SAE
   - CAHAYA MULYO supplier → CV MMP; TOKO ATK supplier → CV SAE

2. **Monthly Input** (`input.php`) — enter:
   - sales target
   - bank balance per entity
   - five inventory buckets
   - costs per entity: bank transport, bank operations, petty cash, salaries/THR, electricity
   - production rolls (bubble and lakban)
   - optional sales/purchase adjustments, with a note

3. **Report** (`report.php`) — review the combined figures. “Close month” freezes the API sales and purchases and the resolved petty cash in `monthly_actuals`; reopen if something changes.

## Custom fields

`/fields` lets you define a manual field once. It then appears in every monthly form for PT TOP, CV MMP, and CV SAE.

- Per entity, money only.
- Role `cost` adds the field to total costs and Laba/Rugi.
- Role `info` displays in a separate “Custom data” report section and does not affect totals.
- Required custom fields join the close validation.
- Deactivate a field to hide it from new months; historical reports keep the values.

## URLs

Public routes: `/`, `/input`, `/mapping`, `/report`, `/fields`, `/login`, `/export`.

- `.htaccess` rewrites clean routes to the PHP files and 301s direct `.php` URLs to the clean form.
- `router.php` does the same for local development.
- Blocked from the web: `config*`, include files, `router.php`, `sources.php`, `test.php`, `HOW_TO_CUSTOMIZE.php`, `tests/`, `docs/`, `cache/`, `data/`, and `.md` files.

If the host ignores `.htaccess`, the `.php` files remain reachable; use a front controller with a `public/` docroot for the strongest isolation.

## Data sources

| Field | Source |
|---|---|
| Sales by month and entity | `SalesOrder.grandTotalAmount` filtered by `dateOrdered`, split with the account mapping |
| Purchases | `PurchaseOrder.grandTotalAmount` filtered by `dateOrdered`, split with the supplier mapping |
| Petty cash | `CPettyCash` when the month has records; manual fallback and optional override |
| Salaries / THR | Manual for now; `CPayroll` does not yet have a usable monthly/entity split |
| Bank balances | Manual |
| Inventory (5 buckets) | Manual; the API has current stock, not a historical valuation |
| Bank transport / operations | Manual |
| Electricity | Manual; required for PT TOP only |
| Production rolls | Manual; production logs are unreliable |
| Sales target | Manual |
| Laba/Rugi | Computed |

`CPettyCash` sits on site1 (all PT TOP) and site2 (`MMP`, `SLP`). The report maps `MMP` to CV MMP and `SLP` to CV SAE. Rows without one of those labels are shown as an unmapped warning and are not silently assigned.

## Backups

- Code: git tag per release, plus a zip outside the repo.
- Data: export the MySQL database (`mysqldump`, or hPanel → phpMyAdmin → Export) before schema changes or deploy. For the SQLite fallback, copy `data/app.sqlite` (and `-wal`/`-shm` when present). Do not put either in git.

## Design system

- `PRODUCT.md` — product truth: users, workflow, constraints.
- `DESIGN.md` — the Packing Docket visual system: tokens, components, rules.
- The UI is deliberately dense and print-first. Read `DESIGN.md` before adding a new screen.

## Tests

```bash
php tests/store_test.php
BASE_URL=http://127.0.0.1:8000 bash tests/routes_test.sh
BASE_URL=https://dashboard.trinitioptimapackindo.com bash tests/live_smoke.sh
```

## Security notes

- `config.php` still contains live EspoCRM API keys. Rotate them and move them to a local untracked file before making the repo public.
- `.htaccess` is tracked in git and must be uploaded on every deploy; it is a hidden file. It blocks `test.php`, `HOW_TO_CUSTOMIZE.php`, `sources.php`, `bootstrap.php`, `tests/`, `docs/`, `cache/`, and `data/` in production. If the host ignores `.htaccess`, remove or move those files outside the webroot.
- `data/error.log` contains stack traces and paths. Keep `data/` private and rotate the log if it grows.
- The monthly report pages use a session login and CSRF tokens; the old dashboard/export do not.
