<?php
/**
 * Contact-form environment diagnostic.
 *
 * Debugging the outage that prompted this file meant guessing at the server's
 * PHP configuration from the outside, because a fatal error returns a zero-byte
 * body and says nothing. This endpoint replaces that guesswork: one
 * authenticated request reports exactly which extension, credential or
 * permission is missing.
 *
 *   https://grupocorasa.mx/shivelybros-gc/contact-diag.php?token=<DIAG_TOKEN>
 *
 * Add &send=1 to also deliver a real test email to MAIL_TO.
 *
 * Access control: DIAG_TOKEN lives in the gitignored config.php and is compared
 * with hash_equals(). Anything else — absent token, wrong token, unset token —
 * returns a plain 404, so the endpoint is indistinguishable from a file that
 * isn't there. It reports capabilities and credential *validity*, never the
 * credentials themselves.
 *
 * Safe to leave installed. Delete it once the form is confirmed healthy if you
 * would rather not have it on disk at all.
 */

declare(strict_types=1);

require __DIR__ . '/contact/lib/bootstrap.php';
require __DIR__ . '/contact/lib/http.php';
require __DIR__ . '/contact/lib/storage.php';
require __DIR__ . '/contact/lib/recipients.php';

contact_tag('diag');

$configPath = __DIR__ . '/contact/config.php';
$cfg        = is_readable($configPath) ? require $configPath : null;

// ── Authentication ──────────────────────────────────────────────────────────
$expected  = is_array($cfg) ? (string)($cfg['DIAG_TOKEN'] ?? '') : '';
$presented = (string)($_GET['token'] ?? '');

if ($expected === '' || strncmp($expected, 'TODO_', 5) === 0 || !hash_equals($expected, $presented)) {
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    contact_response_sent(true);
    echo "<!doctype html><title>404 Not Found</title><h1>Not Found</h1>";
    exit;
}

// ── Report ──────────────────────────────────────────────────────────────────
$report = [
    'generated_at' => (new DateTimeImmutable('now'))->format(DATE_ATOM),
    'verdict'      => [],
];
$problems = [];

// PHP runtime
$report['php'] = [
    'version'           => PHP_VERSION,
    'sapi'              => PHP_SAPI,
    'display_errors'    => (string)ini_get('display_errors'),
    'error_log'         => (string)ini_get('error_log'),
    'allow_url_fopen'   => (bool)filter_var((string)ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN),
    'disable_functions' => (string)ini_get('disable_functions'),
    'max_execution_time'=> (string)ini_get('max_execution_time'),
];

// Extensions the form depends on
$extensions = [];
foreach (['curl', 'openssl', 'mbstring', 'json', 'session', 'filter'] as $extension) {
    $extensions[$extension] = extension_loaded($extension);
}
$report['extensions'] = $extensions;

if (!$extensions['curl']) {
    $problems[] = 'The cURL extension is NOT loaded. This is the failure that took the forms down: '
                . 'the old code called curl_init() unconditionally and died. Enable "curl" in cPanel → '
                . 'Select PHP Version → Extensions. The form now falls back to a stream transport, '
                . 'but cURL is the more reliable path.';
}
if (!$extensions['openssl']) {
    $problems[] = 'The openssl extension is NOT loaded — neither HTTPS calls to Google nor SMTP over '
                . 'TLS can work. Enable "openssl" in cPanel → Select PHP Version → Extensions.';
}
if (!$extensions['mbstring']) {
    $problems[] = 'The mbstring extension is NOT loaded; field length validation will fail.';
}

// Individual functions (a host can disable one without unloading the extension)
$functions = [];
foreach (['curl_init', 'curl_exec', 'file_get_contents', 'fsockopen', 'mail', 'random_bytes'] as $function) {
    $functions[$function] = contact_function_available($function);
}
$report['functions']       = $functions;
$report['http_transports'] = contact_http_transports();
$report['stream_wrappers'] = stream_get_wrappers();

if ($report['http_transports'] === []) {
    $problems[] = 'No outbound HTTP transport is available at all: cURL is missing/disabled AND '
                . 'allow_url_fopen is off. reCAPTCHA cannot be verified, so every submission will be '
                . 'delivered with an "unverified" warning banner until this is fixed.';
}

