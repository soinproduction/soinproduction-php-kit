'use strict';

const assert = require('node:assert');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const source = fs.readFileSync(
    path.join(__dirname, '../plugins/sp-webp-uploads/index.php'),
    'utf8'
);
const match = source.match(
    /private function replace_admin_script\(\): string \{\s+return <<<'JS'\n([\s\S]*?)\nJS;/
);

assert.ok(match, 'replace admin script must be embedded in the WebP module');

const alerts = [];
const requests = [];
let clickHandler = null;
let lastInput = null;

function button(mime = 'image/webp') {
    const values = {
        attachmentId: 530,
        spOriginalText: '',
    };

    return {
        mime,
        label: 'Replace file',
        data(key, value) {
            if (arguments.length === 2) {
                values[key] = value;
                return this;
            }
            return values[key];
        },
        attr(name) {
            return name === 'data-attachment-mime' ? this.mime : null;
        },
        hasClass() {
            return false;
        },
        toggleClass() {
            return this;
        },
        prop() {
            return this;
        },
        text(value) {
            if (arguments.length) {
                this.label = value;
                return this;
            }
            return this.label;
        },
    };
}

function jquery(target) {
    if (target === document) {
        return {
            on(event, selector, handler) {
                if (event === 'click' && selector === '.sp-webp-replace-trigger') {
                    clickHandler = handler;
                }
            },
        };
    }

    return target;
}

jquery.ajax = function ajax(options) {
    const callbacks = {};
    const request = {
        options,
        callbacks,
        done(callback) {
            callbacks.done = callback;
            return this;
        },
        fail(callback) {
            callbacks.fail = callback;
            return this;
        },
        always(callback) {
            callbacks.always = callback;
            return this;
        },
    };

    requests.push(request);
    return request;
};

class FormDataMock {
    constructor() {
        this.values = new Map();
    }

    append(key, value) {
        this.values.set(key, value);
    }
}

const document = {
    body: {
        appendChild() {},
        removeChild() {},
    },
    createElement(tag) {
        assert.strictEqual(tag, 'input');
        const listeners = {};
        lastInput = {
            files: [],
            accept: '',
            style: {},
            addEventListener(name, callback) {
                listeners[name] = callback;
            },
            click() {},
            choose(file) {
                this.files = [file];
                listeners.change();
            },
        };
        return lastInput;
    },
};

const window = {
    spWebpReplace: {
        ajaxUrl: '/wp-admin/admin-ajax.php',
        nonce: 'test-nonce',
        maxUploadSize: 1024 * 1024,
        confirm: 'Confirm replace',
    },
    alert(message) {
        alerts.push(message);
    },
    confirm() {
        return true;
    },
    location: {
        reload() {},
    },
};

vm.runInNewContext(match[1], {
    document,
    FormData: FormDataMock,
    jQuery: jquery,
    JSON,
    Math,
    Number,
    String,
    window,
});

assert.strictEqual(typeof clickHandler, 'function', 'replace click handler must be registered');

const webpButton = button();
clickHandler.call(webpButton, {preventDefault() {}});
assert.strictEqual(
    lastInput.accept,
    '.webp,.png,.jpg,.jpeg,image/webp,image/png,image/jpeg',
    'WebP attachment picker must also allow convertible PNG and JPEG files'
);
lastInput.choose({name: 'replacement.webp', type: 'image/webp', size: 1024});

assert.strictEqual(requests.length, 1, 'valid replacement must start one AJAX request');
assert.strictEqual(requests[0].options.data.values.get('action'), 'sp_webp_replace_attachment_file');
assert.strictEqual(requests[0].options.data.values.get('attachment_id'), '530');

requests[0].callbacks.fail({
    status: 400,
    responseJSON: {success: false, data: {message: 'Current attachment file is missing or not writable.'}},
});
assert.strictEqual(
    alerts.pop(),
    'Current attachment file is missing or not writable.',
    'HTTP errors must preserve the server JSON message'
);

clickHandler.call(webpButton, {preventDefault() {}});
lastInput.choose({name: 'replacement.webp', type: 'image/webp', size: 1024});
requests[1].callbacks.fail({status: 403, responseText: '-1'});
assert.strictEqual(
    alerts.pop(),
    'The security check expired. Reload the Media Library page and try again.',
    'expired nonce response must be actionable'
);

clickHandler.call(webpButton, {preventDefault() {}});
lastInput.choose({name: 'replacement.webp', type: 'image/webp', size: 1024});
requests[2].callbacks.fail({status: 413, responseText: ''});
assert.strictEqual(
    alerts.pop(),
    'The replacement file is larger than the server upload limit.',
    'payload limit response must be actionable'
);

const requestCount = requests.length;
clickHandler.call(webpButton, {preventDefault() {}});
lastInput.choose({name: 'replacement.png', type: 'image/png', size: 1024});
assert.strictEqual(requests.length, requestCount + 1, 'PNG replacement for WebP must start an AJAX request');
assert.strictEqual(requests[requestCount].options.data.values.get('replacement').name, 'replacement.png');

const jpegButton = button('image/jpeg');
clickHandler.call(jpegButton, {preventDefault() {}});
lastInput.choose({name: 'replacement.png', type: 'image/png', size: 1024});
assert.strictEqual(requests.length, requestCount + 1, 'non-WebP mismatched format must be rejected before AJAX');
assert.strictEqual(
    alerts.pop(),
    'Use the same image format as the current attachment. WebP attachments also accept PNG or JPEG and convert them automatically.'
);

clickHandler.call(webpButton, {preventDefault() {}});
lastInput.choose({name: 'large.webp', type: 'image/webp', size: 2 * 1024 * 1024});
assert.strictEqual(requests.length, requestCount + 1, 'oversized replacement must be rejected before AJAX');
assert.strictEqual(alerts.pop(), 'The replacement file is too large. Maximum upload size: 1 MB.');

console.log('WebP replace JS: 14 checks passed.');
