<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/EspoClient.php';
require_once __DIR__ . '/ui.php';

// ── Inputs ────────────────────────────────────────────────────
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

// Flush cache for the current site + module + year when ?flush=1
if (!empty($_GET['flush'])) {
    $instances = INSTANCES;
    $sitesToFlush = isset($instances[$activeSite]['combined'])
        ? $instances[$activeSite]['combined']
        : [$activeSite];

    foreach ($sitesToFlush as $sk) {
        $prefix  = preg_replace('/[^a-z0-9_]/i', '_', "{$sk}_{$activeModule}_{$year}");
        $pattern = CACHE_DIR . '/' . $prefix . '_*.json';
        foreach (glob($pattern) ?: [] as $f) {
            unlink($f);
        }
    }
}

$client = new EspoClient($activeSite);
$error  = null;
$summary = [];

try {
    $summary = $client->monthlySummary($activeModule, $year);
} catch (RuntimeException $e) {
    $error = $e->getMessage();
}

// ── Aggregates ────────────────────────────────────────────────
$totalCount  = array_sum(array_column($summary, 'count'));
$totalAmount = array_sum(array_column($summary, 'total'));
$months      = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
$chartCounts = [];
$chartAmounts = [];
foreach ($summary as $mo => $data) {
    $chartCounts[]  = $data['count'];
    $chartAmounts[] = round($data['total'], 2);
}
$maxCount    = $chartCounts ? max($chartCounts) : 0;
$peakMonth   = $totalCount ? $months[array_search($maxCount, $chartCounts)] : '—';
$activeMonths = count(array_filter($summary, fn($v) => ($v['count'] ?? 0) > 0));
$avgPerMonth  = $activeMonths ? $totalAmount / $activeMonths : 0;
$siteLabel    = INSTANCES[$activeSite]['name'];
$moduleLabel  = MODULES[$activeModule];
$cacheNote    = !empty($_GET['flush']) && $_GET['flush'] === '1' ? 'Cache cleared' : 'Cached ' . CACHE_TTL . 's';

ui_header($moduleLabel . ' — ' . $siteLabel, 'index.php', [
    'head'        => '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>',
    'mobile_meta' => $siteLabel,
]);
?>
<div class="docket-head">
  <div class="eyebrow">
    <span class="doc-no">01 / DASHBOARD</span>
    <span class="dot">/</span>
    <span><?= $year ?></span>
    <span class="dot">/</span>
    <span><?= e($cacheNote) ?></span>
  </div>
  <div class="docket-title-row">
    <div>
      <h1 class="docket-title"><?= e($moduleLabel) ?> <span class="thin">/ <?= e($siteLabel) ?></span></h1>
      <p class="docket-sub">Live EspoCRM monthly totals. Switch site and module below, or open the monthly report desk.</p>
    </div>
    <div class="docket-actions">
      <span class="tag tag-signal">API live</span>
      <a class="btn btn-outline btn-sm" href="input.php">Monthly input</a>
      <a class="btn btn-outline btn-sm" href="report.php">Report</a>
    </div>
  </div>
</div>

<div class="dashboard-bar">
  <div class="tab-strip" aria-label="Sites">
    <?php foreach (INSTANCES as $siteKey => $siteCfg): ?>
      <a class="tab <?= $activeSite === $siteKey ? 'active' : '' ?>"
         href="?site=<?= urlencode($siteKey) ?>&module=<?= urlencode($activeModule) ?>&year=<?= $year ?>">
        <?= e($siteCfg['name']) ?>
      </a>
    <?php endforeach; ?>
  </div>
  <div class="module-strip" aria-label="Modules">
    <?php foreach (MODULES as $key => $label): ?>
      <a class="module-tab <?= $activeModule === $key ? 'active' : '' ?>"
         href="?site=<?= urlencode($activeSite) ?>&module=<?= urlencode($key) ?>&year=<?= $year ?>">
        <?= e($label) ?>
      </a>
    <?php endforeach; ?>
  </div>
</div>

<?php if ($error): ?>
  <div class="notice notice-danger" role="alert">Could not load EspoCRM: <?= e($error) ?></div>
