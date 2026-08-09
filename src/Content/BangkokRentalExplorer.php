<?php
/**
 * Bangkok long-term rental decision experience.
 *
 * @package Thailand_Platform
 */

namespace Thailand_Platform\Content;

final class BangkokRentalExplorer {
	/**
	 * @param array $route Current managed route.
	 * @return void
	 */
	public static function render( $route ) {
		if (
			'bangkok-apartment-rental' !== ( $route['route_id'] ?? '' )
			|| ! BangkokRentalRepository::ready()
		) {
			return;
		}

		$registry = BangkokRentalRepository::all();
		$areas    = BangkokRentalRepository::areas();
		$labels   = $registry['public_labels'] ?? array();
		$method   = $registry['pricing_method'] ?? array();
		?>
		<section class="thp-bkk-atlas" aria-labelledby="thp-bkk-atlas-title" data-thp-bkk-explorer>
			<div class="thp-bkk-intro">
				<div>
					<p class="thp-kicker">בוחרים אזור לפי החיים שלכם</p>
					<h2 id="thp-bkk-atlas-title"><?php echo esc_html( $labels['area_comparison_heading'] ?? 'איפה כדאי לגור בבנגקוק' ); ?></h2>
					<p class="thp-bkk-intro-copy">השוו בין אזורים לפי תקציב, רכבת, קצב החיים והדברים שמחכים לכם מחוץ לדירה. כל אזור שוק מחובר גם למחוזות הרשמיים שהוא חוצה.</p>
				</div>
				<p class="thp-bkk-method"><strong>איך לקרוא את המחירים</strong><?php echo esc_html( $method['public_label'] ?? 'טווח שכירות מבוקש משוער לדירה מרוהטת בחוזה שנתי, נדגם באוגוסט 2026.' ); ?></p>
			</div>

			<?php self::render_related_links(); ?>

			<fieldset class="thp-bkk-controls" data-thp-bkk-controls hidden>
				<legend class="thp-sr-only">סינון אזורי מגורים</legend>
				<div class="thp-bkk-field">
					<span class="thp-bkk-budget-line"><label for="thp-bkk-budget">תקציב חודשי מרבי</label><output id="thp-bkk-budget-output" for="thp-bkk-budget" data-thp-bkk-budget-output>50,000 באט</output></span>
					<input id="thp-bkk-budget" type="range" min="10000" max="100000" step="1000" value="50000" data-default-value="50000" data-thp-bkk-budget aria-describedby="thp-bkk-budget-output" aria-valuetext="50,000 באט">
				</div>
				<label class="thp-bkk-field" for="thp-bkk-bedroom"><span>גודל דירה</span><select id="thp-bkk-bedroom" data-thp-bkk-bedroom><option value="one">חדר שינה אחד</option><option value="two">שני חדרי שינה</option></select></label>
				<label class="thp-bkk-field" for="thp-bkk-lifestyle"><span>מה חשוב לכם</span><select id="thp-bkk-lifestyle" data-thp-bkk-lifestyle><option value="all">הכל</option><option value="value">תמורה לתקציב</option><option value="central">מיקום מרכזי</option><option value="quiet">רחובות שקטים</option><option value="nightlife">חיי לילה</option><option value="family">חיי משפחה</option><option value="business">קרבה לעסקים</option><option value="food">אוכל ובתי קפה</option><option value="green">פארקים</option><option value="upscale">מגורים ברמה גבוהה</option></select></label>
				<label class="thp-bkk-field" for="thp-bkk-rail"><span>רכבת קרובה</span><select id="thp-bkk-rail" data-thp-bkk-rail><option value="all">BTS או MRT</option><option value="bts">BTS</option><option value="mrt">MRT</option></select></label>
				<button class="thp-bkk-reset" type="button" data-thp-bkk-reset>ניקוי מסננים</button>
			</fieldset>
			<div class="thp-bkk-result-bar" data-thp-bkk-result-bar hidden><strong data-thp-bkk-results><?php echo esc_html( count( $areas ) ); ?> אזורים מתאימים</strong><span data-thp-bkk-status aria-live="polite" aria-atomic="true">מוצגים כל האזורים</span></div>

			<div class="thp-bkk-workspace">
				<div class="thp-bkk-map-shell">
					<div class="thp-bkk-map-head">
						<div><h3><?php echo esc_html( $labels['area_map_heading'] ?? 'מפת אזורי המגורים' ); ?></h3><p>בחרו נקודה כדי לראות את האזור ברשימה</p></div>
						<div class="thp-bkk-map-key" aria-label="מקרא"><span>BTS ירוק</span><span>MRT כחול</span><span>אזורי שוק, לא גבולות מנהליים</span></div>
					</div>
					<div class="thp-bkk-map" role="group" aria-label="מפת החלטה של עשרה אזורי מגורים בבנגקוק">
						<span class="thp-bkk-rail is-bts" aria-hidden="true"></span><span class="thp-bkk-rail is-mrt" aria-hidden="true"></span>
						<span class="thp-bkk-rail-label is-bts" aria-hidden="true">BTS</span><span class="thp-bkk-rail-label is-mrt" aria-hidden="true">MRT</span>
						<?php foreach ( $areas as $area ) : ?>
							<?php self::render_marker( $area ); ?>
						<?php endforeach; ?>
					</div>
				</div>

				<div class="thp-bkk-area-grid">
					<?php foreach ( $areas as $area ) : ?>
						<?php self::render_area_card( $area ); ?>
					<?php endforeach; ?>
				</div>
			</div>

			<?php self::render_district_directory(); ?>
			<?php self::render_planning( $registry ); ?>
			<?php self::render_sources( $registry ); ?>
		</section>
		<?php
	}

