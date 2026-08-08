/**
 * Contact-form handler for the Shively Bros landing pages.
 *
 * Wires every form with class .js-contact-form to its sibling contact.php via
 * fetch, gates submission on a per-submit reCAPTCHA v3 token, and redirects to
 * the shared thank-you page on success.
 *
 * Each form declares its own scoped alert / counter elements:
 *   data-success-id="..."  -> id of the success alert div
 *   data-error-id="..."    -> id of the error alert div
 *   <... data-req-count>   -> character counter span inside the form
 *   data-endpoint="..."    -> optional endpoint override
 *
 * Hard-won behaviours, each from a real failure:
 *
 *   Never assume the response is JSON. A server-side fatal returns a zero-byte
 *   body; res.json() throws on it, and the old code reported "Respuesta
 *   inesperada del servidor" with no way to tell an outage from a typo. Any
 *   unparseable response now surfaces its HTTP status so it is actionable.
 *
 *   Validate before spending a token. The forms are novalidate, so an
 *   incomplete one used to burn a reCAPTCHA token and a round-trip just to come
 *   back 422. Checking first is faster and puts the cursor on the bad field.
 *
 *   Retry once on an expired token. v3 tokens live 120 seconds; a slow network
 *   or a backgrounded tab can outlast that. Re-issuing silently beats telling a
 *   real customer their security check failed.
 *
 *   Always time out. Without an abort the button can spin forever behind a
 *   hung connection, and the visitor has no idea whether they submitted.
 */
