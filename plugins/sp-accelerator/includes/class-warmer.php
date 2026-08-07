<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SP_Accelerator_Warmer {
	private const STATE_KEY = 'sp_accelerator_warm_state';
	private const LOCK_KEY  = 'sp_accelerator_warm_lock';
	private const RESTART_KEY = 'sp_accelerator_warm_restart_generation';
	private const CANCEL_KEY = 'sp_accelerator_warm_cancel_epoch';
	private const CRON_HOOK = 'sp_accelerator_warm_batch';
	private const MAX_URLS  = 10000;
	private const LOCK_TTL  = 300;

	/** @var SP_Accelerator_Cache */
	private $cache;

	/** @var SP_Accelerator_Config */
	private $config;

	/** @var bool */
	private $registered = false;

	public function __construct( SP_Accelerator_Cache $cache, ?SP_Accelerator_Config $config = null ) {
		$this->cache  = $cache;
		$this->config = $config instanceof SP_Accelerator_Config ? $config : new SP_Accelerator_Config();
	}

	public function register(): void {
		if ( $this->registered ) {
			return;
		}
		$this->registered = true;
		add_action( self::CRON_HOOK, [ $this, 'process_scheduled_batch' ], 10, 1 );
		add_action( 'sp_accelerator_cache_purged', [ $this, 'queue_after_purge' ] );
	}

	public function queue_after_purge( string $generation ): void {
		if ( ! $this->config->enabled( 'page_cache' ) || ! $this->config->enabled( 'auto_warm' ) ) {
			return;
		}

		$epoch = $this->cancellation_epoch();
		update_option( self::RESTART_KEY, [
			'generation' => $generation,
			'epoch'      => $epoch,
			'token'      => wp_generate_password( 16, false, false ),
		], false );
		$this->schedule_next_batch( $epoch );
	}

	/** @return array<string,mixed> */
	public function state(): array {
		$state = get_option( self::STATE_KEY, [] );
		return is_array( $state ) ? array_merge( [
			'status'     => 'idle',
			'total'      => 0,
			'done'       => 0,
			'failed'     => 0,
			'generation' => '',
			'urls'       => [],
			'queue'      => [],
			'failed_urls'=> [],
			'started_at' => 0,
			'finished_at'=> 0,
		], $state ) : [];
	}

	/** @return array<string,mixed>|WP_Error */
	public function start() {
		if ( ! $this->config->enabled( 'page_cache' ) ) {
			return new WP_Error( 'page_cache_disabled', 'Прогрев недоступен: page cache выключен либо заблокирован безопасностью.' );
		}
		$lock = $this->acquire_lock();
		if ( $lock === '' ) {
			return new WP_Error( 'warm_busy', 'Прогрев уже обновляется другим worker. Повторите через несколько секунд.' );
		}
		$epoch = $this->cancellation_epoch();

		try {
			$urls = $this->discover_urls();
			if ( ! $this->epoch_is_current( $epoch ) || ! $this->config->enabled( 'page_cache' ) ) {
				return new WP_Error( 'warm_cancelled', 'Прогрев был отменён во время поиска URL.' );
			}
			if ( empty( $urls ) ) {
				return new WP_Error( 'no_urls', 'Не найдено публичных URL для прогрева.' );
			}

			if ( ! $this->cache->purge_all() ) {
				return new WP_Error( 'generation_failed', 'Не удалось создать новое поколение кеша. Проверьте запись options и cache config.' );
			}
			if ( ! $this->epoch_is_current( $epoch ) || ! $this->config->enabled( 'page_cache' ) ) {
				return new WP_Error( 'warm_cancelled', 'Прогрев был отменён до сохранения нового поколения.' );
			}
			$generation = (string) $this->config->get( 'generation', '1' );
			$state = [
				'status'      => 'running',
				'total'       => count( $urls ),
				'done'        => 0,
				'failed'      => 0,
				'generation'  => $generation,
				'urls'        => $urls,
				'queue'       => $urls,
				'failed_urls' => [],
				'started_at'  => time(),
				'finished_at' => 0,
			];
			if ( ! $this->epoch_is_current( $epoch ) ) {
				return new WP_Error( 'warm_cancelled', 'Прогрев был отменён до сохранения очереди.' );
			}
			update_option( self::STATE_KEY, $state, false );
			$restart = $this->restart_request();
			if ( (string) ( $restart['generation'] ?? '' ) === $generation ) {
				$this->consume_restart_request( $restart );
			}
		} finally {
			$this->release_lock( $lock );
		}

		// Build one entry immediately so the action has a visible, verified effect.
		$this->process_batch( 1, $epoch );
		return $this->state();
	}

	public function process_scheduled_batch( string $epoch = '' ): void {
		if ( $epoch === '' || ! $this->epoch_is_current( $epoch ) ) {
			return;
		}
		$this->process_batch( 2, $epoch );
	}

	public function process_batch( int $batch_size = 2, string $expected_epoch = '' ): void {
		if ( $expected_epoch !== '' && ! $this->epoch_is_current( $expected_epoch ) ) {
			return;
		}
		if ( ! $this->config->enabled( 'page_cache' ) ) {
			$this->cancel();
			return;
		}
		if ( ! $this->has_pending_work() ) {
			return;
		}
		$lock = $this->acquire_lock();
		if ( $lock === '' ) {
			$this->schedule_next_batch( $this->cancellation_epoch() );
			return;
		}
		$epoch = $expected_epoch !== '' ? $expected_epoch : $this->cancellation_epoch();

		try {
			$this->process_locked_batch( $batch_size, $epoch );
		} finally {
			$this->release_lock( $lock );
		}
	}

	private function process_locked_batch( int $batch_size, string $epoch ): void {
		if ( ! $this->epoch_is_current( $epoch ) ) {
			return;
		}
		$state = $this->state();
		if ( ! in_array( (string) ( $state['status'] ?? '' ), [ 'pending', 'running' ], true ) && ! $this->has_restart_request() ) {
			return;
		}
		$state = $this->restart_if_needed( $state );
		$current_generation = (string) $this->config->get( 'generation', '1' );
		if ( (string) $state['generation'] !== $current_generation ) {
			$state = $this->pending_state( $current_generation );
		}
		if ( $state['status'] === 'pending' ) {
			$urls = $this->discover_urls();
			if ( ! $this->epoch_is_current( $epoch ) || ! $this->config->enabled( 'page_cache' ) ) {
				return;
			}
			if ( empty( $urls ) ) {
				$state['status']      = 'complete';
				$state['finished_at'] = time();
				if ( $this->epoch_is_current( $epoch ) ) {
					update_option( self::STATE_KEY, $state, false );
				}
				return;
			}

			$state['status']     = 'running';
			$state['generation'] = (string) $this->config->get( 'generation', '1' );
			$state['urls']       = $urls;
			$state['queue']      = $urls;
			$state['total']      = count( $urls );
		}
		if ( $state['status'] !== 'running' ) {
			return;
		}

		$queue = isset( $state['queue'] ) && is_array( $state['queue'] ) ? $state['queue'] : [];
		$batch = array_splice( $queue, 0, max( 1, min( 10, $batch_size ) ) );

		foreach ( $batch as $url ) {
			if ( ! $this->epoch_is_current( $epoch ) ) {
				return;
			}
			$response = wp_remote_get( (string) $url, [
				'timeout'     => 12,
				'redirection' => 0,
				'headers'     => [ 'X-SP-Cache-Warm' => $this->config->warm_request_token( (string) $url ) ],
				'user-agent'  => 'SP-Accelerator-Warmup/' . SP_Accelerator_Config::VERSION,
			] );
			if ( ! $this->epoch_is_current( $epoch ) ) {
				return;
			}

			if ( is_wp_error( $response ) ) {
				$state['failed']++;
				if ( count( $state['failed_urls'] ) < 30 ) {
					$state['failed_urls'][] = (string) $url . ' — ' . $response->get_error_message();
				}
				continue;
			}

			$code         = (int) wp_remote_retrieve_response_code( $response );
			$cache_status = strtoupper( trim( (string) wp_remote_retrieve_header( $response, 'x-sp-cache' ) ) );
			if ( $code === 200 && $cache_status === 'MISS' ) {
				$state['done']++;
			} else {
				$state['failed']++;
				if ( count( $state['failed_urls'] ) < 30 ) {
					$reason = $code !== 200 ? 'HTTP ' . $code : 'страница не попала в page cache';
					$state['failed_urls'][] = (string) $url . ' — ' . $reason;
				}
			}
		}

		$state['queue'] = $queue;
		if ( empty( $queue ) ) {
			$state['status']      = 'complete';
			$state['finished_at'] = time();
		} else {
			$state['status'] = 'running';
		}
		$state = $this->restart_if_needed( $state );
		if ( ! $this->epoch_is_current( $epoch ) ) {
			return;
		}
		update_option( self::STATE_KEY, $state, false );

		if ( $state['status'] === 'running' ) {
			$this->schedule_next_batch( $epoch );
		}
	}

	private function schedule_next_batch( ?string $epoch = null ): void {
		$epoch = $epoch ?? $this->cancellation_epoch();
		if ( ! $this->config->enabled( 'page_cache' ) || ! $this->epoch_is_current( $epoch ) ) {
			return;
		}
		$args = [ $epoch ];
		if ( ! wp_next_scheduled( self::CRON_HOOK, $args ) ) {
			wp_schedule_single_event( time() + 5, self::CRON_HOOK, $args );
			if ( ! $this->epoch_is_current( $epoch ) ) {
				wp_clear_scheduled_hook( self::CRON_HOOK, $args );
			}
		}
	}

	public function cancel(): void {
		$epoch = time() . ':' . wp_generate_password( 16, false, false );
		if ( get_option( self::CANCEL_KEY, null ) === null ) {
			if ( ! add_option( self::CANCEL_KEY, $epoch, '', false ) ) {
				update_option( self::CANCEL_KEY, $epoch, false );
			}
		} else {
			update_option( self::CANCEL_KEY, $epoch, false );
		}
		wp_clear_scheduled_hook( self::CRON_HOOK );
		delete_option( self::RESTART_KEY );
		delete_option( self::STATE_KEY );
	}

	private function cancellation_epoch(): string {
		$epoch = (string) get_option( self::CANCEL_KEY, '' );
		if ( $epoch !== '' ) {
			return $epoch;
		}
		$created = time() . ':' . wp_generate_password( 16, false, false );
		if ( add_option( self::CANCEL_KEY, $created, '', false ) ) {
			return $created;
		}
		return (string) get_option( self::CANCEL_KEY, $created );
	}

	private function epoch_is_current( string $epoch ): bool {
		return $epoch !== '' && hash_equals( $epoch, (string) get_option( self::CANCEL_KEY, '' ) );
	}

	private function has_pending_work(): bool {
		$state = $this->state();
		return in_array( (string) ( $state['status'] ?? '' ), [ 'pending', 'running' ], true )
			|| $this->has_restart_request();
	}

	private function acquire_lock(): string {
		$existing = (string) get_option( self::LOCK_KEY, '' );
		if ( $existing !== '' ) {
			$parts = explode( ':', $existing, 2 );
			$time  = isset( $parts[0] ) ? (int) $parts[0] : 0;
			if ( $time > 0 && time() - $time < self::LOCK_TTL ) {
				return '';
			}
			delete_option( self::LOCK_KEY );
		}

		$token = time() . ':' . wp_generate_password( 12, false, false );
		return add_option( self::LOCK_KEY, $token, '', false ) ? $token : '';
	}

	private function release_lock( string $token ): void {
		if ( (string) get_option( self::LOCK_KEY, '' ) === $token ) {
			delete_option( self::LOCK_KEY );
		}
	}

	/** @param array<string,mixed> $state @return array<string,mixed> */
	private function restart_if_needed( array $state ): array {
		$restart = $this->restart_request();
		if ( empty( $restart ) || ! $this->epoch_is_current( (string) ( $restart['epoch'] ?? '' ) ) ) {
			return $state;
		}
		$generation = (string) ( $restart['generation'] ?? '' );
		if ( $generation === (string) ( $state['generation'] ?? '' ) ) {
			$this->consume_restart_request( $restart );
			return $state;
		}

		$state = $this->pending_state( $generation );
		$this->consume_restart_request( $restart );
		return $state;
	}

	private function has_restart_request(): bool {
		return ! empty( $this->restart_request() );
	}

	/** @return array<string,mixed> */
	private function restart_request(): array {
		$raw = get_option( self::RESTART_KEY, '' );
		if ( is_array( $raw )
			&& ! empty( $raw['generation'] )
			&& ! empty( $raw['epoch'] )
			&& ! empty( $raw['token'] ) ) {
			$raw['_raw'] = $raw;
			return $raw;
		}
		if ( is_string( $raw ) && $raw !== '' ) {
			return [
				'generation' => $raw,
				'epoch'      => $this->cancellation_epoch(),
				'token'      => 'legacy:' . hash( 'sha256', $raw ),
				'_raw'       => $raw,
			];
		}
		return [];
	}

	/** @param array<string,mixed> $request */
	private function consume_restart_request( array $request ): void {
		if ( ! array_key_exists( '_raw', $request ) ) {
			return;
		}
		if ( get_option( self::RESTART_KEY, '' ) === $request['_raw'] ) {
			delete_option( self::RESTART_KEY );
		}
	}

	/** @return array<string,mixed> */
	private function pending_state( string $generation ): array {
		return [
			'status'      => 'pending',
			'total'       => 0,
			'done'        => 0,
			'failed'      => 0,
			'generation'  => $generation,
			'urls'        => [],
			'queue'       => [],
			'failed_urls' => [],
			'started_at'  => time(),
			'finished_at' => 0,
		];
	}

	/** @return string[] */
	public function discover_urls(): array {
		$urls = [];
		$this->add_discovered_url( $urls, home_url( '/' ) );

		$post_types = get_post_types( [ 'public' => true ], 'objects' );
		foreach ( $post_types as $post_type ) {
			if ( count( $urls ) >= self::MAX_URLS ) {
				break;
			}
			if ( ! is_object( $post_type ) || $post_type->name === 'attachment' ) {
				continue;
			}

			$remaining = self::MAX_URLS - count( $urls );
			$query = new WP_Query( [
				'post_type'              => $post_type->name,
				'post_status'            => 'publish',
				'posts_per_page'         => max( 1, $remaining ),
				'fields'                 => 'ids',
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'suppress_filters'       => false,
			] );

			foreach ( $query->posts as $post_id ) {
				$url = get_permalink( (int) $post_id );
				if ( is_string( $url ) && $url !== '' ) {
					$this->add_discovered_url( $urls, $url );
				}
				if ( count( $urls ) >= self::MAX_URLS ) {
					break;
				}
			}

			if ( count( $urls ) < self::MAX_URLS && ! empty( $post_type->has_archive ) ) {
				$archive = get_post_type_archive_link( $post_type->name );
				if ( is_string( $archive ) && $archive !== '' ) {
					$this->add_discovered_url( $urls, $archive );
					$counts = wp_count_posts( $post_type->name );
					$this->add_pagination_urls( $urls, $archive, isset( $counts->publish ) ? (int) $counts->publish : 0 );
				}
			}
		}

		$posts_page_id = (int) get_option( 'page_for_posts' );
		if ( $posts_page_id > 0 ) {
			$posts_page = get_permalink( $posts_page_id );
			if ( is_string( $posts_page ) && $posts_page !== '' ) {
				$this->add_discovered_url( $urls, $posts_page );
				$counts = wp_count_posts( 'post' );
				$this->add_pagination_urls( $urls, $posts_page, isset( $counts->publish ) ? (int) $counts->publish : 0 );
			}
		} elseif ( get_option( 'show_on_front' ) === 'posts' ) {
			$counts = wp_count_posts( 'post' );
			$this->add_pagination_urls( $urls, home_url( '/' ), isset( $counts->publish ) ? (int) $counts->publish : 0 );
		}

		$taxonomies = count( $urls ) < self::MAX_URLS ? get_taxonomies( [ 'public' => true ], 'objects' ) : [];
		foreach ( $taxonomies as $taxonomy ) {
			if ( count( $urls ) >= self::MAX_URLS ) {
				break;
			}
			if ( ! is_object( $taxonomy ) || in_array( $taxonomy->name, [ 'post_format', 'nav_menu' ], true ) ) {
				continue;
			}

			$terms = get_terms( [
				'taxonomy'   => $taxonomy->name,
				'hide_empty' => true,
				'number'     => min( 5000, self::MAX_URLS - count( $urls ) ),
			] );
			if ( ! is_array( $terms ) ) {
				continue;
			}
			foreach ( $terms as $term ) {
				$url = get_term_link( $term );
				if ( is_string( $url ) ) {
					$this->add_discovered_url( $urls, $url );
					$this->add_pagination_urls( $urls, $url, isset( $term->count ) ? (int) $term->count : 0 );
				}
				if ( count( $urls ) >= self::MAX_URLS ) {
					break;
				}
			}
		}

		$urls = apply_filters( 'sp_accelerator_warm_urls', array_keys( $urls ) );
		$urls = is_array( $urls ) ? $urls : [];
		$home = wp_parse_url( home_url( '/' ) );
		$host = is_array( $home ) ? strtolower( (string) ( $home['host'] ?? '' ) ) : '';
		$port = is_array( $home ) ? (int) ( $home['port'] ?? 0 ) : 0;
		$clean = [];

		foreach ( $urls as $url ) {
			$url = esc_url_raw( (string) $url );
			$parts = wp_parse_url( $url );
			if ( $url === '' || ! is_array( $parts )
				|| strtolower( (string) ( $parts['host'] ?? '' ) ) !== $host
				|| (int) ( $parts['port'] ?? 0 ) !== $port
				|| ! in_array( strtolower( (string) ( $parts['scheme'] ?? '' ) ), [ 'http', 'https' ], true )
				|| ! empty( $parts['query'] )
			) {
				continue;
			}
			$clean[ $url ] = true;
			if ( count( $clean ) >= self::MAX_URLS ) {
				break;
			}
		}

		return array_keys( $clean );
	}

	/** @param array<string,bool> $urls */
	private function add_pagination_urls( array &$urls, string $base_url, int $items ): void {
		if ( count( $urls ) >= self::MAX_URLS ) {
			return;
		}
		$per_page = max( 1, (int) get_option( 'posts_per_page', 10 ) );
		$pages    = min( 1000, (int) ceil( max( 0, $items ) / $per_page ) );
		if ( $pages < 2 || get_option( 'permalink_structure' ) === '' ) {
			return;
		}

		for ( $page = 2; $page <= $pages; $page++ ) {
			$this->add_discovered_url( $urls, user_trailingslashit( trailingslashit( $base_url ) . 'page/' . $page, 'paged' ) );
			if ( count( $urls ) >= self::MAX_URLS ) {
				break;
			}
		}
	}

	/** @param array<string,bool> $urls */
	private function add_discovered_url( array &$urls, string $url ): void {
		if ( count( $urls ) >= self::MAX_URLS ) {
			return;
		}
		$url = trim( $url );
		if ( $url !== '' ) {
			$urls[ $url ] = true;
		}
	}
}
