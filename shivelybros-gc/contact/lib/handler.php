<?php
/**
 * The contact-form pipeline, shared by all six Shively Bros endpoints.
 *
 * Each endpoint is now a dozen lines of configuration around this function.
 * That consolidation is part of the fix, not a tidy-up: the outage affected all
 * six landing pages simultaneously because all six carried their own copy of
 * the same ~276 lines. One handler means the next fix lands everywhere at once.
 *
 * Pipeline, in order, with the reasoning for the order:
 *
 *   method gate      cheapest possible rejection
 *   config           fail fast and loudly on a broken deployment
 *   recipients       a bad recipient list must not surface as "send failed"
 *   honeypot         free bot rejection, before any real work
 *   rate limit       read-only check; sheds floods before outbound calls
 *   validation       no network round-trip for an obviously incomplete form
 *   reCAPTCHA        one outbound call, verdict-based (see recaptcha.php)
 *   journal          lead written to disk BEFORE the send can fail
 *   send             SMTP, retry-on-connect, sendmail fallback
 *   journal outcome  delivery result appended
 *
 * Every exit goes through contact_fail()/contact_finish(), so the response is
 * always JSON with a machine-readable code.
 */

declare(strict_types=1);

if (defined('SHIVELY_CONTACT_HANDLER')) {
    return;
}
define('SHIVELY_CONTACT_HANDLER', true);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/http.php';
require_once __DIR__ . '/recipients.php';
require_once __DIR__ . '/recaptcha.php';
require_once __DIR__ . '/storage.php';
require_once __DIR__ . '/leadlog.php';
require_once __DIR__ . '/ratelimit.php';
require_once __DIR__ . '/template.php';
require_once __DIR__ . '/mailer.php';

/** Values the `servicio` field is allowed to take, across every page. */
const CONTACT_ALLOWED_SERVICES = [
    // Legacy service lines (still selectable on the main landing page)
    'Gestión de Inventario',
    'Servicios de Procura',
    'Gestión de Regrind & Retep',
    // Product-line landing pages
    'Filtración Industrial',
    'Metrología',
    'MRO',
    'Herramientas de Corte',
    'Abrasivos',
    'Otro',
];

/**
 * @param array $options tag, form_source, subject_prefix, origin_label, base_dir
 */
