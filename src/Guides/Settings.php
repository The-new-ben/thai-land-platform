<?php
/**
 * Administrator controls for the isolated priority guides runtime.
 *
 * @package Thailand_Platform
 */

namespace Thailand_Platform\Guides;

final class Settings {
	/**
	 * @return void
	 */
	public function register() {
		add_action( 'admin_init', array( $this, 'register_setting' ) );
		add_action( 'admin_menu', array( $this, 'register_page' ) );
		add_action( 'add_option_' . FeatureFlag::OPTION, array( $this, 'purge_after_add' ), 10, 2 );
		add_action( 'update_option_' . FeatureFlag::OPTION, array( $this, 'purge_after_update' ), 10, 3 );
	}

	/**
	 * @param string $option Option name.
	 * @param mixed  $value New value.
	 * @return void
	 */
	public function purge_after_add( $option, $value ) {
		unset( $option, $value );
		self::purge_caches();
	}

	/**
	 * @param mixed  $old_value Previous value.
	 * @param mixed  $new_value New value.
	 * @param string $option Option name.
	 * @return void
	 */
	public function purge_after_update( $old_value, $new_value, $option ) {
		unset( $option );
		if ( $old_value !== $new_value ) {
			self::purge_caches();
		}
	}

	/**
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
			'thailand_platform_guides',
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
			'Thailand Platform Guides',
			'Thailand Platform Guides',
			'manage_options',
			'thailand-platform-guides',
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
		$mode = FeatureFlag::mode();
		?>
		<div class="wrap">
			<h1>Thailand Platform Guides</h1>
			<p>This mode controls only the seven exact guide identities. Stored WordPress bodies remain unchanged.</p>
			<form method="post" action="options.php">
				<?php settings_fields( 'thailand_platform_guides' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="thailand-platform-guides-mode">Guides mode</label></th>
						<td>
							<select id="thailand-platform-guides-mode" name="<?php echo esc_attr( FeatureFlag::OPTION ); ?>">
								<option value="off" <?php selected( $mode, FeatureFlag::MODE_OFF ); ?>>Off: WordPress templates</option>
								<option value="canary" <?php selected( $mode, FeatureFlag::MODE_CANARY ); ?>>Canary: administrators with exact links</option>
								<option value="live" <?php selected( $mode, FeatureFlag::MODE_LIVE ); ?>>Live: published identities only</option>
							</select>
							<p class="description">THAILAND_PLATFORM_DISABLE_GUIDES always forces Off.</p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>

			<?php if ( FeatureFlag::MODE_CANARY === $mode ) : ?>
				<h2>Administrator canary links</h2>
				<ul>
					<?php foreach ( Repository::all()['routes_by_id'] ?? array() as $route ) : ?>
						<li><a href="<?php echo esc_url( Context::canary_url( $route ) ); ?>"><?php echo esc_html( $route['public']['h1'] ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
		<?php
	}
}
