<?php
/**
 * Minimal outbound HTTP POST with transport fallback.
 *
 * The original contact form called curl_init() directly. When the cURL
 * extension is not available to PHP — a one-click mistake in cPanel's
 * "Select PHP Version", and the cause of the outage this replaces — that line
 * raises "Call to undefined function", which was uncaught and killed the whole
 * request before any mail was attempted.
 *
 * Two lessons are baked in here:
 *   1. Probe before calling. Never assume an extension is present.
 *   2. Degrade, don't die. If cURL is gone, the stream wrapper usually still
 *      works; if both are gone we return a clean failure the caller can
 *      reason about, instead of a fatal.
 *
 * Returns:
 *   ok        bool    transport completed and a response was read
 *   status    int     HTTP status (0 when the request never completed)
 *   body      string  raw response body
 *   error     string  transport-level error, '' on success
 *   transport string  'curl' | 'stream' | 'none'
 */

declare(strict_types=1);

if (defined('SHIVELY_CONTACT_HTTP')) {
    return;
}
define('SHIVELY_CONTACT_HTTP', true);

/** Which transports this PHP install can actually use, best first. */
function contact_http_transports(): array
{
    $available = [];

    // cURL needs the function AND its constants — if the extension is missing,
    // referencing CURLOPT_* is itself a fatal in PHP 8.
    if (contact_function_available('curl_init')
        && contact_function_available('curl_exec')
        && defined('CURLOPT_RETURNTRANSFER')) {
        $available[] = 'curl';
    }

    // Stream fallback needs allow_url_fopen plus a registered https wrapper
    // (which in practice means the openssl extension is loaded).
    if (contact_function_available('file_get_contents')
        && filter_var((string)ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN)
        && in_array('https', stream_get_wrappers(), true)) {
        $available[] = 'stream';
    }

    return $available;
}

function contact_http_post(string $url, array $fields, int $timeout = 8): array
{
    $payload    = http_build_query($fields);
    $transports = contact_http_transports();

    if ($transports === []) {
        contact_log('no outbound HTTP transport available (cURL missing/disabled and allow_url_fopen off)');
        return [
            'ok'        => false,
            'status'    => 0,
            'body'      => '',
            'error'     => 'no HTTP transport available (enable the curl extension or allow_url_fopen)',
            'transport' => 'none',
        ];
    }

    $lastError = '';
    foreach ($transports as $transport) {
        try {
            $result = $transport === 'curl'
                ? contact_http_post_curl($url, $payload, $timeout)
                : contact_http_post_stream($url, $payload, $timeout);
        } catch (Throwable $e) {
            // A transport blowing up must never escape as a fatal — that is the
            // entire failure mode being fixed. Log it and try the next one.
            $result = ['ok' => false, 'status' => 0, 'body' => '', 'error' => get_class($e) . ': ' . $e->getMessage()];
        }

        $result['transport'] = $transport;
        if ($result['ok']) {
            return $result;
        }

        $lastError = $result['error'];
        contact_log(sprintf('HTTP transport "%s" failed: %s', $transport, $lastError));
    }

    return [
        'ok'        => false,
        'status'    => 0,
        'body'      => '',
        'error'     => $lastError,
        'transport' => end($transports) ?: 'none',
    ];
}

function contact_http_post_curl(string $url, string $payload, int $timeout): array
{
    $ch = curl_init($url);
    if ($ch === false) {
        return ['ok' => false, 'status' => 0, 'body' => '', 'error' => 'curl_init failed'];
    }

    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => min(5, $timeout),
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded', 'Expect:'],
    ]);

    $body   = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error  = (string)curl_error($ch);
    curl_close($ch);

    if ($body === false) {
        return ['ok' => false, 'status' => $status, 'body' => '', 'error' => $error ?: 'curl_exec returned false'];
    }
    if ($status !== 200) {
        return ['ok' => false, 'status' => $status, 'body' => (string)$body, 'error' => 'unexpected HTTP status ' . $status];
    }

    return ['ok' => true, 'status' => $status, 'body' => (string)$body, 'error' => ''];
}

function contact_http_post_stream(string $url, string $payload, int $timeout): array
{
    $context = stream_context_create([
        'http' => [
            'method'        => 'POST',
            'header'        => "Content-Type: application/x-www-form-urlencoded\r\n"
                             . 'Content-Length: ' . strlen($payload) . "\r\n",
            'content'       => $payload,
            'timeout'       => $timeout,
            'ignore_errors' => true, // read the body even on 4xx/5xx
        ],
        'ssl' => [
            'verify_peer'       => true,
            'verify_peer_name'  => true,
            'allow_self_signed' => false,
        ],
    ]);

    $body = @file_get_contents($url, false, $context);
    if ($body === false) {
        return ['ok' => false, 'status' => 0, 'body' => '', 'error' => 'file_get_contents failed'];
    }

    // $http_response_header is injected into local scope by the stream wrapper.
    $status = 0;
    if (isset($http_response_header[0]) && preg_match('#\s(\d{3})\s#', $http_response_header[0], $m)) {
        $status = (int)$m[1];
    }
    if ($status !== 200) {
        return ['ok' => false, 'status' => $status, 'body' => $body, 'error' => 'unexpected HTTP status ' . $status];
    }

    return ['ok' => true, 'status' => $status, 'body' => $body, 'error' => ''];
}
