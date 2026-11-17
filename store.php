<?php
require_once __DIR__ . '/config.local.php';
require_once __DIR__ . '/bootstrap.php';

const SCOPES = ['ALL', 'TOP', 'MMP', 'SAE'];
const ENTITIES = ['TOP' => 'PT TOP', 'MMP' => 'CV MMP', 'SAE' => 'CV SAE'];

function db_is_mysql(): bool
{
    // DB_PATH is set by tests, which always run on in-memory SQLite.
    return !defined('DB_PATH') && defined('DB_DSN');
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    if (app_is_production() && (!defined('DB_DSN') || !defined('DB_USER') || !defined('DB_PASS'))) {
        throw new RuntimeException('Production database is not configured. Set DB_DSN, DB_USER, and DB_PASS in config.local.php.');
    }

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];

    if (db_is_mysql()) {
        $pdo = new PDO(DB_DSN, DB_USER, DB_PASS, $options);
        init_schema($pdo);
        return $pdo;
    }

    $dir  = defined('DATA_DIR') ? DATA_DIR : __DIR__ . '/data';
    $path = defined('DB_PATH') ? DB_PATH : $dir . '/app.sqlite';

    if ($path !== ':memory:') {
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }
        $ht = $dir . '/.htaccess';
        if (!file_exists($ht)) {
            file_put_contents($ht, "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Deny from all\n</IfModule>\n");
        }
    }

    $pdo = new PDO('sqlite:' . $path, null, null, $options);
    $pdo->exec('PRAGMA journal_mode = WAL');
    $pdo->exec('PRAGMA foreign_keys = ON');
    init_schema($pdo);

    return $pdo;
}

