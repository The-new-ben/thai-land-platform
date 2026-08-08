<?php
/**
 * Hub, continuation, freshness, and source renderers.
 *
 * @package Thailand_Platform
 */

namespace Thailand_Platform\Content;

final class ContextualLinks {
	/**
	 * @param array $route Current route.
	 * @return void
	 */
	public static function render_hub_experience( $route ) {
		$experience = Repository::hub_experience();
		$cards      = array();
		foreach ( $experience['cards'] ?? array() as $card ) {
			$cards[ $card['route_id'] ] = $card;
		}
		?>
		<section class="thp-decision-finder" aria-labelledby="thp-decision-title">
			<div class="thp-section-heading">
				<p class="thp-kicker">מתחילים מההחלטה שלכם</p>
				<h2 id="thp-decision-title"><?php echo esc_html( $experience['section_heading'] ?? 'איך מתקדמים בנדל״ן בתאילנד' ); ?></h2>
			</div>
			<div class="thp-decision-grid">
				<?php foreach ( $experience['decision_paths'] ?? array() as $decision ) : ?>
					<article class="thp-decision-card">
						<h3><?php echo esc_html( $decision['prompt'] ); ?></h3>
						<ul>
							<?php foreach ( $decision['choices'] as $choice ) : ?>
								<?php $target = Repository::route_by_id( $choice['target_route_id'] ); ?>
								<?php if ( is_array( $target ) ) : ?>
									<li>
										<a href="<?php echo esc_url( home_url( $target['path'] ) ); ?>" data-thp-target-owner="<?php echo esc_attr( $target['route_id'] ); ?>" data-thp-relationship="decision">
											<strong><?php echo esc_html( $choice['label'] ); ?></strong>
											<span><?php echo esc_html( $choice['detail'] ); ?></span>
										</a>
									</li>
								<?php endif; ?>
							<?php endforeach; ?>
						</ul>
					</article>
				<?php endforeach; ?>
			</div>
		</section>

		<section class="thp-guide-index" aria-labelledby="thp-guide-index-title" data-thp-continuations>
			<div class="thp-section-heading">
				<p class="thp-kicker">מדריכים לפי שלב</p>
				<h2 id="thp-guide-index-title">מפת הדרכים לנדל״ן בתאילנד</h2>
			</div>
			<?php foreach ( $experience['sections'] ?? array() as $section ) : ?>
				<section class="thp-guide-group" aria-labelledby="thp-group-<?php echo esc_attr( $section['section_id'] ); ?>">
					<header>
						<h3 id="thp-group-<?php echo esc_attr( $section['section_id'] ); ?>"><?php echo esc_html( $section['title'] ); ?></h3>
						<p><?php echo esc_html( $section['description'] ); ?></p>
					</header>
					<div class="thp-guide-grid">
						<?php foreach ( $section['route_ids'] as $route_id ) : ?>
							<?php
							$target = Repository::route_by_id( $route_id );
							$card   = $cards[ $route_id ] ?? null;
							if ( ! is_array( $target ) || ! is_array( $card ) ) {
								continue;
							}
							?>
							<article class="thp-guide-card">
								<p class="thp-kicker"><?php echo esc_html( $card['eyebrow'] ); ?></p>
								<h4><?php echo esc_html( $card['title'] ); ?></h4>
								<p><?php echo esc_html( $card['summary'] ); ?></p>
								<a href="<?php echo esc_url( home_url( $target['path'] ) ); ?>" data-thp-target-owner="<?php echo esc_attr( $route_id ); ?>" data-thp-relationship="child_spoke"><?php echo esc_html( $card['action_label'] ); ?> <span aria-hidden="true">←</span></a>
							</article>
						<?php endforeach; ?>
					</div>
				</section>
			<?php endforeach; ?>
		</section>
		<?php
	}

	/**
	 * @param array $route Current route.
	 * @return void
	 */
	public static function render_continuations( $route ) {
		$links = $route['continuations'] ?? array();
		if ( empty( $links ) ) {
			return;
		}
		$labels = Repository::labels();
		?>
		<section class="thp-continuations" aria-labelledby="thp-continuations-title" data-thp-continuations>
			<div class="thp-section-heading">
				<p class="thp-kicker">השלב הבא</p>
				<h2 id="thp-continuations-title"><?php echo esc_html( $labels['continuations_heading'] ?? 'עוד מדריכים בנושא' ); ?></h2>
			</div>
			<div class="thp-continuation-grid">
				<?php foreach ( $links as $link ) : ?>
					<?php $target = Repository::route_by_id( $link['target_route_id'] ); ?>
					<?php if ( is_array( $target ) ) : ?>
						<a class="thp-continuation-card" href="<?php echo esc_url( home_url( $target['path'] ) ); ?>" data-thp-target-owner="<?php echo esc_attr( $target['route_id'] ); ?>" data-thp-relationship="sibling">
							<strong><?php echo esc_html( $link['label'] ); ?></strong>
							<span><?php echo esc_html( $link['context'] ); ?></span>
							<small>למדריך <span aria-hidden="true">←</span></small>
						</a>
					<?php endif; ?>
				<?php endforeach; ?>
			</div>
		</section>
		<?php
	}

	/**
	 * @param array $route Current route.
	 * @return void
	 */
	public static function render_freshness_and_sources( $route ) {
		$freshness = Repository::freshness( $route['freshness_id'] ?? '' );
		$sources   = array_filter(
			array_map(
				array( Repository::class, 'source' ),
				$route['source_ids'] ?? array()
			)
		);
		$labels = Repository::labels();
		if ( ! is_array( $freshness ) || empty( $sources ) ) {
			return;
		}
		?>
		<section class="thp-source-panel" aria-label="מידע ועדכונים">
			<div class="thp-freshness">
				<p class="thp-kicker"><?php echo esc_html( $labels['freshness_heading'] ?? 'פרטים שמשתנים' ); ?></p>
				<strong><?php echo esc_html( $freshness['label'] ); ?></strong>
				<span><?php echo esc_html( $freshness['detail'] ); ?></span>
			</div>
			<div class="thp-sources">
				<p class="thp-kicker"><?php echo esc_html( $labels['sources_heading'] ?? 'מקורות שימושיים' ); ?></p>
				<ul>
					<?php foreach ( $sources as $source ) : ?>
						<li><a href="<?php echo esc_url( $source['url'] ); ?>" rel="noopener"><?php echo esc_html( $source['label'] ); ?></a><span><?php echo esc_html( $source['scope_label'] ); ?></span></li>
					<?php endforeach; ?>
				</ul>
			</div>
		</section>
		<?php
	}
}
