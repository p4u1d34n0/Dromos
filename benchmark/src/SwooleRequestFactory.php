<?php

namespace Benchmark;

use Dromos\Http\Request;

/**
 * Converts an OpenSwoole HTTP request into a Dromos Request
 *
 * Maps Swoole's lowercase server/header arrays to the uppercase $_SERVER-style
 * keys that Dromos and PSR-7 consumers expect, and handles JSON body parsing.
 */
class SwooleRequestFactory
{
    public static function fromSwoole(\OpenSwoole\HTTP\Request $swooleRequest): Request
    {
        $server = $swooleRequest->server ?? [];
        $headers = $swooleRequest->header ?? [];

        $method = strtoupper($server['request_method'] ?? 'GET');
        $uri = $server['request_uri'] ?? '/';
        if (!empty($server['query_string'])) {
            $uri .= '?' . $server['query_string'];
        }

        // Build server params (uppercase keys like $_SERVER)
        $serverParams = [];
        foreach ($server as $key => $value) {
            $serverParams[strtoupper($key)] = $value;
        }
        $serverParams['REMOTE_ADDR'] = $server['remote_addr'] ?? '127.0.0.1';

        // Normalise header keys for Request::create() and populate HTTP_* server params
        $headerMap = [];
        foreach ($headers as $key => $value) {
            $headerMap[str_replace('-', '_', ucwords($key, '-'))] = $value;
            $serverParams['HTTP_' . strtoupper(str_replace('-', '_', $key))] = $value;
        }
        if (isset($headers['content-type'])) {
            $serverParams['CONTENT_TYPE'] = $headers['content-type'];
        }

        $queryParams = $swooleRequest->get ?? [];
        $rawBody = $swooleRequest->rawContent() ?: '';

        // Parse body based on content type
        $parsedBody = $swooleRequest->post ?? [];
        $contentType = $headers['content-type'] ?? '';
        if (str_contains($contentType, 'application/json') && $rawBody !== '') {
            $decoded = json_decode($rawBody, true, 64);
            if (json_last_error() === JSON_ERROR_NONE) {
                $parsedBody = $decoded;
            }
        }

        return Request::create(
            method: $method,
            uri: $uri,
            serverParams: $serverParams,
            headers: $headerMap,
            queryParams: $queryParams,
            parsedBody: $parsedBody,
            body: $rawBody
        );
    }
}
