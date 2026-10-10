/*
 * filament-spid: behaviour of the SPID button component.
 *
 * Loaded as a script file (Filament asset), never inline, so a strict
 * Content-Security-Policy only needs to allow the application's own scripts.
 * The AgID button script (spid-sp-access-button.min.js) starts with
 * `jQuery&&...`, so jQuery is loaded first when the page has none; both are
 * loaded once per page however many buttons it renders. Each button is
 * initialised once: rerenders and Livewire navigation only pick up new ones.
 */
(function (root, factory) {
    var api = factory();

    if (typeof module === 'object' && module.exports) {
        module.exports = api;
    }

    if (!root || !root.document) {
        return;
    }

    // The script tag can run again (several buttons, Livewire navigation):
    // keep the first instance and its listeners.
    if (!root.FilamentSpidButton) {
        root.FilamentSpidButton = api;
        api.listen(root);
    }

    root.FilamentSpidButton.boot(root);
})(typeof window !== 'undefined' ? window : null, function () {
    'use strict';

    /**
     * Fisher–Yates: a uniformly random permutation of a copy of items.
     *
     * @param {Array} items
     * @param {function(): number} [random] returns a number in [0, 1)
     * @returns {Array}
     */
    function shuffle(items, random) {
        var result = items.slice();
        var next = random || Math.random;

        for (var i = result.length - 1; i > 0; i--) {
            var j = Math.floor(next() * (i + 1));
            var swap = result[i];
            result[i] = result[j];
            result[j] = swap;
        }

        return result;
    }

    /**
     * Load a script once per page; run onload when it is available and
     * onerror when it failed (now or earlier).
     */
    function loadScript(doc, id, src, onload, onerror) {
        var script = doc.getElementById(id);

        if (script && script.getAttribute('data-loaded')) {
            onload();
            return;
        }

        if (script && script.getAttribute('data-failed')) {
            onerror();
            return;
        }

        if (!script) {
            script = doc.createElement('script');
            script.id = id;
            script.src = src;
            script.async = false;
            script.addEventListener('load', function () { script.setAttribute('data-loaded', '1'); });
            script.addEventListener('error', function () { script.setAttribute('data-failed', '1'); });
            doc.head.appendChild(script);
        }

        script.addEventListener('load', onload);
        script.addEventListener('error', onerror);
    }

    function children(list, className) {
        return Array.prototype.filter.call(list.children, function (item) {
            return item.classList.contains(className);
        });
    }

    /**
     * Wire one button: shuffle its IdPs, submit the chosen one with its own
     * form, keep aria-expanded in sync and close the menu on Escape.
     */
    function initButton(win, wrapper) {
        var $ = win.jQuery;
        var trigger = wrapper.querySelector('[spid-idp-button]');
        var popup = wrapper.querySelector('.spid-idp-button');
        var menu = wrapper.querySelector('.spid-idp-button-menu');
        var input = wrapper.querySelector('input[name="provider"]');

        if (!trigger || !popup || !menu || !input) {
            return;
        }

        // Shuffle only the IdPs, as the AgID guidelines require; the
        // information links stay last, in their order.
        var idps = children(menu, 'spid-idp-button-link');
        var links = children(menu, 'spid-idp-support-link');

        shuffle(idps).forEach(function (item) { menu.appendChild(item); });
        links.forEach(function (item) { menu.appendChild(item); });

        idps.forEach(function (item) {
            // Runs before the submit button's default action.
            item.addEventListener('click', function () {
                input.value = item.getAttribute('data-idp');
            });
        });

        // The AgID script triggers jQuery "show"/"hide" on the popup.
        $(popup).on('show', function () {
            trigger.setAttribute('aria-expanded', 'true');
            var first = menu.querySelector('.spid-idp-button-link button, .spid-idp-support-link a');
            if (first) {
                first.focus();
            }
        });
        $(popup).on('hide', function () {
            trigger.setAttribute('aria-expanded', 'false');
        });

        trigger.addEventListener('keydown', function (event) {
            // role="button": Space activates it like Enter.
            if (event.key === ' ') {
                event.preventDefault();
                trigger.click();
            }
        });

        wrapper.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && trigger.classList.contains('spid-idp-button-open')) {
                $(trigger).spidIDPButton('hide');
                trigger.focus();
            }
        });
    }

    function pending(doc) {
        return Array.prototype.slice.call(doc.querySelectorAll('[data-spid-button]:not([data-spid-initialized])'));
    }

    /** Initialise every button on the page that is not initialised yet. */
    function boot(win) {
        var doc = win.document;
        var wrappers = pending(doc);

        if (wrappers.length === 0) {
            return;
        }

        var first = wrappers[0];

        function failed() {
            pending(doc).forEach(function (wrapper) {
                wrapper.setAttribute('data-spid-error', '1');
                var message = wrapper.querySelector('[data-spid-load-error]');
                if (message) {
                    message.hidden = false;
                }
            });
            if (win.console) {
                win.console.error('filament-spid: the SPID button scripts could not be loaded; run php artisan vendor:publish --tag=spid-assets and php artisan filament:assets.');
            }
        }

        function ready() {
            pending(doc).forEach(function (wrapper) {
                wrapper.setAttribute('data-spid-initialized', '1');
                initButton(win, wrapper);
            });
        }

        function withButtonScript() {
            loadScript(doc, 'spid-sp-access-button', first.getAttribute('data-button-src'), ready, failed);
        }

        if (typeof win.jQuery === 'undefined') {
            loadScript(doc, 'spid-jquery', first.getAttribute('data-jquery-src'), withButtonScript, failed);
        } else {
            withButtonScript();
        }
    }

    function listen(win) {
        win.document.addEventListener('DOMContentLoaded', function () { boot(win); });
        win.document.addEventListener('livewire:navigated', function () { boot(win); });
    }

    return { shuffle: shuffle, boot: boot, listen: listen };
});
