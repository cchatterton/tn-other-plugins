(() => {
    'use strict';
    const form = document.querySelector('.tnop-wrap [data-tnop-check]')?.closest('form');
    const dialog = document.getElementById('tnop-dialog');
    if (!form || !dialog || typeof dialog.showModal !== 'function') return;

    const updateForm = document.getElementById('tnop-update-form');
    const updateSelected = document.getElementById('tnop-update-selected');
    const selectAll = document.getElementById('tnop-select-all');
    const selection = () => {
        const boxes = [...updateForm.querySelectorAll('[name="checked[]"]:not(:disabled)')];
        const chosen = boxes.filter(box => box.checked);
        updateSelected.disabled = chosen.length === 0;
        if (selectAll) {
            selectAll.checked = boxes.length > 0 && chosen.length === boxes.length;
            selectAll.indeterminate = chosen.length > 0 && chosen.length < boxes.length;
        }
    };
    updateForm?.addEventListener('change', event => {
        if (event.target === selectAll) {
            updateForm.querySelectorAll('[name="checked[]"]:not(:disabled)').forEach(box => { box.checked = selectAll.checked; });
        }
        selection();
    });

    const title = document.getElementById('tnop-dialog-title');
    const message = document.getElementById('tnop-dialog-message');
    const activity = dialog.querySelector('.tnop-activity .spinner');
    const close = document.getElementById('tnop-close');
    let running = false;
    let reload = false;
    let opener = null;

    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (running) return;
        running = true;
        reload = false;
        opener = document.activeElement;
        title.textContent = 'Checking for updates';
        message.textContent = 'Checking WordPress and plugin sources…';
        activity.hidden = false;
        close.disabled = true;
        dialog.setAttribute('aria-busy', 'true');
        dialog.showModal();
        title.focus();

        try {
            const body = new URLSearchParams({ action: 'tnop_check', nonce: tnopAdmin.nonce });
            const response = await fetch(tnopAdmin.url, { method: 'POST', credentials: 'same-origin', body });
            const result = await response.json();
            if (!response.ok || !result.success) throw new Error(result.data?.message || 'The update check could not be completed.');
            const count = Number(result.data.updates) || 0;
            title.textContent = 'Check complete';
            message.textContent = `${count} update${count === 1 ? '' : 's'} available. Close to view the results.`;
            reload = true;
        } catch (error) {
            title.textContent = 'Check incomplete';
            message.textContent = error.message || 'The update check failed. Please try again.';
        } finally {
            running = false;
            activity.hidden = true;
            dialog.setAttribute('aria-busy', 'false');
            close.disabled = false;
            close.focus();
        }
    });

    close.addEventListener('click', () => dialog.close());
    dialog.addEventListener('cancel', event => { if (running) event.preventDefault(); });
    dialog.addEventListener('close', () => {
        if (reload) window.location.assign(new URL('plugins.php?page=tn-other-plugins&tab=installed', window.location.href));
        else opener?.focus();
    });
})();
