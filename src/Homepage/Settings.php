<?php
/**
 * Administrator-only homepage presentation controls.
 *
 * @package Thailand_Platform
 */

namespace Thailand_Platform\Homepage;

final class Settings {
	/**
	 * Register the bounded allowlisted option and settings screen.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_init', array( $this, 'register_setting' ) );
		add_action( 'admin_menu', array( $this, 'register_page' ) );
		add_action( 'add_option_' . FeatureFlag::OPTION, array( $this, 'purge_after_add' ), 10, 2 );
		add_action( 'update_option_' . FeatureFlag::OPTION, array( $this, 'purge_after_update' ), 10, 3 );
	}

	/**
	 * Purge page caches after the first explicit mode is stored.
	 *
	 * @param string $option Option name.
	 * @param mixed  $value  New value.
	 * @return void
	 */
	public function purge_after_add( $option, $value ) {
		unset( $option, $value );
		self::purge_caches();
	}

	/**
	 * Purge page caches after a real mode transition.
	 *
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

		self::purge_caches();
	}

	/**
	 * Clear WordPress object cache and the installed ezCache page cache.
	 *
	 * @return void
	 */
	public static function purge_caches() {
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

	/**
	 * @return void
	 */
	public function register_setting() {
		register_setting(
			'thailand_platform_homepage',
			FeatureFlag::OPTION,
			array(
				'type'              => 'string',
				'default'           => FeatureFlag::MODE_OFF,
				'sanitize_callback' => array( FeatureFlag::class, 'sanitize' ),
			)
		);
	}

	/**
	 * @return void
	 */
	public function register_page() {
		add_options_page(
			'Thailand Platform',
			'Thailand Platform',
			'manage_options',
			'thailand-platform',
			array( $this, 'render_page' )
		);
	}

	/**
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$mode       = FeatureFlag::mode();
		$canary_url = add_query_arg( FeatureFlag::CANARY_QUERY, '1', home_url( '/' ) );
		?>
		<div class="wrap">
			<h1>Thailand Platform</h1>
			<p>Presentation mode changes only the homepage template. It does not alter the configured front page, content, URLs, redirects, or sitemap records.</p>
			<form method="post" action="options.php">
				<?php settings_fields( 'thailand_platform_homepage' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="thailand-platform-homepage-mode">Homepage mode</label></th>
						<td>
							<select id="thailand-platform-homepage-mode" name="<?php echo esc_attr( FeatureFlag::OPTION ); ?>">
								<option value="off" <?php selected( $mode, FeatureFlag::MODE_OFF ); ?>>Off: legacy homepage</option>
								<option value="canary" <?php selected( $mode, FeatureFlag::MODE_CANARY ); ?>>Canary: administrators only</option>
								<option value="live" <?php selected( $mode, FeatureFlag::MODE_LIVE ); ?>>Live: public homepage</option>
							</select>
							<p class="description">The emergency constant <code>THAILAND_PLATFORM_DISABLE_HOMEPAGE</code> always forces Off.</p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
			<?php if ( FeatureFlag::MODE_CANARY === $mode ) : ?>
				<p><a class="button button-secondary" href="<?php echo esc_url( $canary_url ); ?>">Open administrator canary</a></p>
			<?php endif; ?>
		</div>
		<?php
	}
}
