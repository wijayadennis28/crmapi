<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/store.php';
require_once __DIR__ . '/sources.php';
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
        header('Location: input?period=' . urlencode($period) . '&saved=1');
        exit;
    }
}

$inputs  = get_inputs($period);
$note    = get_period_note($period);
$closed  = is_closed($period);
$defs    = metric_defs();

if ($closed) {
    $closedActuals = get_actuals($period);
    $pettyCash = [
        'values'   => array_fill_keys(array_keys(ENTITIES), 0.0),
        'present'  => array_fill_keys(array_keys(ENTITIES), false),
        'unmapped' => [],
        'errors'   => [],
        'records'  => 0,
    ];
    foreach (ENTITIES as $entity => $label) {
        if (isset($closedActuals[$entity]['cost_petty_cash'])) {
            $pettyCash['values'][$entity] = (float)$closedActuals[$entity]['cost_petty_cash'];
            $pettyCash['present'][$entity] = true;
        }
    }
} else {
    $pettyCash = petty_cash_values($period);
}
$apiValues   = ['petty_cash' => $pettyCash];
$missing     = missing_inputs($period, $apiValues);
$pettyResolved = petty_cash_resolved($inputs, $pettyCash);
$pettySource   = $closed ? 'Frozen' : petty_cash_source($inputs, $pettyCash);
$pettySourceClass = $pettySource === 'API' ? 'tag-ok' : ($pettySource === 'Override' ? 'tag-signal' : 'tag-muted');
$savedAt     = last_saved($period);

$groups = [];
foreach ($defs as $metric => $def) {
    $groups[$def['group']][$metric] = $def;
}

$requiredTotal = 0;
foreach ($defs as $def) {
    if (!empty($def['required'])) {
        $requiredTotal += count($def['scopes']);
    }
}
$filledRequired = $requiredTotal - count($missing);

$bankTotal = 0.0;
$inventoryTotal = 0.0;
$costTotal = 0.0;
foreach ($defs as $metric => $def) {
    foreach ($def['scopes'] as $scope) {
        $value = (float)($inputs[$scope][$metric] ?? 0);
        if ($metric === 'bank_balance') {
            $bankTotal += $value;
        } elseif (str_starts_with($metric, 'inv_')) {
            $inventoryTotal += $value;
        } elseif (str_starts_with($metric, 'cost_')) {
            $costTotal += $metric === 'cost_petty_cash'
                ? (float)($pettyResolved[$scope] ?? 0)
                : $value;
        }
    }
}

$previous = date('Y-m', strtotime($period . '-01 -1 month'));
$next     = date('Y-m', strtotime($period . '-01 +1 month'));

ui_header('Monthly Input', 'input', ['mobile_meta' => period_label($period)]);
?>
<div class="docket-head">
  <div class="eyebrow">
    <span class="doc-no">02 / INPUT</span>
    <span class="dot">/</span>
    <span><?= e(period_label($period)) ?></span>
    <span class="dot">/</span>
    <span>Manual figures</span>
  </div>
  <div class="docket-title-row">
    <div>
      <h1 class="docket-title">Monthly input <span class="thin">/ <?= e(period_label($period)) ?></span></h1>
      <p class="docket-sub">Enter only the figures that do not come from EspoCRM. Sales and purchases are pulled from the API; petty cash is pulled from CPettyCash when the month has records. All other rows are manual unless marked API.</p>
    </div>
    <div class="docket-actions">
      <?= ui_stamp($closed) ?>
      <?php if ($savedAt): ?><span class="small muted">Saved <?= e($savedAt) ?></span><?php endif; ?>
    </div>
  </div>
</div>

<?php if (isset($_GET['saved'])): ?>
  <div class="notice notice-ok" role="status" aria-live="polite">Saved. The ledger is up to date.</div>
<?php endif; ?>
<?php foreach ($errors as $error): ?>
  <div class="notice notice-danger" role="alert"><?= e($error) ?></div>
<?php endforeach; ?>

<?php if ($pettyCash['errors']): ?>
  <div class="notice notice-warn">
    Could not read CPettyCash: <?= e(implode('; ', $pettyCash['errors'])) ?>. Petty cash is manual for this month.
  </div>
<?php endif; ?>
<?php if ($pettyCash['unmapped']): ?>
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
<?php if (!$closed && !$pettyCash['errors'] && $pettyCash['records'] === 0): ?>
  <div class="notice notice-info">
    No CPettyCash records for <?= e(period_label($period)) ?>; enter petty cash manually.
  </div>
<?php endif; ?>

