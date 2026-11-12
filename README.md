# TOP CRM Dashboard + Monthly Report

Two parts in one PHP app:

- **Dashboard** (`index.php`, `export.php`) — live monthly counts/totals from EspoCRM.
- **Monthly report** (`input.php`, `mapping.php`, `report.php`) — combines API sales/purchases with figures that only exist outside EspoCRM (target, bank, inventory, some costs, production rolls).

No Composer, no framework. PHP 8.1+, SQLite.

## Setup

1. Copy the local config:

   ```bash
   cp config.local.example.php config.local.php
   ```

2. Generate a password hash and put it in `config.local.php`:

   ```bash
   php -r 'echo password_hash("your-password", PASSWORD_DEFAULT), "\n";'
   ```

3. Make sure `data/` is writable by PHP. The app creates `data/app.sqlite`.
   `data/.htaccess` denies web access on Apache. On Nginx or a custom setup, move `DATA_DIR` outside the document root or add an equivalent deny rule.

4. Open `login.php` and sign in. The dashboard itself remains public; the monthly report pages require login.

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

3. **Report** (`report.php`) — review the combined figures. “Close month” freezes the API sales and purchases in `monthly_actuals`; reopen if something changes.

## What comes from the API

- Sales by month and entity: `SalesOrder.grandTotalAmount` filtered by `dateOrdered`.
- Purchases: `PurchaseOrder.grandTotalAmount` filtered by `dateOrdered`.
- Lakban sales/rolls: accounts containing `LAKBAN`.
- Daily actual sales: `SalesOrder.dateOrdered`.

Everything else is manual because the CRM has no bank, inventory valuation, target, or reliable production/cost module.

## Backups

- Code: git tag per release, plus a zip outside the repo.
- Data: copy `data/app.sqlite` (and `-wal`/`-shm` when present) before schema changes or deploy. Do not put the SQLite file in git.

## Tests

```bash
php tests/store_test.php
```

## Security notes

- `config.php` still contains live EspoCRM API keys. Rotate them and move them to a local untracked file before making the repo public.
- Delete or password-protect `test.php` and `HOW_TO_CUSTOMIZE.php` in production.
- The monthly report pages use a session login and CSRF tokens; the old dashboard/export do not.
