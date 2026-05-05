<?php
/**
 * Shively Bros contact form endpoint.
 *
 * Pipeline: POST → honeypot → field validation → reCAPTCHA v3 verify
 *           → SMTP send via PHPMailer → JSON response.
 *
 * Failure mode is "fail closed": any unexpected error returns a generic
 * error to the client and logs detail to error_log().
 */

declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

// ── Method gate ─────────────────────────────────────────────────────────────
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['ok' => false, 'error' => 'Método no permitido.']);
    exit;
}

// ── Load config ─────────────────────────────────────────────────────────────
$configPath = __DIR__ . '/contact/config.php';
if (!is_readable($configPath)) {
    error_log('[shively-contact] config.php missing or unreadable at ' . $configPath);
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Configuración del servidor incompleta.']);
    exit;
}
$cfg = require $configPath;

// ── Honeypot ────────────────────────────────────────────────────────────────
// Bots fill every visible input. The "website" field is hidden via CSS and
// has tabindex=-1 + autocomplete=off — humans never touch it. If it's
// non-empty, return a fake success so the bot moves on without retrying.
if (!empty($_POST['website'] ?? '')) {
    echo json_encode(['ok' => true]);
    exit;
}

// ── Field extraction + validation ───────────────────────────────────────────
$nombre        = trim((string)($_POST['nombre']        ?? ''));
$empresa       = trim((string)($_POST['empresa']       ?? ''));
$cargo         = trim((string)($_POST['cargo']         ?? ''));
$email         = trim((string)($_POST['email']         ?? ''));
$telefono      = trim((string)($_POST['telefono']      ?? ''));
$servicio      = trim((string)($_POST['servicio']      ?? ''));
$requerimiento = trim((string)($_POST['requerimiento'] ?? ''));
$recaptchaTok  = trim((string)($_POST['g-recaptcha-response'] ?? ''));

$ALLOWED_SERVICES = [
    // Legacy services (still on live index.html)
    'Gestión de Inventario',
    'Servicios de Procura',
    'Gestión de Regrind & Retep',
    // New product lines (CHANGE-EXAMPLE.html demo)
    'Filtración Industrial',
    'Metrología',
    'MRO',
    'Herramientas de Corte',
    'Abrasivos',
    'Otro',
];

$errors = [];
if ($nombre        === '' || mb_strlen($nombre)        > 100)  $errors[] = 'nombre';
if ($empresa       === '' || mb_strlen($empresa)       > 100)  $errors[] = 'empresa';
if ($cargo         === '' || mb_strlen($cargo)         > 100)  $errors[] = 'cargo';
if ($email         === '' || mb_strlen($email)         > 150 || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'email';
if ($telefono      !== '' && mb_strlen($telefono)      > 30)   $errors[] = 'telefono';
if ($servicio      === '' || !in_array($servicio, $ALLOWED_SERVICES, true)) $errors[] = 'servicio';
if ($requerimiento === '' || mb_strlen($requerimiento) > 2000) $errors[] = 'requerimiento';
if ($recaptchaTok  === '') $errors[] = 'recaptcha';

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Campos inválidos o incompletos.', 'fields' => $errors]);
    exit;
}

// ── reCAPTCHA v3 verification ───────────────────────────────────────────────
if (!verifyRecaptcha($recaptchaTok, $cfg)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Verificación de seguridad fallida. Por favor recargue la página e intente de nuevo.']);
    exit;
}

// ── Send email via PHPMailer SMTP ───────────────────────────────────────────
require_once __DIR__ . '/contact/lib/PHPMailer/Exception.php';
require_once __DIR__ . '/contact/lib/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/contact/lib/PHPMailer/SMTP.php';

$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host       = $cfg['SMTP_HOST'];
    $mail->Port       = (int)$cfg['SMTP_PORT'];
    $mail->SMTPAuth   = true;
    $mail->Username   = $cfg['SMTP_USER'];
    $mail->Password   = $cfg['SMTP_PASS'];
    $mail->SMTPSecure = $cfg['SMTP_SECURE']; // 'tls' or 'ssl'
    $mail->CharSet    = 'UTF-8';
    $mail->Encoding   = 'base64';
    $mail->SMTPDebug  = (int)($cfg['SMTP_DEBUG'] ?? 0);
    if ($mail->SMTPDebug > 0) {
        $mail->Debugoutput = static function ($str) { error_log('[shively-contact][smtp] ' . $str); };
    }
    $mail->Timeout = 15;

    $mail->setFrom($cfg['SMTP_FROM'], $cfg['SMTP_FROM_NAME']);
    $mail->addAddress($cfg['MAIL_TO']);
    $mail->addReplyTo($email, $nombre);

    $mail->Subject = sprintf('[Sitio web] Nuevo contacto — %s — %s', $empresa, $servicio);
    $mail->isHTML(true);
    $mail->Body    = buildHtmlBody($nombre, $empresa, $cargo, $email, $telefono, $servicio, $requerimiento);
    $mail->AltBody = buildTextBody($nombre, $empresa, $cargo, $email, $telefono, $servicio, $requerimiento);

    $mail->send();
} catch (PHPMailerException $e) {
    error_log('[shively-contact] PHPMailer error: ' . $mail->ErrorInfo);
    http_response_code(502);
    echo json_encode(['ok' => false, 'error' => 'No fue posible enviar el mensaje. Por favor intente más tarde o escríbanos directamente.']);
    exit;
} catch (\Throwable $e) {
    error_log('[shively-contact] Unexpected error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Error inesperado del servidor.']);
    exit;
}

