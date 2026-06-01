<?php
/**
 * HOW_TO_CUSTOMIZE.php
 * ====================
 * Quick reference for maintaining this dashboard.
 * Open in browser: http://your-site/crmapi/HOW_TO_CUSTOMIZE.php
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>How to Customize</title>
<style>
  body { font-family: system-ui, sans-serif; background: #f0f2f5; color: #1e293b; padding: 32px; max-width: 860px; margin: auto; }
  h1   { font-size: 1.4rem; margin-bottom: 6px; }
  h2   { font-size: 1.05rem; margin: 32px 0 8px; color: #2563eb; border-bottom: 2px solid #dbeafe; padding-bottom: 6px; }
  p, li { font-size: .9rem; line-height: 1.7; }
  ul   { padding-left: 20px; }
  pre  { background: #1e293b; color: #e2e8f0; border-radius: 8px; padding: 16px 20px; font-size: .83rem; overflow-x: auto; line-height: 1.6; }
  code { background: #f1f5f9; padding: 2px 6px; border-radius: 4px; font-size: .85rem; color: #0f172a; }
  .box { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 20px 24px; margin-bottom: 24px; box-shadow: 0 1px 4px rgba(0,0,0,.06); }
  .tip { background: #fffbeb; border: 1px solid #fde68a; border-radius: 8px; padding: 12px 16px; font-size: .85rem; margin-top: 12px; }
  .warn{ background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 12px 16px; font-size: .85rem; margin-top: 12px; }
  .step{ display:inline-block; background:#2563eb; color:#fff; border-radius:99px; width:22px; height:22px; text-align:center; line-height:22px; font-size:.75rem; font-weight:700; margin-right:6px; }
</style>
</head>
<body>

<h1>&#128218; How to Customize the CRM Dashboard</h1>
<p>Everything is controlled from a single file: <code>config.php</code>.</p>

<!-- ═══════════════════════════════════════════════════════════════════ -->
<h2>1. Add a New Module</h2>
<div class="box">
  <p>Find the <code>MODULES</code> array in <code>config.php</code> and add a new line:</p>
  <pre><code>define('MODULES', [
    'SalesOrder'    => 'Sales Orders',
    'PurchaseOrder' => 'Purchase Orders',
    // ... existing entries ...

    'YourModuleName' => 'Display Name Here',  // ← add this
]);</code></pre>

  <p><strong>Where do I find the correct module name?</strong></p>
  <ul>
    <li>Go to EspoCRM → <strong>Admin → Entity Manager</strong></li>
    <li>Look at the <strong>Name</strong> column (NOT the Label column)</li>
    <li>Use that exact value, e.g. <code>CPayroll</code>, <code>DeliveryOrder</code></li>
  </ul>

  <div class="tip">
    &#128161; <strong>Tip:</strong> Custom modules usually start with <code>C</code> (e.g. <code>CPayroll</code>).
    Standard Sales modules use plain names like <code>SalesOrder</code>.
  </div>

  <p style="margin-top:14px"><strong>Example — adding Delivery Order:</strong></p>
  <pre><code>'DeliveryOrder' => 'Delivery Orders',</code></pre>
</div>

<!-- ═══════════════════════════════════════════════════════════════════ -->
<h2>2. Add a New EspoCRM Site / URL</h2>
<div class="box">
  <p>Find the <code>INSTANCES</code> array in <code>config.php</code> and add a new entry:</p>
  <pre><code>define('INSTANCES', [
    'site1' => [
        'name'    => 'Site 1',
        'url'     => 'https://crm1.example.com',
        'api_key' => 'abc123...',
    ],
    'site2' => [
        'name'    => 'Site 2',
        'url'     => 'https://crm2.example.com',
        'api_key' => 'def456...',
    ],

    // ← Add your new site below:
    'site3' => [
        'name'    => 'Site 3',                          // shown in the sidebar tab
        'url'     => 'https://crm3.example.com',        // no trailing slash
        'api_key' => 'your-api-key-for-site-3',
    ],
]);</code></pre>

  <p><strong>How to get an API key from EspoCRM:</strong></p>
  <ul>
    <li><span class="step">1</span> Go to <strong>Admin → Users → Create User</strong></li>
    <li><span class="step">2</span> Set <strong>User Type = API</strong></li>
    <li><span class="step">3</span> Open the <strong>API Key</strong> tab → click <strong>Generate</strong> → copy the key</li>
    <li><span class="step">4</span> Assign the user a <strong>Role</strong> with <em>API Access</em> enabled and <em>Read</em> on the modules you need</li>
    <li><span class="step">5</span> Save the user</li>
  </ul>

  <div class="tip">
    &#128161; The site key (<code>site1</code>, <code>site2</code> …) is just an internal identifier.
    You can name it anything — no spaces, use letters/numbers/underscores only.
  </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════ -->
<h2>3. Change the Amount Field</h2>
<div class="box">
  <p>
    The dashboard sums up order values automatically. It tries these field names in order:
    <code>grandTotalAmount</code> → <code>amount</code> → <code>totalAmount</code>.
  </p>
  <p>
    If your custom module uses a different field name (e.g. <code>totalValue</code>),
    open <code>EspoClient.php</code> and find this line inside <code>monthlySummary()</code>:
  </p>
  <pre><code>$total += (float)($r['grandTotalAmount'] ?? $r['amount'] ?? $r['totalAmount'] ?? 0);</code></pre>
  <p>Add your field name to the chain:</p>
  <pre><code>$total += (float)($r['grandTotalAmount'] ?? $r['amount'] ?? $r['totalAmount'] ?? $r['totalValue'] ?? 0);</code></pre>
</div>

<!-- ═══════════════════════════════════════════════════════════════════ -->
<h2>4. Change the Date Filter Field</h2>
<div class="box">
  <p>
    By default, records are filtered by <code>createdAt</code>.
    If you want to filter by a different date (e.g. <code>dateOrdered</code>, <code>dateInvoiced</code>),
    change this line in <code>config.php</code>:
  </p>
  <pre><code>define('DATE_FIELD', 'createdAt');   // ← change to your field name</code></pre>
  <div class="tip">
    &#128161; The field name must match exactly what EspoCRM uses internally
    (check via Admin → Entity Manager → [Module] → Fields).
  </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════ -->
<h2>5. Adjust Cache Duration</h2>
<div class="box">
  <p>
    API results are cached for 5 minutes by default to avoid hammering the server on every page load.
    Change it in <code>config.php</code>:
  </p>
  <pre><code>define('CACHE_TTL', 300);   // seconds — set to 0 to disable caching</code></pre>
  <p>To clear the cache manually, just delete all files inside the <code>cache/</code> folder.</p>
</div>

<!-- ═══════════════════════════════════════════════════════════════════ -->
<h2>6. File Overview</h2>
<div class="box">
  <ul>
    <li><code>config.php</code> — <strong>All settings live here</strong>: URLs, API keys, modules, cache TTL</li>
    <li><code>EspoClient.php</code> — API communication, pagination, caching logic</li>
    <li><code>index.php</code> — Dashboard UI (charts, stat cards, monthly table)</li>
    <li><code>export.php</code> — CSV &amp; Excel download handler</li>
    <li><code>test.php</code> — API connectivity checker (delete in production)</li>
    <li><code>assets/style.css</code> — All styling</li>
    <li><code>cache/</code> — Temporary API response cache (auto-created)</li>
  </ul>
</div>

<div class="warn">
  &#9888; <strong>Security reminder:</strong> Delete or restrict access to
  <code>test.php</code> and <code>HOW_TO_CUSTOMIZE.php</code> once you go live.
  They expose your site structure and should not be publicly accessible.
</div>

</body>
</html>