<?php if ($closed): ?>
  <div class="notice notice-info">This month is closed and locked. Reopen it from the <a href="report?period=<?= urlencode($period) ?>">report</a> to edit.</div>
<?php elseif ($missing): ?>
  <div class="notice notice-warn">
    <?= count($missing) ?> required figure<?= count($missing) === 1 ? '' : 's' ?> still missing. You can save a draft, but the month cannot be closed until they are filled.
  </div>
<?php endif; ?>

<div class="period-bar">
  <div class="period-nav">
    <a class="btn btn-outline btn-sm" href="input?period=<?= urlencode($previous) ?>">&larr; <?= e(date('M Y', strtotime($previous . '-01'))) ?></a>
    <form method="get" class="row">
      <label class="sr-only" for="period">Month</label>
      <input class="input" type="month" id="period" name="period" value="<?= e($period) ?>" required>
      <button type="submit" class="btn btn-outline btn-sm">Load</button>
    </form>
    <a class="btn btn-outline btn-sm" href="input?period=<?= urlencode($next) ?>"><?= e(date('M Y', strtotime($next . '-01'))) ?> &rarr;</a>
  </div>
  <a class="btn btn-outline btn-sm" href="report?period=<?= urlencode($period) ?>">View report</a>
</div>

<form method="post" id="entry-form">
  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
  <input type="hidden" name="period" value="<?= e($period) ?>">
  <input type="hidden" name="action" value="save">

  <div class="entry-layout">
    <div class="entry-main">
      <div class="sheet flush">
        <div class="sheet-head">
          <div class="sheet-title-wrap">
            <span class="sheet-no">A</span>
            <span class="sheet-title">Input ledger</span>
          </div>
          <span class="sheet-meta"><?= e(period_label($period)) ?> &middot; <?= $closed ? 'locked' : 'draft' ?></span>
        </div>
        <p class="scroll-hint mobile-only">Scroll sideways for CV MMP / CV SAE &rarr;</p>
        <div class="matrix-scroll">
          <table class="matrix">
            <thead>
              <tr>
                <th>Figure</th>
                <?php foreach (SCOPES as $scope): ?>
                  <th><?= e(scope_label($scope)) ?></th>
                <?php endforeach; ?>
              </tr>
            </thead>
            <tbody>
              <?php $groupNo = 0; foreach ($groups as $group => $metrics): $groupNo++; ?>
                <tr class="group-row">
                  <th colspan="<?= count(SCOPES) + 1 ?>"><span class="group-no"><?= str_pad((string)$groupNo, 2, '0', STR_PAD_LEFT) ?></span><?= e($group) ?></th>
                </tr>
                <?php foreach ($metrics as $metric => $def): ?>
                  <tr>
                    <td class="row-label">
                      <strong>
                        <?= e($def['label']) ?>
                        <?php if ($metric === 'cost_petty_cash'): ?>
                          <span class="tag <?= e($pettySourceClass) ?> ml-6"><?= e($pettySource) ?></span>
                        <?php endif; ?>
                      </strong>
                      <span class="unit"><?= e($def['unit'] === 'IDR' ? 'IDR' : $def['unit']) ?></span>
                    </td>
                    <?php foreach (SCOPES as $scope): ?>
                      <?php if (in_array($scope, $def['scopes'], true)): ?>
                        <?php
                          $value    = $inputs[$scope][$metric] ?? null;
                          $unit     = $def['unit'];
                          $required = !empty($def['required']);
                          $groupKey = '';
                          if ($metric === 'bank_balance') {
                              $groupKey = 'bank';
                          } elseif (str_starts_with($metric, 'inv_')) {
                              $groupKey = 'inventory';
                          } elseif (str_starts_with($metric, 'cost_')) {
                              $groupKey = 'cost';
                          } elseif (str_starts_with($metric, 'production_')) {
                              $groupKey = 'production';
                          } elseif ($metric === 'sales_target') {
                              $groupKey = 'target';
                          } elseif (str_contains($metric, 'adjustment')) {
                              $groupKey = 'adjustment';
                          }

                          $apiPresent = $metric === 'cost_petty_cash' && !empty($pettyCash['present'][$scope]);
                          $apiAmount  = $apiPresent ? (float)$pettyCash['values'][$scope] : 0.0;
                          $apiLabel   = $closed ? 'Frozen' : 'API';
                          $apiPreview = $metric === 'cost_petty_cash'
                              ? ($apiPresent ? $apiLabel . ': Rp ' . format_idr($apiAmount, 2) : 'No API records')
                              : '';
                          $previewText = '';
                          if ($value !== null) {
                              $previewText = $metric === 'cost_petty_cash'
                                  ? 'Override: Rp ' . format_idr((float)$value, 2)
                                  : '';
                          } elseif ($apiPreview !== '') {
                              $previewText = $apiPreview;
                          }
                          $ariaLabel = $def['label'] . ' — ' . scope_label($scope);
                          if ($apiPreview !== '') {
                              $ariaLabel .= ' — ' . $apiPreview;
                          }
                        ?>
                        <td class="cell">
                          <div class="cell-wrap">
                            <input
                              class="cell-input"
                              type="number"
                              step="<?= $unit === 'rolls' ? '1' : '0.01' ?>"
                              inputmode="decimal"
                              autocomplete="off"
                              name="v[<?= e($scope) ?>][<?= e($metric) ?>]"
                              value="<?= $value === null ? '' : e(number_format($value, 2, '.', '')) ?>"
                              data-unit="<?= e($unit) ?>"
                              data-required="<?= $required ? '1' : '0' ?>"
                              data-group="<?= e($groupKey) ?>"
                              data-touched="0"
                              data-api-present="<?= $apiPresent ? '1' : '0' ?>"
                              data-api-amount="<?= $apiPresent ? e(number_format($apiAmount, 2, '.', '')) : '' ?>"
                              data-api-preview="<?= e($apiPreview) ?>"
                              aria-label="<?= e($ariaLabel) ?>"
                              <?= $closed ? 'disabled' : '' ?>>
                            <span class="cell-preview" aria-hidden="true"><?= e($previewText) ?></span>
                          </div>
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

      <div class="sheet">
        <div class="sheet-head">
          <div class="sheet-title-wrap"><span class="sheet-no">B</span><span class="sheet-title">Month note</span></div>
        </div>
        <div class="sheet-body">
          <label class="sr-only" for="note">Month note</label>
          <textarea class="textarea" id="note" name="note" rows="4" placeholder="Reason for adjustments, missing documents, etc." <?= $closed ? 'disabled' : '' ?>><?= e($note) ?></textarea>
          <p class="small muted mt-8">Required when you enter a sales or purchase adjustment.</p>
        </div>
      </div>

      <div class="sticky-actions">
        <div class="meta">
          <?= $closed ? 'Month is closed.' : 'Changes are audited with your user and timestamp.' ?>
          <?php if ($savedAt): ?><span class="muted"> Last saved <?= e($savedAt) ?>.</span><?php endif; ?>
        </div>
        <div class="row">
          <?php if (!$closed): ?>
            <button type="submit" class="btn btn-signal">Save monthly input</button>
          <?php endif; ?>
          <a class="btn btn-outline" href="report?period=<?= urlencode($period) ?>">Go to report</a>
        </div>
      </div>
    </div>

    <aside class="entry-rail">
      <div class="sheet">
        <div class="sheet-head">
          <div class="sheet-title-wrap"><span class="sheet-no">C</span><span class="sheet-title">Completion</span></div>
        </div>
        <div class="sheet-body">
          <div class="completion">
            <span class="completion-number" data-completion><?= $filledRequired ?>/<?= $requiredTotal ?></span>
            <span class="completion-label">required</span>
          </div>
          <div class="ticks" aria-hidden="true">
            <?php for ($i = 0; $i < $requiredTotal; $i++): ?>
              <span class="tick <?= $i < $filledRequired ? 'on' : '' ?>" data-tick></span>
            <?php endfor; ?>
          </div>
          <p class="small muted mt-8">Blank means not entered, not zero. Required fields are checked only when you close the month.</p>
        </div>
      </div>

      <div class="sheet">
        <div class="sheet-head">
          <div class="sheet-title-wrap"><span class="sheet-no">D</span><span class="sheet-title">Manual totals</span></div>
        </div>
        <div class="sheet-body tight">
          <table class="manual-totals">
            <tr><td class="label">Bank</td><td class="num" data-total-for="bank">Rp <?= e(number_format($bankTotal, 0, ',', '.')) ?></td></tr>
            <tr><td class="label">Inventory</td><td class="num" data-total-for="inventory">Rp <?= e(number_format($inventoryTotal, 0, ',', '.')) ?></td></tr>
            <tr><td class="label">Operating costs</td><td class="num" data-total-for="cost">Rp <?= e(number_format($costTotal, 0, ',', '.')) ?></td></tr>
          </table>
        </div>
      </div>

    </aside>
  </div>
</form>
<?php ui_footer(); ?>
