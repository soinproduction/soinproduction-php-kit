(function () {
    'use strict';

    tinymce.PluginManager.add('toc_item', function (editor) {
        var svg = `
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#2ca9bc" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <line x1="3" y1="5" x2="21" y2="5" stroke="#000" />
              <line x1="8" y1="12" x2="21" y2="12" />
              <line x1="8" y1="19" x2="21" y2="19" />
              <circle cx="4" cy="12" r="1.5" fill="#2ca9bc" stroke="none" />
              <circle cx="4" cy="19" r="1.5" fill="#2ca9bc" stroke="none" />
            </svg>
        `;
        var iconDataUri = 'data:image/svg+xml;utf8,' + encodeURIComponent(svg);

        function getClosestBlock(node) {
            var el = node && node.nodeType === 3 ? node.parentNode : node;
            var body = editor.getBody();
            while (el && el.nodeType === 1 && el !== body) {
                var tag = el.nodeName.toLowerCase();
                if (['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p'].indexOf(tag) !== -1) {
                    return el;
                }
                el = el.parentNode;
            }
            return null;
        }

        function setTocItem(type) {
            var node = editor.selection.getNode();
            var blockEl = getClosestBlock(node);

            if (!blockEl) {
                editor.windowManager.alert('Please select a text block (heading or paragraph).');
                return;
            }

            editor.undoManager.transact(function () {
                var dom = editor.dom;
                if (!type) {
                    dom.setAttrib(blockEl, 'data-toc-item', null);
                } else {
                    dom.setAttrib(blockEl, 'data-toc-item', type);
                }
            });

            editor.nodeChanged();
        }

        editor.addButton('toc_item', {
            type: 'menubutton',
            image: iconDataUri,
            tooltip: 'Table of Contents (TOC) Item',
            menu: [
                {
                    text: 'Main TOC Item',
                    onclick: function() {
                        setTocItem('parent');
                    }
                },
                {
                    text: 'Sub TOC Item',
                    onclick: function() {
                        setTocItem('child');
                    }
                },
                {
                    text: 'Remove from TOC',
                    onclick: function() {
                        setTocItem(null);
                    }
                }
            ],
            onpostrender: function () {
                var btn = this;
                editor.on('NodeChange', function (e) {
                    var blockEl = getClosestBlock(e.element);
                    var val = blockEl ? editor.dom.getAttrib(blockEl, 'data-toc-item') : '';
                    btn.active(val === 'parent' || val === 'child' || val === 'true');
                });
            }
        });
    });
})();
