(function () {
	'use strict';

	tinymce.PluginManager.add('shortcode_button', function (editor) {
		editor.addButton('shortcode_button', {
			tooltip: 'Add shortcode',
			image: 'data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A//www.w3.org/2000/svg%22%20fill%3D%22%2350575e%22%20viewBox%3D%220%200%2020%2020%22%3E%3Cpath%20fill%3D%22none%22%20d%3D%22M0%200h20v20H0z%22/%3E%3Cpath%20d%3D%22M6%2014H4V6h2V4H2v12h4m1.1%201h2.1l3.7-14h-2.1M14%204v2h2v8h-2v2h4V4%22/%3E%3C/svg%3E',
			onclick: function () {
				const forms = ajax_params.shortcodes;

				if (!forms || !forms.length) {
					alert("Shortcodes not found!");
					return;
				}

				editor.windowManager.open({
					title: 'Select Shortcode',
					body: [
						{
							type: 'listbox',
							name: 'custom_shortcode',
							label: 'Shortcode',
							values: forms
						}
					],
					onsubmit: function (e) {
						const shortcode = e.data.custom_shortcode;
						if (shortcode) {
							editor.insertContent('[' + shortcode + ']');
						}
					}
				});
			}
		});
	});
})();
