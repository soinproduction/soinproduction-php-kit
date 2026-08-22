'use strict';

const assert = require('node:assert');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

function control() {
    return {
        disabled: false,
        hidden: false,
        textContent: '',
        className: '',
        listeners: {},
        classList: {add() {}},
        addEventListener(name, callback) {
            this.listeners[name] = callback;
        },
        querySelector() {
            return this;
        },
    };
}

async function settle() {
    await new Promise((resolve) => setImmediate(resolve));
    await new Promise((resolve) => setImmediate(resolve));
}

(async () => {
    const listeners = {};
    const controls = new Map();
    let requests = 0;

    const root = {
        querySelector(selector) {
            if (!controls.has(selector)) {
                controls.set(selector, control());
            }
            return controls.get(selector);
        },
    };
    const document = {
        querySelector(selector) {
            return selector === '[data-sp-deployment-manager]' ? root : null;
        },
        addEventListener(name, callback) {
            listeners[name] = callback;
        },
    };
    const window = {
        clearInterval() {},
        confirm() { return true; },
        setInterval() { return 1; },
    };
    const snapshots = [
        {
            installed: 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
            installed_short: 'aaaaaaaaaaaa',
            remote: {sha: 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', short: 'aaaaaaaaaaaa'},
            environment: {available: true, message: 'Composer ready'},
            state: {},
            update_available: false,
        },
        {
            installed: 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
            installed_short: 'aaaaaaaaaaaa',
            remote: {sha: 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb', short: 'bbbbbbbbbbbb'},
            environment: {available: true, message: 'Composer ready'},
            state: {},
            update_available: true,
        },
    ];
    const context = {
        document,
        window,
        URLSearchParams,
        fetch() {
            const snapshot = snapshots[Math.min(requests, snapshots.length - 1)];
            requests += 1;
            return Promise.resolve({
                ok: true,
                json: () => Promise.resolve({success: true, data: snapshot}),
            });
        },
    };

    vm.runInNewContext(
        fs.readFileSync(path.join(__dirname, '../plugins/sp-deployment-manager/assets/admin.js'), 'utf8'),
        context
    );

    assert.strictEqual(requests, 0, 'request must wait for the shared bootstrap payload');
    assert.strictEqual(typeof listeners['sp-admin-bootstrap-ready'], 'function', 'ready listener must be registered');

    window.SPAdminData = {
        get() {
            return {
                ajaxUrl: '/wp-admin/admin-ajax.php',
                nonce: 'test-nonce',
                copy: {up_to_date: 'Up to date', update_ready: 'Update available'},
            };
        },
    };
    listeners['sp-admin-bootstrap-ready']();
    await settle();

    assert.strictEqual(requests, 1, 'initial snapshot must load when the bootstrap becomes ready');
    const updateButton = controls.get('[data-sp-deployment-update]');
    const checkButton = controls.get('[data-sp-deployment-check]');
    assert.strictEqual(updateButton.disabled, true, 'update remains disabled when revisions match');

    checkButton.listeners.click();
    await settle();

    assert.strictEqual(requests, 2, 'manual update check requests a fresh snapshot');
    assert.strictEqual(updateButton.disabled, false, 'update becomes enabled without reloading the page');

    console.log('Deployment manager JS: 5 checks passed.');
})().catch((error) => {
    console.error(error);
    process.exit(1);
});
