<?php
/**
 * Dependency-free managed real-estate runtime tests.
 */

$GLOBALS['thp_content_test_singular'] = false;
$GLOBALS['thp_content_test_preview']  = false;
$GLOBALS['thp_content_test_feed']     = false;
$GLOBALS['thp_content_test_embed']    = false;
$GLOBALS['thp_content_test_post_id']  = 0;
$GLOBALS['thp_content_test_types']    = array();
$GLOBALS['thp_content_test_statuses'] = array();
$GLOBALS['thp_content_test_bodies']   = array();
$GLOBALS['thp_content_test_passwords'] = array();

function is_singular() {
	return (bool) $GLOBALS['thp_content_test_singular'];
}

function is_preview() {
	return (bool) $GLOBALS['thp_content_test_preview'];
}

function is_feed() {
	return (bool) $GLOBALS['thp_content_test_feed'];
}

function is_embed() {
	return (bool) $GLOBALS['thp_content_test_embed'];
}

function get_queried_object_id() {
	return (int) $GLOBALS['thp_content_test_post_id'];
}

function get_post_type( $post_id ) {
	return $GLOBALS['thp_content_test_types'][ (int) $post_id ] ?? null;
}

function get_post_status( $post_id ) {
	return $GLOBALS['thp_content_test_statuses'][ (int) $post_id ] ?? null;
}

function post_password_required( $post_id = 0 ) {
	return (bool) ( $GLOBALS['thp_content_test_passwords'][ (int) $post_id ] ?? false );
}

function get_post_field( $field, $post_id ) {
	if ( 'post_content' !== $field ) {
		return null;
	}
	return $GLOBALS['thp_content_test_bodies'][ (int) $post_id ] ?? null;
}

function absint( $value ) {
	return abs( (int) $value );
}

function wp_parse_url( $url, $component = -1 ) {
	return parse_url( $url, $component );
}

function home_url( $path = '/' ) {
	return 'https://example.test/' . ltrim( (string) $path, '/' );
}

function apply_filters( $hook, $value ) {
	$arguments = array_slice( func_get_args(), 2 );
	return tl_test_apply_filters( $hook, $value, ...$arguments );
}

require __DIR__ . '/run.php';

use Thailand_Platform\Content\Assets as Content_Assets;
use Thailand_Platform\Content\Breadcrumbs as Content_Breadcrumbs;
use Thailand_Platform\Content\Context as Content_Context;
use Thailand_Platform\Content\FeatureFlag as Content_FeatureFlag;
use Thailand_Platform\Content\Renderer as Content_Renderer;
use Thailand_Platform\Content\Repository as Content_Repository;
use Thailand_Platform\Content\Seo as Content_Seo;

function thp_content_set_request( $mode, $post_id, $path, $post_type = 'post', $status = 'publish', $password_required = false ) {
	$GLOBALS['tl_test_options'][ Content_FeatureFlag::OPTION ] = $mode;
	$GLOBALS['thp_content_test_singular'] = true;
	$GLOBALS['thp_content_test_preview']  = false;
	$GLOBALS['thp_content_test_feed']     = false;
	$GLOBALS['thp_content_test_embed']    = false;
	$GLOBALS['thp_content_test_post_id']  = $post_id;
	$GLOBALS['thp_content_test_types'][ $post_id ] = $post_type;
	$GLOBALS['thp_content_test_statuses'][ $post_id ] = $status;
	$GLOBALS['thp_content_test_passwords'][ $post_id ] = $password_required;
	$GLOBALS['thp_content_test_bodies'][ $post_id ] = '<p>תוכן קיים נשמר.</p><h2>כותרת משנה</h2><p>המשך שימושי.</p>';
	$_SERVER['REQUEST_URI'] = $path;
	Content_Context::reset_for_tests();
	Content_Renderer::reset_for_tests();
}

$registry = Content_Repository::all();
tl_test_assert( 'thailand-real-estate-v1' === $registry['contract_id'], 'Content registry contract mismatch.' );
tl_test_assert( 8 === count( $registry['routes_by_id'] ), 'Managed route count mismatch.' );
tl_test_assert( 'thailand-real-estate' === $registry['route_id_by_post_id']['841'], 'Hub post ID binding mismatch.' );

