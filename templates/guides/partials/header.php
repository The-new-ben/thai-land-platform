<?php
/**
 * Priority guide document header.
 *
 * @package Thailand_Platform
 */

defined( 'ABSPATH' ) || exit;

use Thailand_Platform\Guides\Repository;
use Thailand_Platform\Guides\View;

$guide_navigation = array();
foreach ( array( 'thailand-visas', 'thailand-law-and-tax' ) as $guide_navigation_id ) {
	$guide_navigation_route = Repository::route_by_id( $guide_navigation_id );
	if ( ! is_array( $guide_navigation_route ) ) {
		continue;
	}
	$guide_navigation_url = View::route_url( $guide_navigation_route );
	if ( '' !== $guide_navigation_url ) {
		$guide_navigation[] = array(
			'url'   => $guide_navigation_url,
			'label' => $guide_navigation_route['public']['kicker'],
		);
	}
}
?>
<header class="thp-guide-header" data-thp-guide-header>
	<div class="thp-guide-header-inner">
		<a class="thp-guide-brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="Thai-Land.co.il, דף הבית">
			<span class="thp-guide-brand-mark" aria-hidden="true">TH</span>
			<span>
				<strong>Thai-Land</strong>
				<small>תאילנד לישראלים</small>
			</span>
		</a>

		<nav class="thp-guide-desktop-nav" aria-label="תפריט ראשי">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>">דף הבית</a>
			<?php foreach ( $guide_navigation as $guide_navigation_item ) : ?>
				<a href="<?php echo esc_url( $guide_navigation_item['url'] ); ?>"><?php echo esc_html( $guide_navigation_item['label'] ); ?></a>
			<?php endforeach; ?>
		</nav>

		<button class="thp-guide-menu-toggle" type="button" aria-controls="thp-guide-mobile-nav" aria-expanded="false" data-thp-menu-open hidden>
			<span aria-hidden="true"></span>
			<span class="thp-sr-only">פתיחת תפריט</span>
		</button>
	</div>

	<div class="thp-guide-mobile-shell" id="thp-guide-mobile-nav" hidden data-thp-mobile-shell>
		<div class="thp-guide-mobile-backdrop" data-thp-menu-close aria-hidden="true"></div>
		<div class="thp-guide-mobile-panel" role="dialog" aria-modal="true" aria-labelledby="thp-guide-mobile-title" tabindex="-1" data-thp-mobile-panel>
			<div class="thp-guide-mobile-heading">
				<strong id="thp-guide-mobile-title">תפריט ראשי</strong>
				<button type="button" data-thp-menu-close>
					<span class="thp-guide-close-icon" aria-hidden="true"></span>
					<span class="thp-sr-only">סגירת תפריט</span>
				</button>
			</div>
			<nav aria-label="תפריט ראשי לנייד">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>">דף הבית</a>
				<?php foreach ( $guide_navigation as $guide_navigation_item ) : ?>
					<a href="<?php echo esc_url( $guide_navigation_item['url'] ); ?>"><?php echo esc_html( $guide_navigation_item['label'] ); ?></a>
				<?php endforeach; ?>
			</nav>
		</div>
	</div>
</header>
