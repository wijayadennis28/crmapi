<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/store.php';
require_once __DIR__ . '/ui.php';
require_login();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id = !empty($_POST['id']) ? (int)$_POST['id'] : null;
        $errors = save_custom_field($_POST, $id);
        if (!$errors) {
            header('Location: fields?saved=1');
            exit;
        }
    } elseif ($action === 'toggle') {
        set_custom_field_active((int)($_POST['id'] ?? 0), !empty($_POST['active']));
        header('Location: fields?toggled=1');
        exit;
    }
}

$editId  = (int)($_GET['edit'] ?? 0);
$editing = $editId > 0 ? custom_field_by_id($editId) : null;

if ($editId > 0 && $editing === null) {
    $errors[] = 'Field not found.';
    $editId = 0;
}

$fields = custom_field_defs(false);
$nextSort = 10;
foreach ($fields as $field) {
    $nextSort = max($nextSort, (int)$field['sort_order'] + 10);
}

$form = $editing ?? [
    'id' => 0,
    'label' => '',
    'group_name' => 'Other costs',
    'role' => 'cost',
    'required' => 0,
    'allow_negative' => 0,
    'sort_order' => $nextSort,
    'active' => 1,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $errors) {
    $form = [
        'id' => (int)($_POST['id'] ?? 0),
        'label' => trim((string)($_POST['label'] ?? '')),
        'group_name' => trim((string)($_POST['group_name'] ?? '')),
        'role' => ($_POST['role'] ?? 'cost') === 'info' ? 'info' : 'cost',
        'required' => !empty($_POST['required']) ? 1 : 0,
        'allow_negative' => !empty($_POST['allow_negative']) ? 1 : 0,
        'sort_order' => (int)($_POST['sort_order'] ?? 0),
        'active' => !empty($_POST['active']) ? 1 : 0,
    ];
}

ui_header('Custom Fields', 'fields', ['mobile_meta' => 'CUSTOM FIELDS']);
?>
<div class="docket-head">
  <div class="eyebrow">
    <span class="doc-no">05 / FIELDS</span>
    <span class="dot">/</span>
    <span>Custom manual fields</span>
  </div>
  <div class="docket-title-row">
    <div>
      <h1 class="docket-title">Custom fields</h1>
      <p class="docket-sub">Define a field once; it appears in every monthly form for PT TOP, CV MMP, and CV SAE. Cost fields count in total costs; info fields are display only.</p>
    </div>
    <div class="docket-actions">
      <a class="btn btn-outline btn-sm" href="input">Monthly input</a>
      <a class="btn btn-outline btn-sm" href="report">Report</a>
    </div>
  </div>
</div>

<?php if (isset($_GET['saved'])): ?>
  <div class="notice notice-ok" role="status">Custom field saved.</div>
<?php endif; ?>
<?php if (isset($_GET['toggled'])): ?>
  <div class="notice notice-ok" role="status">Field status updated.</div>
<?php endif; ?>
<?php foreach ($errors as $error): ?>
  <div class="notice notice-danger" role="alert"><?= e($error) ?></div>
<?php endforeach; ?>

<div class="sheet">
  <div class="sheet-head">
    <div class="sheet-title-wrap">
      <span class="sheet-no">A</span>
      <span class="sheet-title"><?= $editing ? 'Edit field' : 'Add field' ?></span>
    </div>
    <span class="sheet-meta"><?= $editing ? 'custom_' . (int)$editing['id'] : 'new' ?></span>
  </div>
  <div class="sheet-body">
    <form method="post">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" value="<?= (int)$form['id'] ?>">

      <div class="form-grid">
        <div class="field">
          <label for="label">Label</label>
          <input class="input" id="label" name="label" value="<?= e($form['label']) ?>" placeholder="e.g. Other operating cost" required>
        </div>
        <div class="field">
          <label for="group_name">Group</label>
          <input class="input" id="group_name" name="group_name" value="<?= e($form['group_name']) ?>" placeholder="Other costs" required>
        </div>
        <div class="field">
          <label for="role">Role</label>
          <select class="select" id="role" name="role">
            <option value="cost" <?= $form['role'] === 'cost' ? 'selected' : '' ?>>Cost — affects totals</option>
            <option value="info" <?= $form['role'] === 'info' ? 'selected' : '' ?>>Info — display only</option>
          </select>
        </div>
        <div class="field">
          <label for="sort_order">Sort order</label>
          <input class="input" id="sort_order" name="sort_order" type="number" step="1" min="0" value="<?= (int)$form['sort_order'] ?>">
        </div>
      </div>

      <div class="check-row">
        <label class="check"><input type="checkbox" name="required" value="1" <?= !empty($form['required']) ? 'checked' : '' ?>> Required for close</label>
        <label class="check"><input type="checkbox" name="allow_negative" value="1" <?= !empty($form['allow_negative']) ? 'checked' : '' ?>> Allow negative</label>
        <label class="check"><input type="checkbox" name="active" value="1" <?= !empty($form['active']) ? 'checked' : '' ?>> Active</label>
      </div>

      <div class="row mt-16">
        <button type="submit" class="btn btn-signal"><?= $editing ? 'Save changes' : 'Add field' ?></button>
        <?php if ($editing): ?>
          <a class="btn btn-outline" href="fields">Cancel</a>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>

<div class="sheet">
  <div class="sheet-head">
    <div class="sheet-title-wrap"><span class="sheet-no">B</span><span class="sheet-title">Defined fields</span></div>
    <span class="sheet-meta"><?= count($fields) ?> field<?= count($fields) === 1 ? '' : 's' ?></span>
  </div>
  <div class="table-wrap">
    <table class="data">
      <thead>
        <tr><th>Label</th><th>Group</th><th>Role</th><th>Required</th><th>Status</th><th>Actions</th></tr>
      </thead>
      <tbody>
      <?php if (!$fields): ?>
        <tr data-empty="1"><td colspan="6" class="muted">No custom fields yet. Add one above to make it appear in the monthly input.</td></tr>
      <?php endif; ?>
      <?php foreach ($fields as $field): ?>
        <tr>
          <td>
            <strong><?= e($field['label']) ?></strong>
            <span class="small muted">custom_<?= (int)$field['id'] ?></span>
          </td>
          <td><?= e($field['group_name']) ?></td>
          <td>
            <span class="tag <?= $field['role'] === 'cost' ? 'tag-signal' : 'tag-muted' ?>">
              <?= e($field['role'] === 'cost' ? 'Cost' : 'Info') ?>
            </span>
          </td>
          <td><?= !empty($field['required']) ? '<span class="tag tag-muted">Yes</span>' : '<span class="muted">—</span>' ?></td>
          <td>
            <span class="tag <?= !empty($field['active']) ? 'tag-ok' : 'tag-muted' ?>">
              <?= !empty($field['active']) ? 'Active' : 'Inactive' ?>
            </span>
          </td>
          <td>
            <div class="row">
              <a class="btn btn-outline btn-sm" href="fields?edit=<?= (int)$field['id'] ?>">Edit</a>
              <form method="post" class="inline-form">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="toggle">
                <input type="hidden" name="id" value="<?= (int)$field['id'] ?>">
                <input type="hidden" name="active" value="<?= !empty($field['active']) ? 0 : 1 ?>">
                <button type="submit" class="btn btn-outline btn-sm"><?= !empty($field['active']) ? 'Deactivate' : 'Activate' ?></button>
              </form>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="sheet-body tight">
    <p class="small muted">Custom fields are per entity and money only. Deactivating hides a field from new months but keeps its history in past reports.</p>
  </div>
</div>
<?php ui_footer(); ?>