thp_content_set_request( Content_FeatureFlag::MODE_LIVE, 841, '/%D7%A0%D7%93%D7%9C%D7%9F-%D7%91%D7%AA%D7%90%D7%99%D7%9C%D7%A0%D7%93/?source=test', 'page' );
$hub = Content_Context::route();
tl_test_assert( is_array( $hub ) && 'thailand-real-estate' === $hub['route_id'], 'Encoded hub route did not resolve.' );
tl_test_assert( Content_Renderer::ready( $hub ), 'Managed hub renderer is not ready.' );
tl_test_assert(
	THAILAND_PLATFORM_DIR . 'templates/content-document.php' === tl_test_apply_filters( 'template_include', 'legacy.php' ),
	'Managed hub template was not selected.'
);

$seo_title = $hub['public']['seo_title'] . ' | Thai-Land.co.il';
tl_test_assert( $seo_title === tl_test_apply_filters( 'wpseo_title', 'Legacy title' ), 'Managed title mismatch.' );
tl_test_assert( $hub['public']['meta_description'] === tl_test_apply_filters( 'wpseo_metadesc', 'Legacy description' ), 'Managed description mismatch.' );
tl_test_assert( home_url( $hub['path'] ) === tl_test_apply_filters( 'wpseo_canonical', 'legacy' ), 'Managed canonical mismatch.' );
tl_test_assert( Content_Assets::hero_url( '1717' ) === tl_test_apply_filters( 'wpseo_opengraph_image', 'legacy' ), 'Managed social image mismatch.' );
tl_test_assert( '1717' === tl_test_apply_filters( 'wpseo_opengraph_image_width', '0' ), 'Managed social image width mismatch.' );
tl_test_assert( '916' === tl_test_apply_filters( 'wpseo_opengraph_image_height', '0' ), 'Managed social image height mismatch.' );
tl_test_assert( 'index, follow, max-image-preview:large' === tl_test_apply_filters( 'wpseo_robots', 'noindex, follow' ), 'Managed robots mismatch.' );
$robots = tl_test_apply_filters( 'wp_robots', array( 'noindex' => true, 'nofollow' => true ) );
tl_test_assert( ! isset( $robots['noindex'] ) && ! isset( $robots['nofollow'] ), 'Managed route retained blocking robots.' );
tl_test_assert( true === $robots['index'] && true === $robots['follow'], 'Managed route lacks index/follow.' );
$classes = tl_test_apply_filters( 'body_class', array() );
tl_test_assert( in_array( 'thp-real-estate', $classes, true ), 'Managed root class missing.' );
tl_test_assert( in_array( 'thp-real-estate-hub', $classes, true ), 'Managed hub class missing.' );

$GLOBALS['tl_test_styles']      = array();
$GLOBALS['tl_test_scripts']     = array();
$GLOBALS['tl_test_script_data'] = array();
tl_test_do_action( 'wp_enqueue_scripts' );
tl_test_assert( isset( $GLOBALS['tl_test_styles'][ Content_Assets::STYLE_HANDLE ] ), 'Managed CSS was not enqueued.' );
tl_test_assert( isset( $GLOBALS['tl_test_scripts'][ Content_Assets::SCRIPT_HANDLE ] ), 'Managed JavaScript was not enqueued.' );
tl_test_assert( THAILAND_PLATFORM_VERSION === $GLOBALS['tl_test_styles'][ Content_Assets::STYLE_HANDLE ]['version'], 'Managed CSS version mismatch.' );
tl_test_assert( 'defer' === $GLOBALS['tl_test_script_data'][ Content_Assets::SCRIPT_HANDLE ]['strategy'], 'Managed JavaScript is not deferred.' );

ob_start();
Content_Breadcrumbs::render( $hub );
$breadcrumb_markup = ob_get_clean();
tl_test_assert( 1 === substr_count( $breadcrumb_markup, 'data-thp-breadcrumbs' ), 'Visible breadcrumb is missing or duplicated.' );
tl_test_assert( 1 === substr_count( $breadcrumb_markup, 'aria-current="page"' ), 'Current breadcrumb marker mismatch.' );

