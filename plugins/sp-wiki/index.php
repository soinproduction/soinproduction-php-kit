<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'SP_Theme_Wiki', false ) ) {
	final class SP_Theme_Wiki {
		private const PAGE_SLUG = 'sp-wiki';

		/** @var array<string, string> */
		private const PLUGIN_ICONS = [
			'sp-accelerator'          => 'performance',
			'sp-allow-svg-upload'     => 'format-image',
			'sp-cf7'                  => 'email-alt',
			'sp-content-manager'      => 'admin-users',
			'sp-cpt-archives'         => 'archive',
			'sp-dev-mode'             => 'editor-code',
			'sp-favorite-posts'       => 'star-filled',
			'sp-google-reviews'       => 'google',
			'sp-redirects'            => 'randomize',
			'sp-share'                => 'share',
			'sp-tag-manager'          => 'tag',
			'sp-uploads-webp-convert' => 'format-image',
			'sp-video-preview'        => 'video-alt3',
			'sp-wiki'                 => 'book-alt',
		];

		public static function init(): void {
			add_action( 'admin_menu', [ __CLASS__, 'register_page' ] );
			add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
		}

		public static function register_page(): void {
			$copy = self::copy( self::site_language() );

			add_options_page(
				$copy['title'],
				'<span style="display:flex;align-items:center;gap:5px;">
					<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true">
						<path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H11a2 2 0 0 1 2 2v15a2 2 0 0 0-2-2H6.5A2.5 2.5 0 0 0 4 20.5v-15Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
						<path d="M20 5.5A2.5 2.5 0 0 0 17.5 3H13v17a2 2 0 0 1 2-2h2.5a2.5 2.5 0 0 1 2.5 2.5v-15Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
					</svg>
					Wiki
				</span>',
				'manage_options',
				self::PAGE_SLUG,
				[ __CLASS__, 'render_page' ]
			);
		}

		public static function enqueue_assets( string $hook ): void {
			if ( $hook !== 'settings_page_' . self::PAGE_SLUG ) {
				return;
			}

			$base_dir = __DIR__ . '/assets/';
			$base_url = trailingslashit( \SoinProduction\Kit\Bootstrapper::pathToUrl( __DIR__ ) ) . 'assets/';

			wp_enqueue_style(
				'sp-theme-wiki',
				$base_url . 'admin.css',
				[ 'sp-admin-ui' ],
				is_readable( $base_dir . 'admin.css' ) ? (string) filemtime( $base_dir . 'admin.css' ) : null
			);

			wp_enqueue_script(
				'sp-theme-wiki',
				$base_url . 'admin.js',
				[],
				is_readable( $base_dir . 'admin.js' ) ? (string) filemtime( $base_dir . 'admin.js' ) : null,
				true
			);
		}

		public static function render_page(): void {
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}

			$language     = self::site_language();
			$theme_docs   = self::discover_theme_docs( $language );
			$plugin_docs  = self::discover_plugin_docs( $language );
			$catalog      = array_merge( $theme_docs, $plugin_docs );
			$requested_id = isset( $_GET['doc'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['doc'] ) ) : '';
			$current       = self::find_document( $catalog, $requested_id );

			if ( $current === null && $catalog !== [] ) {
				$current = reset( $catalog );
			}

			$locale_label = $language === 'ru' ? 'Русский' : 'English';
			$copy         = self::copy( $language );
			?>
			<div class="wrap sp-admin-page sp-wiki">
				<header class="sp-admin-header">
					<div class="sp-admin-header__identity">
						<span class="sp-admin-header__icon dashicons dashicons-book-alt" aria-hidden="true"></span>
						<div class="sp-admin-header__copy">
							<h1><?php echo esc_html( $copy['title'] ); ?></h1>
							<p><?php echo esc_html( $copy['description'] ); ?></p>
						</div>
					</div>
					<div class="sp-admin-header__actions">
						<span class="sp-wiki__locale"><span class="dashicons dashicons-translation" aria-hidden="true"></span><?php echo esc_html( $locale_label ); ?></span>
					</div>
				</header>

				<div class="sp-wiki__metrics sp-admin-metrics">
					<div class="sp-admin-metric">
						<span><?php echo esc_html( $copy['theme_chapters'] ); ?></span>
						<strong><?php echo esc_html( (string) count( $theme_docs ) ); ?></strong>
						<small><?php echo esc_html( $copy['theme_source'] ); ?></small>
					</div>
					<div class="sp-admin-metric">
						<span><?php echo esc_html( $copy['connected_modules'] ); ?></span>
						<strong><?php echo esc_html( (string) count( $plugin_docs ) ); ?></strong>
						<small><?php echo esc_html( $copy['dynamic_note'] ); ?></small>
					</div>
				</div>

				<div class="sp-wiki__layout">
					<aside class="sp-wiki__sidebar sp-admin-card">
						<label class="sp-wiki__search">
							<span class="dashicons dashicons-search" aria-hidden="true"></span>
							<span class="screen-reader-text"><?php echo esc_html( $copy['search'] ); ?></span>
							<input type="search" data-sp-wiki-search placeholder="<?php echo esc_attr( $copy['search'] ); ?>">
						</label>

						<?php self::render_navigation_group( $copy['theme'], $theme_docs, $current, 'admin-home' ); ?>
						<?php self::render_navigation_group( $copy['modules'], $plugin_docs, $current, 'admin-plugins' ); ?>

						<p class="sp-wiki__empty" data-sp-wiki-empty hidden><?php echo esc_html( $copy['nothing_found'] ); ?></p>
					</aside>

					<main class="sp-wiki__article sp-admin-card">
						<?php if ( $current !== null ) : ?>
							<div class="sp-wiki__article-head sp-admin-card__header">
								<div class="sp-admin-card__copy">
									<p class="sp-wiki__eyebrow"><?php echo esc_html( $current['type'] === 'plugin' ? $copy['connected_module'] : $copy['theme_documentation'] ); ?></p>
									<h2><?php echo esc_html( $current['title'] ); ?></h2>
								</div>
								<code><?php echo esc_html( $current['source'] ); ?></code>
							</div>
							<div class="sp-wiki__markdown">
								<?php echo wp_kses_post( self::render_markdown( $current['content'], $current ) ); ?>
							</div>
						<?php else : ?>
							<div class="sp-wiki__blank">
								<span class="dashicons dashicons-book-alt" aria-hidden="true"></span>
								<h2><?php echo esc_html( $copy['no_docs'] ); ?></h2>
							</div>
						<?php endif; ?>
					</main>
				</div>
			</div>
			<?php
		}

		/**
		 * @param array<int, array<string, string>> $documents
		 * @param array<string, string>|null          $current
		 */
		private static function render_navigation_group( string $label, array $documents, ?array $current, string $icon ): void {
			if ( $documents === [] ) {
				return;
			}
			?>
			<nav class="sp-wiki__nav" aria-label="<?php echo esc_attr( $label ); ?>">
				<h2><span class="dashicons dashicons-<?php echo esc_attr( $icon ); ?>" aria-hidden="true"></span><?php echo esc_html( $label ); ?></h2>
				<ul>
					<?php foreach ( $documents as $document ) :
						$is_current = $current !== null && $current['id'] === $document['id'];
						$doc_icon   = $document['type'] === 'plugin' ? ( self::PLUGIN_ICONS[ $document['slug'] ] ?? 'admin-plugins' ) : 'media-document';
						$url        = add_query_arg( [ 'page' => self::PAGE_SLUG, 'doc' => $document['id'] ], admin_url( 'options-general.php' ) );
						?>
						<li data-sp-wiki-item data-search="<?php echo esc_attr( strtolower( $document['title'] . ' ' . $document['slug'] ) ); ?>">
							<a href="<?php echo esc_url( $url ); ?>"<?php echo $is_current ? ' class="is-current" aria-current="page"' : ''; ?>>
								<span class="dashicons dashicons-<?php echo esc_attr( $doc_icon ); ?>" aria-hidden="true"></span>
								<span><?php echo esc_html( $document['title'] ); ?></span>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			</nav>
			<?php
		}

		private static function site_language(): string {
			$locale = function_exists( 'get_locale' ) ? strtolower( (string) get_locale() ) : 'en_us';
			return str_starts_with( $locale, 'ru' ) ? 'ru' : 'en';
		}

		/** @return array<string, string> */
		private static function copy( string $language ): array {
			if ( $language === 'ru' ) {
				return [
					'title'               => 'Wiki темы',
					'description'         => 'Живая документация темы и всех подключённых кастомных модулей.',
					'theme_chapters'      => 'Главы о теме',
					'theme_source'        => 'Автоматически из docs/ru',
					'connected_modules'   => 'Подключённые модули',
					'dynamic_note'        => 'Список строится по фактически загруженному коду',
					'search'              => 'Поиск по документации…',
					'theme'               => 'Документация темы',
					'modules'             => 'Подключённые модули',
					'nothing_found'       => 'Ничего не найдено.',
					'connected_module'    => 'Подключённый модуль',
					'theme_documentation' => 'Документация темы',
					'no_docs'             => 'Документация пока не добавлена.',
				];
			}

			return [
				'title'               => 'Theme Wiki',
				'description'         => 'Live documentation for the theme and every connected custom module.',
				'theme_chapters'      => 'Theme chapters',
				'theme_source'        => 'Discovered automatically in docs/en',
				'connected_modules'   => 'Connected modules',
				'dynamic_note'        => 'Built from code loaded by the current request',
				'search'              => 'Search documentation…',
				'theme'               => 'Theme documentation',
				'modules'             => 'Connected modules',
				'nothing_found'       => 'Nothing found.',
				'connected_module'    => 'Connected module',
				'theme_documentation' => 'Theme documentation',
				'no_docs'             => 'Documentation has not been added yet.',
			];
		}

		/** @return array<int, array<string, string>> */
		private static function discover_theme_docs( string $language ): array {
			$directory = trailingslashit( defined( 'THEME_DIR' ) ? THEME_DIR : get_template_directory() ) . 'docs/' . $language;
			$files     = glob( $directory . '/*.md' );

			if ( ! is_array( $files ) ) {
				return [];
			}

			natcasesort( $files );
			$documents = [];
			$home      = $directory . '/README.md';

			if ( in_array( $home, $files, true ) ) {
				$files = array_values( array_diff( $files, [ $home ] ) );
				array_unshift( $files, $home );
			}

			foreach ( $files as $file ) {
				$content = self::read_file( $file );
				if ( $content === null ) {
					continue;
				}

				$slug        = pathinfo( $file, PATHINFO_FILENAME );
				$documents[] = [
					'id'      => 'theme:' . $slug,
					'type'    => 'theme',
					'slug'    => $slug,
					'title'   => self::document_title( $content, $slug ),
					'content' => $content,
					'source'  => 'docs/' . $language . '/' . basename( $file ),
				];
			}

			return $documents;
		}

		/** @return array<int, array<string, string>> */
		private static function discover_plugin_docs( string $language ): array {
			$theme_dir = trailingslashit( defined( 'THEME_DIR' ) ? THEME_DIR : get_template_directory() );
			$roots     = [
				[
					'directory' => $theme_dir . 'core/plugins',
					'source'    => 'core/plugins',
				],
				[
					'directory' => $theme_dir . 'vendor/soinproduction/php-kit/plugins',
					'source'    => 'vendor/soinproduction/php-kit/plugins',
				],
			];
			$roots = apply_filters( 'sp_theme_wiki_plugin_roots', $roots );
			$roots = is_array( $roots ) ? $roots : [];

			$included  = array_filter( array_map( 'realpath', get_included_files() ) );
			$documents = [];

			foreach ( $roots as $root ) {
				$plugins_dir = isset( $root['directory'] ) ? (string) $root['directory'] : '';
				$source_root = isset( $root['source'] ) ? trim( (string) $root['source'], '/' ) : '';
				$directories = $plugins_dir !== '' ? glob( trailingslashit( $plugins_dir ) . '*', GLOB_ONLYDIR ) : [];

				if ( ! is_array( $directories ) ) {
					continue;
				}

				natcasesort( $directories );

				foreach ( $directories as $directory ) {
					$slug = basename( $directory );
					if ( $slug === '' || isset( $documents[ $slug ] ) || str_starts_with( $slug, '_' ) || ! is_file( $directory . '/index.php' ) ) {
						continue;
					}

					$real_directory = trailingslashit( (string) realpath( $directory ) );
					$is_connected   = false;
					foreach ( $included as $included_file ) {
						if ( str_starts_with( (string) $included_file, $real_directory ) ) {
							$is_connected = true;
							break;
						}
					}

					if ( ! $is_connected ) {
						continue;
					}

					$localized = $directory . '/README.' . $language . '.md';
					$fallback  = $directory . '/README.en.md';
					$legacy    = $directory . '/README.md';
					$file      = is_readable( $localized ) ? $localized : ( is_readable( $fallback ) ? $fallback : ( is_readable( $legacy ) ? $legacy : '' ) );
					$content   = $file !== '' ? self::read_file( $file ) : null;

					if ( $content === null ) {
						$content = $language === 'ru'
							? '# ' . self::humanize_slug( $slug ) . "\n\nДокументация для подключённого модуля ещё не добавлена. Создайте `README.ru.md` и `README.en.md` рядом с `index.php`."
							: '# ' . self::humanize_slug( $slug ) . "\n\nDocumentation for this connected module has not been added yet. Create `README.ru.md` and `README.en.md` next to `index.php`.";
					}

					$documents[ $slug ] = [
						'id'      => 'plugin:' . $slug,
						'type'    => 'plugin',
						'slug'    => $slug,
						'title'   => self::document_title( $content, self::humanize_slug( $slug ) ),
						'content' => $content,
						'source'  => $source_root . '/' . $slug . '/' . ( $file !== '' ? basename( $file ) : 'README.' . $language . '.md' ),
					];
				}
			}

			uksort( $documents, 'strnatcasecmp' );

			return array_values( $documents );
		}

		/**
		 * @param array<int, array<string, string>> $catalog
		 * @return array<string, string>|null
		 */
		private static function find_document( array $catalog, string $requested_id ): ?array {
			if ( $requested_id === '' ) {
				return null;
			}

			foreach ( $catalog as $document ) {
				if ( hash_equals( $document['id'], $requested_id ) ) {
					return $document;
				}
			}

			return null;
		}

		private static function read_file( string $file ): ?string {
			if ( ! is_readable( $file ) ) {
				return null;
			}

			$content = file_get_contents( $file );
			return is_string( $content ) ? $content : null;
		}

		private static function document_title( string $content, string $fallback ): string {
			if ( preg_match( '/^#\s+(.+)$/m', $content, $match ) === 1 ) {
				return trim( wp_strip_all_tags( $match[1] ) );
			}

			return $fallback;
		}

		private static function humanize_slug( string $slug ): string {
			$slug = preg_replace( '/^sp-/', '', $slug );
			return ucwords( str_replace( '-', ' ', (string) $slug ) );
		}

		/** @param array<string, string> $context */
		private static function render_markdown( string $markdown, array $context ): string {
			$lines = preg_split( '/\r\n|\r|\n/', $markdown );
			if ( ! is_array( $lines ) ) {
				return '';
			}

			$html  = '';
			$count = count( $lines );
			$index = 0;

			while ( $index < $count ) {
				$line = rtrim( $lines[ $index ] );

				if ( trim( $line ) === '' ) {
					$index++;
					continue;
				}

				if ( preg_match( '/^```([a-z0-9_-]*)\s*$/i', trim( $line ), $fence ) === 1 ) {
					$language = sanitize_html_class( $fence[1] );
					$code     = [];
					$index++;
					while ( $index < $count && ! str_starts_with( trim( $lines[ $index ] ), '```' ) ) {
						$code[] = $lines[ $index ];
						$index++;
					}
					$index++;
					$html .= '<pre><code' . ( $language !== '' ? ' class="language-' . esc_attr( $language ) . '"' : '' ) . '>' . esc_html( implode( "\n", $code ) ) . '</code></pre>';
					continue;
				}

				if ( preg_match( '/^(#{1,6})\s+(.+)$/', $line, $heading ) === 1 ) {
					$level = strlen( $heading[1] );
					$text  = trim( $heading[2] );
					$id    = sanitize_title( wp_strip_all_tags( $text ) );
					$html .= '<h' . $level . ( $id !== '' ? ' id="' . esc_attr( $id ) . '"' : '' ) . '>' . self::render_inline( $text, $context ) . '</h' . $level . '>';
					$index++;
					continue;
				}

				if ( preg_match( '/^\s*(?:---+|___+|\*\*\*+)\s*$/', $line ) === 1 ) {
					$html .= '<hr>';
					$index++;
					continue;
				}

				if ( str_starts_with( ltrim( $line ), '>' ) ) {
					$quote = [];
					while ( $index < $count && str_starts_with( ltrim( $lines[ $index ] ), '>' ) ) {
						$quote[] = ltrim( substr( ltrim( $lines[ $index ] ), 1 ) );
						$index++;
					}
					$html .= '<blockquote><p>' . self::render_inline( implode( ' ', $quote ), $context ) . '</p></blockquote>';
					continue;
				}

				if ( preg_match( '/^\s*[-*+]\s+(.+)$/', $line ) === 1 ) {
					$html .= '<ul>';
					while ( $index < $count && preg_match( '/^\s*[-*+]\s+(.+)$/', $lines[ $index ], $item ) === 1 ) {
						$html .= '<li>' . self::render_inline( trim( $item[1] ), $context ) . '</li>';
						$index++;
					}
					$html .= '</ul>';
					continue;
				}

				if ( preg_match( '/^\s*\d+[.)]\s+(.+)$/', $line ) === 1 ) {
					$html .= '<ol>';
					while ( $index < $count && preg_match( '/^\s*\d+[.)]\s+(.+)$/', $lines[ $index ], $item ) === 1 ) {
						$html .= '<li>' . self::render_inline( trim( $item[1] ), $context ) . '</li>';
						$index++;
					}
					$html .= '</ol>';
					continue;
				}

				if ( $index + 1 < $count && str_contains( $line, '|' ) && self::is_table_separator( $lines[ $index + 1 ] ) ) {
					$headers = self::table_cells( $line );
					$index  += 2;
					$html   .= '<div class="sp-wiki__table-wrap"><table><thead><tr>';
					foreach ( $headers as $cell ) {
						$html .= '<th>' . self::render_inline( $cell, $context ) . '</th>';
					}
					$html .= '</tr></thead><tbody>';
					while ( $index < $count && str_contains( $lines[ $index ], '|' ) && trim( $lines[ $index ] ) !== '' ) {
						$html .= '<tr>';
						foreach ( self::table_cells( $lines[ $index ] ) as $cell ) {
							$html .= '<td>' . self::render_inline( $cell, $context ) . '</td>';
						}
						$html .= '</tr>';
						$index++;
					}
					$html .= '</tbody></table></div>';
					continue;
				}

				$paragraph = [ trim( $line ) ];
				$index++;
				while ( $index < $count && trim( $lines[ $index ] ) !== '' && ! self::is_block_start( $lines[ $index ], $lines[ $index + 1 ] ?? '' ) ) {
					$paragraph[] = trim( $lines[ $index ] );
					$index++;
				}
				$html .= '<p>' . self::render_inline( implode( ' ', $paragraph ), $context ) . '</p>';
			}

			return $html;
		}

		private static function is_block_start( string $line, string $next ): bool {
			$trimmed = trim( $line );
			return $trimmed === ''
				|| str_starts_with( $trimmed, '```' )
				|| preg_match( '/^(#{1,6})\s+/', $line ) === 1
				|| preg_match( '/^\s*(?:[-*+]\s+|\d+[.)]\s+|>)/', $line ) === 1
				|| preg_match( '/^\s*(?:---+|___+|\*\*\*+)\s*$/', $line ) === 1
				|| ( str_contains( $line, '|' ) && self::is_table_separator( $next ) );
		}

		private static function is_table_separator( string $line ): bool {
			$cells = self::table_cells( $line );
			if ( $cells === [] ) {
				return false;
			}

			foreach ( $cells as $cell ) {
				if ( preg_match( '/^:?-{3,}:?$/', trim( $cell ) ) !== 1 ) {
					return false;
				}
			}

			return true;
		}

		/** @return array<int, string> */
		private static function table_cells( string $line ): array {
			$line  = trim( $line );
			$line  = trim( $line, '|' );
			$cells = array_map( 'trim', explode( '|', $line ) );
			return array_values( array_filter( $cells, static fn( string $cell ): bool => $cell !== '' ) );
		}

		/** @param array<string, string> $context */
		private static function render_inline( string $text, array $context ): string {
			$tokens = [];
			$store  = static function ( string $html ) use ( &$tokens ): string {
				$key            = '@@SPWIKI' . count( $tokens ) . '@@';
				$tokens[ $key ] = $html;
				return $key;
			};

			$text = preg_replace_callback(
				'/`([^`]+)`/',
				static fn( array $match ): string => $store( '<code>' . esc_html( $match[1] ) . '</code>' ),
				$text
			) ?? $text;

			$text = preg_replace_callback(
				'/\[([^\]]+)\]\(([^)]+)\)/',
				static function ( array $match ) use ( $store, $context ): string {
					$href = self::resolve_markdown_url( trim( $match[2] ), $context );
					if ( $href === '' ) {
						return $match[1];
					}
					$external = preg_match( '#^https?://#i', $href ) === 1;
					return $store( '<a href="' . esc_url( $href ) . '"' . ( $external ? ' target="_blank" rel="noopener noreferrer"' : '' ) . '>' . esc_html( $match[1] ) . '</a>' );
				},
				$text
			) ?? $text;

			$text = esc_html( $text );
			$text = preg_replace( '/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $text ) ?? $text;
			$text = preg_replace( '/(?<!\*)\*([^*]+)\*(?!\*)/', '<em>$1</em>', $text ) ?? $text;

			return strtr( $text, $tokens );
		}

		/** @param array<string, string> $context */
		private static function resolve_markdown_url( string $url, array $context ): string {
			if ( $url === '' ) {
				return '';
			}

			if ( str_starts_with( $url, '#' ) || preg_match( '#^(?:https?://|mailto:)#i', $url ) === 1 ) {
				return $url;
			}

			if ( preg_match( '#(?:^|/)(?:en|ru)/#i', str_replace( '\\', '/', $url ) ) === 1 ) {
				return '';
			}

			$path = (string) wp_parse_url( $url, PHP_URL_PATH );
			if ( strtolower( pathinfo( $path, PATHINFO_EXTENSION ) ) !== 'md' ) {
				return '';
			}

			$slug = pathinfo( basename( $path ), PATHINFO_FILENAME );
			$id   = $context['type'] === 'plugin' ? 'plugin:' . $context['slug'] : 'theme:' . $slug;

			return add_query_arg( [ 'page' => self::PAGE_SLUG, 'doc' => $id ], admin_url( 'options-general.php' ) );
		}
	}

	SP_Theme_Wiki::init();
}
