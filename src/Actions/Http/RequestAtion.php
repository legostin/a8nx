<?php

namespace A8nx\Actions\Http;

use A8nx\Actions\ActionInterface;
use A8nx\Context\Context;

class RequestAtion implements ActionInterface
{

    public function execute(array $params, Context &$context, array $steps = []): array
    {
        // Validate required URL
        if (!isset($params['url'])) {
            throw new \InvalidArgumentException('URL parameter is required');
        }

        $url = $params['url'];
        // Support query parameters as an array
        if (isset($params['query']) && is_array($params['query'])) {
            $queryString = http_build_query($params['query']);
            if ($queryString !== '') {
                $url .= (strpos($url, '?') === false ? '?' : '&') . $queryString;
            }
        }

        $method = strtoupper($params['method'] ?? 'GET');
        $headers = $params['headers'] ?? [];
        $body = $params['body'] ?? null;
        // Optional timeout in seconds
        $timeout = $params['timeout'] ?? 0; // 0 means no timeout

        $curl = curl_init();
        if ($curl === false) {
            throw new \RuntimeException('Failed to initialize HTTP client');
        }

        $curlOptions = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HEADER => false,
        ];

        if ($body !== null) {
            if (is_array($body)) {
                $body = json_encode($body);
                $headers[] = 'Content-Type: application/json';
            }
            $curlOptions[CURLOPT_POSTFIELDS] = $body;
        }

        if ($method !== 'GET') {
            $curlOptions[CURLOPT_CUSTOMREQUEST] = $method;
        }

        if (!empty($headers)) {
            $curlOptions[CURLOPT_HTTPHEADER] = $headers;
        }

        if ($timeout > 0) {
            $curlOptions[CURLOPT_TIMEOUT] = (int)$timeout;
        }

        foreach ($curlOptions as $opt => $val) {
            curl_setopt($curl, $opt, $val);
        }

        $response = curl_exec($curl);
        $error = curl_error($curl);
        $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($response === false) {
            $context->getLogger()?->error('HTTP request failed', ['url' => $url, 'error' => $error]);
            if ($ctxOut = $context->getOutput()) {
                $ctxOut->writeln('<error>HTTP request failed: ' . $error . '</error>');
            }
            return [
                'success' => false,
                'code' => $code,
                'error' => $error,
                'message' => 'HTTP request failed' . ($error ? ': ' . $error : '')
            ];
        }

        $decoded = json_decode($response, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $result = [
                'success' => true,
                'code' => $code,
                'body' => $response,
                'decoded' => $decoded,
            ];
        } else {
            $result = [
                'success' => true,
                'code' => $code,
                'body' => $response,
            ];
        }

        // Attach user-friendly message for HTTP errors (non-2xx)
        if ($code >= 400) {
            $msg = 'HTTP error ' . $code;
            if (!empty($response)) {
                $msg .= ': ' . (strlen($response) > 512 ? substr($response, 0, 512) . '...' : $response);
            }
            $result['message'] = $msg;
            if ($ctxOut = $context->getOutput()) {
                $ctxOut->writeln('<error>' . $msg . '</error>');
                if (!empty($response)) {
                    $snippet = (strlen($response) > 512) ? substr($response, 0, 512) . '...' : $response;
                    $ctxOut->writeln('<error>' . $snippet . '</error>');
                }
            }
        }

        $context->getLogger()?->info('HTTP request completed', ['url' => $url, 'code' => $code]);
        return $result;
    }
}
