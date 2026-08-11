<?php
/**
 * Administrator controls for the Digital Islands runtime.
 *
 * @package Thailand_Platform
 */

namespace Thailand_Platform\DigitalIslands;

final class Settings {
	const PAGE_SLUG                    = 'thailand-platform-digital-islands';
	const SAVE_ACTION                  = 'thailand_platform_digital_islands_save';
	const NONCE_ACTION                 = 'thailand_platform_digital_islands_settings';
	const NONCE_NAME                   = 'thailand_platform_digital_islands_nonce';
	const EXPECTED_PUBLIC_ENTITY_COUNT = 49;

	/** @var bool */
	private static $automatic_cache_purge_requested = false;

	/** @return void */
	public function register() {
		add_action( 'admin_init', array( FeatureFlag::class, 'register_setting' ) );
		add_action( 'admin_menu', array( $this, 'register_page' ) );
		add_action( 'admin_post_' . self::SAVE_ACTION, array( $this, 'handle_save' ) );
		foreach ( array( FeatureFlag::OPTION, FeatureFlag::PAGE_ID_OPTION ) as $option ) {
			add_action( 'add_option_' . $option, array( $this, 'purge_after_add' ), 10, 2 );
			add_action( 'update_option_' . $option, array( $this, 'purge_after_update' ), 10, 3 );
		}
	}

	/**
	 * @param string $option Option name.
	 * @param mixed  $value  New value.
	 * @return void
	 */
	public function purge_after_add( $option, $value ) {
		unset( $option, $value );
		self::$automatic_cache_purge_requested = true;
		self::request_cache_purge( 'option_add' );
	}

	/**
	 * @param mixed  $old_value Previous value.
	 * @param mixed  $new_value New value.
	 * @param string $option    Option name.
	 * @return void
	 */
	public function purge_after_update( $old_value, $new_value, $option ) {
		unset( $option );
		if ( $old_value === $new_value ) {
			return;
		}
		self::$automatic_cache_purge_requested = true;
		self::request_cache_purge( 'option_update' );
	}

	/** @return void */
	public function register_page() {
		add_options_page(
			'Thailand Platform Digital Islands',
			'Thailand Platform Digital Islands',
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Save both settings as one fail-closed administrative operation.
	 *
	 * @return void
	 */
	public function handle_save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage Digital Islands.', 'thailand-platform' ), '', array( 'response' => 403 ) );
		}
		$request_method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( (string) wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';
		if ( 'POST' !== $request_method ) {
			wp_die( esc_html__( 'Digital Islands settings accept POST requests only.', 'thailand-platform' ), '', array( 'response' => 405 ) );
		}

		check_admin_referer( self::NONCE_ACTION, self::NONCE_NAME );

		$submitted_mode = isset( $_POST[ FeatureFlag::OPTION ] )
			? wp_unslash( $_POST[ FeatureFlag::OPTION ] )
			: FeatureFlag::MODE_OFF;
		$submitted_page_id = isset( $_POST[ FeatureFlag::PAGE_ID_OPTION ] )
			? wp_unslash( $_POST[ FeatureFlag::PAGE_ID_OPTION ] )
			: 0;

		$mode    = FeatureFlag::sanitize( $submitted_mode );
		$page_id = self::submitted_page_id( $submitted_page_id );
		$page    = self::page_status( $page_id );
		$canary_page = self::canary_page_status( $page_id );
		$artifact = self::artifact_status();
		$notice  = 'saved';

		if ( FeatureFlag::MODE_LIVE === $mode && ( ! $page['ready'] || ! $artifact['public_ready'] ) ) {
			$mode   = FeatureFlag::MODE_OFF;
			$notice = 'live_blocked';
		} elseif ( FeatureFlag::MODE_CANARY === $mode && ( ! $canary_page['ready'] || ! $artifact['canary_ready'] ) ) {
			$mode   = FeatureFlag::MODE_OFF;
			$notice = 'canary_blocked';
		}

		/* Put the runtime Off before changing its identity, then enable it last. */
		update_option( FeatureFlag::OPTION, FeatureFlag::MODE_OFF, false );
		update_option( FeatureFlag::PAGE_ID_OPTION, $page_id, false );
		if ( FeatureFlag::MODE_OFF !== $mode ) {
			update_option( FeatureFlag::OPTION, $mode, false );
		}

		$stored_mode    = get_option( FeatureFlag::OPTION, FeatureFlag::MODE_OFF );
		$stored_page_id = absint( get_option( FeatureFlag::PAGE_ID_OPTION, 0 ) );
		if ( $stored_mode !== $mode || $stored_page_id !== $page_id ) {
			update_option( FeatureFlag::OPTION, FeatureFlag::MODE_OFF, false );
			$notice = 'save_failed';
		}

		$cache = self::$automatic_cache_purge_requested ? 'automatic' : 'not_requested';
		if ( isset( $_POST['thailand_platform_digital_islands_purge_cache'] ) ) {
			self::request_cache_purge( 'administrator_request' );
			$cache = 'manual';
		}

		do_action(
			'thailand_platform_digital_islands_settings_saved',
			array(
				'mode'       => FeatureFlag::mode(),
				'page_id'    => FeatureFlag::page_id(),
				'cache'      => $cache,
				'notice'     => $notice,
			)
		);

		$redirect = add_query_arg(
			array(
				'page'         => self::PAGE_SLUG,
				'thp_di_notice' => $notice,
				'thp_di_cache'  => $cache,
			),
			admin_url( 'options-general.php' )
		);
		wp_safe_redirect( $redirect );
		exit;
	}

	/** @return void */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage Digital Islands.', 'thailand-platform' ), '', array( 'response' => 403 ) );
		}

