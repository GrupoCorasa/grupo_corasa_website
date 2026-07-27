<?php
/**
 * Shively Bros contact form configuration — EXAMPLE.
 *
 * Copy this file to config.php and fill in the real values.
 * config.php is gitignored; this example file is committed so the
 * required shape is always discoverable.
 */

return [
    'RECAPTCHA_SECRET_KEY' => '',
    'RECAPTCHA_MIN_SCORE'  => 0.5,
    'RECAPTCHA_ACTION'     => 'contact_submit',

    'SMTP_HOST'      => '',
    'SMTP_PORT'      => 587,
    'SMTP_SECURE'    => 'tls',
    'SMTP_USER'      => '',
    'SMTP_PASS'      => '',
    'SMTP_FROM'      => 'noreply@grupocorasa.mx',
    'SMTP_FROM_NAME' => 'Sitio Web Shively Bros',

    // MAIL_TO / MAIL_CC each accept a single address string or an array of
    // addresses. Invalid entries are logged and skipped; an address present in
    // both lists is sent To only. MAIL_TO must resolve to at least one address.
    'MAIL_TO'        => 'infoshivelybros@grupocorasa.mx',
    'MAIL_CC'        => ['info@corasaindustrial.com'],

    'SMTP_DEBUG'     => 0,
];
