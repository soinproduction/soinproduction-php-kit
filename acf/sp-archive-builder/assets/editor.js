(function ($) {
    'use strict';
    function initialize(card) {
        if (card.closest('.acf-clone')) return;
        var root = card.querySelector('[data-sp-archive-editor]');
        if (!root || root.__spArchiveEditor) return;
        root.__spArchiveEditor = true;
        var i18n = JSON.parse(root.dataset.i18n || '{}');
        function t(text) { return i18n[text] || text; }
        var settings = card.querySelector('[data-archive-editor-settings]');
        var list = settings.querySelector('[data-archive-editor-rows]');
        var scope = settings.querySelector('[data-archive-editor-scope]');
        var typeInput = root.querySelector('[data-archive-editor-type]');
        var sources = JSON.parse(root.dataset.sources || '{}');
        var taxonomies = JSON.parse(root.dataset.taxonomies || '{}');
        var rowsByType = {};
        var scopeByType = {};
        var current = root.dataset.currentType && sources[root.dataset.currentType] ? root.dataset.currentType : typeInput.value;
        typeInput.value = current;
        rowsByType[current] = JSON.parse(root.dataset.rows || '[]');
        scopeByType[current] = JSON.parse(root.dataset.scope || '{}');
        function prefix() { return root.querySelector('input[name$="[editor_configured]"]').name.replace(/\[editor_configured\]$/, ''); }
        function option(select, value, text, selected) {
            var element = new Option(t(text), value, false, selected);
            select.add(element);
        }
        function control(row, key, text, element) {
            var label = document.createElement('label');
            var caption = document.createElement('span');
            caption.textContent = t(text);
            label.append(caption, element);
            element.dataset.key = key;
            row.append(label);
            return element;
        }
        function readRows() {
            return Array.from(list.children).map(function (row) {
                var result = {};
                row.querySelectorAll('[data-key]').forEach(function (input) { result[input.dataset.key] = input.value; });
                return result;
            });
        }
        function readScope() {
            var result = {};
            scope.querySelectorAll('input:checked').forEach(function (input) {
                if (!result[input.dataset.taxonomy]) result[input.dataset.taxonomy] = [];
                result[input.dataset.taxonomy].push(input.value);
            });
            return result;
        }
        function renumber() {
            Array.from(list.children).forEach(function (row, index) {
                row.querySelectorAll('[data-key]').forEach(function (input) { input.name = prefix() + '[editor_filters][' + index + '][' + input.dataset.key + ']'; });
                row.querySelector('[data-move-up]').disabled = index === 0;
                row.querySelector('[data-move-down]').disabled = index === list.children.length - 1;
            });
            var used = readRows().map(function (row) { return row.source; });
            list.querySelectorAll('select[data-key="source"]').forEach(function (select) {
                Array.from(select.options).forEach(function (item) { item.disabled = item.value !== select.value && used.includes(item.value); });
            });
            settings.querySelector('[data-archive-editor-empty]').hidden = list.children.length > 0;
            settings.querySelector('[data-archive-editor-add]').disabled = used.length >= Object.keys(sources[current] || {}).length;
            remember();
        }
        function remember() {
            // ACF duplicates DOM attributes; keep its cloned editor state current.
            root.dataset.currentType = current;
            root.dataset.rows = JSON.stringify(readRows());
            root.dataset.scope = JSON.stringify(readScope());
        }
        function announce(message) { settings.querySelector('[data-archive-editor-status]').textContent = t(message); }
        function tool(row, action, icon, text, handler) {
            var button = document.createElement('button');
            button.type = 'button';
            button.className = 'button sp-archive-editor__tool';
            button.setAttribute('aria-label', t(text));
            button.title = t(text);
            button.dataset[action] = '';
            var glyph = document.createElement('span');
            glyph.className = 'dashicons dashicons-' + icon;
            glyph.setAttribute('aria-hidden', 'true');
            button.append(glyph);
            button.addEventListener('click', handler);
            row.append(button);
        }
        function addRow(value, focus) {
            var available = sources[current] || {};
            var selected = available[value.source] ? value.source : Object.keys(available)[0];
            if (!selected) return;
            var row = document.createElement('div');
            row.className = 'sp-archive-editor__row';
            var drag = document.createElement('span');
            drag.className = 'dashicons dashicons-menu sp-archive-editor__drag';
            drag.setAttribute('aria-hidden', 'true');
            row.append(drag);
            var source = control(row, 'source', 'Source', document.createElement('select'));
            Object.keys(available).forEach(function (key) { option(source, key, available[key].label, key === selected); });
            var label = control(row, 'label', 'Label', document.createElement('input'));
            label.type = 'text'; label.value = value.label || ''; label.placeholder = available[selected].label;
            var ui = control(row, 'ui', 'Display', document.createElement('select'));
            Object.entries({buttons: 'Buttons', checkbox: 'Checkboxes', select: 'Select', multiselect: 'Multiple select', radio: 'Radio buttons'}).forEach(function (item) { option(ui, item[0], item[1], item[0] === (value.ui || 'checkbox')); });
            var mode = control(row, 'terms_mode', 'Terms', document.createElement('select'));
            Object.entries({children: 'Include child terms', parent: 'Top-level terms', selected: 'Selected terms only'}).forEach(function (item) { option(mode, item[0], item[1], item[0] === (value.terms_mode || 'children')); });
            mode.parentElement.hidden = available[selected].kind !== 'taxonomy';
            var actions = document.createElement('div');
            actions.className = 'sp-archive-editor__actions';
            tool(actions, 'moveUp', 'arrow-up-alt2', 'Move filter up', function () {
                if (row.previousElementSibling) list.insertBefore(row, row.previousElementSibling);
                renumber(); announce('Filter moved up.');
            });
            tool(actions, 'moveDown', 'arrow-down-alt2', 'Move filter down', function () {
                if (row.nextElementSibling) list.insertBefore(row.nextElementSibling, row);
                renumber(); announce('Filter moved down.');
            });
            tool(actions, 'removeFilter', 'trash', 'Remove filter', function () {
                var next = row.nextElementSibling || row.previousElementSibling;
                row.remove(); renumber();
                (next ? next.querySelector('select') : settings.querySelector('[data-archive-editor-add]')).focus();
                announce('Filter removed.');
            });
            row.append(actions);
            source.addEventListener('change', function () {
                label.placeholder = available[source.value].label;
                mode.parentElement.hidden = available[source.value].kind !== 'taxonomy';
                renumber();
            });
            list.append(row); renumber();
            if (focus) source.focus();
        }
        function render() {
            list.replaceChildren();
            (rowsByType[current] || []).forEach(function (row) { addRow(row, false); });
            scope.replaceChildren();
            var selectedScope = scopeByType[current] || {};
            Object.entries(taxonomies[current] || {}).forEach(function (item) {
                var taxonomy = item[0], data = item[1];
                var details = document.createElement('details');
                details.className = 'sp-archive-editor__scope';
                details.open = !!(selectedScope[taxonomy] || []).length;
                var summary = document.createElement('summary');
                summary.textContent = data.label;
                details.append(summary);
                var terms = document.createElement('div');
                var choices = Object.assign({}, data.terms || {});
                (selectedScope[taxonomy] || []).forEach(function (slug) {
                    if (!Object.prototype.hasOwnProperty.call(choices, slug)) choices[slug] = t('Unavailable term:') + ' ' + slug;
                });
                Object.entries(choices).forEach(function (term) {
                    var label = document.createElement('label');
                    var input = document.createElement('input');
                    input.type = 'checkbox'; input.value = term[0]; input.dataset.taxonomy = taxonomy;
                    input.name = prefix() + '[term_scope][' + taxonomy + '][]';
                    input.checked = (selectedScope[taxonomy] || []).includes(term[0]);
                    label.append(input, document.createTextNode(term[1])); terms.append(label);
                });
                if (!terms.children.length) {
                    var empty = document.createElement('p'); empty.className = 'description'; empty.textContent = t('No terms yet. Add terms to this post type first.'); terms.append(empty);
                }
                details.append(terms); scope.append(details);
            });
            card.querySelectorAll('[data-archive-manual-type]').forEach(function (panel) {
                var active = panel.dataset.archiveManualType === current;
                panel.hidden = !active;
                panel.querySelectorAll('[name]').forEach(function (input) { input.disabled = !active; });
            });
            card.querySelectorAll('[data-archive-type-label]').forEach(function (label) { label.textContent = typeInput.selectedOptions[0].text; });
            renumber();
        }
        settings.querySelector('[data-archive-editor-add]').addEventListener('click', function () {
            var used = readRows().map(function (row) { return row.source; });
            var source = Object.keys(sources[current] || {}).find(function (key) { return !used.includes(key); });
            if (source) addRow({source: source}, true);
        });
        typeInput.addEventListener('change', function () {
            rowsByType[current] = readRows(); scopeByType[current] = readScope();
            current = typeInput.value; render();
        });
        settings.addEventListener('input', remember);
        settings.addEventListener('change', remember);
        if ($.fn.sortable) $(list).sortable({items: '> .sp-archive-editor__row', handle: '.sp-archive-editor__drag', axis: 'y', update: function () { renumber(); announce('Filter order updated.'); }});
        render();
    }
    function boot(context) {
        var root = context && context.jquery ? context[0] : context || document;
        if (root.matches && root.matches('.sp-archive-builder-card')) initialize(root);
        root.querySelectorAll('.sp-archive-builder-card').forEach(initialize);
    }
    $(function () { boot(document); });
    if (window.acf) ['ready', 'append'].forEach(function (hook) { acf.addAction(hook, function ($el) { boot($el); }); });
})(jQuery);
