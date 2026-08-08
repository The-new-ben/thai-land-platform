<?php
/**
 * Homepage request authorization and mode handling.
 *
 * @package Thailand_Platform
 */

namespace Thailand_Platform\Homepage;

final class Context {
	/**
	 * Register request-level controls.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'template_redirect', array( $this, 'protect_canary' ), 1 );
	}

	/**
	 * Render only on the unchanged canonical front page.
	 *
	 * @return bool
	 */
	public static function should_render() {
		if ( ! is_front_page() ) {
			return false;
		}

		$mode = FeatureFlag::mode();

		$authorized = FeatureFlag::MODE_LIVE === $mode
			|| ( FeatureFlag::MODE_CANARY === $mode
			&& FeatureFlag::canary_requested()
			&& current_user_can( 'manage_options' ) );

		return $authorized && Renderer::ready();
	}

	/**
	 * Whether this is the private administrator canary response.
	 *
	 * @return bool
	 */
	public static function is_authorized_canary() {
		return FeatureFlag::MODE_CANARY === FeatureFlag::mode()
			&& FeatureFlag::canary_requested()
			&& is_front_page()
			&& current_user_can( 'manage_options' );
	}

	/**
	 * Return unauthorized canary attempts as a normal 404.
	 *
	 * @return void
	 */
	public function protect_canary() {
		if ( ! is_front_page() || ! FeatureFlag::canary_requested() ) {
			return;
		}

		if ( FeatureFlag::MODE_CANARY === FeatureFlag::mode() && current_user_can( 'manage_options' ) ) {
			nocache_headers();
			return;
		}

		global $wp_query;

		if ( is_object( $wp_query ) && method_exists( $wp_query, 'set_404' ) ) {
			$wp_query->set_404();
		}

		status_header( 404 );
		nocache_headers();
	}
}
