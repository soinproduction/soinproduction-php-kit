(function () {
    'use strict';

    tinymce.PluginManager.add('sp_widgets', function (editor) {

        var svg = `
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20">
              <path fill="none" stroke="#3858e9" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/>
            </svg>
        `;
        var iconDataUri = 'data:image/svg+xml;utf8,' + encodeURIComponent(svg);

        function esc(s) {
            return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;')
                .replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
        }

        var widgetConfig = {};
        var ajaxUrl = window.ajaxurl || (window.ajax_params ? window.ajax_params.ajax_url : '/wp-admin/admin-ajax.php');
        var iframeBaseUrl = ajaxUrl.replace('admin-ajax.php', 'admin-post.php');
        var catalogCacheKey = '';
        var bootstrapCallbacks = [];
        var bootstrapListening = false;
        var sharedStore = window.SPAdminWidgetStore = window.SPAdminWidgetStore || {
            previewCache: {},
            previewCallbacks: {},
            previewTimer: null,
            catalog: null,
            catalogRequest: null
        };

        function applyWidgetConfig() {
            var next = window.SPAdminData && typeof window.SPAdminData.get === 'function'
                ? window.SPAdminData.get('editorWidgets', {})
                : {};
            widgetConfig = next && typeof next === 'object' ? next : {};
            ajaxUrl = widgetConfig.ajaxUrl || window.ajaxurl || (window.ajax_params ? window.ajax_params.ajax_url : '/wp-admin/admin-ajax.php');
            iframeBaseUrl = ajaxUrl.replace('admin-ajax.php', 'admin-post.php');
            var nextCatalogCacheKey = String(widgetConfig.catalogCacheKey || '');
            if (sharedStore.catalogCacheKey === nextCatalogCacheKey) {
                catalogCacheKey = nextCatalogCacheKey;
                return;
            }

            catalogCacheKey = nextCatalogCacheKey;
            sharedStore.catalog = null;
            sharedStore.catalogRequest = null;
            sharedStore.catalogCacheKey = catalogCacheKey;
            if (catalogCacheKey) {
                try {
                    var storedCatalog = JSON.parse(window.sessionStorage.getItem(catalogCacheKey) || 'null');
                    if (Array.isArray(storedCatalog)) sharedStore.catalog = storedCatalog;
                } catch (_) {}
            }
        }

        function widgetNonce() {
            applyWidgetConfig();
            return widgetConfig.nonce || window.SP_WIDGETS_NONCE || '';
        }

        function whenWidgetBootstrapReady(callback) {
            if (widgetNonce()) {
                return true;
            }

            bootstrapCallbacks.push(callback);
            if (!bootstrapListening) {
                bootstrapListening = true;
                document.addEventListener('sp-admin-bootstrap-ready', function () {
                    bootstrapListening = false;
                    applyWidgetConfig();
                    var callbacks = bootstrapCallbacks.slice();
                    bootstrapCallbacks = [];
                    callbacks.forEach(function (ready) { ready(); });
                }, {once: true});
            }

            return false;
        }

        applyWidgetConfig();
        var previewCache = sharedStore.previewCache;

        function normalizeAlign(value) {
            value = String(value || '').trim().toLowerCase();
            return ['left', 'center', 'right'].indexOf(value) !== -1 ? value : '';
        }

        function widgetShortcode(id, align) {
            align = normalizeAlign(align);
            return '[widget id="' + parseInt(id, 10) + '"' + (align ? ' align="' + align + '"' : '') + ']';
        }

        function parseWidgetShortcodeAttrs(rawAttrs) {
            var attrs = {};
            String(rawAttrs || '').replace(/([a-z0-9_-]+)\s*=\s*(?:"([^"]*)"|'([^']*)'|([^\s\]]+))/gi, function (_, key, doubleValue, singleValue, bareValue) {
                attrs[String(key || '').toLowerCase()] = doubleValue || singleValue || bareValue || '';
                return '';
            });
            return attrs;
        }

        function nodeAlign(node) {
            if (!node) {
                return '';
            }

            var align = normalizeAlign(node.getAttribute('data-widget-align'));
            if (align) {
                return align;
            }

            if (node.style) {
                align = normalizeAlign(node.style.textAlign);
                if (align) {
                    return align;
                }
            }

            if (node.classList) {
                if (node.classList.contains('aligncenter')) return 'center';
                if (node.classList.contains('alignleft')) return 'left';
                if (node.classList.contains('alignright')) return 'right';
            }

            return '';
        }

        function normalizeWidgetPreviewHtml(html) {
            if (!html) return '';
            var doc = editor.getDoc();
            if (!doc) return html;
            var temp = doc.createElement('div');
            temp.innerHTML = html;

            Array.prototype.slice.call(temp.querySelectorAll('[style]')).forEach(function (node) {
                var style = String(node.getAttribute('style') || '');
                var cleaned = style
                    .replace(/(^|;)\s*display\s*:\s*(block|list-item)\s*;?/gi, '$1')
                    .replace(/;{2,}/g, ';')
                    .replace(/^\s*;\s*|\s*;\s*$/g, '')
                    .trim();

                if (cleaned) {
                    node.setAttribute('style', cleaned);
                } else {
                    node.removeAttribute('style');
                }
            });

            return temp.innerHTML;
        }

        function editorPreviewStyles() {
            return `
                .sp-editor-widget {
                    position: relative;
                    display: inline-flex;
                    width: fit-content;
                    max-width: 100%;
                    border: .1rem dashed rgba(72, 125, 228, .32);
                    background: rgba(237, 239, 247, .62);
                    box-shadow: 0 .6rem 1.8rem rgba(26, 37, 56, .08);
                    cursor: default;
                    vertical-align: middle;
                    user-select: none;
                    margin: 0 1em !important;
                }

                .sp-editor-widget.is-selected {
                    outline: .2rem solid rgba(72, 125, 228, .72);
                    outline-offset: .2rem;
                }
                .sp-editor-widget.is-loading .sp-editor-widget__preview,
                .sp-editor-widget.is-error .sp-editor-widget__preview {
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    min-width: 14rem;
                    min-height: 4.4rem;
                    color: #64748b;
                    font-size: 1.2rem;
                    font-weight: 700;
                    letter-spacing: .04em;
                    text-transform: uppercase;
                }
                .sp-editor-widget.is-error {
                    border-color: rgba(220, 38, 38, .38);
                    background: rgba(254, 242, 242, .82);
                }
                .sp-editor-widget__preview {
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    max-width: 100%;
                    pointer-events: none;
                }
                .sp-editor-widget__edit {
                    position: absolute;
                    top: -.9rem;
                    right: -.9rem;
                    z-index: 5;
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    width: 2.8rem;
                    height: 2.8rem;
                    padding: 0;
                    border: .1rem solid var(--color-accent, #3858e9);
                    border-radius: 0;
                    background: var(--color-accent, #3858e9);
                    color: #fff;
                    font-size: 1.4rem;
                    line-height: 1;
                    cursor: pointer;
                    box-shadow: none;
                    transform: none;
                    pointer-events: auto;
                    user-select: none;
                }
                .sp-editor-widget__edit::before {
                    font-family: dashicons;
                    display: inline-block;
                    line-height: 1;
                    content: "\\f464";
                    display: block;
                    cursor: pointer;
                }
                .sp-editor-widget__edit:hover {
                    border-color: var(--color-accent-hover, #2145e6);
                    background: var(--color-accent-hover, #2145e6);
                    color: #fff;
                    box-shadow: none;
                    transform: none;
                }
                .sp-editor-widget-empty {
                    color: #64748b;
                    font-size: 1.2rem;
                    font-weight: 700;
                    letter-spacing: .04em;
                    text-transform: uppercase;
                }
                .sp-editor-inline-row > .sp-editor-widget {
                    margin: 0;
                }
            `;
        }

        function buildPreviewShell(id, content, state, align) {
            var safeId = parseInt(id, 10);
            align = normalizeAlign(align);

            var className = 'sp-editor-widget mceNonEditable' + (state ? ' is-' + state : '');
            return '<span class="' + className + '" data-sp-widget-preview="1" data-widget-id="' + safeId + '"' +
                (align ? ' data-widget-align="' + align + '" style="text-align: ' + align + ';"' : '') +
                ' contenteditable="false" data-mce-contenteditable="false" data-mce-resize="false">' +
                '<span class="sp-editor-widget__preview">' + content + '</span>' +
                '<span class="sp-editor-widget__edit" data-sp-widget-edit="' + safeId + '" title="Edit widget" aria-label="Edit widget" role="button" tabindex="-1" contenteditable="false" data-mce-contenteditable="false"></span>' +
                '</span>';
        }

        function shortcodesToPreviews(content) {
            var normalized = previewsToShortcodes(content);
            return String(normalized || '').replace(/\[widget\b([^\]]*)\]/g, function (shortcode, rawAttrs) {
                var attrs = parseWidgetShortcodeAttrs(rawAttrs);
                var id = parseInt(attrs.id, 10);
                if (!id) {
                    return shortcode;
                }

                var align = normalizeAlign(attrs.align);

                return buildPreviewShell(id, 'Widget #' + id + '...', 'loading', align);
            });
        }

        function convertPreviewsToShortcodesIn(root) {
            if (!root || !root.querySelectorAll) {
                return;
            }
            var doc = root.ownerDocument || document;

            root.querySelectorAll('.sp-editor-widget[data-widget-id]').forEach(function (node) {
                var id = parseInt(node.getAttribute('data-widget-id'), 10);
                if (!id || !node.parentNode) return;
                node.parentNode.replaceChild(doc.createTextNode(widgetShortcode(id, nodeAlign(node))), node);
            });
            root.querySelectorAll('.sp-editor-row-anchor').forEach(function (node) {
                if (node.parentNode) {
                    node.parentNode.removeChild(node);
                }
            });
            root.querySelectorAll('.sp-editor-widget__edit, [data-sp-widget-edit], [data-sp-widget-preview]').forEach(function (node) {
                if (node.classList && node.classList.contains('sp-editor-widget')) {
                    return;
                }
                if (node.parentNode) {
                    node.parentNode.removeChild(node);
                }
            });
        }

        function previewsToShortcodes(content) {
            var wrap = document.createElement('div');
            wrap.innerHTML = String(content || '');
            convertPreviewsToShortcodesIn(wrap);

            return wrap.innerHTML.replace(/\u200B/g, '');
        }

        function selectedWidgets() {
            var body = editor.getBody ? editor.getBody() : null;
            if (!body) {
                return [];
            }
            return Array.prototype.slice.call(body.querySelectorAll('.sp-editor-widget.is-selected[data-widget-id]')).filter(function (w) {
                return !w.closest('.mce-offscreen-selection, [data-mce-bogus="all"]');
            });
        }

        function insertTextAfterSelectedWidget(text) {
            var widgets = selectedWidgets();
            if (widgets.length !== 1) {
                return false;
            }

            var widget = widgets[0];
            var parent = widget.parentNode;
            if (!parent) {
                return false;
            }

            var doc = editor.getDoc();
            var node = doc.createTextNode(text);
            parent.insertBefore(node, widget.nextSibling);
            widget.classList.remove('is-selected');
            editor.selection.setCursorLocation(node, node.nodeValue.length);
            editor.fire('change');
            return true;
        }

        function applyAlignToSelectedWidgets(align) {
            align = normalizeAlign(align);
            if (!align) {
                return false;
            }

            var widgets = selectedWidgets();
            if (!widgets.length) {
                return false;
            }

            widgets.forEach(function (widget) {
                var parent = widget.parentElement;
                if (parent && parent !== editor.getBody()) {
                    parent.style.textAlign = align;
                    widget.removeAttribute('data-widget-align');
                    widget.style.textAlign = '';
                    return;
                }

                widget.setAttribute('data-widget-align', align);
                widget.style.textAlign = align;
            });

            return true;
        }

        function flushWidgetPreviewQueue() {
            sharedStore.previewTimer = null;

            if (!whenWidgetBootstrapReady(function () {
                if (Object.keys(sharedStore.previewCallbacks).length && !sharedStore.previewTimer) {
                    sharedStore.previewTimer = window.setTimeout(flushWidgetPreviewQueue, 0);
                }
            })) {
                return;
            }

            var ids = Object.keys(sharedStore.previewCallbacks).map(function (id) {
                return parseInt(id, 10);
            }).filter(Boolean).slice(0, 50);
            if (!ids.length) return;

            var callbacks = {};
            ids.forEach(function (id) {
                callbacks[id] = sharedStore.previewCallbacks[id] || [];
                delete sharedStore.previewCallbacks[id];
            });

            jQuery.ajax({
                url: ajaxUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'sp_render_widget_previews',
                    nonce: widgetNonce(),
                    ids: ids
                }
            }).done(function (res) {
                var previews = res && res.success && res.data && res.data.previews
                    ? res.data.previews
                    : {};

                ids.forEach(function (id) {
                    var item = previews[id] || previews[String(id)] || null;
                    var html = item && item.html ? item.html : '';
                    if (html) previewCache[id] = html;
                    (callbacks[id] || []).forEach(function (fn) { fn(html); });
                });
            }).fail(function () {
                ids.forEach(function (id) {
                    (callbacks[id] || []).forEach(function (fn) { fn(''); });
                });
            }).always(function () {
                if (Object.keys(sharedStore.previewCallbacks).length && !sharedStore.previewTimer) {
                    sharedStore.previewTimer = window.setTimeout(flushWidgetPreviewQueue, 0);
                }
            });
        }

        function renderWidgetPreview(id, callback) {
            id = parseInt(id, 10);
            if (!id) {
                callback('');
                return;
            }

            if (previewCache[id]) {
                callback(previewCache[id]);
                return;
            }

            sharedStore.previewCallbacks[id] = sharedStore.previewCallbacks[id] || [];
            sharedStore.previewCallbacks[id].push(callback);
            if (!sharedStore.previewTimer) {
                sharedStore.previewTimer = window.setTimeout(flushWidgetPreviewQueue, 0);
            }
        }

        function refreshWidgetPreviewNode(node) {
            if (!node) return;
            var id = parseInt(node.getAttribute('data-widget-id'), 10);
            if (!id) return;

            renderWidgetPreview(id, function (html) {
                var preview = node.querySelector('.sp-editor-widget__preview');
                if (!preview) return;

                if (!html) {
                    preview.innerHTML = '<span class="sp-editor-widget-empty">Widget #' + id + ' unavailable</span>';
                    node.classList.remove('is-loading');
                    node.classList.add('is-error');
                    return;
                }

                var safeHtml = normalizeWidgetPreviewHtml(html);

                if (preview.innerHTML !== safeHtml) {
                    preview.innerHTML = safeHtml;
                }
                node.classList.remove('is-loading', 'is-error');
            });
        }

        function refreshWidgetPreviews() {
            var body = editor.getBody ? editor.getBody() : null;
            if (!body) return;
            body.querySelectorAll('.sp-editor-widget[data-widget-id]').forEach(refreshWidgetPreviewNode);
        }

        function ensureCaretPadding() {
            var body = editor.getBody ? editor.getBody() : null;
            if (!body) return;

            body.querySelectorAll('.sp-editor-widget[data-widget-id]').forEach(function (widget) {

                if (widget.parentNode && widget.parentNode.closest &&
                    widget.parentNode.closest('.sp-editor-inline-row')) {
                    return;
                }

                var prev = widget.previousSibling;
                if (!prev || (prev.nodeType === 1 && prev.classList && prev.classList.contains('sp-editor-widget'))) {
                    widget.parentNode.insertBefore(editor.getDoc().createTextNode('\u200B'), widget);
                }

                var next = widget.nextSibling;
                var needsPadding = !next ||
                    (next.nodeType === 1 && next.classList && next.classList.contains('sp-editor-widget'));

                if (!needsPadding && next.nodeType === 3 && !next.nodeValue) {
                    needsPadding = !next.nextSibling;
                }

                if (needsPadding) {
                    widget.parentNode.insertBefore(editor.getDoc().createTextNode('\u200B'), widget.nextSibling);
                }
            });
        }

        function restoreWidgetEditHandles() {
            var body = editor.getBody ? editor.getBody() : null;
            if (!body) return;

            body.querySelectorAll('.sp-editor-widget[data-widget-id]').forEach(function (widget) {
                var id = parseInt(widget.getAttribute('data-widget-id'), 10);
                if (!id || widget.querySelector('.sp-editor-widget__edit[data-sp-widget-edit]')) {
                    return;
                }

                var handle = editor.getDoc().createElement('span');
                handle.className = 'sp-editor-widget__edit';
                handle.setAttribute('data-sp-widget-edit', String(id));
                handle.setAttribute('title', 'Edit widget');
                handle.setAttribute('aria-label', 'Edit widget');
                handle.setAttribute('role', 'button');
                handle.setAttribute('tabindex', '-1');
                handle.setAttribute('contenteditable', 'false');
                handle.setAttribute('data-mce-contenteditable', 'false');
                widget.appendChild(handle);
            });
        }

        function invalidateWidgetPreview(id) {
            id = parseInt(id, 10);
            if (id && previewCache[id]) {
                delete previewCache[id];
            }
            sharedStore.catalog = null;
            sharedStore.catalogRequest = null;
            if (catalogCacheKey) {
                try { window.sessionStorage.removeItem(catalogCacheKey); } catch (_) {}
            }
        }

        function getSelectedWidgetId() {
            try {
                var node = editor.selection.getNode();
                var widgetNode = node && node.closest ? node.closest('.sp-editor-widget[data-widget-id]') : null;
                if (widgetNode && widgetNode.closest('.mce-offscreen-selection, [data-mce-bogus="all"]')) {
                    var body = editor.getBody();
                    if (body) {
                        var realWidget = body.querySelector('.sp-editor-widget[data-widget-id][data-mce-selected]');
                        if (realWidget && !realWidget.closest('.mce-offscreen-selection, [data-mce-bogus="all"]')) {
                            widgetNode = realWidget;
                        } else {
                            var wId = widgetNode.getAttribute('data-widget-id');
                            var candidates = Array.prototype.slice.call(body.querySelectorAll('.sp-editor-widget[data-widget-id="' + wId + '"]')).filter(function (c) {
                                return !c.closest('.mce-offscreen-selection, [data-mce-bogus="all"]');
                            });
                            widgetNode = candidates[0] || null;
                        }
                    }
                }
                if (widgetNode) {
                    return parseInt(widgetNode.getAttribute('data-widget-id'), 10);
                }

                var selContent = editor.selection.getContent({ format: 'text' });
                if (selContent) {
                    var match = /\[widget\s+id=["']?(\d+)["']?[^\]]*\]/.exec(selContent);
                    if (match) {
                        return parseInt(match[1], 10);
                    }
                }
            } catch (_) {}
            return null;
        }

        function open(forcedWidgetId) {
            if (!whenWidgetBootstrapReady(function () { open(forcedWidgetId); })) {
                return;
            }

            var activeId = forcedWidgetId || getSelectedWidgetId();
            var isEditMode = (activeId !== null && activeId !== undefined && activeId !== 0);
            var bookmark = editor.selection.getBookmark(2, true);

            var selectedWidgetId = null;
            var widgetSaved = false;
            var saveWatchInterval = null;
            var saveWatchTimeout = null;

            var html = `
                <style>
                    .spw-wrap {
                        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                        padding: 0;
                        display: flex;
                        flex-direction: column;
                        height: 100%;
                        box-sizing: border-box;
                        background: #ffffff;
                    }
                    .spw-bar {
                        display: flex;
                        align-items: center;
                        gap: 12px;
                        padding: 16px 20px;
                        border-bottom: 1px solid #e2e8f0;
                        background: #f8fafc;
                        flex-wrap: wrap;
                    }
                    .spw-bar-group {
                        display: flex;
                        align-items: center;
                        gap: 8px;
                    }
                    .spw-bar-label {
                        display: block;
                        margin-bottom: 5px;
                        color: #475569;
                        font-size: 11px;
                        font-weight: 700;
                        letter-spacing: .06em;
                        text-transform: uppercase;
                    }
                    .spw-select {
                        padding: 8px 12px;
                        border: 1px solid #cbd5e1;
                        border-radius: 0;
                        font-size: 14px;
                        min-width: 330px;
                        outline: none;
                        background-color: #fff;
                    }
                    .spw-input {
                        padding: 8px 12px;
                        border: 1px solid #cbd5e1;
                        border-radius: 0;
                        font-size: 14px;
                        width: 270px;
                        outline: none;
                    }
                    .spw-btn {
                        padding: 8px 18px;
                        background-color: #3858e9;
                        color: #fff;
                        border-radius: 0;
                        font-size: 14px;
                        font-weight: 600;
                        cursor: pointer;
                        border: none;
                        transition: background-color 0.2s;
                        display: inline-flex;
                        align-items: center;
                        justify-content: center;
                    }
                    .spw-btn:hover {
                        background-color: #2145e6;
                    }
                    .spw-btn:disabled {
                        background-color: #cbd5e1;
                        cursor: not-allowed;
                    }
                    .spw-body {
                        flex-grow: 1;
                        position: relative;
                        background: #ffffff;
                        height: 790px;
                        display: flex;
                        flex-direction: column;
                    }
                    .spw-picker {
                        flex: 1 1 auto;
                        min-height: 0;
                        overflow: auto;
                        padding: 26px 28px 88px;
                    }
                    .spw-picker-head {
                        display: flex;
                        align-items: flex-end;
                        justify-content: space-between;
                        gap: 18px;
                        margin-bottom: 18px;
                    }
                    .spw-picker-title {
                        margin: 0;
                        color: #1d2327;
                        font-size: 17px;
                        font-weight: 700;
                        line-height: 1.25;
                    }
                    .spw-picker-subtitle {
                        margin: 5px 0 0;
                        color: #64748b;
                        font-size: 13px;
                        line-height: 1.4;
                    }
                    .spw-search {
                        width: 280px;
                        max-width: 100%;
                        padding: 9px 12px;
                        border: 1px solid #cbd5e1;
                        border-radius: 0;
                        font-size: 14px;
                    }
                    .spw-widget-grid {
                        display: grid;
                        grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
                        gap: 16px;
                    }
                    .spw-widget-card {
                        display: flex;
                        flex-direction: column;
                        min-height: 204px;
                        padding: 0;
                        border: 2px solid #e2e8f0;
                        border-radius: 0;
                        background: #ffffff;
                        cursor: pointer;
                        text-align: left;
                        transition: border-color .15s ease, background-color .15s ease;
                    }
                    .spw-widget-card:hover {
                        border-color: #3858e9;
                        box-shadow: none;
                        transform: none;
                    }
                    .spw-widget-card.is-active {
                        border-color: #3858e9;
                        background: #f7f8ff;
                        box-shadow: none;
                    }
                    .spw-widget-preview {
                        position: relative;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        height: 126px;
                        margin: 12px 12px 0;
                        border: 1px solid #e2e8f0;
                        border-radius: 0;
                        background: #f1f5f9;
                        overflow: hidden;
                    }
                    .spw-card-actions {
                        position: absolute;
                        left: 0;
                        right: 0;
                        bottom: 0;
                        display: flex;
                        justify-content: center;
                        padding: 10px 8px 8px;
                        background: linear-gradient(transparent, rgba(15, 23, 42, .45));
                        opacity: 0;
                        transition: opacity .15s ease;
                        pointer-events: none;
                    }
                    .spw-widget-card:hover .spw-card-actions {
                        opacity: 1;
                        pointer-events: auto;
                    }
                    .spw-act {
                        padding: 6px 16px;
                        border: none;
                        border-radius: 0;
                        font-size: 12px;
                        font-weight: 700;
                        cursor: pointer;
                        box-shadow: none;
                    }
                    .spw-act--insert {
                        background: #3858e9;
                        color: #fff;
                    }
                    .spw-act--insert:hover {
                        background: #2145e6;
                    }
                    .spw-dup-link {
                        padding: 0;
                        border: none;
                        background: none;
                        color: #487de4;
                        font-size: 12px;
                        cursor: pointer;
                        text-decoration: underline;
                        text-underline-offset: 2px;
                        opacity: 0;
                        transition: opacity .15s ease;
                    }
                    .spw-widget-card:hover .spw-dup-link,
                    .spw-widget-card.is-active .spw-dup-link {
                        opacity: 1;
                    }
                    .spw-dup-link:hover {
                        color: #2145e6;
                    }
                    .spw-card-new {
                        display: flex;
                        flex-direction: column;
                        align-items: center;
                        justify-content: center;
                        gap: 10px;
                        min-height: 204px;
                        border-style: dashed;
                        border-color: #cbd5e1;
                        background: #f8fafc;
                        color: #64748b;
                    }
                    .spw-card-new:hover {
                        border-color: #3858e9;
                        transform: none;
                        box-shadow: none;
                    }
                    .spw-new-cta {
                        display: flex;
                        flex-direction: column;
                        align-items: center;
                        gap: 6px;
                    }
                    .spw-new-plus {
                        font-size: 36px;
                        line-height: 1;
                        font-weight: 300;
                        color: #3858e9;
                    }
                    .spw-new-label {
                        font-size: 14px;
                        font-weight: 700;
                        color: #475569;
                    }
                    .spw-new-hint {
                        font-size: 12px;
                        color: #94a3b8;
                    }
                    .spw-new-form {
                        display: none;
                        flex-direction: column;
                        gap: 10px;
                        width: 82%;
                    }
                    .spw-card-new.is-editing .spw-new-form {
                        display: flex;
                    }
                    .spw-card-new.is-editing .spw-new-cta {
                        display: none;
                    }
                    .spw-new-form .spw-input {
                        width: 100%;
                        box-sizing: border-box;
                    }
                    .spw-new-actions {
                        display: flex;
                        gap: 8px;
                    }
                    .spw-new-cancel {
                        padding: 8px 14px;
                        border: 1px solid #cbd5e1;
                        border-radius: 0;
                        background: #fff;
                        color: #475569;
                        font-size: 13px;
                        cursor: pointer;
                    }
                    .spw-widget-preview img {
                        width: 100%;
                        height: 100%;
                        object-fit: contain;
                    }
                    .spw-widget-preview span {
                        color: #94a3b8;
                        font-size: 12px;
                        font-weight: 700;
                        letter-spacing: .06em;
                        text-transform: uppercase;
                    }
                    .spw-widget-meta {
                        display: flex;
                        flex-direction: column;
                        gap: 5px;
                        padding: 13px 14px 14px;
                    }
                    .spw-widget-name {
                        overflow: hidden;
                        color: #1e293b;
                        font-size: 14px;
                        font-weight: 700;
                        line-height: 1.3;
                        text-overflow: ellipsis;
                        white-space: nowrap;
                    }
                    .spw-widget-line {
                        display: flex;
                        align-items: center;
                        justify-content: space-between;
                        gap: 10px;
                        color: #64748b;
                        font-size: 12px;
                    }
                    .spw-widget-type {
                        overflow: hidden;
                        text-overflow: ellipsis;
                        white-space: nowrap;
                    }
                    .spw-widget-id {
                        flex: 0 0 auto;
                        color: #94a3b8;
                        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace;
                    }
                    .spw-empty {
                        display: none;
                        padding: 38px;
                        border: 1px dashed #cbd5e1;
                        border-radius: 0;
                        color: #64748b;
                        text-align: center;
                        font-size: 14px;
                    }
                    .spw-loading {
                        position: absolute;
                        inset: 0;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        flex-direction: column;
                        gap: 8px;
                        background: rgba(255, 255, 255, 0.9);
                        font-size: 16px;
                        color: #64748b;
                        font-weight: 500;
                        z-index: 10;
                    }
                    .spw-loading:before {
                        content: "";
                        width: 42px;
                        height: 42px;
                        border: 1px solid #e2e8f0;
                        border-radius: 0;
                        background:
                            linear-gradient(90deg, #3858e9 0 26%, transparent 26%) 10px 12px / 22px 4px no-repeat,
                            linear-gradient(90deg, #cbd5e1 0 70%, transparent 70%) 10px 21px / 22px 4px no-repeat,
                            linear-gradient(90deg, #cbd5e1 0 55%, transparent 55%) 10px 30px / 22px 4px no-repeat,
                            #f8fafc;
                    }
                    .spw-iframe {
                        width: 100%;
                        height: 100%;
                        flex-grow: 1;
                        border: none;
                        display: none;
                    }
                </style>
                <div class="spw-wrap" data-spw-root>
                    <div class="spw-body">
                        ${!isEditMode ? `
                        <div class="spw-picker" id="spw-picker">
                            <div class="spw-picker-head">
                                <div>
                                    <h3 class="spw-picker-title">Available widgets</h3>
                                    <p class="spw-picker-subtitle">Hover a card and press <strong>Insert</strong>, or double-click it. <strong>Duplicate</strong> creates an editable copy.</p>
                                </div>
                                <input class="spw-search" type="search" id="spw-widget-search" placeholder="Search widgets...">
                            </div>
                            <div class="spw-widget-grid" id="spw-widget-grid"></div>
                            <div class="spw-empty" id="spw-empty">No widgets found.</div>
                        </div>
                        ` : ''}
                        <div class="spw-loading" id="spw-loader" style="${isEditMode ? '' : 'display:none;'}">
                            ${isEditMode ? 'Loading widget fields...' : 'Select a widget or create a new one to begin editing.'}
                        </div>
                        <iframe class="spw-iframe" id="spw-frame"></iframe>
                    </div>
                </div>
            `;

            function restoreFrameAfterFailedSave() {
                if (widgetSaved) {
                    return;
                }
                loader.style.display = 'none';
                frame.style.display = 'block';
                setInsertEnabled(true);
            }

            function stopSaveWatch() {
                if (saveWatchInterval) {
                    window.clearInterval(saveWatchInterval);
                    saveWatchInterval = null;
                }
                if (saveWatchTimeout) {
                    window.clearTimeout(saveWatchTimeout);
                    saveWatchTimeout = null;
                }
            }

            function startSaveWatch() {
                stopSaveWatch();

                saveWatchInterval = window.setInterval(function () {
                    if (widgetSaved) {
                        stopSaveWatch();
                        return;
                    }
                    var doc = null;
                    try {
                        doc = frame.contentWindow && frame.contentWindow.document;
                    } catch (e) {
                        doc = null;
                    }
                    if (doc && doc.querySelector('.acf-error-message, .acf-field .acf-error, .acf-validation-error')) {
                        stopSaveWatch();
                        restoreFrameAfterFailedSave();
                    }
                }, 400);

                saveWatchTimeout = window.setTimeout(function () {
                    stopSaveWatch();
                    restoreFrameAfterFailedSave();
                }, 20000);
            }

            var messageHandler = function (event) {
                if (event.origin !== window.location.origin || event.source !== frame.contentWindow) {
                    return;
                }
                if (event.data && event.data.type === 'sp_widget_saved') {
                    var data = event.data;
                    if (data && data.id) {
                        widgetSaved = true;
                        stopSaveWatch();
                        invalidateWidgetPreview(data.id);
                        if (!isEditMode) {
                            insertWidgetShortcode(data.id);
                        } else {
                            refreshWidgetPreviews();
                        }
                        setTimeout(function () {
                            win.close();
                        }, 150);
                    }
                    window.removeEventListener('message', messageHandler);
                }
            };

            var win = editor.windowManager.open({
                title: isEditMode ? 'Edit Widget' : 'Insert Widget / Section',
                width: Math.min(1680, window.innerWidth - 48),
                height: Math.min(920, window.innerHeight - 48),
                body: [{type: 'container', html: html}],
                buttons: [
                    {
                        text: 'Cancel',
                        onclick: 'close'
                    },
                    {
                        text: isEditMode ? 'Save Changes' : 'Insert Widget',
                        subtype: 'primary',
                        name: 'spw_insert_btn',
                        disabled: !isEditMode,
                        onclick: function () {
                            handleModalAction();
                        }
                    }
                ]
            });

            window.addEventListener('message', messageHandler);

            win.on('close', function () {
                stopSaveWatch();
                window.removeEventListener('message', messageHandler);
            });

            var root = win.getEl().querySelector('[data-spw-root]');
            var frame = root.querySelector('#spw-frame');
            var loader = root.querySelector('#spw-loader');
            var insertBtn = win.find('#spw_insert_btn')[0];
            var picker = root.querySelector('#spw-picker');
            var widgetGrid = root.querySelector('#spw-widget-grid');
            var searchInput = root.querySelector('#spw-widget-search');
            var emptyState = root.querySelector('#spw-empty');
            var widgetsList = [];

            function setInsertEnabled(enabled) {
                var disabled = !enabled;

                if (insertBtn) {
                    if (typeof insertBtn.disabled === 'function') {
                        insertBtn.disabled(disabled);
                    } else {
                        insertBtn.disabled = disabled;
                    }
                }

                var footerButtons = win.getEl().querySelectorAll('button');
                footerButtons.forEach(function (button) {
                    var label = (button.textContent || '').trim();
                    if (label !== 'Insert Widget' && label !== 'Save Changes') {
                        return;
                    }

                    button.disabled = disabled;
                    button.setAttribute('aria-disabled', disabled ? 'true' : 'false');

                    var btnWrap = button.closest('.mce-btn');
                    if (btnWrap) {
                        btnWrap.classList.toggle('mce-disabled', disabled);
                    }
                });
            }

            function insertWidgetShortcode(id) {
                var shortcode = '[widget id="' + id + '"]';
                editor.focus();
                if (bookmark) {
                    editor.selection.moveToBookmark(bookmark);
                }

                try {
                    var current = editor.selection.getNode();
                    var selectedWidget = current && current.closest ? current.closest('.sp-editor-widget[data-widget-id]') : null;
                    if (selectedWidget) {
                        editor.selection.select(selectedWidget);
                        editor.selection.collapse(false);
                    }
                } catch (_) {}

                editor.insertContent(
                    shortcodesToPreviews(shortcode) +
                    '<span id="sp-widget-caret" data-mce-bogus="1">&#xFEFF;</span>'
                );

                var marker = editor.dom.get('sp-widget-caret');
                if (marker && marker.parentNode) {
                    var anchor = editor.getDoc().createTextNode('\u200B');
                    marker.parentNode.replaceChild(anchor, marker);
                    try {
                        editor.selection.setCursorLocation(anchor, 1);
                    } catch (_) {}
                }

                refreshWidgetPreviews();
                if (typeof editor.save === 'function') {
                    editor.save();
                }
                editor.fire('change');
            }

            function handleModalAction() {
                if (!isEditMode && selectedWidgetId && frame.style.display !== 'block') {
                    insertWidgetShortcode(selectedWidgetId);
                    win.close();
                    return;
                }

                var iframe = root.querySelector('#spw-frame');
                if (iframe && iframe.contentWindow) {
                    var doc = iframe.contentWindow.document;
                    var form = doc.querySelector('form.acf-form');
                    if (form) {
                        loader.style.display = 'flex';
                        loader.textContent = 'Saving changes...';
                        frame.style.display = 'none';
                        startSaveWatch();

                        var submitBtn = form.querySelector('input[type="submit"]') || form.querySelector('.acf-form-submit input');
                        if (submitBtn) {
                            submitBtn.click();
                        } else {
                            form.submit();
                        }
                        return;
                    }
                }

                if (!isEditMode && selectedWidgetId) {
                    insertWidgetShortcode(selectedWidgetId);
                }
                win.close();
            }

            function bindFooterActionFallback() {
                win.getEl().addEventListener('click', function (event) {
                    var button = event.target && event.target.closest ? event.target.closest('button') : null;
                    if (!button) return;

                    var label = (button.textContent || '').trim();
                    if (label !== 'Insert Widget' && label !== 'Save Changes') {
                        return;
                    }

                    if (button.disabled || button.getAttribute('aria-disabled') === 'true') {
                        return;
                    }

                    event.preventDefault();
                    event.stopPropagation();
                    if (typeof event.stopImmediatePropagation === 'function') {
                        event.stopImmediatePropagation();
                    }
                    handleModalAction();
                }, true);
            }

            function duplicateWidget(sourceId) {
                loader.style.display = 'flex';
                loader.textContent = 'Duplicating widget...';
                if (picker) picker.style.display = 'none';

                jQuery.ajax({
                    url: ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'sp_duplicate_widget',
                        nonce: widgetNonce(),
                        id: sourceId
                    },
                    success: function (res) {
                        if (res.success && res.data && res.data.id) {
                            selectedWidgetId = null;
                            setInsertEnabled(false);
                            loadWidgetInIframe(res.data.id, 'create');
                        } else {
                            loader.style.display = 'none';
                            if (picker) picker.style.display = 'block';
                            alert('Duplication error: ' + (res.data || 'Unknown error'));
                        }
                    },
                    error: function () {
                        loader.style.display = 'none';
                        if (picker) picker.style.display = 'block';
                        alert('Server connection error.');
                    }
                });
            }

            var createCard = null;

            function buildCreateCard() {
                if (createCard) {
                    return createCard;
                }

                createCard = document.createElement('div');
                createCard.className = 'spw-widget-card spw-card-new';
                createCard.setAttribute('role', 'button');
                createCard.tabIndex = 0;
                createCard.innerHTML = `
                    <span class="spw-new-cta">
                        <span class="spw-new-plus">+</span>
                        <span class="spw-new-label">Create new widget</span>
                        <span class="spw-new-hint">Start from a blank widget</span>
                    </span>
                    <span class="spw-new-form">
                        <input class="spw-input spw-new-title" type="text" placeholder="Widget title">
                        <span class="spw-new-actions">
                            <button type="button" class="spw-btn spw-new-create">Create</button>
                            <button type="button" class="spw-new-cancel">Cancel</button>
                        </span>
                    </span>
                `;

                var titleInput = createCard.querySelector('.spw-new-title');
                var createBtn = createCard.querySelector('.spw-new-create');
                var cancelBtn = createCard.querySelector('.spw-new-cancel');

                function submitCreate() {
                    var title = titleInput.value.trim();
                    if (!title) {
                        titleInput.focus();
                        return;
                    }

                    createBtn.disabled = true;
                    jQuery.ajax({
                        url: ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'sp_create_new_widget',
                            nonce: widgetNonce(),
                            title: title
                        },
                        success: function (res) {
                            createBtn.disabled = false;
                            if (res.success && res.data) {
                                selectedWidgetId = null;
                                setInsertEnabled(false);
                                titleInput.value = '';
                                createCard.classList.remove('is-editing');

                                loadWidgetInIframe(res.data.id, 'create');
                            } else {
                                alert('Creation error: ' + (res.data || 'Unknown error'));
                            }
                        },
                        error: function () {
                            createBtn.disabled = false;
                            alert('Server connection error.');
                        }
                    });
                }

                createCard.addEventListener('click', function (event) {
                    if (event.target.closest('.spw-new-form')) {
                        return;
                    }
                    createCard.classList.add('is-editing');
                    titleInput.focus();
                });
                createBtn.addEventListener('click', submitCreate);
                cancelBtn.addEventListener('click', function (event) {
                    event.stopPropagation();
                    titleInput.value = '';
                    createCard.classList.remove('is-editing');
                });
                titleInput.addEventListener('keydown', function (event) {
                    if (event.key === 'Enter') {
                        event.preventDefault();
                        submitCreate();
                    }
                    if (event.key === 'Escape') {
                        createCard.classList.remove('is-editing');
                    }
                });

                return createCard;
            }

            function renderWidgets(filter) {
                if (!widgetGrid) return;

                var query = String(filter || '').trim().toLowerCase();
                var items = widgetsList.filter(function (w) {
                    return !query || [w.title, w.type_label, w.id].join(' ').toLowerCase().indexOf(query) !== -1;
                });

                widgetGrid.innerHTML = '';
                widgetGrid.appendChild(buildCreateCard());

                items.forEach(function (w) {
                    var card = document.createElement('div');
                    card.className = 'spw-widget-card' + (String(w.id) === String(selectedWidgetId) ? ' is-active' : '');
                    card.setAttribute('data-widget-id', w.id);
                    card.setAttribute('role', 'button');
                    card.tabIndex = 0;
                    card.innerHTML = `
                        <span class="spw-widget-preview">
                            ${w.preview_url ? '<img src="' + esc(w.preview_url) + '" alt="">' : '<span>No Preview</span>'}
                            <span class="spw-card-actions">
                                <button type="button" class="spw-act spw-act--insert" data-act="insert" title="Insert this widget into the editor">Insert</button>
                            </span>
                        </span>
                        <span class="spw-widget-meta">
                            <span class="spw-widget-name">${esc(w.title)}</span>
                            <span class="spw-widget-line">
                                <span class="spw-widget-type">${esc(w.type_label || 'Widget')}</span>
                                <span style="display:inline-flex;align-items:center;gap:10px;">
                                    <button type="button" class="spw-dup-link" data-act="duplicate" title="Create a copy of this widget and edit its content">Duplicate</button>
                                    <span class="spw-widget-id">#${esc(w.id)}</span>
                                </span>
                            </span>
                        </span>
                    `;

                    card.addEventListener('click', function (event) {
                        var action = event.target.closest('[data-act]');
                        if (action) {
                            event.stopPropagation();
                            if (action.getAttribute('data-act') === 'insert') {
                                insertWidgetShortcode(w.id);
                                win.close();
                            } else {
                                duplicateWidget(w.id);
                            }
                            return;
                        }

                        selectedWidgetId = w.id;
                        frame.src = 'about:blank';
                        frame.style.display = 'none';
                        loader.style.display = 'none';
                        if (picker) picker.style.display = 'block';
                        setInsertEnabled(true);
                        renderWidgets(searchInput ? searchInput.value : '');
                    });
                    card.addEventListener('dblclick', function () {
                        selectedWidgetId = w.id;
                        insertWidgetShortcode(selectedWidgetId);
                        win.close();
                    });
                    card.addEventListener('keydown', function (event) {
                        if (event.key === 'Enter' && !event.target.closest('[data-act]')) {
                            insertWidgetShortcode(w.id);
                            win.close();
                        }
                    });
                    widgetGrid.appendChild(card);
                });

                if (emptyState) {
                    emptyState.style.display = items.length ? 'none' : 'block';
                }
            }

            setInsertEnabled(isEditMode);
            bindFooterActionFallback();

            function loadWidgetInIframe(id, mode) {
                loader.style.display = 'flex';
                loader.textContent = 'Loading widget fields...';
                frame.style.display = 'none';
                if (picker) picker.style.display = 'none';

                frame.src = iframeBaseUrl + '?action=sp_edit_widget_iframe&id=' + id + '&mode=' + encodeURIComponent(mode || 'insert') + '&nonce=' + encodeURIComponent(widgetNonce());

                frame.onload = function () {
                    loader.style.display = 'none';
                    frame.style.display = 'block';
                    setInsertEnabled(true);
                };
            }

            if (!isEditMode) {
                if (Array.isArray(sharedStore.catalog)) {
                    widgetsList = sharedStore.catalog;
                } else {
                    if (!sharedStore.catalogRequest) {
                        sharedStore.catalogRequest = jQuery.ajax({
                            url: ajaxUrl,
                            type: 'POST',
                            dataType: 'json',
                            data: { action: 'sp_get_widgets_list', nonce: widgetNonce() }
                        }).done(function (res) {
                            if (res && res.success && Array.isArray(res.data)) {
                                sharedStore.catalog = res.data;
                                if (catalogCacheKey) {
                                    try { window.sessionStorage.setItem(catalogCacheKey, JSON.stringify(res.data)); } catch (_) {}
                                }
                            }
                        }).always(function () {
                            sharedStore.catalogRequest = null;
                        });
                    }

                    sharedStore.catalogRequest.done(function () {
                        widgetsList = Array.isArray(sharedStore.catalog) ? sharedStore.catalog : [];
                        renderWidgets(searchInput ? searchInput.value : '');
                    });
                }

                renderWidgets('');

                if (searchInput) {
                    searchInput.addEventListener('input', function () {
                        renderWidgets(searchInput.value);
                    });
                }
            } else {
                loadWidgetInIframe(activeId, 'edit');
            }
        }

        editor.on('PreInit', function () {
            if (editor.schema && typeof editor.schema.addValidChildren === 'function') {
                editor.schema.addValidChildren(
                    '+span[div|p|section|article|figure|figcaption|ul|ol|li|table|h1|h2|h3|h4|h5|h6|blockquote|form|iframe|aside|header|footer]'
                );
            }
        });

        editor.on('PreProcess', function (event) {
            if (event && event.node && event.node !== editor.getBody()) {
                convertPreviewsToShortcodesIn(event.node);
            }
        });

        editor.on('BeforeSetContent', function (event) {
            if (event && typeof event.content === 'string') {
                event.content = shortcodesToPreviews(event.content);
            }
        });

        editor.on('SetContent', function () {
            restoreWidgetEditHandles();
            ensureCaretPadding();
            refreshWidgetPreviews();
        });

        editor.on('NodeChange', function () {
            restoreWidgetEditHandles();
            ensureCaretPadding();
        });

        editor.on('PostProcess', function (event) {
            if (event && event.get && typeof event.content === 'string') {
                event.content = previewsToShortcodes(event.content);
            }
        });

        editor.on('GetContent', function (event) {
            if (event && typeof event.content === 'string') {
                event.content = previewsToShortcodes(event.content);
            }
        });

        editor.on('SaveContent', function (event) {
            if (event && typeof event.content === 'string') {
                event.content = previewsToShortcodes(event.content);
            }
        });

        editor.on('ExecCommand', function (event) {
            var map = {
                JustifyLeft: 'left',
                JustifyCenter: 'center',
                JustifyRight: 'right'
            };
            var align = map[event && event.command];
            if (align && applyAlignToSelectedWidgets(align)) {
                editor.fire('change');
            }
        });

        editor.on('init', function () {
            if (editor.dom && typeof editor.dom.addStyle === 'function') {
                editor.dom.addStyle(editorPreviewStyles());
            }

            var body = editor.getBody ? editor.getBody() : null;
            if (body) {
                body.addEventListener('mousedown', function (event) {
                    var target = event.target;
                    if (target && target.nodeType === 3) {
                        target = target.parentNode;
                    }
                    if (!target || typeof target.closest !== 'function') {
                        if (!event.shiftKey && !event.metaKey && !event.ctrlKey) {
                            body.querySelectorAll('.sp-editor-widget.is-selected').forEach(function (node) {
                                node.classList.remove('is-selected');
                            });
                        }
                        return;
                    }

                    var button = target.closest('.sp-editor-widget__edit[data-sp-widget-edit]');
                    var widget = target.closest('.sp-editor-widget[data-widget-id]');

                    if (!button && !widget) {
                        if (!event.shiftKey && !event.metaKey && !event.ctrlKey) {
                            body.querySelectorAll('.sp-editor-widget.is-selected').forEach(function (node) {
                                node.classList.remove('is-selected');
                            });
                        }
                        return;
                    }

                    if (button) {
                        event.preventDefault();
                        event.stopPropagation();
                        if (typeof event.stopImmediatePropagation === 'function') {
                            event.stopImmediatePropagation();
                        }
                    }

                    if (widget) {
                        if (event.shiftKey || event.metaKey || event.ctrlKey) {
                            event.preventDefault();
                            widget.classList.toggle('is-selected');
                        } else {
                            body.querySelectorAll('.sp-editor-widget.is-selected').forEach(function (node) {
                                if (node !== widget) {
                                    node.classList.remove('is-selected');
                                }
                            });
                            widget.classList.add('is-selected');

                            if (!button) {
                                try {
                                    editor.selection.select(widget);
                                } catch (_) {}
                            }
                        }
                    }

                    var id = parseInt((button || widget).getAttribute(button ? 'data-sp-widget-edit' : 'data-widget-id'), 10);
                    if (!button) {
                        return;
                    }

                    if (id) {
                        open(id);
                    }
                }, true);

                body.addEventListener('keydown', function (event) {
                    if ((event.key === ' ' || event.key === 'Spacebar') && insertTextAfterSelectedWidget('\u00a0')) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                }, true);
            }

            refreshWidgetPreviews();
        });

        editor.addButton('sp_widgets', {
            image: iconDataUri,
            tooltip: 'Widgets for Editor',
            onclick: function () {
                open();
            }
        });

    });
})();
