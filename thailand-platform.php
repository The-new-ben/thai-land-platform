<?php
/**
 * Plugin Name: Thailand Platform
 * Plugin URI: https://thai-land.co.il/
 * Description: Geography, search, homepage, real-estate, priority guide, and Digital Islands runtime for thai-land.co.il.
 * Version: 0.5.2
 * Requires at least: 6.9
 * Requires PHP: 7.4
 * Author: thai-land.co.il
 * Author URI: https://thai-land.co.il/
 * Text Domain: thailand-platform
 * Update URI: https://thai-land.co.il/
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'THAILAND_PLATFORM_VERSION', '0.5.2' );
define( 'THAILAND_PLATFORM_FILE', __FILE__ );
define( 'THAILAND_PLATFORM_DIR', plugin_dir_path( __FILE__ ) );
define( 'THAILAND_PLATFORM_ENABLE_UPDATE_CHECKER', false );

require_once THAILAND_PLATFORM_DIR . 'src/Support/Compatibility.php';
require_once THAILAND_PLATFORM_DIR . 'src/Support/Loader.php';
require_once THAILAND_PLATFORM_DIR . 'src/Health/Route.php';
require_once THAILAND_PLATFORM_DIR . 'src/Geography/Repository.php';
require_once THAILAND_PLATFORM_DIR . 'src/Geography/Resolver.php';
require_once THAILAND_PLATFORM_DIR . 'src/Geography/Route.php';
require_once THAILAND_PLATFORM_DIR . 'src/Homepage/FeatureFlag.php';
require_once THAILAND_PLATFORM_DIR . 'src/Homepage/Context.php';
require_once THAILAND_PLATFORM_DIR . 'src/Homepage/Assets.php';
require_once THAILAND_PLATFORM_DIR . 'src/Homepage/Seo.php';
require_once THAILAND_PLATFORM_DIR . 'src/Homepage/Renderer.php';
require_once THAILAND_PLATFORM_DIR . 'src/Homepage/Settings.php';
require_once THAILAND_PLATFORM_DIR . 'src/Homepage/Module.php';
require_once THAILAND_PLATFORM_DIR . 'src/Content/Repository.php';
require_once THAILAND_PLATFORM_DIR . 'src/Content/BangkokRentalRepository.php';
require_once THAILAND_PLATFORM_DIR . 'src/Content/BangkokRentalExplorer.php';
require_once THAILAND_PLATFORM_DIR . 'src/Content/FeatureFlag.php';
require_once THAILAND_PLATFORM_DIR . 'src/Content/Context.php';
require_once THAILAND_PLATFORM_DIR . 'src/Content/Assets.php';
require_once THAILAND_PLATFORM_DIR . 'src/Content/Seo.php';
require_once THAILAND_PLATFORM_DIR . 'src/Content/Breadcrumbs.php';
require_once THAILAND_PLATFORM_DIR . 'src/Content/ContextualLinks.php';
require_once THAILAND_PLATFORM_DIR . 'src/Content/Renderer.php';
require_once THAILAND_PLATFORM_DIR . 'src/Content/Settings.php';
require_once THAILAND_PLATFORM_DIR . 'src/Content/Module.php';
require_once THAILAND_PLATFORM_DIR . 'src/Guides/FeatureFlag.php';
require_once THAILAND_PLATFORM_DIR . 'src/Guides/Repository.php';
require_once THAILAND_PLATFORM_DIR . 'src/Guides/Context.php';
require_once THAILAND_PLATFORM_DIR . 'src/Guides/Assets.php';
require_once THAILAND_PLATFORM_DIR . 'src/Guides/HomepageNavigation.php';
require_once THAILAND_PLATFORM_DIR . 'src/Guides/View.php';
require_once THAILAND_PLATFORM_DIR . 'src/Guides/Seo.php';
require_once THAILAND_PLATFORM_DIR . 'src/Guides/Schema.php';
require_once THAILAND_PLATFORM_DIR . 'src/Guides/Renderer.php';
require_once THAILAND_PLATFORM_DIR . 'src/Guides/Settings.php';
require_once THAILAND_PLATFORM_DIR . 'src/Guides/Module.php';
require_once THAILAND_PLATFORM_DIR . 'src/DigitalIslands/StrictJson.php';
require_once THAILAND_PLATFORM_DIR . 'src/DigitalIslands/ArtifactVerifier.php';
require_once THAILAND_PLATFORM_DIR . 'src/DigitalIslands/Repository.php';
require_once THAILAND_PLATFORM_DIR . 'src/DigitalIslands/PublicView.php';
require_once THAILAND_PLATFORM_DIR . 'src/DigitalIslands/FeatureFlag.php';
require_once THAILAND_PLATFORM_DIR . 'src/DigitalIslands/Context.php';
require_once THAILAND_PLATFORM_DIR . 'src/DigitalIslands/Privacy.php';
require_once THAILAND_PLATFORM_DIR . 'src/DigitalIslands/Seo.php';
require_once THAILAND_PLATFORM_DIR . 'src/DigitalIslands/Schema.php';
require_once THAILAND_PLATFORM_DIR . 'src/DigitalIslands/RendererAssets.php';
require_once THAILAND_PLATFORM_DIR . 'src/DigitalIslands/Assets.php';
require_once THAILAND_PLATFORM_DIR . 'src/DigitalIslands/Renderer.php';
require_once THAILAND_PLATFORM_DIR . 'src/DigitalIslands/View.php';
require_once THAILAND_PLATFORM_DIR . 'src/DigitalIslands/RestController.php';
require_once THAILAND_PLATFORM_DIR . 'src/DigitalIslands/HomepageNavigation.php';
require_once THAILAND_PLATFORM_DIR . 'src/DigitalIslands/Settings.php';
require_once THAILAND_PLATFORM_DIR . 'src/DigitalIslands/Module.php';
require_once THAILAND_PLATFORM_DIR . 'src/Updates/Checker.php';

define(
	'THAILAND_PLATFORM_MANIFEST_URL',
	'https://raw.githubusercontent.com/The-new-ben/thai-land-platform/main/release.json'
);

/**
 * Validate the bounded bootstrap release before activation.
 *
 * Activation creates no options, tables, posts, users, or rewrite rules.
 *
 * @return void
 */
function thailand_platform_activate() {
	Thailand_Platform\Support\Compatibility::assert_activation_requirements();
}

/**
 * Purge presentation caches without deleting content or configuration.
 *
 * @return void
 */
function thailand_platform_deactivate() {
	Thailand_Platform\Homepage\Settings::purge_caches();
}

register_activation_hook( THAILAND_PLATFORM_FILE, 'thailand_platform_activate' );
register_deactivation_hook( THAILAND_PLATFORM_FILE, 'thailand_platform_deactivate' );

add_action(
	'plugins_loaded',
	static function () {
		Thailand_Platform\Support\Loader::boot();
	}
);
