(function () {
    'use strict';

    tinymce.PluginManager.add('underline_toggle_elem', function (editor) {
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
                            if (seen) {
                                if (!seen.has(b)) { seen.add(b); out.push(b); }
                            } else {
                                if (out.indexOf(b) === -1) out.push(b);
                            }
                        }
                    }
                    if (out.length) return out;
                }
            }

            var el = nearestElement(editor.selection.getNode());
            if (el && el !== editor.getBody()) out.push(el);
            return out;
        }

        var LINE_KEYS = { 'underline':1, 'overline':1, 'line-through':1 };
        var STYLE_KEYS = { 'solid':1, 'double':1, 'dotted':1, 'dashed':1, 'wavy':1 };

        function readInlineDecoration(el) {
            var s = el && el.style ? el.style : null;
            var res = { lines: [], style: '' };
            if (!s) return res;

            var lineStr = s.textDecorationLine || '';
            if (lineStr) {
                res.lines = lineStr.toLowerCase().split(/\s+/).filter(Boolean);
            } else {
                var sh = (s.textDecoration || '').toLowerCase();
                if (sh) {
                    sh.split(/\s+/).forEach(function (tok) {
                        if (LINE_KEYS[tok]) res.lines.push(tok);
                    });
                }
            }

            res.style = (s.textDecorationStyle || '').toLowerCase();
            return res;
        }

        function writeInlineDecoration(el, dec) {
            if (!el || !el.style) return;

            if (dec.lines) {
                if (dec.lines.length) el.style.textDecorationLine = dec.lines.join(' ');
                else el.style.removeProperty('text-decoration-line');
            }

            if (typeof dec.style !== 'undefined') {
                if (dec.style === '') el.style.removeProperty('text-decoration-style');
                else el.style.textDecorationStyle = dec.style;
            }

            if (!el.style.textDecorationLine && !el.style.textDecorationStyle) {
                el.style.removeProperty('text-decoration');
            }
        }

        function clearDecoration(el) {
            if (!el || !el.style) return;
            el.style.removeProperty('text-decoration');
            el.style.removeProperty('text-decoration-line');
            el.style.removeProperty('text-decoration-style');
        }

        function openDialog() {
            var targets = collectTargets();
            if (!targets || !targets.length) {
                editor.windowManager.alert('Place your cursor or select the elements.');
                return;
            }

            var seed = readInlineDecoration(targets[0]);

            var win = editor.windowManager.open({
                title: 'Text Decoration',
                body: [
                    { type: 'checkbox', name: 'underline',   label: 'Underline',    checked: seed.lines.indexOf('underline') !== -1 },
                    { type: 'checkbox', name: 'overline',    label: 'Overline',     checked: seed.lines.indexOf('overline') !== -1 },
                    { type: 'checkbox', name: 'linethrough', label: 'Line-through', checked: seed.lines.indexOf('line-through') !== -1 },
                    { type: 'listbox',  name: 'style', label: 'Style', values: [
                            { text: '— keep —', value: '__keep__' },
                            { text: 'Clear',     value: '__none__' },
                            { text: 'solid',  value: 'solid'  },
                            { text: 'double', value: 'double' },
                            { text: 'dotted', value: 'dotted' },
                            { text: 'dashed', value: 'dashed' },
                            { text: 'wavy',   value: 'wavy'   }
                        ], value: seed.style ? seed.style : '__keep__' }
                ],
                buttons: [
                    {
                        text: 'Reset all',
                        onclick: function () {
                            editor.undoManager.transact(function () {
                                for (var i = 0; i < targets.length; i++) clearDecoration(targets[i]);
                            });
                            win.close();
                            editor.nodeChanged();
                        }
                    },
                    { text: 'Cancel', onclick: 'close' },
                    {
                        text: 'Apply',
                        subtype: 'primary',
                        onclick: function () {
                            var data = win.toJSON();

                            var lines = [];
                            if (data.underline)   lines.push('underline');
                            if (data.overline)    lines.push('overline');
                            if (data.linethrough) lines.push('line-through');

                            var style = seed.style;
                            if (data.style === '__none__') style = '';
                            else if (data.style !== '__keep__') style = (STYLE_KEYS[data.style] ? data.style : '');

                            editor.undoManager.transact(function () {
                                for (var i = 0; i < targets.length; i++) {
                                    var current = readInlineDecoration(targets[i]);
                                    writeInlineDecoration(targets[i], {
                                        lines: lines,
                                        style: (data.style === '__keep__') ? current.style : style
                                    });
                                }
                            });

                            win.close();
                            editor.nodeChanged();
                        }
                    }
                ]
            });
        }

        editor.addButton('underline_toggle_elem', {
            icon: 'underline',
            tooltip: 'Text Decoration…',
            onclick: openDialog
        });
    });
})();
