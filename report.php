<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/store.php';
require_once __DIR__ . '/ui.php';
require_once __DIR__ . '/EspoClient.php';
require_login();

function build_sales(string $period): array
{
    $from = $period . '-01';
    $to   = date('Y-m-t', strtotime($from));
    $out  = ['data' => [], 'detail' => [], 'auto' => 0, 'errors' => []];

    foreach (['site1', 'site2'] as $site) {
        try {
            $client = new EspoClient($site);
            $rows   = $client->fetchMonthly('SalesOrder', $from, $to);
        } catch (RuntimeException $e) {
            $out['errors'][] = "$site sales: " . $e->getMessage();
            continue;
        }

        $map = get_account_map($site);
        foreach ($rows as $row) {
            $accountId = $row['accountId'] ?? '';
            $account   = $row['accountName'] ?? '';
            $entity    = $map[$accountId] ?? suggest_entity($site, $account, 'account');
            // shortcut: line split by account name; upgrade to item-level if orders mix bubble/lakban
            $line      = stripos($account, 'lakban') !== false ? 'lakban' : 'bubble';
            if ($site !== 'site1' && !isset($map[$accountId])) {
                $out['auto']++;
            }
            $amount = (float)($row['grandTotalAmount'] ?? 0);
            $out['data'][$entity][$line] = ($out['data'][$entity][$line] ?? 0) + $amount;
            $out['detail'][$entity][$line][] = [
                'name' => $row['name'] ?? '',
                'account' => $account,
                'date' => $row['dateOrdered'] ?? '',
                'amount' => $amount,
            ];
        }
    }

    return $out;
}

function build_purchases(string $period): array
{
    $from = $period . '-01';
    $to   = date('Y-m-t', strtotime($from));
    $out  = ['data' => [], 'detail' => [], 'auto' => 0, 'errors' => []];

    foreach (['site1', 'site2'] as $site) {
        try {
            $client = new EspoClient($site);
            $rows   = $client->fetchMonthly('PurchaseOrder', $from, $to);
        } catch (RuntimeException $e) {
            $out['errors'][] = "$site purchases: " . $e->getMessage();
            continue;
        }

        $map = get_supplier_map($site);
        foreach ($rows as $row) {
            $supplierId = $row['supplierId'] ?? $row['accountId'] ?? '';
            $supplier   = $row['supplierName'] ?? $row['accountName'] ?? '';
            $entity     = $map[$supplierId] ?? suggest_entity($site, $supplier, 'supplier');
            if ($site !== 'site1' && !isset($map[$supplierId])) {
                $out['auto']++;
            }
            $amount = (float)($row['grandTotalAmount'] ?? 0);
            $out['data'][$entity] = ($out['data'][$entity] ?? 0) + $amount;
            $out['detail'][$entity][] = [
                'name' => $row['name'] ?? '',
                'supplier' => $supplier,
                'date' => $row['dateOrdered'] ?? '',
                'amount' => $amount,
            ];
        }
    }

    return $out;
}

function build_actual_rows(string $period): array
{
    $sales     = build_sales($period);
    $purchases = build_purchases($period);
    $errors    = array_merge($sales['errors'], $purchases['errors']);
    $rows      = [];

    foreach (ENTITIES as $entity => $label) {
        foreach (['bubble', 'lakban'] as $line) {
            $rows[] = [
                'scope'  => $entity,
                'metric' => 'sales_' . $line,
                'value'  => $sales['data'][$entity][$line] ?? 0,
                'detail' => $sales['detail'][$entity][$line] ?? [],
            ];
        }
        $rows[] = [
            'scope'  => $entity,
            'metric' => 'purchases',
            'value'  => $purchases['data'][$entity] ?? 0,
            'detail' => $purchases['detail'][$entity] ?? [],
        ];
    }

    return [$rows, $sales['auto'] + $purchases['auto'], $errors];
}

$period = $_POST['period'] ?? $_GET['period'] ?? date('Y-m');
if (!valid_period($period)) {
    $period = date('Y-m');
}

$errors = [];
$closed = is_closed($period);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'close') {
        $missing = missing_inputs($period);
        if ($missing) {
            $errors[] = 'Cannot close: ' . count($missing) . ' required input(s) are still missing.';
        } else {
            [$rows, $auto, $apiErrors] = build_actual_rows($period);
            if ($apiErrors) {
                $errors = array_merge($errors, $apiErrors);
            } else {
                save_actuals($period, $rows);
                close_period($period, auth_user());
                header('Location: report.php?period=' . urlencode($period) . '&closed=1');
                exit;
            }
        }
    } elseif ($action === 'reopen') {
        reopen_period($period);
        header('Location: report.php?period=' . urlencode($period) . '&reopened=1');
        exit;
    }
}

