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
		return attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url;
	}

	function emptyMediaPreview() {
		return $('<span>', { class: 'sp-background-field__empty' })
			.append($('<span>', { class: 'sp-background-field__empty-icon', 'aria-hidden': 'true' }).text('+'))
			.append($('<span>').text('Select an image or video'));
	}

	function emptyPosterPreview() {
		return $('<span>').text('Optional poster image');
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
				attachment_id: Number($panel.find('[data-sp-background-media-id]').val()) || 0,
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
		var type = $panel.find('[data-sp-background-media-type]').val() || 'image';
		var fit = $panel.find('[data-sp-background-fit]:checked').val() || 'cover';
		var x = Math.round(clamp($panel.find('[data-sp-background-position="x"]').val(), 0, 100, 50));
		var y = Math.round(clamp($panel.find('[data-sp-background-position="y"]').val(), 0, 100, 50));
		$panel.find('[data-sp-background-poster-panel]').prop('hidden', type !== 'video');
		$panel.find('[data-sp-background-preview]').css({
			'--sp-preview-fit': fit,
			'--sp-preview-x': x + '%',
			'--sp-preview-y': y + '%',
			'--sp-focal-x': x + '%',
			'--sp-focal-y': y + '%'
		});
		$panel.find('[data-sp-background-position-output]').text(x + '% / ' + y + '%');
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
		var $content = $panel.find('[data-sp-background-preview-content]');
		var type = attachment.type === 'video' ? 'video' : 'image';
		$panel.find('[data-sp-background-media-id]').val(attachment.id).trigger('change');
		$panel.find('[data-sp-background-media-type]').val(type);
		$panel.find('[data-sp-background-remove]').removeClass('is-hidden');
		$panel.find('[data-sp-background-select-label]').text('Replace media');
		$panel.find('[data-sp-background-preview]').addClass('is-filled');
		$content.empty();
		if (type === 'image') {
			$('<img>', { src: previewUrl(attachment), alt: '' }).appendTo($content);
		} else {
			$('<span>', { class: 'sp-background-field__file' })
				.append($('<span>', { class: 'sp-background-field__empty-icon', 'aria-hidden': 'true' }).text('▶'))
				.append($('<span>').text(attachment.filename || attachment.title || 'Video selected')).appendTo($content);
		}
		syncPanel($panel);
		announce($field, (type === 'video' ? 'Video selected: ' : 'Image selected: ') + (attachment.filename || attachment.title || 'media'));
	}

	function updatePoster($panel, attachment) {
		var $preview = $panel.find('[data-sp-background-poster-preview]');
		$panel.find('[data-sp-background-poster-id]').val(attachment.id).trigger('change');
		$panel.find('[data-sp-background-poster-remove]').removeClass('is-hidden');
		$panel.find('[data-sp-background-poster-select-label]').text('Replace poster');
		$preview.addClass('is-filled').empty().append($('<img>', { src: previewUrl(attachment), alt: '' }));
		syncState($panel.closest('[data-sp-background-field]'));
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
		var allowVideo = $panel.closest('[data-sp-background-field]').attr('data-allow-video') !== 'false';
		var frame = wp.media({
			title: allowVideo ? 'Select background image or video' : 'Select background image',
			button: { text: 'Use as background' },
			library: { type: allowVideo ? ['image', 'video'] : 'image' },
			multiple: false
		});
		frame.on('select', function () { updateMedia($panel, frame.state().get('selection').first().toJSON()); });
		frame.open();
	});

	$(document).on('click', '[data-sp-background-remove]', function (event) {
		event.preventDefault();
		var $panel = $(this).closest('[data-sp-background-panel]');
		$panel.find('[data-sp-background-media-id], [data-sp-background-poster-id]').val('');
		$panel.find('[data-sp-background-media-type]').val('image');
		$panel.find('[data-sp-background-preview]').removeClass('is-filled');
		$panel.find('[data-sp-background-preview-content]').empty().append(emptyMediaPreview());
		$panel.find('[data-sp-background-select-label]').text('Select media');
		$panel.find('[data-sp-background-remove], [data-sp-background-poster-remove]').addClass('is-hidden');
		$panel.find('[data-sp-background-poster-preview]').removeClass('is-filled').empty().append(emptyPosterPreview());
		$panel.find('[data-sp-background-poster-select-label]').text('Select poster');
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
		$panel.find('[data-sp-background-poster-id]').val('');
		$panel.find('[data-sp-background-poster-preview]').removeClass('is-filled').empty().append(emptyPosterPreview());
		$panel.find('[data-sp-background-poster-select-label]').text('Select poster');
		$(this).addClass('is-hidden');
		syncState($panel.closest('[data-sp-background-field]'));
	});

	$(document).on('change', '[data-sp-background-fit]', function () { syncPanel($(this).closest('[data-sp-background-panel]')); });
	$(document).on('input change', '[data-sp-background-position]', function () {
		var $panel = $(this).closest('[data-sp-background-panel]');
		setPosition($panel, $panel.find('[data-sp-background-position="x"]').val(), $panel.find('[data-sp-background-position="y"]').val());
	});

	$(document).on('pointerdown', '[data-sp-background-focal-surface]', function (event) {
		event.preventDefault();
		this.setPointerCapture(event.originalEvent.pointerId);
		$(this).attr('data-dragging', 'true');
		updateFocalFromEvent($(this), event.originalEvent);
	});
	$(document).on('pointermove', '[data-sp-background-focal-surface][data-dragging="true"]', function (event) { updateFocalFromEvent($(this), event.originalEvent); });
	$(document).on('pointerup pointercancel', '[data-sp-background-focal-surface]', function () { $(this).removeAttr('data-dragging'); });
	$(document).on('keydown', '[data-sp-background-focal-surface]', function (event) {
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
