<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/store.php';
require_once __DIR__ . '/sources.php';
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

function build_actual_rows(string $period, array $pettyCash): array
{
    $sales     = build_sales($period);
    $purchases = build_purchases($period);
    $inputs    = get_inputs($period);
    $petty     = petty_cash_resolved($inputs, $pettyCash);
    $source    = petty_cash_source($inputs, $pettyCash);
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
        $rows[] = [
            'scope'  => $entity,
            'metric' => 'cost_petty_cash',
            'value'  => $petty[$entity] ?? 0,
            'detail' => ['source' => $source],
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
$pettyCash = $closed ? [
    'values'   => array_fill_keys(array_keys(ENTITIES), 0.0),
    'present'  => array_fill_keys(array_keys(ENTITIES), false),
    'unmapped' => [],
    'errors'   => [],
    'records'  => 0,
] : petty_cash_values($period);
$apiValues = ['petty_cash' => $pettyCash];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'close') {
        $missing = missing_inputs($period, $apiValues);
        if ($missing) {
            $errors[] = 'Cannot close: ' . count($missing) . ' required input(s) are still missing.';
        } else {
            [$rows, $auto, $apiErrors] = build_actual_rows($period, $pettyCash);
            if ($apiErrors) {
                $errors = array_merge($errors, $apiErrors);
            } else {
                save_actuals($period, $rows);
                close_period($period, auth_user());
                header('Location: report?period=' . urlencode($period) . '&closed=1');
                exit;
            }
        }
    } elseif ($action === 'reopen') {
        reopen_period($period);
        header('Location: report?period=' . urlencode($period) . '&reopened=1');
        exit;
    }
}

