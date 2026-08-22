(function () {
    'use strict';

    var initialized = false;

    function initialize() {
        if (initialized) {
            return true;
        }

        var root = document.querySelector('[data-sp-deployment-manager]');
        var config = window.SPAdminData ? window.SPAdminData.get('deploymentManager', {}) : {};
        if (!root || !config.ajaxUrl || !config.nonce) {
            return false;
        }

        initialized = true;

    var copy = config.copy || {};
    var checkButton = root.querySelector('[data-sp-deployment-check]');
    var updateButton = root.querySelector('[data-sp-deployment-update]');
    var rollbackButton = root.querySelector('[data-sp-deployment-rollback]');
    var notice = root.querySelector('[data-sp-deployment-notice]');
    var pollTimer = 0;
    var busy = false;
    var lastSnapshot = null;

    function request(action, data) {
        var body = new URLSearchParams(Object.assign({action: action, nonce: config.nonce}, data || {}));
        return fetch(config.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
            body: body.toString()
        }).then(function (response) {
            return response.json().then(function (payload) {
                if (!response.ok || !payload.success) {
                    var message = payload && payload.data && payload.data.message ? payload.data.message : 'Request failed.';
                    throw new Error(message);
                }
                return payload.data;
            });
        });
    }

    function setNotice(message, type) {
        notice.hidden = !message;
        notice.className = 'notice inline notice-' + (type || 'info');
        notice.querySelector('p').textContent = message || '';
    }

    function text(selector, value) {
        var node = root.querySelector(selector);
        if (node) {
            node.textContent = value || '—';
        }
    }

    function shortReference(reference) {
        return reference ? String(reference).slice(0, 12) : '—';
    }

    function render(snapshot) {
        lastSnapshot = snapshot;
        var remote = snapshot.remote || {};
        var environment = snapshot.environment || {};
        var state = snapshot.state || {};
        var running = state.status === 'pending' || state.status === 'running';
        var rollback = state.rollback || null;
        var badge = root.querySelector('[data-sp-deployment-badge]');

        text('[data-sp-deployment-installed]', snapshot.installed_short || shortReference(snapshot.installed));
        text('[data-sp-deployment-remote]', remote.short || shortReference(remote.sha));
        text('[data-sp-deployment-root]', snapshot.project_root || '—');
        text('[data-sp-deployment-composer]', environment.message || copy.unavailable);
        text('[data-sp-deployment-state]', state.message || copy.up_to_date);

        badge.className = 'sp-deployment__badge';
        if (remote.error || !environment.available) {
            badge.textContent = copy.unavailable || 'Unavailable';
            badge.classList.add('is-error');
        } else if (snapshot.update_available) {
            badge.textContent = copy.update_ready || 'Update available';
            badge.classList.add('is-update');
        } else {
            badge.textContent = copy.up_to_date || 'Up to date';
            badge.classList.add('is-current');
        }

        var commit = root.querySelector('[data-sp-deployment-commit]');
        commit.hidden = !remote.message;
        text('[data-sp-deployment-message]', remote.message || '');
        text('[data-sp-deployment-date]', remote.date ? new Date(remote.date).toLocaleString() : '');

        updateButton.disabled = busy || running || !snapshot.update_available || !environment.available;
        rollbackButton.disabled = busy || running || !rollback || !rollback.path;
        checkButton.disabled = busy || running;

        var rollbackCopy = rollback && rollback.target
            ? '→ ' + shortReference(rollback.target)
            : (copy.rollback_empty || 'No recovery point.');
        text('[data-sp-deployment-rollback-copy]', rollbackCopy);

        var logCard = root.querySelector('[data-sp-deployment-log-card]');
        var log = state.log || '';
        logCard.hidden = !log;
        text('[data-sp-deployment-log]', log);

        if (remote.error) {
            setNotice(remote.error, 'error');
        } else if (!environment.available) {
            setNotice(environment.message || copy.unavailable, 'warning');
        } else if (state.status === 'error') {
            setNotice(state.message, 'error');
        } else if (state.status === 'success') {
            setNotice(state.message, 'success');
        } else if (running) {
            setNotice(state.message, 'info');
        } else {
            setNotice('', 'info');
        }

        if (running) {
            startPolling();
        } else {
            stopPolling();
        }
    }

    function load(force) {
        return request('sp_deployment_snapshot', {force: force ? '1' : ''})
            .then(render)
            .catch(function (error) {
                setNotice(error.message, 'error');
            });
    }

    function startPolling() {
        if (pollTimer) {
            return;
        }
        pollTimer = window.setInterval(function () { load(false); }, Number(config.pollInterval) || 3000);
    }

    function stopPolling() {
        if (pollTimer) {
            window.clearInterval(pollTimer);
            pollTimer = 0;
        }
    }

    checkButton.addEventListener('click', function () {
        busy = true;
        checkButton.disabled = true;
        load(true).finally(function () {
            busy = false;
            if (lastSnapshot) {
                render(lastSnapshot);
            } else {
                checkButton.disabled = false;
            }
        });
    });

    updateButton.addEventListener('click', function () {
        if (!window.confirm(copy.confirm_update || 'Install the update?')) {
            return;
        }
        busy = true;
        updateButton.disabled = true;
        request('sp_deployment_update').then(function () {
            busy = false;
            startPolling();
            return load(false);
        }).catch(function (error) {
            busy = false;
            setNotice(error.message, 'error');
            load(false);
        });
    });

    rollbackButton.addEventListener('click', function () {
        if (!window.confirm(copy.confirm_rollback || 'Rollback dependencies?')) {
            return;
        }
        busy = true;
        rollbackButton.disabled = true;
        request('sp_deployment_rollback').then(function () {
            busy = false;
            startPolling();
            return load(false);
        }).catch(function (error) {
            busy = false;
            setNotice(error.message, 'error');
            load(false);
        });
    });

        load(false);
        return true;
    }

    if (!initialize()) {
        document.addEventListener('sp-admin-bootstrap-ready', initialize, {once: true});
    }
}());
