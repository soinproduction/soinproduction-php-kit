<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'acf/include_field_types', function (): void {
    if ( ! class_exists( 'acf_field' ) || class_exists( 'acf_field_taxonomy_urls', false ) ) {
        return;
    }

    class acf_field_taxonomy_urls extends acf_field {

        public function initialize(): void {
            $this->name     = 'taxonomy_urls';
            $this->label    = __( 'Taxonomy URLs', 'acf' );
            $this->category = 'relational';
            $this->defaults = [
                'taxonomy'   => 'category',
                'icon_field' => 'icon',
            ];
        }

        /* ── Settings ──────────────────────────────────────── */

        public function render_field_settings( array $field ): void {
            acf_render_field_setting( $field, [
                'label'        => __( 'Taxonomy', 'acf' ),
                'instructions' => __( 'Select the taxonomy to pull terms from.', 'acf' ),
                'type'         => 'select',
                'name'         => 'taxonomy',
                'choices'      => acf_get_taxonomy_labels(),
            ] );

            acf_render_field_setting( $field, [
                'label'        => __( 'Icon ACF Field', 'acf' ),
                'instructions' => __( 'ACF image field name on the term (leave empty to hide icons).', 'acf' ),
                'type'         => 'text',
                'name'         => 'icon_field',
                'placeholder'  => 'icon',
            ] );
        }

        /* ── Render ────────────────────────────────────────── */

        public function render_field( array $field ): void {
            $taxonomy   = $field['taxonomy'] ?? 'category';
            $icon_field = $field['icon_field'] ?? '';
            $value      = is_array( $field['value'] ) ? $field['value'] : [];
            $post_id    = (int) ( $field['post_id'] ?? get_the_ID() );

            $term_ids = [];
            if ( $post_id > 0 ) {
                $assigned = wp_get_object_terms( $post_id, $taxonomy, [ 'fields' => 'ids' ] );
                if ( ! is_wp_error( $assigned ) ) {
                    $term_ids = array_map( 'intval', $assigned );
                }
            }

            $field_name = esc_attr( $field['name'] );
            $field_id   = esc_attr( $field['id'] ?? '' );

            echo '<div class="sp-tax-urls sp-admin-component sp-acf-component" data-sp-admin-component aria-busy="false"'
                . ' data-taxonomy="' . esc_attr( $taxonomy ) . '"'
                . ' data-icon-field="' . esc_attr( $icon_field ) . '"'
                . ' data-field-name="' . $field_name . '"'
                . ' data-field-id="' . $field_id . '"'
                . ' data-has-icons="' . ( $icon_field !== '' ? '1' : '0' ) . '"'
                . '>';

            echo '<div class="sp-tax-urls__grid">';

            if ( ! empty( $term_ids ) ) {
                $terms = get_terms( [
                    'taxonomy'   => $taxonomy,
                    'include'    => $term_ids,
                    'hide_empty' => false,
                    'orderby'    => 'name',
                    'order'      => 'ASC',
                ] );

                if ( ! is_wp_error( $terms ) ) {
                    foreach ( $terms as $term ) {
                        echo self::render_row( $term, $field_name, $field_id, $icon_field, $value );
                    }
                }
            }

            echo '</div>';

            echo '<p class="sp-tax-urls__empty"'
                . ( ! empty( $term_ids ) ? ' style="display:none"' : '' )
                . '>'
                . '<span class="sp-tax-urls__empty-icon">🔗</span>'
                . '<span>' . esc_html__( 'No terms assigned. Select terms in the taxonomy metabox →', 'acf' ) . '</span>'
                . '</p>';

            echo '<div class="sp-tax-urls__status sp-acf-status" role="status" aria-live="polite" aria-atomic="true"></div>';

            echo '</div>';
        }

        /* ── Single row HTML ───────────────────────────────── */

        public static function render_row( WP_Term $term, string $field_name, string $field_id, string $icon_field, array $value ): string {
            $term_id  = (int) $term->term_id;
            $term_url = isset( $value[ $term_id ] ) ? (string) $value[ $term_id ] : '';
            $input_id = esc_attr( $field_id . '-' . $term_id );

            $html = '<div class="sp-tax-urls__row" data-term-id="' . esc_attr( $term_id ) . '">';

            // ── Icon ──────────────────────────────────────────────
            if ( $icon_field !== '' ) {
                $icon_html = '';
                if ( function_exists( 'get_field' ) ) {
                    $icon = get_field( $icon_field, $term );
                    if ( is_array( $icon ) && ! empty( $icon['sizes']['thumbnail'] ) ) {
                        $icon_html = '<img src="' . esc_url( $icon['sizes']['thumbnail'] ) . '" alt="" />';
                    } elseif ( is_array( $icon ) && ! empty( $icon['url'] ) ) {
                        $icon_html = '<img src="' . esc_url( $icon['url'] ) . '" alt="" />';
                    } elseif ( is_string( $icon ) && $icon !== '' ) {
                        $icon_html = '<img src="' . esc_url( $icon ) . '" alt="" />';
                    }
                }

                $html .= '<div class="sp-tax-urls__icon-cell">';
                if ( $icon_html !== '' ) {
                    $html .= '<span class="sp-tax-urls__icon-wrap">' . $icon_html . '</span>';
                } else {
                    $html .= '<span class="sp-tax-urls__icon-placeholder"><span class="dashicons dashicons-format-aside"></span></span>';
                }
                $html .= '</div>';
            }

            // ── Name ──────────────────────────────────────────────
            $html .= '<div class="sp-tax-urls__name-cell">';
            $html .= '<label for="' . $input_id . '" class="sp-tax-urls__name">' . esc_html( $term->name ) . '</label>';
            if ( ! empty( $term->description ) ) {
                $html .= '<span class="sp-tax-urls__desc">' . esc_html( $term->description ) . '</span>';
            }
            $html .= '</div>';

            // ── URL ───────────────────────────────────────────────
            $has_url = $term_url !== '';
            $html .= '<div class="sp-tax-urls__url-cell">';
            $html .= '<div class="sp-tax-urls__input-wrap' . ( $has_url ? ' has-value' : '' ) . '">';
            $html .= '<svg class="sp-tax-urls__link-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>';
            $html .= '<input type="url"'
                . ' id="' . $input_id . '"'
                . ' name="' . esc_attr( $field_name ) . '[' . esc_attr( $term_id ) . ']"'
                . ' value="' . esc_attr( $term_url ) . '"'
                . ' class="sp-tax-urls__input"'
                . ' placeholder="https://"'
                . ' aria-invalid="false"'
                . ' />';
            if ( $has_url ) {
                $open_label = sprintf( __( 'Open URL for %s', 'acf' ), $term->name );
                $html .= '<a href="' . esc_url( $term_url ) . '" target="_blank" rel="noopener" class="sp-tax-urls__open-btn" title="' . esc_attr__( 'Open URL', 'acf' ) . '" aria-label="' . esc_attr( $open_label ) . '">'
                    . '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>'
                    . '</a>';
            }
            $html .= '</div>';
            $html .= '</div>';

            $html .= '</div>';

            return $html;
        }

        /* ── Save ──────────────────────────────────────────── */

        public function update_value( $value, $post_id, array $field ) {
            if ( ! is_array( $value ) ) return [];
            $clean = [];
            foreach ( $value as $term_id => $url ) {
                $term_id = (int) $term_id;
                $url     = trim( (string) $url );
                if ( $term_id > 0 && $url !== '' ) {
                    $clean[ $term_id ] = esc_url_raw( $url );
                }
            }
            return $clean;
        }

        public function format_value( $value, $post_id, array $field ) {
            return is_array( $value ) ? $value : [];
        }

        /* ── Admin assets ──────────────────────────────────── */

        public function input_admin_enqueue_scripts(): void {
            $handle = 'acf-taxonomy-urls';

            if ( ! wp_style_is( $handle, 'registered' ) ) {
                $css = <<<'CSS'
.sp-tax-urls {
    container-type: inline-size;
    color: var(--sp-acf-text);
}

.sp-tax-urls__grid {
    display: flex;
    flex-direction: column;
    overflow: hidden;
    border: 1px solid var(--sp-acf-border);
    border-radius: var(--sp-acf-radius);
    background: var(--sp-acf-surface);
    box-shadow: var(--sp-acf-shadow);
    transition: border-color var(--sp-acf-transition), box-shadow var(--sp-acf-transition), opacity var(--sp-acf-transition);
}

.sp-tax-urls__grid:empty {
    display: none;
}

.sp-tax-urls__grid:focus-within {
    border-color: var(--sp-acf-accent);
    box-shadow: var(--sp-acf-focus);
}

.sp-tax-urls.is-loading .sp-tax-urls__grid {
    opacity: .72;
}

.sp-tax-urls__row {
    position: relative;
    display: grid;
    grid-template-columns: 40px minmax(140px, 1fr) minmax(220px, 2fr);
    align-items: center;
    gap: 12px;
    min-height: 56px;
    padding: 9px 12px;
    background: var(--sp-acf-surface);
    transition: background var(--sp-acf-transition), opacity var(--sp-acf-transition), transform var(--sp-acf-transition);
}

.sp-tax-urls__row + .sp-tax-urls__row {
    border-top: 1px solid var(--sp-acf-border);
}

.sp-tax-urls__row:hover,
.sp-tax-urls__row:focus-within {
    z-index: 1;
    background: var(--sp-acf-surface-soft);
}

.sp-tax-urls[data-has-icons="0"] .sp-tax-urls__row {
    grid-template-columns: minmax(140px, 1fr) minmax(220px, 2fr);
}

.sp-tax-urls__row.is-removing {
    opacity: 0;
    transform: translateX(-8px);
    pointer-events: none;
}

.sp-tax-urls__row.is-adding {
    animation: spTaxIn var(--sp-acf-transition) both;
}

@keyframes spTaxIn {
    from { opacity: 0; transform: translateY(-6px); }
    to { opacity: 1; transform: translateY(0); }
}

.sp-tax-urls__icon-cell,
.sp-tax-urls__icon-wrap,
.sp-tax-urls__icon-placeholder {
    display: flex;
    align-items: center;
    justify-content: center;
}

.sp-tax-urls__icon-wrap,
.sp-tax-urls__icon-placeholder {
    box-sizing: border-box;
    width: var(--sp-acf-control-height);
    height: var(--sp-acf-control-height);
    flex-shrink: 0;
    overflow: hidden;
    border: 1px solid var(--sp-acf-border);
    border-radius: var(--sp-acf-radius);
    background: var(--sp-acf-segment-bg);
}

.sp-tax-urls__icon-wrap img {
    display: block;
    width: 26px;
    height: 26px;
    object-fit: contain;
}

.sp-tax-urls__icon-placeholder {
    color: var(--sp-acf-text-subtle);
}

.sp-tax-urls__icon-placeholder .dashicons {
    width: 16px;
    height: 16px;
    font-size: 16px;
    line-height: 1;
}

.sp-tax-urls__name-cell {
    display: flex;
    min-width: 0;
    flex-direction: column;
    gap: 2px;
}

.sp-tax-urls__name {
    margin: 0;
    color: var(--sp-acf-text);
    font-size: 13px;
    font-weight: 700;
    line-height: 1.3;
    cursor: pointer;
    transition: color var(--sp-acf-transition);
}

.sp-tax-urls__row:hover .sp-tax-urls__name,
.sp-tax-urls__row:focus-within .sp-tax-urls__name {
    color: var(--sp-acf-accent-hover);
}

.sp-tax-urls__desc {
    overflow: hidden;
    color: var(--sp-acf-text-muted);
    font-size: 11px;
    line-height: 1.35;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.sp-tax-urls__url-cell,
.sp-tax-urls__input-wrap {
    display: flex;
    min-width: 0;
    align-items: center;
}

.sp-tax-urls__input-wrap {
    position: relative;
    width: 100%;
}

.sp-tax-urls__link-icon {
    position: absolute;
    z-index: 1;
    top: 50%;
    left: 11px;
    width: 14px;
    height: 14px;
    flex-shrink: 0;
    color: var(--sp-acf-text-subtle);
    pointer-events: none;
    transform: translateY(-50%);
    transition: color var(--sp-acf-transition);
}

.sp-tax-urls__input-wrap:focus-within .sp-tax-urls__link-icon,
.sp-tax-urls__input-wrap.has-value:not(.has-error) .sp-tax-urls__link-icon {
    color: var(--sp-acf-accent);
}

.sp-tax-urls__input-wrap.has-error .sp-tax-urls__link-icon {
    color: var(--sp-acf-error);
}

.sp-tax-urls__input {
    box-sizing: border-box;
    width: 100%;
    min-width: 0;
    min-height: var(--sp-acf-control-height) !important;
    margin: 0 !important;
    padding: 6px 42px 6px 34px !important;
    border: 1px solid var(--sp-acf-border-strong) !important;
    border-radius: var(--sp-acf-radius) !important;
    background: var(--sp-acf-input-bg) !important;
    color: var(--sp-acf-text) !important;
    font-size: 13px !important;
    line-height: 1.35 !important;
    box-shadow: none !important;
    transition: background var(--sp-acf-transition), border-color var(--sp-acf-transition), box-shadow var(--sp-acf-transition) !important;
}

.sp-tax-urls__input:hover:not(:disabled) {
    border-color: var(--sp-acf-accent) !important;
}

.sp-tax-urls__input:focus {
    border-color: var(--sp-acf-accent) !important;
    background: var(--sp-acf-surface) !important;
    box-shadow: var(--sp-acf-focus) !important;
    outline: 0 !important;
}

.sp-tax-urls__input-wrap.has-value:not(.has-error) .sp-tax-urls__input {
    border-color: var(--sp-acf-accent) !important;
    background: var(--sp-acf-accent-soft) !important;
}

.sp-tax-urls__input-wrap.has-error .sp-tax-urls__input,
.sp-tax-urls__input[aria-invalid="true"] {
    border-color: var(--sp-acf-error) !important;
    background: var(--sp-acf-surface) !important;
    box-shadow: 0 0 0 1px var(--sp-acf-error) !important;
}

.sp-tax-urls__input:disabled {
    border-color: var(--sp-acf-border) !important;
    background: var(--sp-acf-surface-soft) !important;
    color: var(--sp-acf-text-subtle) !important;
    cursor: wait;
    opacity: .8;
}

.sp-tax-urls__open-btn {
    position: absolute;
    z-index: 2;
    top: 50%;
    right: 0;
    display: flex;
    width: var(--sp-acf-control-height);
    height: var(--sp-acf-control-height);
    align-items: center;
    justify-content: center;
    border-left: 1px solid transparent;
    border-radius: var(--sp-acf-radius);
    color: var(--sp-acf-accent-hover);
    opacity: 0;
    text-decoration: none;
    transform: translateY(-50%);
    transition: background var(--sp-acf-transition), border-color var(--sp-acf-transition), box-shadow var(--sp-acf-transition), color var(--sp-acf-transition), opacity var(--sp-acf-transition);
}

.sp-tax-urls__open-btn svg {
    width: 14px;
    height: 14px;
}

.sp-tax-urls__input-wrap.has-value:hover .sp-tax-urls__open-btn,
.sp-tax-urls__input-wrap.has-value:focus-within .sp-tax-urls__open-btn,
.sp-tax-urls__open-btn:focus-visible {
    opacity: 1;
}

.sp-tax-urls__open-btn:hover {
    border-left-color: var(--sp-acf-border-strong);
    background: var(--sp-acf-accent);
    color: var(--color-on-accent);
}

.sp-tax-urls__open-btn:active {
    border-left-color: var(--sp-acf-accent-hover);
    background: var(--sp-acf-accent-hover);
    color: var(--color-on-accent);
}

.sp-tax-urls__open-btn:focus-visible {
    border-left-color: var(--sp-acf-accent);
    box-shadow: var(--sp-acf-focus);
    outline: 0;
}

.sp-tax-urls__open-btn[aria-disabled="true"] {
    color: var(--sp-acf-text-subtle);
    cursor: wait;
    pointer-events: none;
}

.sp-tax-urls__empty {
    display: flex;
    min-height: 62px;
    align-items: center;
    gap: 10px;
    box-sizing: border-box;
    margin: 0;
    padding: 14px 16px;
    border: 1px dashed var(--sp-acf-border-strong);
    border-radius: var(--sp-acf-radius);
    background: var(--sp-acf-surface-soft);
    color: var(--sp-acf-text-muted);
    font-size: 13px;
    line-height: 1.4;
}

.sp-tax-urls__empty-icon {
    flex-shrink: 0;
    font-size: 18px;
}

.sp-tax-urls__status {
    display: flex;
    min-height: 28px;
    align-items: center;
    gap: 7px;
    box-sizing: border-box;
    padding: 6px 2px 0;
    color: var(--sp-acf-text-muted);
    font-size: 12px;
    line-height: 1.3;
}

.sp-tax-urls__status:empty {
    display: none;
}

.sp-tax-urls__status.is-loading {
    color: var(--sp-acf-accent-hover);
}

.sp-tax-urls__status.is-loading::before {
    width: 12px;
    height: 12px;
    flex: 0 0 12px;
    box-sizing: border-box;
    border: 2px solid var(--sp-acf-border-strong);
    border-top-color: var(--sp-acf-accent);
    border-radius: 50%;
    content: "";
    animation: spTaxSpin .7s linear infinite;
}

.sp-tax-urls__status.is-success {
    color: var(--sp-acf-success);
}

.sp-tax-urls__status.is-error {
    color: var(--sp-acf-error);
}

@keyframes spTaxSpin {
    to { transform: rotate(360deg); }
}

@container (max-width: 720px) {
    .sp-tax-urls__row {
        grid-template-columns: 40px minmax(0, 1fr);
    }

    .sp-tax-urls[data-has-icons="0"] .sp-tax-urls__row {
        grid-template-columns: 1fr;
    }

    .sp-tax-urls__url-cell {
        grid-column: 1 / -1;
    }
}

@container (max-width: 430px) {
    .sp-tax-urls__row {
        gap: 8px;
        padding: 8px;
    }

    .sp-tax-urls__desc {
        white-space: normal;
    }
}

@media (hover: none), (pointer: coarse) {
    .sp-tax-urls__open-btn {
        width: 40px;
        height: 40px;
        opacity: 1;
    }

    .sp-tax-urls__input {
        min-height: 40px !important;
        padding-right: 48px !important;
    }
}

@media (prefers-reduced-motion: reduce) {
    .sp-tax-urls *,
    .sp-tax-urls *::before,
    .sp-tax-urls *::after {
        scroll-behavior: auto !important;
        animation-duration: .01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: .01ms !important;
    }
}
CSS;

                wp_register_style( $handle, false );
                wp_add_inline_style( $handle, $css );
            }
            wp_enqueue_style( $handle );

            if ( ! wp_script_is( $handle, 'registered' ) ) {
                $js = <<<'JS'
(function ($) {
    'use strict';

    var selector = '.sp-tax-urls';
    var config = window.spTaxUrlsConfig || {};
    var widgetCounter = 0;
    var openIcon = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>';

    function debounce(fn, ms) {
        var timer;
        return function () {
            var context = this;
            var args = arguments;
            window.clearTimeout(timer);
            timer = window.setTimeout(function () {
                fn.apply(context, args);
            }, ms);
        };
    }

    function getWidgetState($widget) {
        var state = $widget.data('spTaxUrlsState');
        if (!state) {
            state = {
                request: null,
                sequence: 0,
                renderTimer: null,
                successTimer: null,
                urlCache: {}
            };
            $widget.data('spTaxUrlsState', state);
        }
        return state;
    }

    function setStatus($widget, type, message, source) {
        var $status = $widget.find('.sp-tax-urls__status').first();
        $status
            .removeClass('is-loading is-success is-error')
            .removeAttr('data-status-source')
            .text(message || '');

        if (type) {
            $status.addClass('is-' + type);
        }
        if (source) {
            $status.attr('data-status-source', source);
        }
    }

    function setBusy($widget, busy, message) {
        $widget
            .toggleClass('is-loading', busy)
            .attr('aria-busy', busy ? 'true' : 'false');
        $widget.find('.sp-tax-urls__input').prop('disabled', busy);
        $widget.find('.sp-tax-urls__open-btn')
            .attr('aria-disabled', busy ? 'true' : 'false')
            .attr('tabindex', busy ? '-1' : null);

        if (busy) {
            setStatus($widget, 'loading', message || config.loading || 'Updating term URLs…', 'sync');
        }
    }

    function inputIsValid($input, value) {
        var input = $input.get(0);
        if (value === '') {
            return true;
        }
        if (/^(?:javascript|data):/i.test(value)) {
            return false;
        }
        return !input || typeof input.checkValidity !== 'function' || input.checkValidity();
    }

    function updateInputState($input, announceValidation) {
        var $wrap = $input.closest('.sp-tax-urls__input-wrap');
        var $widget = $input.closest(selector);
        var value = $.trim(String($input.val() || ''));
        var valid = inputIsValid($input, value);
        var hasValue = value !== '';
        var $button = $wrap.find('.sp-tax-urls__open-btn');

        $wrap
            .toggleClass('has-value', hasValue)
            .toggleClass('has-error', hasValue && !valid);
        $input.attr('aria-invalid', hasValue && !valid ? 'true' : 'false');

        if (hasValue && valid) {
            if (!$button.length) {
                $button = $('<a class="sp-tax-urls__open-btn" target="_blank" rel="noopener"></a>');
                $button.html(openIcon);
                $wrap.append($button);
            }

            var termName = $.trim($input.closest('.sp-tax-urls__row').find('.sp-tax-urls__name').first().text());
            var openLabel = String(config.openUrlFor || 'Open URL for %s').replace('%s', termName);
            $button.attr({
                href: value,
                title: config.openUrl || 'Open URL',
                'aria-label': openLabel,
                'aria-disabled': $widget.attr('aria-busy') === 'true' ? 'true' : 'false'
            });
        } else {
            $button.remove();
        }

        if (announceValidation && hasValue && !valid) {
            setStatus($widget, 'error', config.invalidUrl || 'Enter a valid URL.', 'validation');
        } else if ($widget.find('.sp-tax-urls__status').attr('data-status-source') === 'validation' && valid) {
            setStatus($widget, '', '', '');
        }
    }

    function findChecklist(taxonomy) {
        var $checklist = $('#' + taxonomy + 'checklist, #' + taxonomy + '-checklist, [id$="' + taxonomy + 'checklist"]');
        if (!$checklist.length) {
            $checklist = $('#taxonomy-' + taxonomy + ' .categorychecklist');
        }
        return $checklist.first();
    }

    function initWidget() {
        var $widget = $(this);
        if ($widget.data('spTaxUrlsReady')) {
            return;
        }

        var taxonomy = String($widget.data('taxonomy') || '');
        var $checklist = taxonomy ? findChecklist(taxonomy) : $();
        if (!taxonomy || !$checklist.length) {
            return;
        }

        var $grid = $widget.find('.sp-tax-urls__grid').first();
        var $empty = $widget.find('.sp-tax-urls__empty').first();
        var state = getWidgetState($widget);
        var namespace = '.spTaxUrls' + (++widgetCounter);

        function cacheUrls() {
            $grid.find('.sp-tax-urls__row').each(function () {
                var $row = $(this);
                var termId = parseInt($row.attr('data-term-id'), 10);
                var value = String($row.find('.sp-tax-urls__input').val() || '');
                if (!termId) {
                    return;
                }
                if (value !== '') {
                    state.urlCache[termId] = value;
                } else {
                    delete state.urlCache[termId];
                }
            });
        }

        function getCheckedIds() {
            var ids = [];
            var seen = {};
            $checklist.find('input[type="checkbox"]:checked').each(function () {
                var termId = parseInt($(this).val(), 10);
                if (termId > 0 && !seen[termId]) {
                    seen[termId] = true;
                    ids.push(termId);
                }
            });
            return ids;
        }

        function finishRows(sequence, newIds, htmlMap) {
            if (sequence !== state.sequence) {
                return;
            }

            var allowed = {};
            newIds.forEach(function (termId) {
                allowed[termId] = true;
            });

            $grid.find('.sp-tax-urls__row').each(function () {
                var termId = parseInt($(this).attr('data-term-id'), 10);
                if (!allowed[termId]) {
                    $(this).remove();
                }
            });

            newIds.forEach(function (termId) {
                var $row = $grid.find('.sp-tax-urls__row').filter(function () {
                    return parseInt($(this).attr('data-term-id'), 10) === termId;
                }).first();

                if (!$row.length && htmlMap[termId]) {
                    $row = $(htmlMap[termId]).addClass('is-adding');
                    if (Object.prototype.hasOwnProperty.call(state.urlCache, termId)) {
                        $row.find('.sp-tax-urls__input').val(state.urlCache[termId]);
                    }
                }

                if ($row.length) {
                    $row.removeClass('is-removing');
                    $row.find('.sp-tax-urls__input').each(function () {
                        updateInputState($(this), false);
                    });
                    $grid.append($row);
                }
            });

            var hasRows = $grid.find('.sp-tax-urls__row').length > 0;
            $empty.toggle(!hasRows);
            setBusy($widget, false);
            setStatus($widget, 'success', config.success || 'Term URLs updated.', 'sync');

            window.clearTimeout(state.successTimer);
            state.successTimer = window.setTimeout(function () {
                if (sequence === state.sequence && $widget.find('.sp-tax-urls__status').attr('data-status-source') === 'sync') {
                    setStatus($widget, '', '', '');
                }
            }, 2800);
        }

        function syncRowsNow() {
            cacheUrls();

            state.sequence += 1;
            var sequence = state.sequence;
            var checked = getCheckedIds();

            window.clearTimeout(state.renderTimer);
            window.clearTimeout(state.successTimer);
            $grid.find('.sp-tax-urls__row').removeClass('is-removing');

            if (state.request && typeof state.request.abort === 'function') {
                state.request.abort();
            }
            state.request = null;

            if (!checked.length) {
                $grid.empty();
                $empty.show();
                setBusy($widget, false);
                setStatus($widget, '', '', '');
                return;
            }

            $empty.hide();
            setBusy($widget, true, config.loading || 'Updating term URLs…');

            state.request = $.ajax({
                url: window.ajaxurl || '',
                method: 'POST',
                dataType: 'json',
                data: {
                    action: 'sp_tax_urls_get_rows',
                    taxonomy: taxonomy,
                    icon_field: $widget.data('icon-field') || '',
                    field_name: $widget.data('field-name') || '',
                    field_id: $widget.data('field-id') || '',
                    term_ids: checked,
                    _wpnonce: config.nonce || window.spTaxUrlsNonce || ''
                }
            }).done(function (response) {
                if (sequence !== state.sequence) {
                    return;
                }
                if (!response || !response.success || !response.data) {
                    setBusy($widget, false);
                    setStatus($widget, 'error', config.error || 'Could not update term URLs. Please try again.', 'sync');
                    return;
                }

                var newIds = (response.data.term_ids || []).map(function (termId) {
                    return parseInt(termId, 10);
                }).filter(function (termId) {
                    return termId > 0;
                });
                var htmlMap = response.data.rows || {};
                var hasRemovals = false;
                var allowed = {};

                newIds.forEach(function (termId) {
                    allowed[termId] = true;
                });
                $grid.find('.sp-tax-urls__row').each(function () {
                    var termId = parseInt($(this).attr('data-term-id'), 10);
                    if (!allowed[termId]) {
                        hasRemovals = true;
                        $(this).addClass('is-removing');
                    }
                });

                if (hasRemovals) {
                    state.renderTimer = window.setTimeout(function () {
                        finishRows(sequence, newIds, htmlMap);
                    }, 170);
                } else {
                    finishRows(sequence, newIds, htmlMap);
                }
            }).fail(function (xhr, statusText) {
                if (sequence !== state.sequence || statusText === 'abort') {
                    return;
                }
                setBusy($widget, false);
                setStatus($widget, 'error', config.error || 'Could not update term URLs. Please try again.', 'sync');
            }).always(function () {
                if (sequence === state.sequence) {
                    state.request = null;
                }
            });
        }

        var syncRows = debounce(syncRowsNow, 150);
        $widget.data('spTaxUrlsReady', true);
        $widget.find('.sp-tax-urls__input').each(function () {
            updateInputState($(this), false);
        });
        $checklist.on('change' + namespace, 'input[type="checkbox"]', syncRows);
    }

    function init(scope) {
        var $scope = $(scope || document);
        $scope.find(selector).add($scope.filter(selector)).each(initWidget);
    }

    $(document).on('input change', '.sp-tax-urls__input', function () {
        updateInputState($(this), true);
    });

    $(document).on('click', '.sp-tax-urls__open-btn[aria-disabled="true"]', function (event) {
        event.preventDefault();
    });

    $(function () {
        init(document);
    });

    if (window.acf && typeof window.acf.addAction === 'function') {
        window.acf.addAction('append', function ($element) {
            init($element && $element.length ? $element : document);
        });
        window.acf.addAction('new_field/type=taxonomy_urls', function (field) {
            init(field && field.$el ? field.$el : document);
        });
    }
})(jQuery);
JS;

                wp_register_script( $handle, false, [ 'jquery' ], null, true );
                wp_add_inline_script( $handle, $js );
            }
            wp_enqueue_script( $handle );

            $script_config = [
                'nonce'      => wp_create_nonce( 'sp_tax_urls' ),
                'loading'    => __( 'Updating term URLs…', 'acf' ),
                'success'    => __( 'Term URLs updated.', 'acf' ),
                'error'      => __( 'Could not update term URLs. Please try again.', 'acf' ),
                'invalidUrl' => __( 'Enter a valid URL.', 'acf' ),
                'openUrl'    => __( 'Open URL', 'acf' ),
                'openUrlFor' => __( 'Open URL for %s', 'acf' ),
            ];
            wp_add_inline_script(
                $handle,
                'window.spTaxUrlsConfig = ' . wp_json_encode( $script_config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . '; window.spTaxUrlsNonce = window.spTaxUrlsConfig.nonce;',
                'before'
            );
        }
    }

    acf_register_field_type( 'acf_field_taxonomy_urls' );
} );


/* ── AJAX ──────────────────────────────────────────────── */

add_action( 'wp_ajax_sp_tax_urls_get_rows', function (): void {
    if ( ! check_ajax_referer( 'sp_tax_urls', '_wpnonce', false ) ) {
        wp_send_json_error( 'Invalid nonce' );
    }
	if ( ! is_user_logged_in() ) {
		wp_send_json_error( 'Permission denied', 403 );
	}

    $taxonomy   = sanitize_key( $_POST['taxonomy'] ?? '' );
    $icon_field = sanitize_key( $_POST['icon_field'] ?? '' );
    $field_name = sanitize_text_field( $_POST['field_name'] ?? '' );
    $field_id   = sanitize_text_field( $_POST['field_id'] ?? '' );
    $term_ids   = isset( $_POST['term_ids'] ) && is_array( $_POST['term_ids'] )
        ? array_map( 'absint', $_POST['term_ids'] )
        : [];

    if ( $taxonomy === '' || empty( $term_ids ) ) {
        wp_send_json_error( 'Missing data' );
    }

	$taxonomy_object = get_taxonomy( $taxonomy );
	if (
		! $taxonomy_object
		|| empty( $taxonomy_object->cap->assign_terms )
		|| ! current_user_can( (string) $taxonomy_object->cap->assign_terms )
	) {
		wp_send_json_error( 'Permission denied', 403 );
	}

    $terms = get_terms( [
        'taxonomy'   => $taxonomy,
        'include'    => $term_ids,
        'hide_empty' => false,
        'orderby'    => 'name',
        'order'      => 'ASC',
    ] );

    if ( is_wp_error( $terms ) ) {
        wp_send_json_error( 'Invalid taxonomy' );
    }

    $rows    = [];
    $ordered = [];

    foreach ( $terms as $term ) {
        $tid          = (int) $term->term_id;
        $rows[ $tid ] = acf_field_taxonomy_urls::render_row( $term, $field_name, $field_id, $icon_field, [] );
        $ordered[]    = $tid;
    }

    wp_send_json_success( [
        'term_ids' => $ordered,
        'rows'     => $rows,
    ] );
} );
