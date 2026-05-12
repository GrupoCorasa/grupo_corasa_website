<?php
/**
 * Thank-you page shown after a successful contact form submission.
 *
 * Access is gated by a one-shot session flag set in contact.php on success.
 * Direct URL access redirects to the main landing. Refreshing the page
 * after consumption also bounces — the flag is cleared on render.
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
session_start();

header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex, nofollow');

if (empty($_SESSION['contact_form_submitted'])) {
    http_response_code(303);
    header('Location: /shivelybros-gc/index.html');
    exit;
}

$source = $_SESSION['contact_form_source'] ?? '/shivelybros-gc/index.html';

unset($_SESSION['contact_form_submitted']);
unset($_SESSION['contact_form_source']);
session_write_close();
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

    <!-- Google tag (gtag.js) — Google Analytics (GA4) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-61H5JPVFCH"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag() { dataLayer.push(arguments); }
        gtag('js', new Date());
        gtag('config', 'G-61H5JPVFCH');
    </script>

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
            background: var(--dark);
            color: var(--white);
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        .gracias-header {
            display: flex;
            align-items: center;
            padding: 18px 28px;
            background: var(--dark2);
            border-bottom: 1px solid rgba(255, 255, 255, 0.06);
        }
        .gracias-header a { display: inline-flex; align-items: center; }
        .gracias-header img { height: 52px; }
        .gracias-header .brand-text {
            margin-left: 14px;
            font-family: 'Oswald', sans-serif;
            font-weight: 600;
            letter-spacing: 0.5px;
            font-size: 18px;
            color: var(--white);
        }
        .gracias-header .brand-text span { color: var(--green); }
        main.gracias-main {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 48px 20px;
        }
        .gracias-card {
            background: var(--dark2);
            border: 1px solid rgba(255, 255, 255, 0.06);
            border-radius: 16px;
            padding: 52px 38px;
            max-width: 640px;
            width: 100%;
            text-align: center;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.4);
        }
        .gracias-check {
            width: 76px;
            height: 76px;
            border-radius: 50%;
            background: rgba(36, 173, 67, 0.15);
            color: var(--green);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 34px;
            margin-bottom: 22px;
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
            .gracias-header { padding: 14px 18px; }
            .gracias-header img { height: 44px; }
            .gracias-header .brand-text { font-size: 16px; margin-left: 10px; }
            .gracias-card { padding: 38px 22px; }
            .gracias-card h1 { font-size: 26px; }
            .gracias-card .lead { font-size: 15.5px; }
        }
    </style>
</head>
<body>
    <!-- Google Tag Manager (noscript) -->
    <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-TKV3MDLS"
    height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
    <!-- End Google Tag Manager (noscript) -->

    <header class="gracias-header">
        <a href="https://grupocorasa.mx" aria-label="Grupo Corasa">
            <img src="image/slider/logo.png" alt="Shively Bros">
        </a>
        <span class="brand-text d-none d-md-inline">Corasa &nbsp;·&nbsp; <span>Shively Bros</span></span>
    </header>

    <main class="gracias-main">
        <div class="gracias-card">
            <div class="gracias-check"><i class="fa fa-check"></i></div>
            <h1>¡Gracias por contactarnos!</h1>
            <p class="lead">Hemos recibido correctamente tu solicitud. Uno de nuestros especialistas se pondrá en contacto contigo a la brevedad para dar seguimiento a tu requerimiento.</p>
            <a href="<?= htmlspecialchars($source, ENT_QUOTES, 'UTF-8') ?>" class="btn-volver">
                <i class="fa fa-arrow-left"></i> Volver al Sitio
            </a>
            <p class="closing">En Shively Bros ofrecemos soluciones industriales respaldadas por experiencia, soporte técnico y atención especializada para la industria.</p>
        </div>
    </main>

    <script>
        window.dataLayer = window.dataLayer || [];
        window.dataLayer.push({
            event: 'form_submit_success',
            form_source: <?= json_encode($source, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
            form_type: 'contact',
            page_path: '/shivelybros-gc/gracias.php'
        });
    </script>
</body>
</html>
