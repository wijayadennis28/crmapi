<?php
// Run: php tests/store_test.php
define('DB_PATH', ':memory:');
require_once __DIR__ . '/../store.php';
require_once __DIR__ . '/../sources.php';

$failed = 0;
function check(string $label, bool $condition): void
{
    global $failed;
    if ($condition) {
        echo "PASS  $label\n";
    } else {
        $failed++;
        echo "FAIL  $label\n";
    }
}

$pdo = db();
$tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
check('schema created', in_array('monthly_inputs', $tables, true) && in_array('monthly_actuals', $tables, true));

$period = '2026-03';
$values = [];
foreach (metric_defs() as $metric => $def) {
    foreach ($def['scopes'] as $scope) {
        $values[$scope][$metric] = '1000';
    }
}

$errors = save_inputs($period, $values, 'test note', 'tester');
check('save all required inputs', $errors === []);
check('read saved input', get_inputs($period)['SAE']['bank_balance'] === 1000.0);
check('no missing inputs', missing_inputs($period) === []);
check('audit row written', (int)$pdo->query('SELECT COUNT(*) FROM input_history')->fetchColumn() > 0);

$errors = save_inputs($period, ['ALL' => ['sales_target' => '-5']], '', 'tester');
check('rejects negative non-adjustment', count($errors) === 1 && str_contains($errors[0], 'cannot be negative'));

$errors = save_inputs($period, ['TOP' => ['sales_adjustment' => '1100']], '', 'tester');
check('adjustment requires note', count($errors) === 1 && str_contains($errors[0], 'note is required'));

save_actuals($period, [
    ['scope' => 'TOP', 'metric' => 'sales_bubble', 'value' => 123.45, 'detail' => ['x' => 1]],
    ['scope' => 'TOP', 'metric' => 'purchases', 'value' => 50],
]);
$actuals = get_actuals($period);
check('actuals saved', $actuals['TOP']['sales_bubble'] === 123.45 && $actuals['TOP']['purchases'] === 50.0);

close_period($period, 'tester');
check('period closes', is_closed($period));
$errors = save_inputs($period, $values, 'test note', 'tester');
check('closed period blocks edits', count($errors) === 1 && str_contains($errors[0], 'closed'));
reopen_period($period);
check('period reopens', !is_closed($period));

save_mappings('site2', 'account', ['acc1' => ['name' => 'DUNIA PACKING _MMP', 'entity' => 'SAE']]);
check('account mapping saved', get_account_map('site2')['acc1'] === 'SAE');

save_mappings('site2', 'supplier', ['sup1' => ['name' => 'PT. CAHAYA MULYO PLASTIK', 'entity' => 'MMP']]);
check('supplier mapping saved', get_supplier_map('site2')['sup1'] === 'MMP');

check('site1 auto maps to TOP', suggest_entity('site1', 'ANY', 'account') === 'TOP');
check('MMP-tagged account maps to SAE', suggest_entity('site2', 'DUNIA PACKING _MMP', 'account') === 'SAE');
check('untagged account maps to MMP', suggest_entity('site2', 'GAJAH GALIH', 'account') === 'MMP');
check('RIZKI MOTOR exception maps to SAE', suggest_entity('site2', 'RIZKI MOTOR', 'account') === 'SAE');
check('CAHAYA MULYO supplier maps to MMP', suggest_entity('site2', 'PT. CAHAYA MULYO PLASTIK', 'supplier') === 'MMP');
check('TOKO ATK supplier maps to SAE', suggest_entity('site2', 'TOKO ATK 1368 SURABAYA', 'supplier') === 'SAE');
check('laba/rugi formula', abs(calc_laba_rugi(100, 50, 25, 30, 20) - 125.0) < 0.0001);

// ── Petty cash source rules ─────────────────────────────────
check('site1 petty cash maps to TOP', petty_cash_entity_for('site1', 'anything') === 'TOP');
check('site2 MMP maps to MMP', petty_cash_entity_for('site2', 'MMP') === 'MMP');
check('site2 SLP maps to SAE', petty_cash_entity_for('site2', ' SLP ') === 'SAE');
check('unknown petty cash label is unmapped', petty_cash_entity_for('site2', 'beli tiner 2 liter') === null);

$petty = [
    'values'  => ['TOP' => 10.0, 'MMP' => 20.0, 'SAE' => 30.0],
    'present' => ['TOP' => true, 'MMP' => true, 'SAE' => false],
];
$pettyInputs = ['MMP' => ['cost_petty_cash' => 5.0]];
$resolved = petty_cash_resolved($pettyInputs, $petty);
check('petty cash manual override wins', $resolved['MMP'] === 5.0);
check('petty cash API fallback', $resolved['TOP'] === 10.0);
check('petty cash no API/no manual is zero', $resolved['SAE'] === 0.0);
check('petty cash source override', petty_cash_source($pettyInputs, $petty) === 'Override');
check('petty cash source API', petty_cash_source([], $petty) === 'API');
check('petty cash source manual', petty_cash_source([], ['present' => ['TOP' => false, 'MMP' => false, 'SAE' => false]]) === 'Manual');

check('electricity is TOP only', metric_defs()['cost_electricity']['scopes'] === ['TOP']);

$apiPeriod = '2026-04';
$apiValues = [];
foreach (metric_defs() as $metric => $def) {
    if ($metric === 'cost_petty_cash') {
        continue;
    }
    foreach ($def['scopes'] as $scope) {
        $apiValues[$scope][$metric] = '1000';
    }
}
$errors = save_inputs($apiPeriod, $apiValues, 'test', 'tester');
check('required inputs saved without petty cash', $errors === []);
check('petty cash missing without API', count(missing_inputs($apiPeriod)) === 3);
check('petty cash filled by API', missing_inputs($apiPeriod, [
    'petty_cash' => ['present' => ['TOP' => true, 'MMP' => true, 'SAE' => true]],
]) === []);

exit($failed === 0 ? 0 : 1);
