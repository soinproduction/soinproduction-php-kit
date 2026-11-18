(function ($) {
    'use strict';
    var nextId = 0;
    function initialize(card) {
        if (card.closest('.acf-clone')) return;
        var root = card.querySelector('[data-sp-archive-editor]');
        if (!root || root.__spArchiveEditor) return;
        root.__spArchiveEditor = true;
        var i18n = JSON.parse(root.dataset.i18n || '{}');
        function t(text) { return i18n[text] || text; }
        var settings = card.querySelector('[data-archive-editor-settings]');
        var list = settings.querySelector('[data-archive-editor-rows]');
        var values = settings.querySelector('[data-archive-editor-values]');
        var scope = settings.querySelector('[data-archive-editor-scope]');
        var typeInput = root.querySelector('[data-archive-editor-type]');
        var sources = JSON.parse(root.dataset.sources || '{}');
        var taxonomies = JSON.parse(root.dataset.taxonomies || '{}');
        var rowsByType = {}, scopeByType = {};
        var current = root.dataset.currentType && sources[root.dataset.currentType] ? root.dataset.currentType : typeInput.value;
        typeInput.value = current;
        rowsByType[current] = JSON.parse(root.dataset.rows || '[]');
        scopeByType[current] = JSON.parse(root.dataset.scope || '{}');
        function prefix() { return root.querySelector('input[name$="[editor_configured]"]').name.replace(/\[editor_configured\]$/, ''); }
        function option(select, value, text, selected) { select.add(new Option(t(text), value, false, selected)); }
        function control(parent, key, text, element) {
            var label = document.createElement('label'), caption = document.createElement('span');
            caption.textContent = t(text); label.append(caption, element); element.dataset.key = key; parent.append(label);
            return element;
        }
        function readRows() {
            return Array.from(list.children).map(function (row) {
                var result = {source: row.dataset.source};
                row.querySelectorAll('[data-key]').forEach(function (input) {
                    result[input.dataset.key] = input.type === 'checkbox' ? (input.checked ? 1 : 0) : input.value;
                });
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
        function remember() {
            root.dataset.currentType = current;
            root.dataset.rows = JSON.stringify(readRows());
            root.dataset.scope = JSON.stringify(readScope());
            values.replaceChildren();
            readRows().forEach(function (row, index) {
                Object.entries(row).forEach(function (entry) {
                    var input = document.createElement('input'); input.type = 'hidden';
                    input.name = prefix() + '[editor_filters][' + index + '][' + entry[0] + ']'; input.value = entry[1]; values.append(input);
                });
            });
        }
        function announce(message) { settings.querySelector('[data-archive-editor-status]').textContent = t(message); }
        function tool(parent, action, icon, text, handler) {
            var button = document.createElement('button'); button.type = 'button';
            button.className = 'button sp-archive-editor__tool'; button.setAttribute('aria-label', t(text)); button.title = t(text); button.dataset[action] = '';
            var glyph = document.createElement('span'); glyph.className = 'dashicons dashicons-' + icon; glyph.setAttribute('aria-hidden', 'true'); button.append(glyph);
            button.addEventListener('click', handler); parent.append(button); return button;
        }
        function refresh() {
            var active = Array.from(list.children).filter(function (row) { return row.querySelector('[data-key="enabled"]').checked; });
            Array.from(list.children).forEach(function (row) {
                var enabled = active.includes(row), index = active.indexOf(row);
                row.classList.toggle('is-active', enabled);
                row.querySelector('[data-key="ui"]').disabled = !enabled;
                row.querySelector('[data-move-up]').disabled = !enabled || index === 0;
                row.querySelector('[data-move-down]').disabled = !enabled || index === active.length - 1;
                row.querySelector('[data-details]').disabled = !enabled;
                if (!enabled) { row.querySelector('[data-details]').setAttribute('aria-expanded', 'false'); row.querySelector('.sp-archive-editor__details').hidden = true; }
                var label = row.querySelector('[data-key="label"]').value.trim(), original = sources[current][row.dataset.source].label;
                row.querySelector('[data-title]').textContent = label || original;
                row.querySelector('[data-details]').setAttribute('aria-label', t('Filter settings') + ': ' + (label || original));
                var secondary = row.querySelector('[data-original]'); secondary.textContent = t('Source:') + ' ' + original; secondary.hidden = !label || label === original;
            });
            settings.querySelector('[data-archive-editor-empty]').hidden = list.children.length > 0;
            remember();
        }
        function addRow(key, value) {
            var source = sources[current][key], row = document.createElement('div'); row.className = 'sp-archive-editor__row'; row.dataset.source = key;
            var main = document.createElement('div'); main.className = 'sp-archive-editor__main';
            var drag = document.createElement('span'); drag.className = 'dashicons dashicons-menu sp-archive-editor__drag'; drag.setAttribute('aria-hidden', 'true'); main.append(drag);
            var toggle = document.createElement('label'); toggle.className = 'sp-archive-editor__toggle';
            var enabled = document.createElement('input'); enabled.type = 'checkbox'; enabled.dataset.key = 'enabled'; enabled.checked = value.enabled === undefined ? !!value.source : !!Number(value.enabled);
            var copy = document.createElement('span'), title = document.createElement('strong'), original = document.createElement('small'); title.dataset.title = ''; original.dataset.original = ''; copy.append(title, original); toggle.append(enabled, copy); main.append(toggle);
            var ui = control(main, 'ui', 'Display', document.createElement('select'));
            Object.entries({buttons:'Buttons', checkbox:'Checkboxes', select:'Select', multiselect:'Multiple select', radio:'Radio buttons'}).forEach(function (entry) { option(ui, entry[0], entry[1], entry[0] === (value.ui || source.definition.ui || 'checkbox')); });
            var actions = document.createElement('div'); actions.className = 'sp-archive-editor__actions';
            function move(direction) {
                var sibling = direction < 0 ? row.previousElementSibling : row.nextElementSibling;
                while (sibling && !sibling.querySelector('[data-key="enabled"]').checked) sibling = direction < 0 ? sibling.previousElementSibling : sibling.nextElementSibling;
                if (sibling) list.insertBefore(row, direction < 0 ? sibling : sibling.nextElementSibling);
                refresh(); announce('Filter order updated.');
            }
            tool(actions, 'moveUp', 'arrow-up-alt2', 'Move filter up', function () { move(-1); });
            tool(actions, 'moveDown', 'arrow-down-alt2', 'Move filter down', function () { move(1); });
            var details = document.createElement('div'); details.className = 'sp-archive-editor__details'; details.id = 'sp-archive-filter-details-' + (++nextId); details.hidden = true;
            var disclosure = tool(actions, 'details', 'admin-generic', 'Filter settings', function () {
                details.hidden = !details.hidden; disclosure.setAttribute('aria-expanded', String(!details.hidden));
            });
            disclosure.setAttribute('aria-controls', details.id); disclosure.setAttribute('aria-expanded', 'false'); main.append(actions); row.append(main);
            var label = control(details, 'label', 'Name on website', document.createElement('input')); label.type = 'text'; label.value = value.label || ''; label.placeholder = source.label;
            var mode = control(details, 'terms_mode', 'Category options', document.createElement('select'));
            Object.entries({children:'Include subcategories', parent:'Top-level categories', selected:'Only selected categories'}).forEach(function (entry) { option(mode, entry[0], entry[1], entry[0] === (value.terms_mode || 'children')); });
            mode.parentElement.hidden = !source.hierarchical;
            row.append(details); list.append(row);
        }
        function render() {
            list.replaceChildren(); scope.replaceChildren();
            var saved = rowsByType[current] || [], available = sources[current] || {}, seen = {};
            saved.forEach(function (row) { if (available[row.source] && !seen[row.source]) { seen[row.source] = true; addRow(row.source, row); } });
            Object.keys(available).forEach(function (key) { if (!seen[key]) addRow(key, {enabled:0}); });
            var selectedScope = scopeByType[current] || {};
            Object.entries(taxonomies[current] || {}).forEach(function (item) {
                var taxonomy = item[0], data = item[1], details = document.createElement('details'); details.className = 'sp-archive-editor__scope'; details.open = !!(selectedScope[taxonomy] || []).length;
                var summary = document.createElement('summary'); summary.textContent = data.label; details.append(summary);
                var terms = document.createElement('div'), choices = Object.assign({}, data.terms || {});
                (selectedScope[taxonomy] || []).forEach(function (slug) { if (!Object.prototype.hasOwnProperty.call(choices, slug)) choices[slug] = t('Unavailable term:') + ' ' + slug; });
                Object.entries(choices).forEach(function (term) {
                    var label = document.createElement('label'), input = document.createElement('input'); input.type = 'checkbox'; input.value = term[0]; input.dataset.taxonomy = taxonomy;
                    input.name = prefix() + '[term_scope][' + taxonomy + '][]'; input.checked = (selectedScope[taxonomy] || []).includes(term[0]); label.append(input, document.createTextNode(term[1])); terms.append(label);
                });
                if (!terms.children.length) { var empty = document.createElement('p'); empty.className = 'description'; empty.textContent = t('No terms yet. Add terms to this post type first.'); terms.append(empty); }
                details.append(terms); scope.append(details);
            });
            card.querySelectorAll('[data-archive-manual-type]').forEach(function (panel) {
                var active = panel.dataset.archiveManualType === current; panel.hidden = !active; panel.querySelectorAll('[name]').forEach(function (input) { input.disabled = !active; });
            });
            card.querySelectorAll('[data-archive-type-label]').forEach(function (label) { label.textContent = typeInput.selectedOptions[0].text; });
            refresh();
        }
        typeInput.addEventListener('change', function () { rowsByType[current] = readRows(); scopeByType[current] = readScope(); current = typeInput.value; render(); });
        settings.addEventListener('input', refresh); settings.addEventListener('change', refresh);
        if ($.fn.sortable) $(list).sortable({items:'> .sp-archive-editor__row.is-active', handle:'.sp-archive-editor__drag', axis:'y', update:function () { refresh(); announce('Filter order updated.'); }});
        render();
    }
    function organizeFieldSettings(context) {
        context.querySelectorAll('[data-archive-field-catalog]').forEach(function (catalog) {
            if (catalog.__organized) return; catalog.__organized = true;
            var object = catalog.closest('.acf-field-object') || catalog.parentElement.parentElement;
            var groups = [
                {title:catalog.dataset.initial, keys:['filters_enabled','term_scope','filters'], mode:'initial'},
                {title:catalog.dataset.display, keys:['confirm','reset','disable_empty','group_on_all','load_more_label','all_label']},
                {title:catalog.dataset.advanced, keys:['action','page_arg','url_page_arg','sort_arg','per_page_arg']}
            ];
            groups.forEach(function (group) {
                var fields = group.keys.map(function (key) { return object.querySelector('.acf-field-setting-' + key); }).filter(Boolean);
                if (!fields.length || fields[0].closest('.sp-archive-field-settings')) return;
                var details = document.createElement('details'); details.className = 'sp-archive-field-settings'; if(group.mode) details.dataset.archiveInitial = '';
                var summary = document.createElement('summary'); summary.textContent = group.title; details.append(summary); fields[0].before(details); fields.forEach(function (field) { details.append(field); });
            });
            function sync() {
                var toggle = object.querySelector('.acf-field-setting-editor_config input[type=checkbox]'); var enabled = toggle && toggle.checked;
                catalog.closest('.acf-field').hidden = !enabled;
                var types = object.querySelector('.acf-field-setting-allowed_post_types'); if(types) types.hidden = !enabled;
                var initial = object.querySelector('[data-archive-initial]'); if(initial) { initial.open = !enabled; initial.querySelector('summary').textContent = enabled ? catalog.dataset.initial : catalog.dataset.fixed; }
                var selected = types ? Array.from(types.querySelector('select').selectedOptions).map(function (option) { return option.value; }) : [];
                catalog.querySelectorAll('[data-catalog-type]').forEach(function (panel) { panel.hidden = selected.length > 0 && !selected.includes(panel.dataset.catalogType); });
            }
            $(object).on('change', '.acf-field-setting-editor_config input, .acf-field-setting-allowed_post_types select', sync); sync();
        });
    }
    function boot(context) {
        var root = context && context.jquery ? context[0] : context || document;
        if (root.matches && root.matches('.sp-archive-builder-card')) initialize(root);
        root.querySelectorAll('.sp-archive-builder-card').forEach(initialize); organizeFieldSettings(root);
    }
    $(function () { boot(document); });
    if (window.acf) ['ready','append','open_field'].forEach(function (hook) { acf.addAction(hook, function ($el) { boot($el); }); });
})(jQuery);
