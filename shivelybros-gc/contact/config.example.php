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

    'MAIL_TO'        => 'infoshivelybros@grupocorasa.mx',

    'SMTP_DEBUG'     => 0,
];
