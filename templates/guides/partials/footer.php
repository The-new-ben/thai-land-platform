<?php
/**
 * Priority guide document footer.
 *
 * @package Thailand_Platform
 */

defined( 'ABSPATH' ) || exit;

use Thailand_Platform\Guides\Repository;
use Thailand_Platform\Guides\View;
?>
<footer class="thp-guide-footer">
	<div class="thp-guide-footer-inner">
		<div class="thp-guide-footer-brand">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>">Thai-Land.co.il</a>
			<p>מידע שימושי לישראלים שמטיילים, גרים ועושים עסקים בתאילנד.</p>
		</div>
		<nav aria-label="מדריכים מרכזיים">
			<strong>מדריכים מרכזיים</strong>
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>">דף הבית</a>
			<?php foreach ( array( 'thailand-visas', 'thailand-law-and-tax' ) as $footer_route_id ) : ?>
				<?php $footer_route = Repository::route_by_id( $footer_route_id ); ?>
				<?php $footer_url = is_array( $footer_route ) ? View::route_url( $footer_route ) : ''; ?>
				<?php if ( '' !== $footer_url ) : ?>
					<a href="<?php echo esc_url( $footer_url ); ?>"><?php echo esc_html( $footer_route['public']['kicker'] ); ?></a>
				<?php endif; ?>
			<?php endforeach; ?>
		</nav>
	</div>
	<div class="thp-guide-footer-bottom">
		<p>© <?php echo esc_html( gmdate( 'Y' ) ); ?> Thai-Land.co.il</p>
		<a href="#thp-guide-main">חזרה לתחילת המדריך</a>
	</div>
</footer>
