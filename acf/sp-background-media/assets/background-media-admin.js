/* SP Background Media — ACF admin behavior */
(function ($) {
	'use strict';

	function clamp(value, min, max, fallback) {
		var number = Number(value);
		return Number.isFinite(number) ? Math.max(min, Math.min(max, number)) : fallback;
	}

	function normalizeHex(value, fallback) {
		var hex = String(value || '').trim();
		if (/^#[0-9a-f]{3}$/i.test(hex)) {
			hex = '#' + hex.slice(1).split('').map(function (char) { return char + char; }).join('');
		}
		return /^#[0-9a-f]{6}$/i.test(hex) ? hex.toUpperCase() : (fallback || '#000000');
	}

	function announce($field, message) {
		var $status = $field.find('[data-sp-background-status]');
		$status.text('');
		window.setTimeout(function () { $status.text(message || ''); }, 20);
	}

	function previewUrl(attachment) {
		return attachment.url || (attachment.sizes && attachment.sizes.full ? attachment.sizes.full.url : '');
	}

	function emptyMediaPreview(type) {
		return $('<span>', { class: 'sp-background-field__empty' })
			.append($('<span>', { class: 'sp-background-field__empty-icon', 'aria-hidden': 'true' }).text('+'))
			.append($('<span>').text(type === 'video' ? 'Select MP4 or WEBM video' : 'Select an image'));
	}

	function emptyPosterPreview() {
		return $('<span>').text('Not selected');
	}

	function panelMediaType($panel) {
		return $panel.find('[data-sp-background-media-type]').val() === 'video' ? 'video' : 'image';
	}

	function renderPreview($panel) {
		var type = panelMediaType($panel);
		var $preview = $panel.find('[data-sp-background-preview]');
		var $content = $panel.find('[data-sp-background-preview-content]');
		var $image = $panel.find('[data-sp-background-image-id]');
		var $poster = $panel.find('[data-sp-background-poster-id]');
		var imageId = Number($image.val()) || 0;
		var mp4Id = Number($panel.find('[data-sp-background-video-id="mp4"]').val()) || 0;
		var webmId = Number($panel.find('[data-sp-background-video-id="webm"]').val()) || 0;
		var posterId = Number($poster.val()) || 0;
		var hasMedia = type === 'video' ? Boolean(mp4Id || webmId) : Boolean(imageId);
		var visualUrl = type === 'video' ? (posterId ? $poster.attr('data-preview-url') : '') : $image.attr('data-preview-url');
		var fileNames = ['webm', 'mp4'].map(function (format) {
			return $panel.find('[data-sp-background-video-id="' + format + '"]').attr('data-file-name') || '';
		}).filter(Boolean);

		$panel.find('[data-sp-background-media-id]').val(type === 'video' ? (mp4Id || webmId) : imageId);
		$preview
			.toggleClass('is-filled', hasMedia)
			.toggleClass('has-visual', Boolean(hasMedia && visualUrl))
			.attr('aria-disabled', hasMedia && visualUrl ? 'false' : 'true')
			.attr('tabindex', hasMedia && visualUrl ? '0' : '-1');
		$content.empty();
		if (hasMedia && visualUrl) {
			$('<img>', { src: visualUrl, alt: '' }).appendTo($content);
		} else if (type === 'video' && hasMedia) {
			$('<span>', { class: 'sp-background-field__file' })
				.append($('<span>', { class: 'sp-background-field__empty-icon', 'aria-hidden': 'true' }).text('▶'))
				.append($('<span>').text(fileNames.join(' · ') || 'Video selected')).appendTo($content);
		} else {
			$content.append(emptyMediaPreview(type));
		}
	}

	function hexToRgba(hex, opacity) {
		var normalized = normalizeHex(hex, '#000000').slice(1);
		return 'rgba(' + parseInt(normalized.slice(0, 2), 16) + ', ' + parseInt(normalized.slice(2, 4), 16) + ', ' + parseInt(normalized.slice(4, 6), 16) + ', ' + (clamp(opacity, 0, 100, 0) / 100) + ')';
	}

	function gradientStops($overlay) {
		var stops = [];
		$overlay.find('[data-sp-gradient-stop]').each(function () {
			var $stop = $(this);
			var color = normalizeHex($stop.find('[data-sp-stop-color]').val(), '#000000');
			var opacity = Math.round(clamp($stop.find('[data-sp-stop-opacity]').val(), 0, 100, 100));
			var position = Math.round(clamp($stop.find('[data-sp-stop-position]').val(), 0, 100, 50));
			$stop.find('[data-sp-stop-swatch]').css('--sp-current-color', color);
			stops.push({ color: color, opacity: opacity, position: position });
		});
		return stops.sort(function (left, right) { return left.position - right.position; });
	}

	function fieldState($field) {
		var state = {};
		$field.find('[data-sp-background-panel]').each(function () {
			var $panel = $(this);
			state[$panel.attr('data-sp-background-panel')] = {
				media_type: panelMediaType($panel),
				attachment_id: Number($panel.find('[data-sp-background-media-id]').val()) || 0,
				image_id: Number($panel.find('[data-sp-background-image-id]').val()) || 0,
				mp4_id: Number($panel.find('[data-sp-background-video-id="mp4"]').val()) || 0,
				webm_id: Number($panel.find('[data-sp-background-video-id="webm"]').val()) || 0,
				poster_id: Number($panel.find('[data-sp-background-poster-id]').val()) || 0,
				fit: $panel.find('[data-sp-background-fit]:checked').val() || 'cover',
				position_x: Math.round(clamp($panel.find('[data-sp-background-position="x"]').val(), 0, 100, 50)),
				position_y: Math.round(clamp($panel.find('[data-sp-background-position="y"]').val(), 0, 100, 50))
			};
		});

		var $overlay = $field.find('[data-sp-background-overlay]').first();
		if ($overlay.length) {
			state.overlay = {
				enabled: $overlay.find('[data-sp-background-overlay-enabled]').is(':checked') ? 1 : 0,
				type: $overlay.find('[data-sp-background-overlay-types] input:checked').val() || 'solid',
				color: normalizeHex($overlay.find('[data-sp-overlay-value="solid-color"]').val(), '#000000'),
				opacity: Math.round(clamp($overlay.find('[data-sp-overlay-value="solid-opacity"]').val(), 0, 100, 40)),
				angle: Math.round(clamp($overlay.find('[data-sp-overlay-value="angle"]').val(), 0, 360, 180)),
				stops: gradientStops($overlay)
			};
		}
		return state;
	}

	function syncState($field) {
		var $state = $field.find('[data-sp-background-state]').first();
		if ($state.length) {
			$state.val(JSON.stringify(fieldState($field)));
		}
	}

	function setPosition($panel, x, y) {
		x = Math.round(clamp(x, 0, 100, 50));
		y = Math.round(clamp(y, 0, 100, 50));
		$panel.find('[data-sp-background-position="x"]').val(x);
		$panel.find('[data-sp-background-position="y"]').val(y);
		syncPanel($panel);
	}

	function syncPanel($panel) {
		var type = panelMediaType($panel);
		var fit = $panel.find('[data-sp-background-fit]:checked').val() || 'cover';
		var x = Math.round(clamp($panel.find('[data-sp-background-position="x"]').val(), 0, 100, 50));
		var y = Math.round(clamp($panel.find('[data-sp-background-position="y"]').val(), 0, 100, 50));
		$panel.find('[data-sp-background-video-panel]').prop('hidden', type !== 'video');
		$panel.find('[data-sp-background-image-actions]').prop('hidden', type !== 'image');
		$panel.find('[data-sp-background-preview]').css({
			'--sp-preview-fit': fit,
			'--sp-preview-x': x + '%',
			'--sp-preview-y': y + '%',
			'--sp-focal-x': x + '%',
			'--sp-focal-y': y + '%'
		});
		$panel.find('[data-sp-background-position-output]').text(x + '% / ' + y + '%');
		renderPreview($panel);
		syncState($panel.closest('[data-sp-background-field]'));
	}

	function updateFocalFromEvent($surface, event) {
		var rect = $surface[0].getBoundingClientRect();
		setPosition(
			$surface.closest('[data-sp-background-panel]'),
			((event.clientX - rect.left) / rect.width) * 100,
			((event.clientY - rect.top) / rect.height) * 100
		);
	}

	function syncColorControl($control, value) {
		var hex = normalizeHex(value || $control.find('[data-sp-color-value]').val(), '#000000');
		$control.find('[data-sp-color-value]').val(hex);
		$control.find('[data-sp-color-native]').val(hex.toLowerCase());
		$control.find('[data-sp-color-chip]').css('--sp-current-color', hex);
		$control.find('[data-sp-palette-color]').each(function () {
			var active = normalizeHex($(this).attr('data-sp-palette-color'), '') === hex;
			$(this).toggleClass('is-active', active).attr('aria-pressed', active ? 'true' : 'false');
		});
	}

	function overlayValue($overlay, key, fallback) {
		var $input = $overlay.find('[data-sp-overlay-value="' + key + '"]').first();
		return $input.length ? $input.val() : fallback;
	}

	function syncOverlay($overlay) {
		if (!$overlay.length) { return; }
		var enabled = $overlay.find('[data-sp-background-overlay-enabled]').is(':checked');
		var type = $overlay.find('[data-sp-background-overlay-types] input:checked').val() || 'solid';
		var background;
		$overlay.find('[data-sp-background-overlay-body]').prop('hidden', !enabled);
		$overlay.find('[data-sp-background-overlay-controls]').each(function () {
			$(this).prop('hidden', $(this).attr('data-sp-background-overlay-controls') !== type);
		});
		if (type === 'gradient') {
			var stops = gradientStops($overlay).map(function (stop) {
				return hexToRgba(stop.color, stop.opacity) + ' ' + stop.position + '%';
			});
			background = 'linear-gradient(' + clamp(overlayValue($overlay, 'angle', 180), 0, 360, 180) + 'deg, ' + stops.join(', ') + ')';
		} else {
			background = hexToRgba(overlayValue($overlay, 'solid-color', '#000000'), overlayValue($overlay, 'solid-opacity', 40));
		}
		$overlay.find('[data-sp-background-overlay-preview]').css('--sp-overlay-preview', background);
		syncState($overlay.closest('[data-sp-background-field]'));
	}

	function renumberStops($overlay) {
		var $list = $overlay.find('[data-sp-gradient-stops]');
		var $stops = $list.children('[data-sp-gradient-stop]');
		var max = Number($list.attr('data-max-stops')) || 8;
		$stops.each(function (index) {
			var $stop = $(this);
			$stop.find('[name]').each(function () {
				this.name = this.name.replace(/\[stops\]\[(?:\d+|__INDEX__)\]/, '[stops][' + index + ']');
			});
			$stop.find('[data-sp-gradient-stop-label]').text(index + 1);
			$stop.find('[data-sp-gradient-stop-remove]').prop('hidden', $stops.length <= 2);
		});
		$overlay.find('[data-sp-gradient-stop-add]').prop('disabled', $stops.length >= max);
	}

	function nextStopPosition($overlay) {
		var positions = gradientStops($overlay).map(function (stop) { return stop.position; });
		var bestStart = 0;
		var bestGap = -1;
		for (var index = 1; index < positions.length; index += 1) {
			var gap = positions[index] - positions[index - 1];
			if (gap > bestGap) { bestGap = gap; bestStart = positions[index - 1]; }
		}
		return Math.round(bestStart + Math.max(bestGap, 0) / 2);
	}

	function updateMedia($panel, attachment) {
		var $field = $panel.closest('[data-sp-background-field]');
		var $image = $panel.find('[data-sp-background-image-id]');
		$image.val(attachment.id).attr('data-preview-url', previewUrl(attachment)).trigger('change');
		$panel.find('[data-sp-background-media-type]').val('image');
		$panel.find('[data-sp-background-type-choice][value="image"]').prop('checked', true);
		$panel.find('[data-sp-background-remove]').removeClass('is-hidden');
		$panel.find('[data-sp-background-select-label]').text('Replace image');
		syncPanel($panel);
		announce($field, 'Image selected: ' + (attachment.filename || attachment.title || 'image'));
	}

	function updateVideo($panel, attachment, format) {
		var mime = attachment.mime || (attachment.type && attachment.subtype ? attachment.type + '/' + attachment.subtype : '');
		var expected = format === 'webm' ? 'video/webm' : 'video/mp4';
		var upper = format.toUpperCase();
		var $field = $panel.closest('[data-sp-background-field]');
		if (mime !== expected) {
			announce($field, 'Please select a ' + upper + ' video.');
			return;
		}

		var fileName = attachment.filename || attachment.title || upper + ' video';
		var $input = $panel.find('[data-sp-background-video-id="' + format + '"]');
		$input.val(attachment.id).attr('data-file-name', fileName).trigger('change');
		$panel.find('[data-sp-background-video-status="' + format + '"]').addClass('is-filled').text(fileName);
		$panel.find('[data-sp-background-video-select="' + format + '"]').text('Replace ' + upper);
		$panel.find('[data-sp-background-video-remove="' + format + '"]').removeClass('is-hidden');
		$panel.find('[data-sp-background-media-type]').val('video');
		$panel.find('[data-sp-background-type-choice][value="video"]').prop('checked', true);
		syncPanel($panel);
		announce($field, upper + ' selected: ' + fileName);
	}

	function updatePoster($panel, attachment) {
		var $preview = $panel.find('[data-sp-background-poster-preview]');
		$panel.find('[data-sp-background-poster-id]').val(attachment.id).attr('data-preview-url', previewUrl(attachment)).trigger('change');
		$panel.find('[data-sp-background-poster-remove]').removeClass('is-hidden');
		$panel.find('[data-sp-background-poster-select-label]').text('Replace poster');
		$preview.addClass('is-filled').empty().append($('<img>', { src: previewUrl(attachment), alt: '' }));
		syncPanel($panel);
	}

	$(document).on('click', '[data-sp-background-tab]', function (event) {
		event.preventDefault();
		var $tab = $(this);
		var $field = $tab.closest('[data-sp-background-field]');
		var target = $tab.attr('data-sp-background-tab');
		$field.find('[data-sp-background-tab]').removeClass('is-active').attr('aria-selected', 'false');
		$tab.addClass('is-active').attr('aria-selected', 'true');
		$field.find('[data-sp-background-panel]').removeClass('is-active').prop('hidden', true);
		$field.find('[data-sp-background-panel="' + target + '"]').addClass('is-active').prop('hidden', false);
	});

	$(document).on('click', '[data-sp-background-select]', function (event) {
		event.preventDefault();
		var $panel = $(this).closest('[data-sp-background-panel]');
		var frame = wp.media({
			title: 'Select background image',
			button: { text: 'Use as background' },
			library: { type: 'image' },
			multiple: false
		});
		frame.on('select', function () { updateMedia($panel, frame.state().get('selection').first().toJSON()); });
		frame.open();
	});

	$(document).on('click', '[data-sp-background-remove]', function (event) {
		event.preventDefault();
		var $panel = $(this).closest('[data-sp-background-panel]');
		$panel.find('[data-sp-background-image-id]').val('').attr('data-preview-url', '');
		$panel.find('[data-sp-background-select-label]').text('Select image');
		$(this).addClass('is-hidden');
		syncPanel($panel);
	});

	$(document).on('change', '[data-sp-background-type-choice]', function () {
		var $panel = $(this).closest('[data-sp-background-panel]');
		$panel.find('[data-sp-background-media-type]').val($(this).val());
		syncPanel($panel);
	});

	$(document).on('click', '[data-sp-background-video-select]', function (event) {
		event.preventDefault();
		var $button = $(this);
		var $panel = $button.closest('[data-sp-background-panel]');
		var format = $button.attr('data-sp-background-video-select') === 'webm' ? 'webm' : 'mp4';
		var upper = format.toUpperCase();
		var frame = wp.media({ title: 'Select ' + upper + ' background video', button: { text: 'Use ' + upper }, library: { type: 'video' }, multiple: false });
		frame.on('select', function () { updateVideo($panel, frame.state().get('selection').first().toJSON(), format); });
		frame.open();
	});

	$(document).on('click', '[data-sp-background-video-remove]', function (event) {
		event.preventDefault();
		var $button = $(this);
		var $panel = $button.closest('[data-sp-background-panel]');
		var format = $button.attr('data-sp-background-video-remove') === 'webm' ? 'webm' : 'mp4';
		var upper = format.toUpperCase();
		$panel.find('[data-sp-background-video-id="' + format + '"]').val('').attr('data-file-name', '');
		$panel.find('[data-sp-background-video-status="' + format + '"]').removeClass('is-filled').text('Not selected');
		$panel.find('[data-sp-background-video-select="' + format + '"]').text('Select ' + upper);
		$button.addClass('is-hidden');
		syncPanel($panel);
	});

	$(document).on('click', '[data-sp-background-poster-select]', function (event) {
		event.preventDefault();
		var $panel = $(this).closest('[data-sp-background-panel]');
		var frame = wp.media({ title: 'Select video poster', button: { text: 'Use as poster' }, library: { type: 'image' }, multiple: false });
		frame.on('select', function () { updatePoster($panel, frame.state().get('selection').first().toJSON()); });
		frame.open();
	});

	$(document).on('click', '[data-sp-background-poster-remove]', function (event) {
		event.preventDefault();
		var $panel = $(this).closest('[data-sp-background-panel]');
		$panel.find('[data-sp-background-poster-id]').val('').attr('data-preview-url', '');
		$panel.find('[data-sp-background-poster-preview]').removeClass('is-filled').empty().append(emptyPosterPreview());
		$panel.find('[data-sp-background-poster-select-label]').text('Select poster');
		$(this).addClass('is-hidden');
		syncPanel($panel);
	});

	$(document).on('change', '[data-sp-background-fit]', function () { syncPanel($(this).closest('[data-sp-background-panel]')); });
	$(document).on('input change', '[data-sp-background-position]', function () {
		var $panel = $(this).closest('[data-sp-background-panel]');
		setPosition($panel, $panel.find('[data-sp-background-position="x"]').val(), $panel.find('[data-sp-background-position="y"]').val());
	});

	$(document).on('pointerdown', '[data-sp-background-focal-surface]', function (event) {
		if (!$(this).hasClass('has-visual')) { return; }
		event.preventDefault();
		this.setPointerCapture(event.originalEvent.pointerId);
		$(this).attr('data-dragging', 'true');
		updateFocalFromEvent($(this), event.originalEvent);
	});
	$(document).on('pointermove', '[data-sp-background-focal-surface][data-dragging="true"]', function (event) { updateFocalFromEvent($(this), event.originalEvent); });
	$(document).on('pointerup pointercancel', '[data-sp-background-focal-surface]', function () { $(this).removeAttr('data-dragging'); });
	$(document).on('keydown', '[data-sp-background-focal-surface]', function (event) {
		if (!$(this).hasClass('has-visual')) { return; }
		var movement = { ArrowLeft: [-1, 0], ArrowRight: [1, 0], ArrowUp: [0, -1], ArrowDown: [0, 1] }[event.key];
		if (!movement) { return; }
		event.preventDefault();
		var $panel = $(this).closest('[data-sp-background-panel]');
		var step = event.shiftKey ? 10 : 1;
		setPosition($panel, Number($panel.find('[data-sp-background-position="x"]').val()) + movement[0] * step, Number($panel.find('[data-sp-background-position="y"]').val()) + movement[1] * step);
	});

	$(document).on('input change', '[data-sp-background-overlay] input', function () {
		var $control = $(this).closest('[data-sp-color-control]');
		if ($control.length && $(this).is('[data-sp-color-native], [data-sp-color-value]')) { syncColorControl($control, $(this).val()); }
		syncOverlay($(this).closest('[data-sp-background-overlay]'));
	});
	$(document).on('click', '[data-sp-palette-color]', function () {
		var $control = $(this).closest('[data-sp-color-control]');
		syncColorControl($control, $(this).attr('data-sp-palette-color'));
		syncOverlay($(this).closest('[data-sp-background-overlay]'));
	});
	$(document).on('click', '[data-sp-gradient-angle]', function () {
		var $overlay = $(this).closest('[data-sp-background-overlay]');
		$overlay.find('[data-sp-overlay-value="angle"]').val($(this).attr('data-sp-gradient-angle'));
		syncOverlay($overlay);
	});
	$(document).on('click', '[data-sp-gradient-stop-add]', function () {
		var $overlay = $(this).closest('[data-sp-background-overlay]');
		var $list = $overlay.find('[data-sp-gradient-stops]');
		if ($list.children('[data-sp-gradient-stop]').length >= (Number($list.attr('data-max-stops')) || 8)) { return; }
		var html = $overlay.find('[data-sp-gradient-stop-template]').html().replace(/__INDEX__/g, String(Date.now()));
		var $stop = $(html.trim());
		$stop.find('[data-sp-stop-position]').val(nextStopPosition($overlay));
		$list.append($stop);
		$stop.find('[data-sp-color-control]').each(function () { syncColorControl($(this)); });
		renumberStops($overlay);
		syncOverlay($overlay);
	});
	$(document).on('click', '[data-sp-gradient-stop-remove]', function () {
		var $overlay = $(this).closest('[data-sp-background-overlay]');
		if ($overlay.find('[data-sp-gradient-stops]').children('[data-sp-gradient-stop]').length <= 2) { return; }
		$(this).closest('[data-sp-gradient-stop]').remove();
		renumberStops($overlay);
		syncOverlay($overlay);
	});

	function init($scope) {
		$scope.find('[data-sp-background-field]').addBack('[data-sp-background-field]').each(function () {
			var $field = $(this);
			$field.find('[data-sp-color-control]').each(function () { syncColorControl($(this)); });
			$field.find('[data-sp-background-panel]').each(function () { syncPanel($(this)); });
			$field.find('[data-sp-background-overlay]').each(function () { renumberStops($(this)); syncOverlay($(this)); });
			syncState($field);
		});
	}

	$(document).on('submit', 'form', function () { $(this).find('[data-sp-background-field]').each(function () { syncState($(this)); }); });
	$(function () { init($(document)); });
	if (window.acf) {
		window.acf.addAction('append', function ($element) { init($element); });
		window.acf.addAction('validation_begin', function ($form) { $form.find('[data-sp-background-field]').each(function () { syncState($(this)); }); });
	}
})(jQuery);
