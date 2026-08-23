(function (w) {
    'use strict';

    if (!w.tinymce || !w.tinymce.PluginManager) {
        throw new Error('TinyMCE 4 not found. Load TinyMCE before TagStyleSelector.');
    }

    class TagStyleSelector {
        constructor(opts) {
            if (!opts || !Array.isArray(opts.classes)) {
                throw new Error('TagStyleSelector: provide { classes: [...] }');
            }

            this.pluginName     = 'tag_style_selector';
            this.blockSelector  = 'a,p,h1,h2,h3,h4,h5,h6,li,div';
            this.items          = opts.classes;

            this._registered    = false;
            this._initPatched   = false;

            this._registerPlugin();
            this._patchTinymceInit();
            this._attachToExisting();
        }

        _getSelectedBlocks(editor) {
            var dom = editor.dom;

            if (editor.selection && typeof editor.selection.getSelectedBlocks === 'function') {
                var blocks = editor.selection.getSelectedBlocks();
                if (blocks && blocks.length) return blocks;
            }

            var rng = editor.selection ? editor.selection.getRng() : null;
            if (!rng) {
                var node = editor.selection ? editor.selection.getNode() : editor.getBody();
                var only = dom.getParent(node, this.blockSelector) || editor.getBody();
                return only ? [only] : [];
            }

            var start = dom.getParent(rng.startContainer, this.blockSelector) || editor.getBody();
            var end   = dom.getParent(rng.endContainer,   this.blockSelector) || start;

            var out = [];
            var n = start;
            while (n) {
                if (dom.is(n, this.blockSelector)) out.push(n);
                if (n === end) break;
                n = n.nextSibling;
                if (!n) break;
            }
            if (!out.length) {
                var cur = dom.getParent(editor.selection.getNode(), this.blockSelector);
                if (cur) out.push(cur);
            }
            return out;
        }

        _applyClass(editor, className) {
            var isReset = (className == null || className === '' || className === 'default');

            editor.undoManager.transact(() => {
                var dom = editor.dom;
                var blocks = this._getSelectedBlocks(editor);
                if (!blocks || !blocks.length) return;

                for (var i = 0; i < blocks.length; i++) {
                    var host = dom.is(blocks[i], this.blockSelector) ? blocks[i] : dom.getParent(blocks[i], this.blockSelector);
                    if (!host) continue;

                    var prev = (dom.getAttrib(host, 'data-typo') || '').trim();
                    if (prev) dom.removeClass(host, prev);

                    if (isReset) {
                        dom.setAttrib(host, 'data-typo', null);
                    } else {
                        dom.addClass(host, className);
                        dom.setAttrib(host, 'data-typo', className);
                    }
                }
            });

            editor.nodeChanged();
        }

        _menu(editor) {
            var self = this;
            return this.items.map(function (it) {
                var isReset = (it.value == null || it.value === '' || it.value === 'default');
                var cls = isReset ? 'default' : String(it.value);
                return {
                    text: String(it.text || cls),
                    onclick: function () { self._applyClass(editor, isReset ? 'default' : cls); }
                };
            });
        }

        _registerPlugin() {
            if (this._registered) return;
            this._registered = true;

            var self = this;
            w.tinymce.PluginManager.add(this.pluginName, function (editor) {
                editor.addButton(self.pluginName, {
                    type: 'menubutton',
                    text: 'Typography',
                    icon: 'settings',
                    tooltip: 'Apply typography class to block',
                    menu: self._menu(editor)
                });

                return {
                    getMetadata: function () {
                        return { name: 'Tag Style Selector (TinyMCE4, constructor)', version: '1.1.0' };
                    }
                };
            });
        }

        _patchTinymceInit() {
            if (this._initPatched) return;
            this._initPatched = true;

            var pluginName = this.pluginName;
            var origInit = w.tinymce.init;

            w.tinymce.init = function (userConfig) {
                var cfg = Object.assign({}, userConfig || {});

                var plugins = cfg.plugins || '';
                if (Array.isArray(plugins)) plugins = plugins.join(' ');
                if (typeof plugins !== 'string') plugins = String(plugins || '');
                if (!plugins.split(/\s+/).includes(pluginName)) {
                    plugins = (plugins ? (plugins + ' ') : '') + pluginName;
                }
                cfg.plugins = plugins;

                var toolbar = cfg.toolbar || '';
                if (Array.isArray(toolbar)) toolbar = toolbar.join(' ');
                if (typeof toolbar !== 'string') toolbar = String(toolbar || '');
                if (!toolbar.split(/\s+|\|/).includes(pluginName)) {
                    toolbar = pluginName + (toolbar ? (' | ' + toolbar) : '');
                }
                cfg.toolbar = toolbar;

                return origInit.call(w.tinymce, cfg);
            };
        }

        _attachToExisting() {
            var editors = Array.isArray(w.tinymce.editors) ? w.tinymce.editors.slice() : [];
            if (!editors.length) return;

            var pluginName = this.pluginName;
            var needReinit = [];

            for (var i = 0; i < editors.length; i++) {
                var ed = editors[i];
                if (!ed || !ed.settings) continue;

                var hasPlugin = false;
                var p = ed.settings.plugins || '';
                if (Array.isArray(p)) p = p.join(' ');
                if (typeof p !== 'string') p = String(p || '');
                hasPlugin = p.split(/\s+/).indexOf(pluginName) >= 0;

                var tb = ed.settings.toolbar || '';
                if (Array.isArray(tb)) tb = tb.join(' ');
                if (typeof tb !== 'string') tb = String(tb || '');
                var hasButton = tb.split(/\s+|\|/).indexOf(pluginName) >= 0;

                if (!hasPlugin || !hasButton) {
                    needReinit.push(ed);
                }
            }

            if (!needReinit.length) return;

            needReinit.forEach((ed) => {
                var settings = Object.assign({}, ed.settings);
                var targetEl = ed.getElement();
                if (!targetEl) return;

                w.tinymce.remove(ed);

                var plugins = settings.plugins || '';
                if (Array.isArray(plugins)) plugins = plugins.join(' ');
                if (typeof plugins !== 'string') plugins = String(plugins || '');
                if (!plugins.split(/\s+/).includes(pluginName)) {
                    plugins = (plugins ? (plugins + ' ') : '') + pluginName;
                }
                settings.plugins = plugins;

                var toolbar = settings.toolbar || '';
                if (Array.isArray(toolbar)) toolbar = toolbar.join(' ');
                if (typeof toolbar !== 'string') toolbar = String(toolbar || '');
                if (!toolbar.split(/\s+|\|/).includes(pluginName)) {
                    toolbar = toolbar + (toolbar ? (' ' + pluginName) : pluginName);
                }
                settings.toolbar = toolbar;

                delete settings.selector;
                settings.target = targetEl;

                w.tinymce.init(settings);
            });
        }
    }

    new TagStyleSelector({
        classes: (w.TAG_STYLE_SELECTOR && Array.isArray(w.TAG_STYLE_SELECTOR.classes)) ? w.TAG_STYLE_SELECTOR.classes : [],
    });

    w.TagStyleSelector = TagStyleSelector;

})(window);
