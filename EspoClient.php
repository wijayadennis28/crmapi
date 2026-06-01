<?php
require_once __DIR__ . '/config.php';

class EspoClient
{
    private string  $siteKey;
    private bool    $isCombined = false;
    private array   $combinedClients = []; // EspoClient[] for each child site
    private string  $baseUrl = '';
    private string  $apiKey  = '';

    /**
     * @param string $siteKey  Key from INSTANCES array e.g. 'site1', or 'combined'
     */
    public function __construct(string $siteKey)
    {
        $instances = INSTANCES;
        if (!isset($instances[$siteKey])) {
            throw new InvalidArgumentException("Unknown site key: $siteKey");
        }
        $cfg = $instances[$siteKey];
        $this->siteKey = $siteKey;

        if (isset($cfg['combined'])) {
            // Virtual combined instance — build a client for each child site
            $this->isCombined = true;
            foreach ($cfg['combined'] as $childKey) {
                $this->combinedClients[] = new EspoClient($childKey);
            }
        } else {
            $this->baseUrl = rtrim($cfg['url'], '/') . '/api/' . ESPO_API_VERSION;
            $this->apiKey  = $cfg['api_key'];
        }
    }

    /**
     * Fetch all records for a module within a date range, handling pagination.
     *
     * @param string $module   EspoCRM module name e.g. 'SalesOrder'
     * @param string $dateFrom Y-m-d
     * @param string $dateTo   Y-m-d
     * @return array
     */
    public function fetchMonthly(string $module, string $dateFrom, string $dateTo): array
    {
        // Combined mode: merge records from all child sites
        if ($this->isCombined) {
            $merged = [];
            foreach ($this->combinedClients as $child) {
                $merged = array_merge($merged, $child->fetchMonthly($module, $dateFrom, $dateTo));
            }
            return $merged;
        }

        $records = [];
        $offset  = 0;

        do {
            $params = http_build_query([
                'maxSize'                   => PAGE_SIZE,
                'offset'                    => $offset,
                'where[0][type]'            => 'between',
                'where[0][attribute]'       => DATE_FIELD,
                'where[0][value][]'         => $dateFrom . ' 00:00:00',
                'where[1][value][]'         => $dateTo   . ' 23:59:59',
                'orderBy'                   => DATE_FIELD,
                'order'                     => 'asc',
            ]);

            // EspoCRM between filter needs both values in the same key array
            $params = 'maxSize=' . PAGE_SIZE
                . '&offset=' . $offset
                . '&where[0][type]=between'
                . '&where[0][attribute]=' . urlencode(DATE_FIELD)
                . '&where[0][value][]=' . urlencode($dateFrom . ' 00:00:00')
                . '&where[0][value][]=' . urlencode($dateTo   . ' 23:59:59')
                . '&orderBy=' . urlencode(DATE_FIELD)
                . '&order=asc';

            $url  = "{$this->baseUrl}/{$module}?{$params}";
            $data = $this->get($url);

            if (empty($data['list'])) {
                break;
            }

            $records = array_merge($records, $data['list']);
            $offset += PAGE_SIZE;

        } while (count($records) < ($data['total'] ?? 0));

        return $records;
    }

    /**
     * Return monthly summary: count and total amount per month for a module.
     *
     * @param string $module
     * @param int    $year
     * @return array  ['01' => ['count'=>5,'total'=>1200.00], ...]
     */
    public function monthlySummary(string $module, int $year): array
    {
        // Combined mode: sum monthly summaries from all child sites
        if ($this->isCombined) {
            $merged = [];
            foreach ($this->combinedClients as $child) {
                foreach ($child->monthlySummary($module, $year) as $mk => $data) {
                    $merged[$mk]['count'] = ($merged[$mk]['count'] ?? 0) + $data['count'];
                    $merged[$mk]['total'] = ($merged[$mk]['total'] ?? 0) + $data['total'];
                }
            }
            ksort($merged);
            return $merged;
        }

        $summary = [];

        for ($m = 1; $m <= 12; $m++) {
            $monthKey = str_pad($m, 2, '0', STR_PAD_LEFT);
            $dateFrom = "$year-$monthKey-01";
            $dateTo   = date('Y-m-t', strtotime($dateFrom));

            $cacheKey = "{$this->siteKey}_{$module}_{$year}_{$monthKey}";
            $cached   = $this->getCache($cacheKey);

            if ($cached !== null) {
                $summary[$monthKey] = $cached;
                continue;
            }

            $records = $this->fetchMonthly($module, $dateFrom, $dateTo);

            $total = 0;
            foreach ($records as $r) {
                $total += (float)($r['grandTotalAmount'] ?? $r['amount'] ?? $r['totalAmount'] ?? $r['cashAmount'] ?? 0);
            }

            $data = ['count' => count($records), 'total' => $total];
            $this->setCache($cacheKey, $data);
            $summary[$monthKey] = $data;
        }

        return $summary;
    }

    /**
     * Raw GET request to EspoCRM API.
     */
    public function get(string $url): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTPHEADER     => [
                'X-Api-Key: ' . $this->apiKey,
                'Accept: application/json',
            ],
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($err) {
            throw new RuntimeException("cURL error: $err");
        }
        if ($code !== 200) {
            // Include EspoCRM's own error message if available
            $decoded = json_decode($body, true);
            $detail  = $decoded['message'] ?? $decoded['error'] ?? trim(strip_tags($body));
            $detail  = $detail ? " — $detail" : '';
            throw new RuntimeException("HTTP $code$detail (URL: $url)");
        }

        return json_decode($body, true) ?? [];
    }

    // ── Cache helpers ────────────────────────────────────────────────────────

    private function getCachePath(string $key): string
    {
        if (!is_dir(CACHE_DIR)) {
            mkdir(CACHE_DIR, 0750, true);
        }
        return CACHE_DIR . '/' . preg_replace('/[^a-z0-9_]/i', '_', $key) . '.json';
    }

    private function getCache(string $key): ?array
    {
        if (CACHE_TTL === 0) return null;
        $path = $this->getCachePath($key);
        if (!file_exists($path)) return null;
        if (time() - filemtime($path) > CACHE_TTL) return null;
        $data = json_decode(file_get_contents($path), true);
        return is_array($data) ? $data : null;
    }

    private function setCache(string $key, array $data): void
    {
        if (CACHE_TTL === 0) return;
        file_put_contents($this->getCachePath($key), json_encode($data));
    }
}