// Configuration shape — presence and validity only, never secret values
$configReport = ['file_readable' => is_array($cfg)];
if (is_array($cfg)) {
    foreach (['SMTP_HOST', 'SMTP_PORT', 'SMTP_SECURE', 'SMTP_USER', 'SMTP_FROM', 'SMTP_FROM_NAME'] as $key) {
        $configReport[$key] = (string)($cfg[$key] ?? '');
    }
    $configReport['SMTP_PASS']            = ($cfg['SMTP_PASS'] ?? '') !== ''
        ? 'set (' . strlen((string)$cfg['SMTP_PASS']) . ' chars)' : 'MISSING';
    $configReport['RECAPTCHA_SECRET_KEY'] = ($cfg['RECAPTCHA_SECRET_KEY'] ?? '') !== ''
        ? 'set (' . strlen((string)$cfg['RECAPTCHA_SECRET_KEY']) . ' chars)' : 'MISSING';
    $configReport['RECAPTCHA_ACTION']     = (string)($cfg['RECAPTCHA_ACTION'] ?? 'contact_submit');
    $configReport['RECAPTCHA_MIN_SCORE']  = (float)($cfg['RECAPTCHA_MIN_SCORE'] ?? 0.5);
    $configReport['MAIL_TO']              = normalizeRecipients($cfg['MAIL_TO'] ?? []);
    $configReport['MAIL_CC']              = normalizeRecipients($cfg['MAIL_CC'] ?? [], $configReport['MAIL_TO']);

    if ($configReport['MAIL_TO'] === []) {
        $problems[] = 'MAIL_TO resolves to zero valid addresses — nothing could be delivered.';
    }

    // SMTP_SECURE and SMTP_PORT must agree or the connection hangs or is refused.
    $port   = (int)($cfg['SMTP_PORT'] ?? 0);
    $secure = strtolower((string)($cfg['SMTP_SECURE'] ?? ''));
    if (($port === 465 && $secure !== 'ssl') || ($port === 587 && $secure !== 'tls')) {
        $problems[] = sprintf(
            'SMTP_PORT (%d) and SMTP_SECURE ("%s") disagree. Use 465 with "ssl", or 587 with "tls".',
            $port,
            $secure
        );
    }
}
$report['config'] = $configReport;

// Writable storage for the lead journal and rate limiter
$leadDir = is_array($cfg) ? contact_storage_dir($cfg, 'leads') : null;
$rateDir = is_array($cfg) ? contact_storage_dir($cfg, 'ratelimit') : null;
$report['storage'] = [
    'leads_dir'     => $leadDir ?? 'UNAVAILABLE',
    'ratelimit_dir' => $rateDir ?? 'UNAVAILABLE',
];
if ($leadDir === null) {
    $problems[] = 'Lead journal storage is not writable — submissions will still be emailed, but '
                . 'no on-disk backup will exist if delivery fails.';
}

// Live outbound test: can this host actually reach Google?
$verify = contact_http_post('https://www.google.com/recaptcha/api/siteverify', [
    'secret'   => (string)($cfg['RECAPTCHA_SECRET_KEY'] ?? ''),
    'response' => 'diagnostic-probe-not-a-real-token',
], 10);
$verifyData = json_decode($verify['body'], true);
$report['recaptcha_reachability'] = [
    'reachable'   => $verify['ok'],
    'transport'   => $verify['transport'],
    'http_status' => $verify['status'],
    'error'       => $verify['error'],
    'error_codes' => is_array($verifyData) && isset($verifyData['error-codes']) ? $verifyData['error-codes'] : null,
];
if (!$verify['ok']) {
    $problems[] = 'Could not reach Google reCAPTCHA from this server: ' . $verify['error']
                . '. Submissions will be delivered unverified until this is resolved.';
} elseif (is_array($verifyData) && in_array('invalid-input-secret', (array)($verifyData['error-codes'] ?? []), true)) {
    $problems[] = 'Google rejected RECAPTCHA_SECRET_KEY as invalid. Confirm the secret key matches the '
                . 'site key used in the pages (both must come from the same v3 registration).';
}

// Live SMTP test: connect and authenticate, without sending anything
$report['smtp'] = is_array($cfg) ? diag_smtp_check($cfg, $problems) : ['tested' => false];

