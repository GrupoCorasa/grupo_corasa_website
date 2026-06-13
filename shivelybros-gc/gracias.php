<?php
/**
 * Thank-you page shown after a contact form submission.
 *
 * The page is publicly viewable: any direct URL visit renders normally.
 * Conversion tracking is gated separately — the GTM `form_submit_success`
 * event fires only when the one-shot session flag set by contact.php on a
 * genuine submission is present. That flag is consumed on render, so a
 * refresh or a plain direct hit shows the page without (re-)firing the
 * conversion. Direct visitors fall back to the main landing for the
 * "Volver al Sitio" link.
 */

declare(strict_types=1);

session_cache_limiter('');
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/shivelybros-gc/',
    'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                  || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'),
    'httponly' => true,
    'samesite' => 'Lax',
]);
// Only resume an existing session — the page is public now, so don't mint a
// fresh session (and Set-Cookie) for anonymous direct visitors or crawlers.
// A genuine submission always arrives carrying the cookie set by contact.php.
if (!empty($_COOKIE[session_name()])) {
    session_start();
}

header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex, nofollow');

// Did the visitor arrive from an actual form submission? The flag is a
// one-shot set by contact.php; its presence — not access to the page — is
// what authorizes the conversion event emitted at the bottom of the page.
$genuineSubmission = !empty($_SESSION['contact_form_submitted']);
$source            = $_SESSION['contact_form_source'] ?? '/shivelybros-gc/index.html';

// Consume the one-shot flag so a refresh (or a later direct hit reusing the
// same session) renders the page without re-firing the conversion.
if ($genuineSubmission) {
    unset($_SESSION['contact_form_submitted'], $_SESSION['contact_form_source']);
}
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}
?><!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Gracias — Shively Bros</title>

    <!-- Google Tag Manager -->
    <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
    new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
    j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
    'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
    })(window,document,'script','dataLayer','GTM-TKV3MDLS');</script>
    <!-- End Google Tag Manager -->

    <link rel="stylesheet" href="bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&family=Oswald:wght@500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --green:     #24ad43;
            --green-dk:  #1a8533;
            --dark:      #12161c;
            --dark2:     #1b2028;
            --light:     #f5f6f8;
            --muted:     #8a95a3;
            --white:     #ffffff;
        }
        * { box-sizing: border-box; }
        html, body { height: 100%; }
        body {
            margin: 0;
            font-family: 'Open Sans', system-ui, -apple-system, sans-serif;
            color: var(--white);
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            background:
                linear-gradient(135deg,
                    rgba(0, 0, 0, 0.82) 0%,
                    rgba(18, 22, 28, 0.74) 55%,
                    rgba(36, 173, 67, 0.22) 100%),
                url('image/hero.jpg') center center / cover no-repeat;
            background-attachment: fixed;
        }
        main.gracias-main {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 48px 20px;
        }
        .gracias-card {
            background: rgba(27, 32, 40, 0.82);
            border: 1px solid rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-radius: 18px;
            padding: 52px 38px;
            max-width: 640px;
            width: 100%;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.55);
        }
        .gracias-logo {
            height: 96px;
            width: auto;
            margin: 0 0 26px;
            filter: drop-shadow(0 4px 12px rgba(0, 0, 0, 0.4));
        }
        .gracias-card h1 {
            font-family: 'Oswald', sans-serif;
            font-weight: 600;
            color: var(--white);
            font-size: 34px;
            line-height: 1.2;
            margin: 0 0 18px;
        }
        .gracias-card .lead {
            font-size: 17px;
            line-height: 1.65;
            color: rgba(255, 255, 255, 0.85);
            margin: 0 0 32px;
        }
        .btn-volver {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: var(--green);
            color: var(--white);
            text-decoration: none;
            font-weight: 700;
            padding: 14px 32px;
            border-radius: 999px;
            transition: background 0.2s ease, transform 0.15s ease;
            font-size: 15px;
            letter-spacing: 0.3px;
        }
        .btn-volver:hover, .btn-volver:focus {
            background: var(--green-dk);
            color: var(--white);
            text-decoration: none;
            transform: translateY(-1px);
        }
        .gracias-card .closing {
            margin: 38px 0 0;
            padding-top: 28px;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            color: var(--muted);
            font-size: 14px;
            line-height: 1.7;
        }
        @media (max-width: 575px) {
            body { background-attachment: scroll; }
            .gracias-card { padding: 38px 22px; }
            .gracias-card h1 { font-size: 26px; }
            .gracias-card .lead { font-size: 15.5px; }
            .gracias-logo { height: 76px; margin-bottom: 20px; }
        }
    </style>
</head>
<body>
    <!-- Google Tag Manager (noscript) -->
    <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-TKV3MDLS"
    height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
    <!-- End Google Tag Manager (noscript) -->

    <main class="gracias-main">
        <div class="gracias-card">
            <img class="gracias-logo" src="image/shively-bros.png" alt="Shively Bros">
            <h1>¡Gracias por contactarnos!</h1>
            <p class="lead">Hemos recibido correctamente tu solicitud. Uno de nuestros especialistas se pondrá en contacto contigo a la brevedad para dar seguimiento a tu requerimiento.</p>
            <a href="<?= htmlspecialchars($source, ENT_QUOTES, 'UTF-8') ?>" class="btn-volver">
                <i class="fa fa-arrow-left"></i> Volver al Sitio
            </a>
            <p class="closing">En Shively Bros ofrecemos soluciones industriales respaldadas por experiencia, soporte técnico y atención especializada para la industria.</p>
        </div>
    </main>

    <?php if ($genuineSubmission): ?>
    <script>
        window.dataLayer = window.dataLayer || [];
        window.dataLayer.push({
            event: 'form_submit_success',
            form_source: <?= json_encode($source, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
            form_type: 'contact',
            page_path: '/shivelybros-gc/gracias.php'
        });
    </script>
    <?php endif; ?>
</body>
</html>
