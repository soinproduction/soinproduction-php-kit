(function () {
    tinymce.PluginManager.add('aosanimate', function (editor) {
        function getHostEl() {
            var dom = editor.dom;
            var n = editor.selection.getNode();
            return dom.getParent(n, 'p,h1,h2,h3,h4,h5,h6,li,div,section,article,figure') || n;
        }

        function setAttr(el, name, val, opts) {
            opts = opts || {};
            if (val == null || val === '' || (opts.numeric && isNaN(val))) {
                el.removeAttribute(name);
                return;
            }
            if (opts.bool) {
                if (val === true || val === 'true' || val === '1' || val === 1) {
                    el.setAttribute(name, 'true');
                } else {
                    el.removeAttribute(name);
                }
                return;
            }
            if (opts.numeric) val = String(parseInt(val, 10));
            el.setAttribute(name, val);
        }

        function openDialog(preset) {
            preset = preset || {};
            var win = editor.windowManager.open({
                title: 'AOS Animation',
                body: [
                    {
                        type: 'listbox',
                        name: 'aos',
                        label: 'Animation Type',
                        values: [
                            { value: '', text: 'none' },
                            { value: 'fade', text: 'fade' },
                            { value: 'fade-up', text: 'fade-up' },
                            { value: 'fade-down', text: 'fade-down' },
                            { value: 'fade-left', text: 'fade-left' },
                            { value: 'fade-right', text: 'fade-right' },
                            { value: 'fade-up-right', text: 'fade-up-right' },
                            { value: 'fade-up-left', text: 'fade-up-left' },
                            { value: 'flip-up', text: 'flip-up' },
                            { value: 'flip-down', text: 'flip-down' },
                            { value: 'flip-left', text: 'flip-left' },
                            { value: 'flip-right', text: 'flip-right' },
                            { value: 'slide-up', text: 'slide-up' },
                            { value: 'slide-down', text: 'slide-down' },
                            { value: 'slide-left', text: 'slide-left' },
                            { value: 'slide-right', text: 'slide-right' },
                            { value: 'zoom-in', text: 'zoom-in' },
                            { value: 'zoom-in-up', text: 'zoom-in-up' },
                            { value: 'zoom-in-down', text: 'zoom-in-down' },
                            { value: 'zoom-in-left', text: 'zoom-in-left' },
                            { value: 'zoom-in-right', text: 'zoom-in-right' },
                            { value: 'zoom-out', text: 'zoom-out' },
                            { value: 'zoom-out-up', text: 'zoom-out-up' },
                            { value: 'zoom-out-down', text: 'zoom-out-down' },
                            { value: 'zoom-out-left', text: 'zoom-out-left' },
                            { value: 'zoom-out-right', text: 'zoom-out-right' }
                        ]
                    },
                    { type: 'textbox', name: 'aos_duration', label: 'Duration (ms)', value: preset.aos_duration || '400', subtype: 'number' },
                    { type: 'textbox', name: 'aos_delay',    label: 'Delay (ms)',    value: preset.aos_delay    || '0',   subtype: 'number' },
                    { type: 'textbox', name: 'aos_offset',   label: 'Offset (px)',   value: preset.aos_offset   || '120', subtype: 'number' },
                    { type: 'checkbox',name: 'aos_once',     label: 'Once',          checked: !!preset.aos_once },
                    {
                        type: 'listbox',
                        name: 'aos_placement',
                        label: 'Anchor Placement',
                        values: [
                            { value: 'top-bottom',    text: 'top-bottom' },
                            { value: 'top-center',    text: 'top-center' },
                            { value: 'top-top',       text: 'top-top' },
                            { value: 'center-bottom', text: 'center-bottom' },
                            { value: 'center-top',    text: 'center-top' },
                            { value: 'bottom-bottom', text: 'bottom-bottom' },
                            { value: 'bottom-center', text: 'bottom-center' },
                            { value: 'bottom-top',    text: 'bottom-top' }
                        ]
                    },
                    {
                        type: 'listbox',
                        name: 'aos_easing',
                        label: 'Easing',
                        values: [
                            { value: 'linear', text: 'linear' },
                            { value: 'ease', text: 'ease' },
                            { value: 'ease-in', text: 'ease-in' },
                            { value: 'ease-out', text: 'ease-out' },
                            { value: 'ease-in-out', text: 'ease-in-out' },
                            { value: 'ease-in-back', text: 'ease-in-back' },
                            { value: 'ease-out-back', text: 'ease-out-back' },
                            { value: 'ease-in-out-back', text: 'ease-in-out-back' },
                            { value: 'ease-in-sine', text: 'ease-in-sine' },
                            { value: 'ease-out-sine', text: 'ease-out-sine' },
                            { value: 'ease-in-out-sine', text: 'ease-in-out-sine' },
                            { value: 'ease-in-quad', text: 'ease-in-quad' },
                            { value: 'ease-out-quad', text: 'ease-out-quad' },
                            { value: 'ease-in-out-quad', text: 'ease-in-out-quad' },
                            { value: 'ease-in-cubic', text: 'ease-in-cubic' },
                            { value: 'ease-out-cubic', text: 'ease-out-cubic' },
                            { value: 'ease-in-out-cubic', text: 'ease-in-out-cubic' },
                            { value: 'ease-in-quart', text: 'ease-in-quart' },
                            { value: 'ease-out-quart', text: 'ease-out-quart' },
                            { value: 'ease-in-out-quart', text: 'ease-in-out-quart' }
                        ]
                    }
                ],
                onsubmit: function (e) {
                    var d = e.data;
                    var host = getHostEl();
                    if (!d.aos) {
                        [
                            'data-aos','data-aos-duration','data-aos-delay','data-aos-offset',
                            'data-aos-once','data-aos-easing','data-aos-anchor-placement'
                        ].forEach(function (k) { host.removeAttribute(k); });
                        return;
                    }

                    host.setAttribute('data-aos', d.aos);
                    setAttr(host, 'data-aos-duration', d.aos_duration, { numeric: true });
                    setAttr(host, 'data-aos-delay',    d.aos_delay,    { numeric: true });
                    setAttr(host, 'data-aos-offset',   d.aos_offset,   { numeric: true });
                    setAttr(host, 'data-aos-once',     d.aos_once,     { bool: true });
                    setAttr(host, 'data-aos-easing',   d.aos_easing);
                    setAttr(host, 'data-aos-anchor-placement', d.aos_placement);
                }
            });

            if (preset.aos != null)   win.find('[name=aos]').value(preset.aos);
            if (preset.aos_placement) win.find('[name=aos_placement]').value(preset.aos_placement);
            if (preset.aos_easing)    win.find('[name=aos_easing]').value(preset.aos_easing);

            return win;
        }

        editor.addButton('aosanimate', {
            text: 'AOS',
            icon: false,
            tooltip: 'AOS animation attributes',
            onclick: function () {
                var el = getHostEl();
                var preset = {
                    aos: el.getAttribute('data-aos') || '',
                    aos_duration: el.getAttribute('data-aos-duration') || '400',
                    aos_delay: el.getAttribute('data-aos-delay') || '0',
                    aos_offset: el.getAttribute('data-aos-offset') || '120',
                    aos_once: (el.getAttribute('data-aos-once') === 'true'),
                    aos_easing: el.getAttribute('data-aos-easing') || 'ease',
                    aos_placement: el.getAttribute('data-aos-anchor-placement') || 'top-bottom'
                };
                openDialog(preset);
            }
        });

        editor.addMenuItem('aosanimate', {
            text: 'AOS Animate...',
            context: 'format',
            onclick: function () {
                var el = getHostEl();
                var preset = {
                    aos: el.getAttribute('data-aos') || '',
                    aos_duration: el.getAttribute('data-aos-duration') || '400',
                    aos_delay: el.getAttribute('data-aos-delay') || '0',
                    aos_offset: el.getAttribute('data-aos-offset') || '120',
                    aos_once: (el.getAttribute('data-aos-once') === 'true'),
                    aos_easing: el.getAttribute('data-aos-easing') || 'ease',
                    aos_placement: el.getAttribute('data-aos-anchor-placement') || 'top-bottom'
                };
                openDialog(preset);
            }
        });
    });
})();
