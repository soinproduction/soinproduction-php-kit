(function () {
    'use strict';

    tinymce.PluginManager.add('sp_editor_row', function (editor) {
        var svg = `
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32">
              <path fill="#3858e9" d="M4 24h24v2H4zm22-6H6v-4h20zm2 0v-4a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v4a2 2 0 0 0 2 2h20a2 2 0 0 0 2-2M4 6h24v2H4z"/>
              <path fill="transparent" d="M0 0h32v32H0z" transform="rotate(90 16 16)"/>
            </svg>
        `;
        var iconDataUri = 'data:image/svg+xml;utf8,' + encodeURIComponent(svg);

        editor.on('init', function () {
            var doc = editor.getDoc();
            if (!doc) {
                return;
            }
            var id = 'sp-editor-row-styles';
            if (doc.getElementById(id)) {
                return;
            }
            var style = doc.createElement('style');
            style.id = id;
            style.textContent = `
                .sp-editor-inline-row {
                    display: flex;
                    align-items: center;
                    flex-wrap: wrap;
                    box-sizing: border-box;
                    width: 100% !important;
                    max-width: 100%;
                    min-height: 4.4rem;
                    margin: .6rem 0;
                    padding: .8rem;
                    border: .1rem dashed rgba(72, 125, 228, .422);
                    border-radius: 0;
                    background: rgba(72, 125, 228, .04);
                    vertical-align: middle;
                }
                .sp-editor-inline-row[style*="text-align: center"],
                .sp-editor-inline-row[style*="text-align:center"] {
                    justify-content: center;
                }
                .sp-editor-inline-row[style*="text-align: right"],
                .sp-editor-inline-row[style*="text-align:right"] {
                    justify-content: flex-end;
                }
                .sp-editor-inline-row[style*="text-align: left"],
                .sp-editor-inline-row[style*="text-align:left"] {
                    justify-content: flex-start;
                }
                .sp-editor-inline-row.is-selected {
                    outline: .2rem solid rgba(72, 125, 228, .56);
                    outline-offset: .2rem;
                }
                .sp-editor-inline-row[data-mce-selected] {
                    outline: .2rem solid rgba(72, 125, 228, .56);
                    outline-offset: .2rem;
                }
                .sp-editor-inline-row:empty::before {
                    content: "Row";
                    color: #94a3b8;
                    font-size: 1.2rem;
                    font-weight: 700;
                    letter-spacing: .04em;
                    text-transform: uppercase;
                    pointer-events: none;
                }
                p[data-sp-row-caret-line="1"] {
                    display: block !important;
                    box-sizing: border-box !important;
                    width: 100% !important;
                    min-width: 100% !important;
                    min-height: 1.6em !important;
                    line-height: 1.6 !important;
                    margin: .4rem 0 !important;
                    padding: 0 !important;
                }
            `;
            doc.head.appendChild(style);

            var body = editor.getBody();
            if (body && !body._spRowCaretInstalled) {
                body._spRowCaretInstalled = true;
                body.addEventListener('mousedown', function (event) {
                    var target = event.target;
                    var row = target && target.classList && target.classList.contains('sp-editor-inline-row') ? target : null;
                    if (!row || !row.hasAttribute('data-editor-row')) {
                        return;
                    }

                    event.preventDefault();

                    var x = event.clientX;
                    var ref = null;
                    Array.prototype.slice.call(row.children).some(function (child) {
                        var rect = child.getBoundingClientRect();
                        if (x < rect.left + rect.width / 2) {
                            ref = child;
                            return true;
                        }
                        return false;
                    });

                    var d = editor.getDoc();
                    var anchor;
                    if (ref) {
                        anchor = (ref.previousSibling && ref.previousSibling.nodeType === 3)
                            ? ref.previousSibling
                            : row.insertBefore(d.createTextNode('\u200B'), ref);
                    } else {
                        anchor = (row.lastChild && row.lastChild.nodeType === 3)
                            ? row.lastChild
                            : row.appendChild(d.createTextNode('\u200B'));
                    }

                    try {
                        editor.selection.setCursorLocation(anchor, anchor.nodeValue.length);
                        editor.focus();
                    } catch (_) {}
                    editor.nodeChanged();
                }, true);
            }
        });

        function stripZeroWidth(event) {
            if (event && typeof event.content === 'string') {
                event.content = event.content
                    .replace(/\u200B/g, '')
                    .replace(/\sdata-sp-row-caret-line=(["'])1\1/g, '');
            }
        }
        editor.on('GetContent SaveContent', stripZeroWidth);
        editor.on('PostProcess', function (event) {
            if (event && event.get) {
                stripZeroWidth(event);
            }
        });

        function copyAlignment(src, dest) {
            if (!src || !dest) return;
            if (src.style && src.style.textAlign) {
                dest.style.textAlign = src.style.textAlign;
            }
            var mceStyle = src.getAttribute('data-mce-style');
            if (mceStyle) {
                dest.setAttribute('data-mce-style', mceStyle);
            }
            if (src.classList) {
                ['aligncenter', 'alignleft', 'alignright'].forEach(function (cls) {
                    if (src.classList.contains(cls)) {
                        dest.classList.add(cls);
                    }
                });
            }
        }

        function splitParagraphAt(p, child) {

            if (!p || p.nodeName !== 'P' || !child || child.parentNode !== p) {

                return null;
            }
            var doc = p.ownerDocument || document;
            var parent = p.parentNode;
            var pAfter = doc.createElement('p');

            copyAlignment(p, pAfter);

            var cur = child;
            var movedCount = 0;
            while (cur) {
                var temp = cur.nextSibling;

                safeMoveNode(pAfter, cur);
                cur = temp;
                movedCount++;
            }

            parent.insertBefore(pAfter, p.nextSibling);

            return pAfter;
        }

        function safeMoveNode(targetParent, node, beforeRef) {
            var nodeDesc = node.nodeType === 1 ? (node.tagName + '.' + Array.prototype.slice.call(node.classList).join('.') + ' [widget-id=' + node.getAttribute('data-widget-id') + ']') : ('TextNode("' + node.nodeValue.replace(/\u200B/g, '\\u200B') + '")');

            var isCeFalse = node.nodeType === 1 && node.getAttribute('contenteditable') === 'false';
            var hasNonEditableClass = node.nodeType === 1 && node.classList && node.classList.contains('mceNonEditable');
            if (isCeFalse) {
                node.setAttribute('contenteditable', 'true');
                node.setAttribute('data-mce-contenteditable', 'true');
            }
            if (hasNonEditableClass) {
                node.classList.remove('mceNonEditable');
            }
            if (beforeRef) {
                targetParent.insertBefore(node, beforeRef);
            } else {
                targetParent.appendChild(node);
            }
            if (hasNonEditableClass) {
                node.classList.add('mceNonEditable');
            }
            if (isCeFalse) {
                node.setAttribute('contenteditable', 'false');
                node.setAttribute('data-mce-contenteditable', 'false');
            }

        }

        function isParagraphEmpty(p) {
            if (!p || p.nodeName !== 'P') return false;
            var text = p.textContent.replace(/\u200B/g, '').trim();
            if (text !== '') return false;
            var children = p.querySelectorAll('*');
            for (var i = 0; i < children.length; i++) {
                if (children[i].nodeName !== 'BR' || !children[i].hasAttribute('data-mce-bogus')) {
                    return false;
                }
            }
            return true;
        }

        function isEditorRow(node) {
            return node && node.closest ? node.closest('.sp-editor-inline-row[data-editor-row], .row[data-editor-row]') : null;
        }

        function nodeAsEditorRow(node) {
            if (!node || node.nodeType !== 1) {
                return null;
            }
            if (node.matches && node.matches(ROW_SELECTOR)) {
                return node;
            }
            return isEditorRow(node);
        }

        function getEnterRow() {
            var row = isEditorRow(editor.selection.getNode());
            if (row) {
                return row;
            }

            var rng;
            try {
                rng = editor.selection.getRng();
            } catch (_) {
                return null;
            }
            if (!rng || !rng.collapsed) {
                return null;
            }

            var container = rng.startContainer;
            var offset = rng.startOffset;

            if (container && container.nodeType === 3) {
                return isEditorRow(container.parentNode);
            }

            if (!container || container.nodeType !== 1) {
                return null;
            }

            return nodeAsEditorRow(container.childNodes[offset - 1]) ||
                nodeAsEditorRow(container.childNodes[offset]) ||
                null;
        }

        function unwrapEditorRow(row) {
            if (!row || !row.parentNode) {
                return false;
            }
            var dom = editor.dom;
            var doc = editor.getDoc();
            var parent = row.parentNode;
            var next = row.nextSibling;
            var needsP = parent.nodeName === 'BODY';
            var currentP = null;
            var firstMoved = null;

            Array.prototype.slice.call(row.childNodes).forEach(function (child) {
                if (child.nodeType === 1 && child.classList && child.classList.contains('sp-editor-row-anchor')) {
                    return;
                }
                if (needsP && !dom.isBlock(child)) {

                    if (!currentP) {
                        currentP = doc.createElement('p');
                        parent.insertBefore(currentP, next);
                    }
                    safeMoveNode(currentP, child);
                } else {
                    currentP = null;
                    safeMoveNode(parent, child, next);
                }
                if (!firstMoved) {
                    firstMoved = child;
                }
            });

            parent.removeChild(row);

            if (firstMoved) {
                try {
                    editor.selection.setCursorLocation(firstMoved, 0);
                } catch (_) {}
            }
            return true;
        }

        function flattenParagraphs(node) {
            var paragraphs = Array.prototype.slice.call(node.querySelectorAll('p'));
            paragraphs.forEach(function (p) {
                if (!p.parentNode) {
                    return;
                }
                while (p.firstChild) {
                    safeMoveNode(p.parentNode, p.firstChild, p);
                }
                p.parentNode.removeChild(p);
            });
        }

        var ROW_SELECTOR = '.sp-editor-inline-row[data-editor-row], .row[data-editor-row]';

        function containsRow(node) {
            return !!(node && node.nodeType === 1 &&
                (isEditorRow(node) || node.querySelector(ROW_SELECTOR)));
        }

        function makeRow() {
            var row = editor.getDoc().createElement('div');
            row.className = 'row sp-editor-inline-row';
            row.setAttribute('data-editor-row', '1');
            return row;
        }

        function liftRowOutOfParagraph(row) {
            var p = row.parentNode;
            if (!p || p.nodeName !== 'P') {
                return;
            }
            var doc = editor.getDoc();
            var parent = p.parentNode;
            var pBefore = doc.createElement('p');
            var pAfter = doc.createElement('p');

            copyAlignment(p, pBefore);
            copyAlignment(p, pAfter);

            while (p.firstChild && p.firstChild !== row) {
                safeMoveNode(pBefore, p.firstChild);
            }
            p.removeChild(row);
            while (p.firstChild) {
                safeMoveNode(pAfter, p.firstChild);
            }

            if (pBefore.textContent.replace(/\u200B/g, '').trim() !== '' || pBefore.querySelector('*')) {
                parent.insertBefore(pBefore, p);
            }
            parent.insertBefore(row, p);
            if (pAfter.childNodes.length === 0) {
                pAfter.innerHTML = '<br data-mce-bogus="1">';
            }
            parent.insertBefore(pAfter, p);
            parent.removeChild(p);
        }

        function paragraphHasEditableContent(p) {
            if (!p || p.nodeName !== 'P') {
                return false;
            }
            if (p.textContent.replace(/\u200B/g, '').trim() !== '') {
                return true;
            }
            return Array.prototype.slice.call(p.childNodes).some(function (child) {
                return child.nodeType === 1 &&
                    child.nodeName !== 'BR' &&
                    !child.hasAttribute('data-mce-bogus');
            });
        }

        function prepareCaretParagraph(p) {
            if (!p || paragraphHasEditableContent(p)) {
                return p;
            }
            p.setAttribute('data-sp-row-caret-line', '1');
            p.innerHTML = '<br data-mce-bogus="1">';
            return p;
        }

        function ensureParagraphAfter(row) {
            var next = row.nextElementSibling;
            if (!next || next.nodeName !== 'P') {
                next = editor.getDoc().createElement('p');
                copyAlignment(row, next);
                row.parentNode.insertBefore(next, row.nextSibling);
            } else if (!paragraphHasEditableContent(next)) {
                copyAlignment(row, next);
            }
            return prepareCaretParagraph(next);
        }

        function placeCaretInParagraph(p) {
            var target = prepareCaretParagraph(p);
            if (!target) {
                return;
            }

            try {
                editor.selection.setCursorLocation(target, 0);
            } catch (_) {
                try {
                    editor.selection.select(target);
                    editor.selection.collapse(true);
                } catch (__) {}
            }

            try {
                var doc = editor.getDoc();
                var win = editor.getWin();
                var selection = win.getSelection();
                var range = doc.createRange();
                range.setStart(target, 0);
                range.collapse(true);
                selection.removeAllRanges();
                selection.addRange(range);
            } catch (_) {}
        }

        function placeCaretInParagraphSoon(p) {
            placeCaretInParagraph(p);
            setTimeout(function () {
                if (!p || !p.parentNode) {
                    return;
                }
                editor.focus();
                placeCaretInParagraph(p);
                editor.nodeChanged();
            }, 0);
        }

        function clearUsedCaretLines() {
            var body = editor.getBody();
            if (!body) {
                return;
            }
            Array.prototype.slice.call(body.querySelectorAll('p[data-sp-row-caret-line="1"]')).forEach(function (p) {
                if (paragraphHasEditableContent(p)) {
                    p.removeAttribute('data-sp-row-caret-line');
                }
            });
        }

        editor.on('input keyup NodeChange', clearUsedCaretLines);

        function finishWrap(row) {
            ensureParagraphAfter(row);
            try {
                editor.selection.select(row);
            } catch (_) {}
            editor.nodeChanged();
            if (typeof editor.save === 'function') {
                editor.save();
            }
            editor.fire('change');
        }

        function topLevelUnder(node, root) {
            while (node && node.parentNode !== root) {
                node = node.parentNode;
            }
            return node;
        }

        function toggleRow() {
            editor.focus();

            var node = editor.selection.getNode();
            var row = isEditorRow(node);

            var body = editor.getBody();
            var doc = editor.getDoc();

            if (row) {
                editor.undoManager.transact(function () {
                    try {
                        editor.getWin().getSelection().removeAllRanges();
                    } catch (_) {}
                    try {
                        editor.selection.select(body, true);
                        editor.selection.collapse(true);
                    } catch (_) {}

                    unwrapEditorRow(row);
                    editor.nodeChanged();
                    if (typeof editor.save === 'function') {
                        editor.save();
                    }
                    editor.fire('change');
                });
                return;
            }

            var selectedWidgets = Array.prototype.slice.call(body.querySelectorAll('.sp-editor-widget.is-selected')).filter(function (w) {
                var isBogus = w.closest('.mce-offscreen-selection, [data-mce-bogus="all"]');
                return !isBogus;
            });

            if (selectedWidgets.length > 1) {
                editor.undoManager.transact(function () {

                    body.querySelectorAll('[data-mce-selected]').forEach(function (el) {
                        el.removeAttribute('data-mce-selected');
                    });
                    try {
                        editor.getWin().getSelection().removeAllRanges();
                    } catch (_) {}
                    try {
                        editor.selection.select(body, true);
                        editor.selection.collapse(true);
                    } catch (_) {}

                    var firstWidget = selectedWidgets[0];
                    var lastWidget = selectedWidgets[selectedWidgets.length - 1];
                    var parentP = firstWidget.parentNode;

                    var parentContainers = [];
                    selectedWidgets.forEach(function (w) {
                        if (w.parentNode && parentContainers.indexOf(w.parentNode) === -1) {
                            parentContainers.push(w.parentNode);
                        }
                    });

                    var sRow = makeRow();
                    copyAlignment(parentP, sRow);
                    var pAfter = null;

                    if (parentP && parentP.nodeName === 'P') {

                        pAfter = splitParagraphAt(parentP, lastWidget.nextSibling);

                        parentP.parentNode.insertBefore(sRow, pAfter || parentP.nextSibling);

                        if (pAfter && parentContainers.indexOf(pAfter) === -1) {
                            parentContainers.push(pAfter);
                        }
                    } else {

                        firstWidget.parentNode.insertBefore(sRow, firstWidget);
                    }

                    selectedWidgets.forEach(function (w, idx) {

                        safeMoveNode(sRow, w);
                        w.classList.remove('is-selected');
                    });

                    parentContainers.forEach(function (container, idx) {
                        var empty = isParagraphEmpty(container);

                        if (empty) {
                            container.parentNode.removeChild(container);
                        }
                    });

                    finishWrap(sRow);
                });
                return;
            }

            var widget = node && node.closest
                ? node.closest('[data-widget-id], [contenteditable="false"]')
                : null;
            if (widget) {
                var isBogus = widget.closest('.mce-offscreen-selection, [data-mce-bogus="all"]');
                if (isBogus) {

                    var realWidget = body.querySelector('.sp-editor-widget[data-widget-id][data-mce-selected]');
                    if (realWidget && !realWidget.closest('.mce-offscreen-selection, [data-mce-bogus="all"]')) {
                        widget = realWidget;
                    } else {
                        var wId = widget.getAttribute('data-widget-id');
                        var candidates = Array.prototype.slice.call(body.querySelectorAll('.sp-editor-widget[data-widget-id="' + wId + '"]')).filter(function (c) {
                            return !c.closest('.mce-offscreen-selection, [data-mce-bogus="all"]');
                        });
                        widget = candidates[0] || null;
                    }

                }
            }
            if (widget && widget !== body) {
                editor.undoManager.transact(function () {
                    body.querySelectorAll('[data-mce-selected]').forEach(function (el) {
                        el.removeAttribute('data-mce-selected');
                    });
                    try {
                        editor.getWin().getSelection().removeAllRanges();
                    } catch (_) {}
                    try {
                        editor.selection.select(body, true);
                        editor.selection.collapse(true);
                    } catch (_) {}

                    var parentP = widget.parentNode;
                    var wRow = makeRow();
                    copyAlignment(parentP, wRow);
                    var pAfter = null;

                    if (parentP && parentP.nodeName === 'P') {
                        pAfter = splitParagraphAt(parentP, widget.nextSibling);
                        parentP.parentNode.insertBefore(wRow, pAfter || parentP.nextSibling);
                    } else {
                        widget.parentNode.insertBefore(wRow, widget);
                    }

                    safeMoveNode(wRow, widget);

                    [parentP, pAfter].forEach(function (container) {
                        if (isParagraphEmpty(container)) {
                            container.parentNode.removeChild(container);
                        }
                    });

                    finishWrap(wRow);
                });

                return;
            }

            var rng = editor.selection.getRng();

            if (rng.collapsed) {
                var block = editor.dom.getParent(node, editor.dom.isBlock);
                if (block && block !== body && block.nodeName === 'P') {
                    var pRow = makeRow();
                    block.parentNode.insertBefore(pRow, block);
                    while (block.firstChild) {
                        safeMoveNode(pRow, block.firstChild);
                    }
                    block.parentNode.removeChild(block);
                    finishWrap(pRow);
                    return;
                }

                var nRow = makeRow();
                try {
                    rng.insertNode(nRow);
                } catch (_) {
                    body.appendChild(nRow);
                }
                liftRowOutOfParagraph(nRow);
                finishWrap(nRow);
                return;
            }

            var root = rng.commonAncestorContainer;
            if (root.nodeType === 3) {
                root = root.parentNode;
            }
            if (root.closest && root.closest(ROW_SELECTOR)) {
                editor.windowManager.alert('This selection is already inside a row.');
                return;
            }

            var startTop = topLevelUnder(rng.startContainer, root);
            var endTop = topLevelUnder(rng.endContainer, root);
            if (!startTop || !endTop) {
                editor.windowManager.alert('Could not wrap this selection. Try selecting whole lines.');
                return;
            }

            var nodes = [];
            var cur = startTop;
            while (cur) {
                nodes.push(cur);
                if (cur === endTop) {
                    break;
                }
                cur = cur.nextSibling;
            }
            if (cur !== endTop) {
                editor.windowManager.alert('Could not wrap this selection. Try selecting whole lines.');
                return;
            }

            var hasRow = nodes.some(function (n) {
                return containsRow(n);
            });
            if (hasRow) {
                editor.windowManager.alert('This selection already contains a row.');
                return;
            }

            var sRow = makeRow();
            startTop.parentNode.insertBefore(sRow, startTop);
            nodes.forEach(function (n) {
                sRow.appendChild(n);
            });
            flattenParagraphs(sRow);
            liftRowOutOfParagraph(sRow);
            finishWrap(sRow);
        }

        editor.on('keydown', function (e) {
            if (e.keyCode === 13 && !e.shiftKey) {
                var row = getEnterRow();
                if (row) {
                    e.preventDefault();

                    var next = ensureParagraphAfter(row);
                    placeCaretInParagraphSoon(next);
                    editor.focus();
                    editor.nodeChanged();
                }
            }
        });

        editor.addButton('sp_editor_row', {
            image: iconDataUri,
            tooltip: 'Wrap in Row',
            onclick: toggleRow,
            onpostrender: function () {
                var btn = this;
                editor.on('NodeChange', function (e) {
                    var inRow = !!isEditorRow(e.element);
                    btn.active(inRow);
                });
            }
        });
    });
})();
