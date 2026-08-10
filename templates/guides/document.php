<?php
/**
 * Generic full-document template for priority guides and collections.
 *
 * @package Thailand_Platform
 */

defined( 'ABSPATH' ) || exit;

use Thailand_Platform\Guides\Assets;
use Thailand_Platform\Guides\Context;
use Thailand_Platform\Guides\View;

$route = Context::route();
if ( ! is_array( $route ) ) {
	return;
}
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'thp-guide-document' ); ?>>
<?php if ( function_exists( 'wp_body_open' ) ) : ?>
	<?php wp_body_open(); ?>
<?php endif; ?>
<a class="thp-guide-skip-link" href="#thp-guide-main">דילוג לתוכן</a>
<?php require THAILAND_PLATFORM_DIR . 'templates/guides/partials/header.php'; ?>

<main id="thp-guide-main" class="thp-guide" data-thp-guide-route="<?php echo esc_attr( $route['route_id'] ); ?>" data-thp-guide-owner="<?php echo esc_attr( $route['seo_owner_id'] ); ?>">
	<div class="thp-guide-frame">
		<?php View::breadcrumbs( $route ); ?>
	</div>

	<article>
		<header class="thp-guide-hero">
			<div class="thp-guide-frame thp-guide-hero-grid">
				<div class="thp-guide-hero-copy">
					<p class="thp-guide-kicker"><?php echo esc_html( $route['public']['kicker'] ); ?></p>
					<h1><?php echo esc_html( $route['public']['h1'] ); ?></h1>
					<p class="thp-guide-lede"><?php echo esc_html( $route['public']['lede'] ); ?></p>
					<p class="thp-guide-date">
						<span><?php echo ! empty( $route['freshness']['historical'] ) ? 'מידע היסטורי' : 'עודכן לאחרונה'; ?></span>
						<time datetime="<?php echo esc_attr( $route['freshness']['checked_on'] ); ?>" dir="ltr"><?php echo esc_html( View::format_date( $route['freshness']['checked_on'] ) ); ?></time>
					</p>
				</div>
				<figure class="thp-guide-hero-media">
					<picture>
						<source media="(max-width: 720px)" srcset="<?php echo esc_url( Assets::hero_url( $route, 720 ) ); ?>">
						<source media="(max-width: 1279px)" srcset="<?php echo esc_url( Assets::hero_url( $route, 1200 ) ); ?>">
						<img src="<?php echo esc_url( Assets::hero_url( $route, 1717 ) ); ?>" width="1717" height="916" alt="<?php echo esc_attr( $route['public']['h1'] ); ?>" fetchpriority="high" decoding="async">
					</picture>
				</figure>
			</div>
		</header>

		<div class="thp-guide-frame">
			<section class="thp-guide-answer" aria-labelledby="thp-guide-answer-title">
				<div>
					<p>בקצרה</p>
					<h2 id="thp-guide-answer-title"><?php echo esc_html( $route['public']['answer_title'] ); ?></h2>
				</div>
				<p><?php echo esc_html( $route['public']['answer'] ); ?></p>
			</section>

			<?php View::facts( $route ); ?>

			<div class="thp-guide-content-grid">
				<aside class="thp-guide-sidebar">
					<?php View::table_of_contents( $route ); ?>
				</aside>
				<div class="thp-guide-sections">
					<?php View::sections( $route ); ?>
				</div>
			</div>

			<?php View::contextual_links( $route ); ?>
			<?php View::questions( $route ); ?>
			<?php View::related( $route ); ?>
			<?php View::sources( $route ); ?>
		</div>
	</article>
</main>

<?php require THAILAND_PLATFORM_DIR . 'templates/guides/partials/footer.php'; ?>
<?php wp_footer(); ?>
</body>
</html>