<?php endif; ?>

<form method="get" class="toolbar">
  <input type="hidden" name="module" value="<?= e($activeModule) ?>">
  <input type="hidden" name="site" value="<?= e($activeSite) ?>">
  <div class="field">
    <label for="year">Year</label>
    <input class="input" type="number" id="year" name="year" value="<?= $year ?>" min="2000" max="2100">
  </div>
  <button type="submit" name="flush" value="0" class="btn btn-primary btn-sm">Refresh</button>
  <button type="submit" name="flush" value="1" class="btn btn-outline btn-sm" title="Clear cache and reload fresh data">Force refresh</button>
  <span class="spacer"></span>
  <div class="export-links">
    <a class="btn btn-outline btn-sm" href="export.php?site=<?= urlencode($activeSite) ?>&module=<?= urlencode($activeModule) ?>&year=<?= $year ?>&month=all&format=csv">Export CSV</a>
    <a class="btn btn-outline btn-sm" href="export.php?site=<?= urlencode($activeSite) ?>&module=<?= urlencode($activeModule) ?>&year=<?= $year ?>&month=all&format=excel">Export Excel</a>
  </div>
</form>

<div class="figures">
  <div class="figure">
    <div class="figure-kicker">Total records</div>
    <div class="figure-number"><?= e(number_format($totalCount, 0, ',', '.')) ?></div>
    <div class="figure-note">All months, <?= $year ?></div>
  </div>
  <div class="figure">
    <div class="figure-kicker">Total amount</div>
    <div class="figure-number">Rp <?= e(number_format($totalAmount, 0, ',', '.')) ?></div>
    <div class="figure-note">Sum of order values</div>
  </div>
  <div class="figure">
    <div class="figure-kicker">Average / active month</div>
    <div class="figure-number">Rp <?= e(number_format($avgPerMonth, 0, ',', '.')) ?></div>
    <div class="figure-note"><?= $activeMonths ?> active month<?= $activeMonths === 1 ? '' : 's' ?></div>
  </div>
  <div class="figure">
    <div class="figure-kicker">Peak month</div>
    <div class="figure-number small"><?= e($peakMonth) ?></div>
    <div class="figure-note">Highest record count</div>
  </div>
</div>

<div class="chart-grid">
  <div class="chart-sheet">
    <div class="chart-head">
      <span class="chart-title">Monthly record count</span>
      <span class="chart-meta"><?= e($moduleLabel) ?> / <?= $year ?></span>
    </div>
    <div class="chart-wrap"><canvas id="chartCount" aria-label="Monthly record count chart" role="img"></canvas></div>
  </div>
  <div class="chart-sheet">
    <div class="chart-head">
      <span class="chart-title">Monthly total amount</span>
      <span class="chart-meta">IDR / <?= $year ?></span>
    </div>
    <div class="chart-wrap"><canvas id="chartAmount" aria-label="Monthly total amount chart" role="img"></canvas></div>
  </div>
</div>

<div class="sheet">
  <div class="sheet-head">
    <div class="sheet-title-wrap"><span class="sheet-no">01</span><span class="sheet-title">Monthly breakdown</span></div>
    <span class="sheet-meta"><?= e($moduleLabel) ?> / <?= $year ?></span>
  </div>
  <div class="table-wrap">
    <table class="data">
      <thead>
        <tr>
          <th>Month</th>
          <th class="num">Records</th>
          <th class="num">Total amount</th>
          <th>Export</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($summary as $mk => $data):
          $idx = (int)$mk - 1;
          $isPeak = $data['count'] > 0 && $data['count'] === $maxCount;
        ?>
          <tr>
            <td>
              <strong><?= e($months[$idx]) ?> <?= $year ?></strong>
              <?php if ($isPeak): ?><span class="tag tag-signal ml-6">Peak</span><?php endif; ?>
            </td>
            <td class="num">
              <?php if ($data['count'] === 0): ?>
                <span class="tag tag-muted">0</span>
              <?php else: ?>
                <?= e(number_format($data['count'], 0, ',', '.')) ?>
              <?php endif; ?>
            </td>
            <td class="num"><?= e(number_format($data['total'], 2, ',', '.')) ?></td>
            <td>
              <a class="small" href="export.php?site=<?= urlencode($activeSite) ?>&module=<?= urlencode($activeModule) ?>&year=<?= $year ?>&month=<?= $mk ?>&format=csv">CSV</a>
              <span class="muted">/</span>
              <a class="small" href="export.php?site=<?= urlencode($activeSite) ?>&module=<?= urlencode($activeModule) ?>&year=<?= $year ?>&month=<?= $mk ?>&format=excel">XLS</a>
            </td>
          </tr>
        <?php endforeach; ?>
        <tr class="total-row">
          <td>Total</td>
          <td class="num"><?= e(number_format($totalCount, 0, ',', '.')) ?></td>
          <td class="num"><?= e(number_format($totalAmount, 2, ',', '.')) ?></td>
          <td></td>
        </tr>
      </tbody>
    </table>
  </div>
