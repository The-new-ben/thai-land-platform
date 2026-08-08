<?php
/**
 * Administrator controls for managed real-estate pages.
 *
 * @package Thailand_Platform
 */

namespace Thailand_Platform\Content;

use Thailand_Platform\Homepage\Settings as Homepage_Settings;

final class Settings {
	/**
	 * @return void
	 */
	public function register() {
		add_action( 'admin_init', array( $this, 'register_setting' ) );
		add_action( 'admin_menu', array( $this, 'register_page' ) );
		add_action( 'add_option_' . FeatureFlag::OPTION, array( $this, 'purge_after_change' ), 10, 2 );
		add_action( 'update_option_' . FeatureFlag::OPTION, array( $this, 'purge_after_update' ), 10, 3 );
	}

	/**
	 * @return void
	 */
	public function register_setting() {
		register_setting(
			'thailand_platform_content',
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
			'Thailand Content',
			'Thailand Content',
			'manage_options',
			'thailand-platform-content',
			array( $this, 'render_page' )
		);
	}

	/**
	 * @param mixed $option Option name.
	 * @param mixed $value New value.
	 * @return void
	 */
	public function purge_after_change( $option, $value ) {
		unset( $option, $value );
		Homepage_Settings::purge_caches();
	}

	/**
	 * @param mixed $old_value Previous value.
	 * @param mixed $new_value New value.
	 * @param mixed $option Option name.
	 * @return void
	 */
	public function purge_after_update( $old_value, $new_value, $option ) {
		unset( $option );
		if ( $old_value !== $new_value ) {
			Homepage_Settings::purge_caches();
		}
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
			<h1>Thailand real-estate pages</h1>
			<p>This switch changes presentation only for the eight exact post ID and path bindings in the immutable content registry.</p>
			<form method="post" action="options.php">
				<?php settings_fields( 'thailand_platform_content' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="thailand-platform-real-estate-mode">Real-estate mode</label></th>
						<td>
							<select id="thailand-platform-real-estate-mode" name="<?php echo esc_attr( FeatureFlag::OPTION ); ?>">
								<option value="off" <?php selected( $mode, FeatureFlag::MODE_OFF ); ?>>Off: theme templates</option>
								<option value="live" <?php selected( $mode, FeatureFlag::MODE_LIVE ); ?>>Live: managed templates</option>
							</select>
							<p class="description">The emergency constant <code>THAILAND_PLATFORM_DISABLE_REAL_ESTATE</code> always forces Off.</p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
