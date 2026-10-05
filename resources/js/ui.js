/**
 * UI shell (WAVE UI/UX 2.0-12) — solo presentación.
 *
 * - Modal y slide-over accesibles: apertura, cierre con Escape/click en el
 *   fondo, trampa de foco simple y devolución del foco al disparador.
 * - Sin dependencias externas; delegación de eventos en `document` para que
 *   siga funcionando tras un render parcial de Livewire.
 */

const FOCUSABLE = [
    'a[href]',
    'button:not([disabled])',
    'input:not([disabled]):not([type="hidden"])',
    'select:not([disabled])',
    'textarea:not([disabled])',
    '[tabindex]:not([tabindex="-1"])',
].join(',');

const state = {
    open: null,
    trigger: null,
};

function panelOf(overlay) {
    return overlay.querySelector('.ui-modal-panel, .ui-drawer-panel');
}

function openOverlay(id) {
    const overlay = document.getElementById(id);
    if (!overlay) return;

    closeOverlay();

    overlay.hidden = false;
    state.open = overlay;
    state.trigger = document.activeElement;

    const panel = panelOf(overlay);
    const first = panel && panel.querySelector(FOCUSABLE);
    if (first) first.focus();
    else if (panel) {
        panel.setAttribute('tabindex', '-1');
        panel.focus();
    }

    document.body.style.overflow = 'hidden';
}

function closeOverlay() {
    if (!state.open) return;

    state.open.hidden = true;
    state.open = null;
    document.body.style.overflow = '';

    if (state.trigger && document.contains(state.trigger)) {
        state.trigger.focus();
    }
    state.trigger = null;
}

document.addEventListener('click', (event) => {
    const opener = event.target.closest('[data-modal-open], [data-drawer-open]');
    if (opener) {
        event.preventDefault();
        openOverlay(opener.getAttribute('data-modal-open') || opener.getAttribute('data-drawer-open'));
        return;
    }

    if (event.target.closest('[data-modal-close], [data-drawer-close]')) {
        closeOverlay();
    }
});

document.addEventListener('keydown', (event) => {
    if (!state.open) return;

    if (event.key === 'Escape') {
        event.preventDefault();
        closeOverlay();
        return;
    }

    if (event.key !== 'Tab') return;

    const panel = panelOf(state.open);
    if (!panel) return;

    const focusable = Array.from(panel.querySelectorAll(FOCUSABLE)).filter(
        (el) => el.offsetParent !== null
    );
    if (focusable.length === 0) return;

    const first = focusable[0];
    const last = focusable[focusable.length - 1];

    if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
    }
});