	/**
	 * Render reader-facing links to the canonical Bangkok and price owners.
	 *
	 * @return void
	 */
	private static function render_related_links() {
		$price_route = Repository::route_by_id( 'thailand-property-prices' );
		$price_path  = is_array( $price_route ) ? $price_route['path'] : '/price/';
		$price_owner = is_array( $price_route ) ? $price_route['seo_owner_id'] : 'thailand-property-prices';
		?>
		<nav class="thp-bkk-related-links" aria-label="מדריכים משלימים">
			<span>להשלמת התמונה</span>
			<p>הכירו שכונות, תחבורה ותכנון בעיר בתוך <a href="<?php echo esc_url( home_url( '/בנגקוק-תאילנד/' ) ); ?>" data-thp-target-owner="bangkok" data-thp-relationship="support">מדריך בנגקוק</a>, והשוו את שוק השכירות לעלויות רכישה בתוך <a href="<?php echo esc_url( home_url( $price_path ) ); ?>" data-thp-target-route="thailand-property-prices" data-thp-target-owner="<?php echo esc_attr( $price_owner ); ?>" data-thp-relationship="sibling">מחירי נדל״ן בתאילנד</a>.</p>
		</nav>
		<?php
	}

	/**
	 * @param array $area Area record.
	 * @return void
	 */
	private static function render_marker( $area ) {
		$name     = $area['names']['he'];
		$position = $area['map_position'];
		$bands    = $area['monthly_asking_bands'];
		$style    = sprintf(
			'--thp-map-x:%s%%;--thp-map-y:%s%%',
			esc_attr( (string) (float) $position['x_percent'] ),
			esc_attr( (string) (float) $position['y_percent'] )
		);
		$label = sprintf(
			'%1$s, חדר שינה אחד %2$s עד %3$s באט',
			$name,
			number_format_i18n( $bands['one_bedroom']['min_thb'] ),
			number_format_i18n( $bands['one_bedroom']['max_thb'] )
		);
		?>
		<button type="button" class="thp-bkk-marker" style="<?php echo esc_attr( $style ); ?>" data-area-id="<?php echo esc_attr( $area['area_id'] ); ?>" data-thp-bkk-marker aria-label="<?php echo esc_attr( $label ); ?>" aria-pressed="false" disabled><?php echo esc_html( $name ); ?></button>
		<?php
	}

