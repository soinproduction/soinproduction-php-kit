(function () {
    tinymce.PluginManager.add('decor_toggle', function (editor) {

        function safeSelectionNode() {
            if (!editor || !editor.selection || typeof editor.selection.getNode !== 'function') return null;
            try { return editor.selection.getNode(); } catch (e) { return null; }
        }

        function isInsideDecor(node) {
            var n = node || safeSelectionNode();
            if (!n || !editor.dom || typeof editor.dom.getParent !== 'function') return false;
            return !!editor.dom.getParent(n, function (el) {
                return el.nodeName === 'SPAN' && editor.dom.hasClass(el, 'decor');
            });
        }

        function wrapWithDecor() {
            if (!editor) return;
            var sel = editor.selection;
            if (!sel) return;

            var html = '';
            try { html = sel.getContent({ format: 'html' }); } catch (e) { html = ''; }

            if (html && html.length) {
                editor.execCommand('mceInsertContent', false, '<span class="decor">' + html + '</span>');
            } else {
                editor.execCommand('mceInsertContent', false, '<span class="decor"></span>');
                var nodes = editor.dom.select('span.decor');
                if (nodes && nodes.length) {
                    var last = nodes[nodes.length - 1];
                    try { sel.select(last); sel.collapse(false); } catch (e) {}
                }
            }
        }

        function unwrapDecor() {
            var node = safeSelectionNode();
            if (!node) return;
            var span = editor.dom.getParent(node, function (el) {
                return el.nodeName === 'SPAN' && editor.dom.hasClass(el, 'decor');
            });
            if (span) editor.dom.remove(span, true);
        }

        function toggleDecor() {
            if (!editor) return;
            editor.focus();
            editor.undoManager.transact(function () {
                if (isInsideDecor()) unwrapDecor(); else wrapWithDecor();
            });
        }

        const svg = `
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none">
              <ellipse cx="12" cy="12" rx="10" ry="7" stroke="#50575e" stroke-width="1.8" fill="none" stroke-linecap="round" stroke-dasharray="3 2"/>
              <text x="12" y="16" text-anchor="middle" font-size="9" fill="#50575e" font-family="serif" font-style="italic">A</text>
            </svg>
        `;
        var iconDataUri = 'data:image/svg+xml;utf8,' + encodeURIComponent(svg);

        editor.addButton('decor_toggle', {
            title: 'Decor span (toggle)',
            image: iconDataUri,
            onclick: toggleDecor,
            onPostRender: function () {
                var ctrl = this;

                function sync() {
                    var node = safeSelectionNode();
                    if (!node) return;
                    ctrl.active(isInsideDecor(node));
                }

                editor.on('init', function () {
                    setTimeout(sync, 0);
                    editor.on('NodeChange KeyUp SetContent ExecCommand', sync);
                    editor.on('focus', sync);
                });
            }
        });

        editor.addMenuItem('decor_toggle', {
            text: 'Decor span',
            image: iconDataUri,
            context: 'format',
            onclick: toggleDecor
        });

    });
})();