</div>

<script>
const months  = <?= json_encode($months) ?>;
const counts  = <?= json_encode($chartCounts) ?>;
const amounts = <?= json_encode($chartAmounts) ?>;

if (window.Chart) {
  Chart.defaults.font.family = "'Archivo', 'Helvetica Neue', Arial, sans-serif";
  Chart.defaults.font.size = 10;
  Chart.defaults.color = '#62665f';

  const compact = new Intl.NumberFormat('id-ID', { notation: 'compact', maximumFractionDigits: 1 });
  const money   = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 });

  const base = {
    responsive: true,
    maintainAspectRatio: false,
    animation: { duration: 180 },
    plugins: {
      legend: { display: false },
      tooltip: {
        backgroundColor: '#141414',
        titleFont: { family: "'Archivo', sans-serif", size: 11, weight: '600' },
        bodyFont: { family: "'Archivo', sans-serif", size: 11 },
        padding: 10,
        displayColors: false,
        cornerRadius: 1
      }
    },
    scales: {
      x: {
        grid: { display: false },
        border: { color: '#141414' },
        ticks: { font: { size: 10 } }
      },
      y: {
        beginAtZero: true,
        grid: { color: '#e6e8e2', drawTicks: false },
        border: { display: false },
        ticks: { font: { size: 10 }, padding: 6 }
      }
    }
  };

  new Chart(document.getElementById('chartCount'), {
    type: 'bar',
    data: {
      labels: months,
      datasets: [{
        data: counts,
        backgroundColor: '#c62828',
        hoverBackgroundColor: '#9f1d1d',
        borderRadius: 0,
        borderSkipped: false,
        barPercentage: 0.58
      }]
    },
    options: {
      ...base,
      plugins: {
        ...base.plugins,
        tooltip: {
          ...base.plugins.tooltip,
          callbacks: { label: (ctx) => `${money.format(ctx.parsed.y)} records` }
        }
      },
      scales: {
        ...base.scales,
        y: {
          ...base.scales.y,
          ticks: { ...base.scales.y.ticks, callback: (value) => compact.format(value) }
        }
      }
    }
  });

  new Chart(document.getElementById('chartAmount'), {
    type: 'line',
    data: {
      labels: months,
      datasets: [{
        data: amounts,
        borderColor: '#141414',
        backgroundColor: 'rgba(198, 40, 40, .14)',
        fill: true,
        tension: 0.25,
        borderWidth: 2,
        pointRadius: 3,
        pointBackgroundColor: '#141414',
        pointBorderColor: '#c62828',
        pointBorderWidth: 2,
        pointHoverRadius: 4
      }]
    },
    options: {
      ...base,
      plugins: {
        ...base.plugins,
        tooltip: {
          ...base.plugins.tooltip,
          callbacks: { label: (ctx) => `Rp ${money.format(ctx.parsed.y)}` }
        }
      },
      scales: {
        ...base.scales,
        y: {
          ...base.scales.y,
          ticks: { ...base.scales.y.ticks, callback: (value) => compact.format(value) }
        }
      }
    }
  });
}
</script>
<?php ui_footer(); ?>
