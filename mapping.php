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
    header('Location: mapping.php?site=' . urlencode($site) . '&period=' . urlencode($period) . '&saved=1');
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

ui_header('Mappings', 'mapping.php');
?>
<div class="page-head">
  <div>
    <h1>Entity Mappings</h1>
    <p>
      EspoCRM does not store a legal entity. Map each customer/supplier to PT TOP, CV MMP, or CV SAE.
      Empty rows fall back to the existing name-suffix rule.
    </p>
  </div>
</div>

<?php if (isset($_GET['saved'])): ?>
  <div class="alert" style="background:#f0fdf4;border:1px solid #86efac;color:#15803d">Mappings saved.</div>
<?php endif; ?>
<?php if ($error): ?>
  <div class="alert alert-error">Could not load API data: <?= e($error) ?></div>
<?php endif; ?>

<form method="get" class="controls" style="margin-bottom:16px">
  <div class="control-group">
    <label>Instance</label>
    <select name="site">
      <option value="site1" <?= $site === 'site1' ? 'selected' : '' ?>>TOP — <?= e(INSTANCES['site1']['name']) ?></option>
      <option value="site2" <?= $site === 'site2' ? 'selected' : '' ?>>MMP/SLP — <?= e(INSTANCES['site2']['name']) ?></option>
    </select>
  </div>
  <div class="control-group">
    <label>Month</label>
    <input type="month" name="period" value="<?= e($period) ?>">
  </div>
  <button type="submit" class="btn btn-primary">Load</button>
  <a class="btn btn-outline" href="report.php?period=<?= urlencode($period) ?>">Report</a>
</form>

<form method="post">
  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
  <input type="hidden" name="site" value="<?= e($site) ?>">
  <input type="hidden" name="period" value="<?= e($period) ?>">

  <div class="section-card">
    <div class="section-head">
      <h2>Customers — <?= e(INSTANCES[$site]['name']) ?> — <?= e(period_label($period)) ?></h2>
      <span class="small muted"><?= count($accounts) ?> accounts</span>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>Account</th><th class="num">Sales</th><th>Orders</th><th>Entity</th></tr>
        </thead>
        <tbody>
        <?php if (!$accounts): ?>
          <tr><td colspan="4" class="muted">No sales orders for this month.</td></tr>
        <?php endif; ?>
        <?php foreach ($accounts as $id => $row): ?>
          <?php $current = $accountMap[$id] ?? ''; $suggested = suggest_entity($site, $row['name'], 'account'); ?>
          <tr>
            <td>
              <strong><?= e($row['name']) ?></strong>
              <input type="hidden" name="accounts[<?= e($id) ?>][name]" value="<?= e($row['name']) ?>">
            </td>
            <td class="num"><?= e(format_idr($row['total'], 2)) ?></td>
            <td><?= (int)$row['orders'] ?></td>
            <td>
              <?php if ($site === 'site1'): ?>
                <span class="small">PT TOP</span>
                <input type="hidden" name="accounts[<?= e($id) ?>][entity]" value="TOP">
              <?php else: ?>
                <select name="accounts[<?= e($id) ?>][entity]" style="height:34px;padding:0 8px;border:1px solid var(--border);border-radius:6px">
                  <option value="">Auto: <?= e(entity_label($suggested)) ?></option>
                  <?php foreach (ENTITIES as $key => $label): ?>
                    <option value="<?= e($key) ?>" <?= $current === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                  <?php endforeach; ?>
                </select>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="section-card">
    <div class="section-head">
      <h2>Suppliers — <?= e(INSTANCES[$site]['name']) ?> — <?= e(period_label($period)) ?></h2>
      <span class="small muted"><?= count($suppliers) ?> suppliers</span>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>Supplier</th><th class="num">Purchases</th><th>POs</th><th>Entity</th></tr>
        </thead>
        <tbody>
        <?php if (!$suppliers): ?>
          <tr><td colspan="4" class="muted">No purchase orders for this month.</td></tr>
        <?php endif; ?>
        <?php foreach ($suppliers as $id => $row): ?>
          <?php $current = $supplierMap[$id] ?? ''; $suggested = suggest_entity($site, $row['name'], 'supplier'); ?>
          <tr>
            <td>
              <strong><?= e($row['name']) ?></strong>
              <input type="hidden" name="suppliers[<?= e($id) ?>][name]" value="<?= e($row['name']) ?>">
            </td>
            <td class="num"><?= e(format_idr($row['total'], 2)) ?></td>
            <td><?= (int)$row['orders'] ?></td>
            <td>
              <?php if ($site === 'site1'): ?>
                <span class="small">PT TOP</span>
                <input type="hidden" name="suppliers[<?= e($id) ?>][entity]" value="TOP">
              <?php else: ?>
                <select name="suppliers[<?= e($id) ?>][entity]" style="height:34px;padding:0 8px;border:1px solid var(--border);border-radius:6px">
                  <option value="">Auto: <?= e(entity_label($suggested)) ?></option>
                  <?php foreach (ENTITIES as $key => $label): ?>
                    <option value="<?= e($key) ?>" <?= $current === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                  <?php endforeach; ?>
                </select>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="actions">
    <button type="submit" class="btn btn-primary">Save mappings</button>
    <a class="btn btn-outline" href="input.php?period=<?= urlencode($period) ?>">Monthly input</a>
  </div>
</form>
<?php ui_footer(); ?>