function contact_handle(array $options): void
{
    contact_tag((string)($options['tag'] ?? 'contact'));

    $baseDir     = rtrim((string)$options['base_dir'], '/');
    $formSource  = (string)($options['form_source'] ?? '/shivelybros-gc/index.html');
    $originLabel = $options['origin_label'] ?? null;

    // ── Method gate ─────────────────────────────────────────────────────────
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        if (!headers_sent()) {
            header('Allow: POST');
        }
        contact_fail(405, 'E_METHOD', 'Método no permitido.');
    }

    // ── Config ──────────────────────────────────────────────────────────────
    $configPath = $baseDir . '/config.php';
    if (!is_readable($configPath)) {
        contact_log('config.php missing or unreadable at ' . $configPath);
        contact_fail(500, 'E_CONFIG_MISSING', 'Configuración del servidor incompleta.');
    }

    $cfg = require $configPath;
    if (!is_array($cfg)) {
        contact_log('config.php did not return an array');
        contact_fail(500, 'E_CONFIG_INVALID', 'Configuración del servidor incompleta.');
    }

    foreach (['SMTP_HOST', 'SMTP_PORT', 'SMTP_USER', 'SMTP_PASS', 'SMTP_FROM'] as $key) {
        if (($cfg[$key] ?? '') === '') {
            contact_log('config.php is missing required key: ' . $key);
            contact_fail(500, 'E_CONFIG_INCOMPLETE', 'Configuración del servidor incompleta.');
        }
    }

    // ── Recipients ──────────────────────────────────────────────────────────
    // Resolved before the reCAPTCHA round-trip so a typo in the recipient list
    // fails fast instead of masquerading as a downstream send failure.
    $mailTo = normalizeRecipients($cfg['MAIL_TO'] ?? []);
    $mailCc = normalizeRecipients($cfg['MAIL_CC'] ?? [], $mailTo);

    if ($mailTo === []) {
        contact_log('MAIL_TO resolved to zero valid recipients');
        contact_fail(500, 'E_NO_RECIPIENTS', 'Configuración del servidor incompleta.');
    }

    // ── Honeypot ────────────────────────────────────────────────────────────
    // Hidden via CSS, tabindex=-1, autocomplete=off — humans never fill it.
    // Answer with a fake success so the bot moves on instead of retrying.
    if (trim((string)($_POST['website'] ?? '')) !== '') {
        contact_log('honeypot triggered');
        contact_finish(200, ['ok' => true]);
    }

    // ── Rate limit (read-only; quota is spent after validation) ─────────────
    $limit = contact_ratelimit_check($cfg);
    if (!$limit['allowed']) {
        if (!headers_sent()) {
            header('Retry-After: ' . $limit['retry_after']);
        }
        contact_fail(
            429,
            'E_RATE_LIMITED',
            'Hemos recibido varias solicitudes desde su conexión. Por favor espere unos minutos '
            . 'e intente de nuevo, o escríbanos directamente por WhatsApp.'
        );
    }

    // ── Field extraction + validation ───────────────────────────────────────
    $fields = [
        'nombre'        => contact_input('nombre'),
        'empresa'       => contact_input('empresa'),
        'cargo'         => contact_input('cargo'),
        'email'         => contact_input('email'),
        'telefono'      => contact_input('telefono'),
        'servicio'      => contact_input('servicio'),
        'requerimiento' => contact_input('requerimiento'),
    ];
    $recaptchaToken = contact_input('g-recaptcha-response');

    $errors = contact_validate($fields, $recaptchaToken);
    if ($errors !== []) {
        contact_finish(422, [
            'ok'     => false,
            'error'  => 'Campos inválidos o incompletos.',
            'code'   => 'E_VALIDATION',
            'fields' => $errors,
        ]);
    }

    // ── reCAPTCHA ───────────────────────────────────────────────────────────
    $recaptcha = contact_verify_recaptcha($recaptchaToken, $cfg);
    $notices   = [];

    switch ($recaptcha['verdict']) {
        case 'pass':
            break;

        case 'expired':
            // Near-certainly a real person whose token aged out (v3 tokens live
            // 120 seconds). Ask for a retry instead of calling them a bot.
            contact_fail(
                403,
                'E_RECAPTCHA_EXPIRED',
                'Su verificación de seguridad expiró. Por favor envíe el formulario de nuevo.'
            );
            break;

        case 'unavailable':
            // Fail OPEN: deliver the lead, flagged, rather than lose it to an
            // outage between this host and Google.
            $notices[] = 'La verificación anti-bot no pudo completarse (' . $recaptcha['reason']
                       . '). Este mensaje NO fue verificado por reCAPTCHA — revíselo antes de responder.';
            contact_log('proceeding without reCAPTCHA verification: ' . $recaptcha['reason']);
            break;

        case 'bot':
        default:
            contact_fail(
                403,
                'E_RECAPTCHA_FAILED',
                'Verificación de seguridad fallida. Por favor recargue la página e intente de nuevo.'
            );
            break;
    }

    // Submission accepted — spend one unit of rate-limit quota.
    contact_ratelimit_record($cfg);

    // ── Journal the lead BEFORE attempting delivery ─────────────────────────
    $leadId = contact_lead_id();
    $meta   = [
        'origin_label' => $originLabel,
        'source'       => $formSource,
        'origin'       => (string)($options['origin_label'] ?? ''),
        'notices'      => $notices,
        'recaptcha'    => $recaptcha,
        'timestamp'    => contact_now($cfg)->format('d/m/Y H:i:s (T)'),
        'ip'           => contact_client_ip(),
    ];
    contact_lead_record($cfg, $leadId, $fields, $meta);

    // ── Send ────────────────────────────────────────────────────────────────
    $subjectPrefix = (string)($options['subject_prefix'] ?? '[Sitio web]');
    $result = contact_send_mail(
        $cfg,
        $mailTo,
        $mailCc,
        [
            'subject'     => contact_subject(sprintf('%s Nuevo contacto — %s — %s', $subjectPrefix, $fields['empresa'], $fields['servicio'])),
            'html'        => contact_build_html_body($fields, $meta),
            'text'        => contact_build_text_body($fields, $meta),
            'reply_email' => $fields['email'],
            'reply_name'  => $fields['nombre'],
        ],
        __DIR__
    );

    contact_lead_outcome($cfg, $leadId, $result['ok'], $result['transport'], $result['error']);

    if (!$result['ok']) {
        contact_fail(
            502,
            'E_SEND_FAILED',
            'No fue posible enviar el mensaje. Por favor intente más tarde o escríbanos directamente.'
        );
    }

    // ── Success ─────────────────────────────────────────────────────────────
    contact_mark_success($formSource);
    contact_finish(200, ['ok' => true]);
}