	/**
	 * @param array $area Area record.
	 * @return void
	 */
	private static function render_area_card( $area ) {
		$bands       = $area['monthly_asking_bands'];
		$corridor    = BangkokRentalRepository::corridor( $area['corridor_id'] );
		$districts   = array_filter( array_map( array( BangkokRentalRepository::class, 'district' ), $area['official_district_ids'] ) );
		$stations    = array_filter( array_map( array( BangkokRentalRepository::class, 'station' ), $area['station_ids'] ) );
		$rail_modes  = array_values( array_unique( array_column( $stations, 'mode' ) ) );
		$tags        = $area['persona_tags'];
		$copy        = $area['public_copy'];
		?>
		<article class="thp-bkk-area-card" data-thp-bkk-area data-area-id="<?php echo esc_attr( $area['area_id'] ); ?>" data-min-one="<?php echo esc_attr( $bands['one_bedroom']['min_thb'] ); ?>" data-max-one="<?php echo esc_attr( $bands['one_bedroom']['max_thb'] ); ?>" data-min-two="<?php echo esc_attr( $bands['two_bedroom']['min_thb'] ); ?>" data-max-two="<?php echo esc_attr( $bands['two_bedroom']['max_thb'] ); ?>" data-lifestyle="<?php echo esc_attr( implode( '|', $tags ) ); ?>" data-rail="<?php echo esc_attr( implode( '|', $rail_modes ) ); ?>">
			<header>
				<div class="thp-bkk-card-top"><h3><?php echo esc_html( $copy['title'] ); ?><small><bdi lang="en" dir="ltr"><?php echo esc_html( $area['names']['en'] ); ?></bdi> <span aria-hidden="true">·</span> <bdi lang="th" dir="ltr"><?php echo esc_html( $area['names']['th'] ); ?></bdi></small></h3><span class="thp-bkk-corridor"><?php echo esc_html( $corridor['names']['he'] ?? $copy['eyebrow'] ); ?></span></div>
				<p class="thp-bkk-fit"><?php echo esc_html( $copy['summary'] ); ?></p>
			</header>
			<dl class="thp-bkk-prices">
				<div><dt>חדר שינה אחד</dt><dd><?php echo esc_html( self::price_band( $bands['one_bedroom'] ) ); ?></dd></div>
				<div><dt>שני חדרי שינה</dt><dd><?php echo esc_html( self::price_band( $bands['two_bedroom'] ) ); ?></dd></div>
			</dl>
			<ul class="thp-bkk-tags" aria-label="מאפייני האזור">
				<?php foreach ( $tags as $tag ) : ?><li><?php echo esc_html( self::tag_label( $tag ) ); ?></li><?php endforeach; ?>
			</ul>
			<details><summary><?php echo esc_html( $copy['action_label'] ); ?></summary>
				<div class="thp-bkk-area-detail">
					<p><strong>למי האזור מתאים</strong><?php echo esc_html( $area['fit_summary'] ); ?></p>
					<p><strong>מה כדאי לשקול</strong><?php echo esc_html( $area['tradeoff'] ); ?></p>
					<p><strong>רכבת קרובה</strong><?php self::render_station_labels( $stations ); ?></p>
					<p><strong>מחוזות רשמיים</strong><?php echo esc_html( implode( ', ', array_map( array( self::class, 'district_label' ), $districts ) ) ); ?></p>
					<?php foreach ( $area['micro_area_notes'] as $note ) : ?><p><strong><?php echo esc_html( $note['label'] ); ?></strong><?php echo esc_html( $note['detail'] ); ?></p><?php endforeach; ?>
					<p><strong>חיי יום יום</strong><?php echo esc_html( implode( ' · ', $area['daily_life_cues'] ) ); ?></p>
				</div>
			</details>
		</article>
		<?php
	}

	/**
	 * @return void
	 */
	private static function render_district_directory() {
		$districts = BangkokRentalRepository::districts();
		?>
		<details class="thp-bkk-district-directory">
			<summary>כל 50 המחוזות הרשמיים של בנגקוק</summary>
			<p>שמות כמו אסוק, תונג לו וראצ׳דה הם אזורי שוק שימושיים לחיפוש דירה. עיריית בנגקוק מחלקת את העיר ל-50 מחוזות רשמיים, ולכן אזור מגורים אחד יכול לחצות יותר ממחוז אחד.</p>
			<ul class="thp-bkk-district-list">
				<?php foreach ( $districts as $district ) : ?><li><?php echo esc_html( $district['names']['he'] ); ?><small><bdi lang="en" dir="ltr"><?php echo esc_html( $district['names']['en'] ); ?></bdi> <span aria-hidden="true">·</span> <bdi lang="th" dir="ltr"><?php echo esc_html( $district['names']['th'] ); ?></bdi></small></li><?php endforeach; ?>
			</ul>
		</details>
		<?php
	}

