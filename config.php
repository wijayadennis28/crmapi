<?php
// EspoCRM API Version
define('ESPO_API_VERSION', 'v1');

// Multiple EspoCRM instances — add/edit as needed
define('INSTANCES', [
    'site1' => [
        'name'    => 'Site 1',                    // Display name
        'url'     => 'https://erp.trinitioptimapackindo.com',  // Base URL (no trailing slash)
        'api_key' => 'ff283f586ea4603f7bbb19551f3dd0c0',
    ],
    'site2' => [
        'name'    => 'Site 2',
        'url'     => 'https://erp-nonppn.trinitioptimapackindo.com',
        'api_key' => '03d3ae59d8df23d1a5df217cf22404c2',
    ],
]);

// Cache settings (seconds). Set to 0 to disable.
define('CACHE_TTL', 300); // 5 minutes
define('CACHE_DIR', __DIR__ . '/cache');

// Modules to display on dashboard
define('MODULES', [
    'SalesOrder'    => 'Sales Orders',
    'PurchaseOrder' => 'Purchase Orders',
    'Quote'         => 'Quotes',
    'ReceiptOrder'  => 'Receipt Orders',
    'ReturnOrder'   => 'Return Orders',
    'TransferOrder' => 'Transfer Orders',
    'CPayroll'      => 'Payroll',
    'CPettyCash'    => 'Petty Cash',
]);

// Date field used for monthly filtering per module
define('DATE_FIELD', 'createdAt');

// Max records per API request
define('PAGE_SIZE', 200);
