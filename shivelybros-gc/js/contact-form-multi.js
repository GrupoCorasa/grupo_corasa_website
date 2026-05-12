/**
 * Contact-form handler for shivelybros-gc/index.html.
 *
 * Wires every form with class .js-contact-form to contact.php via fetch,
 * gates submission on a per-submit reCAPTCHA v3 token, and fires a Google
 * Ads conversion event on success.
 *
 * Each form must declare its own scoped alert / counter elements via data
 * attributes:
 *   data-success-id="..."  -> id of the success alert div
 *   data-error-id="..."    -> id of the error alert div
 *   <... data-req-count>   -> the character counter span inside the form
 */
(function () {
    'use strict';

    var CFG = window.__SHIVELY_FORM_CFG || {};

    document.addEventListener('DOMContentLoaded', function () {
        var forms = document.querySelectorAll('.js-contact-form');
        if (!forms.length) return;
        forms.forEach(wire);
    });

    function wire(form) {
        var submitBtn   = form.querySelector('button[type="submit"]');
        var submitLabel = submitBtn ? submitBtn.innerHTML : '';
        var alertOk     = document.getElementById(form.dataset.successId || '');
        var alertErr    = document.getElementById(form.dataset.errorId   || '');
        var tokenInput  = form.querySelector('input[name="g-recaptcha-response"]');
        var reqField    = form.querySelector('textarea[name="requerimiento"]');
        var reqCount    = form.querySelector('[data-req-count]');

        if (reqField && reqCount) {
            var updateCount = function () { reqCount.textContent = String(reqField.value.length); };
            reqField.addEventListener('input', updateCount);
            updateCount();
        }

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            hide(alertOk);
            hide(alertErr);

            if (!CFG.recaptchaSiteKey || /^TODO_/.test(CFG.recaptchaSiteKey)) {
                showError('La protección anti-bot aún no está configurada. Intente más tarde.');
                return;
            }
            if (typeof window.grecaptcha === 'undefined' || typeof window.grecaptcha.ready !== 'function') {
                showError('No se pudo cargar la verificación de seguridad. Recargue la página e intente de nuevo.');
                return;
            }

            setSubmitting(true);

            window.grecaptcha.ready(function () {
                window.grecaptcha
                    .execute(CFG.recaptchaSiteKey, { action: 'contact_submit' })
                    .then(function (token) {
                        if (tokenInput) tokenInput.value = token;
                        return submitForm();
                    })
                    .catch(function (err) {
                        console.error('[contact-form-multi] reCAPTCHA error:', err);
                        showError('No se pudo verificar la seguridad. Intente de nuevo.');
                        setSubmitting(false);
                    });
            });
        });

        function submitForm() {
            return fetch('contact.php', {
                method: 'POST',
                body: new FormData(form),
                credentials: 'same-origin'
            })
                .then(function (res) {
                    return res.json().catch(function () {
                        return { ok: false, error: 'Respuesta inesperada del servidor.' };
                    });
                })
                .then(function (data) {
                    if (data && data.ok) {
                        showSuccess('¡Gracias! Redirigiendo…');
                        form.reset();
                        if (reqCount) reqCount.textContent = '0';
                        // Brief pause so the session cookie set by contact.php
                        // commits in the browser and the user sees confirmation
                        // before the navigation fires.
                        window.setTimeout(function () {
                            window.location.assign('/shivelybros-gc/gracias.php');
                        }, 1200);
                    } else {
                        showError((data && data.error) || 'No fue posible enviar el formulario.');
                    }
                })
                .catch(function (err) {
                    console.error('[contact-form-multi] network error:', err);
                    showError('Error de red. Verifique su conexión e intente de nuevo.');
                })
                .then(function () {
                    setSubmitting(false);
                });
        }

        function setSubmitting(isSubmitting) {
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
            if (!alertErr) return;
            alertErr.textContent = msg;
            alertErr.style.display = 'block';
        }

        function hide(el) {
            if (el) el.style.display = 'none';
        }
    }
})();
