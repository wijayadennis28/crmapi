<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/EspoClient.php';

// ── Inputs ────────────────────────────────────────────────────────────────────
$year         = (int)($_GET['year']   ?? date('Y'));
$activeModule = $_GET['module'] ?? array_key_first(MODULES);
$activeSite   = $_GET['site']   ?? array_key_first(INSTANCES);

if (!array_key_exists($activeModule, MODULES)) {
    $activeModule = array_key_first(MODULES);
}
if (!array_key_exists($activeSite, INSTANCES)) {
    $activeSite = array_key_first(INSTANCES);
}
if ($year < 2000 || $year > 2100) {
    $year = (int)date('Y');
}

$client = new EspoClient($activeSite);
$error  = null;
$summary = [];

try {
    $summary = $client->monthlySummary($activeModule, $year);
} catch (RuntimeException $e) {
    $error = $e->getMessage();
}

// ── Aggregates ────────────────────────────────────────────────────────────────
$totalCount  = array_sum(array_column($summary, 'count'));
$totalAmount = array_sum(array_column($summary, 'total'));
$months      = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
$chartCounts = [];
$chartAmounts = [];
foreach ($summary as $mo => $data) {
    $chartCounts[]  = $data['count'];
    $chartAmounts[] = round($data['total'], 2);
}
$peakMonth = !empty($chartCounts) ? $months[array_search(max($chartCounts), $chartCounts)] : '-';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars(INSTANCES[$activeSite]['name']) ?> — CRM Dashboard</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
</head>
<body>

<div class="loader-overlay" id="loader"><div class="spinner"></div></div>

