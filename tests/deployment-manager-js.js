'use strict';

const assert = require('node:assert');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const listeners = {};
let requests = 0;

const control = {
    addEventListener() {},
};
const root = {
    querySelector() {
        return control;
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
const window = {};
const context = {
    document,
    window,
    URLSearchParams,
    fetch() {
        requests += 1;
        return new Promise(() => {});
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
        };
    },
};
listeners['sp-admin-bootstrap-ready']();

assert.strictEqual(requests, 1, 'initial snapshot must load when the bootstrap becomes ready');

console.log('Deployment manager JS: 3 checks passed.');
