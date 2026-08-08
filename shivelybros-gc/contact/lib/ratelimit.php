<?php
/**
 * Per-IP sliding-window rate limit.
 *
 * This exists because reCAPTCHA now fails open. If Google is unreachable the
 * form keeps accepting leads, so something else has to stand between the form
 * and a flood — otherwise "never lose a lead" would quietly become "accept
 * unlimited spam during an outage".
 *
 * Two deliberate choices:
 *
 *   Quota is spent on ACCEPTED submissions only. Checking is read-only and
 *   happens early; the hit is recorded after validation passes. A bot posting
 *   junk therefore can't burn a real customer's allowance from a shared office
 *   NAT, and the cap still means "5 real enquiries per hour" as intended.
 *
 *   Storage failure allows the request. A read-only disk must not take the
 *   contact form offline — that trade is the whole lesson of this outage.
 *
 * IPs are stored as a salted hash: enough to count, not a plaintext visitor log.
 */

declare(strict_types=1);

if (defined('SHIVELY_CONTACT_RATELIMIT')) {
    return;
}
define('SHIVELY_CONTACT_RATELIMIT', true);

function contact_ratelimit_enabled(array $cfg): bool
{
    return (int)($cfg['RATE_LIMIT_MAX'] ?? 5) > 0;
}

function contact_ratelimit_file(array $cfg): ?string
{
    $dir = contact_storage_dir($cfg, 'ratelimit');
    if ($dir === null) {
        return null;
    }
    // Salted with the secret key so the bucket filename can't be reversed to an
    // IP by anyone who somehow gets a directory listing.
    $salt = (string)($cfg['RECAPTCHA_SECRET_KEY'] ?? 'shively');
    return $dir . '/' . hash('sha256', $salt . '|' . contact_client_ip()) . '.json';
}

/** Timestamps for this IP that are still inside the window. */
function contact_ratelimit_hits(array $cfg): array
{
    $file = contact_ratelimit_file($cfg);
    if ($file === null || !is_file($file)) {
        return [];
    }

    $raw = @file_get_contents($file);
    if ($raw === false) {
        return [];
    }

    $hits = json_decode($raw, true);
    if (!is_array($hits)) {
        return [];
    }

    $cutoff = time() - (int)($cfg['RATE_LIMIT_WINDOW'] ?? 3600);
    $hits   = array_values(array_filter($hits, static function ($ts) use ($cutoff) {
        return is_numeric($ts) && (int)$ts > $cutoff;
    }));

    return array_map('intval', $hits);
}

/**
 * Read-only check. Returns ['allowed'=>bool, 'retry_after'=>int seconds].
 */
function contact_ratelimit_check(array $cfg): array
{
    if (!contact_ratelimit_enabled($cfg)) {
        return ['allowed' => true, 'retry_after' => 0];
    }

    $max  = (int)($cfg['RATE_LIMIT_MAX'] ?? 5);
    $hits = contact_ratelimit_hits($cfg);

    if (count($hits) < $max) {
        return ['allowed' => true, 'retry_after' => 0];
    }

    // Quota frees up when the oldest hit leaves the window.
    $window     = (int)($cfg['RATE_LIMIT_WINDOW'] ?? 3600);
    $retryAfter = max(1, (min($hits) + $window) - time());

    contact_log(sprintf('rate limit reached: %d hits in %ds', count($hits), $window));

    return ['allowed' => false, 'retry_after' => $retryAfter];
}

/** Spend one unit of quota. Called only once a submission is accepted. */
function contact_ratelimit_record(array $cfg): void
{
    if (!contact_ratelimit_enabled($cfg)) {
        return;
    }
    $file = contact_ratelimit_file($cfg);
    if ($file === null) {
        return;
    }

    $hits   = contact_ratelimit_hits($cfg);
    $hits[] = time();

    $encoded = json_encode(array_values($hits));
    if ($encoded !== false) {
        @file_put_contents($file, $encoded, LOCK_EX);
        @chmod($file, 0600);
    }

    contact_ratelimit_gc($cfg);
}

/**
 * Probabilistic sweep of expired buckets (~1 request in 50) so the directory
 * doesn't grow without bound on a form that gets steady traffic.
 */
function contact_ratelimit_gc(array $cfg): void
{
    // mt_rand, not random_int: this runs before the message is sent, and
    // random_int can throw when the entropy source is unavailable. Housekeeping
    // must never be the reason a lead fails to go out, and the choice of which
    // requests sweep the directory does not need to be unpredictable.
    if (mt_rand(1, 50) !== 1) {
        return;
    }
    $dir = contact_storage_dir($cfg, 'ratelimit');
    if ($dir === null) {
        return;
    }

    $cutoff = time() - (int)($cfg['RATE_LIMIT_WINDOW'] ?? 3600);
    foreach ((array)glob($dir . '/*.json') as $file) {
        if (is_string($file) && @filemtime($file) < $cutoff) {
            @unlink($file);
        }
    }
}
