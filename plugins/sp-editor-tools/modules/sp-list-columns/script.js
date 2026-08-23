(function () {
	'use strict';

	tinymce.PluginManager.add('list_columns', function (editor) {
		var icons = {
			'2': {$icon},
			'3': {$icon3},
			'4': {$icon4}
		};

		function getClosestList(node) {
			var element = node && node.nodeType === 3 ? node.parentNode : node;
			var body = editor.getBody();

			while (element && element.nodeType === 1 && element !== body) {
				if (element.nodeName === 'UL' || element.nodeName === 'OL') {
					return element;
				}
				element = element.parentNode;
			}

			return null;
		}

		function setupButton(name, columns) {
			editor.addButton(name, {
				image: icons[columns],
				tooltip: columns + '-column list',
				onclick: function () {
					var list = getClosestList(editor.selection.getNode());

					if (!list) {
						editor.windowManager.alert('Please place cursor inside a list (ul or ol).');
						return;
					}

					editor.undoManager.transact(function () {
						var current = editor.dom.getAttrib(list, 'data-column');
						editor.dom.setAttrib(list, 'data-column', current === columns ? null : columns);
					});

					editor.nodeChanged();
				},
				onPostRender: function () {
					var button = this;
					editor.on('NodeChange', function (event) {
						var list = getClosestList(event.element);
						button.active(!!list && editor.dom.getAttrib(list, 'data-column') === columns);
					});
				}
			});
		}

		setupButton('list_columns', '2');
		setupButton('list_columns_3', '3');
		setupButton('list_columns_4', '4');
	});
})();
