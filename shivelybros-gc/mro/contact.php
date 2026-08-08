<?php
/**
 * Contact form endpoint — Suministros MRO landing page.
 *
 * All behaviour lives in contact/lib/handler.php, which every Shively Bros
 * endpoint shares. This file only declares what makes this page different.
 * Keeping the logic in one place is deliberate: the outage that took these
 * forms down hit all six at once because all six carried their own copy of it.
 */

declare(strict_types=1);

$lib = __DIR__ . '/../contact/lib';

// Guard the one require that cannot protect itself. Deployment here is a manual
// file upload, so a partially copied directory is a realistic failure — and a
// missing include is a fatal error, which is exactly the zero-byte 500 this
// rewrite exists to eliminate. Once bootstrap.php is loaded its shutdown
// handler turns any later missing file into clean JSON.
if (!is_readable($lib . '/bootstrap.php')) {
    error_log('[shively-contact:mro] bootstrap.php missing at ' . $lib);
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo '{"ok":false,"error":"Configuraci\u00f3n del servidor incompleta.","code":"E_BOOTSTRAP_MISSING"}';
    exit;
}

require $lib . '/bootstrap.php';
require $lib . '/handler.php';

contact_handle([
    'tag'            => 'mro',
    'form_source'    => '/shivelybros-gc/mro/index.html',
    'subject_prefix' => '[Sitio web · MRO]',
    'origin_label'   => 'Landing MRO',
    'base_dir'       => __DIR__ . '/../contact',
]);
