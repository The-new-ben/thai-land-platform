<?php
/**
 * Accessible server-rendered Digital Islands list helpers.
 *
 * @package Thailand_Platform
 */

namespace Thailand_Platform\DigitalIslands;

final class View {
	/**
	 * @param array $entities Public-safe entities.
	 * @return array
	 */
	public static function grouped_entities( $entities ) {
		$groups = array();
		foreach ( $entities as $entity ) {
			$type = $entity['entity_type'];
			if ( ! isset( $groups[ $type ] ) ) {
				$groups[ $type ] = array(
					'label'    => self::type_label( $type ),
					'entities' => array(),
				);
			}
			$groups[ $type ]['entities'][] = $entity;
		}

		uasort(
			$groups,
			static function ( $left, $right ) {
				return strcmp( $left['label'], $right['label'] );
			}
		);
		return $groups;
	}

	/** @param array $entity Entity. @return string */
	public static function name( $entity ) {
		foreach ( array( 'he', 'en', 'th' ) as $locale ) {
			$name = $entity['names'][ $locale ] ?? null;
			if ( is_string( $name ) && '' !== $name ) {
				return $name;
			}
		}
		return '';
	}

	/** @param string $type Entity type. @return string */
	public static function type_label( $type ) {
		$labels = array(
			'banking'             => 'בנקאות ודואר',
			'education'           => 'חינוך ומשפחה',
			'government'          => 'רשויות ומשרדי ממשלה',
			'health'              => 'בריאות',
			'landmark'            => 'נקודות התמצאות',
			'postal'              => 'דואר',
			'professional_service' => 'שירותים מקצועיים',
			'property_project'    => 'פרויקטים למגורים',
			'road'                => 'צירי דרך להתמצאות',
			'settlement'          => 'יישובים ואזורים',
			'telecom'             => 'תקשורת',
			'transport'           => 'נמלים ותחבורה',
			'utility'             => 'מים וחשמל',
		);
		return $labels[ $type ] ?? 'מקומות ושירותים';
	}

	/** @param array $dimensions Trusted decision dimension IDs. @return array */
	public static function decision_dimension_labels( $dimensions ) {
		$labels = array(
			'offer_availability'                    => 'זמינות ההצעה בתאריך הבדיקה',
			'asking_price'                          => 'מחיר מבוקש, מטבע, מסים ותנאי תשלום',
			'parcel_reference_match'                => 'התאמה בין המיקום, מספר החלקה והמסמכים',
			'title_document_claim'                   => 'סוג מסמך הזכות והפרטים הרשומים בו',
			'seller_authority'                      => 'זהות המוכר וסמכותו לבצע את העסקה',
			'road_access'                           => 'גישה חוקית ומעשית מהדרך אל החלקה',
			'utility_access'                        => 'חיבור אפשרי למים, חשמל, תקשורת ופינוי פסולת',
			'planning_classification'               => 'סיווג תכנוני ושימושים שמותר לבקש',
			'protected_area_overlap'                => 'חפיפה עם יער, שטח מוגן או מגבלה סביבתית',
			'slope_and_drainage'                    => 'שיפוע, ניקוז, יציבות קרקע וסיכון להצפה',
			'coastal_and_environmental_constraints' => 'מרחק מהחוף ותנאים סביבתיים מקומיים',
			'ownership_structure'                   => 'מבנה החזקה חוקי שמתאים לרוכש ולנכס',
			'building_permit'                       => 'היתרים ואישורים שנדרשים לפני בנייה',
		);
		$result = array();
		foreach ( is_array( $dimensions ) ? $dimensions : array() as $dimension ) {
			if ( isset( $labels[ $dimension ] ) ) {
				$result[] = array( 'dimension_id' => $dimension, 'label_he' => $labels[ $dimension ] );
			}
		}
		return $result;
	}
}
