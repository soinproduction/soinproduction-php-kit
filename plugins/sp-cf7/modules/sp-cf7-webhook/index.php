<?php
	/**
	 * Plugin Name: CF7 Per-Form Webhook
	 * Description: Adds a "Webhook" tab to each Contact Form 7 form so submissions can be POSTed to a webhook (Make/Zapier/Salesforce endpoint). Each form is configured independently.
	 * Version:     1.0.0
	 * Author:      Custom
	 * License:     GPL-2.0+
	 * Text Domain: cf7-webhook
	 *
	 * Requires Contact Form 7 to be active.
	 */

	if ( ! defined( 'ABSPATH' ) ) {
		exit; // No direct access.
	}

	/**
	 * Meta key under which we store per-form webhook settings (keyed by the CF7 post ID).
	 */
	const CF7_WEBHOOK_META_KEY = '_cf7_webhook_settings';

	/* -------------------------------------------------------------------------
	 * 1. ADMIN UI — add a "Webhook" panel to each form's editor
	 * ---------------------------------------------------------------------- */

	add_filter( 'wpcf7_editor_panels', 'cf7_webhook_add_panel' );
	function cf7_webhook_add_panel( $panels ) {
		$panels['cf7-webhook-panel'] = array(
			'title'    => __( 'Webhook', 'cf7-webhook' ),
			'callback' => 'cf7_webhook_render_panel',
		);
		return $panels;
	}

	add_action( 'admin_enqueue_scripts', 'cf7_webhook_enqueue_admin_assets' );
	function cf7_webhook_enqueue_admin_assets() {
		$page = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : '';

		if ( 0 !== strpos( $page, 'wpcf7' ) ) {
			return;
		}

		wp_register_style( 'cf7-webhook-admin', false, array(), '1.0.0' );
		wp_enqueue_style( 'cf7-webhook-admin' );
		wp_add_inline_style(
			'cf7-webhook-admin',
			<<<'CSS'
			.cf7-webhook-panel {
				color: var(--sp-admin-text);
				font-family: var(--sp-admin-font);
			}

			.cf7-webhook-panel .form-table td {
				max-width: 1120px;
			}

			.cf7-webhook-map-source {
				display: none;
			}

			.cf7-webhook-toggle {
				display: inline-flex;
				align-items: center;
				gap: 10px;
				color: var(--sp-admin-text);
				cursor: pointer;
				user-select: none;
			}

			.cf7-webhook-toggle__input {
				position: absolute;
				width: 1px;
				height: 1px;
				overflow: hidden;
				clip: rect(0 0 0 0);
				clip-path: inset(50%);
				white-space: nowrap;
			}

			.cf7-webhook-toggle__track {
				position: relative;
				flex: 0 0 auto;
				width: 48px;
				height: 26px;
				border-radius: 999px;
				background: var(--sp-admin-border-strong);
				box-shadow: inset 0 0 0 1px rgb(26 31 36 / 8%);
				transition: background-color 0.18s ease, box-shadow 0.18s ease;
			}

			.cf7-webhook-toggle__thumb {
				position: absolute;
				top: 3px;
				left: 3px;
				width: 20px;
				height: 20px;
				border-radius: 50%;
				background: var(--sp-admin-surface);
				box-shadow: 0 1px 3px rgb(26 31 36 / 24%);
				transition: transform 0.18s ease;
			}

			.cf7-webhook-toggle__input:checked + .cf7-webhook-toggle__track {
				background: var(--sp-admin-accent);
				box-shadow: inset 0 0 0 1px rgb(26 31 36 / 5%);
			}

			.cf7-webhook-toggle__input:checked + .cf7-webhook-toggle__track .cf7-webhook-toggle__thumb {
				transform: translateX(22px);
			}

			.cf7-webhook-toggle__input:focus + .cf7-webhook-toggle__track {
				box-shadow: 0 0 0 2px var(--sp-admin-surface), var(--sp-admin-focus);
			}

			.cf7-webhook-toggle__text {
				font-weight: 500;
			}

			.cf7-webhook-disabled-notice {
				margin: 8px 0 0;
				padding: 8px 10px;
				border-left: 4px solid var(--sp-admin-danger);
				border-radius: 0 var(--sp-admin-radius-xs) var(--sp-admin-radius-xs) 0;
				background: color-mix(in srgb, var(--sp-admin-danger) 8%, var(--sp-admin-surface));
				color: var(--sp-admin-danger);
			}

			.cf7-webhook-map-builder {
				padding: 16px;
				border: 1px solid var(--sp-admin-border);
				border-radius: var(--sp-admin-radius);
				background: var(--sp-admin-surface);
				box-shadow: var(--sp-admin-shadow-xs);
			}

			.cf7-webhook-map-builder__header {
				display: flex;
				align-items: flex-start;
				justify-content: space-between;
				gap: 16px;
				margin-bottom: 14px;
			}

			.cf7-webhook-map-builder__header strong {
				display: block;
				margin-bottom: 3px;
				color: var(--sp-admin-text);
				font-size: 13px;
			}

			.cf7-webhook-map-builder__header p {
				margin: 0;
				color: var(--sp-admin-muted);
			}

			.cf7-webhook-map-builder__labels,
			.cf7-webhook-map-row {
				display: grid;
				grid-template-columns: 34px minmax(160px, 0.9fr) 140px minmax(220px, 1fr) auto;
				gap: 10px;
				align-items: center;
			}

			.cf7-webhook-map-builder__labels {
				margin-bottom: 6px;
				color: var(--sp-admin-muted);
				font-size: 11px;
				font-weight: 600;
				text-transform: uppercase;
				letter-spacing: 0.03em;
			}

			.cf7-webhook-map-rows {
				display: grid;
				gap: 8px;
			}

			.cf7-webhook-map-row {
				padding: 10px;
				border: 1px solid var(--sp-admin-border);
				border-radius: var(--sp-admin-radius-sm);
				background: var(--sp-admin-surface-subtle);
				transition: border-color 0.15s ease, box-shadow 0.15s ease, opacity 0.15s ease;
			}

			.cf7-webhook-map-row.is-dragging {
				opacity: 0.48;
				border-color: var(--sp-admin-accent);
				box-shadow: var(--sp-admin-focus);
			}

			.cf7-webhook-map-row__drag {
				display: inline-flex;
				align-items: center;
				justify-content: center;
				width: 34px;
				height: 38px;
				padding: 0;
				border: 1px solid transparent;
				border-radius: 6px;
				background: transparent;
				cursor: grab;
				touch-action: none;
				transition: border-color 0.15s ease, background-color 0.15s ease;
			}

			.cf7-webhook-map-row__drag:hover,
			.cf7-webhook-map-row__drag:focus {
				border-color: var(--sp-admin-border-strong);
				background: var(--sp-admin-surface);
				outline: none;
			}

			.cf7-webhook-map-row__drag:active {
				cursor: grabbing;
			}

			.cf7-webhook-map-row__drag span {
				display: block;
				width: 16px;
				height: 20px;
				background-image: radial-gradient(var(--sp-admin-muted) 1.5px, transparent 1.5px);
				background-position: 0 0;
				background-size: 6px 6px;
			}

			.cf7-webhook-map-row input,
			.cf7-webhook-map-row select {
				width: 100%;
				max-width: none;
			}

			.cf7-webhook-map-row__custom {
				display: none;
			}

			.cf7-webhook-map-row.is-custom-value .cf7-webhook-map-row__field {
				display: none;
			}

			.cf7-webhook-map-row.is-custom-value .cf7-webhook-map-row__custom {
				display: block;
			}

			.cf7-webhook-map-row__remove {
				display: inline-flex;
				align-items: center;
				justify-content: center;
				width: 38px;
				height: 38px;
				padding: 0;
				border: 1px solid color-mix(in srgb, var(--sp-admin-danger) 35%, var(--sp-admin-border));
				border-radius: var(--sp-admin-radius-xs);
				background: color-mix(in srgb, var(--sp-admin-danger) 6%, var(--sp-admin-surface));
				color: var(--sp-admin-danger);
				cursor: pointer;
				transition: border-color 0.15s ease, background-color 0.15s ease, color 0.15s ease;
			}

			.cf7-webhook-map-row__remove:hover,
			.cf7-webhook-map-row__remove:focus {
				border-color: var(--sp-admin-danger);
				background: color-mix(in srgb, var(--sp-admin-danger) 12%, var(--sp-admin-surface));
				color: var(--sp-admin-danger);
				box-shadow: 0 0 0 3px color-mix(in srgb, var(--sp-admin-danger) 18%, transparent);
				outline: none;
			}

			.cf7-webhook-map-row__remove span {
				display: block;
				margin-top: -2px;
				font-size: 22px;
				font-weight: 600;
				line-height: 1;
			}

			.cf7-webhook-map-builder__actions {
				display: flex;
				align-items: center;
				gap: 10px;
				margin-top: 12px;
			}

			.cf7-webhook-fields {
				margin-top: 12px;
				padding: 16px;
				border: 1px solid var(--sp-admin-border);
				border-radius: var(--sp-admin-radius);
				background: var(--sp-admin-surface);
				box-shadow: var(--sp-admin-shadow-xs);
			}

			.cf7-webhook-fields__header {
				display: flex;
				align-items: flex-start;
				justify-content: space-between;
				gap: 16px;
				margin-bottom: 12px;
			}

			.cf7-webhook-fields__header strong {
				display: block;
				margin-bottom: 3px;
				color: var(--sp-admin-text);
				font-size: 13px;
			}

			.cf7-webhook-fields__header p {
				margin: 0;
				color: var(--sp-admin-muted);
			}

			.cf7-webhook-fields__badge {
				flex: 0 0 auto;
				padding: 4px 9px;
				border-radius: 999px;
				background: var(--sp-admin-accent-soft);
				color: var(--sp-admin-accent-hover);
				font-size: 11px;
				font-weight: 600;
				line-height: 1.4;
				text-transform: uppercase;
				letter-spacing: 0.03em;
			}

			.cf7-webhook-fields__example {
				display: flex;
				flex-wrap: wrap;
				align-items: center;
				gap: 8px;
				margin-bottom: 12px;
				padding: 10px 12px;
				border-left: 4px solid var(--sp-admin-accent);
				border-radius: 0 var(--sp-admin-radius-xs) var(--sp-admin-radius-xs) 0;
				background: var(--sp-admin-surface-subtle);
				color: var(--sp-admin-muted);
			}

			.cf7-webhook-fields__example code {
				color: var(--sp-admin-text);
			}

			.cf7-webhook-fields__list {
				display: flex;
				flex-wrap: wrap;
				gap: 8px;
			}

			.cf7-webhook-token {
				padding: 6px 10px;
				border: 1px solid var(--sp-admin-border-strong);
				border-radius: 999px;
				background: var(--sp-admin-surface-subtle);
				color: var(--sp-admin-text);
				font-family: Consolas, Monaco, monospace;
				font-size: 12px;
				line-height: 1.4;
				cursor: pointer;
				transition: border-color 0.15s ease, background-color 0.15s ease, box-shadow 0.15s ease, color 0.15s ease;
			}

			.cf7-webhook-token:hover,
			.cf7-webhook-token:focus {
				border-color: var(--sp-admin-accent);
				background: var(--sp-admin-accent-soft);
				color: var(--sp-admin-accent-hover);
				box-shadow: var(--sp-admin-focus);
				outline: none;
			}

			.cf7-webhook-token.is-inserted {
				border-color: var(--sp-admin-success);
				background: color-mix(in srgb, var(--sp-admin-success) 10%, var(--sp-admin-surface));
				color: var(--sp-admin-success);
			}

			.cf7-webhook-token.is-used {
				opacity: 0.42;
				background: var(--sp-admin-surface-subtle);
				box-shadow: none;
			}

			.cf7-webhook-token.is-used::after {
				content: " used";
				margin-left: 4px;
				color: var(--sp-admin-muted);
				font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
				font-size: 10px;
				font-weight: 700;
				text-transform: uppercase;
				letter-spacing: 0.03em;
			}

			@media (max-width: 782px) {
				.cf7-webhook-map-builder__header,
				.cf7-webhook-fields__header {
					display: block;
				}

				.cf7-webhook-fields__badge {
					display: inline-block;
					margin-top: 10px;
				}

				.cf7-webhook-map-builder__labels {
					display: none;
				}

				.cf7-webhook-map-row {
					grid-template-columns: 34px 1fr;
				}

				.cf7-webhook-map-row input,
				.cf7-webhook-map-row select,
				.cf7-webhook-map-row__remove {
					grid-column: 2;
				}
			}
CSS
		);

		wp_register_script( 'cf7-webhook-admin', false, array(), '1.0.0', true );
		wp_enqueue_script( 'cf7-webhook-admin' );
		wp_add_inline_script(
			'cf7-webhook-admin',
			<<<'JS'
			(function () {
				document.addEventListener('DOMContentLoaded', function () {
					var panel = document.querySelector('.cf7-webhook-panel');
					var map = document.getElementById('cf7-webhook-map');
					var builder = panel ? panel.querySelector('[data-cf7-webhook-map-builder]') : null;
					var rows = builder ? builder.querySelector('[data-cf7-webhook-map-rows]') : null;
					var addButton = builder ? builder.querySelector('[data-cf7-webhook-add-row]') : null;
					var activeRow = null;
					var draggedRow = null;
					var dragHandle = null;

					if (!panel || !map) {
						return;
					}

					function getRowParts(row) {
						return {
							key: row.querySelector('[data-cf7-webhook-map-key]'),
							type: row.querySelector('[data-cf7-webhook-map-type]'),
							custom: row.querySelector('[data-cf7-webhook-map-custom]'),
							field: row.querySelector('[data-cf7-webhook-map-field]')
						};
					}

					function getRowType(parts) {
						return parts.type && parts.type.value === 'custom' ? 'custom' : 'field';
					}

					function updateRowMode(row) {
						var parts = getRowParts(row);
						var type = getRowType(parts);

						row.classList.toggle('is-custom-value', type === 'custom');
					}

					function getUsedFields() {
						var used = {};

						if (!rows) {
							return used;
						}

						rows.querySelectorAll('[data-cf7-webhook-map-row]').forEach(function (row) {
							var parts = getRowParts(row);
							var type = getRowType(parts);
							var field = parts.field ? parts.field.value.trim() : '';

							if (type === 'field' && field) {
								used[field] = (used[field] || 0) + 1;
							}
						});

						return used;
					}

					function updateUsedFieldStates() {
						var used = getUsedFields();

						panel.querySelectorAll('[data-cf7-webhook-field]').forEach(function (button) {
							var field = button.getAttribute('data-cf7-webhook-field') || '';
							var isUsed = !!used[field];

							button.classList.toggle('is-used', isUsed);
							button.setAttribute('aria-disabled', isUsed ? 'true' : 'false');
						});

						if (!rows) {
							return;
						}

						rows.querySelectorAll('[data-cf7-webhook-map-row]').forEach(function (row) {
							var parts = getRowParts(row);
							var type = getRowType(parts);
							var current = parts.field ? parts.field.value : '';

							updateRowMode(row);

							if (!parts.field) {
								return;
							}

							Array.prototype.forEach.call(parts.field.options, function (option) {
								if (!option.value) {
									option.disabled = false;
									return;
								}

								option.disabled = type === 'field' && !!used[option.value] && option.value !== current;
							});
						});
					}

					function syncMap() {
						if (!rows) {
							return;
						}

						var lines = [];

						rows.querySelectorAll('[data-cf7-webhook-map-row]').forEach(function (row) {
							var parts = getRowParts(row);
							var key = parts.key ? parts.key.value.trim() : '';
							var type = getRowType(parts);
							var field = parts.field ? parts.field.value.trim() : '';
							var custom = parts.custom ? parts.custom.value.trim() : '';

							updateRowMode(row);

							if (key && type === 'custom' && custom) {
								lines.push(key + ' = custom:' + custom);
							} else if (key && type === 'field' && field) {
								lines.push(key + ' = ' + field);
							}
						});

						map.value = lines.join('\n');
						updateUsedFieldStates();
					}

					function ensureOneRow() {
						if (!rows || rows.querySelector('[data-cf7-webhook-map-row]')) {
							return;
						}

						syncMap();
					}

					function addRow(key, type, value) {
						if (!rows) {
							return null;
						}

						var source = rows.querySelector('[data-cf7-webhook-map-row]');

						if (!source) {
							return null;
						}

						var row = source.cloneNode(true);
						var parts = getRowParts(row);

						if (parts.key) {
							parts.key.value = key || '';
						}

						if (parts.type) {
							parts.type.value = type === 'custom' ? 'custom' : 'field';
						}

						if (parts.field) {
							parts.field.value = type === 'custom' ? '' : (value || '');
						}

						if (parts.custom) {
							parts.custom.value = type === 'custom' ? (value || '') : '';
						}

						rows.appendChild(row);
						activeRow = row;
						updateRowMode(row);
						syncMap();

						return row;
					}

					function clearDragState() {
						if (draggedRow) {
							draggedRow.classList.remove('is-dragging');
						}

						draggedRow = null;
						dragHandle = null;
					}

					function moveDraggedRow(targetRow, clientY) {
						if (!rows || !draggedRow || !targetRow || targetRow === draggedRow) {
							return;
						}

						var rect = targetRow.getBoundingClientRect();
						var insertAfter = clientY > rect.top + (rect.height / 2);

						if (insertAfter) {
							rows.insertBefore(draggedRow, targetRow.nextSibling);
						} else {
							rows.insertBefore(draggedRow, targetRow);
						}

						syncMap();
					}

					function finishDrag(event) {
						if (!draggedRow) {
							return;
						}

						if (event && event.preventDefault) {
							event.preventDefault();
						}

						if (dragHandle && dragHandle.releasePointerCapture && event && 'pointerId' in event) {
							try {
								dragHandle.releasePointerCapture(event.pointerId);
							} catch (error) {}
						}

						clearDragState();
						syncMap();
					}

					if (rows) {
						rows.addEventListener('focusin', function (event) {
							var row = event.target.closest('[data-cf7-webhook-map-row]');

							if (row) {
								activeRow = row;
							}
						});

						rows.addEventListener('input', syncMap);
						rows.addEventListener('change', syncMap);
						rows.addEventListener('pointerdown', function (event) {
							var handle = event.target.closest('[data-cf7-webhook-drag-handle]');

							if (!handle) {
								return;
							}

							draggedRow = handle.closest('[data-cf7-webhook-map-row]');

							if (!draggedRow) {
								return;
							}

							event.preventDefault();
							activeRow = draggedRow;
							dragHandle = handle;
							draggedRow.classList.add('is-dragging');

							if (dragHandle.setPointerCapture) {
								dragHandle.setPointerCapture(event.pointerId);
							}
						});
						rows.addEventListener('pointermove', function (event) {
							var target = document.elementFromPoint(event.clientX, event.clientY);
							var targetRow = target ? target.closest('[data-cf7-webhook-map-row]') : null;

							if (!draggedRow || !targetRow) {
								return;
							}

							event.preventDefault();
							moveDraggedRow(targetRow, event.clientY);
						});
						document.addEventListener('pointerup', finishDrag);
						document.addEventListener('pointercancel', finishDrag);
						rows.addEventListener('click', function (event) {
							var removeButton = event.target.closest('[data-cf7-webhook-remove-row]');

							if (!removeButton) {
								return;
							}

							var row = removeButton.closest('[data-cf7-webhook-map-row]');

							if (row) {
								if (rows.querySelectorAll('[data-cf7-webhook-map-row]').length <= 1) {
									var parts = getRowParts(row);

									if (parts.key) {
										parts.key.value = '';
										parts.key.focus();
									}

									if (parts.field) {
										parts.field.value = '';
									}

									if (parts.type) {
										parts.type.value = 'field';
									}

									if (parts.custom) {
										parts.custom.value = '';
									}

									updateRowMode(row);
									activeRow = row;
								} else {
									row.remove();
									activeRow = null;
								}

								ensureOneRow();
								syncMap();
							}
						});
					}

					if (addButton) {
						addButton.addEventListener('click', function () {
							var row = addRow('', 'field', '');
							var parts = row ? getRowParts(row) : {};

							if (parts.key) {
								parts.key.focus();
							}
						});
					}

					ensureOneRow();
					syncMap();

					panel.querySelectorAll('[data-cf7-webhook-field]').forEach(function (button) {
						button.addEventListener('click', function () {
							var field = button.getAttribute('data-cf7-webhook-field') || '';
							var row = activeRow || (rows ? rows.querySelector('[data-cf7-webhook-map-row]') : null);

							if (row) {
								var currentParts = getRowParts(row);

								if (currentParts.field && currentParts.field.value && !activeRow) {
									row = null;
								}
							}

							if (!row) {
								row = addRow('', 'field', field);
							}

							if (row) {
								var parts = getRowParts(row);

								if (parts.type) {
									parts.type.value = 'field';
								}

								if (parts.custom) {
									parts.custom.value = '';
								}

								if (parts.field) {
									parts.field.value = field;
									parts.field.focus();
									activeRow = row;
									updateRowMode(row);
									syncMap();
								}
							}

							if (window.navigator && navigator.clipboard && navigator.clipboard.writeText) {
								navigator.clipboard.writeText(field).catch(function () {});
							}

							button.classList.add('is-inserted');
							window.setTimeout(function () {
								button.classList.remove('is-inserted');
							}, 900);
						});
					});
				});
			})();
JS
		);
	}

	/**
	 * Render the settings panel for a single form.
	 *
	 * @param WPCF7_ContactForm $form
	 */
	function cf7_webhook_render_panel( $form ) {
		$form_id  = $form->id();
		$settings = cf7_webhook_get_settings( $form_id );

		$field_names = cf7_webhook_get_form_field_names( $form );
		$map_rows    = cf7_webhook_parse_map_rows( $settings['map'] );

		if ( empty( $map_rows ) ) {
			$map_rows = array(
				array(
					'key'   => '',
					'type'  => 'field',
					'value' => '',
				),
			);
		}

		$first_field_name = ! empty( $field_names ) ? reset( $field_names ) : '';
		?>
		<div class="cf7-webhook-panel sp-admin-component" data-sp-admin-component>
			<h2><?php esc_html_e( 'Webhook settings for this form', 'cf7-webhook' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'These settings apply only to this form. On a successful submission the data is sent to the URL below.', 'cf7-webhook' ); ?>
			</p>

			<table class="form-table" role="presentation">
				<tbody>
				<tr>
					<th scope="row"><?php esc_html_e( 'Enable webhook', 'cf7-webhook' ); ?></th>
					<td>
						<label class="cf7-webhook-toggle">
							<input class="cf7-webhook-toggle__input" type="checkbox" name="cf7_webhook[enabled]" value="1" <?php checked( ! empty( $settings['enabled'] ) ); ?> />
							<span class="cf7-webhook-toggle__track" aria-hidden="true">
								<span class="cf7-webhook-toggle__thumb"></span>
							</span>
							<span class="cf7-webhook-toggle__text"><?php esc_html_e( 'Send this form\'s submissions to the webhook', 'cf7-webhook' ); ?></span>
						</label>
						<?php if ( empty( $settings['enabled'] ) && ! empty( $settings['url'] ) ) : ?>
							<p class="cf7-webhook-disabled-notice">
								<?php esc_html_e( 'Webhook URL is set, but sending is currently disabled.', 'cf7-webhook' ); ?>
							</p>
						<?php endif; ?>
					</td>
				</tr>

				<tr>
					<th scope="row"><label for="cf7-webhook-url"><?php esc_html_e( 'Webhook URL', 'cf7-webhook' ); ?></label></th>
					<td>
						<input type="url" id="cf7-webhook-url" class="large-text" name="cf7_webhook[url]"
						       value="<?php echo esc_attr( $settings['url'] ); ?>"
						       placeholder="https://hook.eu1.make.com/xxxxxxxx" />
					</td>
				</tr>

				<tr>
					<th scope="row"><label for="cf7-webhook-method"><?php esc_html_e( 'HTTP method', 'cf7-webhook' ); ?></label></th>
					<td>
						<select id="cf7-webhook-method" name="cf7_webhook[method]">
							<?php foreach ( array( 'POST', 'PUT', 'PATCH' ) as $m ) : ?>
								<option value="<?php echo esc_attr( $m ); ?>" <?php selected( $settings['method'], $m ); ?>><?php echo esc_html( $m ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>

				<tr>
					<th scope="row"><label for="cf7-webhook-format"><?php esc_html_e( 'Payload format', 'cf7-webhook' ); ?></label></th>
					<td>
						<select id="cf7-webhook-format" name="cf7_webhook[format]">
							<option value="json" <?php selected( $settings['format'], 'json' ); ?>>JSON (application/json)</option>
							<option value="form" <?php selected( $settings['format'], 'form' ); ?>>Form (application/x-www-form-urlencoded)</option>
						</select>
					</td>
				</tr>

				<tr>
					<th scope="row"><label for="cf7-webhook-headers"><?php esc_html_e( 'Extra headers', 'cf7-webhook' ); ?></label></th>
					<td>
						<textarea id="cf7-webhook-headers" class="large-text code" rows="3" name="cf7_webhook[headers]"
						          placeholder="Authorization: Bearer XXXXX&#10;X-Api-Key: YYYYY"><?php echo esc_textarea( $settings['headers'] ); ?></textarea>
						<p class="description"><?php esc_html_e( 'One header per line, format: "Header-Name: value". Use this for auth tokens.', 'cf7-webhook' ); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row"><label for="cf7-webhook-map"><?php esc_html_e( 'Field mapping', 'cf7-webhook' ); ?></label></th>
					<td>
						<textarea id="cf7-webhook-map" class="cf7-webhook-map-source" name="cf7_webhook[map]" aria-hidden="true"><?php echo esc_textarea( $settings['map'] ); ?></textarea>
						<div class="cf7-webhook-map-builder" data-cf7-webhook-map-builder>
							<div class="cf7-webhook-map-builder__header">
								<div>
									<strong><?php esc_html_e( 'Payload field mapping', 'cf7-webhook' ); ?></strong>
									<p><?php esc_html_e( 'Each row sends one payload key with either a CF7 field value or a custom static value.', 'cf7-webhook' ); ?></p>
								</div>
							</div>

							<div class="cf7-webhook-map-builder__labels" aria-hidden="true">
								<span></span>
								<span><?php esc_html_e( 'Payload key', 'cf7-webhook' ); ?></span>
								<span><?php esc_html_e( 'Source', 'cf7-webhook' ); ?></span>
								<span><?php esc_html_e( 'Value', 'cf7-webhook' ); ?></span>
								<span><?php esc_html_e( 'Action', 'cf7-webhook' ); ?></span>
							</div>

							<div class="cf7-webhook-map-rows" data-cf7-webhook-map-rows>
								<?php foreach ( $map_rows as $map_row ) : ?>
									<?php
										$payload_key       = isset( $map_row['key'] ) ? $map_row['key'] : '';
										$source_type       = isset( $map_row['type'] ) && 'custom' === $map_row['type'] ? 'custom' : 'field';
										$mapped_field_name = 'field' === $source_type && isset( $map_row['value'] ) ? $map_row['value'] : '';
										$custom_value      = 'custom' === $source_type && isset( $map_row['value'] ) ? $map_row['value'] : '';
									?>
									<div class="cf7-webhook-map-row <?php echo 'custom' === $source_type ? 'is-custom-value' : ''; ?>" data-cf7-webhook-map-row>
										<button type="button" class="cf7-webhook-map-row__drag" data-cf7-webhook-drag-handle aria-label="<?php esc_attr_e( 'Drag to reorder mapping row', 'cf7-webhook' ); ?>">
											<span aria-hidden="true"></span>
										</button>
										<input type="text" class="regular-text" name="cf7_webhook_map[key][]" data-cf7-webhook-map-key value="<?php echo esc_attr( $payload_key ); ?>" placeholder="FirstName" aria-label="<?php esc_attr_e( 'Payload key', 'cf7-webhook' ); ?>" />
										<select class="cf7-webhook-map-row__type" name="cf7_webhook_map[type][]" data-cf7-webhook-map-type aria-label="<?php esc_attr_e( 'Mapping source type', 'cf7-webhook' ); ?>">
											<option value="field" <?php selected( $source_type, 'field' ); ?>><?php esc_html_e( 'CF7 field', 'cf7-webhook' ); ?></option>
											<option value="custom" <?php selected( $source_type, 'custom' ); ?>><?php esc_html_e( 'Custom value', 'cf7-webhook' ); ?></option>
										</select>
										<select class="cf7-webhook-map-row__field" name="cf7_webhook_map[field][]" data-cf7-webhook-map-field aria-label="<?php esc_attr_e( 'CF7 field key', 'cf7-webhook' ); ?>">
											<option value=""><?php esc_html_e( 'Select field', 'cf7-webhook' ); ?></option>
											<?php foreach ( $field_names as $field_name ) : ?>
												<option value="<?php echo esc_attr( $field_name ); ?>" <?php selected( $mapped_field_name, $field_name ); ?>>
													<?php echo esc_html( $field_name ); ?>
												</option>
											<?php endforeach; ?>
										</select>
										<input type="text" class="regular-text cf7-webhook-map-row__custom" name="cf7_webhook_map[custom][]" data-cf7-webhook-map-custom value="<?php echo esc_attr( $custom_value ); ?>" placeholder="<?php esc_attr_e( 'Static value / ID', 'cf7-webhook' ); ?>" aria-label="<?php esc_attr_e( 'Custom static value', 'cf7-webhook' ); ?>" />
										<button type="button" class="cf7-webhook-map-row__remove" data-cf7-webhook-remove-row aria-label="<?php esc_attr_e( 'Remove mapping row', 'cf7-webhook' ); ?>">
											<span aria-hidden="true">&times;</span>
										</button>
									</div>
								<?php endforeach; ?>
							</div>

							<div class="cf7-webhook-map-builder__actions">
								<button type="button" class="button button-secondary" data-cf7-webhook-add-row>
									<?php esc_html_e( 'Add mapping row', 'cf7-webhook' ); ?>
								</button>
								<span class="description"><?php esc_html_e( 'Leave all rows empty to send every CF7 field as-is.', 'cf7-webhook' ); ?></span>
							</div>
						</div>

						<?php if ( $field_names ) : ?>
							<div class="cf7-webhook-fields" aria-live="polite">
								<div class="cf7-webhook-fields__header">
									<div>
										<strong><?php esc_html_e( 'Available CF7 field keys', 'cf7-webhook' ); ?></strong>
										<p><?php esc_html_e( 'Click a key to put it into the currently selected mapping row.', 'cf7-webhook' ); ?></p>
									</div>
									<span class="cf7-webhook-fields__badge"><?php esc_html_e( 'Click to select', 'cf7-webhook' ); ?></span>
								</div>
								<div class="cf7-webhook-fields__example">
									<span><?php esc_html_e( 'Example:', 'cf7-webhook' ); ?></span>
									<code>SalesforceField = <?php echo esc_html( $first_field_name ); ?></code>
								</div>
								<div class="cf7-webhook-fields__list">
									<?php foreach ( $field_names as $field_name ) : ?>
										<button type="button" class="cf7-webhook-token" data-cf7-webhook-field="<?php echo esc_attr( $field_name ); ?>">
											<?php echo esc_html( $field_name ); ?>
										</button>
									<?php endforeach; ?>
								</div>
							</div>
						<?php endif; ?>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Include metadata', 'cf7-webhook' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="cf7_webhook[include_meta]" value="1" <?php checked( ! empty( $settings['include_meta'] ) ); ?> />
							<?php esc_html_e( 'Add _meta (form id, form title, page URL, submitted_at) to the payload', 'cf7-webhook' ); ?>
						</label>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Debug logging', 'cf7-webhook' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="cf7_webhook[debug]" value="1" <?php checked( ! empty( $settings['debug'] ) ); ?> />
							<?php esc_html_e( 'Write request/response to the PHP error log (for testing only)', 'cf7-webhook' ); ?>
						</label>
						<?php if ( ! empty( $settings['last_status'] ) ) : ?>
							<p class="description">
								<?php
									/* translators: %s: last HTTP status / error string */
									printf( esc_html__( 'Last delivery: %s', 'cf7-webhook' ), esc_html( $settings['last_status'] ) );
								?>
							</p>
						<?php endif; ?>
					</td>
				</tr>
				</tbody>
			</table>
		</div>

		<?php
	}

	/* -------------------------------------------------------------------------
	 * 2. SAVE — persist the panel settings when the form is saved
	 * ---------------------------------------------------------------------- */

	add_action( 'wpcf7_save_contact_form', 'cf7_webhook_save_settings' );
	function cf7_webhook_save_settings( $form ) {
		if ( ! current_user_can( 'wpcf7_edit_contact_form', $form->id() ) ) {
			return;
		}

		// CF7 already verifies its own nonce before this hook runs.
		$raw = isset( $_POST['cf7_webhook'] ) && is_array( $_POST['cf7_webhook'] )
			? wp_unslash( $_POST['cf7_webhook'] )
			: array();

		$existing = cf7_webhook_get_settings( $form->id() );
		$fields   = cf7_webhook_get_form_field_names( $form );
		$map      = isset( $raw['map'] ) ? cf7_webhook_filter_map_text( sanitize_textarea_field( $raw['map'] ), $fields ) : '';

		if ( isset( $_POST['cf7_webhook_map'] ) && is_array( $_POST['cf7_webhook_map'] ) ) {
			$map = cf7_webhook_map_rows_to_text( wp_unslash( $_POST['cf7_webhook_map'] ), $fields );
		}

		$settings = array(
			'enabled'      => ! empty( $raw['enabled'] ) ? 1 : 0,
			'url'          => isset( $raw['url'] ) ? esc_url_raw( trim( $raw['url'] ) ) : '',
			'method'       => isset( $raw['method'] ) && in_array( $raw['method'], array( 'POST', 'PUT', 'PATCH' ), true ) ? $raw['method'] : 'POST',
			'format'       => isset( $raw['format'] ) && 'form' === $raw['format'] ? 'form' : 'json',
			'headers'      => isset( $raw['headers'] ) ? sanitize_textarea_field( $raw['headers'] ) : '',
			'map'          => $map,
			'include_meta' => ! empty( $raw['include_meta'] ) ? 1 : 0,
			'debug'        => ! empty( $raw['debug'] ) ? 1 : 0,
			// Keep the last delivery status (set at send time), don't wipe it on save.
			'last_status'  => isset( $existing['last_status'] ) ? $existing['last_status'] : '',
		);

		update_post_meta( $form->id(), CF7_WEBHOOK_META_KEY, $settings );
	}

	/* -------------------------------------------------------------------------
	 * 3. SEND — fire the webhook on a successful submission
	 * ---------------------------------------------------------------------- */

// Fires after CF7 validation passes (works even if email is skipped).
	add_action( 'wpcf7_before_send_mail', 'cf7_webhook_dispatch', 10, 1 );
	function cf7_webhook_dispatch( $form ) {
		$form_id  = $form->id();
		$settings = cf7_webhook_get_settings( $form_id );

		if ( empty( $settings['enabled'] ) || empty( $settings['url'] ) ) {
			return;
		}

		if ( ! class_exists( 'WPCF7_Submission' ) ) {
			return;
		}

		$submission = WPCF7_Submission::get_instance();
		if ( ! $submission ) {
			return;
		}

		$posted = $submission->get_posted_data();
		if ( ! is_array( $posted ) ) {
			$posted = array();
		}

		// Drop CF7 internal fields (_wpcf7*, g-recaptcha, etc.).
		foreach ( array_keys( $posted ) as $key ) {
			if ( '_' === substr( $key, 0, 1 ) || 0 === strpos( $key, 'g-recaptcha' ) ) {
				unset( $posted[ $key ] );
			}
		}

		$payload = cf7_webhook_build_payload( $posted, $settings );

		if ( ! empty( $settings['include_meta'] ) ) {
			$payload['_meta'] = array(
				'form_id'      => $form_id,
				'form_title'   => $form->title(),
				'page_url'     => $submission->get_meta( 'url' ),
				'remote_ip'    => $submission->get_meta( 'remote_ip' ),
				'submitted_at' => current_time( 'c' ),
			);
		}

		$result = cf7_webhook_send( $payload, $settings );

		// Record last status for the admin panel + optional logging.
		$settings['last_status'] = $result;
		update_post_meta( $form_id, CF7_WEBHOOK_META_KEY, $settings );

		if ( ! empty( $settings['debug'] ) ) {
			error_log( '[CF7 Webhook] form ' . $form_id . ' -> ' . $settings['url'] . ' : ' . $result );
			error_log( '[CF7 Webhook] payload: ' . wp_json_encode( $payload ) );
		}
	}

	/**
	 * Build the outgoing payload from posted data + the field map.
	 */
	function cf7_webhook_build_payload( $posted, $settings ) {
		$map = cf7_webhook_parse_map_rows( $settings['map'] );

		if ( empty( $map ) ) {
			// No mapping: send every field as-is.
			return $posted;
		}

		$payload = array();
		foreach ( $map as $row ) {
			$out_key = isset( $row['key'] ) ? $row['key'] : '';
			$type    = isset( $row['type'] ) && 'custom' === $row['type'] ? 'custom' : 'field';
			$value   = isset( $row['value'] ) ? $row['value'] : '';

			if ( '' === $out_key ) {
				continue;
			}

			if ( 'custom' === $type ) {
				$payload[ $out_key ] = $value;
			} else {
				$payload[ $out_key ] = isset( $posted[ $value ] ) ? $posted[ $value ] : '';
			}
		}
		return $payload;
	}

	/**
	 * Actually deliver the request. Returns a short status string.
	 */
	function cf7_webhook_send( $payload, $settings ) {
		$headers = cf7_webhook_parse_headers( $settings['headers'] );

		if ( 'form' === $settings['format'] ) {
			// wp_remote_* encodes an array body as application/x-www-form-urlencoded.
			$body = $payload;
			if ( empty( $headers['Content-Type'] ) && empty( $headers['content-type'] ) ) {
				$headers['Content-Type'] = 'application/x-www-form-urlencoded';
			}
		} else {
			$body = wp_json_encode( $payload );
			if ( empty( $headers['Content-Type'] ) && empty( $headers['content-type'] ) ) {
				$headers['Content-Type'] = 'application/json';
			}
		}

		$args = array(
			'method'      => $settings['method'],
			'timeout'     => 15,
			'redirection' => 3,
			'headers'     => $headers,
			'body'        => $body,
			'blocking'    => true,
		);

		$response = wp_remote_request( $settings['url'], $args );

		if ( is_wp_error( $response ) ) {
			return 'ERROR: ' . $response->get_error_message();
		}

		$code = wp_remote_retrieve_response_code( $response );
		return sprintf( 'HTTP %d at %s', $code, current_time( 'mysql' ) );
	}

	/* -------------------------------------------------------------------------
	 * 4. HELPERS
	 * ---------------------------------------------------------------------- */

	function cf7_webhook_get_settings( $form_id ) {
		$defaults = array(
			'enabled'      => 0,
			'url'          => '',
			'method'       => 'POST',
			'format'       => 'json',
			'headers'      => '',
			'map'          => '',
			'include_meta' => 1,
			'debug'        => 0,
			'last_status'  => '',
		);
		$saved = get_post_meta( $form_id, CF7_WEBHOOK_META_KEY, true );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		return wp_parse_args( $saved, $defaults );
	}

	function cf7_webhook_get_form_field_names( $form ) {
		$tags   = $form->scan_form_tags();
		$fields = array();

		foreach ( $tags as $tag ) {
			if ( ! empty( $tag->name ) ) {
				$fields[ $tag->name ] = true;
			}
		}

		return array_keys( $fields );
	}

	/**
	 * Parse "Header-Name: value" lines into an associative array.
	 */
	function cf7_webhook_parse_headers( $text ) {
		$headers = array();
		$lines   = preg_split( '/\r\n|\r|\n/', (string) $text );
		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( '' === $line || false === strpos( $line, ':' ) ) {
				continue;
			}
			list( $name, $value ) = array_map( 'trim', explode( ':', $line, 2 ) );
			if ( '' !== $name ) {
				$headers[ $name ] = $value;
			}
		}
		return $headers;
	}

	function cf7_webhook_map_rows_to_text( $rows, $allowed_fields = null ) {
		$keys           = isset( $rows['key'] ) && is_array( $rows['key'] ) ? $rows['key'] : array();
		$types          = isset( $rows['type'] ) && is_array( $rows['type'] ) ? $rows['type'] : array();
		$fields         = isset( $rows['field'] ) && is_array( $rows['field'] ) ? $rows['field'] : array();
		$customs        = isset( $rows['custom'] ) && is_array( $rows['custom'] ) ? $rows['custom'] : array();
		$allowed_lookup = null === $allowed_fields ? null : array_fill_keys( $allowed_fields, true );
		$lines          = array();

		foreach ( $keys as $index => $key ) {
			$key   = sanitize_text_field( $key );
			$type  = isset( $types[ $index ] ) && 'custom' === $types[ $index ] ? 'custom' : 'field';
			$field = isset( $fields[ $index ] ) ? sanitize_text_field( $fields[ $index ] ) : '';
			$custom = isset( $customs[ $index ] ) ? sanitize_text_field( $customs[ $index ] ) : '';

			if ( '' === $key ) {
				continue;
			}

			if ( 'custom' === $type ) {
				if ( '' === $custom ) {
					continue;
				}

				$lines[] = $key . ' = custom:' . $custom;
				continue;
			}

			if ( '' === $field || ( null !== $allowed_lookup && ! isset( $allowed_lookup[ $field ] ) ) ) {
				continue;
			}

			$lines[] = $key . ' = ' . $field;
		}

		return implode( "\n", $lines );
	}

	function cf7_webhook_filter_map_text( $text, $allowed_fields = null ) {
		$map            = cf7_webhook_parse_map_rows( $text );
		$allowed_lookup = null === $allowed_fields ? null : array_fill_keys( $allowed_fields, true );
		$lines          = array();

		foreach ( $map as $row ) {
			$key   = isset( $row['key'] ) ? sanitize_text_field( $row['key'] ) : '';
			$type  = isset( $row['type'] ) && 'custom' === $row['type'] ? 'custom' : 'field';
			$value = isset( $row['value'] ) ? sanitize_text_field( $row['value'] ) : '';

			if ( '' === $key || '' === $value ) {
				continue;
			}

			if ( 'custom' === $type ) {
				$lines[] = $key . ' = custom:' . $value;
				continue;
			}

			if ( null !== $allowed_lookup && ! isset( $allowed_lookup[ $value ] ) ) {
				continue;
			}

			$lines[] = $key . ' = ' . $value;
		}

		return implode( "\n", $lines );
	}

	/**
	 * Parse mapping lines into row data.
	 *
	 * Supports old lines like "payload_key = cf7_field" and new custom lines like
	 * "payload_key = custom:static value".
	 */
	function cf7_webhook_parse_map_rows( $text ) {
		$rows  = array();
		$lines = preg_split( '/\r\n|\r|\n/', (string) $text );

		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( '' === $line || false === strpos( $line, '=' ) ) {
				continue;
			}

			list( $out, $value ) = array_map( 'trim', explode( '=', $line, 2 ) );
			if ( '' === $out || '' === $value ) {
				continue;
			}

			$type = 'field';
			if ( 0 === strpos( $value, 'custom:' ) ) {
				$type  = 'custom';
				$value = trim( substr( $value, strlen( 'custom:' ) ) );

				if ( '' === $value ) {
					continue;
				}
			} elseif ( 0 === strpos( $value, 'field:' ) ) {
				$value = trim( substr( $value, strlen( 'field:' ) ) );

				if ( '' === $value ) {
					continue;
				}
			}

			$rows[] = array(
				'key'   => $out,
				'type'  => $type,
				'value' => $value,
			);
		}

		return $rows;
	}

	/**
	 * Parse field mapping lines into [payload_key => cf7_field].
	 */
	function cf7_webhook_parse_map( $text ) {
		$map   = array();
		$rows = cf7_webhook_parse_map_rows( $text );

		foreach ( $rows as $row ) {
			if ( isset( $row['type'] ) && 'field' !== $row['type'] ) {
				continue;
			}

			if ( ! empty( $row['key'] ) && ! empty( $row['value'] ) ) {
				$map[ $row['key'] ] = $row['value'];
			}
		}

		return $map;
	}