thp_content_set_request( Content_FeatureFlag::MODE_LIVE, 65, '/אפשרויות-משכנתא-ומימון-נכסים-בתאילנד/', 'post' );
$spoke = Content_Context::route();
tl_test_assert( is_array( $spoke ) && 'thailand-property-financing' === $spoke['route_id'], 'Exact spoke did not resolve.' );
tl_test_assert( 3 === count( $spoke['breadcrumbs'] ), 'Spoke breadcrumb depth mismatch.' );

thp_content_set_request( Content_FeatureFlag::MODE_LIVE, 66, '/אפשרויות-משכנתא-ומימון-נכסים-בתאילנד/', 'post' );
tl_test_assert( null === Content_Context::route(), 'Wrong post ID matched a managed route.' );
thp_content_set_request( Content_FeatureFlag::MODE_LIVE, 65, '/אפשרויות-משכנתא-ומימון-נכסים-בתאילנד-extra/', 'post' );
tl_test_assert( null === Content_Context::route(), 'Near-prefix path matched a managed route.' );
thp_content_set_request( Content_FeatureFlag::MODE_LIVE, 65, '/אפשרויות-משכנתא-ומימון-נכסים-בתאילנד', 'post' );
tl_test_assert( null === Content_Context::route(), 'Missing trailing slash bypassed exact route identity.' );
thp_content_set_request( Content_FeatureFlag::MODE_LIVE, 65, '//אפשרויות-משכנתא-ומימון-נכסים-בתאילנד/', 'post' );
tl_test_assert( null === Content_Context::route(), 'Repeated leading slash bypassed exact route identity.' );
thp_content_set_request( Content_FeatureFlag::MODE_LIVE, 65, '/%2Fאפשרויות-משכנתא-ומימון-נכסים-בתאילנד/', 'post' );
tl_test_assert( null === Content_Context::route(), 'Encoded path separator bypassed exact route identity.' );
thp_content_set_request( Content_FeatureFlag::MODE_LIVE, 65, '/אפשרויות-משכנתא-ומימון-נכסים-בתאילנד%2F', 'post' );
tl_test_assert( null === Content_Context::route(), 'Encoded trailing separator bypassed exact route identity.' );
thp_content_set_request( Content_FeatureFlag::MODE_LIVE, 65, '/אפשרויות-משכנתא-ומימון-נכסים-בתאילנד%5C', 'post' );
tl_test_assert( null === Content_Context::route(), 'Encoded backslash bypassed exact route identity.' );
thp_content_set_request( Content_FeatureFlag::MODE_LIVE, 65, '/אפשרויות-משכנתא-ומימון-נכסים-בתאילנד/', 'page' );
tl_test_assert( null === Content_Context::route(), 'Wrong post type matched a managed route.' );
thp_content_set_request( Content_FeatureFlag::MODE_LIVE, 65, '/אפשרויות-משכנתא-ומימון-נכסים-בתאילנד/', 'post', 'draft' );
tl_test_assert( null === Content_Context::route(), 'Draft post rendered publicly.' );
thp_content_set_request( Content_FeatureFlag::MODE_LIVE, 65, '/אפשרויות-משכנתא-ומימון-נכסים-בתאילנד/', 'post', 'publish', true );
tl_test_assert( null === Content_Context::route(), 'Password-protected post rendered through the managed template.' );
thp_content_set_request( Content_FeatureFlag::MODE_OFF, 65, '/אפשרויות-משכנתא-ומימון-נכסים-בתאילנד/', 'post' );
tl_test_assert( null === Content_Context::route(), 'Off mode resolved managed content.' );
tl_test_assert( 'Legacy' === tl_test_apply_filters( 'wpseo_title', 'Legacy' ), 'Off mode changed an SEO title.' );

thp_content_set_request( Content_FeatureFlag::MODE_LIVE, 65, '/אפשרויות-משכנתא-ומימון-נכסים-בתאילנד/', 'post' );
$GLOBALS['thp_content_test_preview'] = true;
Content_Context::reset_for_tests();
tl_test_assert( null === Content_Context::route(), 'Preview request rendered managed content.' );