$inputs = get_inputs($period);
$closed = is_closed($period);
$actuals = $closed ? get_actuals($period) : [];
$autoMapped = 0;

if ($actuals) {
    $salesData = [];
    $purchaseData = [];
    foreach ($actuals as $scope => $metrics) {
        foreach ($metrics as $metric => $value) {
            if (str_starts_with($metric, 'sales_')) {
                $salesData[$scope][substr($metric, 6)] = $value;
            } elseif ($metric === 'purchases') {
                $purchaseData[$scope] = $value;
            }
        }
    }
} else {
    $sales     = build_sales($period);
    $purchases = build_purchases($period);
    $salesData = $sales['data'];
    $purchaseData = $purchases['data'];
    $autoMapped = $sales['auto'] + $purchases['auto'];
    $errors = array_merge($errors, $sales['errors'], $purchases['errors']);
}

$target = array_key_exists('sales_target', $inputs['ALL'] ?? []) ? $inputs['ALL']['sales_target'] : null;

$salesAdjustment = [];
$purchaseAdjustment = [];
$bank = [];
$costs = [];
$totalSales = 0.0;
$totalBubble = 0.0;
$totalLakban = 0.0;

foreach (ENTITIES as $entity => $label) {
    $bubble = (float)($salesData[$entity]['bubble'] ?? 0);
    $lakban = (float)($salesData[$entity]['lakban'] ?? 0);
    $adj    = (float)($inputs[$entity]['sales_adjustment'] ?? 0);
    $salesAdjustment[$entity] = $adj;
    $totalBubble += $bubble;
    $totalLakban += $lakban;
    $totalSales += $bubble + $lakban + $adj;

    $purchasesEntity = (float)($purchaseData[$entity] ?? 0);
    $purchaseAdjustment[$entity] = (float)($inputs[$entity]['purchase_adjustment'] ?? 0);
    $bank[$entity] = (float)($inputs[$entity]['bank_balance'] ?? 0);

    foreach (array_keys(metric_defs()) as $metric) {
        if (str_starts_with($metric, 'cost_')) {
            $costs[$metric][$entity] = (float)($inputs[$entity][$metric] ?? 0);
        }
    }
}

$totalPurchases = array_sum($purchaseData) + array_sum($purchaseAdjustment);
$totalBank = array_sum($bank);

$inventory = [
    'inv_finished_bubble' => (float)($inputs['ALL']['inv_finished_bubble'] ?? 0),
    'inv_finished_lakban' => (float)($inputs['ALL']['inv_finished_lakban'] ?? 0),
    'inv_raw_material' => (float)($inputs['ALL']['inv_raw_material'] ?? 0),
    'inv_plastic_bag' => (float)($inputs['ALL']['inv_plastic_bag'] ?? 0),
    'inv_recycle' => (float)($inputs['ALL']['inv_recycle'] ?? 0),
];
$totalInventory = array_sum($inventory);

$costTotals = [];
$totalCosts = 0.0;
foreach ($costs as $metric => $byEntity) {
    $costTotals[$metric] = array_sum($byEntity);
    $totalCosts += $costTotals[$metric];
}

$productionBubble = (float)($inputs['ALL']['production_rolls_bubble'] ?? 0);
$productionLakban = (float)($inputs['ALL']['production_rolls_lakban'] ?? 0);
$labaRugi = calc_laba_rugi($totalSales, $totalInventory, $totalBank, $totalPurchases, $totalCosts);
$missing = missing_inputs($period);
$targetDiff = $target !== null ? $totalSales - $target : null;
$targetPct = $target ? ($totalSales / $target * 100) : null;
$closedInfo = get_setting('closed:' . $period);

ui_header('Monthly Report', 'report.php');
?>
<div class="page-head">
  <div>
    <h1>Monthly Report — <?= e(period_label($period)) ?></h1>
    <p>
      API sales and purchases<?= $closed ? ' (frozen at close)' : ' (live)' ?>,
      combined with the monthly input.
    </p>
  </div>
  <div class="actions">
    <span class="status-pill <?= $closed ? 'status-closed' : 'status-open' ?>"><?= $closed ? 'Closed' : 'Open' ?></span>
    <?php if ($closedInfo): ?><span class="small muted">Closed <?= e(str_replace('|', ' by ', $closedInfo)) ?></span><?php endif; ?>
    <a class="btn btn-outline" href="input.php?period=<?= urlencode($period) ?>">Edit input</a>
    <a class="btn btn-outline" href="mapping.php?period=<?= urlencode($period) ?>">Mappings</a>
  </div>
