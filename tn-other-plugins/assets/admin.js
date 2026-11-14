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
    const progressArea = document.getElementById('tnop-progress-area');
    const progress = document.getElementById('tnop-progress');
    const failures = document.getElementById('tnop-failures');
    const close = document.getElementById('tnop-close');
    let running = false;
    let reload = false;
    let opener = null;

    const open = (heading, status) => {
        running = true;
        reload = false;
        opener = document.activeElement;
        title.textContent = heading;
        message.textContent = status;
        activity.hidden = false;
        progressArea.hidden = true;
        failures.hidden = true;
        failures.open = false;
        failures.querySelector('ul').replaceChildren();
        close.disabled = true;
        dialog.setAttribute('aria-busy', 'true');
        dialog.showModal();
        title.focus();
    };
    const finish = () => {
        running = false;
        activity.hidden = true;
        dialog.setAttribute('aria-busy', 'false');
        close.disabled = false;
        close.focus();
    };
    const call = async body => {
        const response = await fetch(tnopAdmin.url, { method: 'POST', credentials: 'same-origin', body });
        const raw = await response.text();
        let result;
        try { result = JSON.parse(raw); }
        catch (_) { throw new Error('WordPress returned an invalid response. Check the Plugins screen before trying again.'); }
        if (!response.ok || !result.success) throw new Error(result.data?.message || 'The request could not be completed.');
        return result.data;
    };

    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (running) return;
        open('Checking for updates', 'Checking WordPress and plugin sources…');

        try {
            const body = new URLSearchParams({ action: 'tnop_check', nonce: tnopAdmin.nonce });
            const result = await call(body);
            const count = Number(result.updates) || 0;
            title.textContent = 'Check complete';
            message.textContent = `${count} update${count === 1 ? '' : 's'} available. Close to view the results.`;
            reload = true;
        } catch (error) {
            title.textContent = 'Check incomplete';
            message.textContent = error.message || 'The update check failed. Please try again.';
        } finally {
            finish();
        }
    });

    updateForm?.addEventListener('submit', async event => {
        event.preventDefault();
        if (running) return;
        const boxes = [...updateForm.querySelectorAll('[name="checked[]"]:checked:not(:disabled)')];
        if (!boxes.length) return;
        open('Updating plugins', `Updating ${boxes.length} selected plugin${boxes.length === 1 ? '' : 's'}… WordPress may briefly enter maintenance mode.`);
        progressArea.hidden = false;
        progress.max = boxes.length;
        progress.value = 0;
        reload = true;
        const failuresList = [];
        let succeeded = 0;
        try {
            for (const [index, box] of boxes.entries()) {
                const name = box.dataset.pluginName || box.value;
                message.textContent = `Updating ${name} (${index + 1} of ${boxes.length})… WordPress may briefly enter maintenance mode.`;
                const body = new URLSearchParams({ action: 'tnop_update_selected', nonce: tnopAdmin.updateNonce, plugin: box.value });
                try {
                    const result = await call(body);
                    if (result.item.status === 'success') succeeded++;
                    else failuresList.push(result.item);
                } catch (error) {
                    failuresList.push({ name, message: error.message || 'Update failed.' });
                }
                progress.value = index + 1;
            }
            title.textContent = failuresList.length ? 'Finished with errors' : 'Updates complete';
            message.textContent = `${succeeded} plugin${succeeded === 1 ? '' : 's'} updated${failuresList.length ? `, ${failuresList.length} failed` : ''}. Close to refresh the list.`;
            failuresList.forEach(item => {
                const li = document.createElement('li');
                li.textContent = `${item.name}: ${item.message}`;
                failures.querySelector('ul').append(li);
            });
            failures.hidden = failuresList.length === 0;
        } catch (error) {
            title.textContent = 'Update incomplete';
            message.textContent = `${error.message || 'The update request failed.'} Close to refresh the list and verify the installed versions.`;
        } finally {
            finish();
        }
    });

    close.addEventListener('click', () => dialog.close());
    dialog.addEventListener('cancel', event => { if (running) event.preventDefault(); });
    dialog.addEventListener('close', () => {
        if (reload) window.location.assign(new URL('plugins.php?page=tn-other-plugins&tab=installed', window.location.href));
        else opener?.focus();
    });
})();