		$mode     = FeatureFlag::mode();
		$page_id  = FeatureFlag::page_id();
		$page     = self::page_status( $page_id );
		$canary_page = self::canary_page_status( $page_id );
		$artifact = self::artifact_status();
		$live_ready = $page['ready'] && $artifact['public_ready'];
		$canary_ready = $canary_page['ready'] && $artifact['canary_ready'];
		self::render_notice();
		?>
		<div class="wrap">
			<h1>Thailand Platform Digital Islands</h1>
			<p>This controls only the reviewed Koh Phangan map runtime. It does not create, publish, edit, or delete a WordPress page.</p>

			<div class="notice notice-<?php echo $live_ready ? 'success' : 'warning'; ?> inline">
				<p><strong>Live gate: <?php echo $live_ready ? 'ready' : 'blocked'; ?>.</strong>
				<?php if ( ! $live_ready ) : ?> The public template and REST representation remain fail-closed.<?php endif; ?></p>
				<p><strong>Canary gate: <?php echo $canary_ready ? 'ready for an administrator' : 'blocked'; ?>.</strong>
				<?php if ( ! $canary_ready ) : ?> No private Canary template or REST representation can be selected.<?php endif; ?></p>
			</div>

			<h2>Readiness</h2>
			<ul>
				<li><strong>Live page identity:</strong> <?php echo esc_html( $page['message'] ); ?></li>
				<li><strong>Canary page identity:</strong> <?php echo esc_html( $canary_page['message'] ); ?></li>
				<li><strong>Dataset:</strong> <?php echo esc_html( $artifact['message'] ); ?></li>
				<li><strong>Canary projection:</strong> <?php echo esc_html( (string) $artifact['canary_count'] ); ?> reviewed entities.</li>
				<li><strong>Public projection:</strong> <?php echo esc_html( (string) $artifact['public_count'] ); ?> of <?php echo esc_html( (string) self::EXPECTED_PUBLIC_ENTITY_COUNT ); ?> required entities.</li>
			</ul>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::SAVE_ACTION ); ?>">
				<?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="thailand-platform-digital-islands-mode">Runtime mode</label></th>
						<td>
							<select id="thailand-platform-digital-islands-mode" name="<?php echo esc_attr( FeatureFlag::OPTION ); ?>">
								<option value="off" <?php selected( $mode, FeatureFlag::MODE_OFF ); ?>>Off: use the ordinary WordPress route</option>
								<option value="canary" <?php selected( $mode, FeatureFlag::MODE_CANARY ); ?>>Canary: administrators only, noindex and no-store</option>
								<option value="live" <?php selected( $mode, FeatureFlag::MODE_LIVE ); ?>>Live: exact published page and reviewed public data only</option>
							</select>
							<p class="description">THAILAND_PLATFORM_DISABLE_DIGITAL_ISLANDS always forces Off. A blocked Live or Canary request is saved as Off.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="thailand-platform-digital-islands-page-id">Koh Phangan map page ID</label></th>
						<td>
							<input
								type="number"
								min="1"
								step="1"
								class="small-text"
								id="thailand-platform-digital-islands-page-id"
								name="<?php echo esc_attr( FeatureFlag::PAGE_ID_OPTION ); ?>"
								value="<?php echo 0 < $page_id ? esc_attr( (string) $page_id ) : ''; ?>"
							>
							<p class="description">No ID is hardcoded. Live requires this exact object to be a public page at <?php echo esc_html( $page['canonical_path'] ); ?> with an empty stored password.</p>
							<?php if ( $page['ready'] && function_exists( 'get_edit_post_link' ) ) : ?>
								<?php $edit_link = get_edit_post_link( $page_id, '' ); ?>
								<?php if ( is_string( $edit_link ) && '' !== $edit_link ) : ?><p><a href="<?php echo esc_url( $edit_link ); ?>">Edit the bound page</a></p><?php endif; ?>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row">Cache handling</th>
						<td>
							<label><input type="checkbox" name="thailand_platform_digital_islands_purge_cache" value="1"> Request an additional WordPress, LiteSpeed and UPress cache clear</label>
							<p class="description">Mode or page-identity changes always request a purge. This checkbox also purges after a no-change save. Always verify the public result with a cache-busted request after release.</p>
						</td>
					</tr>
				</table>
				<?php submit_button( 'Save Digital Islands settings' ); ?>
			</form>

			<?php if ( FeatureFlag::MODE_CANARY === $mode && $canary_ready ) : ?>
				<p><a class="button" href="<?php echo esc_url( home_url( $page['canonical_path'] ) ); ?>">Open administrator Canary</a></p>
			<?php elseif ( FeatureFlag::MODE_LIVE === $mode && $live_ready ) : ?>
				<p><a class="button" href="<?php echo esc_url( home_url( $page['canonical_path'] ) ); ?>">Open live Koh Phangan map</a></p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * @param mixed $value Submitted option value.
	 * @return int
	 */
	private static function submitted_page_id( $value ) {
		if ( is_int( $value ) ) {
			return FeatureFlag::sanitize_page_id( $value );
		}
		if ( ! is_string( $value ) || 1 !== preg_match( '/\A[0-9]+\z/D', $value ) ) {
			return 0;
		}
		return FeatureFlag::sanitize_page_id( $value );
	}

	/**
	 * @param int $page_id Candidate WordPress page ID.
	 * @return array
	 */
	private static function page_status( $page_id ) {
		$canonical_path = self::canonical_path();
		$status = array(
			'ready'          => false,
			'code'           => 'not_configured',
			'message'        => 'No WordPress page ID is configured.',
			'canonical_path' => $canonical_path,
		);
		if ( 0 >= $page_id ) {
			return $status;
		}
		if ( '' === $canonical_path ) {
			$status['code']    = 'canonical_unavailable';
			$status['message'] = 'The reviewed canonical path is unavailable.';
			return $status;
		}
		if ( 'page' !== get_post_type( $page_id ) ) {
			$status['code']    = 'wrong_type';
			$status['message'] = 'ID ' . $page_id . ' is missing or is not a WordPress page.';
			return $status;
		}
		if ( 'publish' !== get_post_status( $page_id ) ) {
			$status['code']    = 'not_published';
			$status['message'] = 'ID ' . $page_id . ' is not published.';
			return $status;
		}
		$password = get_post_field( 'post_password', $page_id );
		if ( ! is_string( $password ) || '' !== $password || post_password_required( $page_id ) ) {
			$status['code']    = 'password_protected';
			$status['message'] = 'ID ' . $page_id . ' has a stored or active post password.';
			return $status;
		}
		$page_uri = function_exists( 'get_page_uri' ) ? get_page_uri( $page_id ) : '';
		if ( ! is_string( $page_uri ) || Context::stored_page_uri_path( $page_uri ) !== $canonical_path ) {
			$status['code']    = 'wrong_permalink';
			$status['message'] = 'ID ' . $page_id . ' does not own the exact page URI ' . $canonical_path . '.';
			return $status;
		}
		$permalink = get_permalink( $page_id );
		if ( ! is_string( $permalink ) || self::safe_path( $permalink ) !== $canonical_path ) {
			$status['code']    = 'wrong_permalink';
			$status['message'] = 'ID ' . $page_id . ' does not own the exact canonical path ' . $canonical_path . '.';
			return $status;
		}

		$status['ready']   = true;
		$status['code']    = 'ready';
		$status['message'] = 'ID ' . $page_id . ' is the exact published, password-free canonical page.';
		return $status;
	}

	/**
	 * Canary uses the same configured page identity, but the page may remain in
	 * a non-public editorial status while an administrator reviews it.
	 *
	 * @param int $page_id Candidate WordPress page ID.
	 * @return array
	 */
	private static function canary_page_status( $page_id ) {
		$canonical_path = self::canonical_path();
		$status = array(
			'ready'          => false,
			'code'           => 'not_configured',
			'message'        => 'No WordPress page ID is configured.',
			'canonical_path' => $canonical_path,
		);
		if ( 0 >= $page_id ) {
			return $status;
		}
		if ( '' === $canonical_path ) {
			$status['code']    = 'canonical_unavailable';
			$status['message'] = 'The reviewed canonical path is unavailable.';
			return $status;
		}
		if ( 'page' !== get_post_type( $page_id ) ) {
			$status['code']    = 'wrong_type';
			$status['message'] = 'ID ' . $page_id . ' is missing or is not a WordPress page.';
			return $status;
		}
		$status_value = get_post_status( $page_id );
		if ( ! in_array( $status_value, array( 'draft', 'pending', 'private', 'publish' ), true ) ) {
			$status['code']    = 'wrong_status';
			$status['message'] = 'ID ' . $page_id . ' is not in an administrator-reviewable status.';
			return $status;
		}
		$password = get_post_field( 'post_password', $page_id );
		if ( ! is_string( $password ) || '' !== $password ) {
			$status['code']    = 'password_protected';
			$status['message'] = 'ID ' . $page_id . ' has a stored post password.';
			return $status;
		}
		$page_uri = function_exists( 'get_page_uri' ) ? get_page_uri( $page_id ) : '';
		if ( ! is_string( $page_uri ) || Context::stored_page_uri_path( $page_uri ) !== $canonical_path ) {
			$status['code']    = 'wrong_permalink';
			$status['message'] = 'ID ' . $page_id . ' does not own the exact page URI ' . $canonical_path . '.';
			return $status;
		}

		$status['ready']   = true;
		$status['code']    = 'ready';
		$status['message'] = 'ID ' . $page_id . ' is the exact password-free page identity for administrator Canary review.';
		return $status;
	}

	/** @return array */
	private static function artifact_status() {
		$status = array(
			'canary_ready' => false,
			'public_ready' => false,
			'canary_count' => 0,
			'public_count' => 0,
			'message'      => 'The reviewed Digital Islands artifact is unavailable.',
		);
		try {
			$registry = Repository::all();
			$status['canary_count'] = count( $registry['canary_map_entities'] ?? array() );
			$status['public_count'] = count( $registry['public_map_entities'] ?? array() );
			$status['canary_ready'] = Renderer::ready_for_canary()
				&& self::EXPECTED_PUBLIC_ENTITY_COUNT === $status['canary_count'];
			$status['public_ready'] = Renderer::ready_for_public()
				&& ArtifactVerifier::PUBLICATION_STATE_PUBLIC === ( $registry['publication_state'] ?? null )
				&& self::EXPECTED_PUBLIC_ENTITY_COUNT === $status['canary_count']
				&& $status['canary_count'] === $status['public_count'];
			$status['message'] = sprintf(
				'%s (%s), publication state %s.',
				(string) ( $registry['dataset_version'] ?? 'unknown version' ),
				(string) ( $registry['checked_on'] ?? 'unknown review date' ),
				(string) ( $registry['publication_state'] ?? 'unknown' )
			);
		} catch ( \Throwable $exception ) {
			unset( $exception );
		}
		return $status;
	}

	/** @return string */
	private static function canonical_path() {
		try {
			return self::safe_path( Repository::canonical_path() );
		} catch ( \Throwable $exception ) {
			unset( $exception );
			return '';
		}
	}

	/** @param string $url Absolute or relative URL. @return string */
	private static function safe_path( $url ) {
		return Context::safe_url_path( $url );
	}

	/** @return void */
	private static function request_cache_purge( $reason ) {
		do_action( 'thailand_platform_digital_islands_cache_purge_requested', $reason );
		do_action( 'thailand_platform_homepage_cache_purge_requested', 'digital_islands_' . $reason );
		do_action( 'litespeed_purge_all' );
		if ( function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
		}

		$cache_class = '\\Upress\\EzCache\\Cache';
		if ( ! class_exists( $cache_class ) || ! method_exists( $cache_class, 'instance' ) ) {
			return;
		}
		try {
			$cache = call_user_func( array( $cache_class, 'instance' ) );
			if ( is_object( $cache ) && method_exists( $cache, 'clear_cache' ) ) {
				$cache->clear_cache( false );
			}
		} catch ( \Throwable $exception ) {
			unset( $exception );
		}
	}

	/** @return void */
	private static function render_notice() {
		$notice = isset( $_GET['thp_di_notice'] ) ? sanitize_key( wp_unslash( $_GET['thp_di_notice'] ) ) : '';
		$cache  = isset( $_GET['thp_di_cache'] ) ? sanitize_key( wp_unslash( $_GET['thp_di_cache'] ) ) : '';
		$messages = array(
			'saved'          => array( 'success', 'Digital Islands settings were saved.' ),
			'live_blocked'   => array( 'error', 'Live was rejected and the runtime was set Off because the exact page or 49-item public artifact is not ready.' ),
			'canary_blocked' => array( 'error', 'Canary was rejected and the runtime was set Off because its exact page identity, reviewed artifact, or assets are unavailable.' ),
			'save_failed'    => array( 'error', 'The requested settings could not be verified after saving. The runtime was forced Off.' ),
		);
		if ( isset( $messages[ $notice ] ) ) {
			?>
			<div class="notice notice-<?php echo esc_attr( $messages[ $notice ][0] ); ?> is-dismissible"><p><?php echo esc_html( $messages[ $notice ][1] ); ?></p></div>
			<?php
		}
		if ( in_array( $cache, array( 'automatic', 'manual' ), true ) ) {
			?>
			<div class="notice notice-info is-dismissible"><p>WordPress, homepage, LiteSpeed and UPress cache-purge hooks were <?php echo 'automatic' === $cache ? 'automatically ' : ''; ?>requested. This is not a substitute for cache-busted public verification.</p></div>
			<?php
		}
	}
}
