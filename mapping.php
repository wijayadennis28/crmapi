<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/store.php';
require_once __DIR__ . '/ui.php';
require_once __DIR__ . '/EspoClient.php';
require_login();

$site = $_GET['site'] ?? 'site2';
if (!in_array($site, ['site1', 'site2'], true)) {
    $site = 'site2';
}
$period = $_GET['period'] ?? date('Y-m');
if (!valid_period($period)) {
    $period = date('Y-m');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $site   = $_POST['site'] ?? $site;
    $period = $_POST['period'] ?? $period;
    if (!in_array($site, ['site1', 'site2'], true) || !valid_period($period)) {
        http_response_code(400);
        exit('Invalid input.');
    }
    save_mappings($site, 'account', $_POST['accounts'] ?? []);
    save_mappings($site, 'supplier', $_POST['suppliers'] ?? []);
    header('Location: mapping?site=' . urlencode($site) . '&period=' . urlencode($period) . '&saved=1');
    exit;
}

$from = $period . '-01';
$to   = date('Y-m-t', strtotime($from));

$accounts  = [];
$suppliers = [];
$error     = null;

try {
    $client = new EspoClient($site);
    foreach ($client->fetchMonthly('SalesOrder', $from, $to) as $row) {
        $id = $row['accountId'] ?? '';
        if ($id === '') {
            continue;
        }
        $accounts[$id]['name']   = $row['accountName'] ?? '';
        $accounts[$id]['total']  = ($accounts[$id]['total'] ?? 0) + (float)($row['grandTotalAmount'] ?? 0);
        $accounts[$id]['orders'] = ($accounts[$id]['orders'] ?? 0) + 1;
    }
    foreach ($client->fetchMonthly('PurchaseOrder', $from, $to) as $row) {
        $id = $row['supplierId'] ?? $row['accountId'] ?? '';
        if ($id === '') {
            continue;
        }
        $suppliers[$id]['name']   = $row['supplierName'] ?? $row['accountName'] ?? '';
        $suppliers[$id]['total']  = ($suppliers[$id]['total'] ?? 0) + (float)($row['grandTotalAmount'] ?? 0);
        $suppliers[$id]['orders'] = ($suppliers[$id]['orders'] ?? 0) + 1;
    }
} catch (RuntimeException $e) {
    $error = $e->getMessage();
}

uasort($accounts, fn($a, $b) => $b['total'] <=> $a['total']);
uasort($suppliers, fn($a, $b) => $b['total'] <=> $a['total']);

$accountMap  = get_account_map($site);
$supplierMap = get_supplier_map($site);

$autoAccounts = 0;
foreach ($accounts as $id => $row) {
    if (!isset($accountMap[$id])) {
        $autoAccounts++;
    }
}
$autoSuppliers = 0;
foreach ($suppliers as $id => $row) {
    if (!isset($supplierMap[$id])) {
        $autoSuppliers++;
    }
}
$autoTotal = $site === 'site2' ? $autoAccounts + $autoSuppliers : 0;

ui_header('Entity Mappings', 'mapping', ['mobile_meta' => period_label($period)]);
?>
<div class="docket-head">
  <div class="eyebrow">
    <span class="doc-no">03 / MAPPING</span>
    <span class="dot">/</span>
    <span><?= e(period_label($period)) ?></span>
    <span class="dot">/</span>
    <span><?= e(INSTANCES[$site]['name']) ?></span>
  </div>
  <div class="docket-title-row">
    <div>
      <h1 class="docket-title">Entity mapping</h1>
      <p class="docket-sub">EspoCRM has no legal-entity field. Route each customer and supplier to PT TOP, CV MMP, or CV SAE. Empty rows use the name-suffix fallback.</p>
    </div>
    <div class="docket-actions">
      <?php if ($autoTotal): ?><span class="tag tag-signal"><?= $autoTotal ?> auto</span><?php endif; ?>
      <a class="btn btn-outline btn-sm" href="report?period=<?= urlencode($period) ?>">Report</a>
    </div>
  </div>
</div>

<?php if (isset($_GET['saved'])): ?>
  <div class="notice notice-ok" role="status" aria-live="polite">Mappings saved.</div>
<?php endif; ?>
<?php if ($error): ?>
  <div class="notice notice-danger" role="alert">Could not load API data: <?= e($error) ?></div>
<?php endif; ?>

<div class="toolbar">
  <div class="toolbar-group">
    <span class="control-label">Instance</span>
    <div class="segmented">
      <a href="mapping?site=site1&period=<?= urlencode($period) ?>" class="<?= $site === 'site1' ? 'active' : '' ?>">TOP</a>
      <a href="mapping?site=site2&period=<?= urlencode($period) ?>" class="<?= $site === 'site2' ? 'active' : '' ?>">MMP / SLP</a>
    </div>
  </div>
  <form method="get" class="toolbar-group">
    <input type="hidden" name="site" value="<?= e($site) ?>">
    <div class="field">
      <label for="period">Month</label>
      <input class="input" type="month" id="period" name="period" value="<?= e($period) ?>">
    </div>
    <button type="submit" class="btn btn-outline btn-sm">Load</button>
  </form>
  <span class="spacer"></span>
  <a class="btn btn-outline btn-sm" href="input?period=<?= urlencode($period) ?>">Monthly input</a>
</div>

<p class="scroll-hint mobile-only">Tables scroll sideways for all columns &rarr;</p>

