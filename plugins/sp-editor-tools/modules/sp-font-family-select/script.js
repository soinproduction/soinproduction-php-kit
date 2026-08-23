(function () {
    tinymce.PluginManager.add('font_family_select', function (editor, url) {
        editor.addButton('font_family_select', {
            type: 'listbox',
            text: 'Font Family',
            icon: false,
            tooltip: 'Select Family',
            onselect: function (e) {
                const font = this.value();
                editor.formatter.register('custom_font_family', {
                    inline: 'span',
                    styles: { 'font-family': font },
                    remove_similar: true
                });
                editor.formatter.apply('custom_font_family');
            },
            values: [
                { text: 'IBM Plex Sans', value: 'IBM Plex Sans, sans-serif' },
                { text: 'IBM Plex Mono', value: 'IBM Plex Mono, sans-serif' },
            ]
        });
    });
})();
