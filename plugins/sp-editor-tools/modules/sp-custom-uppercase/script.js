(function () {
    'use strict';

    tinymce.PluginManager.add('textcase_elem', function (editor) {
        function nearestAnchor(node) {
            var el = node && node.nodeType === 3 ? node.parentNode : node;
            while (el && el.nodeType === 1 && el !== editor.getBody()) {
                if (el.nodeName === 'A') return el;
                el = el.parentNode;
            }
            return null;
        }

        function nearestElement(node) {
            if (!node) return null;
            return node.nodeType === 3 ? node.parentNode : (node.nodeType === 1 ? node : null);
        }

        function collectTargets() {
            var rng = editor.selection.getRng();
            var out = [];

            var a = nearestAnchor(editor.selection.getNode());
            if (a) { out.push(a); return out; }

            if (!rng.collapsed) {
                var blocks = editor.selection.getSelectedBlocks();
                if (blocks && blocks.length) {
                    var seen = typeof Set !== 'undefined' ? new Set() : null;
                    for (var i = 0; i < blocks.length; i++) {
                        var b = blocks[i];
                        if (b && b.nodeType === 1) {
                            if (seen) { if (!seen.has(b)) { seen.add(b); out.push(b); } }
                            else { if (out.indexOf(b) === -1) out.push(b); }
                        }
                    }
                    if (out.length) return out;
                }
            }

            var el = nearestElement(editor.selection.getNode());
            if (el && el !== editor.getBody()) out.push(el);
            return out;
        }

        var ALLOWED = { uppercase:1, lowercase:1, capitalize:1 };

        function readTransform(el) {
            if (!el || !el.style) return '';
            var v = (el.style.textTransform || '').toLowerCase();
            return ALLOWED[v] ? v : '';
        }

        function writeTransform(el, value) {
            if (!el || !el.style) return;
            if (value === '' || value === '__clear__') {
                el.style.removeProperty('text-transform');
            } else if (ALLOWED[value]) {
                el.style.textTransform = value;
            }
        }

        function selectionTransform() {
            var t = collectTargets();
            if (!t || !t.length) return '';
            return readTransform(t[0]);
        }

        function applyTransform(value) {
            var targets = collectTargets();
            if (!targets || !targets.length) {
                editor.windowManager.alert('Select text or place cursor inside an element.');
                return;
            }
            editor.undoManager.transact(function () {
                for (var i = 0; i < targets.length; i++) {
                    writeTransform(targets[i], value);
                }
            });
            editor.nodeChanged();
        }

        function openDialog() {
            var seed = selectionTransform() || '__keep__';

            var win = editor.windowManager.open({
                title: 'Text case',
                body: [
                    {
                        type: 'listbox',
                        name: 'case',
                        label: 'Case',
                        values: [
                            { text: '— keep —', value: '__keep__' },
                            { text: 'Clear (reset)', value: '__clear__' },
                            { text: 'UPPERCASE', value: 'uppercase' },
                            { text: 'lowercase', value: 'lowercase' },
                            { text: 'Capitalize', value: 'capitalize' }
                        ],
                        value: seed
                    }
                ],
                buttons: [
                    {
                        text: 'Reset',
                        onclick: function () {
                            applyTransform('__clear__');
                            win.close();
                        }
                    },
                    { text: 'Cancel', onclick: 'close' },
                    {
                        text: 'Apply',
                        subtype: 'primary',
                        onclick: function () {
                            var data = win.toJSON();
                            if (!data || !data.case) { win.close(); return; }
                            if (data.case !== '__keep__') applyTransform(data.case);
                            win.close();
                        }
                    }
                ]
            });
        }

        const svg = `
            <svg xmlns="http://www.w3.org/2000/svg" xml:space="preserve" width="800" height="800" viewBox="0 0 512 512">
              <path d="M490.7 0H21.3A21.3 21.3 0 0 0 0 21.3v469.4C0 502.4 9.6 512 21.3 512h469.4c11.7 0 21.3-9.6 21.3-21.3V21.3C512 9.6 502.4 0 490.7 0m-21.4 469.3H42.7V42.7h426.6z"/>
              <path d="M276.2 377.3 241 271l-.2-.4L191 121.3a21.3 21.3 0 0 0-40.5 0L100.5 271 65.1 377.3a21.3 21.3 0 0 0 40.5 13.4l30.7-92H205l30.7 92a21.3 21.3 0 1 0 40.4-13.4M150.5 256l20.2-60.5 20.1 60.5zM447.4 378.8l-42.7-170.6c-5-20.3-33.3-22-40.7-2.4l-64 170.7a21.3 21.3 0 0 0 40 15l10.8-28.8h48.5l6.7 26.5a21.3 21.3 0 1 0 41.4-10.4M366.8 320l13.1-35 8.8 35zM441.8 91.6a21.3 21.3 0 0 0-30.2 0l-6.3 6.2V85.3a21.3 21.3 0 1 0-42.6 0v12.5l-6.3-6.2a21.3 21.3 0 0 0-30.2 30.2l42.7 42.6 1.6 1.4.7.6 1 .7.9.5.8.5 1 .5 1 .4.9.4 1 .3 1 .3 1 .3 1.2.1.9.2h4.2l1-.2 1-.1 1.1-.3 1-.3 1-.3 1-.4q.5 0 .9-.4l1-.5.8-.5 1-.5.9-.7.7-.6 1.6-1.4 42.6-42.6a21.3 21.3 0 0 0 0-30.2"/>
            </svg>
        `;
        var iconDataUri = 'data:image/svg+xml;utf8,' + encodeURIComponent(svg);

        editor.addButton('textcase_elem', {
            image: iconDataUri,
            tooltip: 'Text case…',
            onclick: openDialog,
            onPostRender: function () {
                var btn = this;
                editor.on('NodeChange', function () {
                    btn.active(!!selectionTransform());
                });
            }
        });
    });
})();
