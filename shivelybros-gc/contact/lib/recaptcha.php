<?php
/**
 * reCAPTCHA v3 verification.
 *
 * The important design decision here is *what a failure means*. The previous
 * implementation collapsed every outcome into a single boolean, so "Google
 * says this is a bot" and "we couldn't reach Google" both rejected the lead.
 * That is the wrong trade for a B2B contact form: a network hiccup between the
 * host and Google silently costs a real sales lead.
 *
 * So verification returns a verdict, not a bool:
 *
 *   pass         Google verified the token and the score clears the threshold.
 *   bot          Google gave a definitive negative — reject.
 *   expired      The token timed out or was replayed. Almost always a human who
 *                left the tab open (v3 tokens live 120s), so we ask them to
 *                retry rather than accusing them of being a bot.
 *   unavailable  We could not get an answer: no HTTP transport, network error,
 *                malformed response, or our own key is misconfigured. The
 *                caller fails OPEN — the lead is delivered with a visible
 *                warning banner so a human decides.
 *
 * Fail-open is safe here because it is not the only defence: the honeypot and
 * the per-IP rate limit both still apply.
 */

declare(strict_types=1);

if (defined('SHIVELY_CONTACT_RECAPTCHA')) {
    return;
}
define('SHIVELY_CONTACT_RECAPTCHA', true);

const RECAPTCHA_VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';

/**
 * Google error codes that describe OUR misconfiguration rather than the
 * visitor's behaviour. Rejecting the visitor for these would punish them for a
 * server-side mistake, so they fail open (and log loudly).
 */
const RECAPTCHA_SERVER_SIDE_CODES = [
    'missing-input-secret',
    'invalid-input-secret',
    'bad-request',
];

/** Codes meaning the token is stale or already spent — a retry usually fixes it. */
const RECAPTCHA_STALE_CODES = [
    'timeout-or-duplicate',
];

/**
 * @return array{verdict:string, score:?float, reason:string, transport:string}
 */
function contact_verify_recaptcha(string $token, array $cfg): array
{
    $result = static function (string $verdict, string $reason, ?float $score = null, string $transport = '-'): array {
        return ['verdict' => $verdict, 'reason' => $reason, 'score' => $score, 'transport' => $transport];
    };

    $secret = (string)($cfg['RECAPTCHA_SECRET_KEY'] ?? '');
    if ($secret === '' || strncmp($secret, 'TODO_', 5) === 0) {
        contact_log('reCAPTCHA secret key not configured — failing open');
        return $result('unavailable', 'secret-not-configured');
    }

    // Overridable so the pipeline can be exercised against a stub end-to-end.
    // Production never sets this; it stays on Google's endpoint.
    $verifyUrl = (string)($cfg['RECAPTCHA_VERIFY_URL'] ?? RECAPTCHA_VERIFY_URL);

    $response = contact_http_post($verifyUrl, [
        'secret'   => $secret,
        'response' => $token,
        'remoteip' => contact_client_ip(),
    ], (int)($cfg['RECAPTCHA_TIMEOUT'] ?? 8));

    if (!$response['ok']) {
        contact_log('reCAPTCHA unreachable (' . $response['transport'] . '): ' . $response['error']);
        return $result('unavailable', 'transport:' . $response['error'], null, $response['transport']);
    }

    $data = json_decode($response['body'], true);
    if (!is_array($data)) {
        contact_log('reCAPTCHA returned a non-JSON body');
        return $result('unavailable', 'malformed-response', null, $response['transport']);
    }

    if (empty($data['success'])) {
        $codes = [];
        if (isset($data['error-codes']) && is_array($data['error-codes'])) {
            $codes = array_map('strval', $data['error-codes']);
        }
        $codeList = $codes ? implode(',', $codes) : 'unknown';

        if (array_intersect($codes, RECAPTCHA_SERVER_SIDE_CODES)) {
            contact_log('reCAPTCHA rejected for a SERVER-SIDE reason (check the secret key): ' . $codeList);
            return $result('unavailable', 'server-side:' . $codeList, null, $response['transport']);
        }
        if (array_intersect($codes, RECAPTCHA_STALE_CODES)) {
            contact_log('reCAPTCHA token stale: ' . $codeList);
            return $result('expired', $codeList, null, $response['transport']);
        }

        contact_log('reCAPTCHA rejected the token: ' . $codeList);
        return $result('bot', $codeList, null, $response['transport']);
    }

    $expectedAction = (string)($cfg['RECAPTCHA_ACTION'] ?? 'contact_submit');
    $actualAction   = (string)($data['action'] ?? '');
    if ($actualAction !== $expectedAction) {
        // A valid token issued for a different action: token replay. Logged with
        // both values because the other cause is a config/JS drift after an edit.
        contact_log(sprintf('reCAPTCHA action mismatch: expected "%s", got "%s"', $expectedAction, $actualAction));
        return $result('bot', 'action-mismatch', null, $response['transport']);
    }

    $score    = isset($data['score']) ? (float)$data['score'] : 0.0;
    $minScore = (float)($cfg['RECAPTCHA_MIN_SCORE'] ?? 0.5);
    if ($score < $minScore) {
        contact_log(sprintf('reCAPTCHA score below threshold: %.2f < %.2f', $score, $minScore));
        return $result('bot', sprintf('low-score:%.2f', $score), $score, $response['transport']);
    }

    return $result('pass', 'ok', $score, $response['transport']);
}
