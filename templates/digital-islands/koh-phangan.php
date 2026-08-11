<?php
/**
 * Hebrew-first Canary/Live document for the Koh Phangan Digital Island.
 *
 * @package Thailand_Platform
 */

defined( 'ABSPATH' ) || exit;

use Thailand_Platform\DigitalIslands\Context;
use Thailand_Platform\DigitalIslands\PublicView;
use Thailand_Platform\DigitalIslands\RestController;
use Thailand_Platform\DigitalIslands\View;

$as_of           = gmdate( 'Y-m-d' );
$representation  = Context::representation_state();
$island_payload  = PublicView::island_payload( $as_of, $representation );
$layers_payload  = PublicView::layers_payload( $as_of, $representation );
$entities_payload = PublicView::entities_payload( $as_of, $representation );
$island          = $island_payload['island'];
$groups          = View::grouped_entities( $entities_payload['entities'] );
$decision_dimensions = View::decision_dimension_labels( $island_payload['decision_policy']['required_dimensions'] );
$official_tools  = $island_payload['official_tools'];
$rest_base       = rest_url( RestController::REST_NAMESPACE . '/digital-islands/' . RestController::ISLAND_ID );
$rest_nonce      = PublicView::REPRESENTATION_CANARY === $representation ? wp_create_nonce( 'wp_rest' ) : '';
?>
<!doctype html>
<html lang="he" dir="rtl">
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'thp-di-document' ); ?>>
<?php if ( function_exists( 'wp_body_open' ) ) : ?>
	<?php wp_body_open(); ?>
<?php endif; ?>

<a class="thp-di-skip-link" href="#thp-di-main">דילוג למפה ולרשימה</a>

<header class="thp-di-header">
	<div class="thp-di-frame thp-di-header-inner">
		<a class="thp-di-brand" href="<?php echo esc_url( home_url( '/' ) ); ?>">תאילנד מקרוב</a>
		<nav aria-label="ניווט עיקרי">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>">דף הבית</a>
			<a href="#thp-di-places">מקומות ושירותים</a>
		</nav>
	</div>
</header>

<main
	id="thp-di-main"
	class="thp-di-main"
	data-digital-island-app
	data-rest-base="<?php echo esc_url( $rest_base ); ?>"
	<?php if ( '' !== $rest_nonce ) : ?>data-rest-nonce="<?php echo esc_attr( $rest_nonce ); ?>"<?php endif; ?>
	data-island-id="<?php echo esc_attr( $island['geo_id'] ); ?>"
	data-island-center-lat="<?php echo esc_attr( (string) $island['center']['latitude'] ); ?>"
	data-island-center-lng="<?php echo esc_attr( (string) $island['center']['longitude'] ); ?>"
