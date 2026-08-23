(function () {
    tinymce.PluginManager.add('social_list', function (editor) {
        const svg = `
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="#50575e" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="18" cy="5" r="3"></circle>
              <circle cx="6" cy="12" r="3"></circle>
              <circle cx="18" cy="19" r="3"></circle>
              <line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line>
              <line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line>
            </svg>
        `;

        var iconDataUri = 'data:image/svg+xml;utf8,' + encodeURIComponent(svg);

        editor.addButton('social_list', {
            tooltip: 'Insert Social List',
            image: iconDataUri,
            onclick: function () {
                editor.insertContent('[social_list]');
            }
        });
    });
})();
