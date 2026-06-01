<?php
/**
 * export.php — Export module records to CSV or Excel (xlsx via simple XML)
 *
 * Usage:
 *   export.php?site=site1&module=SalesOrder&year=2026&month=05&format=csv
 *   export.php?site=site2&module=SalesOrder&year=2026&month=05&format=excel
 *   export.php?site=site1&module=SalesOrder&year=2026&month=all&format=csv
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/EspoClient.php';

// ── Input validation ────────────────────────────────────────────────────────
$allowedModules = array_keys(MODULES);
$allowedSites   = array_keys(INSTANCES);
$module   = $_GET['module'] ?? '';
$site     = $_GET['site']   ?? array_key_first(INSTANCES);
$year     = (int)($_GET['year']   ?? date('Y'));
$monthRaw = $_GET['month'] ?? 'all';
$format   = strtolower($_GET['format'] ?? 'csv');

if (!in_array($module, $allowedModules, true)) {
    http_response_code(400);
    exit('Invalid module.');
}
if (!in_array($site, $allowedSites, true)) {
    http_response_code(400);
    exit('Invalid site.');
}
if ($year < 2000 || $year > 2100) {
    http_response_code(400);
    exit('Invalid year.');
}
if (!in_array($format, ['csv', 'excel'], true)) {
    http_response_code(400);
    exit('Invalid format. Use csv or excel.');
}

// ── Fetch records ────────────────────────────────────────────────────────────
$client  = new EspoClient($site);
$records = [];

try {
    if ($monthRaw === 'all') {
        // Full year export
        $dateFrom = "$year-01-01";
        $dateTo   = "$year-12-31";
        $records  = $client->fetchMonthly($module, $dateFrom, $dateTo);
        $month    = 'all';
    } else {
        $month    = str_pad((int)$monthRaw, 2, '0', STR_PAD_LEFT);
        $dateFrom = "$year-$month-01";
        $dateTo   = date('Y-m-t', strtotime($dateFrom));
        $records  = $client->fetchMonthly($module, $dateFrom, $dateTo);
    }
} catch (RuntimeException $e) {
    http_response_code(502);
    exit('Failed to fetch data: ' . htmlspecialchars($e->getMessage()));
}

// ── Build column list from first record ──────────────────────────────────────
if (empty($records)) {
    http_response_code(204);
    exit("No records found for $module in $year-$month.");
}

$columns = array_keys($records[0]);

// ── Export ───────────────────────────────────────────────────────────────────
$siteName = preg_replace('/[^a-z0-9]/i', '_', INSTANCES[$site]['name']);
$filename = "{$siteName}_{$module}_{$year}_{$month}";

if ($format === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header("Content-Disposition: attachment; filename=\"{$filename}.csv\"");
    header('Cache-Control: no-cache, no-store, must-revalidate');

    $out = fopen('php://output', 'w');
    // BOM for Excel UTF-8 compatibility
    fputs($out, "\xEF\xBB\xBF");
    fputcsv($out, $columns);

    foreach ($records as $row) {
        $line = [];
        foreach ($columns as $col) {
            $val = $row[$col] ?? '';
            if (is_array($val)) {
                $val = json_encode($val);
            }
            $line[] = $val;
        }
        fputcsv($out, $line);
    }
    fclose($out);

} else {
    // Excel via SpreadsheetML (opens natively in Excel/LibreOffice, no library needed)
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header("Content-Disposition: attachment; filename=\"{$filename}.xls\"");
    header('Cache-Control: no-cache, no-store, must-revalidate');

    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
         xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">
  <Worksheet ss:Name="' . htmlspecialchars($module) . '">
    <Table>';

    // Header row
    echo '<Row>';
    foreach ($columns as $col) {
        echo '<Cell><Data ss:Type="String">' . htmlspecialchars($col) . '</Data></Cell>';
    }
    echo '</Row>';

    // Data rows
    foreach ($records as $row) {
        echo '<Row>';
        foreach ($columns as $col) {
            $val = $row[$col] ?? '';
            if (is_array($val)) {
                $val = json_encode($val);
            }
            $type = is_numeric($val) ? 'Number' : 'String';
            echo '<Cell><Data ss:Type="' . $type . '">' . htmlspecialchars((string)$val) . '</Data></Cell>';
        }
        echo '</Row>';
    }

    echo '  </Table>
  </Worksheet>
</Workbook>';
}