	/**
	 * @param array $registry Bangkok registry.
	 * @return void
	 */
	private static function render_planning( $registry ) {
		?>
		<div class="thp-bkk-planning">
			<section class="thp-bkk-cost" aria-labelledby="thp-bkk-cost-title" data-thp-bkk-calculator hidden>
				<p class="thp-kicker">מתכננים את הכניסה לדירה</p><h3 id="thp-bkk-cost-title">כמה כסף להכין לחתימה</h3>
				<p>בחרו שכר דירה חודשי וקבלו מסגרת מהירה לפיקדון ולתשלום הראשון.</p>
				<div class="thp-bkk-cost-field"><label for="thp-bkk-cost-rent">שכר דירה חודשי</label><output id="thp-bkk-cost-rent-output" for="thp-bkk-cost-rent" data-thp-bkk-cost-rent-output>30,000 באט</output><input id="thp-bkk-cost-rent" type="range" min="10000" max="100000" step="1000" value="30000" data-thp-bkk-cost-rent aria-describedby="thp-bkk-cost-rent-output" aria-valuetext="30,000 באט"></div>
				<div class="thp-bkk-cost-grid"><div><span>פיקדון לתכנון, עד שני חודשי שכירות</span><strong data-thp-bkk-cost-deposit>60,000 באט</strong></div><div><span>פיקדון ועוד חודש ראשון</span><strong data-thp-bkk-cost-entry>90,000 באט</strong></div></div>
				<small>זהו חישוב לתכנון מזומן בכניסה. בדקו בחוזה את הסכום המדויק ואת התנאים שחלים על המשכיר.</small>
			</section>
			<section class="thp-bkk-lease" aria-labelledby="thp-bkk-lease-title">
				<p class="thp-kicker">לפני שמעבירים כסף</p><h3 id="thp-bkk-lease-title">כללים שכדאי להכיר לפני כניסה לדירה</h3>
				<p>הנקודות הבאות מרכזות את הכללים העדכניים שמשפיעים על שוכרים ועל משכירים עסקיים.</p>
				<ul class="thp-bkk-legal-list">
					<?php foreach ( BangkokRentalRepository::facts() as $fact ) : ?><li><strong><?php echo esc_html( $fact['public_label'] ); ?></strong><?php echo esc_html( $fact['public_value'] ); ?></li><?php endforeach; ?>
				</ul>
			</section>
		</div>
		<?php
	}

	/**
	 * @param array $registry Bangkok registry.
	 * @return void
	 */
	private static function render_sources( $registry ) {
		$sources = BangkokRentalRepository::sources();
		?>
		<div class="thp-bkk-atlas-sources"><details><summary>מקורות למחירים, תחבורה, מחוזות וחוזים</summary><ul>
			<?php foreach ( $sources as $source ) : ?><li><a href="<?php echo esc_url( $source['url'] ); ?>" rel="noopener"><?php echo esc_html( $source['name'] ); ?></a>, <?php echo esc_html( $source['publisher'] ); ?></li><?php endforeach; ?>
		</ul></details></div>
		<?php
	}

	/**
	 * @param array $band Asking band.
	 * @return string
	 */
	private static function price_band( $band ) {
		return number_format_i18n( $band['min_thb'] ) . ' עד ' . number_format_i18n( $band['max_thb'] ) . ' באט';
	}

	/**
	 * @param array $stations Station records.
	 * @return void
	 */
	private static function render_station_labels( $stations ) {
		$count = count( $stations );
		$index = 0;
		foreach ( $stations as $station ) {
			++$index;
			?>
			<span class="thp-bkk-station-label"><bdi lang="en" dir="ltr"><?php echo esc_html( strtoupper( $station['mode'] ) . ' ' . $station['code'] ); ?></bdi> <?php echo esc_html( $station['names']['he'] ); ?></span><?php echo $index < $count ? ', ' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php
		}
	}

	/**
	 * @param array $district District record.
	 * @return string
	 */
	private static function district_label( $district ) {
		return $district['names']['he'];
	}

	/**
	 * @param string $tag Machine tag.
	 * @return string
	 */
	private static function tag_label( $tag ) {
		$labels = array(
			'central'   => 'מרכזי',
			'value'     => 'תמורה לתקציב',
			'nightlife' => 'חיי לילה',
			'quiet'     => 'רחובות שקטים',
			'family'    => 'משפחות',
			'business'  => 'עסקים',
			'food'      => 'אוכל ובתי קפה',
			'rail'      => 'רכבת קרובה',
			'green'     => 'פארקים',
			'upscale'   => 'רמה גבוהה',
			'local'     => 'אופי מקומי',
		);
		return $labels[ $tag ] ?? $tag;
	}
}