function init_schema(PDO $pdo): void
{
    if (db_is_mysql()) {
        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS monthly_inputs (
    period      VARCHAR(7)   NOT NULL,
    scope       VARCHAR(8)   NOT NULL,
    metric      VARCHAR(64)  NOT NULL,
    value       DOUBLE       NULL,
    note        TEXT         NULL,
    updated_at  DATETIME     NOT NULL,
    updated_by  VARCHAR(100) NOT NULL,
    PRIMARY KEY (period, scope, metric)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS monthly_actuals (
    period      VARCHAR(7)   NOT NULL,
    scope       VARCHAR(8)   NOT NULL,
    metric      VARCHAR(64)  NOT NULL,
    value       DOUBLE       NULL,
    detail_json LONGTEXT     NULL,
    captured_at DATETIME     NOT NULL,
    PRIMARY KEY (period, scope, metric)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS account_entity_map (
    site         VARCHAR(16)  NOT NULL,
    account_id   VARCHAR(64)  NOT NULL,
    account_name VARCHAR(255) NULL,
    entity       VARCHAR(8)   NOT NULL,
    updated_at   DATETIME     NULL,
    PRIMARY KEY (site, account_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS supplier_entity_map (
    site          VARCHAR(16)  NOT NULL,
    supplier_id   VARCHAR(64)  NOT NULL,
    supplier_name VARCHAR(255) NULL,
    entity        VARCHAR(8)   NOT NULL,
    updated_at    DATETIME     NULL,
    PRIMARY KEY (site, supplier_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS input_history (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    period     VARCHAR(7)   NULL,
    scope      VARCHAR(8)   NULL,
    metric     VARCHAR(64)  NULL,
    old_value  DOUBLE       NULL,
    new_value  DOUBLE       NULL,
    note       TEXT         NULL,
    changed_by VARCHAR(100) NULL,
    changed_at DATETIME     NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
    `key` VARCHAR(191) NOT NULL PRIMARY KEY,
    value TEXT         NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS custom_fields (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    label          VARCHAR(255) NOT NULL,
    group_name     VARCHAR(100) NOT NULL,
    role           VARCHAR(16)  NOT NULL DEFAULT 'cost',
    required       TINYINT      NOT NULL DEFAULT 0,
    allow_negative TINYINT      NOT NULL DEFAULT 0,
    sort_order     INT          NOT NULL DEFAULT 0,
    active         TINYINT      NOT NULL DEFAULT 1,
    created_at     DATETIME     NOT NULL,
    updated_at     DATETIME     NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
        return;
    }

    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS monthly_inputs (
    period      TEXT NOT NULL,
    scope       TEXT NOT NULL,
    metric      TEXT NOT NULL,
    value       REAL,
    note        TEXT,
    updated_at  TEXT NOT NULL,
    updated_by  TEXT NOT NULL,
    PRIMARY KEY (period, scope, metric)
);

CREATE TABLE IF NOT EXISTS monthly_actuals (
    period      TEXT NOT NULL,
    scope       TEXT NOT NULL,
    metric      TEXT NOT NULL,
    value       REAL,
    detail_json TEXT,
    captured_at TEXT NOT NULL,
    PRIMARY KEY (period, scope, metric)
);

CREATE TABLE IF NOT EXISTS account_entity_map (
    site         TEXT NOT NULL,
    account_id   TEXT NOT NULL,
    account_name TEXT,
    entity       TEXT NOT NULL,
    updated_at   TEXT,
    PRIMARY KEY (site, account_id)
);

CREATE TABLE IF NOT EXISTS supplier_entity_map (
    site         TEXT NOT NULL,
    supplier_id   TEXT NOT NULL,
    supplier_name TEXT,
    entity        TEXT NOT NULL,
    updated_at    TEXT,
    PRIMARY KEY (site, supplier_id)
);

CREATE TABLE IF NOT EXISTS input_history (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    period     TEXT,
    scope      TEXT,
    metric     TEXT,
    old_value  REAL,
    new_value  REAL,
    note       TEXT,
    changed_by TEXT,
    changed_at TEXT
);

CREATE TABLE IF NOT EXISTS settings (
    key   TEXT PRIMARY KEY,
    value TEXT
);

CREATE TABLE IF NOT EXISTS custom_fields (
    id             INTEGER PRIMARY KEY AUTOINCREMENT,
    label          TEXT NOT NULL,
    group_name     TEXT NOT NULL,
    role           TEXT NOT NULL DEFAULT 'cost',
    required       INTEGER NOT NULL DEFAULT 0,
    allow_negative INTEGER NOT NULL DEFAULT 0,
    sort_order     INTEGER NOT NULL DEFAULT 0,
    active         INTEGER NOT NULL DEFAULT 1,
    created_at     TEXT NOT NULL,
    updated_at     TEXT NOT NULL
);
SQL);
}

function metric_defs(bool $activeOnly = true): array
{
    $defs = [
        'sales_target' => [
            'label' => 'Sales target', 'group' => 'Sales', 'scopes' => ['ALL'],
            'allow_negative' => false, 'required' => true, 'unit' => 'IDR',
        ],
        'bank_balance' => [
            'label' => 'Bank balance', 'group' => 'Balance', 'scopes' => ['TOP', 'MMP', 'SAE'],
            'allow_negative' => true, 'required' => true, 'unit' => 'IDR',
        ],
        'inv_finished_bubble' => [
            'label' => 'Inventory — finished bubble', 'group' => 'Inventory', 'scopes' => ['ALL'],
            'allow_negative' => false, 'required' => true, 'unit' => 'IDR',
        ],
        'inv_finished_lakban' => [
            'label' => 'Inventory — finished lakban', 'group' => 'Inventory', 'scopes' => ['ALL'],
            'allow_negative' => false, 'required' => true, 'unit' => 'IDR',
        ],
        'inv_raw_material' => [
            'label' => 'Inventory — raw materials', 'group' => 'Inventory', 'scopes' => ['ALL'],
            'allow_negative' => false, 'required' => true, 'unit' => 'IDR',
        ],
        'inv_plastic_bag' => [
            'label' => 'Inventory — plastic bags', 'group' => 'Inventory', 'scopes' => ['ALL'],
            'allow_negative' => false, 'required' => true, 'unit' => 'IDR',
        ],
        'inv_recycle' => [
            'label' => 'Inventory — recycle', 'group' => 'Inventory', 'scopes' => ['ALL'],
            'allow_negative' => false, 'required' => true, 'unit' => 'IDR',
        ],
        'cost_bank_transport' => [
            'label' => 'Bank transport', 'group' => 'Costs', 'scopes' => ['TOP', 'MMP', 'SAE'],
            'allow_negative' => false, 'required' => true, 'unit' => 'IDR',
        ],
        'cost_bank_operation' => [
            'label' => 'Bank operations', 'group' => 'Costs', 'scopes' => ['TOP', 'MMP', 'SAE'],
            'allow_negative' => false, 'required' => true, 'unit' => 'IDR',
        ],
        'cost_petty_cash' => [
            'label' => 'Petty cash', 'group' => 'Costs', 'scopes' => ['TOP', 'MMP', 'SAE'],
            'allow_negative' => false, 'required' => true, 'unit' => 'IDR',
            'source' => 'api_or_manual', 'api' => 'petty_cash',
        ],
        'cost_salary_thr' => [
            'label' => 'Salaries / THR', 'group' => 'Costs', 'scopes' => ['TOP', 'MMP', 'SAE'],
            'allow_negative' => false, 'required' => true, 'unit' => 'IDR',
        ],
        'cost_electricity' => [
            'label' => 'Electricity', 'group' => 'Costs', 'scopes' => ['TOP'],
            'allow_negative' => false, 'required' => true, 'unit' => 'IDR',
        ],
        'production_rolls_bubble' => [
            'label' => 'Production — bubble rolls', 'group' => 'Production', 'scopes' => ['ALL'],
            'allow_negative' => false, 'required' => true, 'unit' => 'rolls',
        ],
        'production_rolls_lakban' => [
            'label' => 'Production — lakban rolls', 'group' => 'Production', 'scopes' => ['ALL'],
            'allow_negative' => false, 'required' => true, 'unit' => 'rolls',
        ],
        'sales_adjustment' => [
            'label' => 'Sales adjustment', 'group' => 'Adjustments', 'scopes' => ['TOP', 'MMP', 'SAE'],
            'allow_negative' => true, 'required' => false, 'unit' => 'IDR',
        ],
        'purchase_adjustment' => [
            'label' => 'Purchase adjustment', 'group' => 'Adjustments', 'scopes' => ['TOP', 'MMP', 'SAE'],
            'allow_negative' => true, 'required' => false, 'unit' => 'IDR',
        ],
    ];

    foreach (custom_field_defs($activeOnly) as $row) {
        $defs['custom_' . $row['id']] = custom_metric_def($row);
    }

    return $defs;
}

function custom_metric_def(array $row): array
{
    return [
        'label' => $row['label'],
        'group' => $row['group_name'],
        'scopes' => ['TOP', 'MMP', 'SAE'],
        'allow_negative' => (bool)$row['allow_negative'],
        'required' => (bool)$row['required'],
        'unit' => 'IDR',
        'role' => $row['role'],
        'source' => 'manual',
        'custom' => true,
        'sort_order' => (int)$row['sort_order'],
    ];
}

function is_cost_metric(string $metric, array $def): bool
{
    return ($def['role'] ?? '') === 'cost' || str_starts_with($metric, 'cost_');
}

function custom_field_defs(bool $activeOnly = true): array
{
    $sql = 'SELECT * FROM custom_fields';
    if ($activeOnly) {
        $sql .= ' WHERE active = 1';
    }
    $sql .= ' ORDER BY sort_order, id';
    return db()->query($sql)->fetchAll();
}

function custom_field_by_id(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM custom_fields WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

function save_custom_field(array $data, ?int $id = null): array
{
    $label = trim((string)($data['label'] ?? ''));
    $group = trim((string)($data['group_name'] ?? ''));
    $role = ($data['role'] ?? 'cost') === 'info' ? 'info' : 'cost';
    $required = !empty($data['required']) ? 1 : 0;
    $allowNegative = !empty($data['allow_negative']) ? 1 : 0;
    $sort = max(0, (int)($data['sort_order'] ?? 0));
    $active = !empty($data['active']) ? 1 : 0;

    $errors = [];
    if ($label === '') {
        $errors[] = 'Label is required.';
    }
    if ($group === '') {
        $errors[] = 'Group is required.';
    }
    if ($id !== null && custom_field_by_id($id) === null) {
        $errors[] = 'Field not found.';
    }
    if ($errors) {
        return $errors;
    }

    if ($id !== null) {
        $stmt = db()->prepare('UPDATE custom_fields SET label = ?, group_name = ?, role = ?, required = ?, allow_negative = ?, sort_order = ?, active = ?, updated_at = ? WHERE id = ?');
        $stmt->execute([$label, $group, $role, $required, $allowNegative, $sort, $active, now(), $id]);
    } else {
        $stmt = db()->prepare('INSERT INTO custom_fields (label, group_name, role, required, allow_negative, sort_order, active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$label, $group, $role, $required, $allowNegative, $sort, $active, now(), now()]);
    }

    return [];
}

function set_custom_field_active(int $id, bool $active): void
{
    $stmt = db()->prepare('UPDATE custom_fields SET active = ?, updated_at = ? WHERE id = ?');
    $stmt->execute([$active ? 1 : 0, now(), $id]);
}

function report_metric_defs(string $period): array
{
    $defs = metric_defs();

    $stmt = db()->prepare("SELECT DISTINCT metric FROM monthly_inputs WHERE period = ? AND metric LIKE 'custom_%'");
    $stmt->execute([$period]);
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $metric) {
        if (isset($defs[$metric])) {
            continue;
        }
        $id = (int)substr($metric, 7);
        $row = $id > 0 ? custom_field_by_id($id) : null;
        if ($row !== null) {
            $defs[$metric] = custom_metric_def($row);
        }
    }

    return $defs;
}

function valid_period(string $period): bool
{
    return (bool)preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period);
}

function period_label(string $period): string
{
    return date('F Y', strtotime($period . '-01'));
}

function entity_label(string $entity): string
{
    return ENTITIES[$entity] ?? $entity;
}

function scope_label(string $scope): string
{
    return $scope === 'ALL' ? 'Group' : entity_label($scope);
}

function last_saved(string $period): ?string
{
    $stmt = db()->prepare('SELECT MAX(updated_at) FROM monthly_inputs WHERE period = ?');
    $stmt->execute([$period]);
    $v = $stmt->fetchColumn();
    return $v ? (string)$v : null;
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

function get_inputs(string $period): array
{
    $stmt = db()->prepare('SELECT scope, metric, value FROM monthly_inputs WHERE period = ?');
    $stmt->execute([$period]);
    $out = [];
    foreach ($stmt->fetchAll() as $row) {
        $out[$row['scope']][$row['metric']] = (float)$row['value'];
    }
    return $out;
}

function save_inputs(string $period, array $values, string $note, string $user): array
{
    if (is_closed($period)) {
        return ['This period is closed. Reopen it before editing.'];
    }

    $defs    = metric_defs();
    $old     = get_inputs($period);
    $errors  = [];
    $changes = [];

    foreach ($defs as $metric => $def) {
        foreach ($def['scopes'] as $scope) {
            if (!array_key_exists($metric, $values[$scope] ?? [])) {
                continue;
            }
            $raw = trim((string)$values[$scope][$metric]);
            $hasOld = array_key_exists($metric, $old[$scope] ?? []);
            $oldVal = $hasOld ? $old[$scope][$metric] : null;

            if ($raw === '') {
                if ($hasOld) {
                    $changes[] = [$scope, $metric, $oldVal, null];
                }
                continue;
            }

            if (!is_numeric($raw)) {
                $errors[] = $def['label'] . ' (' . ($scope === 'ALL' ? 'group' : entity_label($scope)) . ') must be a number.';
                continue;
            }

            $val = (float)$raw;
            if (!is_finite($val) || floor($val) !== $val) {
                $errors[] = $def['label'] . ' (' . ($scope === 'ALL' ? 'group' : entity_label($scope)) . ') must be a whole number (no decimals).';
                continue;
            }

            if (!$def['allow_negative'] && $val < 0) {
                $errors[] = $def['label'] . ' (' . ($scope === 'ALL' ? 'group' : entity_label($scope)) . ') cannot be negative.';
                continue;
            }

            if (!$hasOld || abs($oldVal - $val) > 0.00001) {
                $changes[] = [$scope, $metric, $oldVal, $val];
            }
        }
    }

    $hasAdjustment = false;
    foreach ($changes as [$scope, $metric, $oldVal, $newVal]) {
        if ($newVal !== null && in_array($metric, ['sales_adjustment', 'purchase_adjustment'], true)) {
            $hasAdjustment = true;
        }
    }
    if ($hasAdjustment && trim($note) === '') {
        $errors[] = 'A note is required when entering a sales or purchase adjustment.';
    }

    if ($errors) {
        return $errors;
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $del = $pdo->prepare('DELETE FROM monthly_inputs WHERE period = ? AND scope = ? AND metric = ?');
        $set = $pdo->prepare('REPLACE INTO monthly_inputs (period, scope, metric, value, note, updated_at, updated_by) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $audit = $pdo->prepare('INSERT INTO input_history (period, scope, metric, old_value, new_value, note, changed_by, changed_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');

        foreach ($changes as [$scope, $metric, $oldVal, $newVal]) {
            if ($newVal === null) {
                $del->execute([$period, $scope, $metric]);
            } else {
                $set->execute([$period, $scope, $metric, $newVal, $note, now(), $user]);
            }
            $audit->execute([$period, $scope, $metric, $oldVal, $newVal, $note, $user, now()]);
        }

        set_setting('note:' . $period, $note);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        return ['Could not save: ' . $e->getMessage()];
    }

    return [];
}

function missing_inputs(string $period, array $api = []): array
{
    $inputs = get_inputs($period);
    $missing = [];
    foreach (metric_defs() as $metric => $def) {
        if (empty($def['required'])) {
            continue;
        }
        foreach ($def['scopes'] as $scope) {
            $hasManual = array_key_exists($metric, $inputs[$scope] ?? []);
            $hasApi = false;
            if (!$hasManual && ($def['source'] ?? 'manual') === 'api_or_manual' && !empty($def['api'])) {
                $hasApi = !empty($api[$def['api']]['present'][$scope]);
            }
            if (!$hasManual && !$hasApi) {
                $missing[] = ($scope === 'ALL' ? 'Group' : entity_label($scope)) . ' — ' . $def['label'];
            }
        }
    }
    return $missing;
}

function get_period_note(string $period): string
{
    return get_setting('note:' . $period) ?? '';
}

function get_setting(string $key): ?string
{
    $stmt = db()->prepare('SELECT value FROM settings WHERE `key` = ?');
    $stmt->execute([$key]);
    $v = $stmt->fetchColumn();
    return $v === false ? null : (string)$v;
}

function set_setting(string $key, string $value): void
{
    $stmt = db()->prepare('REPLACE INTO settings (`key`, value) VALUES (?, ?)');
    $stmt->execute([$key, $value]);
}

function is_closed(string $period): bool
{
    return get_setting('closed:' . $period) !== null;
}

function close_period(string $period, string $user): void
{
    set_setting('closed:' . $period, now() . '|' . $user);
}

function reopen_period(string $period): void
{
    $stmt = db()->prepare('DELETE FROM settings WHERE `key` = ?');
    $stmt->execute(['closed:' . $period]);
}

function get_actuals(string $period): array
{
    $stmt = db()->prepare('SELECT scope, metric, value FROM monthly_actuals WHERE period = ?');
    $stmt->execute([$period]);
    $out = [];
    foreach ($stmt->fetchAll() as $row) {
        $out[$row['scope']][$row['metric']] = (float)$row['value'];
    }
    return $out;
}

function save_actuals(string $period, array $rows): void
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare('DELETE FROM monthly_actuals WHERE period = ?')->execute([$period]);
        $stmt = $pdo->prepare('INSERT INTO monthly_actuals (period, scope, metric, value, detail_json, captured_at) VALUES (?, ?, ?, ?, ?, ?)');
        foreach ($rows as $row) {
            $stmt->execute([
                $period,
                $row['scope'],
                $row['metric'],
                (float)$row['value'],
                isset($row['detail']) ? json_encode($row['detail'], JSON_UNESCAPED_SLASHES) : null,
                now(),
            ]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function get_account_map(string $site): array
{
    $stmt = db()->prepare('SELECT account_id, entity FROM account_entity_map WHERE site = ?');
    $stmt->execute([$site]);
    return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
}

function get_supplier_map(string $site): array
{
    $stmt = db()->prepare('SELECT supplier_id, entity FROM supplier_entity_map WHERE site = ?');
    $stmt->execute([$site]);
    return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
}

function save_mappings(string $site, string $kind, array $map): int
{
    $table = $kind === 'supplier' ? 'supplier_entity_map' : 'account_entity_map';
    $idCol = $kind === 'supplier' ? 'supplier_id' : 'account_id';
    $nameCol = $kind === 'supplier' ? 'supplier_name' : 'account_name';
    $pdo = db();
    $count = 0;
    $del = $pdo->prepare("DELETE FROM $table WHERE site = ? AND $idCol = ?");
    $set = $pdo->prepare("REPLACE INTO $table (site, $idCol, $nameCol, entity, updated_at) VALUES (?, ?, ?, ?, ?)");

    $pdo->beginTransaction();
    try {
        foreach ($map as $id => $row) {
            $entity = strtoupper((string)($row['entity'] ?? ''));
            if (!isset(ENTITIES[$entity])) {
                $del->execute([$site, $id]);
                continue;
            }
            $set->execute([$site, $id, $row['name'] ?? '', $entity, now()]);
            $count++;
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    return $count;
}

// shortcut: suffix rule derived from the March 2026 report; replace with explicit mappings
function suggest_entity(string $site, string $name, string $kind = 'account'): string
{
    if ($site === 'site1') {
        return 'TOP';
    }
    $n = strtoupper($name);
    if ($kind === 'supplier') {
        if (str_contains($n, 'CAHAYA MULYO')) return 'MMP';
        if (str_contains($n, 'TOKO ATK')) return 'SAE';
        return 'MMP';
    }
    if (str_contains($n, 'RIZKI MOTOR')) return 'SAE';
    if (str_contains($n, 'LAKBAN') || str_contains($n, 'SAE') || str_contains($n, 'MMP')) return 'SAE';
    return 'MMP';
}

function calc_laba_rugi(float $sales, float $inventory, float $bank, float $purchases, float $costs): float
{
    return $sales + $inventory + $bank - $purchases - $costs;
}

function format_idr(float $value, int $decimals = 2): string
{
    return number_format($value, $decimals, ',', '.');
}
