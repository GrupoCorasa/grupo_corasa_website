<?php
/**
 * Append-only lead journal.
 *
 * Email is a lossy channel: the mailbox can fill, credentials can rotate, a
 * relay can go down. When that happens the visitor still filled in the form and
 * still expects a call back — so the lead is written to disk BEFORE the send is
 * attempted, and the delivery outcome is appended afterwards.
 *
 * That ordering is the whole point. A crash between "recorded" and "sent"
 * leaves a recoverable record; the reverse ordering loses the lead entirely,
 * which is exactly what happened during the outage this replaces.
 *
 * Format is JSON Lines (one JSON object per line): appendable without parsing,
 * greppable from cPanel's file manager, and importable into a spreadsheet or
 * CRM later. Two record kinds share the file, correlated by `id`:
 *
 *   {"kind":"lead",     "id":"…", "delivery":"pending", …full submission…}
 *   {"kind":"delivery", "id":"…", "delivery":"sent"|"failed", …}
 */

declare(strict_types=1);

if (defined('SHIVELY_CONTACT_LEADLOG')) {
    return;
}
define('SHIVELY_CONTACT_LEADLOG', true);

/** Correlation id for the two records belonging to one submission. */
function contact_lead_id(): string
{
    try {
        return bin2hex(random_bytes(8));
    } catch (Throwable $e) {
        return substr(md5(uniqid('', true)), 0, 16);
    }
}

/** One file per month keeps any single file small enough to open in a browser. */
function contact_lead_file(array $cfg): ?string
{
    $dir = contact_storage_dir($cfg, 'leads');
    if ($dir === null) {
        return null;
    }
    return $dir . '/leads-' . contact_now($cfg)->format('Y-m') . '.jsonl';
}

/**
 * Record the submission before the send is attempted.
 * Returns false when storage is unavailable; the caller carries on regardless.
 */
function contact_lead_record(array $cfg, string $id, array $fields, array $context): bool
{
    $file = contact_lead_file($cfg);
    if ($file === null) {
        return false;
    }

    $record = [
        'kind'      => 'lead',
        'id'        => $id,
        'ts'        => contact_now($cfg)->format(DATE_ATOM),
        'delivery'  => 'pending',
        'source'    => $context['source']   ?? '',
        'origin'    => $context['origin']   ?? '',
        'nombre'    => $fields['nombre']    ?? '',
        'empresa'   => $fields['empresa']   ?? '',
        'cargo'     => $fields['cargo']     ?? '',
        'email'     => $fields['email']     ?? '',
        'telefono'  => $fields['telefono']  ?? '',
        'servicio'  => $fields['servicio']  ?? '',
        'requerimiento' => $fields['requerimiento'] ?? '',
        'recaptcha' => [
            'verdict' => $context['recaptcha']['verdict'] ?? 'unknown',
            'score'   => $context['recaptcha']['score']   ?? null,
            'reason'  => $context['recaptcha']['reason']  ?? '',
        ],
        'ip'        => contact_client_ip(),
        'ua'        => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 300),
    ];

    return contact_storage_append($file, contact_lead_encode($record));
}

/** Append the delivery outcome for a previously recorded lead. */
function contact_lead_outcome(array $cfg, string $id, bool $sent, string $transport, string $error = ''): bool
{
    $file = contact_lead_file($cfg);
    if ($file === null) {
        return false;
    }

    return contact_storage_append($file, contact_lead_encode([
        'kind'      => 'delivery',
        'id'        => $id,
        'ts'        => contact_now($cfg)->format(DATE_ATOM),
        'delivery'  => $sent ? 'sent' : 'failed',
        'transport' => $transport,
        'error'     => $error,
    ]));
}

/**
 * Encode one record, tolerating malformed UTF-8 rather than silently writing
 * nothing. json_encode returns false on invalid byte sequences, and a visitor
 * pasting from Word is a realistic way to get them.
 */
function contact_lead_encode(array $record): string
{
    $json = json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        $json = json_encode($record, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    }
    return $json === false ? '{"kind":"lead","error":"encode-failed"}' : $json;
}
