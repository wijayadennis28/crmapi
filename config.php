<?php
// EspoCRM API Version
define('ESPO_API_VERSION', 'v1');

// Multiple EspoCRM instances — add/edit as needed
define('INSTANCES', [
    'site1' => [
        'name'    => 'TOP',
        'url'     => 'https://erp.trinitioptimapackindo.com',
        'api_key' => 'ff283f586ea4603f7bbb19551f3dd0c0',
    ],
    'site2' => [
        'name'    => 'MMP/SLP',
        'url'     => 'https://erp-nonppn.trinitioptimapackindo.com',
        'api_key' => '03d3ae59d8df23d1a5df217cf22404c2',
    ],
    'combined' => [
        'name'     => 'ALL (TOP + MMP/SLP)',
        'combined' => ['site1', 'site2'],   // list the site keys to merge
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

// Date field used for the WHERE filter (dashboard grouping), per module.
// Modules not listed here fall back to '_default'.
define('DATE_FILTER_FIELDS', [
    'SalesOrder'    => 'dateOrdered',
    'PurchaseOrder' => 'dateOrdered',
    'CPettyCash'    => 'cashDate',
    '_default'      => 'createdAt',
]);

// Date field used for ORDER BY in API requests, per module.
// Modules not listed here fall back to '_default'.
define('DATE_ORDER_FIELDS', [
    'SalesOrder'    => 'dateOrdered',
    'PurchaseOrder' => 'dateOrdered',
    'CPettyCash'    => 'cashDate',
    '_default'      => 'createdAt',
]);

// Max records per API request
define('PAGE_SIZE', 200);
