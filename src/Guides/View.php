<?php
/**
 * Escaped view helpers for the priority guide document.
 *
 * @package Thailand_Platform
 */

namespace Thailand_Platform\Guides;

final class View {
	/**
	 * @param array $route Managed route.
	 * @return string
	 */
	public static function route_url( $route ) {
		$post_id = absint( $route['wordpress']['post_id'] ?? 0 );
		if ( $post_id && 'publish' === get_post_status( $post_id ) ) {
			return home_url( $route['path'] ?? '/' );
		}
		if (
			Context::is_authorized_canary()
			&& 'draft_canary_or_published_live' === ( $route['wordpress']['state_policy'] ?? '' )
			&& 'draft' === get_post_status( $post_id )
		) {
			return Context::canary_url( $route );
		}
		return '';
	}

	/**
	 * @param array $route Current route.
	 * @return void
	 */
	public static function breadcrumbs( $route ) {
		?>
		<nav class="thp-guide-breadcrumbs" aria-label="פירורי לחם">
			<ol>
				<?php foreach ( $route['breadcrumbs'] as $crumb ) : ?>
					<li>
						<?php
						$url = '';
						if ( empty( $crumb['current'] ) ) {
							if ( '/' === $crumb['path'] ) {
								$url = home_url( '/' );
							} else {
								$target = Repository::route_by_path( $crumb['path'] );
								$url    = is_array( $target ) ? self::route_url( $target ) : '';
							}
						}
						if ( '' !== $url ) :
							?>
							<a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $crumb['label'] ); ?></a>
						<?php else : ?>
							<span<?php echo ! empty( $crumb['current'] ) ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $crumb['label'] ); ?></span>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ol>
		</nav>
		<?php
	}

	/**
	 * @param array $route Current route.
	 * @return void
	 */
	public static function facts( $route ) {
		?>
		<dl class="thp-guide-facts" aria-label="פרטים מרכזיים">
			<?php foreach ( $route['public']['facts'] as $fact ) : ?>
				<div>
					<dt><?php echo esc_html( $fact['label'] ); ?></dt>
					<dd><?php echo esc_html( $fact['value'] ); ?></dd>
				</div>
			<?php endforeach; ?>
		</dl>
		<?php
	}

	/**
	 * @param array $route Current route.
	 * @return void
	 */
	public static function table_of_contents( $route ) {
		?>
		<nav class="thp-guide-toc" aria-labelledby="thp-guide-toc-title">
			<strong id="thp-guide-toc-title">תוכן המדריך</strong>
			<ol>
				<?php foreach ( $route['sections'] as $section ) : ?>
					<li><a href="#<?php echo esc_attr( $section['section_id'] ); ?>"><?php echo esc_html( $section['heading'] ); ?></a></li>
				<?php endforeach; ?>
			</ol>
		</nav>
		<?php
	}

	/**
	 * @param array $route Current route.
	 * @return void
	 */
	public static function sections( $route ) {
		foreach ( $route['sections'] as $index => $section ) :
			?>
			<section class="thp-guide-section" id="<?php echo esc_attr( $section['section_id'] ); ?>" data-thp-guide-section>
				<div class="thp-guide-section-number" aria-hidden="true"><?php echo esc_html( str_pad( (string) ( $index + 1 ), 2, '0', STR_PAD_LEFT ) ); ?></div>
				<div class="thp-guide-section-body">
					<h2><?php echo esc_html( $section['heading'] ); ?></h2>
					<?php foreach ( $section['paragraphs'] as $paragraph ) : ?>
						<p><?php echo esc_html( $paragraph ); ?></p>
					<?php endforeach; ?>

					<?php foreach ( $section['bullet_groups'] as $group ) : ?>
						<div class="thp-guide-list-group">
							<h3><?php echo esc_html( $group['heading'] ); ?></h3>
							<ul>
								<?php foreach ( $group['items'] as $item ) : ?>
									<li><?php echo esc_html( $item ); ?></li>
								<?php endforeach; ?>
							</ul>
						</div>
					<?php endforeach; ?>

					<?php if ( ! empty( $section['steps'] ) ) : ?>
						<ol class="thp-guide-steps">
							<?php foreach ( $section['steps'] as $step ) : ?>
								<li>
									<strong><?php echo esc_html( $step['title'] ); ?></strong>
									<p><?php echo esc_html( $step['text'] ); ?></p>
								</li>
							<?php endforeach; ?>
						</ol>
					<?php endif; ?>

					<?php if ( is_array( $section['callout'] ) ) : ?>
						<aside class="thp-guide-callout thp-guide-callout-<?php echo esc_attr( $section['callout']['tone'] ); ?>">
							<strong><?php echo esc_html( $section['callout']['title'] ); ?></strong>
							<p><?php echo esc_html( $section['callout']['text'] ); ?></p>
						</aside>
					<?php endif; ?>
				</div>
			</section>
			<?php
		endforeach;
	}

	/**
	 * Render authored in-article links while keeping unavailable drafts unlinked.
	 *
	 * @param array $route Current route.
	 * @return void
	 */
	public static function contextual_links( $route ) {
		if ( empty( $route['contextual_links'] ) || ! is_array( $route['contextual_links'] ) ) {
			return;
		}
		?>
		<div class="thp-guide-contextual-links" data-thp-contextual-links>
			<?php foreach ( $route['contextual_links'] as $item ) : ?>
				<?php
				$target_owner_id = (string) ( $item['target_owner_id'] ?? '' );
				$url             = '';
				if ( 'home' === $target_owner_id ) {
					$url = home_url( '/' );
				} else {
					$target = Repository::route_by_id( $target_owner_id );
					$url    = is_array( $target ) ? self::route_url( $target ) : '';
				}
				?>
				<p data-thp-contextual-target="<?php echo esc_attr( $target_owner_id ); ?>">
					<?php echo esc_html( $item['leading_text'] ?? '' ); ?>
					<?php if ( '' !== $url ) : ?>
						<a href="<?php echo esc_url( $url ); ?>" data-thp-contextual-owner="<?php echo esc_attr( $target_owner_id ); ?>"><?php echo esc_html( $item['anchor_text'] ?? '' ); ?></a>
					<?php else : ?>
						<span data-thp-contextual-owner="<?php echo esc_attr( $target_owner_id ); ?>" data-thp-contextual-unlinked><?php echo esc_html( $item['anchor_text'] ?? '' ); ?></span>
					<?php endif; ?>
					<?php echo esc_html( $item['trailing_text'] ?? '' ); ?>
				</p>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Render visible questions and answers without a second schema owner.
	 *
	 * @param array $route Current route.
	 * @return void
	 */
	public static function questions( $route ) {
		?>
		<section class="thp-guide-questions" aria-labelledby="thp-guide-questions-title">
			<div class="thp-guide-section-heading">
				<p>שאלות נפוצות</p>
				<h2 id="thp-guide-questions-title">תשובות קצרות לשאלות חשובות</h2>
			</div>
			<div class="thp-guide-question-list">
				<?php foreach ( $route['faqs'] as $index => $faq ) : ?>
					<details<?php echo 0 === $index ? ' open' : ''; ?>>
						<summary><?php echo esc_html( $faq['question'] ); ?></summary>
						<p><?php echo esc_html( $faq['answer'] ); ?></p>
					</details>
				<?php endforeach; ?>
			</div>
		</section>
		<?php
	}

	/**
	 * @param array $route Current route.
	 * @return void
	 */
	public static function related( $route ) {
		$related = array();
		foreach ( $route['related_route_ids'] as $route_id ) {
			$target = Repository::route_by_id( $route_id );
			if ( ! is_array( $target ) ) {
				continue;
			}
			$url = self::route_url( $target );
			if ( '' !== $url ) {
				$related[] = array( 'route' => $target, 'url' => $url );
			}
		}
		if ( empty( $related ) ) {
			return;
		}
		?>
		<section class="thp-guide-related" aria-labelledby="thp-guide-related-title">
			<div class="thp-guide-section-heading">
				<p>מדריכים נוספים</p>
				<h2 id="thp-guide-related-title">המידע הבא שיעזור לכם להתקדם</h2>
			</div>
			<div class="thp-guide-related-grid">
				<?php foreach ( $related as $item ) : ?>
					<a href="<?php echo esc_url( $item['url'] ); ?>" data-thp-related-route="<?php echo esc_attr( $item['route']['route_id'] ); ?>">
						<span><?php echo esc_html( $item['route']['public']['kicker'] ); ?></span>
						<strong><?php echo esc_html( $item['route']['public']['h1'] ); ?></strong>
						<small>למדריך המלא</small>
					</a>
				<?php endforeach; ?>
			</div>
		</section>
		<?php
	}

	/**
	 * @param array $route Current route.
	 * @return void
	 */
	public static function sources( $route ) {
		?>
		<section class="thp-guide-sources" aria-labelledby="thp-guide-sources-title">
			<div class="thp-guide-section-heading">
				<p>מקורות רשמיים</p>
				<h2 id="thp-guide-sources-title">המסמכים שעליהם מבוסס המדריך</h2>
			</div>
			<ul>
				<?php foreach ( $route['source_ids'] as $source_id ) : ?>
					<?php $source = Repository::source( $source_id ); ?>
					<?php if ( is_array( $source ) ) : ?>
						<li>
							<a href="<?php echo esc_url( $source['url'] ); ?>" rel="noopener noreferrer">
								<strong><bdi dir="auto"><?php echo esc_html( $source['label'] ); ?></bdi></strong>
								<span><bdi dir="auto"><?php echo esc_html( $source['publisher'] ); ?></bdi></span>
							</a>
						</li>
					<?php endif; ?>
				<?php endforeach; ?>
			</ul>
			<p class="thp-guide-reviewed">עודכן לאחרונה: <time datetime="<?php echo esc_attr( $route['freshness']['checked_on'] ); ?>" dir="ltr"><?php echo esc_html( self::format_date( $route['freshness']['checked_on'] ) ); ?></time></p>
		</section>
		<?php
	}

	/**
	 * @param string $value ISO date.
	 * @return string
	 */
	public static function format_date( $value ) {
		$parts = explode( '-', (string) $value );
		if ( 3 !== count( $parts ) ) {
			return (string) $value;
		}
		return $parts[2] . '.' . $parts[1] . '.' . $parts[0];
	}
}
