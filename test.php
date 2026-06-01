<?php
/**
 * test.php — Quick API connectivity & permission checker
 * Access: http://your-site/crmapi/test.php
 * DELETE or restrict this file in production.
 */
require_once __DIR__ . '/config.php';

// Simple endpoints to probe (least-privileged to most)
$probes = [
    'App/user'          => 'Current API user info',
    'SalesOrder'        => 'Sales Orders (list)',
    'PurchaseOrder'     => 'Purchase Orders (list)',
    'Quote'             => 'Quotes (list)',
    'CPayroll'          => 'Payroll (list)',
    'CPettyCash'        => 'Petty Cash (list)',
];

function apiGet(string $baseUrl, string $apiKey, string $endpoint): array
{
    $url = rtrim($baseUrl, '/') . '/api/v1/' . $endpoint . '?maxSize=1';
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_HTTPHEADER     => [
            'X-Api-Key: ' . $apiKey,
            'Accept: application/json',
        ],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HEADER         => true,   // include response headers for debugging
    ]);
    $raw     = curl_exec($ch);
    $code    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $hdrSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $cerr    = curl_error($ch);
    curl_close($ch);

    $body    = substr($raw, $hdrSize);
    $decoded = json_decode($body, true);

    return [
        'url'     => $url,
        'code'    => $code,
        'curl_err'=> $cerr,
        'message' => $decoded['message'] ?? $decoded['error'] ?? null,
        'body'    => $decoded,
    ];
}

