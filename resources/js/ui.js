/**
 * UI shell (WAVE UI/UX 2.0-12) — solo presentación.
 *
 * Modal y slide-over accesibles:
 *  - apertura por data-modal-open / data-drawer-open (páginas Blade) o por
 *    re-render de Livewire (componente con `open` controlado por la propiedad);
 *  - cierre con Escape, clic en el fondo o el botón X;
 *  - trampa de foco simple y devolución del foco al disparador.
 *
 * Sin dependencias externas. Delegación de eventos en `document` y un
 * MutationObserver para que siga funcionando tras un render parcial de Livewire.
 */

const FOCUSABLE = [
    'a[href]',
    'button:not([disabled])',
    'input:not([disabled]):not([type="hidden"])',
    'select:not([disabled])',
    'textarea:not([disabled])',
    '[tabindex]:not([tabindex="-1"])',
].join(',');

const DIALOG_SELECTOR = '.ui-modal, .ui-drawer';

let activeDialog = null;
let returnFocusTo = null;

function visibleDialog() {
    return Array.from(document.querySelectorAll(DIALOG_SELECTOR)).find((el) => !el.hidden) || null;
}

function focusFirst(dialog) {
    const panel = dialog.querySelector('.ui-modal-panel, .ui-drawer-panel') || dialog;
    const target = panel.querySelector(FOCUSABLE);
    if (target) {
        target.focus();
        return;
    }
    panel.setAttribute('tabindex', '-1');
    panel.focus();
}

function sync() {
    const dialog = visibleDialog();

    if (dialog === activeDialog) return;

    if (!dialog) {
        activeDialog = null;
        document.body.style.overflow = '';
        if (returnFocusTo && document.contains(returnFocusTo)) returnFocusTo.focus();
        returnFocusTo = null;
        return;
    }

    if (!activeDialog) {
        returnFocusTo = document.activeElement;
        document.body.style.overflow = 'hidden';
    }

    activeDialog = dialog;

    const panel = dialog.querySelector('.ui-modal-panel, .ui-drawer-panel') || dialog;
    if (!panel.contains(document.activeElement)) focusFirst(dialog);
}

function openOverlay(id) {
    const overlay = document.getElementById(id);
    if (!overlay) return;
    overlay.hidden = false;
    sync();
}

function closeOverlay() {
    if (!activeDialog) return;
    activeDialog.hidden = true;
    sync();
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
    if (!activeDialog) return;

    if (event.key === 'Escape') {
        event.preventDefault();
        // Prioriza el control de cierre declarado en la vista: si lleva
        // wire:click, Livewire también recibe el cierre y la propiedad de
        // estado vuelve a false (no queda desincronizado).
        const closer = activeDialog.querySelector(
            'button[data-modal-close], button[data-drawer-close], [data-modal-close], [data-drawer-close]'
        );
        if (closer) closer.click();
        else closeOverlay();
        return;
    }

    if (event.key !== 'Tab') return;

    const panel = activeDialog.querySelector('.ui-modal-panel, .ui-drawer-panel') || activeDialog;
    const focusable = Array.from(panel.querySelectorAll(FOCUSABLE)).filter(
        (el) => el.offsetParent !== null || el === document.activeElement
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

// Livewire puede abrir o cerrar un diálogo re-renderizando: el observer mantiene
// el estado del shell (foco, scroll) alineado con el DOM real.
if (typeof MutationObserver !== 'undefined') {
    let queued = false;
    const observer = new MutationObserver(() => {
        if (queued) return;
        queued = true;
        requestAnimationFrame(() => {
            queued = false;
            sync();
        });
    });

    observer.observe(document.body, {
        childList: true,
        subtree: true,
        attributes: true,
        attributeFilter: ['hidden'],
    });
}
