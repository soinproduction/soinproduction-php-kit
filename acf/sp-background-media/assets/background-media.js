/* SP Background Media — responsive media loader */
(function () {
	'use strict';

	function activeBreakpoint(root) {
		var width = window.innerWidth;
		var mobile = Number(root.getAttribute('data-mobile-breakpoint'));
		var tablet = Number(root.getAttribute('data-tablet-breakpoint'));

		if (!(mobile > 0 && tablet > mobile)) {
			return 'desktop';
		}

		if (width < mobile) {
			return 'mobile';
		}
		if (width < tablet) {
			return 'tablet';
		}
		return 'desktop';
	}

	function reducedMotion(root) {
		return root.getAttribute('data-respect-reduced-motion') !== 'false'
			&& window.matchMedia
			&& window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	}

	function pauseVideo(video) {
		if (!video.paused) {
			video.pause();
		}
	}

	function activateVideo(video, shouldPlay) {
		var source = video.querySelector('source[data-src]');
		video.muted = true;

		if (source && !source.getAttribute('src')) {
			source.setAttribute('src', source.getAttribute('data-src'));
			video.load();
		}

		if (!shouldPlay || document.hidden) {
			pauseVideo(video);
			return;
		}

		var promise = video.play();
		if (promise && typeof promise.catch === 'function') {
			promise.catch(function () {
				// Autoplay can still be blocked by browser or OS policy. The poster remains visible.
			});
		}
	}

	function activateImage(image) {
		if (image.getAttribute('data-sizes') && !image.getAttribute('sizes')) {
			image.setAttribute('sizes', image.getAttribute('data-sizes'));
		}
		if (image.getAttribute('data-srcset') && !image.getAttribute('srcset')) {
			image.setAttribute('srcset', image.getAttribute('data-srcset'));
		}
		if (image.getAttribute('data-src') && image.getAttribute('src') !== image.getAttribute('data-src')) {
			image.setAttribute('src', image.getAttribute('data-src'));
		}
	}

	function sync(root) {
		var breakpoint = activeBreakpoint(root);
		var canPlay = !reducedMotion(root);

		root.setAttribute('data-active-breakpoint', breakpoint);
		root.querySelectorAll('[data-sp-background-variant]').forEach(function (variant) {
			var active = variant.getAttribute('data-sp-background-variant') === breakpoint;
			if (active) {
				variant.querySelectorAll('[data-sp-background-image]').forEach(activateImage);
			}
			variant.querySelectorAll('[data-sp-background-video]').forEach(function (video) {
				if (active) {
					activateVideo(video, canPlay);
				} else {
					pauseVideo(video);
				}
			});
		});
	}

	function init() {
		var roots = Array.prototype.slice.call(document.querySelectorAll('[data-sp-background-media]'));
		if (!roots.length) {
			return;
		}

		var frame = null;
		function syncAll() {
			roots.forEach(sync);
		}

		function scheduleSync() {
			if (frame !== null) {
				window.cancelAnimationFrame(frame);
			}
			frame = window.requestAnimationFrame(function () {
				frame = null;
				syncAll();
			});
		}

		syncAll();
		window.addEventListener('resize', scheduleSync, { passive: true });
		document.addEventListener('visibilitychange', syncAll);

		if (window.matchMedia) {
			var motionQuery = window.matchMedia('(prefers-reduced-motion: reduce)');
			if (typeof motionQuery.addEventListener === 'function') {
				motionQuery.addEventListener('change', syncAll);
			}
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
