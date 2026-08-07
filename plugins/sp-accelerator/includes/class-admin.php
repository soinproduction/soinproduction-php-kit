<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SP_Accelerator_Admin {
	private const PAGE_SLUG = 'sp-accelerator';

	/** @var SP_Accelerator_Config */
	private $config;

	/** @var SP_Accelerator_Cache */
	private $cache;

	/** @var SP_Accelerator_Dropin */
	private $dropin;

	/** @var SP_Accelerator_Object_Cache */
	private $object_cache;

	/** @var SP_Accelerator_Server */
	private $server;

	/** @var SP_Accelerator_Warmer */
	private $warmer;

	/** @var string */
	private $plugin_url;

	/**
	 * The last arguments intentionally remain backwards compatible with the
	 * pre-object-cache constructor. That keeps wp-admin alive during a
	 * non-atomic FTP deployment where index.php and this file briefly differ.
	 *
	 * @param SP_Accelerator_Object_Cache|string|null $object_cache
	 * @param SP_Accelerator_Warmer|null              $warmer
	 */
	public function __construct( SP_Accelerator_Config $config, SP_Accelerator_Cache $cache, SP_Accelerator_Dropin $dropin, $object_cache = null, $warmer = null, string $plugin_url = '', $server = null ) {
		if ( is_string( $object_cache ) && $plugin_url === '' ) {
			$plugin_url  = $object_cache;
			$object_cache = null;
		}

		if ( ! $object_cache instanceof SP_Accelerator_Object_Cache ) {
			$object_cache = new SP_Accelerator_Object_Cache( $config, dirname( __DIR__ ) );
		}
		if ( ! $warmer instanceof SP_Accelerator_Warmer ) {
			$warmer = new SP_Accelerator_Warmer( $cache, $config );
		}
		if ( ! $server instanceof SP_Accelerator_Server ) {
			$server = new SP_Accelerator_Server();
		}
		if ( $plugin_url === '' ) {
			$plugin_url = trailingslashit( \SoinProduction\Kit\Bootstrapper::pathToUrl( dirname( __DIR__ ) ) );
		}

		$this->config     = $config;
		$this->cache      = $cache;
		$this->dropin     = $dropin;
		$this->object_cache = $object_cache;
		$this->server      = $server;
		$this->warmer     = $warmer;
		$this->plugin_url = trailingslashit( $plugin_url );
	}

	public function register(): void {
		$this->warmer->register();
		add_action( 'admin_menu', [ $this, 'menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'assets' ] );
		add_action( 'admin_post_sp_accelerator_save', [ $this, 'save' ] );
		add_action( 'admin_post_sp_accelerator_purge', [ $this, 'purge' ] );
		add_action( 'admin_post_sp_accelerator_warm', [ $this, 'warm' ] );
		add_action( 'admin_post_sp_accelerator_install_dropin', [ $this, 'install_dropin' ] );
		add_action( 'admin_post_sp_accelerator_remove_dropin', [ $this, 'remove_dropin' ] );
		add_action( 'admin_post_sp_accelerator_install_object_cache', [ $this, 'install_object_cache' ] );
		add_action( 'admin_post_sp_accelerator_remove_object_cache', [ $this, 'remove_object_cache' ] );
		add_action( 'admin_post_sp_accelerator_flush_object_cache', [ $this, 'flush_object_cache' ] );
		add_action( 'admin_post_sp_accelerator_install_server_rules', [ $this, 'install_server_rules' ] );
		add_action( 'admin_post_sp_accelerator_remove_server_rules', [ $this, 'remove_server_rules' ] );
	}

	public function menu(): void {
		add_options_page(
			'SP Accelerator',
			'<span style="display:flex;align-items:center;gap:5px;">
				<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true">
					<path d="M4.9 17a8 8 0 1 1 14.2 0" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
					<path d="m12 13 4-5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
					<circle cx="12" cy="13" r="2" fill="currentColor"/>
				</svg>
				Accelerator
			</span>',
			'manage_options',
			self::PAGE_SLUG,
			[ $this, 'render' ]
		);
	}

	public function assets( string $hook ): void {
		if ( $hook !== 'settings_page_' . self::PAGE_SLUG ) {
			return;
		}

		$style_file    = dirname( __DIR__ ) . '/assets/admin.css';
		$style_version = is_readable( $style_file ) ? (string) filemtime( $style_file ) : SP_Accelerator_Config::VERSION;

		wp_enqueue_style(
			'sp-accelerator-admin',
			$this->plugin_url . 'assets/admin.css',
			[],
			$style_version
		);
	}

	public function save(): void {
		$this->authorize( 'sp_accelerator_save' );
		$input = isset( $_POST['sp_accelerator'] ) && is_array( $_POST['sp_accelerator'] )
			? wp_unslash( $_POST['sp_accelerator'] )
			: [];

		$saved = $this->config->save( $input );
		$purged = $this->cache->purge_all();
		$this->notice(
			$saved && $purged ? 'success' : 'error',
			$saved && $purged
				? 'Настройки сохранены. Поколение кеша обновлено и поставлено на прогрев.'
				: 'Настройки или новое поколение записаны не полностью: проверьте options и права на wp-content/cache/sp-accelerator, затем повторите.'
		);
		$this->redirect();
	}

	public function purge(): void {
		$this->authorize( 'sp_accelerator_purge' );
		$purged = $this->cache->purge_all();
		$this->notice( $purged ? 'success' : 'error', $purged ? 'Кеш очищен мгновенной сменой поколения и поставлен на прогрев.' : 'Не удалось сменить поколение кеша: проверьте запись options и cache config.' );
		$this->redirect();
	}

	public function warm(): void {
		$this->authorize( 'sp_accelerator_warm' );
		$result = $this->warmer->start();
		if ( is_wp_error( $result ) ) {
			$this->notice( 'error', 'Прогрев не запущен: ' . $result->get_error_message() );
		} else {
			$this->notice( 'success', sprintf( 'Запущен прогрев всех публичных URL: %d всего, %d уже готовы.', (int) $result['total'], (int) $result['done'] ) );
		}

		$this->redirect();
	}

	public function install_dropin(): void {
		$this->authorize( 'sp_accelerator_install_dropin' );
		$result = $this->dropin->install();
		if ( is_wp_error( $result ) ) {
			$this->notice( 'error', $result->get_error_message() );
		} else {
			$this->notice( 'success', 'Быстрый drop-in установлен и проверен.' );
		}
		$this->redirect();
	}

	public function remove_dropin(): void {
		$this->authorize( 'sp_accelerator_remove_dropin' );
		$result = $this->dropin->remove();
		if ( is_wp_error( $result ) ) {
			$this->notice( 'error', $result->get_error_message() );
		} else {
			$this->notice( 'success', 'Drop-in удалён; fallback-кеш темы остаётся доступен.' );
		}
		$this->redirect();
	}

	public function install_object_cache(): void {
		$this->authorize( 'sp_accelerator_install_object_cache' );
		$result = $this->object_cache->install();
		$this->notice( is_wp_error( $result ) ? 'error' : 'success', is_wp_error( $result ) ? $result->get_error_message() : 'Persistent object cache установлен. Следующий запрос загрузит SQLite backend.' );
		$this->redirect();
	}

	public function remove_object_cache(): void {
		$this->authorize( 'sp_accelerator_remove_object_cache' );
		$result = $this->object_cache->remove();
		$this->notice( is_wp_error( $result ) ? 'error' : 'success', is_wp_error( $result ) ? $result->get_error_message() : 'Наш object-cache.php удалён, данные очищены.' );
		$this->redirect();
	}

	public function flush_object_cache(): void {
		$this->authorize( 'sp_accelerator_flush_object_cache' );
		$ok = $this->object_cache->flush();
		$this->notice( $ok ? 'success' : 'error', $ok ? 'Persistent object cache очищен.' : 'Не удалось очистить object cache.' );
		$this->redirect();
	}

	public function install_server_rules(): void {
		$this->authorize( 'sp_accelerator_install_server_rules' );
		$result = $this->server->install();
		$this->notice( is_wp_error( $result ) ? 'error' : 'success', is_wp_error( $result ) ? $result->get_error_message() : 'Server cache/compression rules установлены и проверены.' );
		$this->redirect();
	}

	public function remove_server_rules(): void {
		$this->authorize( 'sp_accelerator_remove_server_rules' );
		$result = $this->server->remove();
		$this->notice( is_wp_error( $result ) ? 'error' : 'success', is_wp_error( $result ) ? $result->get_error_message() : 'SP Accelerator marker удалён из .htaccess.' );
		$this->redirect();
	}

	private function authorize( string $nonce_action ): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Недостаточно прав для управления SP Accelerator.' );
		}
		check_admin_referer( $nonce_action );
	}

	private function notice( string $type, string $message ): void {
		set_transient( 'sp_accelerator_notice_' . get_current_user_id(), [
			'type'    => $type === 'error' ? 'error' : 'success',
			'message' => $message,
		], 60 );
	}

	private function redirect(): void {
		wp_safe_redirect( admin_url( 'options-general.php?page=' . self::PAGE_SLUG ) );
		exit;
	}

	private function action_form( string $action, string $label, string $class = 'button' ): void {
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="sp-accelerator__action-form">
			<input type="hidden" name="action" value="<?php echo esc_attr( $action ); ?>">
			<?php wp_nonce_field( $action ); ?>
			<button type="submit" class="<?php echo esc_attr( $class ); ?>"><?php echo esc_html( $label ); ?></button>
		</form>
		<?php
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings     = $this->config->all();
		$stats        = $this->cache->stats();
		$dropin       = $this->dropin->status();
		$object       = $this->object_cache->status();
		$object_stats = $this->object_cache->stats();
		$server       = $this->server->status();
		$warm         = $this->warmer->state();
		$cache_ready  = is_dir( $this->config->cache_root() ) && is_writable( $this->config->cache_root() );
		$notice_key   = 'sp_accelerator_notice_' . get_current_user_id();
		$notice       = get_transient( $notice_key );
		delete_transient( $notice_key );
		$is_enabled   = $this->config->enabled();
		$warm_total   = max( 0, (int) $warm['total'] );
		$warm_ready   = max( 0, (int) $warm['done'] + (int) $warm['failed'] );
		$warm_percent = $warm_total > 0 ? min( 100, (int) round( $warm_ready / $warm_total * 100 ) ) : 0;
		?>
		<div class="wrap sp-accelerator sp-admin-page">
			<header class="sp-accelerator__hero sp-admin-header">
				<div class="sp-admin-header__identity">
					<span class="sp-admin-header__icon dashicons dashicons-performance" aria-hidden="true"></span>
					<div class="sp-admin-header__copy">
					<h1>SP Accelerator</h1>
					<p>Собственный кеш и оптимизация asset-графа темы. Без лицензии, облака и внешнего API.</p>
					</div>
				</div>
				<div class="sp-admin-header__actions">
					<span class="sp-accelerator__master <?php echo $is_enabled ? 'is-on' : 'is-off'; ?>">
						<?php echo $is_enabled ? 'Ускорение включено' : 'Ускорение выключено'; ?>
					</span>
					<button type="submit" class="button button-primary" form="sp-accelerator-settings">Сохранить настройки</button>
				</div>
			</header>

			<?php if ( is_array( $notice ) && ! empty( $notice['message'] ) ) : ?>
				<div class="notice notice-<?php echo esc_attr( (string) $notice['type'] ); ?> is-dismissible"><p><?php echo esc_html( (string) $notice['message'] ); ?></p></div>
			<?php endif; ?>
			<?php if ( $this->config->has_legacy_accelerator_conflict() ) : ?>
				<div class="notice notice-warning"><p><strong>Обнаружен активный Seraphinite Accelerator.</strong> SP Accelerator временно не вмешивается во frontend, чтобы не получить двойную минификацию и задержку скриптов. После деактивации Seraphinite настройки включатся автоматически.</p></div>
			<?php endif; ?>
			<?php if ( is_multisite() && ( ! defined( 'SP_ACCELERATOR_MULTISITE_CACHE' ) || ! SP_ACCELERATOR_MULTISITE_CACHE ) ) : ?>
				<div class="notice notice-warning"><p><strong>Multisite:</strong> frontend-оптимизации активны, но общий page-cache drop-in безопасно отключён. Для осознанного включения после проверки доменов и cookies задайте <code>SP_ACCELERATOR_MULTISITE_CACHE</code>.</p></div>
			<?php endif; ?>
			<?php if ( ! $this->config->storage_is_safe_for_server() ) : ?>
				<div class="notice notice-error"><p><strong>Page cache fail-closed:</strong> <?php echo esc_html( $this->config->storage_safety_message() ); ?></p></div>
			<?php endif; ?>

			<div class="sp-accelerator__metrics sp-admin-metrics">
				<div class="sp-metric sp-admin-metric">
					<span>Page cache</span>
					<strong><?php echo $this->config->enabled( 'page_cache' ) ? 'ON' : 'OFF'; ?></strong>
					<small><?php echo esc_html( number_format_i18n( $stats['files'] ) ); ?> URL закешировано<?php echo $warm_total > 0 ? ' · найдено ' . esc_html( number_format_i18n( $warm_total ) ) : ' · кеш создаётся при визите'; ?></small>
				</div>
				<div class="sp-metric sp-admin-metric">
					<span>Page cache drop-in</span>
					<strong class="sp-status--<?php echo esc_attr( $dropin['code'] ); ?>"><?php echo esc_html( $dropin['label'] ); ?></strong>
					<small><?php echo esc_html( $dropin['detail'] ); ?></small>
				</div>
				<div class="sp-metric sp-admin-metric">
					<span>Object cache</span>
					<strong class="sp-status--<?php echo esc_attr( $object['code'] ); ?>"><?php echo esc_html( $object['label'] ); ?></strong>
					<small><?php echo esc_html( number_format_i18n( $object_stats['items'] ) ); ?> persistent objects · <?php echo esc_html( size_format( $object_stats['bytes'], 1 ) ); ?></small>
				</div>
				<div class="sp-metric sp-admin-metric">
					<span>Cache storage</span>
					<strong><?php echo esc_html( size_format( $stats['bytes'] + $object_stats['bytes'], 1 ) ); ?></strong>
					<small><?php echo $cache_ready ? 'Каталог доступен для записи' : 'Нужны права на запись'; ?></small>
				</div>
				<div class="sp-metric sp-admin-metric">
					<span>Server policy</span>
					<strong><?php echo esc_html( $server['code'] === 'active' ? 'RULES SET' : ( function_exists( 'gzencode' ) && ! empty( $settings['gzip_cache'] ) ? 'GZIP page cache' : 'MANUAL' ) ); ?></strong>
					<small><?php echo esc_html( $server['detail'] ); ?></small>
				</div>
			</div>

			<div class="sp-accelerator__layout">
				<main>
					<form id="sp-accelerator-settings" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="sp_accelerator_save">
						<?php wp_nonce_field( 'sp_accelerator_save' ); ?>

						<section class="sp-panel sp-admin-card">
							<div class="sp-panel__heading sp-admin-card__header"><div class="sp-admin-card__copy"><h2>Основной кеш</h2><p>HTML сохраняется атомарно; устаревший кеш защищает сайт во время перестроения.</p></div><span class="sp-chip">TTFB</span></div>
							<?php $this->switch_row( 'enabled', 'Включить Accelerator', 'Главный переключатель всех frontend-оптимизаций.', $settings ); ?>
							<?php $this->switch_row( 'page_cache', 'Full-page cache', 'Только публичные GET/HEAD-запросы без приватных cookies.', $settings ); ?>
							<?php $this->switch_row( 'auto_warm', 'Автопрогрев после изменений', 'Новое поколение строится очередью WP-Cron, пока посетители получают безопасную stale-копию.', $settings ); ?>
							<?php $this->switch_row( 'gzip_cache', 'Предварительный GZIP', 'Сжатая копия создаётся один раз вместе с HTML.', $settings ); ?>
							<?php $this->switch_row( 'minify_html', 'Безопасная минификация HTML', 'Сохраняет содержимое script/style/pre/textarea/template без изменений.', $settings ); ?>
							<div class="sp-field-grid">
								<label><span>Свежий кеш, сек.</span><input type="number" min="60" max="604800" name="sp_accelerator[cache_ttl]" value="<?php echo esc_attr( (string) $settings['cache_ttl'] ); ?>"></label>
								<label><span>Stale window, сек.</span><input type="number" min="0" max="86400" name="sp_accelerator[stale_ttl]" value="<?php echo esc_attr( (string) $settings['stale_ttl'] ); ?>"></label>
								<label><span>Старое поколение, сек.</span><input type="number" min="0" max="86400" name="sp_accelerator[generation_stale_ttl]" value="<?php echo esc_attr( (string) $settings['generation_stale_ttl'] ); ?>"></label>
								<label><span>Browser HTML cache, сек.</span><input type="number" min="0" max="3600" name="sp_accelerator[browser_cache_ttl]" value="<?php echo esc_attr( (string) $settings['browser_cache_ttl'] ); ?>"></label>
							</div>
						</section>

						<section class="sp-panel sp-admin-card">
							<div class="sp-panel__heading sp-admin-card__header"><div class="sp-admin-card__copy"><h2>Assets темы</h2><p>Оптимизация знает manifest и ACF-секции, поэтому не переписывает готовый HTML вслепую.</p></div><span class="sp-chip">CWV</span></div>
							<?php $this->switch_row( 'preload_main_script', 'Preload main.js', 'Необязательный приоритет main entry; включайте только если замер подтверждает пользу.', $settings ); ?>
							<?php $this->switch_row( 'limit_font_preloads', 'Только критические шрифты', 'DMSans Regular и Bold для навигации и hero heading; остальные не конкурируют с LCP.', $settings ); ?>
							<?php $this->switch_row( 'resource_hints', 'Resource hints', 'До четырёх внешних origins получают preconnect/dns-prefetch из реальной очереди assets.', $settings ); ?>
							<?php $this->switch_row( 'async_main_style', 'Асинхронный main.css', 'Inline critical.css формирует первый экран, полный style.css догружается без блокировки рендера.', $settings ); ?>
							<?php $this->switch_row( 'async_section_styles', 'Асинхронный CSS нижних секций', 'Hero остаётся критическим, остальные section-*.css загружаются через preload-on-load.', $settings ); ?>
							<?php $this->switch_row( 'delay_section_scripts', 'Отложить JS нижних секций', 'Модули и их vendor-зависимости ждут близость секции, interaction, idle или timeout.', $settings ); ?>
							<?php $this->switch_row( 'async_image_decoding', 'Асинхронное декодирование изображений', 'Не меняет уже заданные LCP-атрибуты.', $settings ); ?>
							<label class="sp-inline-field"><span>Fallback для delayed JS</span><input type="number" min="0" max="30000" step="250" name="sp_accelerator[script_delay_ms]" value="<?php echo esc_attr( (string) $settings['script_delay_ms'] ); ?>"><em>мс</em></label>
						</section>

						<section class="sp-panel sp-admin-card">
							<div class="sp-panel__heading sp-admin-card__header"><div class="sp-admin-card__copy"><h2>HTML и LCP media</h2><p>DOM обрабатывается через WordPress HTML API до записи в page cache.</p></div><span class="sp-chip">LCP / CLS</span></div>
							<?php $this->switch_row( 'optimize_markup', 'Оптимизировать markup', 'Главный переключатель безопасных image, iframe и video преобразований.', $settings ); ?>
							<?php $this->switch_row( 'preload_lcp_image', 'Найти и preload LCP image', 'Первое подходящее крупное изображение получает eager, fetchpriority=high и responsive preload.', $settings ); ?>
							<?php $this->switch_row( 'add_image_dimensions', 'Добавлять width/height', 'Локальные attachment/theme images получают размеры файла для снижения CLS.', $settings ); ?>
							<?php $this->switch_row( 'lazy_embeds', 'Lazy iframe и video', 'Внешние iframe получают loading=lazy, offscreen video — preload=none.', $settings ); ?>
						</section>

						<section class="sp-panel sp-admin-card">
							<div class="sp-panel__heading sp-admin-card__header"><div class="sp-admin-card__copy"><h2>Исключения</h2><p>Правила персонализации проверяются до чтения и записи cache entry.</p></div></div>
							<?php $this->switch_row( 'bypass_unknown_cookies', 'Обходить кеш для неизвестных cookies', 'Безопасный режим: только явно разрешённые аналитические cookies могут использовать общий page cache.', $settings ); ?>
							<label><strong>Paths</strong><small>Один path-prefix или регулярное выражение <code>~...~i</code> на строку.</small></label>
							<textarea class="large-text code" rows="10" name="sp_accelerator[exclude_paths]" spellcheck="false"><?php echo esc_textarea( (string) $settings['exclude_paths'] ); ?></textarea>
							<label><strong>Cookie names / prefixes</strong><small>Один lower-case marker на строку; совпадение отключает page cache для запроса.</small></label>
							<textarea class="large-text code" rows="10" name="sp_accelerator[exclude_cookies]" spellcheck="false"><?php echo esc_textarea( (string) $settings['exclude_cookies'] ); ?></textarea>
							<label><strong>Safe cookie prefixes</strong><small>Только cookies, которые гарантированно не меняют серверный HTML. Не добавляйте сюда login, cart, currency или language.</small></label>
							<textarea class="large-text code" rows="8" name="sp_accelerator[allow_cookies]" spellcheck="false"><?php echo esc_textarea( (string) $settings['allow_cookies'] ); ?></textarea>
						</section>

					</form>
				</main>

				<aside>
					<section class="sp-panel sp-panel--aside">
						<div class="sp-admin-card__header"><h2>Static assets / compression</h2></div>
						<p><strong class="sp-status--<?php echo esc_attr( $server['code'] ); ?>"><?php echo esc_html( $server['label'] ); ?></strong></p>
						<p><?php echo esc_html( $server['detail'] ); ?></p>
					<?php if ( $server['code'] === 'missing' ) : ?>
						<?php $this->action_form( 'sp_accelerator_install_server_rules', 'Установить правила .htaccess', 'button button-primary button-hero' ); ?>
					<?php elseif ( $server['code'] === 'outdated' ) : ?>
						<div class="sp-actions">
							<?php $this->action_form( 'sp_accelerator_install_server_rules', 'Обновить rules', 'button button-primary' ); ?>
							<?php $this->action_form( 'sp_accelerator_remove_server_rules', 'Удалить marker', 'button' ); ?>
						</div>
					<?php elseif ( $server['code'] === 'active' ) : ?>
							<?php $this->action_form( 'sp_accelerator_remove_server_rules', 'Удалить наши правила', 'button' ); ?>
					<?php elseif ( $server['code'] === 'ignored' ) : ?>
							<?php $this->action_form( 'sp_accelerator_remove_server_rules', 'Удалить неиспользуемый marker', 'button' ); ?>
					<?php elseif ( in_array( $server['code'], [ 'manual', 'readonly', 'broken' ], true ) ) : ?>
							<p class="sp-warning">Потребуется настройка хостинга.</p>
						<?php endif; ?>
					</section>

					<section class="sp-panel sp-panel--aside">
						<div class="sp-admin-card__header"><h2>Page Cache Drop-in</h2></div>
						<p><?php echo esc_html( $dropin['detail'] ); ?></p>
						<?php if ( $this->config->has_legacy_accelerator_conflict() ) : ?>
							<p class="sp-warning">Деактивируйте Seraphinite перед установкой.</p>
						<?php elseif ( ! $this->config->storage_is_safe_for_server() ) : ?>
							<p class="sp-warning"><?php echo esc_html( $this->config->storage_safety_message() ); ?></p>
						<?php elseif ( in_array( $dropin['code'], [ 'missing', 'replaceable' ], true ) ) : ?>
							<?php $this->action_form( 'sp_accelerator_install_dropin', 'Установить SP Page Cache Drop-in', 'button button-primary button-hero' ); ?>
						<?php elseif ( $dropin['code'] === 'outdated' ) : ?>
							<div class="sp-actions">
								<?php $this->action_form( 'sp_accelerator_install_dropin', 'Обновить Page Cache Drop-in', 'button button-primary' ); ?>
								<?php $this->action_form( 'sp_accelerator_remove_dropin', 'Удалить старый drop-in', 'button' ); ?>
							</div>
						<?php elseif ( in_array( $dropin['code'], [ 'active', 'inactive', 'blocked' ], true ) ) : ?>
							<?php $this->action_form( 'sp_accelerator_remove_dropin', 'Удалить наш drop-in', 'button' ); ?>
						<?php endif; ?>
						<?php if ( $dropin['code'] === 'foreign' ) : ?><p class="sp-warning">Сначала отключите другой page-cache плагин.</p><?php endif; ?>
					</section>

					<section class="sp-panel sp-panel--aside">
						<div class="sp-admin-card__header"><h2>Persistent Object Cache</h2></div>
						<p><?php echo esc_html( $object['detail'] ); ?></p>
						<?php if ( $this->config->has_legacy_accelerator_conflict() ) : ?>
							<p class="sp-warning">Деактивируйте Seraphinite перед установкой.</p>
						<?php elseif ( $object['code'] === 'missing' ) : ?>
							<?php $this->action_form( 'sp_accelerator_install_object_cache', 'Установить Object Cache', 'button button-primary button-hero' ); ?>
						<?php elseif ( $object['code'] === 'outdated' ) : ?>
							<div class="sp-actions">
								<?php $this->action_form( 'sp_accelerator_install_object_cache', 'Обновить Object Cache', 'button button-primary' ); ?>
								<?php $this->action_form( 'sp_accelerator_remove_object_cache', 'Удалить старый drop-in', 'button' ); ?>
							</div>
						<?php elseif ( in_array( $object['code'], [ 'active', 'installed' ], true ) ) : ?>
							<div class="sp-actions">
								<?php $this->action_form( 'sp_accelerator_flush_object_cache', 'Очистить объекты', 'button button-primary' ); ?>
								<?php $this->action_form( 'sp_accelerator_remove_object_cache', 'Удалить drop-in', 'button' ); ?>
							</div>
						<?php else : ?>
							<p class="sp-warning"><?php echo esc_html( $object['detail'] ); ?></p>
						<?php endif; ?>
					</section>

					<section class="sp-panel sp-panel--aside">
						<div class="sp-admin-card__header"><h2>Полный прогрев сайта</h2></div>
						<p>Находит главную, все опубликованные записи/CPT, архивы и публичные термины. URL считается прогретым только после <code>HTTP 200</code> и подтверждённого <code>X-SP-Cache: MISS</code>.</p>
						<?php if ( $warm_total > 0 ) : ?>
							<div class="sp-progress"><i style="width:<?php echo esc_attr( (string) $warm_percent ); ?>%"></i></div>
							<p class="sp-progress__text"><?php echo esc_html( number_format_i18n( $warm_ready ) . ' / ' . number_format_i18n( $warm_total ) ); ?> · <?php echo in_array( $warm['status'], [ 'pending', 'running' ], true ) ? 'прогрев выполняется' : 'прогрев завершён'; ?><?php echo (int) $warm['failed'] > 0 ? ' · ошибок: ' . esc_html( number_format_i18n( (int) $warm['failed'] ) ) : ''; ?></p>
						<?php else : ?>
							<p class="sp-progress__text">Ещё не запускался. Поэтому сейчас отображается только 1 URL, который посетил анонимный браузер.</p>
						<?php endif; ?>
						<?php if ( ! empty( $warm['failed_urls'] ) && is_array( $warm['failed_urls'] ) ) : ?>
							<details>
								<summary>Показать ошибки прогрева</summary>
								<ul class="sp-checklist">
									<?php foreach ( array_slice( $warm['failed_urls'], 0, 30 ) as $failed_url ) : ?>
										<li><?php echo esc_html( (string) $failed_url ); ?></li>
									<?php endforeach; ?>
								</ul>
							</details>
						<?php endif; ?>
						<div class="sp-actions">
							<?php $this->action_form( 'sp_accelerator_purge', 'Очистить кеш', 'button button-primary' ); ?>
							<?php if ( $this->config->enabled( 'page_cache' ) ) : ?>
								<?php $this->action_form( 'sp_accelerator_warm', in_array( $warm['status'], [ 'pending', 'running' ], true ) ? 'Перезапустить прогрев' : 'Прогреть весь сайт', 'button' ); ?>
							<?php endif; ?>
						</div>
						<?php if ( ! $this->config->enabled( 'page_cache' ) ) : ?><p class="sp-warning">Сначала включите и разблокируйте page cache.</p><?php endif; ?>
					</section>

					<section class="sp-panel sp-panel--aside">
						<div class="sp-admin-card__header"><h2>Что кеш не трогает</h2></div>
						<ul class="sp-checklist">
							<li>Администраторов и logged-in cookies</li>
							<li>POST, AJAX, REST, preview и search</li>
							<li>Cart, checkout и WooCommerce sessions</li>
							<li>Password-protected и 404 responses</li>
							<li>Ответы с Set-Cookie или no-store</li>
						</ul>
					</section>
				</aside>
			</div>
		</div>
		<?php
	}

	/** @param array<string,mixed> $settings */
	private function switch_row( string $key, string $title, string $description, array $settings ): void {
		?>
		<label class="sp-switch-row">
			<span><strong><?php echo esc_html( $title ); ?></strong><small><?php echo esc_html( $description ); ?></small></span>
			<input type="checkbox" name="sp_accelerator[<?php echo esc_attr( $key ); ?>]" value="1" <?php checked( ! empty( $settings[ $key ] ) ); ?>>
			<i aria-hidden="true"></i>
		</label>
		<?php
	}
}
