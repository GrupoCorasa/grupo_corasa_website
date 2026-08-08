<?php
/**
 * Delivery layer.
 *
 * SMTP is the primary transport, with two fallbacks chosen to be safe against
 * the one thing that would be worse than a failed send — a duplicated one:
 *
 *   1. Retry, but ONLY on a connect-stage failure. If PHPMailer never opened
 *      the session, nothing was transmitted, so re-sending cannot duplicate the
 *      message. A failure after DATA is never retried, because we can't tell
 *      "rejected" from "delivered, then the connection dropped".
 *
 *   2. Fall back to the local sendmail/mail() queue. This host's web and mail
 *      servers share an IP (69.162.125.98) which the domain's SPF record
 *      already authorises, and cPanel's Exim signs locally-injected mail with
 *      DKIM — so the fallback keeps its deliverability rather than getting
 *      filed as spam. Handing the message to a local queue also survives the
 *      remote SMTP listener being down, which is the usual reason to need it.
 *
 * The caller records the outcome in the lead journal either way.
 */

declare(strict_types=1);

if (defined('SHIVELY_CONTACT_MAILER')) {
    return;
}
define('SHIVELY_CONTACT_MAILER', true);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

function contact_mailer_load(string $libDir): void
{
    require_once $libDir . '/PHPMailer/Exception.php';
    require_once $libDir . '/PHPMailer/PHPMailer.php';
    require_once $libDir . '/PHPMailer/SMTP.php';
}

/**
 * @param array $message  subject, html, text, reply_email, reply_name
 * @return array{ok:bool, transport:string, error:string}
 */
function contact_send_mail(array $cfg, array $to, array $cc, array $message, string $libDir): array
{
    contact_mailer_load($libDir);

    $attempt = static function (bool $useSmtp) use ($cfg, $to, $cc, $message): array {
        $mail = new PHPMailer(true);
        try {
            if ($useSmtp) {
                $mail->isSMTP();
                $mail->Host        = (string)$cfg['SMTP_HOST'];
                $mail->Port        = (int)$cfg['SMTP_PORT'];
                $mail->SMTPAuth    = true;
                $mail->Username    = (string)$cfg['SMTP_USER'];
                $mail->Password    = (string)$cfg['SMTP_PASS'];
                $mail->SMTPSecure  = (string)$cfg['SMTP_SECURE'];
                $mail->SMTPAutoTLS = true;
                $mail->Timeout     = (int)($cfg['SMTP_TIMEOUT'] ?? 15);
                $mail->SMTPDebug   = (int)($cfg['SMTP_DEBUG'] ?? 0);
                if ($mail->SMTPDebug > 0) {
                    $mail->Debugoutput = static function ($str) {
                        contact_log('[smtp] ' . trim((string)$str));
                    };
                }
            } else {
                $mail->isMail();
            }

            $mail->CharSet  = 'UTF-8';
            $mail->Encoding = 'base64';
            $mail->XMailer  = ' '; // don't advertise the library version

            $mail->setFrom((string)$cfg['SMTP_FROM'], (string)$cfg['SMTP_FROM_NAME']);
            // Envelope sender: keeps Return-Path on the SPF-authorised domain
            // instead of whatever the visitor typed.
            $mail->Sender = (string)$cfg['SMTP_FROM'];

            foreach ($to as $address) {
                $mail->addAddress($address);
            }
            foreach ($cc as $address) {
                $mail->addCC($address);
            }

            // Reply-To is a convenience, not the payload — the address is also
            // in the body. PHPMailer validates more strictly than the form does
            // (IDN domains, unusual local parts), so a rejection here must
            // degrade to "send without Reply-To", never to a lost lead.
            try {
                $mail->addReplyTo((string)$message['reply_email'], (string)$message['reply_name']);
            } catch (PHPMailerException $e) {
                contact_log('Reply-To rejected, sending without it: ' . $e->getMessage());
            }

            $mail->Subject = (string)$message['subject'];
            $mail->isHTML(true);
            $mail->Body    = (string)$message['html'];
            $mail->AltBody = (string)$message['text'];

            $mail->send();

            return ['ok' => true, 'error' => ''];
        } catch (Throwable $e) {
            $detail = $mail->ErrorInfo !== '' ? $mail->ErrorInfo : $e->getMessage();
            return ['ok' => false, 'error' => $detail];
        }
    };

    // ── Attempt 1: SMTP ─────────────────────────────────────────────────────
    $result = $attempt(true);
    if ($result['ok']) {
        return ['ok' => true, 'transport' => 'smtp', 'error' => ''];
    }
    contact_log('SMTP send failed: ' . $result['error']);

    // ── Attempt 2: retry, only if the session never opened ──────────────────
    if (contact_smtp_error_is_connect_stage($result['error'])) {
        usleep(750000);
        $retry = $attempt(true);
        if ($retry['ok']) {
            contact_log('SMTP send succeeded on retry after a connect failure');
            return ['ok' => true, 'transport' => 'smtp-retry', 'error' => ''];
        }
        contact_log('SMTP retry failed: ' . $retry['error']);
        $result = $retry;
    }

    // ── Attempt 3: hand off to the local mail queue ─────────────────────────
    $fallbackEnabled = (bool)($cfg['MAIL_FALLBACK_SENDMAIL'] ?? true);
    if ($fallbackEnabled && contact_function_available('mail')) {
        $fallback = $attempt(false);
        if ($fallback['ok']) {
            contact_log('delivered via local mail() fallback after SMTP failure');
            return ['ok' => true, 'transport' => 'sendmail-fallback', 'error' => $result['error']];
        }
        contact_log('mail() fallback failed: ' . $fallback['error']);
    }

    return ['ok' => false, 'transport' => 'none', 'error' => $result['error']];
}

/**
 * Did the failure happen before any message data was transmitted?
 * Only these are safe to retry.
 */
function contact_smtp_error_is_connect_stage(string $error): bool
{
    $needles = [
        'SMTP connect() failed',
        'Could not connect to SMTP host',
        'Connection refused',
        'Connection timed out',
        'Failed to connect to server',
    ];
    // Note: authentication failures are deliberately absent. They are equally
    // safe to retry, but deterministic — a second attempt just adds latency
    // before the fallback that can actually deliver.
    foreach ($needles as $needle) {
        if (stripos($error, $needle) !== false) {
            return true;
        }
    }
    return false;
}
