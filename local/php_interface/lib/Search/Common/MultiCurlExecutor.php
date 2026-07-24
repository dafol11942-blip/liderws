<?php
namespace Lider\Search\Common;

class MultiCurlExecutor
{
    private float $globalDeadline;
    private float $startTime;
    private array $results = [];
    private int $totalRequests = 0;
    private int $completedRequests = 0;

    /**
     * @param array $requests [['url','headers','method','body','_timeout','_key','_priority'], ...]
     * @param float $deadlineSeconds
     * @return array
     */
    public function executeAll(array $requests, float $deadlineSeconds = 8.0): array
    {
        $this->startTime = microtime(true);
        $this->globalDeadline = $deadlineSeconds;
        $this->results = [];
        $this->totalRequests = count($requests);
        $this->completedRequests = 0;

        if (empty($requests)) return [];

        // Сортируем: сначала по _priority (0 = важнее), потом по _timeout
        usort($requests, function ($a, $b) {
            $pa = $a['_priority'] ?? 5;
            $pb = $b['_priority'] ?? 5;
            if ($pa !== $pb) return $pa <=> $pb;
            return ($a['_timeout'] ?? 6) <=> ($b['_timeout'] ?? 6);
        });

        $this->executeChunk($requests, $this->globalDeadline);
        
        return $this->results;
    }

    private function executeChunk(array $requests, float $chunkDeadline): void
    {
        $mh = curl_multi_init();
        $handles = [];

        foreach ($requests as $idx => $req) {
            $timeout = (int)min((int)($req['_timeout'] ?? 6), max(1, (int)ceil($chunkDeadline)));

            $ch = curl_init($req['url']);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => $req['headers'] ?? [],
                CURLOPT_TIMEOUT        => $timeout,
                CURLOPT_CONNECTTIMEOUT => min(3, $timeout),
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_ENCODING       => '',
            ]);

            if (($req['method'] ?? 'GET') === 'POST') {
                curl_setopt($ch, CURLOPT_POST, true);
                if (!empty($req['body'])) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $req['body']);
                }
            }

            curl_multi_add_handle($mh, $ch);
            $handles[(string)$idx] = ['ch' => $ch, 'key' => (string)($req['_key'] ?? '')];
        }

        $active = null;
        do {
            $status = curl_multi_exec($mh, $active);
            if ($status !== CURLM_OK) break;

            $elapsed = microtime(true) - $this->startTime;
            if ($elapsed >= $this->globalDeadline) break;

            $selectTimeout = min(0.15, max(0.01, $this->globalDeadline - $elapsed));
            curl_multi_select($mh, $selectTimeout);
        } while ($active > 0);

        foreach ($handles as $info) {
            $ch = $info['ch'];
            $body = curl_multi_getcontent($ch);
            $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $errMsg = curl_error($ch);
            $key = $info['key'];

            $this->results[$key] = [
                'body'  => ($httpCode === 200 && is_string($body) && $body !== '') ? $body : null,
                'http'  => $httpCode,
                'error' => $errMsg ?: ($httpCode === 200 ? '' : "HTTP {$httpCode}"),
            ];

            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
            $this->completedRequests++;
        }

        curl_multi_close($mh);
    }
}
