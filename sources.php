<?php
require_once __DIR__ . '/store.php';
require_once __DIR__ . '/EspoClient.php';

// Defense in depth: .htaccess and router.php already block this file.
if (PHP_SAPI !== 'cli' && isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    http_response_code(404);
    exit;
}

/**
 * Map a CPettyCash row to a report entity.
 * site1 is always PT TOP; site2 uses MMP/SLP labels.
 */
function petty_cash_entity_for(string $site, string $name): ?string
{
    if ($site === 'site1') {
        return 'TOP';
    }

    $key = strtoupper(trim($name));
    return PETTYCASH_ENTITY_MAP[$key] ?? null;
}

/**
 * Fetch CPettyCash for a month and split it by report entity.
 *
 * @return array{
 *   values: array<string,float>,
 *   present: array<string,bool>,
 *   unmapped: array<string,array{count:int,total:float}>,
 *   errors: array<int,string>,
 *   records: int
 * }
 */
function petty_cash_values(string $period): array
{
    $out = [
        'values' => [],
        'present' => [],
        'unmapped' => [],
        'errors' => [],
        'records' => 0,
    ];
    foreach (ENTITIES as $entity => $label) {
        $out['values'][$entity] = 0.0;
        $out['present'][$entity] = false;
    }

    $from = $period . '-01';
    $to   = date('Y-m-t', strtotime($from));

    foreach (['site1', 'site2'] as $site) {
        try {
            $client = new EspoClient($site);
            $rows   = $client->fetchMonthly('CPettyCash', $from, $to);
        } catch (RuntimeException $e) {
            $out['errors'][] = INSTANCES[$site]['name'] . ': ' . $e->getMessage();
            continue;
        }

        foreach ($rows as $row) {
            $out['records']++;
            $amount = (float)($row['cashAmount'] ?? 0);
            $name   = trim((string)($row['name'] ?? ''));
            $entity = petty_cash_entity_for($site, $name);

            if ($entity === null) {
                $key = $name !== '' ? $name : '(blank)';
                if (!isset($out['unmapped'][$key])) {
                    $out['unmapped'][$key] = ['count' => 0, 'total' => 0.0];
                }
                $out['unmapped'][$key]['count']++;
                $out['unmapped'][$key]['total'] += $amount;
                continue;
            }

            $out['values'][$entity] += $amount;
            $out['present'][$entity] = true;
        }
    }

    return $out;
}

/**
 * Resolve petty cash per entity: manual override first, API second, zero last.
 */
function petty_cash_resolved(array $inputs, array $petty): array
{
    $out = [];
    foreach (ENTITIES as $entity => $label) {
        $manual = array_key_exists('cost_petty_cash', $inputs[$entity] ?? [])
            ? (float)$inputs[$entity]['cost_petty_cash']
            : null;
        $api = !empty($petty['present'][$entity])
            ? (float)($petty['values'][$entity] ?? 0)
            : null;

        $out[$entity] = $manual !== null ? $manual : ($api ?? 0.0);
    }
    return $out;
}

/**
 * Human-readable source for the petty-cash row.
 */
function petty_cash_source(array $inputs, array $petty): string
{
    foreach (ENTITIES as $entity => $label) {
        if (array_key_exists('cost_petty_cash', $inputs[$entity] ?? [])) {
            return 'Override';
        }
    }
    foreach (ENTITIES as $entity => $label) {
        if (!empty($petty['present'][$entity])) {
            return 'API';
        }
    }
    return 'Manual';
}

function petty_cash_unmapped_total(array $petty): float
{
    return array_sum(array_column($petty['unmapped'] ?? [], 'total'));
}

function petty_cash_unmapped_labels(array $petty): string
{
    return implode(', ', array_keys($petty['unmapped'] ?? []));
}
