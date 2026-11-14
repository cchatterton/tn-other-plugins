const assert = require('assert');
const fs = require('fs');
const vm = require('vm');

const listeners = {};
const box = { checked: true, disabled: false, value: 'first/main.php', dataset: { pluginName: 'First' } };
const updateForm = {
    addEventListener: (type, listener) => { listeners[`update-${type}`] = listener; },
    querySelectorAll: () => [box],
};
const checkForm = { addEventListener: (type, listener) => { listeners[`check-${type}`] = listener; } };
const checkButton = { closest: () => checkForm, focus() {} };
const title = { textContent: '', focus() {} };
const message = { textContent: '' };
const spinner = { hidden: false };
const progressArea = { hidden: true };
const progress = { max: 0, value: 0 };
const list = { children: [], replaceChildren() { this.children = []; }, append(item) { this.children.push(item); } };
const failures = { hidden: true, open: false, querySelector: () => list };
const close = { disabled: true, focus() {}, addEventListener: (type, listener) => { listeners[`close-${type}`] = listener; } };
const dialog = {
    open: false,
    showModal() { this.open = true; },
    close() { this.open = false; listeners['dialog-close'](); },
    setAttribute() {},
    querySelector: () => spinner,
    addEventListener: (type, listener) => { listeners[`dialog-${type}`] = listener; },
};
const nodes = {
    'tnop-dialog': dialog,
    'tnop-update-form': updateForm,
    'tnop-update-selected': { disabled: false },
    'tnop-select-all': { checked: false, indeterminate: false },
    'tnop-dialog-title': title,
    'tnop-dialog-message': message,
    'tnop-progress-area': progressArea,
    'tnop-progress': progress,
    'tnop-failures': failures,
    'tnop-close': close,
};
let resolveFetch;
let assigned = '';
const context = {
    URL,
    URLSearchParams,
    document: {
        activeElement: checkButton,
        querySelector: () => checkButton,
        getElementById: id => nodes[id],
        createElement: () => ({ textContent: '' }),
    },
    window: { location: {
        href: 'https://example.test/wp-admin/plugins.php?page=tn-other-plugins',
        assign: url => { assigned = String(url); },
    } },
    tnopAdmin: { url: 'https://example.test/wp-admin/admin-ajax.php', updateNonce: 'test' },
    fetch: () => new Promise(resolve => { resolveFetch = resolve; }),
};
vm.runInNewContext(fs.readFileSync('tn-other-plugins/assets/admin.js', 'utf8'), context);

(async () => {
    const pending = listeners['update-submit']({ preventDefault() {} });
    assert.equal(dialog.open, true, 'The modal must open before the update request completes.');
    assert.equal(title.textContent, 'Updating plugins');
    assert.equal(close.disabled, true);
    resolveFetch({ ok: true, text: async () => JSON.stringify({ success: true, data: {
        item: { name: 'First', status: 'success', message: 'Updated to 2.0.' },
    } }) });
    await pending;
    assert.equal(title.textContent, 'Updates complete');
    assert.equal(progress.value, 1);
    assert.equal(close.disabled, false);
    listeners['close-click']();
    assert.match(assigned, /tab=installed/);
    console.log('Bulk update modal passed.');
})().catch(error => { console.error(error); process.exitCode = 1; });
