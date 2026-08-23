(function () {
    'use strict';

    tinymce.PluginManager.add('custom_lists', function (editor) {

        var CUSTOM_LIST_STYLES = [
            {
                name: 'sparkle',
                title: 'Sparkle',
                listClass: 'list-sparkle',
                itemClass: 'item-sparkle',
                svg: '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 16 16"><path fill="#487de4" d="m8 0 .6 3.4a5 5 0 0 0 4 4L16 8l-3.4.6a5 5 0 0 0-4 4L8 16l-.6-3.4a5 5 0 0 0-4-4L0 8l3.4-.6a5 5 0 0 0 4-4z"/></svg>'
            },
            {
                name: 'check',
                title: 'Check',
                listClass: 'list-check',
                itemClass: 'item-check',
                svg: '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 20 20"><path fill="#039b61" d="M10 20a10 10 0 0 0 7-17 10 10 0 0 0-17 7 10 10 0 0 0 10 10"/><path stroke="#fff" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m6 10 3 3 6-6"/></svg>'
            },
            {
                name: 'cancel V1',
                title: 'Cancel V1',
                listClass: 'list-cancel',
                itemClass: 'item-cancel',
                svg: '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 20 20"><path fill="#f44f73" d="M10 20a10 10 0 1 1 0-20 10 10 0 0 1 0 20m0-18.3a8.3 8.3 0 1 0 0 16.6 8.3 8.3 0 0 0 0-16.6"/><path fill="#f44f73" d="m17.4 16.2-1.3 1.3L2.6 4.1l1.3-1.3z"/></svg>'
            },
            {
                name: 'cancel V2',
                title: 'Cancel V2',
                listClass: 'list-cancel list-cancel--mode',
                itemClass: 'item-cancel item-cancel--mode',
                svg: '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24"><path fill="#c8ccdf" d="M12 22a10 10 0 1 1 0-20 10 10 0 0 1 0 20m0-18.3a8.3 8.3 0 1 0 0 16.6 8.3 8.3 0 0 0 0-16.6"/><path fill="#c8ccdf" d="m19.4 18.2-1.3 1.3L4.6 6.1l1.3-1.3z"/></svg>'
            },
            {
                name: 'info',
                title: 'Info',
                listClass: 'list-info',
                itemClass: 'item-info',
                svg: '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 18 18"><path fill="#487de4" fill-rule="evenodd" d="M9 18A9 9 0 1 1 9 0a9 9 0 0 1 0 18" clip-rule="evenodd"/><path stroke="#fff" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5q-.2 0-.2.3A.3.3 0 1 0 9 5m0 4v5"/></svg>'
            },
            {
                name: 'number-box',
                title: 'Number Box',
                listClass: 'list-number-box',
                itemClass: '',
                listTag: 'ol',
                svg: '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 20 20"><rect width="12" height="12" x="1" y="1" fill="#487de4" rx="1"/><path fill="#fff" d="M7.7 10H6.4V5.3l-1.5.5v-1l2.6-1h.2z"/><path stroke="#5e687a" stroke-linecap="round" stroke-width="1.6" d="M16 4h3M16 10h3M1.8 17h17"/></svg>'
            },
            {
                name: 'number-light',
                title: 'Light Numbers',
                listClass: 'list-number-light',
                itemClass: '',
                listTag: 'ol',
                svg: '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 20 20"><path fill="#487de4" d="M3.8 6H2.6V2.2l-1.2.4v-1l2.2-.8h.2zm.9 6H1.2v-.8l1.6-1.7q.4-.4.5-.7.2-.3.2-.6 0-.35-.2-.55-.15-.25-.5-.25-.3 0-.5.3-.2.25-.2.7H1.1q0-.5.2-.9.25-.4.65-.65.45-.25.95-.25.75 0 1.2.4.45.35.45 1.05 0 .4-.2.8-.2.4-.75 1l-1.1 1.15h2.2zm-2.4 4h.55q.4 0 .6-.2.2-.2.2-.55t-.2-.55q-.2-.2-.55-.2-.3 0-.5.2-.25.2-.25.5H1.1q0-.45.25-.8.2-.35.6-.55.4-.2.9-.2.85 0 1.35.4.5.4.5 1.15 0 .35-.25.7-.2.3-.6.5.45.15.7.5.25.35.25.8 0 .75-.55 1.2-.5.45-1.4.45-.8 0-1.35-.45-.5-.4-.5-1.15h1.05q0 .35.25.55.25.25.6.25.4 0 .65-.2.2-.25.2-.6 0-.85-.95-.85H2.3z"/><path stroke="#5e687a" stroke-linecap="round" stroke-width="1.6" d="M8 4h11M8 10h11M8 16h11"/></svg>'
            }
        ];

        function getClassTokens(className) {
            return String(className || '').split(/\s+/).filter(Boolean);
        }

        function collectClassTokens(type) {
            var out = [];
            for (var i = 0; i < CUSTOM_LIST_STYLES.length; i++) {
                var tokens = getClassTokens(CUSTOM_LIST_STYLES[i][type]);
                for (var j = 0; j < tokens.length; j++) {
                    if (out.indexOf(tokens[j]) === -1) {
                        out.push(tokens[j]);
                    }
                }
            }
            return out;
        }

        var LIST_CLASSES = collectClassTokens('listClass');
        var ITEM_CLASSES = collectClassTokens('itemClass');

        function hasClassSet(node, className) {
            var tokens = getClassTokens(className);
            if (!tokens.length) {
                return false;
            }
            for (var i = 0; i < tokens.length; i++) {
                if (!editor.dom.hasClass(node, tokens[i])) {
                    return false;
                }
            }
            return true;
        }

        function removeClassTokens(node, tokens) {
            for (var i = 0; i < tokens.length; i++) {
                editor.dom.removeClass(node, tokens[i]);
            }
        }

        function addClassSet(node, className) {
            var tokens = getClassTokens(className);
            for (var i = 0; i < tokens.length; i++) {
                editor.dom.addClass(node, tokens[i]);
            }
        }

        function getSelectedListItems() {
            var rng = editor.selection.getRng();
            var out = [];
            var seen = typeof Set !== 'undefined' ? new Set() : null;

            var blocks = editor.selection.getSelectedBlocks();
            if (blocks && blocks.length) {
                for (var i = 0; i < blocks.length; i++) {
                    var b = blocks[i];
                    if (!b || b.nodeType !== 1) continue;

                    if (b.nodeName.toLowerCase() === 'li') {
                        if (seen) {
                            if (!seen.has(b)) { seen.add(b); out.push(b); }
                        } else {
                            if (out.indexOf(b) === -1) out.push(b);
                        }
                    } else {
                        var parentLi = editor.dom.getParent(b, 'li');
                        if (parentLi) {
                            if (seen) {
                                if (!seen.has(parentLi)) { seen.add(parentLi); out.push(parentLi); }
                            } else {
                                if (out.indexOf(parentLi) === -1) out.push(parentLi);
                            }
                        }
                    }
                }
            }

            if (out.length === 0) {
                var node = editor.selection.getNode();
                var parentLi = editor.dom.getParent(node, 'li');
                if (parentLi) {
                    out.push(parentLi);
                }
            }

            return out;
        }

        function getSelectedLists() {
            var items = getSelectedListItems();
            var out = [];
            var seen = typeof Set !== 'undefined' ? new Set() : null;

            for (var i = 0; i < items.length; i++) {
                var list = editor.dom.getParent(items[i], 'ul,ol');
                if (list) {
                    if (seen) {
                        if (!seen.has(list)) { seen.add(list); out.push(list); }
                    } else {
                        if (out.indexOf(list) === -1) out.push(list);
                    }
                }
            }

            if (out.length === 0) {
                var node = editor.selection.getNode();
                var list = editor.dom.getParent(node, 'ul,ol');
                if (list) {
                    out.push(list);
                }
            }

            return out;
        }

        function getActiveListClass() {
            var lists = getSelectedLists();
            if (lists && lists.length) {
                var list = lists[0];
                for (var j = 0; j < CUSTOM_LIST_STYLES.length; j++) {
                    if (hasClassSet(list, CUSTOM_LIST_STYLES[j].listClass)) {
                        return CUSTOM_LIST_STYLES[j].listClass;
                    }
                }
            }
            return null;
        }

        function getActiveItemClass() {
            var items = getSelectedListItems();
            if (items && items.length) {
                var item = items[0];
                for (var j = 0; j < CUSTOM_LIST_STYLES.length; j++) {
                    if (hasClassSet(item, CUSTOM_LIST_STYLES[j].itemClass)) {
                        return CUSTOM_LIST_STYLES[j].itemClass;
                    }
                }
            }
            return null;
        }

        function applyListClass(className, listTag) {
            var desiredTag = listTag || 'ul';
            var lists = getSelectedLists();
            if (!lists.length) {
                editor.execCommand(desiredTag === 'ol' ? 'InsertOrderedList' : 'InsertUnorderedList');
                lists = getSelectedLists();
            }
            if (!lists.length) {
                return;
            }
            editor.undoManager.transact(function () {
                for (var i = 0; i < lists.length; i++) {
                    var list = lists[i];
                    removeClassTokens(list, LIST_CLASSES);
                    if (className) {
                        addClassSet(list, className);
                        if (list.nodeName.toLowerCase() !== desiredTag) {
                            editor.dom.rename(list, desiredTag);
                        }
                    }
                }
            });
            editor.nodeChanged();
        }

        function applyItemClass(className) {
            var items = getSelectedListItems();
            if (!items.length) {
                editor.execCommand('InsertUnorderedList');
                items = getSelectedListItems();
            }
            if (!items.length) {
                return;
            }
            editor.undoManager.transact(function () {
                for (var i = 0; i < items.length; i++) {
                    var item = items[i];
                    removeClassTokens(item, ITEM_CLASSES);
                    if (className) {
                        addClassSet(item, className);
                    }
                }
            });
            editor.nodeChanged();
        }

        function changeListItemLevel(direction) {
            var items = getSelectedListItems();
            if (!items.length) {
                return;
            }
            editor.undoManager.transact(function () {
                editor.execCommand(direction === 'out' ? 'Outdent' : 'Indent');
            });
            editor.nodeChanged();
        }

        function openDialog() {
            var activeListClass = getActiveListClass();
            var activeItemClass = getActiveItemClass();

            var listHtml = '';
            var itemHtml = '';

            for (var i = 0; i < CUSTOM_LIST_STYLES.length; i++) {
                var style = CUSTOM_LIST_STYLES[i];

                var isListActive = (activeListClass === style.listClass);
                var isItemActive = (activeItemClass === style.itemClass);

                var listBtnClass = 'custom-list-dialog__btn' + (isListActive ? ' active' : '');
                var itemBtnClass = 'custom-list-dialog__btn' + (isItemActive ? ' active' : '');

                listHtml += '<button type="button" class="' + listBtnClass + '" data-action="list" data-style="' + style.name + '" title="' + style.title + '">' + style.svg + '<span>' + style.title + '</span></button>';
                if (style.itemClass) {
                    itemHtml += '<button type="button" class="' + itemBtnClass + '" data-action="item" data-style="' + style.name + '" title="' + style.title + '">' + style.svg + '<span>' + style.title + '</span></button>';
                }
            }

            var htmlString = `
                <style>
                    .custom-list-dialog {
                        padding: 16px 18px 18px;
                        background: var(--color-surface, #fff);
                        color: var(--color-text, #1a1f24);
                        font-family: var(--sp-admin-font, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif);
                        box-sizing: border-box;
                    }
                    .custom-list-dialog * {
                        box-sizing: border-box;
                    }
                    .custom-list-dialog__columns {
                        display: flex;
                        gap: 22px;
                    }
                    .custom-list-dialog__column {
                        flex: 1;
                    }
                    .custom-list-dialog__section-title {
                        font-size: 10px;
                        font-weight: 700;
                        color: var(--color-text-2, #525b66);
                        margin-bottom: 12px;
                        text-transform: uppercase;
                        letter-spacing: 0.8px;
                        border-bottom: 1px solid var(--color-border, #e7eaee);
                        padding-bottom: 6px;
                    }
                    .custom-list-dialog__grid {
                        display: grid;
                        grid-template-columns: repeat(2, 1fr);
                        gap: 10px;
                    }
                    .custom-list-dialog__btn {
                        display: flex;
                        flex-direction: column;
                        align-items: center;
                        justify-content: center;
                        height: 58px;
                        border: 1px solid var(--color-border, #e7eaee);
                        border-radius: var(--sp-admin-radius-sm, 9px);
                        background: var(--color-surface, #fff);
                        cursor: pointer;
                        transition: all 0.15s ease-in-out;
                        padding: 6px;
                        box-shadow: var(--sp-admin-shadow-xs, 0 1px 2px rgb(26 31 36 / 4%));
                    }
                    .custom-list-dialog__btn:hover {
                        border-color: var(--color-accent, #3858e9);
                        background: var(--color-surface-alt, #f8fafc);
                        transform: translateY(-1px);
                        box-shadow: var(--sp-admin-shadow, 0 8px 24px rgb(26 31 36 / 5%));
                    }
                    .custom-list-dialog__btn.active {
                        border-color: var(--color-accent, #3858e9);
                        background: var(--sp-admin-accent-softer, #f7f8ff);
                        box-shadow: var(--sp-admin-focus, 0 0 0 3px rgb(56 88 233 / 18%));
                    }
                    .custom-list-dialog__btn:focus-visible {
                        border-color: var(--color-accent, #3858e9);
                        box-shadow: var(--sp-admin-focus, 0 0 0 3px rgb(56 88 233 / 18%));
                        outline: 0;
                    }
                    .custom-list-dialog__btn svg {
                        width: 22px;
                        height: 22px;
                        display: block;
                        margin-bottom: 5px;
                    }
                    .custom-list-dialog__btn span {
                        font-size: 10px;
                        color: var(--color-text, #1a1f24);
                        font-weight: 600;
                        text-align: center;
                        white-space: nowrap;
                        overflow: hidden;
                        text-overflow: ellipsis;
	                        width: 100%;
	                    }
	                    .custom-list-dialog__structure {
	                        display: grid;
	                        grid-template-columns: repeat(2, minmax(0, 1fr));
	                        gap: 10px;
	                        margin-top: 14px;
	                        padding-top: 12px;
	                        border-top: 1px solid var(--color-border, #e7eaee);
	                    }
	                    .custom-list-dialog__structure-btn {
	                        display: flex;
	                        align-items: center;
	                        justify-content: center;
	                        gap: 7px;
	                        height: 38px;
	                        border: 1px solid var(--color-border, #e7eaee);
	                        border-radius: var(--sp-admin-radius-sm, 9px);
	                        background: var(--color-surface, #fff);
	                        color: var(--color-text, #1a1f24);
	                        font-size: 12px;
	                        font-weight: 600;
	                        cursor: pointer;
	                        transition: all 0.15s ease-in-out;
	                    }
	                    .custom-list-dialog__structure-btn:hover {
	                        border-color: var(--color-accent, #3858e9);
	                        background: var(--color-surface-alt, #f8fafc);
	                    }
	                    .custom-list-dialog__structure-btn:focus-visible {
	                        border-color: var(--color-accent, #3858e9);
	                        box-shadow: var(--sp-admin-focus, 0 0 0 3px rgb(56 88 233 / 18%));
	                        outline: 0;
	                    }
	                    .custom-list-dialog__structure-btn svg {
	                        width: 16px;
	                        height: 16px;
	                        display: block;
	                    }
	                </style>
	                <div class="custom-list-dialog" data-sp-admin-component>
	                    <div class="custom-list-dialog__columns">
                        <div class="custom-list-dialog__column">
                            <div class="custom-list-dialog__section-title">Entire List</div>
                            <div class="custom-list-dialog__grid">
                                ${listHtml}
                            </div>
                        </div>
                        <div class="custom-list-dialog__column">
                            <div class="custom-list-dialog__section-title">Single Item (LI)</div>
                            <div class="custom-list-dialog__grid">
                                ${itemHtml}
	                            </div>
	                        </div>
	                    </div>
	                    <div class="custom-list-dialog__structure">
	                        <button type="button" class="custom-list-dialog__structure-btn" data-action="outdent" title="Move selected item one level up">
	                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 18 18"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M7 5 3 9l4 4M3.5 9H15M10 4h5M10 14h5"/></svg>
	                            <span>Outdent item</span>
	                        </button>
	                        <button type="button" class="custom-list-dialog__structure-btn" data-action="indent" title="Make selected item nested">
	                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 18 18"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="m11 5 4 4-4 4M3 9h11.5M3 4h5M3 14h5"/></svg>
	                            <span>Indent item</span>
	                        </button>
	                    </div>
	                </div>
	            `;

            var win = editor.windowManager.open({
                title: 'List Styling',
                html: htmlString.trim(),
                width: 520,
                height: 390,
                buttons: [
                    {
                        text: 'Reset Entire List',
                        onclick: function() {
                            applyListClass(null);
                            win.close();
                        }
                    },
                    {
                        text: 'Reset Item',
                        onclick: function() {
                            applyItemClass(null);
                            win.close();
                        }
                    },
                    { text: 'Cancel', onclick: 'close' }
                ]
            });

            var winEl = win.getEl();
            if (winEl) {
                var buttons = winEl.querySelectorAll('.custom-list-dialog__btn, .custom-list-dialog__structure-btn');
                for (var i = 0; i < buttons.length; i++) {
                    buttons[i].addEventListener('click', function (e) {
                        var target = e.currentTarget;
                        var action = target.getAttribute('data-action');
                        var styleName = target.getAttribute('data-style');

                        if (action === 'indent' || action === 'outdent') {
                            changeListItemLevel(action === 'outdent' ? 'out' : 'in');
                            win.close();
                            return;
                        }

                        var styleObj = null;
                        for (var j = 0; j < CUSTOM_LIST_STYLES.length; j++) {
                            if (CUSTOM_LIST_STYLES[j].name === styleName) {
                                styleObj = CUSTOM_LIST_STYLES[j];
                                break;
                            }
                        }

                        if (styleObj) {
                            if (action === 'list') {
                                applyListClass(styleObj.listClass, styleObj.listTag || 'ul');
                            } else {
                                applyItemClass(styleObj.itemClass);
                            }
                        }
                        win.close();
                    });
                }
            }
        }

        var svg = `
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#2ca9bc" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <line x1="9" y1="6" x2="20" y2="6" stroke="#000" />
              <line x1="9" y1="12" x2="20" y2="12" stroke="#000" />
              <line x1="9" y1="18" x2="20" y2="18" stroke="#000" />
              <path d="M4.5 3.5C4.5 4.88 4.88 5.5 5.5 5.5C4.88 5.5 4.5 6.12 4.5 7.5C4.5 6.12 4.12 5.5 3.5 5.5C4.12 5.5 4.5 4.88 4.5 3.5Z" fill="#3b82f6" stroke="none" />
              <circle cx="4.5" cy="12" r="2.5" fill="#00a86b" stroke="none" />
              <path d="M3.7 12L4.3 12.6L5.3 11.4" stroke="#fff" stroke-width="0.8" fill="none" />
              <circle cx="4.5" cy="18" r="2.5" fill="#ef4444" stroke="none" />
            </svg>
        `;
        var iconDataUri = 'data:image/svg+xml;utf8,' + encodeURIComponent(svg.trim());

        editor.addButton('custom_lists', {
            image: iconDataUri,
            tooltip: 'Custom Lists',
            onclick: openDialog,
            onpostrender: function () {
                var btn = this;
                editor.on('NodeChange', function (e) {
                    var lists = getSelectedLists();
                    var hasCustomList = false;
                    for (var i = 0; i < lists.length; i++) {
                        for (var j = 0; j < LIST_CLASSES.length; j++) {
                            if (editor.dom.hasClass(lists[i], LIST_CLASSES[j])) {
                                hasCustomList = true;
                                break;
                            }
                        }
                    }
                    var items = getSelectedListItems();
                    var hasCustomItem = false;
                    for (var i = 0; i < items.length; i++) {
                        for (var j = 0; j < ITEM_CLASSES.length; j++) {
                            if (editor.dom.hasClass(items[i], ITEM_CLASSES[j])) {
                                hasCustomItem = true;
                                break;
                            }
                        }
                    }
                    btn.active(hasCustomList || hasCustomItem);
                });
            }
        });
    });
})();