$inputs = get_inputs($period);
$closed = is_closed($period);
$actuals = $closed ? get_actuals($period) : [];
$pettyResolved = petty_cash_resolved($inputs, $pettyCash);
$pettySource   = petty_cash_source($inputs, $pettyCash);
$pettyActuals  = [];
$reportDefs    = report_metric_defs($period);
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
            } elseif ($metric === 'cost_petty_cash') {
                $pettyActuals[$scope] = $value;
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

    foreach ($reportDefs as $metric => $def) {
        if (is_cost_metric($metric, $def)) {
            $value = (float)($inputs[$entity][$metric] ?? 0);
            if ($metric === 'cost_petty_cash') {
                $value = $closed
                    ? (float)($pettyActuals[$entity] ?? $value)
                    : (float)($pettyResolved[$entity] ?? $value);
            }
            $costs[$metric][$entity] = $value;
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

$customInfo = [];
foreach ($reportDefs as $metric => $def) {
    if (($def['role'] ?? '') === 'info') {
        $customInfo[$metric] = $def;
    }
}
$statusSection = $customInfo ? 9 : 8;

$productionBubble = (float)($inputs['ALL']['production_rolls_bubble'] ?? 0);
$productionLakban = (float)($inputs['ALL']['production_rolls_lakban'] ?? 0);
$labaRugi = calc_laba_rugi($totalSales, $totalInventory, $totalBank, $totalPurchases, $totalCosts);
$missing = missing_inputs($period, $apiValues);
$targetDiff = $target !== null ? $totalSales - $target : null;
$targetPct = $target ? ($totalSales / $target * 100) : null;
$closedInfo = get_setting('closed:' . $period);

$targetFill  = $targetPct !== null ? max(0, min(100, (float)$targetPct)) : 0;
$targetTone  = ($targetDiff !== null && $targetDiff < 0) ? 'neg' : 'pos';
$liveLabel   = $closed ? 'frozen at close' : 'live';
$pettyTag    = $closed ? 'Frozen' : $pettySource;
$pettyTagClass = $pettyTag === 'API' ? 'tag-ok' : ($pettyTag === 'Override' ? 'tag-signal' : 'tag-muted');

ui_header('Monthly Report', 'report', ['mobile_meta' => period_label($period)]);
?>
<div class="print-only print-head">
  <span>TOP — Triniti Optima Packindo</span>
  <span>Monthly report / <?= e(period_label($period)) ?></span>
  <span><?= $closed ? 'Closed' : 'Draft — live data' ?></span>
</div>

<div class="docket-head">
  <div class="eyebrow">
    <span class="doc-no">04 / REPORT</span>
    <span class="dot">/</span>
    <span><?= e(period_label($period)) ?></span>
    <span class="dot">/</span>
    <span><?= $closed ? 'Frozen' : 'Live' ?></span>
  </div>
  <div class="docket-title-row">
    <div>
      <h1 class="docket-title">Monthly report <span class="thin">/ <?= e(period_label($period)) ?></span></h1>
      <p class="docket-sub">API sales and purchases <?= e($liveLabel) ?>, combined with the monthly input. Amounts in IDR.</p>
    </div>
    <div class="docket-actions">
      <?= ui_stamp($closed) ?>
      <button type="button" class="btn btn-outline btn-sm" data-print>Print</button>
      <a class="btn btn-outline btn-sm" href="input?period=<?= urlencode($period) ?>">Edit input</a>
      <a class="btn btn-outline btn-sm" href="mapping?period=<?= urlencode($period) ?>">Mappings</a>
    </div>
  </div>
</div>

<?php if (isset($_GET['closed'])): ?>
  <div class="notice notice-ok" role="status" aria-live="polite">Month closed and API figures frozen.</div>
<?php endif; ?>
<?php if (isset($_GET['reopened'])): ?>
  <div class="notice notice-warn" role="status">Month reopened. The report is live again until you close it.</div>
<?php endif; ?>
<?php foreach ($errors as $error): ?>
  <div class="notice notice-danger" role="alert"><?= e($error) ?></div>
<?php endforeach; ?>
<?php if (!$closed && $pettyCash['errors']): ?>
  <div class="notice notice-warn">
    Could not read CPettyCash: <?= e(implode('; ', $pettyCash['errors'])) ?>. Petty cash uses manual input for this month.
  </div>
<?php endif; ?>
<?php if (!$closed && $pettyCash['unmapped']): ?>
  <?php
    $unmappedCount = array_sum(array_column($pettyCash['unmapped'], 'count'));
    $unmappedTotal = petty_cash_unmapped_total($pettyCash);
  ?>
  <div class="notice notice-warn">
    <?= (int)$unmappedCount ?> CPettyCash row<?= $unmappedCount === 1 ? '' : 's' ?>
    have no entity label (<?= e(petty_cash_unmapped_labels($pettyCash)) ?>) and total
    Rp <?= e(format_idr($unmappedTotal, 2)) ?>. They are not included in the API totals; fix the CRM label or enter an override.
  </div>
<?php endif; ?>
<?php if ($autoMapped > 0): ?>
  <div class="notice notice-warn">
    <?= $autoMapped ?> order/PO record<?= $autoMapped === 1 ? '' : 's' ?> used the fallback name rule. Save explicit mappings for an exact split.
  </div>
<?php endif; ?>
<?php if (!$closed && $missing): ?>
  <div class="notice notice-warn">
    <?= count($missing) ?> required input<?= count($missing) === 1 ? '' : 's' ?> missing. The month cannot be closed yet.
  </div>
<?php endif; ?>
<?php if ($closedInfo): ?>
  <div class="notice notice-info">
    Closed <?= e(str_replace('|', ' by ', $closedInfo)) ?>.
  </div>
<?php endif; ?>

<div class="figures">
  <div class="figure">
    <div class="figure-kicker">Total sales</div>
    <div class="figure-number">Rp <?= e(number_format($totalSales, 0, ',', '.')) ?></div>
    <div class="figure-note"><?= $target !== null ? 'Target Rp ' . e(number_format($target, 0, ',', '.')) : 'Target not entered' ?></div>
  </div>
  <div class="figure">
    <div class="figure-kicker">Total purchases</div>
    <div class="figure-number">Rp <?= e(number_format($totalPurchases, 0, ',', '.')) ?></div>
    <div class="figure-note">API + adjustments</div>
  </div>
  <div class="figure">
    <div class="figure-kicker">Total costs</div>
    <div class="figure-number">Rp <?= e(number_format($totalCosts, 0, ',', '.')) ?></div>
    <div class="figure-note">Bank, petty, salary, electricity</div>
  </div>
  <div class="figure">
    <div class="figure-kicker">Laba / Rugi</div>
    <div class="figure-number <?= $labaRugi < 0 ? 'neg' : 'pos' ?>">Rp <?= e(number_format($labaRugi, 0, ',', '.')) ?></div>
    <div class="figure-note">Sales + inventory + bank - purchases - costs</div>
  </div>
</div>

<p class="scroll-hint mobile-only">Tables scroll sideways for all columns &rarr;</p>

<div class="sheet">
  <div class="sheet-head">
    <div class="sheet-title-wrap"><span class="sheet-no">01</span><span class="sheet-title">Sales</span></div>
    <span class="sheet-meta">SalesOrder / dateOrdered</span>
  </div>
  <div class="table-wrap">
    <table class="data">
      <thead><tr><th>Entity</th><th class="num">Bubble</th><th class="num">Lakban</th><th class="num">Adjustment</th><th class="num">Total</th></tr></thead>
      <tbody>
      <?php foreach (ENTITIES as $entity => $label): ?>
        <?php
          $bubble = (float)($salesData[$entity]['bubble'] ?? 0);
          $lakban = (float)($salesData[$entity]['lakban'] ?? 0);
          $adj    = $salesAdjustment[$entity];
        ?>
        <tr>
          <td><strong><?= e($label) ?></strong></td>
          <td class="num"><?= e(format_idr($bubble, 2)) ?></td>
          <td class="num"><?= e(format_idr($lakban, 2)) ?></td>
          <td class="num <?= $adj < 0 ? 'neg' : '' ?>"><?= e(format_idr($adj, 2)) ?></td>
          <td class="num strong"><?= e(format_idr($bubble + $lakban + $adj, 2)) ?></td>
        </tr>
      <?php endforeach; ?>
        <tr class="total-row">
          <td>Total</td>
          <td class="num"><?= e(format_idr($totalBubble, 2)) ?></td>
          <td class="num"><?= e(format_idr($totalLakban, 2)) ?></td>
          <td class="num"><?= e(format_idr(array_sum($salesAdjustment), 2)) ?></td>
          <td class="num"><?= e(format_idr($totalSales, 2)) ?></td>
        </tr>
      </tbody>
    </table>
  </div>
</div>

<div class="sheet">
  <div class="sheet-head">
    <div class="sheet-title-wrap"><span class="sheet-no">02</span><span class="sheet-title">Target</span></div>
    <?php if ($target !== null): ?><span class="sheet-meta"><?= e(number_format((float)$targetPct, 1, ',', '.')) ?>% of target</span><?php endif; ?>
  </div>
  <div class="target">
    <?php if ($target === null): ?>
      <p class="muted">No target entered for this month. <a href="input?period=<?= urlencode($period) ?>">Enter it</a>.</p>
    <?php else: ?>
      <div class="target-head">
        <div>
          <div class="figure-kicker">Actual vs target</div>
          <div class="target-pct"><?= e(number_format((float)$targetPct, 1, ',', '.')) ?>%</div>
        </div>
        <div class="text-right">
          <div class="small muted">Target</div>
          <div class="strong">Rp <?= e(number_format($target, 0, ',', '.')) ?></div>
        </div>
      </div>
      <div class="target-meter" role="img" aria-label="<?= e(number_format((float)$targetPct, 1, ',', '.')) ?> percent of target">
        <div class="target-fill" style="width:<?= e(number_format($targetFill, 1, '.', '')) ?>%"></div>
        <div class="target-tick" style="left:100%"></div>
      </div>
      <div class="target-legend">
        <span><i class="key key-fill"></i>Actual Rp <?= e(number_format($totalSales, 0, ',', '.')) ?></span>
        <span><i class="key key-tick"></i>Target Rp <?= e(number_format($target, 0, ',', '.')) ?></span>
        <span class="<?= $targetTone ?>">Variance Rp <?= e(number_format((float)$targetDiff, 0, ',', '.')) ?></span>
      </div>
    <?php endif; ?>
  </div>
</div>

<div class="sheet">
  <div class="sheet-head">
    <div class="sheet-title-wrap"><span class="sheet-no">03</span><span class="sheet-title">Purchases</span></div>
    <span class="sheet-meta">PurchaseOrder / dateOrdered</span>
  </div>
  <div class="table-wrap">
    <table class="data">
      <thead><tr><th>Entity</th><th class="num">API purchases</th><th class="num">Adjustment</th><th class="num">Total</th></tr></thead>
      <tbody>
      <?php foreach (ENTITIES as $entity => $label): ?>
        <?php $p = (float)($purchaseData[$entity] ?? 0); $a = $purchaseAdjustment[$entity]; ?>
        <tr>
          <td><strong><?= e($label) ?></strong></td>
          <td class="num"><?= e(format_idr($p, 2)) ?></td>
          <td class="num <?= $a < 0 ? 'neg' : '' ?>"><?= e(format_idr($a, 2)) ?></td>
          <td class="num strong"><?= e(format_idr($p + $a, 2)) ?></td>
        </tr>
      <?php endforeach; ?>
        <tr class="total-row">
          <td>Total</td>
          <td class="num"><?= e(format_idr(array_sum($purchaseData), 2)) ?></td>
          <td class="num"><?= e(format_idr(array_sum($purchaseAdjustment), 2)) ?></td>
          <td class="num"><?= e(format_idr($totalPurchases, 2)) ?></td>
        </tr>
      </tbody>
    </table>
  </div>
</div>

<div class="sheet">
  <div class="sheet-head">
    <div class="sheet-title-wrap"><span class="sheet-no">04</span><span class="sheet-title">Bank balances</span></div>
    <span class="sheet-meta">Month end</span>
  </div>
  <div class="table-wrap">
    <table class="data">
      <thead><tr><th>Entity</th><th class="num">Balance</th></tr></thead>
      <tbody>
      <?php foreach (ENTITIES as $entity => $label): ?>
        <tr><td><?= e($label) ?></td><td class="num"><?= e(format_idr($bank[$entity], 2)) ?></td></tr>
      <?php endforeach; ?>
        <tr class="total-row"><td>Total</td><td class="num"><?= e(format_idr($totalBank, 2)) ?></td></tr>
      </tbody>
    </table>
  </div>
</div>

<div class="sheet">
  <div class="sheet-head">
    <div class="sheet-title-wrap"><span class="sheet-no">05</span><span class="sheet-title">Inventory</span></div>
    <span class="sheet-meta">Month end</span>
  </div>
  <div class="table-wrap">
    <table class="data">
      <thead><tr><th>Bucket</th><th class="num">Value</th></tr></thead>
      <tbody>
      <?php foreach ($inventory as $metric => $value): ?>
        <tr><td><?= e(metric_defs()[$metric]['label']) ?></td><td class="num"><?= e(format_idr($value, 2)) ?></td></tr>
      <?php endforeach; ?>
        <tr class="total-row"><td>Total</td><td class="num"><?= e(format_idr($totalInventory, 2)) ?></td></tr>
      </tbody>
    </table>
  </div>
</div>

<div class="sheet">
  <div class="sheet-head">
    <div class="sheet-title-wrap"><span class="sheet-no">06</span><span class="sheet-title">Operating costs</span></div>
    <span class="sheet-meta">Month end</span>
  </div>
  <div class="table-wrap">
    <table class="data">
      <thead><tr><th>Cost</th><?php foreach (ENTITIES as $label): ?><th class="num"><?= e($label) ?></th><?php endforeach; ?><th class="num">Total</th></tr></thead>
      <tbody>
      <?php foreach ($costs as $metric => $byEntity): ?>
        <?php $costDef = $reportDefs[$metric] ?? ['label' => $metric]; ?>
        <tr>
          <td>
            <?= e($costDef['label']) ?>
            <?php if ($metric === 'cost_petty_cash'): ?>
              <span class="tag <?= e($pettyTagClass) ?> ml-6"><?= e($pettyTag) ?></span>
            <?php endif; ?>
            <?php if (!empty($costDef['custom'])): ?>
              <span class="tag tag-muted ml-6">Custom</span>
            <?php endif; ?>
          </td>
          <?php foreach (ENTITIES as $entity => $label): ?><td class="num"><?= e(format_idr($byEntity[$entity], 2)) ?></td><?php endforeach; ?>
          <td class="num strong"><?= e(format_idr($costTotals[$metric], 2)) ?></td>
        </tr>
      <?php endforeach; ?>
        <tr class="total-row">
          <td>Total</td>
          <?php foreach (ENTITIES as $entity => $label): ?><td class="num"><?= e(format_idr(array_sum(array_column($costs, $entity)), 2)) ?></td><?php endforeach; ?>
          <td class="num"><?= e(format_idr($totalCosts, 2)) ?></td>
        </tr>
      </tbody>
    </table>
  </div>
  <div class="sheet-body tight">
    <p class="small muted">Petty cash uses CPettyCash when the month has records; manual override otherwise. Closed months show the frozen value.</p>
  </div>
</div>

<div class="sheet">
  <div class="sheet-head">
    <div class="sheet-title-wrap"><span class="sheet-no">07</span><span class="sheet-title">Production rolls</span></div>
    <span class="sheet-meta">Manual input</span>
  </div>
  <div class="table-wrap">
    <table class="data">
      <thead><tr><th>Line</th><th class="num">Rolls</th></tr></thead>
      <tbody>
        <tr><td>Bubble</td><td class="num"><?= e(number_format($productionBubble, 0, ',', '.')) ?></td></tr>
        <tr><td>Lakban</td><td class="num"><?= e(number_format($productionLakban, 0, ',', '.')) ?></td></tr>
      </tbody>
    </table>
  </div>
</div>

<?php if ($customInfo): ?>
<div class="sheet">
  <div class="sheet-head">
    <div class="sheet-title-wrap"><span class="sheet-no">08</span><span class="sheet-title">Custom data</span></div>
    <span class="sheet-meta">Info only / not in totals</span>
  </div>
  <div class="table-wrap">
    <table class="data">
      <thead><tr><th>Field</th><?php foreach (ENTITIES as $label): ?><th class="num"><?= e($label) ?></th><?php endforeach; ?></tr></thead>
      <tbody>
      <?php foreach ($customInfo as $metric => $def): ?>
        <tr>
          <td><?= e($def['label']) ?></td>
          <?php foreach (ENTITIES as $entity => $label): ?><td class="num"><?= e(format_idr((float)($inputs[$entity][$metric] ?? 0), 2)) ?></td><?php endforeach; ?>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="sheet-body tight">
    <p class="small muted">Info fields are shown for context and are not included in total costs or Laba/Rugi.</p>
  </div>
</div>
<?php endif; ?>

<?php if (!$closed && $missing): ?>
<div class="sheet">
  <div class="sheet-head">
    <div class="sheet-title-wrap"><span class="sheet-no">!</span><span class="sheet-title">Missing required input</span></div>
    <span class="sheet-meta"><?= count($missing) ?> item<?= count($missing) === 1 ? '' : 's' ?></span>
  </div>
  <div class="sheet-body">
    <ul class="missing-list">
      <?php foreach ($missing as $item): ?><li><?= e($item) ?></li><?php endforeach; ?>
    </ul>
  </div>
</div>
<?php endif; ?>

<div class="sheet no-print">
  <div class="sheet-head">
    <div class="sheet-title-wrap"><span class="sheet-no"><?= sprintf('%02d', $statusSection) ?></span><span class="sheet-title">Month status</span></div>
    <span class="sheet-meta"><?= $closed ? 'Closed' : 'Open' ?></span>
  </div>
  <div class="sheet-body">
    <div class="between">
      <div>
        <div class="figure-kicker"><?= $closed ? 'Frozen at close' : 'Live API data' ?></div>
        <div class="strong mt-8"><?= $closed ? 'This month is closed. API figures are frozen.' : 'This month is open. API figures are live.' ?></div>
        <p class="small muted mt-8">Closing freezes sales and purchases for this month. Reopening returns the report to live data.</p>
        <?php if ($closedInfo): ?><p class="small muted">Closed <?= e(str_replace('|', ' by ', $closedInfo)) ?>.</p><?php endif; ?>
      </div>
      <div class="row">
        <?php if (!$closed): ?>
          <form method="post" class="inline-form">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="period" value="<?= e($period) ?>">
            <input type="hidden" name="action" value="close">
            <button type="submit" class="btn btn-signal" <?= $missing ? 'disabled title="Fill the missing inputs first"' : '' ?>>Close month &amp; freeze</button>
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
    </div>
  </div>
</div>
<?php ui_footer(); ?>
