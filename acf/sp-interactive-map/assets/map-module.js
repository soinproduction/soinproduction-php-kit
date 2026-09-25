(() => {
'use strict';
const onDomReady = callback => {
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', callback, {once: true});
    else callback();
};

// Prefer above, then below, then a side; clamp the best fit inside the viewport.
function placeTooltip(marker, width, height, bounds) {
    const gap = 12;
    const candidates = [
        {side: 'top', x: marker.left + marker.width / 2 - width / 2, y: marker.top - height - gap},
        {side: 'bottom', x: marker.left + marker.width / 2 - width / 2, y: marker.bottom + gap},
        {side: 'right', x: marker.right + gap, y: marker.top + marker.height / 2 - height / 2},
        {side: 'left', x: marker.left - width - gap, y: marker.top + marker.height / 2 - height / 2},
    ];
    const overflow = p => Math.max(bounds.left - p.x, 0) + Math.max(p.x + width - bounds.right, 0)
        + Math.max(bounds.top - p.y, 0) + Math.max(p.y + height - bounds.bottom, 0);
    const best = candidates.reduce((best, next) => overflow(next) < overflow(best) ? next : best);
    return {
        side: best.side,
        x: Math.max(bounds.left, Math.min(best.x, bounds.right - width)),
        y: Math.max(bounds.top, Math.min(best.y, bounds.bottom - height)),
    };
}

onDomReady(() => {
    document.querySelectorAll('[data-map-zoom]').forEach(map => {
        const viewport = map.querySelector('[data-map-zoom-viewport]');
        const layer = map.querySelector('[data-map-zoom-layer]');
        const controls = map.querySelector('[data-map-zoom-controls]');
        const plus = controls.querySelector('[data-map-zoom-in]');
        const minus = controls.querySelector('[data-map-zoom-out]');
        let scale = 1, targetScale = 1, x = 0, y = 0, drag = null, frame = 0;
        const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
        const clamp = (value, limit) => Math.max(-limit, Math.min(limit, value));
        const render = () => {
            x = clamp(x, Math.max(0, (layer.clientWidth * scale - viewport.clientWidth) / 2));
            y = clamp(y, Math.max(0, (layer.clientHeight * scale - viewport.clientHeight) / 2));
            layer.style.transform = `translate(${x}px, ${y}px) scale(${scale})`;
            const fade = Math.min(64, Math.min(viewport.clientWidth, viewport.clientHeight) * 0.08);
            viewport.style.setProperty('--map-edge-fade', `${fade * Math.min(1, (scale - 1) / 0.5)}px`);
            plus.disabled = targetScale >= 3;
            minus.disabled = targetScale <= 1;
            viewport.dataset.zoomed = String(scale > 1);
        };
        const zoom = delta => {
            cancelAnimationFrame(frame);
            const fromScale = scale, fromX = x, fromY = y;
            targetScale = Math.max(1, Math.min(3, targetScale + delta));
            const started = performance.now();
            const tick = now => {
                const progress = reducedMotion.matches ? 1 : Math.min(1, (now - started) / 350);
                const eased = 1 - Math.pow(1 - progress, 3);
                scale = fromScale + (targetScale - fromScale) * eased;
                x = fromX * scale / fromScale;
                y = fromY * scale / fromScale;
                render();
                frame = progress < 1 ? requestAnimationFrame(tick) : 0;
            };
            tick(started);
        };
        // Pinch updates directly, without the button animation lagging behind the fingers.
        const zoomAt = (nextScale, clientX, clientY) => {
            cancelAnimationFrame(frame);
            const rect = viewport.getBoundingClientRect();
            const anchorX = clientX - rect.left - rect.width / 2;
            const anchorY = clientY - rect.top - rect.height / 2;
            nextScale = Math.max(1, Math.min(3, nextScale));
            const ratio = nextScale / scale;
            x = anchorX - (anchorX - x) * ratio;
            y = anchorY - (anchorY - y) * ratio;
            scale = targetScale = nextScale;
            render();
        };
        let gestureScale = null;
        viewport.addEventListener('wheel', event => {
            // Trackpad pinch is emitted as Ctrl+wheel in Chromium and Firefox.
            if (!event.ctrlKey) return;
            event.preventDefault();
            if (gestureScale !== null) return;
            const unit = event.deltaMode === 1 ? 16 : event.deltaMode === 2 ? viewport.clientHeight : 1;
            const delta = Math.max(-100, Math.min(100, event.deltaY * unit));
            zoomAt(scale * Math.exp(-delta * 0.01), event.clientX, event.clientY);
        }, {passive: false});
        // Safari uses gesture events for trackpad pinch.
        viewport.addEventListener('gesturestart', event => {
            event.preventDefault();
            cancelAnimationFrame(frame);
            gestureScale = scale;
        }, {passive: false});
        viewport.addEventListener('gesturechange', event => {
            if (gestureScale === null) return;
            event.preventDefault();
            zoomAt(gestureScale * event.scale, event.clientX, event.clientY);
        }, {passive: false});
        viewport.addEventListener('gestureend', event => {
            if (gestureScale === null) return;
            event.preventDefault();
            gestureScale = null;
        }, {passive: false});
        plus.addEventListener('click', () => zoom(0.5));
        minus.addEventListener('click', () => zoom(-0.5));
        viewport.addEventListener('pointerdown', event => {
            if (scale === 1 || event.button !== 0 || event.target.closest('button, a, input, textarea, select')) return;
            cancelAnimationFrame(frame);
            targetScale = scale;
            drag = {id: event.pointerId, startX: event.clientX, startY: event.clientY, x, y};
            viewport.setPointerCapture(event.pointerId);
            viewport.dataset.dragging = layer.dataset.dragging = 'true';
        });
        viewport.addEventListener('pointermove', event => {
            if (!drag || event.pointerId !== drag.id) return;
            x = drag.x + event.clientX - drag.startX;
            y = drag.y + event.clientY - drag.startY;
            render();
        });
        const endDrag = () => {
            drag = null;
            viewport.dataset.dragging = layer.dataset.dragging = 'false';
        };
        viewport.addEventListener('pointerup', endDrag);
        viewport.addEventListener('pointercancel', endDrag);
        viewport.addEventListener('lostpointercapture', endDrag);
        viewport.addEventListener('dragstart', event => event.preventDefault());
        new ResizeObserver(render).observe(viewport);
        controls.hidden = false;
        render();
    });

    let closeActive = null, nextId = 0;
    document.querySelectorAll('[data-map-point]').forEach(point => {
        const marker = point.querySelector('[data-map-marker]');
        const tooltip = point.querySelector('[data-map-tooltip]');
        if (!marker || !tooltip) return;
        const viewport = point.closest('[data-map-zoom-viewport]');
        const markers = viewport.querySelectorAll('[data-map-marker]');
        let open = false, frame = 0, closeTimer = 0, showTimer = 0;
        let tooltipId;
        do { tooltipId = `map-tooltip-${++nextId}`; } while (document.getElementById(tooltipId));
        tooltip.id = tooltipId;
        marker.setAttribute('aria-controls', tooltip.id);
        marker.setAttribute('aria-expanded', 'false');
        // A body-level overlay avoids clipping and transformed ancestors during map zoom.
        document.body.append(tooltip);
        tooltip.inert = true;

        const close = () => {
            open = false;
            clearTimeout(showTimer);
            clearTimeout(closeTimer);
            tooltip.inert = true;
            cancelAnimationFrame(frame);
            tooltip.dataset.open = 'false';
            marker.setAttribute('aria-expanded', 'false');
            if (closeActive === close) {
                markers.forEach(item => delete item.dataset.muted);
                closeActive = null;
            }
        };
        const position = () => {
            if (!open) return;
            if (!point.isConnected) { close(); tooltip.remove(); return; }
            const visual = window.visualViewport;
            const left = visual?.offsetLeft || 0, top = visual?.offsetTop || 0;
            const width = visual?.width || document.documentElement.clientWidth;
            const height = visual?.height || window.innerHeight;
            const bounds = {left: left + 12, top: top + 12, right: left + width - 12, bottom: top + height - 12};
            const rect = marker.getBoundingClientRect();
            const clip = viewport?.getBoundingClientRect();
            if (rect.bottom < top || rect.top > top + height || rect.right < left || rect.left > left + width
                || (clip && (rect.bottom < clip.top || rect.top > clip.bottom || rect.right < clip.left || rect.left > clip.right))) {
                close(); return;
            }
            tooltip.style.maxWidth = `${Math.max(1, width - 24)}px`;
            tooltip.style.maxHeight = `${Math.max(1, height - 24)}px`;
            const size = tooltip.getBoundingClientRect();
            const result = placeTooltip(rect, size.width, size.height, bounds);
            tooltip.style.left = `${result.x}px`;
            tooltip.style.top = `${result.y}px`;
            tooltip.dataset.side = result.side;
            tooltip.style.setProperty('--tooltip-enter-x', `${result.side === 'left' ? 8 : result.side === 'right' ? -8 : 0}px`);
            tooltip.style.setProperty('--tooltip-enter-y', `${result.side === 'top' ? 8 : result.side === 'bottom' ? -8 : 0}px`);
            // Follow zoom animation, scrolling, image loading and viewport changes.
            frame = requestAnimationFrame(position);
        };
        const show = () => {
            clearTimeout(showTimer);
            clearTimeout(closeTimer);
            if (open) return;
            closeActive?.();
            open = true;
            closeActive = close;
            markers.forEach(item => { item.dataset.muted = String(item !== marker); });
            position();
            if (!open) return;
            // Commit the hidden, positioned state before starting the transition.
            void tooltip.offsetWidth;
            tooltip.inert = false;
            tooltip.dataset.open = 'true';
            marker.setAttribute('aria-expanded', 'true');
        };
        const scheduleClose = () => {
            clearTimeout(showTimer);
            clearTimeout(closeTimer);
            closeTimer = setTimeout(() => {
                if (!point.matches(':hover') && !tooltip.matches(':hover')
                    && !point.contains(document.activeElement) && !tooltip.contains(document.activeElement)) close();
            }, 180);
        };
        [point, tooltip].forEach(element => {
            element.addEventListener('pointerenter', event => {
                if (event.pointerType === 'touch') return;
                clearTimeout(closeTimer);
                clearTimeout(showTimer);
                if (!open) showTimer = setTimeout(show, 90);
            });
            element.addEventListener('pointerleave', scheduleClose);
            element.addEventListener('focusin', show);
            element.addEventListener('focusout', scheduleClose);
            element.addEventListener('keydown', event => {
                if (event.key === 'Escape') {
                    marker.focus();
                    close();
                    event.preventDefault();
                }
            });
        });
        marker.addEventListener('click', show);
        marker.addEventListener('keydown', event => {
            if (event.key === 'Tab' && !event.shiftKey && open) {
                const link = tooltip.querySelector('a[href], button:not([disabled]), input:not([disabled]), [tabindex="0"]');
                if (link) { event.preventDefault(); link.focus(); }
            }
        });
        tooltip.addEventListener('keydown', event => {
            if (event.key === 'Tab' && event.shiftKey && document.activeElement === tooltip.querySelector('a[href], button:not([disabled]), input:not([disabled]), [tabindex="0"]')) {
                event.preventDefault(); marker.focus();
            }
        });
        document.addEventListener('pointerdown', event => {
            if (open && !point.contains(event.target) && !tooltip.contains(event.target)) close();
        });
    });
});

})();
