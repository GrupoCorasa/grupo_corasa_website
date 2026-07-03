/*!
 * Floating WhatsApp button — progressive-enhancement layer.
 *
 * The button itself is plain HTML/CSS and works without this file. This script
 * only *enhances* it:
 *   1. Rewrites the wa.me link with a page-aware, pre-filled Spanish message.
 *   2. Shows a dismissible greeting bubble a few seconds after load
 *      (dismissal remembered in localStorage so repeat visitors aren't nagged).
 *   3. Pushes a GTM dataLayer event on click so WhatsApp leads are measurable.
 *
 * Fails silently and safely if anything is unavailable.
 */
(function () {
    'use strict';

    var BUBBLE_DELAY_MS   = 3500;                 // wait before the bubble appears
    var DISMISS_STORAGE   = 'waFabBubbleDismissed';
    var DISMISS_DAYS      = 7;                     // don't re-show for repeat visitors within N days
    var DISMISS_MS        = DISMISS_DAYS * 24 * 60 * 60 * 1000;

    /* Map a URL path fragment -> friendly product-line name (for the message & bubble). */
    var SECTIONS = {
        'metrologia':            'Metrología',
        'mro':                   'MRO',
        'filtracion-industrial': 'Filtración Industrial',
        'herramientas-de-corte': 'Herramientas de Corte',
        'abrasivos':             'Abrasivos'
    };

    function ready(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn, { once: true });
        } else {
            fn();
        }
    }

    /* localStorage can throw (private mode / disabled cookies) — guard every access. */
    function safeGet(key) {
        try { return window.localStorage.getItem(key); } catch (e) { return null; }
    }
    function safeSet(key, val) {
        try { window.localStorage.setItem(key, val); } catch (e) { /* ignore */ }
    }

    function detectSection() {
        var path = (window.location.pathname || '').toLowerCase();
        for (var key in SECTIONS) {
            if (SECTIONS.hasOwnProperty(key) && path.indexOf('/' + key) !== -1) {
                return { key: key, name: SECTIONS[key] };
            }
        }
        return { key: 'home', name: null };
    }

    function buildMessage(section) {
        if (section.name) {
            return '¡Hola! Me interesa su línea de ' + section.name +
                   '. ¿Me pueden dar más información?';
        }
        return '¡Hola! Me gustaría recibir más información sobre sus productos y servicios.';
    }

    function buildWaUrl(phone, message) {
        return 'https://wa.me/' + phone + '?text=' + encodeURIComponent(message);
    }

    function pushEvent(section, source) {
        try {
            window.dataLayer = window.dataLayer || [];
            window.dataLayer.push({
                event: 'whatsapp_click',
                wa_context: section.key,
                wa_source: source
            });
        } catch (e) { /* ignore */ }
    }

    /* Upgrade any inline WhatsApp CTA link (e.g. the Marcas-section button).
       The link already works from its baked static href; this just keeps the
       phone number DRY (from data-wa-phone) and adds click tracking. A per-CTA
       `data-wa-message` wins over the generic section message. */
    function enhanceCtas(phone, section) {
        var ctas = document.querySelectorAll('[data-wa-cta]');
        for (var i = 0; i < ctas.length; i++) {
            (function (cta) {
                var custom = cta.getAttribute('data-wa-message');
                var msg = (custom && custom.trim()) ? custom : buildMessage(section);
                cta.setAttribute('href', buildWaUrl(phone, msg));
                if (!cta.getAttribute('target')) { cta.setAttribute('target', '_blank'); }
                if (!cta.getAttribute('rel'))    { cta.setAttribute('rel', 'noopener'); }
                cta.addEventListener('click', function () { pushEvent(section, 'marcas_cta'); });
            })(ctas[i]);
        }
    }

    function bubbleSuppressed() {
        var raw = safeGet(DISMISS_STORAGE);
        if (!raw) { return false; }
        var when = parseInt(raw, 10);
        if (!when) { return false; }
        return (Date.now() - when) < DISMISS_MS;
    }

    ready(function () {
        var widget = document.querySelector('.wa-widget');
        if (!widget) { return; }

        var fab = widget.querySelector('.wa-fab');
        if (!fab) { return; }

        var phone   = widget.getAttribute('data-wa-phone') || '526624197556';
        var section = detectSection();
        var message = buildMessage(section);
        var waUrl   = buildWaUrl(phone, message);

        /* 1) Upgrade the static link with the contextual, pre-filled message. */
        fab.setAttribute('href', waUrl);
        fab.setAttribute('title', 'Escríbenos por WhatsApp');

        /* 3) Track clicks on the button. */
        fab.addEventListener('click', function () { pushEvent(section, 'fab'); });

        /* 3b) Enhance inline WhatsApp CTAs (e.g. the Marcas-section button).
               Placed before the bubble's early-return so it always runs. */
        enhanceCtas(phone, section);

        /* 2) Greeting bubble. */
        if (bubbleSuppressed()) { return; }

        var bubbleTitle = section.name
            ? '¿Buscas ' + section.name + '?'
            : '¿Podemos ayudarte?';
        var bubbleText = 'Escríbenos por WhatsApp y te atendemos al instante. 👋';

        var bubble = document.createElement('div');
        bubble.className = 'wa-widget__bubble';
        /* Passive marketing message: intentionally NOT role="dialog" (it isn't modal or
           focus-managed). Its link and close button carry their own semantics. */

        var close = document.createElement('button');
        close.type = 'button';
        close.className = 'wa-widget__close';
        close.setAttribute('aria-label', 'Cerrar');
        close.innerHTML = '&times;';

        var link = document.createElement('a');
        link.className = 'wa-widget__bubble-link';
        link.href = waUrl;
        link.target = '_blank';
        link.rel = 'noopener';

        var titleEl = document.createElement('span');
        titleEl.className = 'wa-widget__bubble-title';
        titleEl.textContent = bubbleTitle;

        var textEl = document.createElement('span');
        textEl.className = 'wa-widget__bubble-text';
        textEl.textContent = bubbleText;

        link.appendChild(titleEl);
        link.appendChild(textEl);
        bubble.appendChild(close);
        bubble.appendChild(link);
        widget.insertBefore(bubble, fab);

        function hideBubble(remember) {
            bubble.classList.remove('is-visible');
            if (remember) { safeSet(DISMISS_STORAGE, String(Date.now())); }
        }

        close.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            hideBubble(true);
        });
        link.addEventListener('click', function () {
            pushEvent(section, 'bubble');
            hideBubble(true);
        });

        var timer = window.setTimeout(function () {
            bubble.classList.add('is-visible');
        }, BUBBLE_DELAY_MS);

        /* If the user opens the FAB before the bubble shows, don't pop it afterwards. */
        fab.addEventListener('click', function () {
            window.clearTimeout(timer);
            hideBubble(true);
        });

        /* Keyboard affordance: Escape dismisses the greeting bubble while it's visible. */
        document.addEventListener('keydown', function (e) {
            if ((e.key === 'Escape' || e.key === 'Esc') && bubble.classList.contains('is-visible')) {
                hideBubble(true);
            }
        });
    });
})();
