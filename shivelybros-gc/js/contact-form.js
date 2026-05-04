/**
 * Shively Bros contact form handler.
 *
 * Wires #contact-form to contact.php via fetch, gates submission on
 * reCAPTCHA v3 token, and fires a Google Ads conversion event on success.
 *
 * Config comes from window.__SHIVELY_FORM_CFG (set inline in index.html
 * just above this script tag) so swapping placeholders later is a
 * single-file edit.
 */
(function () {
    'use strict';

    var CFG = window.__SHIVELY_FORM_CFG || {};

    document.addEventListener('DOMContentLoaded', function () {
        var form = document.getElementById('contact-form');
        if (!form) return;

        var submitBtn   = form.querySelector('button[type="submit"]');
        var submitLabel = submitBtn ? submitBtn.innerHTML : '';
        var alertOk     = document.getElementById('contact-form-success');
        var alertErr    = document.getElementById('contact-form-error');
        var tokenInput  = form.querySelector('input[name="g-recaptcha-response"]');
        var reqField    = form.querySelector('#cf-requerimiento');
        var reqCount    = document.getElementById('cf-req-count');

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
                        console.error('[contact-form] reCAPTCHA error:', err);
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
                        showSuccess('¡Gracias! Su mensaje ha sido enviado. Le contactaremos a la brevedad.');
                        form.reset();
                        if (reqCount) reqCount.textContent = '0';
                        fireConversion();
                    } else {
                        showError((data && data.error) || 'No fue posible enviar el formulario.');
                    }
                })
                .catch(function (err) {
                    console.error('[contact-form] network error:', err);
                    showError('Error de red. Verifique su conexión e intente de nuevo.');
                })
                .then(function () {
                    setSubmitting(false);
                });
        }

        function fireConversion() {
            if (typeof window.gtag !== 'function') return;
            if (!CFG.adsConversionId || /^TODO_/.test(CFG.adsConversionId)) return;
            window.gtag('event', 'conversion', {
                send_to: CFG.adsConversionId,
                event_callback: function () { /* no-op; UI already updated */ }
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
    });
})();