<div class="layout">

  <!-- Sidebar -->
  <aside class="sidebar">
    <div class="sidebar-logo">
      <h2>&#9685; TOP CRM Dashboard</h2>
      <p>TOP Analytics</p>
    </div>
    <!-- Site switcher tabs -->
    <div style="padding:10px 12px;border-bottom:1px solid var(--border);display:flex;gap:6px;flex-wrap:wrap">
      <?php foreach (INSTANCES as $siteKey => $siteCfg): ?>
      <a href="?site=<?= urlencode($siteKey) ?>&module=<?= urlencode($activeModule) ?>&year=<?= $year ?>"
         class="btn <?= $activeSite === $siteKey ? 'btn-primary' : 'btn-outline' ?>"
         style="height:28px;font-size:.75rem;padding:0 10px">
        <?= htmlspecialchars($siteCfg['name']) ?>
      </a>
      <?php endforeach; ?>
    </div>
    <nav class="sidebar-nav">
      <?php foreach (MODULES as $key => $label): ?>
      <a href="?site=<?= urlencode($activeSite) ?>&module=<?= urlencode($key) ?>&year=<?= $year ?>"
         class="<?= $activeModule === $key ? 'active' : '' ?>">
        <span class="nav-icon">&#9632;</span>
        <?= htmlspecialchars($label) ?>
      </a>
      <?php endforeach; ?>
    </nav>
  </aside>

  <!-- Main -->
  <div class="main">
    <header class="topbar">
      <h1><?= htmlspecialchars(INSTANCES[$activeSite]['name']) ?> &mdash; <?= htmlspecialchars(MODULES[$activeModule]) ?> &mdash; <?= $year ?></h1>
      <div class="topbar-right">
        <span style="font-size:.8rem;color:var(--muted)">Cached <?= CACHE_TTL ?>s</span>
      </div>
    </header>

    <div class="content">

      <?php if ($error): ?>
      <div class="alert alert-error">&#9888; <?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <!-- Controls -->
      <form method="get" class="controls" id="filterForm">
        <input type="hidden" name="module" value="<?= htmlspecialchars($activeModule) ?>">
        <input type="hidden" name="site"   value="<?= htmlspecialchars($activeSite) ?>">
        <div class="control-group">
          <label>Year</label>
          <input type="number" name="year" value="<?= $year ?>" min="2000" max="2100" style="width:90px">
        </div>
        <button type="submit" class="btn btn-primary" onclick="document.getElementById('loader').classList.add('show')">
          &#8635; Refresh
        </button>
        <a href="export.php?site=<?= urlencode($activeSite) ?>&module=<?= urlencode($activeModule) ?>&year=<?= $year ?>&month=all&format=csv"
           class="btn btn-success" id="csvBtn">
          &#8659; Export CSV
        </a>
        <a href="export.php?site=<?= urlencode($activeSite) ?>&module=<?= urlencode($activeModule) ?>&year=<?= $year ?>&month=all&format=excel"
           class="btn btn-accent" id="xlsBtn">
          &#8659; Export Excel
        </a>
      </form>

      <!-- Stat cards -->
      <div class="stat-grid">
        <div class="stat-card blue">
          <div class="label">Total Records</div>
          <div class="value"><?= number_format($totalCount) ?></div>
          <div class="sub">All months <?= $year ?></div>
        </div>
        <div class="stat-card green">
          <div class="label">Total Amount</div>
          <div class="value"><?= number_format($totalAmount, 0) ?></div>
          <div class="sub">Sum of order values</div>
        </div>
        <div class="stat-card amber">
          <div class="label">Avg / Month</div>
          <div class="value"><?= $totalCount ? number_format($totalAmount / max(1, array_sum(array_map(fn($v) => $v['count'] > 0 ? 1 : 0, $summary))), 0) : '0' ?></div>
          <div class="sub">Active months only</div>
        </div>
        <div class="stat-card red">
          <div class="label">Peak Month</div>
          <div class="value"><?= $peakMonth ?></div>
          <div class="sub">Highest record count</div>
        </div>
      </div>

      <!-- Charts -->
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px">
        <div class="chart-card">
          <h3>Monthly Record Count</h3>
          <div class="chart-wrap"><canvas id="chartCount"></canvas></div>
        </div>
        <div class="chart-card">
          <h3>Monthly Total Amount</h3>
          <div class="chart-wrap"><canvas id="chartAmount"></canvas></div>
        </div>
      </div>

      <!-- Monthly breakdown table -->
      <div class="table-card">
        <div class="table-card-header">
          <h3>Monthly Breakdown &mdash; <?= htmlspecialchars(MODULES[$activeModule]) ?> (<?= $year ?>)</h3>
          <div style="display:flex;gap:8px">
            <?php for ($m = 1; $m <= 12; $m++):
              $mk = str_pad($m, 2, '0', STR_PAD_LEFT); ?>
            <a href="export.php?site=<?= urlencode($activeSite) ?>&module=<?= urlencode($activeModule) ?>&year=<?= $year ?>&month=<?= $mk ?>&format=csv"
               class="btn btn-outline" style="height:30px;font-size:.75rem;padding:0 10px">
              <?= $months[$m-1] ?> CSV
            </a>
            <?php endfor; ?>
          </div>
        </div>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Month</th>
                <th>Records</th>
                <th>Total Amount</th>
                <th>Export</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($summary as $mk => $data):
                $idx   = (int)$mk - 1;
                $badge = $data['count'] === 0 ? 'badge-gray' : ($data['count'] === max($chartCounts) ? 'badge-green' : 'badge-blue');
              ?>
              <tr>
                <td><strong><?= $months[$idx] ?> <?= $year ?></strong></td>
                <td><span class="badge <?= $badge ?>"><?= number_format($data['count']) ?></span></td>
                <td><?= number_format($data['total'], 2) ?></td>
                <td>
                  <a href="export.php?site=<?= urlencode($activeSite) ?>&module=<?= urlencode($activeModule) ?>&year=<?= $year ?>&month=<?= $mk ?>&format=csv"
                     class="btn btn-success" style="height:26px;font-size:.73rem;padding:0 8px">CSV</a>
                  <a href="export.php?site=<?= urlencode($activeSite) ?>&module=<?= urlencode($activeModule) ?>&year=<?= $year ?>&month=<?= $mk ?>&format=excel"
                     class="btn btn-accent"  style="height:26px;font-size:.73rem;padding:0 8px;margin-left:4px">XLS</a>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div><!-- /content -->
  </div><!-- /main -->
</div><!-- /layout -->

<script>
const months  = <?= json_encode($months) ?>;
const counts  = <?= json_encode($chartCounts) ?>;
const amounts = <?= json_encode($chartAmounts) ?>;

const sharedOpts = {
  responsive: true, maintainAspectRatio: false,
  plugins: { legend: { display: false } },
  scales: {
    x: { grid: { display: false } },
    y: { beginAtZero: true, grid: { color: '#f1f5f9' } }
  }
};

new Chart(document.getElementById('chartCount'), {
  type: 'bar',
  data: {
    labels: months,
    datasets: [{ data: counts, backgroundColor: '#2563eb', borderRadius: 5 }]
  },
  options: sharedOpts
});

new Chart(document.getElementById('chartAmount'), {
  type: 'line',
  data: {
    labels: months,
    datasets: [{
      data: amounts,
      borderColor: '#0ea5e9',
      backgroundColor: 'rgba(14,165,233,.1)',
      fill: true, tension: .35, pointRadius: 4,
      pointBackgroundColor: '#0ea5e9'
    }]
  },
  options: sharedOpts
});
</script>

<script>
// Auto-shrink stat values that are too long to fit at the default size
document.querySelectorAll('.stat-card .value').forEach(function(el) {
  var len = el.textContent.replace(/[^0-9]/g, '').length;
  if      (len >= 13) el.style.fontSize = '1.05rem';
  else if (len >= 10) el.style.fontSize = '1.25rem';
  else if (len >= 7)  el.style.fontSize = '1.5rem';
});
</script>
</body>
</html>
