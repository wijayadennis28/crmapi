<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/store.php';
require_once __DIR__ . '/ui.php';
require_login();

$period = $_POST['period'] ?? $_GET['period'] ?? date('Y-m');
if (!valid_period($period)) {
    $period = date('Y-m');
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    check_csrf();
    $errors = save_inputs($period, $_POST['v'] ?? [], trim($_POST['note'] ?? ''), auth_user());
    if (!$errors) {
        header('Location: input.php?period=' . urlencode($period) . '&saved=1');
        exit;
    }
}

$inputs  = get_inputs($period);
$note    = get_period_note($period);
$closed  = is_closed($period);
$missing = missing_inputs($period);
$savedAt = last_saved($period);
$defs    = metric_defs();

$groups = [];
foreach ($defs as $metric => $def) {
    $groups[$def['group']][$metric] = $def;
}

$previous = date('Y-m', strtotime($period . '-01 -1 month'));
$next     = date('Y-m', strtotime($period . '-01 +1 month'));

ui_header('Monthly Input', 'input.php');
?>
<div class="page-head">
  <div>
    <h1>Monthly Input — <?= e(period_label($period)) ?></h1>
    <p>Only enter figures that are not available from EspoCRM. Sales and purchases are calculated from the API; production rolls and the figures below are manual.</p>
  </div>
  <div class="actions">
    <span class="status-pill <?= $closed ? 'status-closed' : 'status-open' ?>"><?= $closed ? 'Closed' : 'Open' ?></span>
    <?php if ($savedAt): ?><span class="small muted">Last saved <?= e($savedAt) ?></span><?php endif; ?>
  </div>
</div>

<?php if (isset($_GET['saved'])): ?>
  <div class="alert" style="background:#f0fdf4;border:1px solid #86efac;color:#15803d">Saved.</div>
<?php endif; ?>

<?php foreach ($errors as $error): ?>
  <div class="alert alert-error"><?= e($error) ?></div>
<?php endforeach; ?>

<?php if ($closed): ?>
  <div class="alert" style="background:#f0fdf4;border:1px solid #86efac;color:#15803d">
    This month is closed and locked. Reopen it from the <a href="report.php?period=<?= urlencode($period) ?>">Report</a> page to edit.
  </div>
<?php elseif ($missing): ?>
  <div class="alert" style="background:#fffbeb;border:1px solid #fde68a;color:#b45309">
    <?= count($missing) ?> required figure(s) still missing. You can save a draft, but the month cannot be closed until they are filled.
  </div>
<?php endif; ?>

<form method="get" class="controls" style="margin-bottom:16px">
  <div class="control-group">
    <label>Month</label>
    <input type="month" name="period" value="<?= e($period) ?>" required>
  </div>
  <button type="submit" class="btn btn-primary">Load</button>
  <a class="btn btn-outline" href="input.php?period=<?= urlencode($previous) ?>">&larr; <?= e(period_label($previous)) ?></a>
  <a class="btn btn-outline" href="input.php?period=<?= urlencode($next) ?>"><?= e(period_label($next)) ?> &rarr;</a>
</form>

<form method="post">
  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
  <input type="hidden" name="period" value="<?= e($period) ?>">
  <input type="hidden" name="action" value="save">

  <div class="table-card">
    <div class="table-wrap">
      <table class="matrix">
        <thead>
          <tr>
            <th style="min-width:260px">Figure</th>
            <?php foreach (SCOPES as $scope): ?>
              <th><?= e(scope_label($scope)) ?></th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($groups as $group => $metrics): ?>
            <tr class="group-row"><th colspan="<?= count(SCOPES) + 1 ?>"><?= e($group) ?></th></tr>
            <?php foreach ($metrics as $metric => $def): ?>
              <tr>
                <td>
                  <strong><?= e($def['label']) ?></strong>
                  <span class="unit"><?= e($def['unit'] === 'IDR' ? 'IDR' : $def['unit']) ?></span>
                </td>
                <?php foreach (SCOPES as $scope): ?>
                  <?php if (in_array($scope, $def['scopes'], true)): ?>
                    <?php $value = $inputs[$scope][$metric] ?? null; ?>
                    <td class="cell">
                      <input type="number" step="0.01" name="v[<?= e($scope) ?>][<?= e($metric) ?>]"
                             value="<?= $value === null ? '' : e(number_format($value, 2, '.', '')) ?>"
                             <?= $closed ? 'disabled' : '' ?>>
                    </td>
                  <?php else: ?>
                    <td class="na">&mdash;</td>
                  <?php endif; ?>
                <?php endforeach; ?>
              </tr>
            <?php endforeach; ?>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="section-card">
    <div class="section-head"><h2>Notes</h2></div>
    <div class="section-body">
      <textarea name="note" rows="3" style="width:100%;padding:10px;border:1px solid var(--border);border-radius:6px;font-family:inherit;font-size:.85rem" <?= $closed ? 'disabled' : '' ?>><?= e($note) ?></textarea>
      <p class="small muted" style="margin-top:6px">Note is required when you enter a sales or purchase adjustment.</p>
    </div>
  </div>

  <div class="actions">
    <?php if (!$closed): ?>
      <button type="submit" class="btn btn-primary">Save monthly input</button>
    <?php endif; ?>
    <a class="btn btn-outline" href="report.php?period=<?= urlencode($period) ?>">Go to report</a>
  </div>
</form>
<?php ui_footer(); ?>
