<?php
/**
 * Visible breadcrumb renderer.
 *
 * @package Thailand_Platform
 */

namespace Thailand_Platform\Content;

final class Breadcrumbs {
	/**
	 * @param array $route Current route.
	 * @return void
	 */
	public static function render( $route ) {
		$breadcrumbs = $route['breadcrumbs'] ?? array();
		if ( empty( $breadcrumbs ) ) {
			return;
		}

		$labels = Repository::labels();
		?>
		<nav class="thp-breadcrumbs" aria-label="<?php echo esc_attr( $labels['breadcrumbs_aria'] ?? 'פירורי לחם' ); ?>" data-thp-breadcrumbs>
			<ol>
				<?php foreach ( $breadcrumbs as $index => $breadcrumb ) : ?>
					<li>
						<?php if ( $index + 1 === count( $breadcrumbs ) ) : ?>
							<span aria-current="page"><?php echo esc_html( $breadcrumb['label'] ); ?></span>
						<?php else : ?>
							<a href="<?php echo esc_url( home_url( $breadcrumb['path'] ) ); ?>"><?php echo esc_html( $breadcrumb['label'] ); ?></a>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ol>
		</nav>
		<?php
	}
}
