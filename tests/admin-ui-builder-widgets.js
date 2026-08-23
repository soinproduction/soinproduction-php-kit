'use strict';

const fs = require('fs');
const path = require('path');
const vm = require('vm');

const source = fs.readFileSync(
    path.join(__dirname, '../plugins/sp-admin-ui/assets/builder-widget-selection.js'),
    'utf8'
);

const boundEvents = [];
const acfActions = [];

function jquery(value) {
    if (typeof value === 'function') {
		value();
        return undefined;
    }

    return {
        jquery: true,
        filter() { return this; },
        add() { return this; },
        find() { return this; },
        each() { return this; },
        off() { return this; },
		on(event, selector) {
			boundEvents.push({event, selector});
			return this;
		},
    };
}

const windowObject = {
    setTimeout,
	acf: {
		addAction(name) {
			acfActions.push(name);
		},
	},
};

vm.runInNewContext(source, {
    jQuery: jquery,
    window: windowObject,
    document: {},
});

const api = windowObject.SPBuilderWidgetSelection;
const checks = {
    'public helper API is exposed': !!api,
    'widget name is normalized in the layout title': api.formatLayoutTitle('  Home   — Reviews  ') === 'Widget: Home — Reviews',
    'empty selection restores the generic layout title': api.formatLayoutTitle('') === 'Widgets',
    'active card is vertically centered in its own list': api.centeredScrollTop(
        {top: 100, height: 400},
        {top: 650, height: 180},
        200
    ) === 640,
    'scroll position never becomes negative': api.centeredScrollTop(
        {top: 100, height: 400},
        {top: 110, height: 100},
        0
    ) === 0,
	'dynamic ACF rows are supported': acfActions.includes('append'),
	'widget changes update the title and scroll': boundEvents.some((binding) => (
		binding.event === 'click.spBuilderWidgetSelection' && binding.selector === '.wsb-radio-field .wsb-item'
	)),
};

const failed = Object.keys(checks).filter((name) => !checks[name]);
if (failed.length) {
    throw new Error('Admin UI Builder Widgets failures: ' + failed.join(', '));
}

console.log('Admin UI Builder Widgets: ' + Object.keys(checks).length + ' checks passed.');