(function () {
    'use strict';

    var CFG          = window.__SHIVELY_FORM_CFG || {};
    var REQUEST_TIMEOUT_MS = 20000;

    document.addEventListener('DOMContentLoaded', function () {
        var forms = document.querySelectorAll('.js-contact-form');
        if (!forms.length) return;
        Array.prototype.forEach.call(forms, wire);
    });

    function wire(form) {
        var submitBtn   = form.querySelector('button[type="submit"]');
        var submitLabel = submitBtn ? submitBtn.innerHTML : '';
        var alertOk     = document.getElementById(form.dataset.successId || '');
        var alertErr    = document.getElementById(form.dataset.errorId   || '');
        var tokenInput  = form.querySelector('input[name="g-recaptcha-response"]');
        var reqField    = form.querySelector('textarea[name="requerimiento"]');
        var reqCount    = form.querySelector('[data-req-count]');
        var endpoint    = form.dataset.endpoint || 'contact.php';
        var busy        = false;

        if (alertErr) alertErr.setAttribute('role', 'alert');
        if (alertOk)  alertOk.setAttribute('role', 'status');

        if (reqField && reqCount) {
            var updateCount = function () { reqCount.textContent = String(reqField.value.length); };
            reqField.addEventListener('input', updateCount);
            updateCount();
        }

        // Clear a field's error styling as soon as the visitor corrects it.
        form.addEventListener('input', function (e) {
            if (e.target && e.target.classList) e.target.classList.remove('is-invalid');
        });

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            if (busy) return;                       // guard against double submits

            hide(alertOk);
            hide(alertErr);
            clearFieldErrors();

            // Cheap client-side pass first — no token, no request.
            if (typeof form.checkValidity === 'function' && !form.checkValidity()) {
                var firstInvalid = form.querySelector(':invalid');
                if (firstInvalid) {
                    firstInvalid.classList.add('is-invalid');
                    firstInvalid.focus();
                }
                showError('Por favor complete los campos obligatorios antes de enviar.');
                return;
            }

            if (!CFG.recaptchaSiteKey || /^TODO_/.test(CFG.recaptchaSiteKey)) {
                showError('La protección anti-bot aún no está configurada. Intente más tarde.');
                return;
            }
            if (!window.grecaptcha || typeof window.grecaptcha.ready !== 'function') {
                showError('No se pudo cargar la verificación de seguridad. Recargue la página e intente de nuevo.');
                return;
            }

            setSubmitting(true);
            submitWithFreshToken(false);
        });

        /**
         * Obtain a token, then post. `isRetry` guards the single automatic
         * retry allowed for an expired token.
         */
        function submitWithFreshToken(isRetry) {
            window.grecaptcha.ready(function () {
                window.grecaptcha
                    .execute(CFG.recaptchaSiteKey, { action: 'contact_submit' })
                    .then(function (token) {
                        if (tokenInput) tokenInput.value = token;
                        return postForm(isRetry);
                    })
                    .catch(function (err) {
                        console.error('[contact-form] reCAPTCHA error:', err);
                        showError('No se pudo verificar la seguridad. Intente de nuevo.');
                        setSubmitting(false);
                    });
            });
        }

        function postForm(isRetry) {
            var controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
            var timer = window.setTimeout(function () {
                if (controller) controller.abort();
            }, REQUEST_TIMEOUT_MS);

            var options = { method: 'POST', body: new FormData(form), credentials: 'same-origin' };
            if (controller) options.signal = controller.signal;

            return fetch(endpoint, options)
                .then(function (res) {
                    window.clearTimeout(timer);
                    // Read as text first: an empty or HTML body must not throw.
                    return res.text().then(function (raw) {
                        var data = null;
                        try { data = raw ? JSON.parse(raw) : null; } catch (parseErr) { data = null; }
                        return { status: res.status, data: data, raw: raw };
                    });
                })
                .then(function (res) {
                    if (res.data && res.data.ok) return handleSuccess();

                    // Unparseable body — a server-side crash or a proxy error page.
                    if (!res.data) {
                        console.error('[contact-form] non-JSON response', res.status, res.raw.slice(0, 200));
                        showError(
                            'El servidor respondió de forma inesperada (error ' + res.status + '). ' +
                            'Por favor intente de nuevo en unos minutos o escríbanos por WhatsApp.'
                        );
                        return setSubmitting(false);
                    }

                    // A stale token is almost always a real person on a slow
                    // connection. Re-issue once before showing them an error.
                    if (res.data.code === 'E_RECAPTCHA_EXPIRED' && !isRetry) {
                        return submitWithFreshToken(true);
                    }

                    if (Array.isArray(res.data.fields)) markFieldErrors(res.data.fields);
                    showError(res.data.error || 'No fue posible enviar el formulario.');
                    setSubmitting(false);
                })
                .catch(function (err) {
                    window.clearTimeout(timer);
                    if (err && err.name === 'AbortError') {
                        showError('La solicitud tardó demasiado. Verifique su conexión e intente de nuevo.');
                    } else {
                        console.error('[contact-form] network error:', err);
                        showError('Error de red. Verifique su conexión e intente de nuevo.');
                    }
                    setSubmitting(false);
                });
        }

        function handleSuccess() {
            showSuccess('¡Gracias! Redirigiendo…');
            form.reset();
            if (reqCount) reqCount.textContent = '0';
            // Brief pause so the session cookie set by contact.php commits and
            // the visitor sees the confirmation before the navigation fires.
            window.setTimeout(function () {
                window.location.assign('/shivelybros-gc/gracias.php');
            }, 1200);
        }

        function markFieldErrors(names) {
            names.forEach(function (name) {
                var field = form.querySelector('[name="' + name + '"]');
                if (field && field.classList) field.classList.add('is-invalid');
            });
            var first = form.querySelector('.is-invalid');
            if (first && typeof first.focus === 'function') first.focus();
        }

        function clearFieldErrors() {
            Array.prototype.forEach.call(form.querySelectorAll('.is-invalid'), function (el) {
                el.classList.remove('is-invalid');
            });
        }

        function setSubmitting(isSubmitting) {
            busy = isSubmitting;
            if (!submitBtn) return;
            submitBtn.disabled  = isSubmitting;
            submitBtn.innerHTML = isSubmitting
                ? '<i class="fa fa-spinner fa-spin"></i> Enviando…'
                : submitLabel;
        }

        function showSuccess(msg) {
            if (!alertOk) return;
            alertOk.textContent = msg;
            alertOk.style.display = 'block';
            alertOk.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }

        function showError(msg) {
            // Every page ships both alert elements; log rather than fall back to
            // a blocking alert() if a future template ever drops one.
            if (!alertErr) { console.error('[contact-form]', msg); return; }
            alertErr.textContent = msg;
            alertErr.style.display = 'block';
            alertErr.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }

        function hide(el) {
            if (el) el.style.display = 'none';
        }
    }
})();
