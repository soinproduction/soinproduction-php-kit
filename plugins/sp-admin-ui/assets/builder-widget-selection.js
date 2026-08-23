(function ($, window, document) {
    'use strict';

    var FIELD_SELECTOR = '.wsb-radio-field';
    var ACTIVE_ITEM_SELECTOR = '.wsb-list .wsb-item.is-active';
    var TITLE_SELECTOR = '.acf-fc-layout-handle .acf-fc-layout-title';
    var observerKey = 'spBuilderWidgetTitleObserver';
    var scrollKey = 'spBuilderWidgetInitialScroll';

    function cleanTitle(value) {
        return String(value || '').replace(/\s+/g, ' ').trim();
    }

    function formatLayoutTitle(widgetTitle) {
        widgetTitle = cleanTitle(widgetTitle);
        return widgetTitle ? 'Widget: ' + widgetTitle : 'Widgets';
    }

    function centeredScrollTop(listRect, itemRect, currentScroll) {
        var itemTop = itemRect.top - listRect.top + currentScroll;
        var top = itemTop - ((listRect.height - itemRect.height) / 2);
        return Math.max(0, Math.round(top));
    }

    function activeWidget($field) {
        var $active = $field.find(ACTIVE_ITEM_SELECTOR).first();
        if (!$active.length) {
            return {item: $(), title: ''};
        }

        return {
            item: $active,
            title: cleanTitle($active.find('.wsb-item-name').first().text())
        };
    }

    function layoutForField($field) {
        return $field.closest('.layout[data-layout]').first();
    }

    function writeLayoutTitle($field, widgetTitle) {
        var $layout = layoutForField($field);
        var $title = $layout.children('.acf-fc-layout-actions-wrap').find(TITLE_SELECTOR).first();
        if (!$title.length) {
            return;
        }

        var title = formatLayoutTitle(widgetTitle);
        if (cleanTitle($title.text()) !== title) {
            $title.text(title);
        }

        if (typeof window.MutationObserver !== 'function' || $title.data(observerKey)) {
            return;
        }

        var observer = new window.MutationObserver(function () {
            var current = activeWidget($field);
            var expected = formatLayoutTitle(current.title);
            if (cleanTitle($title.text()) !== expected) {
                $title.text(expected);
            }
        });
        observer.observe($title[0], {childList: true, characterData: true, subtree: true});
        $title.data(observerKey, observer);
    }

    function scrollToActive($field, force) {
        var current = activeWidget($field);
        var $list = $field.find('.wsb-list').first();
        if (!current.item.length || !$list.length) {
            return;
        }

        if (!force && $field.data(scrollKey)) {
            return;
        }

        var list = $list[0];
        var item = current.item[0];
        if (!list || !item || list.clientHeight <= 0) {
            return;
        }

        var top = centeredScrollTop(list.getBoundingClientRect(), item.getBoundingClientRect(), list.scrollTop || 0);
        if (typeof list.scrollTo === 'function') {
            list.scrollTo({top: top, behavior: 'auto'});
        } else {
            list.scrollTop = top;
        }

        $field.data(scrollKey, true);
    }

    function syncField($field, forceScroll) {
        if (!$field || !$field.length) {
            return;
        }

        var current = activeWidget($field);
        writeLayoutTitle($field, current.title);
        scrollToActive($field, !!forceScroll);
    }

    function syncWithin($root) {
        var $root = $root && $root.jquery ? $root : $($root || document);
        $root.filter(FIELD_SELECTOR).add($root.find(FIELD_SELECTOR)).each(function () {
            syncField($(this), false);
        });
    }

    function initialize() {
        syncWithin($(document));

        $(document)
            .off('.spBuilderWidgetSelection')
            .on('click.spBuilderWidgetSelection', FIELD_SELECTOR + ' .wsb-item', function () {
                var $field = $(this).closest(FIELD_SELECTOR);
                window.setTimeout(function () {
                    syncField($field, true);
                }, 0);
            })
            .on('change.spBuilderWidgetSelection', FIELD_SELECTOR + ' input[type="radio"]', function () {
                var $field = $(this).closest(FIELD_SELECTOR);
                window.setTimeout(function () {
                    syncField($field, true);
                }, 0);
            })
            .on('click.spBuilderWidgetSelection', '.layout[data-layout] > .acf-fc-layout-actions-wrap [data-name="collapse-layout"]', function () {
                var $field = $(this).closest('.layout[data-layout]').find(FIELD_SELECTOR).first();
                window.setTimeout(function () {
                    syncField($field, false);
                }, 0);
            });

        if (window.acf && typeof window.acf.addAction === 'function') {
            window.acf.addAction('append', syncWithin);
            window.acf.addAction('ready', function () {
                syncWithin($(document));
            });
        }
    }

    window.SPBuilderWidgetSelection = {
        centeredScrollTop: centeredScrollTop,
        cleanTitle: cleanTitle,
        formatLayoutTitle: formatLayoutTitle,
        syncField: syncField,
        syncWithin: syncWithin
    };

    $(initialize);
}(jQuery, window, document));
