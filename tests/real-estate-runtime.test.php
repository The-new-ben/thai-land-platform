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

function number_format_i18n( $number ) {
	return number_format( (float) $number, 0, '.', ',' );
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
use Thailand_Platform\Content\BangkokRentalExplorer as Content_BangkokRentalExplorer;
use Thailand_Platform\Content\BangkokRentalRepository as Content_BangkokRentalRepository;
use Thailand_Platform\Content\Breadcrumbs as Content_Breadcrumbs;
use Thailand_Platform\Content\Context as Content_Context;
use Thailand_Platform\Content\ContextualLinks as Content_ContextualLinks;
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
tl_test_assert( 'thailand-property-buying-mistakes' === $registry['route_id_by_seo_owner_id']['thailand-property-due-diligence-mistakes'], 'Due-diligence SEO owner bridge mismatch.' );
tl_test_assert( 'bangkok-apartment-rental' === $registry['route_id_by_seo_owner_id']['bangkok-apartment-rental-guide'], 'Bangkok rental SEO owner bridge mismatch.' );
tl_test_assert( 'thailand-property-management' === $registry['route_id_by_seo_owner_id']['property-management-thailand'], 'Property-management SEO owner bridge mismatch.' );
tl_test_assert( 'bangkok-apartment-rental' === Content_Repository::route_by_seo_owner_id( 'bangkok-apartment-rental-guide' )['route_id'], 'SEO owner repository lookup mismatch.' );
$bangkok_registry = Content_BangkokRentalRepository::all();
tl_test_assert( 'bangkok-rental-areas-v1' === $bangkok_registry['contract_id'], 'Bangkok rental registry contract mismatch.' );
tl_test_assert( 50 === count( $bangkok_registry['districts_by_id'] ), 'Bangkok official district count mismatch.' );
tl_test_assert( 10 === count( $bangkok_registry['areas_by_id'] ), 'Bangkok featured area count mismatch.' );

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

ob_start();
Content_ContextualLinks::render_hub_experience( $hub );
$hub_link_markup = ob_get_clean();
foreach (
	array(
		'thailand-property-buying-mistakes' => 'thailand-property-due-diligence-mistakes',
		'bangkok-apartment-rental'         => 'bangkok-apartment-rental-guide',
		'thailand-property-management'     => 'property-management-thailand',
	) as $content_route_id => $seo_owner_id
) {
	tl_test_assert( false !== strpos( $hub_link_markup, 'data-thp-target-route="' . $content_route_id . '"' ), 'Contextual link lost its content route ID.' );
	tl_test_assert( false !== strpos( $hub_link_markup, 'data-thp-target-owner="' . $seo_owner_id . '"' ), 'Contextual link did not emit its canonical SEO owner ID.' );
}

thp_content_set_request( Content_FeatureFlag::MODE_LIVE, 65, '/אפשרויות-משכנתא-ומימון-נכסים-בתאילנד/', 'post' );
$spoke = Content_Context::route();
tl_test_assert( is_array( $spoke ) && 'thailand-property-financing' === $spoke['route_id'], 'Exact spoke did not resolve.' );
tl_test_assert( 3 === count( $spoke['breadcrumbs'] ), 'Spoke breadcrumb depth mismatch.' );

thp_content_set_request( Content_FeatureFlag::MODE_LIVE, 118, '/מדריך-להשכרת-דירה-בבנגקוק/', 'post' );
$bangkok_route = Content_Context::route();
tl_test_assert( is_array( $bangkok_route ) && 'bangkok-apartment-rental' === $bangkok_route['route_id'], 'Bangkok rental route did not resolve.' );
tl_test_assert( Content_Renderer::ready( $bangkok_route ), 'Bangkok rental renderer is not ready.' );
tl_test_assert( false !== strpos( Content_Assets::hero_url( '1717' ), 'bangkok-rental-atlas-v1-1717.webp' ), 'Bangkok rental route did not select its own hero.' );
$bangkok_classes = tl_test_apply_filters( 'body_class', array() );
tl_test_assert( in_array( 'thp-bangkok-rental', $bangkok_classes, true ), 'Bangkok rental body class missing.' );

$GLOBALS['tl_test_styles']       = array();
$GLOBALS['tl_test_scripts']      = array();
$GLOBALS['tl_test_script_data']  = array();
tl_test_do_action( 'wp_enqueue_scripts' );
tl_test_assert( isset( $GLOBALS['tl_test_styles'][ Content_Assets::BANGKOK_STYLE_HANDLE ] ), 'Bangkok rental CSS was not enqueued.' );
tl_test_assert( isset( $GLOBALS['tl_test_scripts'][ Content_Assets::BANGKOK_SCRIPT_HANDLE ] ), 'Bangkok rental JavaScript was not enqueued.' );
tl_test_assert( 'defer' === $GLOBALS['tl_test_script_data'][ Content_Assets::BANGKOK_SCRIPT_HANDLE ]['strategy'], 'Bangkok rental JavaScript is not deferred.' );

ob_start();
Content_BangkokRentalExplorer::render( $bangkok_route );
$bangkok_markup = ob_get_clean();
tl_test_assert( 1 === substr_count( $bangkok_markup, 'data-thp-bkk-explorer' ), 'Bangkok explorer root mismatch.' );
tl_test_assert( 10 === substr_count( $bangkok_markup, 'data-thp-bkk-area ' ), 'Bangkok area card count mismatch.' );
tl_test_assert( 10 === substr_count( $bangkok_markup, 'data-thp-bkk-marker' ), 'Bangkok map marker count mismatch.' );
tl_test_assert( false !== strpos( $bangkok_markup, 'כל 50 המחוזות הרשמיים של בנגקוק' ), 'Bangkok district directory is missing.' );
tl_test_assert( false !== strpos( $bangkok_markup, 'הודעת מגורים TM30' ), 'Bangkok tenant facts omit TM30.' );
tl_test_assert( false !== strpos( $bangkok_markup, 'מס בולים על חוזה שכירות' ), 'Bangkok tenant facts omit stamp duty.' );
tl_test_assert( false === strpos( $bangkok_markup, 'לכרטיס' ), 'Bangkok explorer retained presentation language.' );
tl_test_assert( 1 === substr_count( $bangkok_markup, '<fieldset class="thp-bkk-controls" data-thp-bkk-controls hidden>' ), 'Bangkok filters do not fail closed before enhancement.' );
tl_test_assert( 1 === substr_count( $bangkok_markup, '<legend class="thp-sr-only">סינון אזורי מגורים</legend>' ), 'Bangkok filters lack a semantic group label.' );
tl_test_assert( 1 === substr_count( $bangkok_markup, 'class="thp-bkk-result-bar" data-thp-bkk-result-bar hidden' ), 'Bangkok result status is visible before enhancement.' );
tl_test_assert( 1 === substr_count( $bangkok_markup, 'class="thp-bkk-cost" aria-labelledby="thp-bkk-cost-title" data-thp-bkk-calculator hidden' ), 'Bangkok calculator is visible before enhancement.' );
tl_test_assert( 10 === substr_count( $bangkok_markup, 'aria-pressed="false" disabled' ), 'Bangkok map markers do not fail closed before enhancement.' );
tl_test_assert( false !== strpos( $bangkok_markup, 'id="thp-bkk-budget"' ) && false !== strpos( $bangkok_markup, 'for="thp-bkk-budget"' ) && false !== strpos( $bangkok_markup, 'aria-valuetext="50,000 באט"' ), 'Bangkok budget control lacks its label, output, or value text contract.' );
tl_test_assert( false !== strpos( $bangkok_markup, 'id="thp-bkk-cost-rent"' ) && false !== strpos( $bangkok_markup, 'for="thp-bkk-cost-rent"' ) && false !== strpos( $bangkok_markup, 'aria-valuetext="30,000 באט"' ), 'Bangkok calculator control lacks its label, output, or value text contract.' );
$bangkok_guide_anchor = '<a href="' . esc_url( home_url( '/בנגקוק-תאילנד/' ) ) . '" data-thp-target-owner="bangkok" data-thp-relationship="support">מדריך בנגקוק</a>';
$price_route          = Content_Repository::route_by_id( 'thailand-property-prices' );
$price_anchor         = '<a href="' . esc_url( home_url( $price_route['path'] ) ) . '" data-thp-target-route="thailand-property-prices" data-thp-target-owner="' . esc_attr( $price_route['seo_owner_id'] ) . '" data-thp-relationship="sibling">מחירי נדל״ן בתאילנד</a>';
tl_test_assert( 1 === substr_count( $bangkok_markup, $bangkok_guide_anchor ), 'Bangkok explorer lacks its exact city-guide contextual anchor.' );
tl_test_assert( 1 === substr_count( $bangkok_markup, $price_anchor ), 'Bangkok explorer lacks its exact property-price contextual anchor.' );
foreach ( $presentation_phrases as $presentation_phrase ) {
	tl_test_assert( false === strpos( $bangkok_markup, $presentation_phrase ), 'Bangkok explorer contains presentation language: ' . $presentation_phrase );
}
tl_test_assert( 0 === preg_match( '/[–—]/u', $bangkok_markup ), 'Bangkok explorer contains a forbidden long dash.' );
$bangkok_js = file_get_contents( $root . '/assets/content/bangkok-rental.js' );
tl_test_assert( is_string( $bangkok_js ), 'Bangkok interaction script is unreadable.' );
foreach ( $presentation_phrases as $presentation_phrase ) {
	tl_test_assert( false === strpos( $bangkok_js, $presentation_phrase ), 'Bangkok interaction script contains presentation language: ' . $presentation_phrase );
}
tl_test_assert( 0 === preg_match( '/[–—]/u', $bangkok_js ), 'Bangkok interaction script contains a forbidden long dash.' );
$enhancement_markers = array(
	"markers.forEach((marker) => { marker.disabled = false; });",
	"controls?.removeAttribute('hidden');",
	"resultBar?.removeAttribute('hidden');",
	"calculator?.removeAttribute('hidden');",
);
$interactive_position = strpos( $bangkok_js, "explorer.classList.add('is-interactive');" );
tl_test_assert( false !== $interactive_position, 'Bangkok script never declares successful enhancement.' );
foreach ( $enhancement_markers as $enhancement_marker ) {
	$enhancement_position = strpos( $bangkok_js, $enhancement_marker );
	tl_test_assert( false !== $enhancement_position && $enhancement_position < $interactive_position, 'Bangkok script exposes interactivity before enabling every fail-closed control: ' . $enhancement_marker );
}
tl_test_assert( false !== strpos( $bangkok_js, "window.matchMedia('(prefers-reduced-motion: reduce)').matches" ), 'Bangkok marker navigation ignores reduced-motion preference.' );
tl_test_assert( false !== strpos( $bangkok_js, "budget?.setAttribute('aria-valuetext', formattedBudget);" ), 'Bangkok budget control does not maintain accessible value text.' );
tl_test_assert( false !== strpos( $bangkok_js, "rent?.setAttribute('aria-valuetext', formattedRent);" ), 'Bangkok calculator does not maintain accessible value text.' );

$GLOBALS['thp_content_test_bodies'][118] = '<p>פתיחה שימושית שנשארת.</p><p><strong>דירות להשכרה בבנקוק לפי מחוזות</strong></p><p>רשימת מחוזות ישנה</p><p><strong>דירות מומלצות</strong></p><p>בתאילנד אין חוק שוכרי בית ולכן אין כללים.</p><h2>חלק מעשי</h2><p>תוכן שימושי נוסף.</p>';
Content_Renderer::reset_for_tests();
$upgraded_bangkok_body = Content_Renderer::post_body( $bangkok_route );
tl_test_assert( false !== strpos( $upgraded_bangkok_body, 'פתיחה שימושית שנשארת' ) && false !== strpos( $upgraded_bangkok_body, 'תוכן שימושי נוסף' ), 'Bangkok body cleanup removed useful content.' );
tl_test_assert( false === strpos( $upgraded_bangkok_body, 'רשימת מחוזות ישנה' ), 'Bangkok body retained the replaced district dump.' );
tl_test_assert( false === strpos( $upgraded_bangkok_body, 'אין חוק שוכרי בית' ), 'Bangkok body retained the obsolete tenant-law claim.' );

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
tl_test_assert( false !== strpos( $template, 'data-thp-route-id="<?php echo esc_attr( $route[' . "'route_id'" . '] ); ?>"' ), 'Managed main does not retain its content route ID.' );
tl_test_assert( false !== strpos( $template, 'data-thp-owner-id="<?php echo esc_attr( $route[' . "'seo_owner_id'" . '] ); ?>"' ), 'Managed main does not emit its canonical SEO owner ID.' );
tl_test_assert( false !== strpos( $template, 'wp_head();' ) && false !== strpos( $template, 'wp_footer();' ), 'Managed document omits WordPress integration hooks.' );
tl_test_assert( false !== strpos( $header, '/נדלן-בתאילנד/' ), 'Header does not link the real-estate hub.' );
tl_test_assert( false !== strpos( $footer, '/נדלן-בתאילנד/' ), 'Footer does not link the real-estate hub.' );
tl_test_assert( false !== strpos( $header, 'aria-modal="true"' ), 'Mobile navigation is not declared modal.' );
tl_test_assert( false !== strpos( $header, '<strong>תפריט ראשי</strong>' ), 'Mobile navigation lacks its direct public heading.' );
tl_test_assert( false === strpos( $header, 'לאן ממשיכים?' ), 'Mobile navigation retained generic presentation language.' );
tl_test_assert( false !== strpos( $header, 'class="thp-mobile-nav-backdrop" data-thp-menu-close aria-hidden="true"' ), 'Pointer backdrop is not excluded from sequential navigation.' );
tl_test_assert( false === strpos( $header, '<button class="thp-mobile-nav-backdrop"' ), 'Pointer backdrop remains an unreachable dialog button.' );
tl_test_assert( false !== strpos( $css, '.thp-content' ), 'Managed CSS is not scoped.' );
tl_test_assert( false !== strpos( $css, '.thp-content a { color: var(--thp-forest); }' ), 'Managed links can fall back to the theme accent color.' );
tl_test_assert( false !== strpos( $css, '.thp-content .thp-button-light:visited' ), 'Managed light actions do not preserve contrast after a visit.' );
tl_test_assert( false !== strpos( $css, '.thp-content .thp-menu-toggle:hover' ) && false !== strpos( $css, '.thp-content .thp-menu-toggle:focus' ), 'Theme button states can override the menu control.' );
tl_test_assert( false !== strpos( $css, '.thp-content .thp-menu-toggle' ), 'Menu control is not protected from theme specificity.' );
tl_test_assert( false !== strpos( $css, '.thp-content .thp-menu-toggle { display: none !important; }' ), 'Menu control base hiding rule is missing.' );
tl_test_assert( false !== strpos( $css, '.thp-content .thp-menu-toggle { display: block !important; margin-right: auto; }' ), 'Responsive menu visibility rule is missing.' );
tl_test_assert( false !== strpos( $css, "@media (min-width: 1231px) {\n  .thp-content .thp-menu-toggle { display: none !important; }\n}" ), 'Exact desktop menu breakpoint override is missing.' );
tl_test_assert( 0 === preg_match( '/\.thp-content\s+\.thp-menu-toggle:(?:hover|focus(?:-visible)?|active)[^{]*\{[^}]*\bdisplay\s*:/s', $css ), 'Menu control interaction state can hide the control.' );
tl_test_assert( false !== strpos( $css, '.thp-content .thp-header-search button:hover' ) && false !== strpos( $css, '.thp-content .thp-header-search button:focus-visible' ), 'Header search interaction states can fall back to theme button colors.' );
tl_test_assert( false !== strpos( $css, '.thp-site-header { position: sticky; top: 0; }' ), 'Responsive header does not reserve a persistent dock for accessibility controls.' );
tl_test_assert( false !== strpos( $css, 'html.thp-content-document, body.thp-real-estate, .thp-content { overflow: visible !important; }' ), 'Responsive sticky header remains trapped by a higher-specificity page overflow container.' );
tl_test_assert( false !== strpos( $css, 'body.thp-real-estate.thp-content-menu-open { overflow: hidden !important; }' ), 'Responsive overflow release can defeat the open drawer scroll lock.' );
tl_test_assert( false !== strpos( $css, 'top: 12px !important' ) && false !== strpos( $css, 'left: -180px !important' ) && false !== strpos( $css, 'left: 280px !important' ), 'Responsive accessibility control is not anchored in its reserved header dock.' );
tl_test_assert( false !== strpos( $css, '#pojo-a11y-toolbar.pojo-a11y-toolbar-open { top: 77px !important; left: 12px !important; }' ) && false !== strpos( $css, '#pojo-a11y-toolbar.pojo-a11y-toolbar-open .pojo-a11y-toolbar-toggle { top: -65px !important; left: 88px !important; }' ), 'Open accessibility panel is not positioned below the responsive header while its toggle remains in the reserved dock.' );
tl_test_assert( false !== strpos( $css, 'max-height: calc(100vh - 77px) !important' ) && false !== strpos( $css, 'max-height: calc(100dvh - 77px) !important' ) && false !== strpos( $css, 'overflow-y: auto !important' ), 'Open accessibility panel does not stay inside short responsive viewports.' );
tl_test_assert( false !== strpos( $css, 'body.thp-content-menu-open #pojo-a11y-toolbar' ), 'Open drawer does not hide the external accessibility control.' );
tl_test_assert( false !== strpos( $css, 'body.thp-content-menu-open > :not(.thp-content)' ), 'Open drawer does not suppress top-level external widgets.' );
tl_test_assert( false !== strpos( $js, "matchMedia('(min-width: 1231px)')" ), 'Managed menu breakpoint contract is missing.' );
tl_test_assert( false !== strpos( $js, 'previousFocus = toggle;' ), 'Managed menu does not remember its visible opening control.' );
tl_test_assert( false !== strpos( $js, 'desktop.addListener(closeAtDesktop)' ), 'Managed menu lacks the legacy MediaQueryList listener fallback.' );
tl_test_assert( false !== strpos( $js, 'new MutationObserver' ) && false !== strpos( $js, 'isolationObserver?.disconnect()' ), 'Late external widgets are not isolated and restored with the drawer.' );
tl_test_assert( false !== strpos( $js, "root.querySelector('.thp-brand')?.focus()" ), 'Managed menu does not move focus to the visible desktop header.' );
tl_test_assert( false !== strpos( $js, "event.key === 'Escape'" ), 'Managed menu Escape behavior is missing.' );
tl_test_assert( false !== strpos( $js, 'element.inert = true' ), 'Managed menu does not isolate background controls.' );
tl_test_assert( false !== strpos( $js, "element.setAttribute('aria-hidden', 'true')" ), 'Managed menu does not isolate the background accessibility tree.' );
tl_test_assert( false !== strpos( $js, 'restorePage();' ), 'Managed menu does not restore isolated controls.' );

foreach ( array( $template, $header, $footer, $css, $js ) as $source ) {
	tl_test_assert( false === strpos( $source, chr( 0x2013 ) ) && false === strpos( $source, chr( 0x2014 ) ), 'Managed runtime contains a forbidden long dash.' );
}

fwrite( STDOUT, "PASS: managed real-estate runtime contract\n" );
