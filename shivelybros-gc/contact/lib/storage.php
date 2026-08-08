<?php
/**
 * Writable-storage helper for the lead log and the rate limiter.
 *
 * Everything lives under contact/storage/, which sits inside the directory
 * already blocked by contact/.htaccess (Require all denied). A second .htaccess
 * is dropped alongside the data anyway: defence in depth costs nothing, and a
 * lost parent .htaccess would otherwise publish every lead on the open web.
 *
 * Storage is strictly best-effort. Nothing in here may abort a request — a
 * read-only filesystem should cost us a log line, never a customer's enquiry.
 */

declare(strict_types=1);

if (defined('SHIVELY_CONTACT_STORAGE')) {
    return;
}
define('SHIVELY_CONTACT_STORAGE', true);

/** Written into every storage directory in case the parent rule ever goes missing. */
const CONTACT_STORAGE_HTACCESS = <<<'HTA'
# Contact-form data. Never web-accessible.
<IfModule mod_authz_core.c>
    Require all denied
</IfModule>
<IfModule !mod_authz_core.c>
    Order deny,allow
    Deny from all
</IfModule>
HTA;

/**
 * Resolve (and lazily create) a storage subdirectory.
 * Returns null when storage is unavailable — callers must handle that.
 */
function contact_storage_dir(array $cfg, string $subdirectory): ?string
{
    $base = (string)($cfg['STORAGE_DIR'] ?? '');
    if ($base === '') {
        $base = dirname(__DIR__) . '/storage';
    }
    $path = rtrim($base, '/') . '/' . trim($subdirectory, '/');

    if (is_dir($path)) {
        return is_writable($path) ? $path : contact_storage_unwritable($path);
    }

    if (!@mkdir($path, 0700, true) && !is_dir($path)) {
        contact_log('storage unavailable: could not create ' . $path);
        return null;
    }

    // Protect the whole tree, not just the leaf we happened to create first.
    contact_storage_protect($base);
    contact_storage_protect($path);

    return is_writable($path) ? $path : contact_storage_unwritable($path);
}

function contact_storage_unwritable(string $path): ?string
{
    contact_log('storage unavailable: ' . $path . ' is not writable');
    return null;
}

function contact_storage_protect(string $directory): void
{
    if (!is_dir($directory)) {
        return;
    }
    $htaccess = $directory . '/.htaccess';
    if (!file_exists($htaccess)) {
        @file_put_contents($htaccess, CONTACT_STORAGE_HTACCESS . "\n");
    }
    // Belt and braces for hosts that ignore .htaccess in some directories.
    $index = $directory . '/index.html';
    if (!file_exists($index)) {
        @file_put_contents($index, "");
    }
}

/**
 * Append one line to a file, creating it if needed.
 * LOCK_EX keeps concurrent submissions from interleaving mid-line.
 */
function contact_storage_append(string $file, string $line): bool
{
    $written = @file_put_contents($file, $line . "\n", FILE_APPEND | LOCK_EX);
    if ($written === false) {
        contact_log('failed to append to ' . basename($file));
        return false;
    }
    @chmod($file, 0600);
    return true;
}