$results = [];
foreach (INSTANCES as $siteKey => $cfg) {
    foreach ($probes as $endpoint => $label) {
        $r = apiGet($cfg['url'], $cfg['api_key'], $endpoint);
        $results[$siteKey][] = array_merge(['endpoint' => $endpoint, 'label' => $label], $r);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>API Diagnostics</title>
<style>
  body { font-family: system-ui, sans-serif; background: #f0f2f5; color: #1e293b; padding: 24px; }
  h1 { font-size: 1.2rem; margin-bottom: 20px; }
  h2 { font-size: 1rem; margin: 20px 0 10px; color: #2563eb; }
  table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 8px; overflow: hidden; margin-bottom: 24px; box-shadow: 0 1px 4px rgba(0,0,0,.08); }
  th { background: #f8fafc; padding: 10px 14px; text-align: left; font-size: .78rem; text-transform: uppercase; letter-spacing: .05em; color: #64748b; border-bottom: 1px solid #e2e8f0; }
  td { padding: 10px 14px; border-bottom: 1px solid #f1f5f9; font-size: .85rem; vertical-align: top; }
  tr:last-child td { border-bottom: none; }
  .ok   { color: #16a34a; font-weight: 700; }
  .fail { color: #dc2626; font-weight: 700; }
  .warn { color: #d97706; font-weight: 700; }
  .hint { background: #fffbeb; border: 1px solid #fde68a; border-radius: 6px; padding: 10px 14px; font-size: .82rem; margin-top: 4px; line-height: 1.5; }
  .badge { display:inline-block; padding: 2px 8px; border-radius: 99px; font-size:.75rem; font-weight:600; }
  .b200 { background:#dcfce7; color:#15803d; }
  .b401 { background:#fee2e2; color:#b91c1c; }
  .b403 { background:#fee2e2; color:#b91c1c; }
  .b404 { background:#fef3c7; color:#b45309; }
  .b500 { background:#fee2e2; color:#b91c1c; }
  .b0   { background:#f1f5f9; color:#475569; }
  pre { background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:8px; font-size:.75rem; white-space:pre-wrap; word-break:break-all; margin-top:4px; }
</style>
</head>
<body>
<h1>&#128300; EspoCRM API Diagnostics</h1>

<?php foreach ($results as $siteKey => $checks): ?>
<h2><?= htmlspecialchars(INSTANCES[$siteKey]['name']) ?>
  <small style="font-weight:400;color:#64748b;font-size:.82rem">
    &mdash; <?= htmlspecialchars(INSTANCES[$siteKey]['url']) ?>
  </small>
</h2>
<table>
  <thead>
    <tr><th>Endpoint</th><th>HTTP</th><th>Status</th><th>Details / Fix</th></tr>
  </thead>
  <tbody>
  <?php foreach ($checks as $c): ?>
  <?php
    $ok   = $c['code'] === 200;
    $cls  = $ok ? 'ok' : 'fail';
    $bcls = 'b' . ($c['code'] ?: '0');

    $hint = '';
    if ($c['curl_err']) {
        $hint = '&#10006; cURL error: ' . htmlspecialchars($c['curl_err']) . '<br>Check that the URL is reachable from your PHP server.';
    } elseif ($c['code'] === 401) {
        $hint = '&#128273; <strong>API key rejected.</strong> Go to EspoCRM &rarr; Admin &rarr; Users &rarr; [API user] and verify the API Key value matches config.php.';
    } elseif ($c['code'] === 403) {
        $hint = '&#128274; <strong>Forbidden.</strong> The API user does not have permission. Fix in EspoCRM:<br>'
              . '1. Admin &rarr; Roles &rarr; [user\'s role] &rarr; enable <strong>API Access</strong>.<br>'
              . '2. Ensure the role grants <strong>Read</strong> access to the <em>' . htmlspecialchars($c['endpoint']) . '</em> module.<br>'
              . '3. Re-save the user after changing the role.';
    } elseif ($c['code'] === 404) {
        $hint = '&#10067; Module not found. Check the exact module name in EspoCRM Admin &rarr; Entity Manager.';
    } elseif ($c['code'] === 0) {
        $hint = '&#128268; Could not connect. Check the base URL and that the server is reachable.';
    }

    $userInfo = '';
    if ($ok && $c['endpoint'] === 'App/user') {
        $b = $c['body'];
        $uname = ($b['name'] ?? '') . ' (' . ($b['userName'] ?? '') . ')';
        $roles = implode(', ', array_column($b['roles'] ?? [], 'name'));
        $userInfo = 'Logged in as: <strong>' . htmlspecialchars($uname) . '</strong>'
                  . ($roles ? ' &mdash; Roles: ' . htmlspecialchars($roles) : '');
    }
  ?>
  <tr>
    <td><code><?= htmlspecialchars($c['endpoint']) ?></code><br><small style="color:#64748b"><?= htmlspecialchars($c['label']) ?></small></td>
    <td><span class="badge <?= $bcls ?>"><?= $c['code'] ?: 'ERR' ?></span></td>
    <td class="<?= $cls ?>"><?= $ok ? '&#10004; OK' : '&#10006; FAIL' ?></td>
    <td>
      <?php if ($c['message']): ?>
        <strong>Error:</strong> <?= htmlspecialchars($c['message']) ?><br>
      <?php endif; ?>
      <?php if ($hint): ?>
        <div class="hint"><?= $hint ?></div>
      <?php endif; ?>
      <?php if ($userInfo): ?>
        <div class="hint" style="background:#f0fdf4;border-color:#86efac"><?= $userInfo ?></div>
      <?php endif; ?>
    </td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endforeach; ?>

<h2 style="font-size:1.05rem;margin:32px 0 8px;color:#2563eb;border-bottom:2px solid #dbeafe;padding-bottom:6px">
  &#128269; Field Inspector — see all fields returned by a module
</h2>
<p style="font-size:.85rem;margin-bottom:12px">
  Use this to find the correct <strong>amount field name</strong> when Total Amount shows 0.
</p>
<form method="get" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;margin-bottom:16px">
  <input type="hidden" name="inspect" value="1">
  <div>
    <label style="display:block;font-size:.75rem;font-weight:600;color:#64748b;margin-bottom:3px">SITE</label>
    <select name="isite" style="height:36px;padding:0 10px;border:1px solid #e2e8f0;border-radius:6px;font-size:.85rem">
      <?php foreach (INSTANCES as $sk => $sc): ?>
      <option value="<?= htmlspecialchars($sk) ?>" <?= ($_GET['isite'] ?? '') === $sk ? 'selected' : '' ?>>
        <?= htmlspecialchars($sc['name']) ?>
      </option>
      <?php endforeach; ?>
    </select>
  </div>
  <div>
    <label style="display:block;font-size:.75rem;font-weight:600;color:#64748b;margin-bottom:3px">MODULE NAME</label>
    <input name="imodule" value="<?= htmlspecialchars($_GET['imodule'] ?? 'CPettyCash') ?>"
           style="height:36px;padding:0 10px;border:1px solid #e2e8f0;border-radius:6px;font-size:.85rem;width:200px">
  </div>
  <button type="submit" style="height:36px;padding:0 16px;background:#2563eb;color:#fff;border:none;border-radius:6px;font-size:.85rem;font-weight:600;cursor:pointer">
    Inspect Fields
  </button>
</form>

<?php if (!empty($_GET['inspect'])): ?>
<?php
  $isite   = $_GET['isite']   ?? array_key_first(INSTANCES);
  $imodule = preg_replace('/[^a-zA-Z0-9_]/', '', $_GET['imodule'] ?? '');
  if (isset(INSTANCES[$isite]) && $imodule) {
      $cfg  = INSTANCES[$isite];
      $res  = apiGet($cfg['url'], $cfg['api_key'], $imodule);
      $record = $res['body']['list'][0] ?? null;
  } else {
      $record = null;
      $res = ['code' => 0, 'curl_err' => 'Invalid input'];
  }
?>
<?php if ($record): ?>
<table>
  <thead><tr><th>Field Name (use in EspoClient.php)</th><th>Value from first record</th><th>Type</th></tr></thead>
  <tbody>
  <?php foreach ($record as $field => $val): ?>
  <tr>
    <td><code><?= htmlspecialchars($field) ?></code></td>
    <td style="word-break:break-all"><?= htmlspecialchars(is_array($val) ? json_encode($val) : (string)$val) ?></td>
    <td style="color:#64748b;font-size:.78rem"><?= gettype($val) ?></td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php else: ?>
<div style="background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:12px 16px;font-size:.85rem;color:#b91c1c">
  No records returned. HTTP <?= $res['code'] ?>.
  <?= $res['curl_err'] ? htmlspecialchars($res['curl_err']) : '' ?>
  <?= isset($res['body']['message']) ? htmlspecialchars($res['body']['message']) : '' ?>
</div>
<?php endif; ?>
<?php endif; ?>

<p style="font-size:.78rem;color:#94a3b8;margin-top:24px">
  &#9888; Delete or password-protect <code>test.php</code> before going to production.
</p>
</body>
</html>
