<?php
/**
 * Theme-independent managed content document.
 *
 * @package Thailand_Platform
 */

use Thailand_Platform\Content\Assets;
use Thailand_Platform\Content\BangkokRentalExplorer;
use Thailand_Platform\Content\Breadcrumbs;
use Thailand_Platform\Content\Context;
use Thailand_Platform\Content\ContextualLinks;
use Thailand_Platform\Content\Renderer;
use Thailand_Platform\Content\Repository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$route = Context::route();
if ( ! is_array( $route ) || ! Renderer::ready( $route ) ) {
	return;
}

$body   = Renderer::post_body( $route );
$is_hub = 'hub' === $route['kind'];
$is_bangkok_rental = 'bangkok-apartment-rental' === $route['route_id'];
$parent_route = is_array( $route['parent_link'] ?? null )
	? Repository::route_by_id( $route['parent_link']['target_route_id'] )
	: null;
$hub_route = Repository::route_by_id( Repository::all()['hub_route_id'] ?? '' );
?>
<!doctype html>
<html <?php language_attributes(); ?> class="thp-content-document">
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="theme-color" content="#0b3f3c">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
	<?php if ( function_exists( 'wp_body_open' ) ) { wp_body_open(); } ?>
	<div class="thp-content">
		<?php require THAILAND_PLATFORM_DIR . 'templates/partials/content-header.php'; ?>
		<main id="main-content" data-thp-route-id="<?php echo esc_attr( $route['route_id'] ); ?>" data-thp-owner-id="<?php echo esc_attr( $route['seo_owner_id'] ); ?>">
			<div class="thp-shell thp-breadcrumb-wrap"><?php Breadcrumbs::render( $route ); ?></div>
			<section class="thp-content-hero<?php echo $is_hub ? ' is-hub' : ' is-spoke'; ?>" aria-labelledby="thp-page-title">
				<picture class="thp-hero-art" aria-hidden="true">
					<source media="(max-width: 720px)" srcset="<?php echo esc_url( Assets::hero_url( '720' ) ); ?>">
					<source media="(max-width: 1279px)" srcset="<?php echo esc_url( Assets::hero_url( '1200' ) ); ?>">
					<img src="<?php echo esc_url( Assets::hero_url( '1717' ) ); ?>" alt="" width="1717" height="916" fetchpriority="high">
				</picture>
				<div class="thp-hero-shade"></div>
				<div class="thp-shell thp-hero-inner">
					<div class="thp-hero-copy">
						<p class="thp-kicker"><?php echo esc_html( $is_hub ? 'נדל״ן בתאילנד' : ( $is_bangkok_rental ? 'מגורים ושכירות בבנגקוק' : 'מדריך נדל״ן ממוקד' ) ); ?></p>
						<h1 id="thp-page-title"><?php echo esc_html( $route['public']['h1'] ); ?></h1>
						<p class="thp-hero-summary"><?php echo esc_html( $route['public']['summary'] ); ?></p>
						<?php if ( ! $is_hub && is_array( $parent_route ) ) : ?>
							<a class="thp-button thp-button-light" href="<?php echo esc_url( home_url( $parent_route['path'] ) ); ?>" data-thp-target-route="<?php echo esc_attr( $parent_route['route_id'] ); ?>" data-thp-target-owner="<?php echo esc_attr( $parent_route['seo_owner_id'] ); ?>" data-thp-relationship="parent_hub"><?php echo esc_html( $route['parent_link']['label'] ); ?></a>
						<?php endif; ?>
					</div>
					<?php if ( $is_hub ) : ?>
						<div class="thp-hero-facts" aria-label="מה תמצאו במדריך">
							<div><strong>7</strong><span>מדריכי עומק</span></div>
							<div><strong>3</strong><span>מסלולי החלטה</span></div>
							<div><strong>1</strong><span>נקודת התחלה</span></div>
						</div>
					<?php endif; ?>
				</div>
			</section>

			<div class="thp-shell thp-content-flow">
				<?php if ( $is_hub ) : ?>
					<?php ContextualLinks::render_hub_experience( $route ); ?>
					<article class="thp-prose thp-hub-article" data-thp-preserved-body>
						<?php echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</article>
				<?php else : ?>
					<?php BangkokRentalExplorer::render( $route ); ?>
					<div class="thp-article-layout">
						<article class="thp-prose" data-thp-preserved-body>
							<?php echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</article>
						<aside class="thp-article-aside">
							<nav class="thp-toc" aria-label="תוכן העמוד"><strong>בעמוד הזה</strong><ol data-thp-toc></ol></nav>
							<?php if ( is_array( $hub_route ) ) : ?>
								<a class="thp-aside-hub-link" href="<?php echo esc_url( home_url( $hub_route['path'] ) ); ?>" data-thp-target-route="<?php echo esc_attr( $hub_route['route_id'] ); ?>" data-thp-target-owner="<?php echo esc_attr( $hub_route['seo_owner_id'] ); ?>" data-thp-relationship="parent_hub">לכל מדריכי הנדל״ן בתאילנד <span aria-hidden="true">←</span></a>
							<?php endif; ?>
						</aside>
					</div>
					<?php ContextualLinks::render_continuations( $route ); ?>
				<?php endif; ?>
				<?php ContextualLinks::render_freshness_and_sources( $route ); ?>
			</div>
		</main>
		<?php require THAILAND_PLATFORM_DIR . 'templates/partials/content-footer.php'; ?>
	</div>
	<?php wp_footer(); ?>
</body>
</html>
