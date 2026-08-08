<?php
/**
 * Notification email bodies.
 *
 * Two things beyond the visitor's answers earn their place in the message:
 *
 *   Notices — when reCAPTCHA could not be verified the lead is still delivered
 *   (see recaptcha.php), so the email has to say so plainly at the top. A
 *   silently unverified lead is worse than a rejected one: the recipient can't
 *   tell it apart from a screened one.
 *
 *   Trace block — origin, timestamp, score and IP, kept below the fold. When a
 *   lead is disputed or a spam wave arrives, this is what makes it diagnosable.
 *
 * Every interpolated value passes through contact_esc(); the only HTML that
 * reaches the body is generated here.
 */

declare(strict_types=1);

if (defined('SHIVELY_CONTACT_TEMPLATE')) {
    return;
}
define('SHIVELY_CONTACT_TEMPLATE', true);

/**
 * ENT_SUBSTITUTE matters here: without it htmlspecialchars() returns an empty
 * string for malformed UTF-8, silently blanking a field in the notification
 * instead of showing what the visitor typed. Input is already scrubbed at
 * intake; this is the second line of defence.
 */
function contact_esc(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5 | ENT_SUBSTITUTE, 'UTF-8');
}

/** Ordered label => value pairs shown in the main table and the text part. */
function contact_summary_rows(array $fields, array $meta): array
{
    $rows = [
        ['Nombre',              (string)($fields['nombre']   ?? '')],
        ['Empresa',             (string)($fields['empresa']  ?? '')],
        ['Cargo',               (string)($fields['cargo']    ?? '')],
        ['Email',               (string)($fields['email']    ?? '')],
        ['Teléfono / WhatsApp', ($fields['telefono'] ?? '') !== '' ? (string)$fields['telefono'] : '(no proporcionado)'],
        ['Servicio de Interés', (string)($fields['servicio'] ?? '')],
    ];

    if (!empty($meta['origin_label'])) {
        $rows[] = ['Origen', (string)$meta['origin_label']];
    }

    return $rows;
}

/** Human-readable provenance, kept out of the way at the bottom of the mail. */
function contact_trace_rows(array $meta): array
{
    $recaptcha = is_array($meta['recaptcha'] ?? null) ? $meta['recaptcha'] : [];
    $verdict   = (string)($recaptcha['verdict'] ?? 'unknown');
    $score     = $recaptcha['score'] ?? null;

    $verdictText = $verdict === 'pass'
        ? 'verificado' . ($score !== null ? sprintf(' (score %.2f)', (float)$score) : '')
        : ($verdict === 'unavailable' ? 'NO verificado — servicio no disponible' : $verdict);

    return [
        ['Fecha y hora', (string)($meta['timestamp'] ?? '')],
        ['Página',       (string)($meta['source']    ?? '')],
        ['Anti-bot',     $verdictText],
        ['IP',           (string)($meta['ip'] ?? '')],
    ];
}

function contact_build_html_body(array $fields, array $meta): string
{
    $notices    = is_array($meta['notices'] ?? null) ? $meta['notices'] : [];
    $noticeHtml = '';
    foreach ($notices as $notice) {
        $noticeHtml .= '<div style="margin:0 0 16px;padding:12px 16px;background:#fff6e5;'
                     . 'border-left:4px solid #e0a800;color:#6b4c00;font-size:14px;line-height:1.5;">'
                     . '<strong>Aviso:</strong> ' . contact_esc((string)$notice)
                     . '</div>';
    }

    $rowsHtml = '';
    foreach (contact_summary_rows($fields, $meta) as $row) {
        $rowsHtml .= sprintf(
            '<tr><td style="padding:8px 14px;background:#f5f5f5;font-weight:600;width:200px;">%s</td>'
            . '<td style="padding:8px 14px;">%s</td></tr>',
            contact_esc($row[0]),
            contact_esc($row[1])
        );
    }

    $traceHtml = '';
    foreach (contact_trace_rows($meta) as $row) {
        $traceHtml .= sprintf(
            '<tr><td style="padding:3px 10px 3px 0;color:#999;">%s</td><td style="padding:3px 0;color:#777;">%s</td></tr>',
            contact_esc($row[0]),
            contact_esc($row[1])
        );
    }

    $heading = !empty($meta['origin_label'])
        ? contact_esc((string)$meta['origin_label']) . ' · Shively Bros · grupocorasa.mx'
        : 'Shively Bros · grupocorasa.mx';

    $requerimiento = nl2br(contact_esc((string)($fields['requerimiento'] ?? '')));

    return '<!doctype html><html lang="es"><body style="font-family:Arial,sans-serif;color:#222;line-height:1.6;">'
         . $noticeHtml
         . '<h2 style="color:#1f7a3a;margin:0 0 12px;">Nuevo contacto desde el sitio web</h2>'
         . '<p style="margin:0 0 18px;color:#666;">' . $heading . '</p>'
         . '<table cellpadding="0" cellspacing="0" style="border-collapse:collapse;border:1px solid #ddd;width:100%;max-width:640px;">'
         . $rowsHtml
         . '</table>'
         . '<h3 style="margin:22px 0 8px;">Requerimiento Específico</h3>'
         . '<div style="padding:14px;background:#fafafa;border-left:4px solid #1f7a3a;white-space:pre-wrap;">' . $requerimiento . '</div>'
         . '<p style="margin:24px 0 6px;font-size:12px;color:#999;">Responder a este correo contesta directamente al lead (Reply-To configurado).</p>'
         . '<table cellpadding="0" cellspacing="0" style="border-collapse:collapse;font-size:11px;margin-top:10px;">' . $traceHtml . '</table>'
         . '</body></html>';
}

/**
 * Right-pad by character count, not byte count. str_pad() would under-pad
 * "Teléfono / WhatsApp" because the accented character costs two bytes.
 */
function contact_pad(string $label, int $width): string
{
    $padding = $width - mb_strlen($label, 'UTF-8');
    return $label . ($padding > 0 ? str_repeat(' ', $padding) : ' ');
}

function contact_build_text_body(array $fields, array $meta): string
{
    $line    = str_repeat('-', 50);
    $notices = is_array($meta['notices'] ?? null) ? $meta['notices'] : [];

    $out = '';
    foreach ($notices as $notice) {
        $out .= '*** AVISO: ' . $notice . " ***\n";
    }
    if ($notices) {
        $out .= "\n";
    }

    $title = !empty($meta['origin_label'])
        ? 'Nuevo contacto desde el sitio web — ' . $meta['origin_label'] . ' · Shively Bros'
        : 'Nuevo contacto desde el sitio web — Shively Bros';

    $out .= $title . "\n" . $line . "\n";
    foreach (contact_summary_rows($fields, $meta) as $row) {
        $out .= contact_pad($row[0] . ':', 22) . $row[1] . "\n";
    }
    $out .= $line . "\n"
          . "Requerimiento Específico:\n\n"
          . (string)($fields['requerimiento'] ?? '') . "\n\n"
          . $line . "\n";
    foreach (contact_trace_rows($meta) as $row) {
        $out .= contact_pad($row[0] . ':', 22) . $row[1] . "\n";
    }

    return $out;
}
