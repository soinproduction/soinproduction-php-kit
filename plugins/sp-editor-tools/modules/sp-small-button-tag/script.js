(function () {
    tinymce.PluginManager.add('small_toggle', function (editor) {
        function safeSelectionNode() {
            if (!editor || !editor.selection || typeof editor.selection.getNode !== 'function') return null;
            try { return editor.selection.getNode(); } catch (e) { return null; }
        }

        function isInsideSmall(node) {
            var n = node || safeSelectionNode();
            if (!n || !editor.dom || typeof editor.dom.getParent !== 'function') return false;
            return !!editor.dom.getParent(n, 'small');
        }

        function wrapWithSmall() {
            if (!editor) return;
            var sel = editor.selection;
            if (!sel) return;

            var html = '';
            try { html = sel.getContent({ format: 'html' }); } catch (e) { html = ''; }

            if (html && html.length) {
                editor.execCommand('mceInsertContent', false, '<small>' + html + '</small>');
            } else {
                editor.execCommand('mceInsertContent', false, '<small></small>');
                var nodes = editor.dom.select('small');
                if (nodes && nodes.length) {
                    var last = nodes[nodes.length - 1];
                    try { sel.select(last); sel.collapse(false); } catch (e) {}
                }
            }
        }

        function unwrapSmall() {
            var node = safeSelectionNode();
            if (!node) return;
            var sm = editor.dom.getParent(node, 'small');
            if (sm) editor.dom.remove(sm, true);
        }

        function toggleSmall() {
            if (!editor) return;
            editor.focus();
            editor.undoManager.transact(function () {
                if (isInsideSmall()) unwrapSmall(); else wrapWithSmall();
            });
        }

        const svg = `
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
              <path stroke="#50575e" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V4m0 5-2.5-2M17 9l2.5-2"/>
              <path fill="#50575e" d="M10.6 19.2c0-.2-.3-.4-.5-.4H7.6c-.3 0-.5.2-.5.4l-.4 1.3c-.1.2-.3.4-.5.4H4.7a.5.5 0 0 1-.4-.7l3.3-8.9c0-.2.3-.3.5-.3h1.5c.2 0 .4.1.5.3l3.3 8.9c.2.3 0 .7-.4.7h-1.5a.5.5 0 0 1-.5-.4l-.4-1.3Zm-2.8-2.3c0 .2 0 .3.2.3h1.7c.1 0 .2-.1.2-.3L9 14.3c0-.2-.3-.2-.4 0l-.8 2.6Zm10.3 4-.2-.1c0-.2-.3-.3-.5-.2-.3.3-.8.4-1.3.4a3 3 0 0 1-1.9-.6 2 2 0 0 1-.7-1.6c0-.8.3-1.4.9-1.8.6-.4 1.5-.6 2.6-.6h.2c.3 0 .5-.2.5-.5v-.1c0-.3 0-.6-.2-.7l-.6-.2c-.4 0-.7.1-.8.4-.1.2-.3.4-.6.4h-1.3c-.3 0-.5-.2-.5-.5.2-.4.4-.8.9-1.1a4 4 0 0 1 2.3-.7c1 0 1.8.2 2.3.6.5.4.8 1 .8 1.8v4.9l-.1.2H18Zm-1.5-1.4.7-.1.3-.2V18c0-.2-.1-.5-.4-.5-.5 0-.8.1-1 .3a1 1 0 0 0-.4.9c0 .5.3.8.8.8Z"/>
            </svg>
        `;
        var iconDataUri = 'data:image/svg+xml;utf8,' + encodeURIComponent(svg);

        editor.addButton('small_toggle', {
            title: 'Small (toggle)',
            image: iconDataUri,
            onclick: toggleSmall,
            onPostRender: function () {
                var ctrl = this;

                function sync() {
                    var node = safeSelectionNode();
                    if (!node) return;
                    ctrl.active(isInsideSmall(node));
                }

                editor.on('init', function () {
                    setTimeout(sync, 0);
                    editor.on('NodeChange KeyUp SetContent ExecCommand', sync);
                    editor.on('focus', sync);
                });
            }
        });

        editor.addMenuItem('small_toggle', {
            text: 'Small',
            image: iconDataUri,
            context: 'format',
            onclick: toggleSmall
        });

    });
})();
