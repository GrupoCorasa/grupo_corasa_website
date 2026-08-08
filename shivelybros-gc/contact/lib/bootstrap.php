<?php
/**
 * Request bootstrap shared by every Shively Bros contact endpoint.
 *
 * Its one job is a guarantee: this endpoint always answers with parseable
 * JSON, no matter what goes wrong. The outage this file exists to prevent was
 * an uncaught Error inside the reCAPTCHA step — PHP emitted a zero-byte 500,
 * the browser's res.json() threw on the empty body, and every visitor saw
 * "Respuesta inesperada del servidor." while nothing reached the inbox.
 *
 * Three nets, in order of how early they catch:
 *   1. Output buffering    — a stray warning or BOM can never corrupt the JSON.
 *   2. Exception handler   — catches Throwable, which since PHP 7 includes
 *                            Error (undefined function, TypeError, …).
 *   3. Shutdown handler    — catches what nothing else can: E_PARSE, memory
 *                            exhaustion, and "script ended without replying".
 *
 * Every response carries a machine-readable `code`. Support can act on
 * E_NO_HTTP_TRANSPORT without needing shell access to read a log.
 */

declare(strict_types=1);

if (defined('SHIVELY_CONTACT_BOOTSTRAP')) {
    return;
}
define('SHIVELY_CONTACT_BOOTSTRAP', true);

/** Shown to visitors whenever the real cause isn't theirs to fix. */
define('CONTACT_GENERIC_ERROR', 'No fue posible procesar su solicitud en este momento. Por favor intente de nuevo en unos minutos.');

// Never let PHP print diagnostics into the response body. If display_errors is
// On at the host level, a single notice would land inside the JSON and break
// the front-end exactly the way the original fatal did.
@ini_set('display_errors', '0');
@ini_set('log_errors', '1');
error_reporting(E_ALL);

// Everything the endpoint writes goes through this buffer, so contact_respond()
// can discard unintended output before emitting the real JSON.
ob_start();

/**
 * Per-endpoint log prefix ('home', 'abrasivos', …). Set once by the handler.
 */
function contact_tag(?string $set = null): string
{
    static $tag = 'contact';
    if ($set !== null) {
        $tag = $set;
    }
    return $tag;
}

function contact_log(string $message): void
{
    error_log('[shively-contact:' . contact_tag() . '] ' . $message);
}

/**
 * Tracks whether the response has already been emitted, so the shutdown net
 * stays silent on the normal path and a late fatal can't append a second body.
 */
function contact_response_sent(?bool $set = null): bool
{
    static $sent = false;
    if ($set !== null) {
        $sent = $set;
    }
    return $sent;
}

/**
 * Emit the JSON response. Safe to call from a shutdown handler: output is
 * still buffered at that point, so headers have not been flushed yet.
 */
function contact_respond(int $status, array $payload): void
{
    if (contact_response_sent()) {
        return;
    }
    contact_response_sent(true);

    // Drop anything already written — warnings, whitespace before <?php, etc.
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store');
    }

    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        // Encoding failed (invalid UTF-8 in an echoed field). Never fall back to
        // an empty body — that is the exact failure mode this file prevents.
        $json = '{"ok":false,"error":"' . CONTACT_GENERIC_ERROR . '","code":"E_JSON_ENCODE"}';
    }
    echo $json;
}

/** Respond and stop. */
function contact_finish(int $status, array $payload): void
{
    contact_respond($status, $payload);
    exit;
}

function contact_fail(int $status, string $code, ?string $message = null, array $extra = []): void
{
    contact_finish($status, array_merge([
        'ok'    => false,
        'error' => $message ?? CONTACT_GENERIC_ERROR,
        'code'  => $code,
    ], $extra));
}

/**
 * Is a function both defined and not blacklisted via disable_functions?
 * Shared hosts routinely disable curl_exec or mail while leaving the symbol
 * defined, so function_exists() alone is not a reliable probe.
 */
function contact_function_available(string $function): bool
{
    if (!function_exists($function)) {
        return false;
    }
    static $disabled = null;
    if ($disabled === null) {
        $raw      = (string)ini_get('disable_functions');
        $disabled = array_filter(array_map('trim', explode(',', $raw)));
    }
    return !in_array($function, $disabled, true);
}

/**
 * Client IP. Proxy headers are deliberately ignored: this site is served
 * directly by Apache with no CDN in front, so X-Forwarded-For would be
 * attacker-controlled and would let anyone trivially evade the rate limit.
 */
function contact_client_ip(): string
{
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
}

function contact_now(array $cfg): DateTimeImmutable
{
    $tz = (string)($cfg['TIMEZONE'] ?? 'America/Monterrey');
    try {
        return new DateTimeImmutable('now', new DateTimeZone($tz));
    } catch (Exception $e) {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }
}

// ── Net 2: uncaught Throwable (this is what caught the original outage) ──────
set_exception_handler(static function ($e) {
    contact_log(sprintf(
        'UNCAUGHT %s: %s @ %s:%d',
        get_class($e),
        $e->getMessage(),
        basename($e->getFile()),
        $e->getLine()
    ));
    contact_respond(500, ['ok' => false, 'error' => CONTACT_GENERIC_ERROR, 'code' => 'E_UNCAUGHT']);
});

// ── Warnings/notices: log them, never render them ───────────────────────────
set_error_handler(static function (int $severity, string $message, string $file = '', int $line = 0): bool {
    if (!(error_reporting() & $severity)) {
        return true; // suppressed with @ — stay quiet
    }
    contact_log(sprintf('PHP[%d] %s @ %s:%d', $severity, $message, basename($file), $line));
    return true;     // handled; keep PHP's own printer out of the response
});

// ── Net 3: fatals nothing else can catch, plus "never answered at all" ──────
register_shutdown_function(static function (): void {
    $fatal = error_get_last();
    $isFatal = $fatal !== null
        && in_array($fatal['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true);

    if ($isFatal) {
        contact_log(sprintf(
            'FATAL: %s @ %s:%d',
            $fatal['message'],
            basename((string)$fatal['file']),
            (int)$fatal['line']
        ));
        contact_respond(500, ['ok' => false, 'error' => CONTACT_GENERIC_ERROR, 'code' => 'E_FATAL']);
        return;
    }

    if (!contact_response_sent()) {
        contact_log('request ended without emitting a response');
        contact_respond(500, ['ok' => false, 'error' => CONTACT_GENERIC_ERROR, 'code' => 'E_NO_RESPONSE']);
    }
});