</div>

<?php if (isset($_GET['closed'])): ?>
  <div class="alert" style="background:#f0fdf4;border:1px solid #86efac;color:#15803d">Month closed and API figures frozen.</div>
<?php endif; ?>
<?php if (isset($_GET['reopened'])): ?>
  <div class="alert" style="background:#fffbeb;border:1px solid #fde68a;color:#b45309">Month reopened. The report is live again until you close it.</div>
<?php endif; ?>
<?php foreach ($errors as $error): ?>
  <div class="alert alert-error"><?= e($error) ?></div>
<?php endforeach; ?>
<?php if ($autoMapped > 0): ?>
  <div class="alert" style="background:#fffbeb;border:1px solid #fde68a;color:#b45309">
    <?= $autoMapped ?> account/supplier row(s) used the fallback name rule. Save explicit mappings for an exact split.
  </div>
<?php endif; ?>
<?php if (!$closed && $missing): ?>
  <div class="alert" style="background:#fffbeb;border:1px solid #fde68a;color:#b45309">
    <?= count($missing) ?> required input(s) missing. The month cannot be closed yet.
  </div>
<?php endif; ?>

<div class="summary-grid">
  <div class="summary-card blue"><div class="label">Total Sales</div><div class="value"><?= e(format_idr($totalSales, 0)) ?></div><div class="sub"><?= $target !== null ? 'Target ' . e(format_idr($target, 0)) : 'Target not entered' ?></div></div>
  <div class="summary-card green"><div class="label">Total Purchases</div><div class="value"><?= e(format_idr($totalPurchases, 0)) ?></div><div class="sub">incl. adjustments</div></div>
  <div class="summary-card amber"><div class="label">Total Costs</div><div class="value"><?= e(format_idr($totalCosts, 0)) ?></div><div class="sub">bank, petty, salary, electricity</div></div>
  <div class="summary-card red"><div class="label">Laba / Rugi</div><div class="value"><?= e(format_idr($labaRugi, 0)) ?></div><div class="sub">sales + inventory + bank − purchases − costs</div></div>
</div>

<div class="section-card">
  <div class="section-head"><h2>Sales</h2><span class="small muted">API: SalesOrder by dateOrdered</span></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Entity</th><th class="num">Bubble</th><th class="num">Lakban</th><th class="num">Adjustment</th><th class="num">Total</th></tr></thead>
      <tbody>
      <?php foreach (ENTITIES as $entity => $label): ?>
        <?php
          $bubble = (float)($salesData[$entity]['bubble'] ?? 0);
          $lakban = (float)($salesData[$entity]['lakban'] ?? 0);
          $adj    = $salesAdjustment[$entity];
        ?>
        <tr><td><strong><?= e($label) ?></strong></td><td class="num"><?= e(format_idr($bubble, 2)) ?></td><td class="num"><?= e(format_idr($lakban, 2)) ?></td><td class="num"><?= e(format_idr($adj, 2)) ?></td><td class="num"><strong><?= e(format_idr($bubble + $lakban + $adj, 2)) ?></strong></td></tr>
      <?php endforeach; ?>
        <tr><td><strong>Total</strong></td><td class="num"><strong><?= e(format_idr($totalBubble, 2)) ?></strong></td><td class="num"><strong><?= e(format_idr($totalLakban, 2)) ?></strong></td><td class="num"><strong><?= e(format_idr(array_sum($salesAdjustment), 2)) ?></strong></td><td class="num"><strong><?= e(format_idr($totalSales, 2)) ?></strong></td></tr>
      </tbody>
    </table>
  </div>
</div>

<div class="section-card">
  <div class="section-head"><h2>Target</h2></div>
  <div class="section-body">
    <?php if ($target === null): ?>
      <p class="muted">No target entered for this month. <a href="input.php?period=<?= urlencode($period) ?>">Enter it</a>.</p>
    <?php else: ?>
      <p>Actual <?= e(format_idr($totalSales, 2)) ?> vs target <?= e(format_idr($target, 2)) ?> —
        <strong><?= e(number_format((float)$targetPct, 1, ',', '.')) ?>%</strong>,
        variance <?= e(format_idr((float)$targetDiff, 2)) ?>.
      </p>
    <?php endif; ?>
  </div>
</div>

