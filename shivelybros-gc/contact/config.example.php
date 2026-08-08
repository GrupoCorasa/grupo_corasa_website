<?php
/**
 * Shively Bros contact form configuration — EXAMPLE.
 *
 * Copy this file to config.php and fill in the real values.
 * config.php is gitignored; this example file is committed so the
 * required shape is always discoverable.
 */

return [
    // ── reCAPTCHA v3 ────────────────────────────────────────────────────────
    'RECAPTCHA_SECRET_KEY' => '',
    'RECAPTCHA_MIN_SCORE'  => 0.5,
    'RECAPTCHA_ACTION'     => 'contact_submit',
    'RECAPTCHA_TIMEOUT'    => 8,                    // seconds to wait for Google

    // ── SMTP transport ──────────────────────────────────────────────────────
    'SMTP_HOST'      => '',
    'SMTP_PORT'      => 587,                        // 587 (STARTTLS) or 465 (SMTPS)
    'SMTP_SECURE'    => 'tls',                      // 'tls' for 587, 'ssl' for 465
    'SMTP_USER'      => '',
    'SMTP_PASS'      => '',
    'SMTP_FROM'      => 'noreply@grupocorasa.mx',
    'SMTP_FROM_NAME' => 'Sitio Web Shively Bros',
    'SMTP_TIMEOUT'   => 15,

    // If SMTP fails, hand the message to the server's local mail queue rather
    // than losing it. Only leave this on where locally-injected mail is
    // SPF/DKIM-aligned for the sending domain (true on cPanel/Exim hosts).
    'MAIL_FALLBACK_SENDMAIL' => true,

    // ── Delivery ────────────────────────────────────────────────────────────
    // MAIL_TO / MAIL_CC each accept a single address string or an array of
    // addresses. Invalid entries are logged and skipped; an address present in
    // both lists is sent To only. MAIL_TO must resolve to at least one address.
    'MAIL_TO'        => 'infoshivelybros@grupocorasa.mx',
    'MAIL_CC'        => ['info@corasaindustrial.com'],

    // ── Anti-spam ───────────────────────────────────────────────────────────
    // Backstop for the fail-open reCAPTCHA policy (see contact/lib/recaptcha.php):
    // if Google is unreachable the form keeps accepting leads, so this caps the
    // damage. Counted per IP; only accepted submissions spend quota.
    // Set RATE_LIMIT_MAX to 0 to disable.
    'RATE_LIMIT_MAX'    => 5,
    'RATE_LIMIT_WINDOW' => 3600,                    // seconds

    // ── Operations ──────────────────────────────────────────────────────────
    'TIMEZONE'    => 'America/Monterrey',

    // Absolute path for the lead journal and rate-limit buckets. Leave empty to
    // use contact/storage/ (already blocked from the web by contact/.htaccess).
    'STORAGE_DIR' => '',

    // Secret for contact-diag.php. Any request without this exact token gets a
    // 404. Generate one with:  php -r "echo bin2hex(random_bytes(24));"
    // Leave empty to disable the diagnostic endpoint entirely.
    'DIAG_TOKEN'  => '',

    // ── Debug ───────────────────────────────────────────────────────────────
    // 0 = silent (production), 2 = verbose SMTP transcript to error_log.
    'SMTP_DEBUG'     => 0,
];