// Optional: deliver a real test email
if (isset($_GET['send']) && $_GET['send'] === '1' && is_array($cfg)) {
    require_once __DIR__ . '/contact/lib/template.php';
    require_once __DIR__ . '/contact/lib/mailer.php';

    $fields = [
        'nombre'        => 'Prueba de diagnóstico',
        'empresa'       => 'Grupo Corasa (prueba interna)',
        'cargo'         => 'Sistema',
        'email'         => (string)$cfg['SMTP_FROM'],
        'telefono'      => '',
        'servicio'      => 'Otro',
        'requerimiento' => "Correo de prueba generado por contact-diag.php.\nSi lo recibió, la entrega de correo funciona correctamente.",
    ];
    $meta = [
        'origin_label' => 'Diagnóstico',
        'source'       => '/shivelybros-gc/contact-diag.php',
        'notices'      => ['Este es un correo de PRUEBA enviado manualmente desde el diagnóstico.'],
        'recaptcha'    => ['verdict' => 'n/a', 'score' => null],
        'timestamp'    => contact_now($cfg)->format('d/m/Y H:i:s (T)'),
        'ip'           => contact_client_ip(),
    ];

    $sent = contact_send_mail(
        $cfg,
        normalizeRecipients($cfg['MAIL_TO'] ?? []),
        [],
        [
            'subject'     => '[Prueba] Diagnóstico del formulario de contacto',
            'html'        => contact_build_html_body($fields, $meta),
            'text'        => contact_build_text_body($fields, $meta),
            'reply_email' => (string)$cfg['SMTP_FROM'],
            'reply_name'  => 'Diagnóstico',
        ],
        __DIR__ . '/contact/lib'
    );
    $report['test_email'] = $sent;
    if (!$sent['ok']) {
        $problems[] = 'Test email could not be delivered: ' . $sent['error'];
    }
}

$report['verdict'] = [
    'healthy'  => $problems === [],
    'problems' => $problems,
];

contact_finish(200, $report);


/**
 * Open an SMTP session and authenticate, then hang up without sending.
 * Proves host, port, TLS mode and credentials in one shot.
 */
function diag_smtp_check(array $cfg, array &$problems): array
{
    require_once __DIR__ . '/contact/lib/PHPMailer/Exception.php';
    require_once __DIR__ . '/contact/lib/PHPMailer/PHPMailer.php';
    require_once __DIR__ . '/contact/lib/PHPMailer/SMTP.php';

    $result = ['tested' => true, 'connect' => false, 'tls' => null, 'authenticate' => false, 'error' => ''];

    try {
        $smtp = new PHPMailer\PHPMailer\SMTP();
        $smtp->do_debug = PHPMailer\PHPMailer\SMTP::DEBUG_OFF;
        $smtp->Timeout  = 10;

        $host   = (string)$cfg['SMTP_HOST'];
        $port   = (int)$cfg['SMTP_PORT'];
        $secure = strtolower((string)$cfg['SMTP_SECURE']);
        $target = $secure === 'ssl' ? 'ssl://' . $host : $host;

        if (!$smtp->connect($target, $port, 10)) {
            $result['error'] = 'connect failed: ' . diag_smtp_error($smtp);
            $problems[] = 'Cannot open an SMTP connection to ' . $host . ':' . $port . ' — ' . $result['error']
                        . '. Many shared hosts block outbound SMTP; if so, use localhost:25 or the '
                        . 'sendmail fallback.';
            return $result;
        }
        $result['connect'] = true;

        $smtp->hello('grupocorasa.mx');

        if ($secure === 'tls') {
            $result['tls'] = $smtp->startTLS();
            if (!$result['tls']) {
                $result['error'] = 'STARTTLS failed: ' . diag_smtp_error($smtp);
                $problems[] = 'STARTTLS failed on port ' . $port . ' — ' . $result['error'];
                $smtp->quit();
                return $result;
            }
            $smtp->hello('grupocorasa.mx');
        }

        $result['authenticate'] = $smtp->authenticate((string)$cfg['SMTP_USER'], (string)$cfg['SMTP_PASS']);
        if (!$result['authenticate']) {
            $result['error'] = 'authentication failed: ' . diag_smtp_error($smtp);
            $problems[] = 'SMTP credentials were REJECTED by the mail server. Confirm the mailbox '
                        . $cfg['SMTP_USER'] . ' exists in cPanel → Email Accounts and that SMTP_PASS '
                        . 'is current.';
        }

        $smtp->quit();
    } catch (Throwable $e) {
        $result['error'] = get_class($e) . ': ' . $e->getMessage();
        $problems[]      = 'SMTP check raised an exception: ' . $result['error'];
    }

    return $result;
}

function diag_smtp_error($smtp): string
{
    $error = $smtp->getError();
    if (!is_array($error)) {
        return 'unknown';
    }
    return trim(($error['error'] ?? '') . ' ' . ($error['detail'] ?? '') . ' ' . ($error['smtp_code'] ?? ''));
}