/** Trimmed POST value as a string, tolerant of arrays being posted. */
function contact_input(string $key): string
{
    $value = $_POST[$key] ?? '';
    if (is_array($value)) {
        return ''; // a scalar field arriving as an array is malformed input
    }
    $value = trim((string)$value);

    // A browser on a UTF-8 page always submits UTF-8, so anything else is a
    // scripted or misconfigured client. Substitute the invalid bytes instead of
    // rejecting the lead: one replacement character beats a lost enquiry, and
    // everything downstream — htmlspecialchars(), json_encode() — needs valid
    // UTF-8 to behave. htmlspecialchars() in particular returns an EMPTY string
    // on malformed input, which would silently blank the field in the email.
    if ($value !== '' && !mb_check_encoding($value, 'UTF-8')) {
        contact_log('substituted invalid UTF-8 in field: ' . $key);
        $value = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
    }

    return $value;
}

/**
 * Tidy a subject line built from visitor-supplied text.
 *
 * `empresa` is attacker-controlled and the subject travels far beyond this
 * inbox — ticketing systems, CRMs, chat integrations — some of which render
 * headers as HTML. PHPMailer already encodes the header safely against
 * injection; this just stops a pasted markup fragment from becoming someone
 * else's rendering problem, and keeps the line short enough that mail clients
 * don't truncate it mid-word.
 */
function contact_subject(string $subject): string
{
    $cleaned = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $subject);
    if (!is_string($cleaned)) {
        $cleaned = $subject; // pattern failed (unexpected encoding) — keep the original
    }
    $cleaned = str_replace(['<', '>'], '', $cleaned);

    $collapsed = preg_replace('/\s{2,}/u', ' ', $cleaned);
    if (is_string($collapsed)) {
        $cleaned = $collapsed;
    }

    return mb_substr(trim($cleaned), 0, 200);
}

/** @return string[] names of the fields that failed */
function contact_validate(array $fields, string $recaptchaToken): array
{
    $errors = [];

    foreach (['nombre', 'empresa', 'cargo'] as $key) {
        if ($fields[$key] === '' || mb_strlen($fields[$key]) > 100) {
            $errors[] = $key;
        }
    }

    if ($fields['email'] === ''
        || mb_strlen($fields['email']) > 150
        || !filter_var($fields['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'email';
    }

    if ($fields['telefono'] !== '' && mb_strlen($fields['telefono']) > 30) {
        $errors[] = 'telefono';
    }

    if ($fields['servicio'] === '' || !in_array($fields['servicio'], CONTACT_ALLOWED_SERVICES, true)) {
        $errors[] = 'servicio';
    }

    if ($fields['requerimiento'] === '' || mb_strlen($fields['requerimiento']) > 2000) {
        $errors[] = 'requerimiento';
    }

    if ($recaptchaToken === '') {
        $errors[] = 'recaptcha';
    }

    // Header-injection guard. PHPMailer already rejects these, but catching it
    // here keeps a crafted payload from reaching the mail layer at all.
    foreach (['nombre', 'empresa', 'cargo', 'email', 'telefono', 'servicio'] as $key) {
        if (preg_match('/[\r\n]/', $fields[$key]) && !in_array($key, $errors, true)) {
            $errors[] = $key;
        }
    }

    return $errors;
}

/**
 * Set the one-shot session flag that authorises the conversion event on
 * gracias.php. Deliberately the last thing that happens: starting the session
 * only on success means failed and bot submissions no longer mint a session
 * file or a Set-Cookie header.
 */
function contact_mark_success(string $formSource): void
{
    if (headers_sent()) {
        return;
    }

    session_cache_limiter('');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/shivelybros-gc/',
        'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                      || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    if (session_status() !== PHP_SESSION_ACTIVE && !session_start()) {
        contact_log('session_start failed; skipping conversion flag');
        return;
    }

    $_SESSION['contact_form_submitted'] = true;
    $_SESSION['contact_form_source']    = $formSource;
    session_write_close();
}