<div class="section-card">
  <div class="section-head"><h2>Purchases</h2><span class="small muted">API: PurchaseOrder by dateOrdered</span></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Entity</th><th class="num">API purchases</th><th class="num">Adjustment</th><th class="num">Total</th></tr></thead>
      <tbody>
      <?php foreach (ENTITIES as $entity => $label): ?>
        <?php $p = (float)($purchaseData[$entity] ?? 0); $a = $purchaseAdjustment[$entity]; ?>
        <tr><td><strong><?= e($label) ?></strong></td><td class="num"><?= e(format_idr($p, 2)) ?></td><td class="num"><?= e(format_idr($a, 2)) ?></td><td class="num"><strong><?= e(format_idr($p + $a, 2)) ?></strong></td></tr>
      <?php endforeach; ?>
        <tr><td><strong>Total</strong></td><td class="num"><strong><?= e(format_idr(array_sum($purchaseData), 2)) ?></strong></td><td class="num"><strong><?= e(format_idr(array_sum($purchaseAdjustment), 2)) ?></strong></td><td class="num"><strong><?= e(format_idr($totalPurchases, 2)) ?></strong></td></tr>
      </tbody>
    </table>
  </div>
</div>

<div class="section-card">
  <div class="section-head"><h2>Bank Balances</h2></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Entity</th><th class="num">Balance</th></tr></thead>
      <tbody>
      <?php foreach (ENTITIES as $entity => $label): ?>
        <tr><td><?= e($label) ?></td><td class="num"><?= e(format_idr($bank[$entity], 2)) ?></td></tr>
      <?php endforeach; ?>
        <tr><td><strong>Total</strong></td><td class="num"><strong><?= e(format_idr($totalBank, 2)) ?></strong></td></tr>
      </tbody>
    </table>
  </div>
</div>

<div class="section-card">
  <div class="section-head"><h2>Inventory</h2></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Bucket</th><th class="num">Value</th></tr></thead>
      <tbody>
      <?php foreach ($inventory as $metric => $value): ?>
        <tr><td><?= e(metric_defs()[$metric]['label']) ?></td><td class="num"><?= e(format_idr($value, 2)) ?></td></tr>
      <?php endforeach; ?>
        <tr><td><strong>Total</strong></td><td class="num"><strong><?= e(format_idr($totalInventory, 2)) ?></strong></td></tr>
      </tbody>
    </table>
  </div>
</div>

<div class="section-card">
  <div class="section-head"><h2>Operating Costs</h2></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Cost</th><?php foreach (ENTITIES as $label): ?><th class="num"><?= e($label) ?></th><?php endforeach; ?><th class="num">Total</th></tr></thead>
      <tbody>
      <?php foreach ($costs as $metric => $byEntity): ?>
        <tr>
          <td><?= e(metric_defs()[$metric]['label']) ?></td>
          <?php foreach (ENTITIES as $entity => $label): ?><td class="num"><?= e(format_idr($byEntity[$entity], 2)) ?></td><?php endforeach; ?>
          <td class="num"><strong><?= e(format_idr($costTotals[$metric], 2)) ?></strong></td>
        </tr>
      <?php endforeach; ?>
        <tr><td><strong>Total</strong></td><?php foreach (ENTITIES as $entity => $label): ?><td class="num"><strong><?= e(format_idr(array_sum(array_column($costs, $entity)), 2)) ?></strong></td><?php endforeach; ?><td class="num"><strong><?= e(format_idr($totalCosts, 2)) ?></strong></td></tr>
      </tbody>
    </table>
  </div>
</div>

<div class="section-card">
  <div class="section-head"><h2>Production Rolls</h2></div>
  <div class="section-body">
    <p>Bubble: <strong><?= e(number_format($productionBubble, 0, ',', '.')) ?></strong> rolls &middot;
       Lakban: <strong><?= e(number_format($productionLakban, 0, ',', '.')) ?></strong> rolls</p>
  </div>
</div>

<?php if (!$closed && $missing): ?>
<div class="section-card">
  <div class="section-head"><h2>Missing required input</h2></div>
  <div class="section-body">
    <ul class="missing-list">
      <?php foreach ($missing as $item): ?><li><?= e($item) ?></li><?php endforeach; ?>
    </ul>
  </div>
</div>
<?php endif; ?>

<div class="actions">
  <?php if (!$closed): ?>
    <form method="post" class="inline-form">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="period" value="<?= e($period) ?>">
      <input type="hidden" name="action" value="close">
      <button type="submit" class="btn btn-primary" <?= $missing ? 'disabled title="Fill the missing inputs first"' : '' ?>>Close month &amp; freeze API figures</button>
    </form>
  <?php else: ?>
    <form method="post" class="inline-form">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="period" value="<?= e($period) ?>">
      <input type="hidden" name="action" value="reopen">
      <button type="submit" class="btn btn-outline">Reopen month</button>
    </form>
  <?php endif; ?>
</div>
<?php ui_footer(); ?>
