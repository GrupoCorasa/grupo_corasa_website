<?php
/**
 * Recipient list normalisation, shared by every Shively Bros contact endpoint.
 *
 * MAIL_TO / MAIL_CC in config.php may each be a single address string or an
 * array of addresses. Everything downstream wants a plain list, so normalise
 * here: trim, drop anything that isn't a valid address, and de-duplicate
 * case-insensitively.
 *
 * Addresses passed in $exclude are dropped as well, which is how an address
 * listed in both MAIL_TO and MAIL_CC stays out of the Cc header — PHPMailer
 * silently refuses a duplicate recipient, so filtering it here keeps the
 * configured intent and the actual headers in agreement.
 *
 * A malformed entry is logged and skipped rather than aborting the send: one
 * typo in the config should not take the whole form offline. The caller is
 * responsible for checking that the resulting To list is non-empty.
 */

declare(strict_types=1);

function normalizeRecipients($value, array $exclude = []): array
{
    $seen = [];
    foreach ($exclude as $addr) {
        $seen[mb_strtolower($addr)] = true;
    }

    $out = [];
    foreach ((array)$value as $entry) {
        if (!is_string($entry)) {
            continue;
        }
        $addr = trim($entry);
        if ($addr === '') {
            continue;
        }
        if (!filter_var($addr, FILTER_VALIDATE_EMAIL)) {
            error_log('[shively-contact] skipping invalid configured recipient: ' . $addr);
            continue;
        }
        $key = mb_strtolower($addr);
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $out[] = $addr;
    }

    return $out;
}
