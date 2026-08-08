# Shively Bros contact forms — operations guide

Lives inside `contact/`, which Apache blocks entirely (`Require all denied`), so
this file is not reachable from the web.

---

## What broke

Every submission returned **HTTP 500 with a zero-byte body**, on all six landing
pages, and no email was ever attempted.

`verifyRecaptcha()` was the only step with no `try/catch` around it — the `try`
block began afterwards and only wrapped PHPMailer. Its sole dependency was the
**cURL extension**. With cURL unavailable to PHP, `curl_init()` raises
`Error: Call to undefined function`, which went uncaught and killed the request.
PHP returned an empty 500; the browser's `res.json()` threw on the empty body;
the visitor saw *"Respuesta inesperada del servidor."*

The usual trigger on cPanel is switching the domain's PHP version — the
extension set resets and `curl` quietly gets deselected.

---

## Architecture after the fix

    shivelybros-gc/
      contact.php                    ┐
      abrasivos/contact.php          │ six ~20-line endpoints: each declares only
      mro/contact.php                ├ its subject prefix, origin label and form
      metrologia/contact.php         │ source, then calls the shared handler
      herramientas-de-corte/…        │
      filtracion-industrial/…        ┘
      contact-diag.php                 token-gated environment check

      contact/
        config.php                     secrets (gitignored — never committed)
        config.example.php             the shape, committed
        lib/
          bootstrap.php                always answers JSON, whatever happens
          http.php                     cURL → stream fallback
          recaptcha.php                verdict-based verification
          mailer.php                   SMTP + retry + sendmail fallback
          leadlog.php                  append-only lead journal
          ratelimit.php                per-IP throttle
          template.php                 email bodies
          handler.php                  the pipeline
          recipients.php               MAIL_TO / MAIL_CC normalisation
        storage/                       created at runtime; leads + rate buckets

The six endpoints went from 1,656 duplicated lines to 126. They were duplicated
before, which is precisely why one bug took down all six at once.

### The four guarantees

1. **It always answers JSON.** Output buffering, a `Throwable` handler and a
   shutdown handler mean an empty 500 cannot happen again. Every failure carries
   a machine-readable `code`.
2. **reCAPTCHA can't take the form down.** Verification returns a verdict.
   `bot` rejects. `expired` asks for a retry (v3 tokens live 120s). `unavailable`
   — no transport, network error, bad secret — **fails open**: the lead is
   delivered with a visible ⚠ banner rather than lost. The honeypot and rate
   limit still apply.
3. **Delivery has fallbacks.** SMTP first; one retry but *only* on a connect
   failure (nothing was transmitted, so it cannot duplicate); then the local mail
   queue, which is SPF/DKIM-aligned on this host.
4. **Leads survive delivery failure.** Every submission is journalled to disk
   *before* the send, with the outcome appended after.

---

## Deploying (cPanel File Manager)

Upload into `public_html/shivelybros-gc/`, preserving the directory layout.

**New files** — create `contact/lib/` entries alongside the existing
`recipients.php` and `PHPMailer/`:

    contact/lib/bootstrap.php
    contact/lib/http.php
    contact/lib/recaptcha.php
    contact/lib/storage.php
    contact/lib/leadlog.php
    contact/lib/ratelimit.php
    contact/lib/template.php
    contact/lib/mailer.php
    contact/lib/handler.php
    contact-diag.php

**Replaced files:**

    contact.php
    abrasivos/contact.php
    mro/contact.php
    metrologia/contact.php
    herramientas-de-corte/contact.php
    filtracion-industrial/contact.php
    js/contact-form-multi.js
    contact/config.php          ← same credentials, plus the new keys

`contact/config.php` is gitignored, so it must be uploaded by hand. The new keys
(`DIAG_TOKEN`, `RATE_LIMIT_*`, `MAIL_FALLBACK_SENDMAIL`, `TIMEZONE`,
`STORAGE_DIR`, `SMTP_TIMEOUT`, `RECAPTCHA_TIMEOUT`) all have safe defaults, so
the forms work with the old file too — but the diagnostic needs `DIAG_TOKEN`.

Nothing else changes: same URLs, same form markup, same `gracias.php` flow.
`index.html` and the landing pages are untouched.

> If a change doesn't seem to take effect, give it a few seconds — opcache
> revalidates on a timer.

---

## Verifying, in order

**1. Environment check.** Open, with the token from `config.php`:

    https://grupocorasa.mx/shivelybros-gc/contact-diag.php?token=<DIAG_TOKEN>

Read `verdict`. `"healthy": true` means transports, credentials, recipients and
storage are all good. Otherwise `problems` names each fault and its fix. Without
a valid token this URL returns a plain 404.

**2. Confirm the cURL diagnosis.** In the report, `extensions.curl` should be
`true`. If it is `false`, that was the root cause — fix it in
**cPanel → Select PHP Version → Extensions → tick `curl`**. The form now works
either way (the stream fallback covers it), but cURL is the better path.

**3. Send a real test email:**

    https://grupocorasa.mx/shivelybros-gc/contact-diag.php?token=<DIAG_TOKEN>&send=1

Check `infoshivelybros@grupocorasa.mx` and `info@corasaindustrial.com`.

**4. Submit the live form** on `/shivelybros-gc/` and confirm the redirect to
`gracias.php` and the arriving email.

**5. Optionally delete `contact-diag.php`** once healthy. It is safe to leave —
it 404s without the token and never echoes credentials — but removing it is
fine too.

---

## Day-to-day

**Recovering leads.** `contact/storage/leads/leads-YYYY-MM.jsonl`, one JSON
object per line, downloadable from File Manager. Two records share an `id`: the
submission (`"kind":"lead"`) and its outcome (`"kind":"delivery"`). Anything
showing `"delivery":"failed"` arrived but was never emailed — follow up by hand.

**An email marked ⚠ "NO fue verificado por reCAPTCHA"** means the lead was
accepted without anti-bot screening because Google was unreachable. Treat it with
normal suspicion and check the diagnostic.

**Tuning.** `RATE_LIMIT_MAX` (default 5/hour per IP; `0` disables) and
`RECAPTCHA_MIN_SCORE` (default 0.5 — raise toward 0.7 if spam gets through,
lower toward 0.3 if real enquiries are being blocked).

**Server-side errors** are logged with a `[shively-contact:<page>]` prefix —
grep cPanel's error log for `shively-contact`.

---

## Rolling back

The previous versions of the six `contact.php` files and
`js/contact-form-multi.js` are in git history. Restoring them restores the
outage, so prefer fixing forward.