<form method="post">
  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
  <input type="hidden" name="site" value="<?= e($site) ?>">
  <input type="hidden" name="period" value="<?= e($period) ?>">

  <div class="sheet">
    <div class="sheet-head">
      <div class="sheet-title-wrap"><span class="sheet-no">01</span><span class="sheet-title">Customers</span></div>
      <div class="filter-bar">
        <label class="sr-only" for="customer-filter">Filter customers</label>
        <input class="input filter-input" id="customer-filter" type="search" placeholder="Filter customers…" data-filter="#customers-table" data-filter-count="#customers-count">
        <span class="sheet-meta" id="customers-count"><?= count($accounts) ?> accounts</span>
      </div>
    </div>
    <div class="table-wrap">
      <table class="data" id="customers-table">
        <thead>
          <tr><th>Account</th><th class="num">Sales</th><th class="num">Orders</th><th>Route</th></tr>
        </thead>
        <tbody>
        <?php if (!$accounts): ?>
          <tr data-empty="1"><td colspan="4" class="muted">No sales orders for this month.</td></tr>
        <?php endif; ?>
        <?php foreach ($accounts as $id => $row): ?>
          <?php $current = $accountMap[$id] ?? ''; $suggested = suggest_entity($site, $row['name'], 'account'); ?>
          <tr>
            <td>
              <strong><?= e($row['name']) ?></strong>
              <input type="hidden" name="accounts[<?= e($id) ?>][name]" value="<?= e($row['name']) ?>">
            </td>
            <td class="num"><?= e(format_idr($row['total'], 2)) ?></td>
            <td class="num"><?= (int)$row['orders'] ?></td>
            <td>
              <?php if ($site === 'site1'): ?>
                <span class="tag tag-muted">PT TOP</span>
                <input type="hidden" name="accounts[<?= e($id) ?>][entity]" value="TOP">
              <?php else: ?>
                <div class="route" role="radiogroup" aria-label="Entity for <?= e($row['name']) ?>">
                  <input type="radio" id="acc_<?= e($id) ?>_auto" name="accounts[<?= e($id) ?>][entity]" value="" <?= $current === '' ? 'checked' : '' ?>>
                  <label for="acc_<?= e($id) ?>_auto" class="auto">Auto</label>
                  <?php foreach (ENTITIES as $key => $label): ?>
                    <input type="radio" id="acc_<?= e($id) ?>_<?= e($key) ?>" name="accounts[<?= e($id) ?>][entity]" value="<?= e($key) ?>" <?= $current === $key ? 'checked' : '' ?>>
                    <label for="acc_<?= e($id) ?>_<?= e($key) ?>"><?= e($key) ?></label>
                  <?php endforeach; ?>
                </div>
                <span class="route-hint">Auto: <?= e(entity_label($suggested)) ?></span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="sheet">
    <div class="sheet-head">
      <div class="sheet-title-wrap"><span class="sheet-no">02</span><span class="sheet-title">Suppliers</span></div>
      <div class="filter-bar">
        <label class="sr-only" for="supplier-filter">Filter suppliers</label>
        <input class="input filter-input" id="supplier-filter" type="search" placeholder="Filter suppliers…" data-filter="#suppliers-table" data-filter-count="#suppliers-count">
        <span class="sheet-meta" id="suppliers-count"><?= count($suppliers) ?> suppliers</span>
      </div>
    </div>
    <div class="table-wrap">
      <table class="data" id="suppliers-table">
        <thead>
          <tr><th>Supplier</th><th class="num">Purchases</th><th class="num">POs</th><th>Route</th></tr>
        </thead>
        <tbody>
        <?php if (!$suppliers): ?>
          <tr data-empty="1"><td colspan="4" class="muted">No purchase orders for this month.</td></tr>
        <?php endif; ?>
        <?php foreach ($suppliers as $id => $row): ?>
          <?php $current = $supplierMap[$id] ?? ''; $suggested = suggest_entity($site, $row['name'], 'supplier'); ?>
          <tr>
            <td>
              <strong><?= e($row['name']) ?></strong>
              <input type="hidden" name="suppliers[<?= e($id) ?>][name]" value="<?= e($row['name']) ?>">
            </td>
            <td class="num"><?= e(format_idr($row['total'], 2)) ?></td>
            <td class="num"><?= (int)$row['orders'] ?></td>
            <td>
              <?php if ($site === 'site1'): ?>
                <span class="tag tag-muted">PT TOP</span>
                <input type="hidden" name="suppliers[<?= e($id) ?>][entity]" value="TOP">
              <?php else: ?>
                <div class="route" role="radiogroup" aria-label="Entity for <?= e($row['name']) ?>">
                  <input type="radio" id="sup_<?= e($id) ?>_auto" name="suppliers[<?= e($id) ?>][entity]" value="" <?= $current === '' ? 'checked' : '' ?>>
                  <label for="sup_<?= e($id) ?>_auto" class="auto">Auto</label>
                  <?php foreach (ENTITIES as $key => $label): ?>
                    <input type="radio" id="sup_<?= e($id) ?>_<?= e($key) ?>" name="suppliers[<?= e($id) ?>][entity]" value="<?= e($key) ?>" <?= $current === $key ? 'checked' : '' ?>>
                    <label for="sup_<?= e($id) ?>_<?= e($key) ?>"><?= e($key) ?></label>
                  <?php endforeach; ?>
                </div>
                <span class="route-hint">Auto: <?= e(entity_label($suggested)) ?></span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="sticky-actions no-print">
    <div class="meta">
      <?= count($accounts) + count($suppliers) ?> rows in <?= e(period_label($period)) ?>. Auto rows use the fallback rule.
    </div>
    <div class="row">
      <button type="submit" class="btn btn-signal">Save mappings</button>
      <a class="btn btn-outline" href="input?period=<?= urlencode($period) ?>">Monthly input</a>
    </div>
  </div>
</form>
<?php ui_footer(); ?>