echo json_encode(['ok' => true]);
exit;


// ───────────────────────────────────────────────────────────────────────────
// Helpers
// ───────────────────────────────────────────────────────────────────────────

function verifyRecaptcha(string $token, array $cfg): bool
{
    $secret = $cfg['RECAPTCHA_SECRET_KEY'] ?? '';
    if ($secret === '' || strncmp($secret, 'TODO_', 5) === 0) {
        error_log('[shively-contact] reCAPTCHA secret key not configured');
        return false;
    }

    $payload = http_build_query([
        'secret'   => $secret,
        'response' => $token,
        'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '',
    ]);

    $ch = curl_init('https://www.google.com/recaptcha/api/siteverify');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 5,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $body     = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($body === false || $httpCode !== 200) {
        error_log('[shively-contact] reCAPTCHA HTTP failure: code=' . $httpCode . ' err=' . $curlErr);
        return false;
    }

    $data = json_decode($body, true);
    if (!is_array($data) || empty($data['success'])) {
        $codes = is_array($data['error-codes'] ?? null) ? implode(',', $data['error-codes']) : 'unknown';
        error_log('[shively-contact] reCAPTCHA rejected: ' . $codes);
        return false;
    }

    $expectedAction = $cfg['RECAPTCHA_ACTION'] ?? 'contact_submit';
    if (($data['action'] ?? '') !== $expectedAction) {
        error_log('[shively-contact] reCAPTCHA action mismatch: got=' . ($data['action'] ?? '(none)'));
        return false;
    }

    $minScore = (float)($cfg['RECAPTCHA_MIN_SCORE'] ?? 0.5);
    $score    = (float)($data['score'] ?? 0.0);
    if ($score < $minScore) {
        error_log(sprintf('[shively-contact] reCAPTCHA low score: %.2f < %.2f', $score, $minScore));
        return false;
    }

    return true;
}

function buildHtmlBody(string $nombre, string $empresa, string $cargo, string $email, string $telefono, string $servicio, string $requerimiento): string
{
    $rows = [
        ['Nombre',                $nombre],
        ['Empresa',               $empresa],
        ['Cargo',                 $cargo],
        ['Email',                 $email],
        ['Teléfono / WhatsApp',   $telefono !== '' ? $telefono : '(no proporcionado)'],
        ['Servicio de Interés',   $servicio],
    ];
    $rowsHtml = '';
    foreach ($rows as [$label, $value]) {
        $rowsHtml .= sprintf(
            '<tr><td style="padding:8px 14px;background:#f5f5f5;font-weight:600;width:200px;">%s</td><td style="padding:8px 14px;">%s</td></tr>',
            esc($label),
            esc($value)
        );
    }
    $reqHtml = nl2br(esc($requerimiento));

    return '<!doctype html><html lang="es"><body style="font-family:Arial,sans-serif;color:#222;line-height:1.6;">'
         . '<h2 style="color:#1f7a3a;margin:0 0 12px;">Nuevo contacto desde el sitio web</h2>'
         . '<p style="margin:0 0 18px;color:#666;">Shively Bros · grupocorasa.mx</p>'
         . '<table cellpadding="0" cellspacing="0" style="border-collapse:collapse;border:1px solid #ddd;width:100%;max-width:640px;">'
         . $rowsHtml
         . '</table>'
         . '<h3 style="margin:22px 0 8px;">Requerimiento Específico</h3>'
         . '<div style="padding:14px;background:#fafafa;border-left:4px solid #1f7a3a;white-space:pre-wrap;">' . $reqHtml . '</div>'
         . '<p style="margin:24px 0 0;font-size:12px;color:#999;">Responder a este correo contesta directamente al lead (Reply-To configurado).</p>'
         . '</body></html>';
}

function buildTextBody(string $nombre, string $empresa, string $cargo, string $email, string $telefono, string $servicio, string $requerimiento): string
{
    return "Nuevo contacto desde el sitio web — Shively Bros\n"
         . str_repeat('-', 50) . "\n"
         . "Nombre:               $nombre\n"
         . "Empresa:              $empresa\n"
         . "Cargo:                $cargo\n"
         . "Email:                $email\n"
         . "Teléfono / WhatsApp:  " . ($telefono !== '' ? $telefono : '(no proporcionado)') . "\n"
         . "Servicio de Interés:  $servicio\n"
         . str_repeat('-', 50) . "\n"
         . "Requerimiento Específico:\n\n"
         . $requerimiento . "\n";
}

function esc(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}