>
	<nav class="thp-di-breadcrumb" aria-label="פירורי לחם">
		<ol class="thp-di-frame">
			<li><a href="<?php echo esc_url( home_url( '/' ) ); ?>">תאילנד לישראלים</a></li>
			<li><span>מפת תאילנד</span></li>
			<li aria-current="page">מפת קופנגן</li>
		</ol>
	</nav>

	<section class="thp-di-intro">
		<div class="thp-di-frame thp-di-intro-grid">
			<div>
				<p class="thp-di-kicker">קופנגן, לפי אזורים ומקומות</p>
				<h1>מפת קופנגן: יישובים, שירותים ופרויקטים</h1>
				<p class="thp-di-lede">עברו בין טונג סאלה, באן טאי, סריטאנו, צ'אלוקלאם ושאר חלקי האי. אפשר לבחור תצוגה, לסנן שכבות או להמשיך ישר לרשימה הנגישה.</p>
			</div>
			<div class="thp-di-intro-note">
				<p>נקודת מפה מתארת את מיקום המקום. בדיקת חלקה, רישום וזכויות בנייה נעשית מול המקור הרשמי המתאים.</p>
				<p>המידע נבדק לאחרונה: <time datetime="<?php echo esc_attr( $island_payload['dataset_checked_on'] ); ?>" dir="ltr"><?php echo esc_html( $island_payload['dataset_checked_on'] ); ?></time></p>
			</div>
		</div>
	</section>

	<div class="thp-di-frame thp-di-workspace">
		<aside class="thp-di-controls" aria-label="כלי המפה">
			<fieldset class="thp-di-view-switcher">
				<legend>בחרו תצוגה</legend>
				<div class="thp-di-segmented">
					<button type="button" data-view-mode="3d" aria-pressed="false">עולם תלת ממדי</button>
					<button type="button" data-view-mode="2d" aria-pressed="false">מפה שימושית</button>
					<button type="button" data-view-mode="list" aria-pressed="true">רשימה</button>
				</div>
			</fieldset>

			<form class="thp-di-search" role="search" data-island-search>
				<label for="thp-di-search-input">חיפוש מקום או שירות</label>
				<div>
					<input id="thp-di-search-input" name="island-search" type="search" autocomplete="off" minlength="2" maxlength="80">
					<button type="submit">חיפוש</button>
				</div>
			</form>

			<fieldset class="thp-di-layer-filter">
				<legend>שכבות מידע</legend>
				<div class="thp-di-layer-options">
					<?php foreach ( $layers_payload['layers'] as $layer ) : ?>
						<label>
							<input type="checkbox" value="<?php echo esc_attr( $layer['layer_id'] ); ?>" data-layer-filter checked>
							<span><?php echo esc_html( $layer['label_he'] ); ?></span>
						</label>
					<?php endforeach; ?>
				</div>
			</fieldset>
		</aside>

		<section class="thp-di-experience" aria-labelledby="thp-di-experience-title">
			<div class="thp-di-experience-heading">
				<div>
					<p class="thp-di-eyebrow">תצוגת האי</p>
					<h2 id="thp-di-experience-title">נכנסים לקופנגן</h2>
				</div>
				<p data-renderer-status role="status" aria-live="polite">הרשימה הנגישה פעילה</p>
			</div>

			<div class="thp-di-map-shell" data-renderer-shell>
				<div class="thp-di-renderer-stage" data-renderer-stage role="region" aria-label="מפת קופנגן האינטראקטיבית" tabindex="0"></div>
				<div class="thp-di-map-poster" data-list-poster>
					<div class="thp-di-island-shape" aria-hidden="true">
						<span class="thp-di-pulse thp-di-pulse-north"></span>
						<span class="thp-di-pulse thp-di-pulse-west"></span>
						<span class="thp-di-pulse thp-di-pulse-south"></span>
					</div>
					<div>
						<h3>האי פתוח גם בלי מפה גרפית</h3>
						<p>בחרו מקום ברשימה כדי להתמקד בו. במכשיר שתומך במנוע מפה מקומי, אותה פעולה תעביר את המצלמה לנקודה.</p>
						<a href="#thp-di-places">מעבר לרשימת המקומות</a>
					</div>
				</div>
			</div>
			<p class="thp-di-attribution">נתוני התמצאות נגזרו בחלקם מ-<a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener noreferrer external">© OpenStreetMap contributors</a>. אין שימוש באריחי הקהילה של OpenStreetMap.</p>
		</section>
	</div>

	<section id="thp-di-land-check" class="thp-di-land-check" aria-labelledby="thp-di-land-check-title">
		<div class="thp-di-frame">
			<div class="thp-di-section-heading">
				<div>
					<p class="thp-di-eyebrow">בדיקה לפני קנייה או בנייה</p>
					<h2 id="thp-di-land-check-title">כל נקודה במפה עוברת למסלול בדיקה, לא לפסק דין אוטומטי</h2>
				</div>
				<p>המפה עוזרת לזהות מקום ולהתחיל בדיקה. היא אינה מאשרת בעלות או זכויות בנייה.</p>
			</div>

			<div class="thp-di-land-grid">
				<div class="thp-di-checklist-card">
					<h3>מה בודקים לכל קרקע או נכס</h3>
					<ol class="thp-di-decision-list">
						<?php foreach ( $decision_dimensions as $dimension ) : ?>
							<li data-decision-dimension="<?php echo esc_attr( $dimension['dimension_id'] ); ?>">
								<span aria-hidden="true"></span>
								<?php echo esc_html( $dimension['label_he'] ); ?>
							</li>
						<?php endforeach; ?>
					</ol>
				</div>

				<div class="thp-di-official-tools">
					<h3>כלים רשמיים להמשך הבדיקה</h3>
					<?php foreach ( $official_tools as $tool ) : ?>
						<article class="thp-di-official-tool" data-official-tool="<?php echo esc_attr( $tool['tool_id'] ); ?>">
							<h4><?php echo esc_html( $tool['label_he'] ); ?></h4>
							<ul>
								<?php foreach ( $tool['limitations_he'] as $limitation ) : ?>
									<li><?php echo esc_html( $limitation ); ?></li>
								<?php endforeach; ?>
							</ul>
							<a href="<?php echo esc_url( $tool['url'] ); ?>" target="_blank" rel="noopener noreferrer external">פתיחה באתר הרשמי</a>
						</article>
					<?php endforeach; ?>
				</div>
			</div>
		</div>
	</section>

	<section id="thp-di-places" class="thp-di-places" aria-labelledby="thp-di-places-title">
		<div class="thp-di-frame">
			<div class="thp-di-section-heading">
				<div>
					<p class="thp-di-eyebrow">לפי סוג מקום</p>
					<h2 id="thp-di-places-title">מקומות ושירותים בקופנגן</h2>
				</div>
				<p><span data-visible-count><?php echo esc_html( (string) $entities_payload['entity_count'] ); ?></span> פריטים מוצגים</p>
			</div>

			<div class="thp-di-groups" data-entity-groups>
				<?php foreach ( $groups as $type => $group ) : ?>
					<section class="thp-di-group" data-entity-group="<?php echo esc_attr( $type ); ?>">
						<h3><?php echo esc_html( $group['label'] ); ?></h3>
						<ul class="thp-di-cards" role="list">
							<?php foreach ( $group['entities'] as $entity ) : ?>
								<?php $coordinates = $entity['coordinates']; ?>
								<li
									class="thp-di-card"
									data-entity-card
									data-entity-id="<?php echo esc_attr( $entity['entity_id'] ); ?>"
									data-entity-layers="<?php echo esc_attr( implode( ',', $entity['layer_ids'] ) ); ?>"
									<?php if ( is_array( $coordinates ) ) : ?>
										data-entity-lat="<?php echo esc_attr( (string) $coordinates['latitude'] ); ?>"
										data-entity-lng="<?php echo esc_attr( (string) $coordinates['longitude'] ); ?>"
									<?php endif; ?>
								>
									<div class="thp-di-card-topline">
										<span><?php echo esc_html( $group['label'] ); ?></span>
										<?php if ( 'review_due' === $entity['freshness_state'] ) : ?>
											<span>פרטים בבדיקה חוזרת</span>
										<?php endif; ?>
									</div>
									<h4><?php echo esc_html( View::name( $entity ) ); ?></h4>
									<?php if ( ! empty( $entity['names']['en'] ) && $entity['names']['en'] !== View::name( $entity ) ) : ?>
										<p class="thp-di-card-en" dir="ltr"><?php echo esc_html( $entity['names']['en'] ); ?></p>
									<?php endif; ?>
									<?php if ( '' !== $entity['location_label_he'] ) : ?>
										<p><?php echo esc_html( $entity['location_label_he'] ); ?></p>
									<?php endif; ?>

									<?php if ( array() !== $entity['facts'] ) : ?>
										<dl>
											<?php foreach ( $entity['facts'] as $fact ) : ?>
												<div>
													<dt><?php echo esc_html( $fact['label_he'] ); ?></dt>
											<dd><?php echo esc_html( $fact['value_he'] ); ?></dd>
											<?php if ( array() !== $fact['evidence'] ) : ?>
												<dd class="thp-di-evidence-links">
													<?php foreach ( $fact['evidence'] as $citation ) : ?>
														<a href="<?php echo esc_url( $citation['url'] ); ?>" rel="noopener noreferrer" target="_blank"><?php echo esc_html( 'מקור: ' . $citation['publisher'] ); ?></a>
													<?php endforeach; ?>
												</dd>
											<?php endif; ?>
										</div>
											<?php endforeach; ?>
										</dl>
									<?php endif; ?>

									<?php if ( is_array( $coordinates ) ) : ?>
										<button type="button" class="thp-di-focus-button" data-focus-entity="<?php echo esc_attr( $entity['entity_id'] ); ?>">מיקוד במפה</button>
										<p class="thp-di-coordinate-note">
											<span dir="ltr"><?php echo esc_html( $coordinates['latitude'] . ', ' . $coordinates['longitude'] ); ?></span>
											<?php if ( '' !== $coordinates['basis_label'] ) : ?>
												<span><?php echo esc_html( $coordinates['basis_label'] ); ?></span>
											<?php endif; ?>
										</p>
									<?php else : ?>
										<p class="thp-di-coordinate-note">מופיע ברשימה ללא נקודת מפה.</p>
									<?php endif; ?>

									<?php if ( array() !== $entity['evidence'] ) : ?>
										<p class="thp-di-evidence-links" aria-label="מקורות שנבדקו">
											<?php foreach ( $entity['evidence'] as $citation ) : ?>
												<a href="<?php echo esc_url( $citation['url'] ); ?>" rel="noopener noreferrer" target="_blank" title="<?php echo esc_attr( $citation['title'] ); ?>"><?php echo esc_html( $citation['publisher'] ); ?></a>
											<?php endforeach; ?>
										</p>
									<?php endif; ?>
								</li>
							<?php endforeach; ?>
						</ul>
					</section>
				<?php endforeach; ?>
			</div>

			<p class="thp-di-no-results" data-no-results hidden>לא נמצאו פריטים בהתאמה הנוכחית.</p>
		</div>
	</section>
</main>

<footer class="thp-di-footer">
	<div class="thp-di-frame">
		<p>קופנגן לפי מקומות, שירותים וקשרים בין אזורים.</p>
		<a href="#thp-di-main">חזרה לראש המפה</a>
	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