$GLOBALS['thp_content_test_preview'] = false;
$GLOBALS['thp_content_test_bodies'][65] = '<h1>כותרת מתחרה</h1><p>תוכן</p>';
Content_Context::reset_for_tests();
Content_Renderer::reset_for_tests();
tl_test_assert( ! Content_Renderer::ready( Content_Context::route() ), 'Stored H1 did not fail closed.' );

$GLOBALS['thp_content_test_bodies'][65] = '<main><p>תוכן ראשי מתחרה</p></main>';
Content_Context::reset_for_tests();
Content_Renderer::reset_for_tests();
tl_test_assert( ! Content_Renderer::ready( Content_Context::route() ), 'Stored main landmark did not fail closed.' );

$GLOBALS['thp_content_test_bodies'][65] = '<p>מקף &#x2013; ארוך &mdash; אינו נשאר בתוכן הציבורי.</p>';
Content_Context::reset_for_tests();
Content_Renderer::reset_for_tests();
$normalized_body = Content_Renderer::post_body( Content_Context::route() );
tl_test_assert( 0 === preg_match( '/[\x{2013}\x{2014}]/u', $normalized_body ), 'Rendered body retained a forbidden long dash.' );
tl_test_assert( false === stripos( $normalized_body, '&ndash;' ) && false === stripos( $normalized_body, '&mdash;' ), 'Rendered body retained a forbidden long-dash entity.' );

$root = dirname( __DIR__ );
$template = file_get_contents( $root . '/templates/content-document.php' );
$header   = file_get_contents( $root . '/templates/partials/content-header.php' );
$footer   = file_get_contents( $root . '/templates/partials/content-footer.php' );
$css      = file_get_contents( $root . '/assets/content/content.css' );
$js       = file_get_contents( $root . '/assets/content/content.js' );
tl_test_assert( 1 === preg_match_all( '/<h1\b/i', $template ), 'Managed template does not own exactly one H1.' );
tl_test_assert( 1 === preg_match_all( '/<main\b/i', $template ), 'Managed template does not own exactly one main.' );
tl_test_assert( false !== strpos( $template, 'wp_head();' ) && false !== strpos( $template, 'wp_footer();' ), 'Managed document omits WordPress integration hooks.' );
tl_test_assert( false !== strpos( $header, '/נדלן-בתאילנד/' ), 'Header does not link the real-estate hub.' );
tl_test_assert( false !== strpos( $footer, '/נדלן-בתאילנד/' ), 'Footer does not link the real-estate hub.' );
tl_test_assert( false !== strpos( $header, 'aria-modal="true"' ), 'Mobile navigation is not declared modal.' );
tl_test_assert( false !== strpos( $css, '.thp-content' ), 'Managed CSS is not scoped.' );
tl_test_assert( false !== strpos( $css, 'body.thp-content-menu-open #pojo-a11y-toolbar' ), 'Open drawer does not hide the external accessibility control.' );
tl_test_assert( false !== strpos( $js, "matchMedia('(min-width: 1231px)')" ), 'Managed menu breakpoint contract is missing.' );
tl_test_assert( false !== strpos( $js, "root.querySelector('.thp-brand')?.focus()" ), 'Managed menu does not move focus to the visible desktop header.' );
tl_test_assert( false !== strpos( $js, "event.key === 'Escape'" ), 'Managed menu Escape behavior is missing.' );
tl_test_assert( false !== strpos( $js, 'element.inert = true' ), 'Managed menu does not isolate background controls.' );
tl_test_assert( false !== strpos( $js, "element.setAttribute('aria-hidden', 'true')" ), 'Managed menu does not isolate the background accessibility tree.' );
tl_test_assert( false !== strpos( $js, 'restorePage();' ), 'Managed menu does not restore isolated controls.' );

foreach ( array( $template, $header, $footer, $css, $js ) as $source ) {
	tl_test_assert( false === strpos( $source, chr( 0x2013 ) ) && false === strpos( $source, chr( 0x2014 ) ), 'Managed runtime contains a forbidden long dash.' );
}

fwrite( STDOUT, "PASS: managed real-estate runtime contract\n" );
