<?php
/**
 * Generated Bangkok rental-area registry.
 *
 * Run scripts/build_bangkok_rental_registry.py to rebuild this file.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return json_decode(
	<<<'THAILAND_PLATFORM_BANGKOK_RENTAL_JSON'
{
  "area_id_by_alias": {
    "en": {
      "aree": "market:bangkok:ari",
      "ari": "market:bangkok:ari",
      "ari bangkok": "market:bangkok:ari",
      "asok": "market:bangkok:asok",
      "asok montri": "market:bangkok:asok",
      "asoke": "market:bangkok:asok",
      "ekamai": "market:bangkok:ekkamai",
      "ekkamai": "market:bangkok:ekkamai",
      "ekkamai road": "market:bangkok:ekkamai",
      "huai khwang": "market:bangkok:huai-khwang",
      "huai kwang": "market:bangkok:huai-khwang",
      "huay khwang": "market:bangkok:huai-khwang",
      "on nut": "market:bangkok:on-nut",
      "onnut": "market:bangkok:on-nut",
      "phra ram 9": "market:bangkok:phra-ram-9",
      "phrom phong": "market:bangkok:phrom-phong",
      "phromphong": "market:bangkok:phrom-phong",
      "prom phong": "market:bangkok:phrom-phong",
      "rama 9": "market:bangkok:phra-ram-9",
      "rama ix": "market:bangkok:phra-ram-9",
      "sathon": "market:bangkok:sathon",
      "sathon road": "market:bangkok:sathon",
      "sathorn": "market:bangkok:sathon",
      "si lom": "market:bangkok:silom",
      "silom": "market:bangkok:silom",
      "silom road": "market:bangkok:silom",
      "thong lo": "market:bangkok:thong-lo",
      "thong lor": "market:bangkok:thong-lo",
      "thonglor": "market:bangkok:thong-lo"
    },
    "he": {
      "און נאט": "market:bangkok:on-nut",
      "און נוט": "market:bangkok:on-nut",
      "אונוט": "market:bangkok:on-nut",
      "אסוק": "market:bangkok:asok",
      "אסוקה": "market:bangkok:asok",
      "אקאמאיי": "market:bangkok:ekkamai",
      "אקאמי": "market:bangkok:ekkamai",
      "אקמאי": "market:bangkok:ekkamai",
      "ארי": "market:bangkok:ari",
      "ארי בנגקוק": "market:bangkok:ari",
      "הואי קוואנג": "market:bangkok:huai-khwang",
      "הווי קוואנג": "market:bangkok:huai-khwang",
      "חואי קוואנג": "market:bangkok:huai-khwang",
      "טונג לו": "market:bangkok:thong-lo",
      "טונגלור": "market:bangkok:thong-lo",
      "סאטון": "market:bangkok:sathon",
      "סאתון": "market:bangkok:sathon",
      "סאתורן": "market:bangkok:sathon",
      "סוי ארי": "market:bangkok:ari",
      "סי לום": "market:bangkok:silom",
      "סילום": "market:bangkok:silom",
      "סילום בנגקוק": "market:bangkok:silom",
      "פרה ראם 9": "market:bangkok:phra-ram-9",
      "פרום פונג": "market:bangkok:phrom-phong",
      "פרום פונג סוקומוויט": "market:bangkok:phrom-phong",
      "פרומפונג": "market:bangkok:phrom-phong",
      "צומת אסוק": "market:bangkok:asok",
      "ראמה 9": "market:bangkok:phra-ram-9",
      "רמה 9": "market:bangkok:phra-ram-9",
      "תונג לו": "market:bangkok:thong-lo"
    },
    "th": {
      "ซอยอารีย์": "market:bangkok:ari",
      "ถนนสาทร": "market:bangkok:sathon",
      "ถนนสีลม": "market:bangkok:silom",
      "ทองหล่อ": "market:bangkok:thong-lo",
      "ทองหล่อสุขุมวิท 55": "market:bangkok:thong-lo",
      "พระราม 9": "market:bangkok:phra-ram-9",
      "พระรามเก้า": "market:bangkok:phra-ram-9",
      "พร้อมพงษ์": "market:bangkok:phrom-phong",
      "พร้อมพงษ์สุขุมวิท": "market:bangkok:phrom-phong",
      "สาทร": "market:bangkok:sathon",
      "สีลม": "market:bangkok:silom",
      "สุขุมวิท 63": "market:bangkok:ekkamai",
      "ห้วยขวาง": "market:bangkok:huai-khwang",
      "ห้วยขวางรัชดา": "market:bangkok:huai-khwang",
      "อารีย์": "market:bangkok:ari",
      "อโศก": "market:bangkok:asok",
      "อโศกมนตรี": "market:bangkok:asok",
      "อ่อนนุช": "market:bangkok:on-nut",
      "อ่อนนุชสุขุมวิท 77": "market:bangkok:on-nut",
      "เอกมัย": "market:bangkok:ekkamai"
    }
  },
  "area_ids_by_corridor_id": {
    "corridor:bangkok:ari-phaya-thai": [
      "market:bangkok:ari"
    ],
    "corridor:bangkok:central-sukhumvit": [
      "market:bangkok:asok",
      "market:bangkok:phrom-phong",
      "market:bangkok:thong-lo",
      "market:bangkok:ekkamai"
    ],
    "corridor:bangkok:east-sukhumvit": [
      "market:bangkok:on-nut"
    ],
    "corridor:bangkok:rama9-ratchada": [
      "market:bangkok:phra-ram-9",
      "market:bangkok:huai-khwang"
    ],
    "corridor:bangkok:silom-sathon": [
      "market:bangkok:silom",
      "market:bangkok:sathon"
    ]
  },
  "area_order": [
    "market:bangkok:asok",
    "market:bangkok:phrom-phong",
    "market:bangkok:thong-lo",
    "market:bangkok:ekkamai",
    "market:bangkok:on-nut",
    "market:bangkok:ari",
    "market:bangkok:silom",
    "market:bangkok:sathon",
    "market:bangkok:phra-ram-9",
    "market:bangkok:huai-khwang"
  ],
  "areas_by_id": {
    "market:bangkok:ari": {
      "aliases": {
        "en": [
          "Aree",
          "Ari Bangkok"
        ],
        "he": [
          "ארי בנגקוק",
          "סוי ארי"
        ],
        "th": [
          "ซอยอารีย์"
        ]
      },
      "area_id": "market:bangkok:ari",
      "coordinates": {
        "basis": "market-area centroid",
        "lat": 13.7798,
        "lng": 100.5446
      },
      "corridor_id": "corridor:bangkok:ari-phaya-thai",
      "daily_life_cues": [
        "בתי קפה ומסעדות בתוך הרחובות",
        "גישה ישירה ב-BTS לסיאם",
        "אווירה שכונתית בשעות הערב",
        "שילוב של בנייני בוטיק ומגדלים"
      ],
      "fit_summary": "מתאים למי שמחפש רחובות צדדיים נעימים, בתי קפה, אוכל מקומי ו-BTS ישיר בלי לחיות בתוך מרכז התיירות של סוקומוויט.",
      "indexing_policy": "non_indexable",
      "kind": "market_area",
      "map_position": {
        "x_percent": 27.3,
        "y_percent": 11.3
      },
      "micro_area_notes": [
        {
          "detail": "הלב הפעיל של ארי, עם אוכל, בתי קפה וגישה קצרה יחסית לתחנת BTS.",
          "label": "פהון יותין 7"
        },
        {
          "detail": "רחובות מגורים עמוקים ושקטים יותר, עם בתים נמוכים ובנייני בוטיק.",
          "label": "ארי סמפאן"
        },
        {
          "detail": "אפשרות מעשית למי שרוצה שתי תחנות קרובות יותר ומחפש בניינים חדשים יחסית.",
          "label": "הקצה לכיוון סאנאם פאו"
        }
      ],
      "monthly_asking_bands": {
        "basis_label": "קונדו מרוהט בחוזה טיפוסי של 12 חודשים",
        "checked_on": "2026-08-10",
        "currency": "THB",
        "one_bedroom": {
          "max_thb": 35000,
          "min_thb": 18000
        },
        "pricing_method_id": "method:bangkok:rental-asking-bands",
        "source_ids": [
          "source:propertyscout:bangkok-condos",
          "source:ddproperty:bangkok-condos",
          "source:propertyhub:bangkok-condos"
        ],
        "two_bedroom": {
          "max_thb": 70000,
          "min_thb": 35000
        },
        "unit": "month"
      },
      "names": {
        "en": "Ari",
        "he": "ארי",
        "th": "อารีย์"
      },
      "official_district_ids": [
        "geo:th:bma:1014"
      ],
      "persona_tags": [
        "quiet",
        "food",
        "local",
        "green"
      ],
      "public_copy": {
        "action_label": "לגילוי הרחובות של ארי",
        "eyebrow": "שכונה עם קצב משלה",
        "summary": "ארי מרגישה כמו שכונה בתוך העיר הגדולה, עם רחובות שקטים, אוכל טוב ותחנת BTS אחת שמחברת למרכז. החיפוש הטוב מתחיל בהחלטה בין קרבה לתחנה לבין עומק ושקט.",
        "title": "ארי למי שרוצה בנגקוק מקומית, ירוקה ומחוברת"
      },
      "station_ids": [
        "transit:bts:n5"
      ],
      "tradeoff": "מלאי הדירות קטן יותר מאשר בצירים הגדולים, וחלק מהאפשרויות הטובות נמצאות במרחק הליכה ארוך יחסית מהתחנה."
    },
    "market:bangkok:asok": {
      "aliases": {
        "en": [
          "Asoke",
          "Asok Montri"
        ],
        "he": [
          "אסוקה",
          "צומת אסוק"
        ],
        "th": [
          "อโศกมนตรี"
        ]
      },
      "area_id": "market:bangkok:asok",
      "coordinates": {
        "basis": "market-area centroid",
        "lat": 13.7367,
        "lng": 100.5608
      },
      "corridor_id": "corridor:bangkok:central-sukhumvit",
      "daily_life_cues": [
        "מעבר ישיר בין BTS ל-MRT",
        "גישה נוחה לפארק בנג׳קיטי",
        "קניונים וסופרמרקטים במרחק הליכה",
        "מבחר רחב של מרפאות ומסעדות"
      ],
      "fit_summary": "בחירה חזקה למי שרוצה לעבור בין BTS ל-MRT, להגיע במהירות לאזורי משרדים ולנהל יום שלם כמעט בלי רכב.",
      "indexing_policy": "non_indexable",
      "kind": "market_area",
      "map_position": {
        "x_percent": 45.3,
        "y_percent": 59.2
      },
      "micro_area_notes": [
        {
          "detail": "הכי קצר לרכבות, לקניות ולמשרדים, עם תנועה צפופה כמעט לאורך כל היום.",
          "label": "סביב צומת אסוק"
        },
        {
          "detail": "סמטאות מגורים שמאפשרות לבחור בין קרבה לצומת לבין רחוב רגוע יותר.",
          "label": "סוקומוויט 16 עד 23"
        },
        {
          "detail": "מתאים יותר למי שנוסע לכיוון פטצ׳בורי ופרה ראם 9 דרך MRT או כביש.",
          "label": "צפון אסוק מונטרי"
        }
      ],
      "monthly_asking_bands": {
        "basis_label": "קונדו מרוהט בחוזה טיפוסי של 12 חודשים",
        "checked_on": "2026-08-10",
        "currency": "THB",
        "one_bedroom": {
          "max_thb": 45000,
          "min_thb": 22000
        },
        "pricing_method_id": "method:bangkok:rental-asking-bands",
        "source_ids": [
          "source:propertyscout:bangkok-condos",
          "source:ddproperty:bangkok-condos",
          "source:propertyhub:bangkok-condos"
        ],
        "two_bedroom": {
          "max_thb": 90000,
          "min_thb": 45000
        },
        "unit": "month"
      },
      "names": {
        "en": "Asok",
        "he": "אסוק",
        "th": "อโศก"
      },
      "official_district_ids": [
        "geo:th:bma:1039",
        "geo:th:bma:1033"
      ],
      "persona_tags": [
        "central",
        "business",
        "rail",
        "green"
      ],
      "public_copy": {
        "action_label": "לבדיקת המיקרו אזורים באסוק",
        "eyebrow": "הצומת שמחבר את העיר",
        "summary": "אסוק מציבה אתכם בנקודת המפגש של BTS ו-MRT, קרוב למשרדים, פארק, קניות ושירותים. הבחירה המדויקת היא בין דירה ליד הצומת לבין שקט עמוק יותר בסמטה.",
        "title": "אסוק למי שרוצה להיות מחובר לכל כיוון"
      },
      "station_ids": [
        "transit:bts:e4",
        "transit:mrt:bl22"
      ],
      "tradeoff": "הצומת עמוס ורועש בשעות השיא, ודירות שקטות באמת נמצאות בדרך כלל עמוק יותר בתוך הסמטאות."
    },
    "market:bangkok:ekkamai": {
      "aliases": {
        "en": [
          "Ekamai",
          "Ekkamai Road"
        ],
        "he": [
          "אקמאי",
          "אקאמי"
        ],
        "th": [
          "สุขุมวิท 63"
        ]
      },
      "area_id": "market:bangkok:ekkamai",
      "coordinates": {
        "basis": "market-area centroid",
        "lat": 13.7195,
        "lng": 100.585
      },
      "corridor_id": "corridor:bangkok:central-sukhumvit",
      "daily_life_cues": [
        "גישה ישירה לקו BTS",
        "מסוף אוטובוסים למזרח תאילנד",
        "בתי קפה ומסעדות שכונתיות",
        "מרכזי קניות קטנים ושירותים יומיומיים"
      ],
      "fit_summary": "מתאים לזוגות ולאנשי מקצוע שרוצים להישאר במרכז סוקומוויט, לקבל מבחר אוכל ושירותים ולשלם פחות מטונג לו בחלק מהבניינים.",
      "indexing_policy": "non_indexable",
      "kind": "market_area",
      "map_position": {
        "x_percent": 72.2,
        "y_percent": 78.3
      },
      "micro_area_notes": [
        {
          "detail": "נוח לרכבת, לקניון השכונתי ולמסוף האוטובוסים למזרח תאילנד.",
          "label": "ליד BTS אקאמאיי"
        },
        {
          "detail": "רחובות מגורים עם מסעדות ובתי קפה, באיזון טוב בין שקט לפעילות.",
          "label": "אקאמאיי 10 עד 12"
        },
        {
          "detail": "מבחר גדול יותר של בניינים ושירותים, לצד נסיעה ארוכה יותר לתחנת BTS בשעות עומס.",
          "label": "צפון אקאמאיי"
        }
      ],
      "monthly_asking_bands": {
        "basis_label": "קונדו מרוהט בחוזה טיפוסי של 12 חודשים",
        "checked_on": "2026-08-10",
        "currency": "THB",
        "one_bedroom": {
          "max_thb": 42000,
          "min_thb": 20000
        },
        "pricing_method_id": "method:bangkok:rental-asking-bands",
        "source_ids": [
          "source:propertyscout:bangkok-condos",
          "source:ddproperty:bangkok-condos",
          "source:propertyhub:bangkok-condos"
        ],
        "two_bedroom": {
          "max_thb": 85000,
          "min_thb": 40000
        },
        "unit": "month"
      },
      "names": {
        "en": "Ekkamai",
        "he": "אקאמאיי",
        "th": "เอกมัย"
      },
      "official_district_ids": [
        "geo:th:bma:1039",
        "geo:th:bma:1033"
      ],
      "persona_tags": [
        "central",
        "food",
        "quiet",
        "local"
      ],
      "public_copy": {
        "action_label": "להשוואת האפשרויות באקאמאיי",
        "eyebrow": "מרכזי, אבל מעט רגוע יותר",
        "summary": "אקאמאיי שומרת על הגישה של סוקומוויט ומוסיפה רחובות מגורים, אוכל מקומי ותחושה מעט פחות ראוותנית מטונג לו. מיקום הבניין לאורך סוי 63 קובע את קצב היום.",
        "title": "אקאמאיי לאיזון בין חיבור לעיר לחיים שכונתיים"
      },
      "station_ids": [
        "transit:bts:e7"
      ],
      "tradeoff": "גם כאן הרחוב מתארך הרחק מהתחנה, והאווירה משתנה בין אזור ה-BTS לבין החלק הצפוני העמוס יותר."
    },
    "market:bangkok:huai-khwang": {
      "aliases": {
        "en": [
          "Huay Khwang",
          "Huai Kwang"
        ],
        "he": [
          "הווי קוואנג",
          "חואי קוואנג"
        ],
        "th": [
          "ห้วยขวางรัชดา"
        ]
      },
      "area_id": "market:bangkok:huai-khwang",
      "coordinates": {
        "basis": "market-area centroid",
        "lat": 13.7786,
        "lng": 100.5737
      },
      "corridor_id": "corridor:bangkok:rama9-ratchada",
      "daily_life_cues": [
        "שוק ואוכל רחוב עד שעות מאוחרות",
        "MRT ישיר לפרה ראם 9 ולאסוק",
        "חנויות ושירותים מקומיים רבים",
        "מבחר דירות בתקציב בינוני ונגיש"
      ],
      "fit_summary": "מתאים למי שרוצה MRT, אוכל ושירותים עד שעות מאוחרות ומבחר דירות רחב במחיר נגיש יותר מאזורי סוקומוויט המרכזיים.",
      "indexing_policy": "non_indexable",
      "kind": "market_area",
      "map_position": {
        "x_percent": 59.7,
        "y_percent": 12.7
      },
      "micro_area_notes": [
        {
          "detail": "נוח מאוד לרכבת, לשוק ולשירותים שפועלים עד מאוחר, עם תנועה אנושית גבוהה.",
          "label": "סביב MRT הואי קוואנג"
        },
        {
          "detail": "רחוב עמוס באוכל מקומי, חנויות ודירות, עם תחושה שכונתית חזקה יותר.",
          "label": "פראצ׳ה ראט באמפן"
        },
        {
          "detail": "לעיתים מציע בניינים שקטים יותר וטווח מחירים גמיש, ועדיין נשאר על קו MRT.",
          "label": "הקצה לכיוון סוטיסאן"
        }
      ],
      "monthly_asking_bands": {
        "basis_label": "קונדו מרוהט בחוזה טיפוסי של 12 חודשים",
        "checked_on": "2026-08-10",
        "currency": "THB",
        "one_bedroom": {
          "max_thb": 25000,
          "min_thb": 13000
        },
        "pricing_method_id": "method:bangkok:rental-asking-bands",
        "source_ids": [
          "source:propertyscout:bangkok-condos",
          "source:ddproperty:bangkok-condos",
          "source:propertyhub:bangkok-condos"
        ],
        "two_bedroom": {
          "max_thb": 50000,
          "min_thb": 25000
        },
        "unit": "month"
      },
      "names": {
        "en": "Huai Khwang",
        "he": "הואי קוואנג",
        "th": "ห้วยขวาง"
      },
      "official_district_ids": [
        "geo:th:bma:1017"
      ],
      "persona_tags": [
        "nightlife",
        "rail",
        "value",
        "local"
      ],
      "public_copy": {
        "action_label": "לבדיקת הרחובות בהואי קוואנג",
        "eyebrow": "חיי רחוב אמיתיים על קו MRT",
        "summary": "הואי קוואנג מחברת MRT, שוק לילה, אוכל מקומי ומלאי דירות גדול. היא בחירה מעשית למי שמעדיף אנרגיה עירונית ותקציב נוח על פני שקט של רחוב מגורים קטן.",
        "title": "הואי קוואנג למי שרוצה עיר פעילה ומחיר נגיש"
      },
      "station_ids": [
        "transit:mrt:bl17",
        "transit:mrt:bl18",
        "transit:mrt:bl19"
      ],
      "tradeoff": "האזור פעיל ורועש סביב השוק והרחובות הראשיים, והאופי המקומי הצפוף אינו מתאים לכל מי שמחפש סביבת מגורים רגועה."
    },
    "market:bangkok:on-nut": {
      "aliases": {
        "en": [
          "Onnut",
          "On-Nut"
        ],
        "he": [
          "אונוט",
          "און נאט"
        ],
        "th": [
          "อ่อนนุชสุขุมวิท 77"
        ]
      },
      "area_id": "market:bangkok:on-nut",
      "coordinates": {
        "basis": "market-area centroid",
        "lat": 13.7056,
        "lng": 100.601
      },
      "corridor_id": "corridor:bangkok:east-sukhumvit",
      "daily_life_cues": [
        "סופרמרקטים גדולים ליד התחנה",
        "דוכני אוכל ושווקים מקומיים",
        "מבחר קונדו חדש וישן",
        "נסיעה ישירה ב-BTS למרכז סוקומוויט"
      ],
      "fit_summary": "מתאים למי שרוצה קו BTS, קניות יומיומיות ומבחר דירות גדול בתקציב נגיש יותר מהאזורים המרכזיים של סוקומוויט.",
      "indexing_policy": "non_indexable",
      "kind": "market_area",
      "map_position": {
        "x_percent": 90,
        "y_percent": 93.8
      },
      "micro_area_notes": [
        {
          "detail": "האפשרות הנוחה ביותר לרכבת ולסופרמרקטים, עם ביקוש גבוה לבניינים במרחק הליכה.",
          "label": "סביב BTS און נוט"
        },
        {
          "detail": "מבחר רחב של בניינים ומחירים, אך חשוב למדוד את הדרך מהבניין לרחוב הראשי.",
          "label": "סוקומוויט 77"
        },
        {
          "detail": "חיבור נוח יותר למרכז ותחושה עירונית, לעיתים במחיר מעט גבוה יותר מאון נוט העמוקה.",
          "label": "הקצה לכיוון פרה קנונג"
        }
      ],
      "monthly_asking_bands": {
        "basis_label": "קונדו מרוהט בחוזה טיפוסי של 12 חודשים",
        "checked_on": "2026-08-10",
        "currency": "THB",
        "one_bedroom": {
          "max_thb": 25000,
          "min_thb": 12000
        },
        "pricing_method_id": "method:bangkok:rental-asking-bands",
        "source_ids": [
          "source:propertyscout:bangkok-condos",
          "source:ddproperty:bangkok-condos",
          "source:propertyhub:bangkok-condos"
        ],
        "two_bedroom": {
          "max_thb": 50000,
          "min_thb": 24000
        },
        "unit": "month"
      },
      "names": {
        "en": "On Nut",
        "he": "און נוט",
        "th": "อ่อนนุช"
      },
      "official_district_ids": [
        "geo:th:bma:1009",
        "geo:th:bma:1034"
      ],
      "persona_tags": [
        "value",
        "rail",
        "local"
      ],
      "public_copy": {
        "action_label": "לבדיקת דירות ואזורי משנה באון נוט",
        "eyebrow": "יותר דירה באותו תקציב",
        "summary": "און נוט משלבת רכבת ישירה, קניות ואוכל מקומי עם טווח מחירים נגיש יחסית. היא מתאימה במיוחד למי שמוכן להוסיף זמן נסיעה כדי לקבל דירה גדולה או חדשה יותר.",
        "title": "און נוט לנקודת פתיחה נוחה במזרח סוקומוויט"
      },
      "station_ids": [
        "transit:bts:e8",
        "transit:bts:e9"
      ],
      "tradeoff": "הנסיעה למרכז ארוכה יותר, ובניינים עמוק בתוך סוי 77 יכולים להוסיף תלות במונית, אופנוע או שאטל."
    },
    "market:bangkok:phra-ram-9": {
      "aliases": {
        "en": [
          "Rama 9",
          "Rama IX"
        ],
        "he": [
          "ראמה 9",
          "רמה 9"
        ],
        "th": [
          "พระรามเก้า"
        ]
      },
      "area_id": "market:bangkok:phra-ram-9",
      "coordinates": {
        "basis": "market-area centroid",
        "lat": 13.757,
        "lng": 100.5658
      },
      "corridor_id": "corridor:bangkok:rama9-ratchada",
      "daily_life_cues": [
        "קניונים וסופרמרקטים ליד התחנה",
        "מגדלי משרדים חדשים יחסית",
        "קו MRT ישיר לאסוק ולסילום",
        "מבחר גדול של בנייני קונדו"
      ],
      "fit_summary": "מתאים למי שעובד בציר ראצ׳דה או אסוק, מעדיף בניין חדש יחסית ורוצה MRT, קניות ומשרדים בטווח תקציב בינוני.",
      "indexing_policy": "non_indexable",
      "kind": "market_area",
      "map_position": {
        "x_percent": 50.9,
        "y_percent": 36.7
      },
      "micro_area_notes": [
        {
          "detail": "הכי נוח לרכבת, לקניונים ולמשרדים, עם תנועה ערה ומגדלים צפופים.",
          "label": "סביב MRT פרה ראם 9"
        },
        {
          "detail": "מבחר גדול של קונדו ושירותים, אך חשוב לבדוק חציית כבישים וכניסה לבניין.",
          "label": "צומת ראצ׳דאפיסק"
        },
        {
          "detail": "גישה טובה לבילוי, מופעים ומרכזי קניות נוספים לאורך תחנה אחת של MRT.",
          "label": "לכיוון מרכז התרבות"
        }
      ],
      "monthly_asking_bands": {
        "basis_label": "קונדו מרוהט בחוזה טיפוסי של 12 חודשים",
        "checked_on": "2026-08-10",
        "currency": "THB",
        "one_bedroom": {
          "max_thb": 30000,
          "min_thb": 15000
        },
        "pricing_method_id": "method:bangkok:rental-asking-bands",
        "source_ids": [
          "source:propertyscout:bangkok-condos",
          "source:ddproperty:bangkok-condos",
          "source:propertyhub:bangkok-condos"
        ],
        "two_bedroom": {
          "max_thb": 60000,
          "min_thb": 28000
        },
        "unit": "month"
      },
      "names": {
        "en": "Phra Ram 9",
        "he": "פרה ראם 9",
        "th": "พระราม 9"
      },
      "official_district_ids": [
        "geo:th:bma:1017",
        "geo:th:bma:1026"
      ],
      "persona_tags": [
        "business",
        "rail",
        "value",
        "central"
      ],
      "public_copy": {
        "action_label": "להשוואת בניינים סביב פרה ראם 9",
        "eyebrow": "מרכז עירוני חדש על קו MRT",
        "summary": "פרה ראם 9 מרכזת קונדו, משרדים וקניות סביב תחנת MRT אחת חזקה. היא מתאימה למי שמעדיף נוחות מודרנית ומוכן לחיות בסביבה של צירים רחבים ומגדלים.",
        "title": "פרה ראם 9 לבניין חדש, רכבת ותקציב מאוזן"
      },
      "station_ids": [
        "transit:mrt:bl20",
        "transit:mrt:bl19"
      ],
      "tradeoff": "המרחב סביב הצומת בנוי לכבישים רחבים ולקניונים, ולכן חוויית ההליכה פחות נעימה ברחובות מסוימים מאשר בשכונות קטנות."
    },
    "market:bangkok:phrom-phong": {
      "aliases": {
        "en": [
          "Prom Phong",
          "Phromphong"
        ],
        "he": [
          "פרומפונג",
          "פרום פונג סוקומוויט"
        ],
        "th": [
          "พร้อมพงษ์สุขุมวิท"
        ]
      },
      "area_id": "market:bangkok:phrom-phong",
      "coordinates": {
        "basis": "market-area centroid",
        "lat": 13.7304,
        "lng": 100.5696
      },
      "corridor_id": "corridor:bangkok:central-sukhumvit",
      "daily_life_cues": [
        "פארק בנג׳סירי ליד התחנה",
        "סופרמרקטים יפניים ובינלאומיים",
        "בתי חולים ומרפאות קרובים",
        "מסעדות וקניות ברמה גבוהה"
      ],
      "fit_summary": "מתאים למשפחות ולאנשי מקצוע שמעדיפים סביבת מגורים מטופחת, פארק, קניות איכותיות ושירותים בינלאומיים קרובים.",
      "indexing_policy": "non_indexable",
      "kind": "market_area",
      "map_position": {
        "x_percent": 55.1,
        "y_percent": 66.2
      },
      "micro_area_notes": [
        {
          "detail": "קרוב לתחנה, לפארק ולקניונים, עם מגדלים חדשים יותר ותנועה ערה.",
          "label": "סוקומוויט 24"
        },
        {
          "detail": "רשת רחובות מגורים עם מסעדות, מרפאות ושירותים הפונים לקהילה הבינלאומית.",
          "label": "סוקומוויט 39"
        },
        {
          "detail": "אווירה שכונתית יותר, בתי חולים ושירותי משפחה, אך חלק מהכתובות דורשות נסיעה קצרה לתחנה.",
          "label": "סוקומוויט 49"
        }
      ],
      "monthly_asking_bands": {
        "basis_label": "קונדו מרוהט בחוזה טיפוסי של 12 חודשים",
        "checked_on": "2026-08-10",
        "currency": "THB",
        "one_bedroom": {
          "max_thb": 60000,
          "min_thb": 28000
        },
        "pricing_method_id": "method:bangkok:rental-asking-bands",
        "source_ids": [
          "source:propertyscout:bangkok-condos",
          "source:ddproperty:bangkok-condos",
          "source:propertyhub:bangkok-condos"
        ],
        "two_bedroom": {
          "max_thb": 120000,
          "min_thb": 55000
        },
        "unit": "month"
      },
      "names": {
        "en": "Phrom Phong",
        "he": "פרום פונג",
        "th": "พร้อมพงษ์"
      },
      "official_district_ids": [
        "geo:th:bma:1039",
        "geo:th:bma:1033"
      ],
      "persona_tags": [
        "upscale",
        "family",
        "green",
        "food"
      ],
      "public_copy": {
        "action_label": "להשוואת הרחובות בפרום פונג",
        "eyebrow": "נוחות יומיומית ברמה גבוהה",
        "summary": "פרום פונג מחברת פארק, רכבת, קניות, בריאות ואוכל בתוך אזור מגורים מבוקש במיוחד. כדאי למדוד את הדרך האמיתית מהבניין לתחנה, ולא להסתפק בשם השכונה.",
        "title": "פרום פונג למשפחה ולחיים עירוניים מלוטשים"
      },
      "station_ids": [
        "transit:bts:e5"
      ],
      "tradeoff": "המחירים גבוהים ביחס לרוב בנגקוק, והמרחק מהתחנה בתוך סוי 39 או סוי 49 משנה מאוד את הנוחות היומית."
    },
    "market:bangkok:sathon": {
      "aliases": {
        "en": [
          "Sathorn",
          "Sathon Road"
        ],
        "he": [
          "סאטון",
          "סאתון"
        ],
        "th": [
          "ถนนสาทร"
        ]
      },
      "area_id": "market:bangkok:sathon",
      "coordinates": {
        "basis": "market-area centroid",
        "lat": 13.7195,
        "lng": 100.527
      },
      "corridor_id": "corridor:bangkok:silom-sathon",
      "daily_life_cues": [
        "קרבה למגדלי המשרדים הגדולים",
        "מסעדות בינלאומיות ובתי ספר",
        "רחובות פנימיים שקטים יחסית",
        "גישה ל-BTS בחלקים המערביים"
      ],
      "fit_summary": "מתאים לאנשי מקצוע ולמשפחות שמבקשים קרבה למרכז העסקים, בניינים איכותיים ואפשרות לבחור בין מגדל עירוני לרחוב מגורים שקט.",
      "indexing_policy": "non_indexable",
      "kind": "market_area",
      "map_position": {
        "x_percent": 7.8,
        "y_percent": 78.3
      },
      "micro_area_notes": [
        {
          "detail": "החלק הנוח ביותר למגדלי המשרדים ול-BTS, עם קצב עירוני מהיר לאורך היום.",
          "label": "צ׳ונג נונסי"
        },
        {
          "detail": "גישה טובה לרכבת ולנהר, עם שילוב של בתי מלון, בתי ספר ובנייני מגורים.",
          "label": "סוראסאק"
        },
        {
          "detail": "אזור מגורים שקט וירוק יותר, אך תלוי יותר במונית או ברכב להגעה לרכבת.",
          "label": "יין אקאט והרחובות הפנימיים"
        }
      ],
      "monthly_asking_bands": {
        "basis_label": "קונדו מרוהט בחוזה טיפוסי של 12 חודשים",
        "checked_on": "2026-08-10",
        "currency": "THB",
        "one_bedroom": {
          "max_thb": 50000,
          "min_thb": 22000
        },
        "pricing_method_id": "method:bangkok:rental-asking-bands",
        "source_ids": [
          "source:propertyscout:bangkok-condos",
          "source:ddproperty:bangkok-condos",
          "source:propertyhub:bangkok-condos"
        ],
        "two_bedroom": {
          "max_thb": 110000,
          "min_thb": 45000
        },
        "unit": "month"
      },
      "names": {
        "en": "Sathon",
        "he": "סאתורן",
        "th": "สาทร"
      },
      "official_district_ids": [
        "geo:th:bma:1028",
        "geo:th:bma:1004"
      ],
      "persona_tags": [
        "business",
        "family",
        "quiet",
        "upscale"
      ],
      "public_copy": {
        "action_label": "למציאת החלק המתאים בסאתורן",
        "eyebrow": "מרכז עסקים עם כיסי מגורים",
        "summary": "סאתורן מציעה מגדלי מגורים ליד המשרדים וגם רחובות ירוקים ושקטים יותר מדרום לציר הראשי. לפני שבוחרים בניין, צריך לקבע תחנה או דרך יומית ולא רק שם אזור.",
        "title": "סאתורן למי שרוצה לעבוד במרכז ולחזור לרחוב שקט"
      },
      "station_ids": [
        "transit:bts:s3",
        "transit:bts:s4",
        "transit:bts:s5",
        "transit:mrt:bl25"
      ],
      "tradeoff": "סאתורן רחבה ומפוצלת, ולכן כתובת באזור יכולה להיות ליד BTS או רחוקה מכל רכבת ולדרוש נסיעה יומית בכביש עמוס."
    },
    "market:bangkok:silom": {
      "aliases": {
        "en": [
          "Si Lom",
          "Silom Road"
        ],
        "he": [
          "סי לום",
          "סילום בנגקוק"
        ],
        "th": [
          "ถนนสีลม"
        ]
      },
      "area_id": "market:bangkok:silom",
      "coordinates": {
        "basis": "market-area centroid",
        "lat": 13.7287,
        "lng": 100.534
      },
      "corridor_id": "corridor:bangkok:silom-sathon",
      "daily_life_cues": [
        "שתי מערכות רכבת במרחק קצר",
        "גישה לפארק לומפיני",
        "מסעדות פעילות ביום ובלילה",
        "הליכה קצרה למגדלי משרדים"
      ],
      "fit_summary": "מתאים למי שעובד במרכז העסקים, רוצה בחירה בין BTS ל-MRT ומעדיף מסעדות, קניות ופארק בתוך שגרת ההליכה.",
      "indexing_policy": "non_indexable",
      "kind": "market_area",
      "map_position": {
        "x_percent": 15.6,
        "y_percent": 68.1
      },
      "micro_area_notes": [
        {
          "detail": "נקודת החיבור הנוחה ביותר ל-BTS, ל-MRT, לפארק לומפיני ולגשר הירוק לכיוון פארק בנג׳קיטי.",
          "label": "סאלה דאנג"
        },
        {
          "detail": "אזור עירוני צפוף עם מלונות, מסעדות וחיי ערב, במרחק משתנה מהרכבת.",
          "label": "סוראוונג"
        },
        {
          "detail": "קרוב לאוניברסיטה, לקניות ולאוכל, עם אפשרויות נוספות לאורך קו MRT.",
          "label": "הקצה לכיוון סאם יאן"
        }
      ],
      "monthly_asking_bands": {
        "basis_label": "קונדו מרוהט בחוזה טיפוסי של 12 חודשים",
        "checked_on": "2026-08-10",
        "currency": "THB",
        "one_bedroom": {
          "max_thb": 48000,
          "min_thb": 22000
        },
        "pricing_method_id": "method:bangkok:rental-asking-bands",
        "source_ids": [
          "source:propertyscout:bangkok-condos",
          "source:ddproperty:bangkok-condos",
          "source:propertyhub:bangkok-condos"
        ],
        "two_bedroom": {
          "max_thb": 100000,
          "min_thb": 45000
        },
        "unit": "month"
      },
      "names": {
        "en": "Silom",
        "he": "סילום",
        "th": "สีลม"
      },
      "official_district_ids": [
        "geo:th:bma:1004",
        "geo:th:bma:1007"
      ],
      "persona_tags": [
        "business",
        "rail",
        "nightlife",
        "green"
      ],
      "public_copy": {
        "action_label": "להשוואת הרחובות בסילום",
        "eyebrow": "עבודה, רכבת ופארק במקום אחד",
        "summary": "סילום מחברת משרדים, BTS, MRT, פארק וחיי ערב באזור קומפקטי מאוד. כדאי לבחור את הרחוב לפי שעות השגרה שלכם, כי המרחקים קצרים אבל האופי משתנה במהירות.",
        "title": "סילום לחיים במרכז העסקים בלי לוותר על העיר"
      },
      "station_ids": [
        "transit:bts:s2",
        "transit:mrt:bl26",
        "transit:mrt:bl27"
      ],
      "tradeoff": "הרחובות הראשיים עמוסים ורועשים, וחיי הערב בחלקים מסוימים אינם מתאימים למי שמחפש סביבה שקטה לחלוטין."
    },
    "market:bangkok:thong-lo": {
      "aliases": {
        "en": [
          "Thonglor",
          "Thong Lor"
        ],
        "he": [
          "טונגלור",
          "תונג לו"
        ],
        "th": [
          "ทองหล่อสุขุมวิท 55"
        ]
      },
      "area_id": "market:bangkok:thong-lo",
      "coordinates": {
        "basis": "market-area centroid",
        "lat": 13.7242,
        "lng": 100.5786
      },
      "corridor_id": "corridor:bangkok:central-sukhumvit",
      "daily_life_cues": [
        "מסעדות ובתי קפה בכל טווח מחיר",
        "חיי ערב פעילים לאורך סוי 55",
        "סופרמרקטים ושירותים יפניים",
        "מוניות ושאטלים נפוצים בתוך הרחוב"
      ],
      "fit_summary": "מתאים למי שמעדיף מסעדות, בתי קפה וחיי ערב כחלק מהשגרה ורוצה כתובת בעלת אופי ברור במרכז סוקומוויט.",
      "indexing_policy": "non_indexable",
      "kind": "market_area",
      "map_position": {
        "x_percent": 65.1,
        "y_percent": 73.1
      },
      "micro_area_notes": [
        {
          "detail": "הבחירה הנוחה ביותר לנסיעות יומיות ברכבת, עם פחות תלות במונית או שאטל.",
          "label": "החלק התחתון ליד BTS"
        },
        {
          "detail": "מרכז חיי האוכל והערב, עם פעילות עד שעות מאוחרות ומחירים גבוהים יותר.",
          "label": "טונג לו 10 והרחובות הסמוכים"
        },
        {
          "detail": "בניינים גדולים ושירותים רבים, אך זמן ההגעה לתחנת BTS תלוי בתנועה.",
          "label": "החלק הצפוני לכיוון פטצ׳בורי"
        }
      ],
      "monthly_asking_bands": {
        "basis_label": "קונדו מרוהט בחוזה טיפוסי של 12 חודשים",
        "checked_on": "2026-08-10",
        "currency": "THB",
        "one_bedroom": {
          "max_thb": 65000,
          "min_thb": 28000
        },
        "pricing_method_id": "method:bangkok:rental-asking-bands",
        "source_ids": [
          "source:propertyscout:bangkok-condos",
          "source:ddproperty:bangkok-condos",
          "source:propertyhub:bangkok-condos"
        ],
        "two_bedroom": {
          "max_thb": 140000,
          "min_thb": 60000
        },
        "unit": "month"
      },
      "names": {
        "en": "Thong Lo",
        "he": "טונג לו",
        "th": "ทองหล่อ"
      },
      "official_district_ids": [
        "geo:th:bma:1039",
        "geo:th:bma:1033"
      ],
      "persona_tags": [
        "nightlife",
        "upscale",
        "food",
        "central"
      ],
      "public_copy": {
        "action_label": "למציאת החלק הנכון בטונג לו",
        "eyebrow": "האזור שחי גם אחרי העבודה",
        "summary": "טונג לו מציעה את אחת מסצנות האוכל והבילוי הבולטות בעיר לצד בנייני מגורים ברמה גבוהה. המרחק מה-BTS הוא המשתנה החשוב ביותר בבחירת הבניין הנכון.",
        "title": "טונג לו למי שבוחר סגנון חיים לפני הכול"
      },
      "station_ids": [
        "transit:bts:e6"
      ],
      "tradeoff": "הרחוב ארוך והפקקים מורגשים, לכן כתובת בשם טונג לו יכולה להיות קרובה ל-BTS או רחוקה ממנו משמעותית."
    }
  },
  "checked_on": "2026-08-10",
  "contract_id": "bangkok-rental-areas-v1",
  "corridor_order": [
    "corridor:bangkok:central-sukhumvit",
    "corridor:bangkok:east-sukhumvit",
    "corridor:bangkok:ari-phaya-thai",
    "corridor:bangkok:silom-sathon",
    "corridor:bangkok:rama9-ratchada"
  ],
  "corridors_by_id": {
    "corridor:bangkok:ari-phaya-thai": {
      "area_ids": [
        "market:bangkok:ari"
      ],
      "corridor_id": "corridor:bangkok:ari-phaya-thai",
      "indexing_policy": "non_indexable",
      "kind": "editorial_corridor",
      "map_color": "#7B6BA8",
      "names": {
        "en": "Ari and Phaya Thai",
        "he": "ארי ופאיה תאי",
        "th": "อารีย์และพญาไท"
      },
      "station_ids": [
        "transit:bts:n5"
      ],
      "summary": "מסדרון מגורים צפוני ונעים יותר סביב קו BTS, עם רחובות צדדיים, בתי קפה וגישה ישירה למרכז."
    },
    "corridor:bangkok:central-sukhumvit": {
      "area_ids": [
        "market:bangkok:asok",
        "market:bangkok:phrom-phong",
        "market:bangkok:thong-lo",
        "market:bangkok:ekkamai"
      ],
      "corridor_id": "corridor:bangkok:central-sukhumvit",
      "indexing_policy": "non_indexable",
      "kind": "editorial_corridor",
      "map_color": "#B97835",
      "names": {
        "en": "Central Sukhumvit",
        "he": "סוקומוויט המרכזית",
        "th": "สุขุมวิทตอนกลาง"
      },
      "station_ids": [
        "transit:bts:e4",
        "transit:mrt:bl22",
        "transit:bts:e5",
        "transit:bts:e6",
        "transit:bts:e7"
      ],
      "summary": "רצף עירוני צפוף סביב קו BTS, עם שילוב של משרדים, קניות, מסעדות ובנייני מגורים במרחקים קצרים."
    },
    "corridor:bangkok:east-sukhumvit": {
      "area_ids": [
        "market:bangkok:on-nut"
      ],
      "corridor_id": "corridor:bangkok:east-sukhumvit",
      "indexing_policy": "non_indexable",
      "kind": "editorial_corridor",
      "map_color": "#4E866A",
      "names": {
        "en": "East Sukhumvit",
        "he": "סוקומוויט המזרחית",
        "th": "สุขุมวิทฝั่งตะวันออก"
      },
      "station_ids": [
        "transit:bts:e8",
        "transit:bts:e9"
      ],
      "summary": "המשך מזרחי של קו BTS שמציע יותר דירות בתקציב בינוני, מרכזים שכונתיים וגישה נוחה למרכז העיר."
    },
    "corridor:bangkok:rama9-ratchada": {
      "area_ids": [
        "market:bangkok:phra-ram-9",
        "market:bangkok:huai-khwang"
      ],
      "corridor_id": "corridor:bangkok:rama9-ratchada",
      "indexing_policy": "non_indexable",
      "kind": "editorial_corridor",
      "map_color": "#9A554A",
      "names": {
        "en": "Phra Ram 9 and Ratchada",
        "he": "פרה ראם 9 וראצ׳דה",
        "th": "พระราม 9 และรัชดา"
      },
      "station_ids": [
        "transit:mrt:bl17",
        "transit:mrt:bl18",
        "transit:mrt:bl19",
        "transit:mrt:bl20"
      ],
      "summary": "ציר MRT מתפתח עם משרדים, מרכזי קניות, שווקי ערב ומבחר גדול של בנייני קונדו חדשים יחסית."
    },
    "corridor:bangkok:silom-sathon": {
      "area_ids": [
        "market:bangkok:silom",
        "market:bangkok:sathon"
      ],
      "corridor_id": "corridor:bangkok:silom-sathon",
      "indexing_policy": "non_indexable",
      "kind": "editorial_corridor",
      "map_color": "#315D71",
      "names": {
        "en": "Silom and Sathon",
        "he": "סילום וסאתורן",
        "th": "สีลมและสาทร"
      },
      "station_ids": [
        "transit:bts:s2",
        "transit:bts:s3",
        "transit:bts:s4",
        "transit:bts:s5",
        "transit:mrt:bl25",
        "transit:mrt:bl26",
        "transit:mrt:bl27"
      ],
      "summary": "ליבת העסקים הדרומית של בנגקוק, עם BTS, MRT, מסעדות, פארקים ורחובות מגורים שקטים לצד מגדלי משרדים."
    }
  },
  "district_id_by_alias": {
    "en": {
      "bang bon": "geo:th:bma:1050",
      "bang kapi": "geo:th:bma:1006",
      "bang khae": "geo:th:bma:1040",
      "bang khen": "geo:th:bma:1005",
      "bang kho laem": "geo:th:bma:1031",
      "bang khun thian": "geo:th:bma:1021",
      "bang khun thien": "geo:th:bma:1021",
      "bang na": "geo:th:bma:1047",
      "bang phlat": "geo:th:bma:1025",
      "bang plat": "geo:th:bma:1025",
      "bang rak": "geo:th:bma:1004",
      "bang sue": "geo:th:bma:1029",
      "bangbon": "geo:th:bma:1050",
      "bangkae": "geo:th:bma:1040",
      "bangkapi": "geo:th:bma:1006",
      "bangkhen": "geo:th:bma:1005",
      "bangkholaem": "geo:th:bma:1031",
      "bangkok noi": "geo:th:bma:1020",
      "bangkok yai": "geo:th:bma:1016",
      "bangna": "geo:th:bma:1047",
      "bangrak": "geo:th:bma:1004",
      "bangsue": "geo:th:bma:1029",
      "bueng kum": "geo:th:bma:1027",
      "bung kum": "geo:th:bma:1027",
      "chatuchak": "geo:th:bma:1030",
      "chom thong": "geo:th:bma:1035",
      "din daeng": "geo:th:bma:1026",
      "dindaeng": "geo:th:bma:1026",
      "don muang": "geo:th:bma:1036",
      "don mueang": "geo:th:bma:1036",
      "donmueang": "geo:th:bma:1036",
      "dusit": "geo:th:bma:1002",
      "huai khwang": "geo:th:bma:1017",
      "huay khwang": "geo:th:bma:1017",
      "jatujak": "geo:th:bma:1030",
      "jom thong": "geo:th:bma:1035",
      "kannayao": "geo:th:bma:1043",
      "khan na yao": "geo:th:bma:1043",
      "khlong sam wa": "geo:th:bma:1046",
      "khlong san": "geo:th:bma:1018",
      "khlong toei": "geo:th:bma:1033",
      "klong sam wa": "geo:th:bma:1046",
      "klong san": "geo:th:bma:1018",
      "klong toey": "geo:th:bma:1033",
      "lad krabang": "geo:th:bma:1011",
      "lad prao": "geo:th:bma:1038",
      "lak si": "geo:th:bma:1041",
      "laksi": "geo:th:bma:1041",
      "lat krabang": "geo:th:bma:1011",
      "lat phrao": "geo:th:bma:1038",
      "min buri": "geo:th:bma:1010",
      "minburi": "geo:th:bma:1010",
      "nong chok": "geo:th:bma:1003",
      "nong khaem": "geo:th:bma:1023",
      "nong khem": "geo:th:bma:1023",
      "pathum wan": "geo:th:bma:1007",
      "pathumwan": "geo:th:bma:1007",
      "phasi charoen": "geo:th:bma:1022",
      "phaya thai": "geo:th:bma:1014",
      "phra khanong": "geo:th:bma:1009",
      "phra nakhon": "geo:th:bma:1001",
      "phra nakorn": "geo:th:bma:1001",
      "phyathai": "geo:th:bma:1014",
      "pom prap": "geo:th:bma:1008",
      "pom prap sattru phai": "geo:th:bma:1008",
      "prakanong": "geo:th:bma:1009",
      "pravet": "geo:th:bma:1032",
      "prawet": "geo:th:bma:1032",
      "rajthevee": "geo:th:bma:1037",
      "rat burana": "geo:th:bma:1024",
      "ratchathewi": "geo:th:bma:1037",
      "rath burana": "geo:th:bma:1024",
      "sai mai": "geo:th:bma:1042",
      "saimai": "geo:th:bma:1042",
      "samphan thawong": "geo:th:bma:1013",
      "samphanthawong": "geo:th:bma:1013",
      "saphan sung": "geo:th:bma:1044",
      "sathon": "geo:th:bma:1028",
      "sathorn": "geo:th:bma:1028",
      "suan luang": "geo:th:bma:1034",
      "taling chan": "geo:th:bma:1019",
      "tawi wattana": "geo:th:bma:1048",
      "thawi watthana": "geo:th:bma:1048",
      "thon buri": "geo:th:bma:1015",
      "thonburi": "geo:th:bma:1015",
      "thung khru": "geo:th:bma:1049",
      "tung kru": "geo:th:bma:1049",
      "vadhana": "geo:th:bma:1039",
      "wang thonglang": "geo:th:bma:1045",
      "wangthonglang": "geo:th:bma:1045",
      "watthana": "geo:th:bma:1039",
      "yan nawa": "geo:th:bma:1012",
      "yannawa": "geo:th:bma:1012"
    },
    "he": {
      "באנג בון": "geo:th:bma:1050",
      "באנג נא": "geo:th:bma:1047",
      "באנג סו": "geo:th:bma:1029",
      "באנג פלאט": "geo:th:bma:1025",
      "באנג קאה": "geo:th:bma:1040",
      "באנג קאפי": "geo:th:bma:1006",
      "באנג קו לאם": "geo:th:bma:1031",
      "באנג קון תיאן": "geo:th:bma:1021",
      "באנג קן": "geo:th:bma:1005",
      "באנג ראק": "geo:th:bma:1004",
      "בואנג קום": "geo:th:bma:1027",
      "בנגקוק יאי": "geo:th:bma:1016",
      "בנגקוק נוי": "geo:th:bma:1020",
      "ג אטוג אק": "geo:th:bma:1030",
      "דון מואנג": "geo:th:bma:1036",
      "דון מואנגג": "geo:th:bma:1036",
      "דוסיט": "geo:th:bma:1002",
      "דין דאנג": "geo:th:bma:1026",
      "הואי קוואנג": "geo:th:bma:1017",
      "הווי קוואנג": "geo:th:bma:1017",
      "ואטאנה": "geo:th:bma:1039",
      "וואטאנה": "geo:th:bma:1039",
      "וואנג תונגלנג": "geo:th:bma:1045",
      "טאלינג צ אן": "geo:th:bma:1019",
      "יאן נאווה": "geo:th:bma:1012",
      "לאד פראו": "geo:th:bma:1038",
      "לאט פראו": "geo:th:bma:1038",
      "לאט קראבאנג": "geo:th:bma:1011",
      "לאק סי": "geo:th:bma:1041",
      "מין בורי": "geo:th:bma:1010",
      "נונג צ וק": "geo:th:bma:1003",
      "נונג קאם": "geo:th:bma:1023",
      "סאטון": "geo:th:bma:1028",
      "סאי מאי": "geo:th:bma:1042",
      "סאפאן סונג": "geo:th:bma:1044",
      "סאתורן": "geo:th:bma:1028",
      "סואן לואנג": "geo:th:bma:1034",
      "סמפנתאוונג": "geo:th:bma:1013",
      "פאיה תאי": "geo:th:bma:1014",
      "פאסי צ רואן": "geo:th:bma:1022",
      "פאתום וואן": "geo:th:bma:1007",
      "פום פראפ סאטרו פאי": "geo:th:bma:1008",
      "פייה תאי": "geo:th:bma:1014",
      "פרא קנונג": "geo:th:bma:1009",
      "פראווט": "geo:th:bma:1032",
      "פרה נאקון": "geo:th:bma:1001",
      "פרה קנונג": "geo:th:bma:1009",
      "צ אטוצ אק": "geo:th:bma:1030",
      "צ ום תונג": "geo:th:bma:1035",
      "קאן נא יאו": "geo:th:bma:1043",
      "קלונג טואי": "geo:th:bma:1033",
      "קלונג טוי": "geo:th:bma:1033",
      "קלונג סאם ווא": "geo:th:bma:1046",
      "קלונג סאן": "geo:th:bma:1018",
      "ראט בוראנה": "geo:th:bma:1024",
      "ראצ אתווי": "geo:th:bma:1037",
      "תאווי ואטאנה": "geo:th:bma:1048",
      "תון בורי": "geo:th:bma:1015",
      "תונג קרו": "geo:th:bma:1049"
    },
    "th": {
      "คลองสาน": "geo:th:bma:1018",
      "คลองสามวา": "geo:th:bma:1046",
      "คลองเตย": "geo:th:bma:1033",
      "คันนายาว": "geo:th:bma:1043",
      "จตุจักร": "geo:th:bma:1030",
      "จอมทอง": "geo:th:bma:1035",
      "ดอนเมือง": "geo:th:bma:1036",
      "ดินแดง": "geo:th:bma:1026",
      "ดุสิต": "geo:th:bma:1002",
      "ตลิ่งชัน": "geo:th:bma:1019",
      "ทวีวัฒนา": "geo:th:bma:1048",
      "ทุ่งครุ": "geo:th:bma:1049",
      "ธนบุรี": "geo:th:bma:1015",
      "บางกอกน้อย": "geo:th:bma:1020",
      "บางกอกใหญ่": "geo:th:bma:1016",
      "บางกะปิ": "geo:th:bma:1006",
      "บางขุนเทียน": "geo:th:bma:1021",
      "บางคอแหลม": "geo:th:bma:1031",
      "บางซื่อ": "geo:th:bma:1029",
      "บางนา": "geo:th:bma:1047",
      "บางบอน": "geo:th:bma:1050",
      "บางพลัด": "geo:th:bma:1025",
      "บางรัก": "geo:th:bma:1004",
      "บางเขน": "geo:th:bma:1005",
      "บางแค": "geo:th:bma:1040",
      "บึงกุ่ม": "geo:th:bma:1027",
      "ปทุมวัน": "geo:th:bma:1007",
      "ประเวศ": "geo:th:bma:1032",
      "ป้อมปราบศัตรูพ่าย": "geo:th:bma:1008",
      "พญาไท": "geo:th:bma:1014",
      "พระนคร": "geo:th:bma:1001",
      "พระโขนง": "geo:th:bma:1009",
      "ภาษีเจริญ": "geo:th:bma:1022",
      "มีนบุรี": "geo:th:bma:1010",
      "ยานนาวา": "geo:th:bma:1012",
      "ราชเทวี": "geo:th:bma:1037",
      "ราษฎร์บูรณะ": "geo:th:bma:1024",
      "ลาดกระบัง": "geo:th:bma:1011",
      "ลาดพร้าว": "geo:th:bma:1038",
      "วังทองหลาง": "geo:th:bma:1045",
      "วัฒนา": "geo:th:bma:1039",
      "สวนหลวง": "geo:th:bma:1034",
      "สะพานสูง": "geo:th:bma:1044",
      "สัมพันธวงศ์": "geo:th:bma:1013",
      "สาทร": "geo:th:bma:1028",
      "สายไหม": "geo:th:bma:1042",
      "หนองจอก": "geo:th:bma:1003",
      "หนองแขม": "geo:th:bma:1023",
      "หลักสี่": "geo:th:bma:1041",
      "ห้วยขวาง": "geo:th:bma:1017"
    }
  },
  "district_id_by_bma_code": {
    "1001": "geo:th:bma:1001",
    "1002": "geo:th:bma:1002",
    "1003": "geo:th:bma:1003",
    "1004": "geo:th:bma:1004",
    "1005": "geo:th:bma:1005",
    "1006": "geo:th:bma:1006",
    "1007": "geo:th:bma:1007",
    "1008": "geo:th:bma:1008",
    "1009": "geo:th:bma:1009",
    "1010": "geo:th:bma:1010",
    "1011": "geo:th:bma:1011",
    "1012": "geo:th:bma:1012",
    "1013": "geo:th:bma:1013",
    "1014": "geo:th:bma:1014",
    "1015": "geo:th:bma:1015",
    "1016": "geo:th:bma:1016",
    "1017": "geo:th:bma:1017",
    "1018": "geo:th:bma:1018",
    "1019": "geo:th:bma:1019",
    "1020": "geo:th:bma:1020",
    "1021": "geo:th:bma:1021",
    "1022": "geo:th:bma:1022",
    "1023": "geo:th:bma:1023",
    "1024": "geo:th:bma:1024",
    "1025": "geo:th:bma:1025",
    "1026": "geo:th:bma:1026",
    "1027": "geo:th:bma:1027",
    "1028": "geo:th:bma:1028",
    "1029": "geo:th:bma:1029",
    "1030": "geo:th:bma:1030",
    "1031": "geo:th:bma:1031",
    "1032": "geo:th:bma:1032",
    "1033": "geo:th:bma:1033",
    "1034": "geo:th:bma:1034",
    "1035": "geo:th:bma:1035",
    "1036": "geo:th:bma:1036",
    "1037": "geo:th:bma:1037",
    "1038": "geo:th:bma:1038",
    "1039": "geo:th:bma:1039",
    "1040": "geo:th:bma:1040",
    "1041": "geo:th:bma:1041",
    "1042": "geo:th:bma:1042",
    "1043": "geo:th:bma:1043",
    "1044": "geo:th:bma:1044",
    "1045": "geo:th:bma:1045",
    "1046": "geo:th:bma:1046",
    "1047": "geo:th:bma:1047",
    "1048": "geo:th:bma:1048",
    "1049": "geo:th:bma:1049",
    "1050": "geo:th:bma:1050"
  },
  "district_order": [
    "geo:th:bma:1001",
    "geo:th:bma:1002",
    "geo:th:bma:1003",
    "geo:th:bma:1004",
    "geo:th:bma:1005",
    "geo:th:bma:1006",
    "geo:th:bma:1007",
    "geo:th:bma:1008",
    "geo:th:bma:1009",
    "geo:th:bma:1010",
    "geo:th:bma:1011",
    "geo:th:bma:1012",
    "geo:th:bma:1013",
    "geo:th:bma:1014",
    "geo:th:bma:1015",
    "geo:th:bma:1016",
    "geo:th:bma:1017",
    "geo:th:bma:1018",
    "geo:th:bma:1019",
    "geo:th:bma:1020",
    "geo:th:bma:1021",
    "geo:th:bma:1022",
    "geo:th:bma:1023",
    "geo:th:bma:1024",
    "geo:th:bma:1025",
    "geo:th:bma:1026",
    "geo:th:bma:1027",
    "geo:th:bma:1028",
    "geo:th:bma:1029",
    "geo:th:bma:1030",
    "geo:th:bma:1031",
    "geo:th:bma:1032",
    "geo:th:bma:1033",
    "geo:th:bma:1034",
    "geo:th:bma:1035",
    "geo:th:bma:1036",
    "geo:th:bma:1037",
    "geo:th:bma:1038",
    "geo:th:bma:1039",
    "geo:th:bma:1040",
    "geo:th:bma:1041",
    "geo:th:bma:1042",
    "geo:th:bma:1043",
    "geo:th:bma:1044",
    "geo:th:bma:1045",
    "geo:th:bma:1046",
    "geo:th:bma:1047",
    "geo:th:bma:1048",
    "geo:th:bma:1049",
    "geo:th:bma:1050"
  ],
  "districts_by_id": {
    "geo:th:bma:1001": {
      "aliases": {
        "en": [
          "Phra Nakorn"
        ],
        "he": [],
        "th": []
      },
      "bma_code": "1001",
      "district_id": "geo:th:bma:1001",
      "indexing_policy": "non_indexable",
      "kind": "official_district",
      "names": {
        "en": "Phra Nakhon",
        "he": "פרה נאקון",
        "th": "พระนคร"
      },
      "source_ids": [
        "source:bma:districts"
      ]
    },
    "geo:th:bma:1002": {
      "aliases": {
        "en": [],
        "he": [],
        "th": []
      },
      "bma_code": "1002",
      "district_id": "geo:th:bma:1002",
      "indexing_policy": "non_indexable",
      "kind": "official_district",
      "names": {
        "en": "Dusit",
        "he": "דוסיט",
        "th": "ดุสิต"
      },
      "source_ids": [
        "source:bma:districts"
      ]
    },
    "geo:th:bma:1003": {
      "aliases": {
        "en": [],
        "he": [],
        "th": []
      },
      "bma_code": "1003",
      "district_id": "geo:th:bma:1003",
      "indexing_policy": "non_indexable",
      "kind": "official_district",
      "names": {
        "en": "Nong Chok",
        "he": "נונג צ׳וק",
        "th": "หนองจอก"
      },
      "source_ids": [
        "source:bma:districts"
      ]
    },
    "geo:th:bma:1004": {
      "aliases": {
        "en": [
          "Bangrak"
        ],
        "he": [],
        "th": []
      },
      "bma_code": "1004",
      "district_id": "geo:th:bma:1004",
      "indexing_policy": "non_indexable",
      "kind": "official_district",
      "names": {
        "en": "Bang Rak",
        "he": "באנג ראק",
        "th": "บางรัก"
      },
      "source_ids": [
        "source:bma:districts"
      ]
    },
    "geo:th:bma:1005": {
      "aliases": {
        "en": [
          "Bangkhen"
        ],
        "he": [],
        "th": []
      },
      "bma_code": "1005",
      "district_id": "geo:th:bma:1005",
      "indexing_policy": "non_indexable",
      "kind": "official_district",
      "names": {
        "en": "Bang Khen",
        "he": "באנג קן",
        "th": "บางเขน"
      },
      "source_ids": [
        "source:bma:districts"
      ]
    },
    "geo:th:bma:1006": {
      "aliases": {
        "en": [
          "Bangkapi"
        ],
        "he": [],
        "th": []
      },
      "bma_code": "1006",
      "district_id": "geo:th:bma:1006",
      "indexing_policy": "non_indexable",
      "kind": "official_district",
      "names": {
        "en": "Bang Kapi",
        "he": "באנג קאפי",
        "th": "บางกะปิ"
      },
      "source_ids": [
        "source:bma:districts"
      ]
    },
    "geo:th:bma:1007": {
      "aliases": {
        "en": [
          "Pathumwan"
        ],
        "he": [],
        "th": []
      },
      "bma_code": "1007",
      "district_id": "geo:th:bma:1007",
      "indexing_policy": "non_indexable",
      "kind": "official_district",
      "names": {
        "en": "Pathum Wan",
        "he": "פאתום וואן",
        "th": "ปทุมวัน"
      },
      "source_ids": [
        "source:bma:districts"
      ]
    },
    "geo:th:bma:1008": {
      "aliases": {
        "en": [
          "Pom Prap"
        ],
        "he": [],
        "th": []
      },
      "bma_code": "1008",
      "district_id": "geo:th:bma:1008",
      "indexing_policy": "non_indexable",
      "kind": "official_district",
      "names": {
        "en": "Pom Prap Sattru Phai",
        "he": "פום פראפ סאטרו פאי",
        "th": "ป้อมปราบศัตรูพ่าย"
      },
      "source_ids": [
        "source:bma:districts"
      ]
    },
    "geo:th:bma:1009": {
      "aliases": {
        "en": [
          "Prakanong"
        ],
        "he": [
          "פרא קנונג"
        ],
        "th": []
      },
      "bma_code": "1009",
      "district_id": "geo:th:bma:1009",
      "indexing_policy": "non_indexable",
      "kind": "official_district",
      "names": {
        "en": "Phra Khanong",
        "he": "פרה קנונג",
        "th": "พระโขนง"
      },
      "source_ids": [
        "source:bma:districts"
      ]
    },
    "geo:th:bma:1010": {
      "aliases": {
        "en": [
          "Minburi"
        ],
        "he": [],
        "th": []
      },
      "bma_code": "1010",
      "district_id": "geo:th:bma:1010",
      "indexing_policy": "non_indexable",
      "kind": "official_district",
      "names": {
        "en": "Min Buri",
        "he": "מין בורי",
        "th": "มีนบุรี"
      },
      "source_ids": [
        "source:bma:districts"
      ]
    },
    "geo:th:bma:1011": {
      "aliases": {
        "en": [
          "Lad Krabang"
        ],
        "he": [],
        "th": []
      },
      "bma_code": "1011",
      "district_id": "geo:th:bma:1011",
      "indexing_policy": "non_indexable",
      "kind": "official_district",
      "names": {
        "en": "Lat Krabang",
        "he": "לאט קראבאנג",
        "th": "ลาดกระบัง"
      },
      "source_ids": [
        "source:bma:districts"
      ]
    },
    "geo:th:bma:1012": {
      "aliases": {
        "en": [
          "Yannawa"
        ],
        "he": [],
        "th": []
      },
      "bma_code": "1012",
      "district_id": "geo:th:bma:1012",
      "indexing_policy": "non_indexable",
      "kind": "official_district",
      "names": {
        "en": "Yan Nawa",
        "he": "יאן נאווה",
        "th": "ยานนาวา"
      },
      "source_ids": [
        "source:bma:districts"
      ]
    },
    "geo:th:bma:1013": {
      "aliases": {
        "en": [
          "Samphan Thawong"
        ],
        "he": [],
        "th": []
      },
      "bma_code": "1013",
      "district_id": "geo:th:bma:1013",
      "indexing_policy": "non_indexable",
      "kind": "official_district",
      "names": {
        "en": "Samphanthawong",
        "he": "סמפנתאוונג",
        "th": "สัมพันธวงศ์"
      },
      "source_ids": [
        "source:bma:districts"
      ]
    },
    "geo:th:bma:1014": {
      "aliases": {
        "en": [
          "Phyathai"
        ],
        "he": [
          "פייה תאי"
        ],
        "th": []
      },
      "bma_code": "1014",
      "district_id": "geo:th:bma:1014",
      "indexing_policy": "non_indexable",
      "kind": "official_district",
      "names": {
        "en": "Phaya Thai",
        "he": "פאיה תאי",
        "th": "พญาไท"
      },
      "source_ids": [
        "source:bma:districts"
      ]
    },
    "geo:th:bma:1015": {
      "aliases": {
        "en": [
          "Thonburi"
        ],
        "he": [],
        "th": []
      },
      "bma_code": "1015",
      "district_id": "geo:th:bma:1015",
      "indexing_policy": "non_indexable",
      "kind": "official_district",
      "names": {
        "en": "Thon Buri",
        "he": "תון בורי",
        "th": "ธนบุรี"
      },
      "source_ids": [
        "source:bma:districts"
      ]
    },
    "geo:th:bma:1016": {
      "aliases": {
        "en": [],
        "he": [],
        "th": []
      },
      "bma_code": "1016",
      "district_id": "geo:th:bma:1016",
      "indexing_policy": "non_indexable",
      "kind": "official_district",
      "names": {
        "en": "Bangkok Yai",
        "he": "בנגקוק יאי",
        "th": "บางกอกใหญ่"
      },
      "source_ids": [
        "source:bma:districts"
      ]
    },
    "geo:th:bma:1017": {
      "aliases": {
        "en": [
          "Huay Khwang"
        ],
        "he": [
          "הווי קוואנג"
        ],
        "th": []
      },
      "bma_code": "1017",
      "district_id": "geo:th:bma:1017",
      "indexing_policy": "non_indexable",
      "kind": "official_district",
      "names": {
        "en": "Huai Khwang",
        "he": "הואי קוואנג",
        "th": "ห้วยขวาง"
      },
      "source_ids": [
        "source:bma:districts"
      ]
    },
    "geo:th:bma:1018": {
      "aliases": {
        "en": [
          "Klong San"
        ],
        "he": [],
        "th": []
      },
      "bma_code": "1018",
      "district_id": "geo:th:bma:1018",
      "indexing_policy": "non_indexable",
      "kind": "official_district",
      "names": {
        "en": "Khlong San",
        "he": "קלונג סאן",
        "th": "คลองสาน"
      },
      "source_ids": [
        "source:bma:districts"
      ]
    },
    "geo:th:bma:1019": {
      "aliases": {
        "en": [],
        "he": [],
        "th": []
      },
      "bma_code": "1019",
      "district_id": "geo:th:bma:1019",
      "indexing_policy": "non_indexable",
      "kind": "official_district",
      "names": {
        "en": "Taling Chan",
        "he": "טאלינג צ׳אן",
        "th": "ตลิ่งชัน"
      },
      "source_ids": [
        "source:bma:districts"
      ]
    },
    "geo:th:bma:1020": {
      "aliases": {
        "en": [],
        "he": [],
        "th": []
      },
      "bma_code": "1020",
      "district_id": "geo:th:bma:1020",
      "indexing_policy": "non_indexable",
      "kind": "official_district",
      "names": {
        "en": "Bangkok Noi",
        "he": "בנגקוק נוי",
        "th": "บางกอกน้อย"
      },
      "source_ids": [
        "source:bma:districts"
      ]
    },
    "geo:th:bma:1021": {
      "aliases": {
        "en": [
          "Bang Khun Thien"
        ],
        "he": [],
        "th": []
      },
      "bma_code": "1021",
      "district_id": "geo:th:bma:1021",
      "indexing_policy": "non_indexable",
      "kind": "official_district",
      "names": {
        "en": "Bang Khun Thian",
        "he": "באנג קון תיאן",
        "th": "บางขุนเทียน"
      },
      "source_ids": [
        "source:bma:districts"
      ]
    },
    "geo:th:bma:1022": {
      "aliases": {
        "en": [],
        "he": [],
        "th": []
      },
      "bma_code": "1022",
      "district_id": "geo:th:bma:1022",
      "indexing_policy": "non_indexable",
      "kind": "official_district",
      "names": {
        "en": "Phasi Charoen",
        "he": "פאסי צ׳רואן",
        "th": "ภาษีเจริญ"
      },
      "source_ids": [
        "source:bma:districts"
      ]
    },
    "geo:th:bma:1023": {
      "aliases": {
        "en": [
          "Nong Khem"
        ],
        "he": [],
        "th": []
      },
      "bma_code": "1023",
      "district_id": "geo:th:bma:1023",
      "indexing_policy": "non_indexable",
      "kind": "official_district",
      "names": {
        "en": "Nong Khaem",
        "he": "נונג קאם",
        "th": "หนองแขม"
      },
      "source_ids": [
        "source:bma:districts"
      ]
    },
    "geo:th:bma:1024": {
      "aliases": {
        "en": [
          "Rath Burana"
        ],
        "he": [],
        "th": []
      },
      "bma_code": "1024",
      "district_id": "geo:th:bma:1024",
      "indexing_policy": "non_indexable",
      "kind": "official_district",
      "names": {
        "en": "Rat Burana",
        "he": "ראט בוראנה",
        "th": "ราษฎร์บูรณะ"
      },
      "source_ids": [
        "source:bma:districts"
      ]
    },
    "geo:th:bma:1025": {
      "aliases": {
        "en": [
          "Bang Plat"
        ],
        "he": [],
        "th": []
      },
      "bma_code": "1025",
      "district_id": "geo:th:bma:1025",
      "indexing_policy": "non_indexable",
      "kind": "official_district",
      "names": {
        "en": "Bang Phlat",
        "he": "באנג פלאט",
        "th": "บางพลัด"
      },
      "source_ids": [
        "source:bma:districts"
      ]
    },
    "geo:th:bma:1026": {
      "aliases": {
        "en": [
          "Dindaeng"
        ],
        "he": [],
        "th": []
      },
      "bma_code": "1026",
      "district_id": "geo:th:bma:1026",
      "indexing_policy": "non_indexable",
      "kind": "official_district",
      "names": {
        "en": "Din Daeng",
        "he": "דין דאנג",
        "th": "ดินแดง"
      },
      "source_ids": [
        "source:bma:districts"
      ]
    },
    "geo:th:bma:1027": {
      "aliases": {
        "en": [
          "Bung Kum"
        ],
        "he": [],
        "th": []
      },
      "bma_code": "1027",
      "district_id": "geo:th:bma:1027",
      "indexing_policy": "non_indexable",
      "kind": "official_district",
      "names": {
        "en": "Bueng Kum",
        "he": "בואנג קום",
        "th": "บึงกุ่ม"
      },
      "source_ids": [
        "source:bma:districts"
      ]
    },
    "geo:th:bma:1028": {
      "aliases": {
        "en": [
          "Sathorn"
        ],
        "he": [
          "סאטון"
        ],
        "th": []
      },
      "bma_code": "1028",
      "district_id": "geo:th:bma:1028",
      "indexing_policy": "non_indexable",
      "kind": "official_district",
      "names": {
        "en": "Sathon",
        "he": "סאתורן",
        "th": "สาทร"
      },
      "source_ids": [
        "source:bma:districts"
      ]
    },
    "geo:th:bma:1029": {
      "aliases": {
        "en": [
          "Bangsue"
        ],
        "he": [],
        "th": []
      },
      "bma_code": "1029",
      "district_id": "geo:th:bma:1029",
      "indexing_policy": "non_indexable",
      "kind": "official_district",
      "names": {
        "en": "Bang Sue",
        "he": "באנג סו",
        "th": "บางซื่อ"
      },
      "source_ids": [
        "source:bma:districts"
      ]
    },
    "geo:th:bma:1030": {
      "aliases": {
        "en": [
          "Jatujak"
        ],
        "he": [
          "ג׳אטוג׳אק"
        ],
        "th": []
      },
      "bma_code": "1030",
      "district_id": "geo:th:bma:1030",
      "indexing_policy": "non_indexable",
      "kind": "official_district",
      "names": {
        "en": "Chatuchak",
        "he": "צ׳אטוצ׳אק",
        "th": "จตุจักร"
      },
      "source_ids": [
        "source:bma:districts"
      ]
    },
    "geo:th:bma:1031": {
      "aliases": {
        "en": [
          "Bangkholaem"
        ],
        "he": [],
        "th": []
      },
      "bma_code": "1031",
      "district_id": "geo:th:bma:1031",
      "indexing_policy": "non_indexable",
      "kind": "official_district",
      "names": {
        "en": "Bang Kho Laem",
        "he": "באנג קו לאם",
        "th": "บางคอแหลม"
      },
      "source_ids": [
        "source:bma:districts"
      ]
    },
    "geo:th:bma:1032": {
      "aliases": {
        "en": [
          "Pravet"
        ],
        "he": [],
        "th": []
      },
      "bma_code": "1032",
      "district_id": "geo:th:bma:1032",
      "indexing_policy": "non_indexable",
      "kind": "official_district",
      "names": {
        "en": "Prawet",
        "he": "פראווט",
        "th": "ประเวศ"
      },
      "source_ids": [
        "source:bma:districts"
      ]
    },
    "geo:th:bma:1033": {
      "aliases": {
        "en": [
          "Klong Toey"
        ],
        "he": [
          "קלונג טוי"
        ],
        "th": []
      },
      "bma_code": "1033",
      "district_id": "geo:th:bma:1033",
      "indexing_policy": "non_indexable",
      "kind": "official_district",
      "names": {
        "en": "Khlong Toei",
        "he": "קלונג טואי",
        "th": "คลองเตย"
      },
      "source_ids": [
        "source:bma:districts"
      ]
    },
    "geo:th:bma:1034": {
      "aliases": {
        "en": [],
        "he": [],
        "th": []
      },
      "bma_code": "1034",
      "district_id": "geo:th:bma:1034",
      "indexing_policy": "non_indexable",
      "kind": "official_district",
      "names": {
        "en": "Suan Luang",
        "he": "סואן לואנג",
        "th": "สวนหลวง"
      },
      "source_ids": [
        "source:bma:districts"
      ]
    },
    "geo:th:bma:1035": {
      "aliases": {
        "en": [
          "Jom Thong"
        ],
        "he": [],
        "th": []
      },
      "bma_code": "1035",
      "district_id": "geo:th:bma:1035",
      "indexing_policy": "non_indexable",
      "kind": "official_district",
      "names": {
        "en": "Chom Thong",
        "he": "צ׳ום תונג",
        "th": "จอมทอง"
      },
      "source_ids": [
        "source:bma:districts"
      ]
    },
    "geo:th:bma:1036": {
      "aliases": {
        "en": [
          "Don Mueang",
          "Don Muang"
        ],
        "he": [
          "דון מואנגג"
        ],
        "th": []
      },
      "bma_code": "1036",
      "district_id": "geo:th:bma:1036",
      "indexing_policy": "non_indexable",
      "kind": "official_district",
      "names": {
        "en": "Donmueang",
        "he": "דון מואנג",
        "th": "ดอนเมือง"
      },
      "source_ids": [
        "source:bma:districts"
      ]
    },
    "geo:th:bma:1037": {
      "aliases": {
        "en": [
          "Rajthevee"
        ],
        "he": [],
        "th": []
      },
      "bma_code": "1037",
      "district_id": "geo:th:bma:1037",
      "indexing_policy": "non_indexable",
      "kind": "official_district",
      "names": {
        "en": "Ratchathewi",
        "he": "ראצ׳אתווי",
        "th": "ราชเทวี"
      },
      "source_ids": [
        "source:bma:districts"
      ]
    },
    "geo:th:bma:1038": {
      "aliases": {
        "en": [
          "Lad Prao"
        ],
        "he": [
          "לאד פראו"
        ],
        "th": []
      },
      "bma_code": "1038",
      "district_id": "geo:th:bma:1038",
      "indexing_policy": "non_indexable",
      "kind": "official_district",
      "names": {
        "en": "Lat Phrao",
        "he": "לאט פראו",
        "th": "ลาดพร้าว"
      },
      "source_ids": [
        "source:bma:districts"
      ]
    },
    "geo:th:bma:1039": {
      "aliases": {
        "en": [
          "Watthana"
        ],
        "he": [
          "וואטאנה"
        ],
        "th": []
      },
      "bma_code": "1039",
      "district_id": "geo:th:bma:1039",
      "indexing_policy": "non_indexable",
      "kind": "official_district",
      "names": {
        "en": "Vadhana",
        "he": "ואטאנה",
        "th": "วัฒนา"
      },
      "source_ids": [
        "source:bma:districts"
      ]
    },
    "geo:th:bma:1040": {
      "aliases": {
        "en": [
          "Bangkae"
        ],
        "he": [],
        "th": []
      },
      "bma_code": "1040",
      "district_id": "geo:th:bma:1040",
      "indexing_policy": "non_indexable",
      "kind": "official_district",
      "names": {
        "en": "Bang Khae",
        "he": "באנג קאה",
        "th": "บางแค"
      },
      "source_ids": [
        "source:bma:districts"
      ]
    },
    "geo:th:bma:1041": {
      "aliases": {
        "en": [
          "Laksi"
        ],
        "he": [],
        "th": []
      },
      "bma_code": "1041",
      "district_id": "geo:th:bma:1041",
      "indexing_policy": "non_indexable",
      "kind": "official_district",
      "names": {
        "en": "Lak Si",
        "he": "לאק סי",
        "th": "หลักสี่"
      },
      "source_ids": [
        "source:bma:districts"
      ]
    },
    "geo:th:bma:1042": {
      "aliases": {
        "en": [
          "Saimai"
        ],
        "he": [],
        "th": []
      },
      "bma_code": "1042",
      "district_id": "geo:th:bma:1042",
      "indexing_policy": "non_indexable",
      "kind": "official_district",
      "names": {
        "en": "Sai Mai",
        "he": "סאי מאי",
        "th": "สายไหม"
      },
      "source_ids": [
        "source:bma:districts"
      ]
    },
    "geo:th:bma:1043": {
      "aliases": {
        "en": [
          "Kannayao"
        ],
        "he": [],
        "th": []
      },
      "bma_code": "1043",
      "district_id": "geo:th:bma:1043",
      "indexing_policy": "non_indexable",
      "kind": "official_district",
      "names": {
        "en": "Khan Na Yao",
        "he": "קאן נא יאו",
        "th": "คันนายาว"
      },
      "source_ids": [
        "source:bma:districts"
      ]
    },
    "geo:th:bma:1044": {
      "aliases": {
        "en": [],
        "he": [],
        "th": []
      },
      "bma_code": "1044",
      "district_id": "geo:th:bma:1044",
      "indexing_policy": "non_indexable",
      "kind": "official_district",
      "names": {
        "en": "Saphan Sung",
        "he": "סאפאן סונג",
        "th": "สะพานสูง"
      },
      "source_ids": [
        "source:bma:districts"
      ]
    },
    "geo:th:bma:1045": {
      "aliases": {
        "en": [
          "Wangthonglang"
        ],
        "he": [],
        "th": []
      },
      "bma_code": "1045",
      "district_id": "geo:th:bma:1045",
      "indexing_policy": "non_indexable",
      "kind": "official_district",
      "names": {
        "en": "Wang Thonglang",
        "he": "וואנג תונגלנג",
        "th": "วังทองหลาง"
      },
      "source_ids": [
        "source:bma:districts"
      ]
    },
    "geo:th:bma:1046": {
      "aliases": {
        "en": [
          "Klong Sam Wa"
        ],
        "he": [],
        "th": []
      },
      "bma_code": "1046",
      "district_id": "geo:th:bma:1046",
      "indexing_policy": "non_indexable",
      "kind": "official_district",
      "names": {
        "en": "Khlong Sam Wa",
        "he": "קלונג סאם ווא",
        "th": "คลองสามวา"
      },
      "source_ids": [
        "source:bma:districts"
      ]
    },
    "geo:th:bma:1047": {
      "aliases": {
        "en": [
          "Bangna"
        ],
        "he": [],
        "th": []
      },
      "bma_code": "1047",
      "district_id": "geo:th:bma:1047",
      "indexing_policy": "non_indexable",
      "kind": "official_district",
      "names": {
        "en": "Bang Na",
        "he": "באנג נא",
        "th": "บางนา"
      },
      "source_ids": [
        "source:bma:districts"
      ]
    },
    "geo:th:bma:1048": {
      "aliases": {
        "en": [
          "Tawi Wattana"
        ],
        "he": [],
        "th": []
      },
      "bma_code": "1048",
      "district_id": "geo:th:bma:1048",
      "indexing_policy": "non_indexable",
      "kind": "official_district",
      "names": {
        "en": "Thawi Watthana",
        "he": "תאווי ואטאנה",
        "th": "ทวีวัฒนา"
      },
      "source_ids": [
        "source:bma:districts"
      ]
    },
    "geo:th:bma:1049": {
      "aliases": {
        "en": [
          "Tung Kru"
        ],
        "he": [],
        "th": []
      },
      "bma_code": "1049",
      "district_id": "geo:th:bma:1049",
      "indexing_policy": "non_indexable",
      "kind": "official_district",
      "names": {
        "en": "Thung Khru",
        "he": "תונג קרו",
        "th": "ทุ่งครุ"
      },
      "source_ids": [
        "source:bma:districts"
      ]
    },
    "geo:th:bma:1050": {
      "aliases": {
        "en": [
          "Bangbon"
        ],
        "he": [],
        "th": []
      },
      "bma_code": "1050",
      "district_id": "geo:th:bma:1050",
      "indexing_policy": "non_indexable",
      "kind": "official_district",
      "names": {
        "en": "Bang Bon",
        "he": "באנג בון",
        "th": "บางบอน"
      },
      "source_ids": [
        "source:bma:districts"
      ]
    }
  },
  "entity_policy": {
    "indexing_policy": "non_indexable_structured_entity",
    "market_area_kind": "market_area",
    "official_district_kind": "official_district",
    "relationship": "אזורי השוק מתארים חיפוש מגורים יומיומי ויכולים לחצות גבולות של מחוז רשמי אחד או יותר."
  },
  "fact_order": [
    "fact:thailand:tm30-notification",
    "fact:thailand:lease-controlled-contract",
    "fact:thailand:lease-controlled-scope",
    "fact:thailand:lease-upfront-limit",
    "fact:thailand:lease-utility-charges",
    "fact:thailand:lease-stamp-duty",
    "fact:thailand:lease-deposit-return"
  ],
  "facts_by_id": {
    "fact:thailand:lease-controlled-contract": {
      "checked_on": "2026-08-10",
      "effective_on": "2025-09-04",
      "fact_id": "fact:thailand:lease-controlled-contract",
      "public_label": "כללי חוזה שכירות מעודכנים",
      "public_value": "הודעת OCPB לעסקי השכרת מגורים מבוקרי חוזים נכנסה לתוקף ב-4 בספטמבר 2025, עם דרישה לחוזה ברור ולמסירת עותק לשוכר.",
      "source_ids": [
        "source:ocpb:residential-lease-2025"
      ]
    },
    "fact:thailand:lease-controlled-scope": {
      "checked_on": "2026-08-10",
      "effective_on": "2025-09-04",
      "fact_id": "fact:thailand:lease-controlled-scope",
      "public_label": "על אילו משכירים חלים הכללים",
      "public_value": "הודעת OCPB חלה על מפעיל שמשכיר שלוש יחידות מגורים או יותר, בבניין אחד או בכמה בניינים. בתי מלון ומעונות הכפופים לחוקים ייעודיים אינם נכללים.",
      "source_ids": [
        "source:ocpb:residential-lease-2025"
      ]
    },
    "fact:thailand:lease-deposit-return": {
      "checked_on": "2026-08-10",
      "effective_on": null,
      "fact_id": "fact:thailand:lease-deposit-return",
      "public_label": "מועד החזרת הפיקדון",
      "public_value": "לפי חוזה המגורים התקני של OCPB, כאשר אין נזק באשמת השוכר הפיקדון מוחזר מיד. אם נדרשת בדיקה, ההחזרה היא בתוך שבעה ימים מסיום החוזה והחזרת הנכס, ובלאי רגיל אינו נזק לחיוב.",
      "source_ids": [
        "source:ocpb:standard-lease-2026"
      ]
    },
    "fact:thailand:lease-stamp-duty": {
      "checked_on": "2026-08-10",
      "effective_on": null,
      "fact_id": "fact:thailand:lease-stamp-duty",
      "public_label": "מס בולים על חוזה שכירות",
      "public_value": "מס הבולים על שכירות מקרקעין הוא 1 באט לכל 1,000 באט, או חלק מהם, מסך דמי השכירות ודמי המפתח לאורך תקופת החוזה.",
      "source_ids": [
        "source:revenue:lease-stamp-duty"
      ]
    },
    "fact:thailand:lease-upfront-limit": {
      "checked_on": "2026-08-10",
      "effective_on": "2025-09-04",
      "fact_id": "fact:thailand:lease-upfront-limit",
      "public_label": "שכר דירה מראש ופיקדון",
      "public_value": "בעסק שכירות שנכלל בהודעת OCPB, הסכום המצטבר של שכר דירה מראש ופיקדון אינו יכול לעלות על שלושה חודשי שכירות.",
      "source_ids": [
        "source:ocpb:residential-lease-2025"
      ]
    },
    "fact:thailand:lease-utility-charges": {
      "checked_on": "2026-08-10",
      "effective_on": "2025-09-04",
      "fact_id": "fact:thailand:lease-utility-charges",
      "public_label": "חיובי מים וחשמל בחוזה",
      "public_value": "בעסק שכירות שנכלל בהודעת OCPB, חיובי מים וחשמל צריכים להיות שקופים ולא לעלות על התעריפים שגובים ספקי השירות הרלוונטיים.",
      "source_ids": [
        "source:ocpb:residential-lease-2025"
      ]
    },
    "fact:thailand:tm30-notification": {
      "checked_on": "2026-08-10",
      "effective_on": null,
      "fact_id": "fact:thailand:tm30-notification",
      "public_label": "הודעת מגורים TM30",
      "public_value": "בעל הבית, המחזיק בנכס או מנהל מקום האירוח שמארח אזרח זר באופן זמני צריך למסור הודעת TM30 להגירה בתוך 24 שעות מההגעה.",
      "source_ids": [
        "source:immigration:tm30"
      ]
    }
  },
  "pricing_method": {
    "checked_on": "2026-08-10",
    "currency": "THB",
    "furnishing_basis": "furnished",
    "home_type": "condominium",
    "lease_basis": "typical_12_month_asking_price",
    "pricing_method_id": "method:bangkok:rental-asking-bands",
    "public_label": "טווח שכירות חודשי משוער לקונדו מרוהט בחוזה טיפוסי של 12 חודשים, לפי מחירי פרסום שנבדקו ב-10 באוגוסט 2026",
    "rounding_thb": 1000,
    "source_ids": [
      "source:propertyscout:bangkok-condos",
      "source:ddproperty:bangkok-condos",
      "source:propertyhub:bangkok-condos"
    ],
    "unit": "month"
  },
  "public_labels": {
    "area_comparison_heading": "איפה כדאי לגור בבנגקוק לפי תקציב וסגנון חיים",
    "area_map_heading": "מפת אזורי המגורים בבנגקוק",
    "daily_life_heading": "איך נראים חיי היום יום",
    "estimated_band_heading": "טווח שכירות חודשי משוער",
    "fit_heading": "למי האזור מתאים",
    "micro_area_heading": "איפה להתמקד בתוך האזור",
    "tradeoff_heading": "מה כדאי לקחת בחשבון"
  },
  "registry_sha256": "71215583c1bc6428347d36a57705c92932eb1f7c275f128f065680d5bdb9ed22",
  "schema_sha256": "63af098f311c031489044f2a12aa315602ba923ed037782f46844282db0487cd",
  "schema_version": 1,
  "site": {
    "direction": "rtl",
    "locale": "he-IL",
    "origin": "https://thai-land.co.il",
    "parent_path": "/מדריך-להשכרת-דירה-בבנגקוק/",
    "parent_route_id": "bangkok-apartment-rental"
  },
  "source_order": [
    "source:bma:districts",
    "source:bma:district-vocabulary",
    "source:bma:district-overview",
    "source:bts:route-map",
    "source:bts:timetable",
    "source:bem:blue-line",
    "source:mrta:blue-line",
    "source:ocpb:residential-lease-2025",
    "source:ocpb:standard-lease-2026",
    "source:immigration:tm30",
    "source:revenue:lease-stamp-duty",
    "source:propertyscout:bangkok-condos",
    "source:ddproperty:bangkok-condos",
    "source:propertyhub:bangkok-condos"
  ],
  "source_sha256": "bc9f1f3b303ca0f91b7b87ae0078155c95abca5f8b08ac9f4d8dcb69da7067a9",
  "sources_by_id": {
    "source:bem:blue-line": {
      "checked_on": "2026-08-10",
      "kind": "official_operator",
      "name": "MRT Blue Line station map",
      "publisher": "Bangkok Expressway and Metro Public Company Limited",
      "source_id": "source:bem:blue-line",
      "supports": [
        "MRT Blue Line station codes",
        "MRT Blue Line station names",
        "MRT station sequence"
      ],
      "url": "https://metro.bemplc.co.th/Line-Maps?Line=1&lang=en"
    },
    "source:bma:district-overview": {
      "checked_on": "2026-08-10",
      "kind": "official_government",
      "name": "Bangkok 50 district map overview",
      "publisher": "Bangkok Metropolitan Administration",
      "source_id": "source:bma:district-overview",
      "supports": [
        "official district organization",
        "district map context"
      ],
      "url": "https://district.bangkok.go.th/SEDPortal/50-map-overview/"
    },
    "source:bma:district-vocabulary": {
      "checked_on": "2026-08-10",
      "kind": "official_government",
      "name": "Bangkok district Thai and English vocabulary",
      "publisher": "Bangkok Metropolitan Administration International Affairs Office",
      "source_id": "source:bma:district-vocabulary",
      "supports": [
        "official Thai district names",
        "official English district names",
        "district identity normalization"
      ],
      "url": "https://iao.bangkok.go.th/content-list/157"
    },
    "source:bma:districts": {
      "checked_on": "2026-08-10",
      "kind": "official_government",
      "name": "50 administrative districts of Bangkok",
      "publisher": "Bangkok Metropolitan Administration Open Government Data",
      "source_id": "source:bma:districts",
      "supports": [
        "official district count",
        "official district names",
        "administrative district boundaries"
      ],
      "url": "https://data.bangkok.go.th/dataset/e537025b-1cf6-4c5b-8e46-c2e976f13283/resource/0f40f9b4-617b-46a9-8806-f590da610954/download/district.kml"
    },
    "source:bts:route-map": {
      "checked_on": "2026-08-10",
      "kind": "official_operator",
      "name": "BTS route map",
      "publisher": "Bangkok Mass Transit System Public Company Limited",
      "source_id": "source:bts:route-map",
      "supports": [
        "BTS lines",
        "BTS station sequence",
        "BTS interchange context"
      ],
      "url": "https://www.bts.co.th/btsroutes/btsroutes.pdf"
    },
    "source:bts:timetable": {
      "checked_on": "2026-08-10",
      "kind": "official_operator",
      "name": "BTS station timetable and codes",
      "publisher": "Bangkok Mass Transit System Public Company Limited",
      "source_id": "source:bts:timetable",
      "supports": [
        "BTS station codes",
        "BTS station names"
      ],
      "url": "https://www.bts.co.th/pdf/timetable.pdf"
    },
    "source:ddproperty:bangkok-condos": {
      "checked_on": "2026-08-10",
      "kind": "market_listings",
      "name": "Bangkok condominium rental listings",
      "publisher": "DDproperty",
      "source_id": "source:ddproperty:bangkok-condos",
      "supports": [
        "current asking-price sample",
        "bedroom-level rental listings",
        "location comparison"
      ],
      "url": "https://www.ddproperty.com/en/condo-for-rent/in-bangkok-th10"
    },
    "source:immigration:tm30": {
      "checked_on": "2026-08-10",
      "kind": "official_government",
      "name": "TM30 notification of residence for foreign nationals",
      "publisher": "Immigration Bureau of Thailand",
      "source_id": "source:immigration:tm30",
      "supports": [
        "TM30 responsible party",
        "TM30 notification deadline",
        "official online notification channel"
      ],
      "url": "https://tm30.immigration.go.th/TM30/Foreigner/TM30EN/index.html"
    },
    "source:mrta:blue-line": {
      "checked_on": "2026-08-10",
      "kind": "official_government",
      "name": "MRT Chaloem Ratchamongkhon Line",
      "publisher": "Mass Rapid Transit Authority of Thailand",
      "source_id": "source:mrta:blue-line",
      "supports": [
        "MRT Blue Line authority",
        "current line scope"
      ],
      "url": "https://www.mrta.co.th/en/mrt-chaloem-ratchamongkhon"
    },
    "source:ocpb:residential-lease-2025": {
      "checked_on": "2026-08-10",
      "kind": "official_government",
      "name": "Residential accommodation lease controlled-contract update",
      "publisher": "Office of the Consumer Protection Board",
      "source_id": "source:ocpb:residential-lease-2025",
      "supports": [
        "2025 controlled-contract effective date and scope",
        "fair residential lease terms",
        "utility charge treatment"
      ],
      "url": "https://www.ocpb.go.th/news_view.php?nid=17943"
    },
    "source:ocpb:standard-lease-2026": {
      "checked_on": "2026-08-10",
      "kind": "official_government",
      "name": "OCPB standard residential lease example",
      "publisher": "Office of the Consumer Protection Board",
      "source_id": "source:ocpb:standard-lease-2026",
      "supports": [
        "security-deposit return timing",
        "tenant-caused damage treatment",
        "ordinary wear treatment"
      ],
      "url": "https://www.ocpb.go.th/article_attach/articlefile_2026060814431516244.pdf"
    },
    "source:propertyhub:bangkok-condos": {
      "checked_on": "2026-08-10",
      "kind": "market_listings",
      "name": "Condominiums for rent",
      "publisher": "PropertyHub",
      "source_id": "source:propertyhub:bangkok-condos",
      "supports": [
        "current asking-price sample",
        "building-level rental listings",
        "area-level rental listings"
      ],
      "url": "https://propertyhub.in.th/en/condo-for-rent"
    },
    "source:propertyscout:bangkok-condos": {
      "checked_on": "2026-08-10",
      "kind": "market_listings",
      "name": "Bangkok condominium rentals",
      "publisher": "PropertyScout",
      "source_id": "source:propertyscout:bangkok-condos",
      "supports": [
        "current asking-price sample",
        "bedroom-level rental listings",
        "area-level rental listings"
      ],
      "url": "https://propertyscout.co.th/en/bangkok/rentals/condo/"
    },
    "source:revenue:lease-stamp-duty": {
      "checked_on": "2026-08-10",
      "kind": "official_government",
      "name": "Thailand stamp duty schedule",
      "publisher": "Revenue Department of Thailand",
      "source_id": "source:revenue:lease-stamp-duty",
      "supports": [
        "lease stamp-duty rate",
        "stamp-duty calculation basis"
      ],
      "url": "https://www.rd.go.th/english/21986.html"
    }
  },
  "station_id_by_alias": {
    "en": {
      "ari": "transit:bts:n5",
      "asok": "transit:bts:e4",
      "chong nonsi": "transit:bts:s3",
      "ekkamai": "transit:bts:e7",
      "huai khwang": "transit:mrt:bl18",
      "lumphini": "transit:mrt:bl25",
      "on nut": "transit:bts:e9",
      "phra khanong": "transit:bts:e8",
      "phra ram 9": "transit:mrt:bl20",
      "phrom phong": "transit:bts:e5",
      "saint louis": "transit:bts:s4",
      "sala daeng": "transit:bts:s2",
      "sam yan": "transit:mrt:bl27",
      "si lom": "transit:mrt:bl26",
      "sukhumvit": "transit:mrt:bl22",
      "surasak": "transit:bts:s5",
      "sutthisan": "transit:mrt:bl17",
      "thailand cultural centre": "transit:mrt:bl19",
      "thong lo": "transit:bts:e6"
    },
    "he": {
      "און נוט": "transit:bts:e9",
      "אסוק": "transit:bts:e4",
      "אקאמאיי": "transit:bts:e7",
      "ארי": "transit:bts:n5",
      "הואי קוואנג": "transit:mrt:bl18",
      "טונג לו": "transit:bts:e6",
      "לומפיני": "transit:mrt:bl25",
      "מרכז התרבות של תאילנד": "transit:mrt:bl19",
      "סאלה דאנג": "transit:bts:s2",
      "סאם יאן": "transit:mrt:bl27",
      "סוטיסאן": "transit:mrt:bl17",
      "סוקומוויט": "transit:mrt:bl22",
      "סוראסאק": "transit:bts:s5",
      "סי לום": "transit:mrt:bl26",
      "סנט לואיס": "transit:bts:s4",
      "פרה קנונג": "transit:bts:e8",
      "פרה ראם 9": "transit:mrt:bl20",
      "פרום פונג": "transit:bts:e5",
      "צ ונג נונסי": "transit:bts:s3"
    },
    "th": {
      "ช่องนนทรี": "transit:bts:s3",
      "ทองหล่อ": "transit:bts:e6",
      "พระราม 9": "transit:mrt:bl20",
      "พระโขนง": "transit:bts:e8",
      "พร้อมพงษ์": "transit:bts:e5",
      "ลุมพินี": "transit:mrt:bl25",
      "ศาลาแดง": "transit:bts:s2",
      "ศูนย์วัฒนธรรมแห่งประเทศไทย": "transit:mrt:bl19",
      "สามย่าน": "transit:mrt:bl27",
      "สีลม": "transit:mrt:bl26",
      "สุขุมวิท": "transit:mrt:bl22",
      "สุทธิสาร": "transit:mrt:bl17",
      "สุรศักดิ์": "transit:bts:s5",
      "ห้วยขวาง": "transit:mrt:bl18",
      "อารีย์": "transit:bts:n5",
      "อโศก": "transit:bts:e4",
      "อ่อนนุช": "transit:bts:e9",
      "เซนต์หลุยส์": "transit:bts:s4",
      "เอกมัย": "transit:bts:e7"
    }
  },
  "station_id_by_code": {
    "BL17": "transit:mrt:bl17",
    "BL18": "transit:mrt:bl18",
    "BL19": "transit:mrt:bl19",
    "BL20": "transit:mrt:bl20",
    "BL22": "transit:mrt:bl22",
    "BL25": "transit:mrt:bl25",
    "BL26": "transit:mrt:bl26",
    "BL27": "transit:mrt:bl27",
    "E4": "transit:bts:e4",
    "E5": "transit:bts:e5",
    "E6": "transit:bts:e6",
    "E7": "transit:bts:e7",
    "E8": "transit:bts:e8",
    "E9": "transit:bts:e9",
    "N5": "transit:bts:n5",
    "S2": "transit:bts:s2",
    "S3": "transit:bts:s3",
    "S4": "transit:bts:s4",
    "S5": "transit:bts:s5"
  },
  "station_ids_by_line_id": {
    "transit:bts:silom": [
      "transit:bts:s2",
      "transit:bts:s3",
      "transit:bts:s4",
      "transit:bts:s5"
    ],
    "transit:bts:sukhumvit": [
      "transit:bts:e4",
      "transit:bts:e5",
      "transit:bts:e6",
      "transit:bts:e7",
      "transit:bts:e8",
      "transit:bts:e9",
      "transit:bts:n5"
    ],
    "transit:mrt:blue": [
      "transit:mrt:bl17",
      "transit:mrt:bl18",
      "transit:mrt:bl19",
      "transit:mrt:bl20",
      "transit:mrt:bl22",
      "transit:mrt:bl25",
      "transit:mrt:bl26",
      "transit:mrt:bl27"
    ]
  },
  "station_order": [
    "transit:bts:n5",
    "transit:bts:e4",
    "transit:bts:e5",
    "transit:bts:e6",
    "transit:bts:e7",
    "transit:bts:e8",
    "transit:bts:e9",
    "transit:bts:s2",
    "transit:bts:s3",
    "transit:bts:s4",
    "transit:bts:s5",
    "transit:mrt:bl17",
    "transit:mrt:bl18",
    "transit:mrt:bl19",
    "transit:mrt:bl20",
    "transit:mrt:bl22",
    "transit:mrt:bl25",
    "transit:mrt:bl26",
    "transit:mrt:bl27"
  ],
  "stations_by_id": {
    "transit:bts:e4": {
      "code": "E4",
      "coordinates": {
        "basis": "editorial point aligned to the operator map",
        "lat": 13.7369,
        "lng": 100.5604
      },
      "line_id": "transit:bts:sukhumvit",
      "mode": "bts",
      "names": {
        "en": "Asok",
        "he": "אסוק",
        "th": "อโศก"
      },
      "source_ids": [
        "source:bts:route-map",
        "source:bts:timetable"
      ],
      "station_id": "transit:bts:e4"
    },
    "transit:bts:e5": {
      "code": "E5",
      "coordinates": {
        "basis": "editorial point aligned to the operator map",
        "lat": 13.7303,
        "lng": 100.5696
      },
      "line_id": "transit:bts:sukhumvit",
      "mode": "bts",
      "names": {
        "en": "Phrom Phong",
        "he": "פרום פונג",
        "th": "พร้อมพงษ์"
      },
      "source_ids": [
        "source:bts:route-map",
        "source:bts:timetable"
      ],
      "station_id": "transit:bts:e5"
    },
    "transit:bts:e6": {
      "code": "E6",
      "coordinates": {
        "basis": "editorial point aligned to the operator map",
        "lat": 13.7242,
        "lng": 100.5785
      },
      "line_id": "transit:bts:sukhumvit",
      "mode": "bts",
      "names": {
        "en": "Thong Lo",
        "he": "טונג לו",
        "th": "ทองหล่อ"
      },
      "source_ids": [
        "source:bts:route-map",
        "source:bts:timetable"
      ],
      "station_id": "transit:bts:e6"
    },
    "transit:bts:e7": {
      "code": "E7",
      "coordinates": {
        "basis": "editorial point aligned to the operator map",
        "lat": 13.7195,
        "lng": 100.585
      },
      "line_id": "transit:bts:sukhumvit",
      "mode": "bts",
      "names": {
        "en": "Ekkamai",
        "he": "אקאמאיי",
        "th": "เอกมัย"
      },
      "source_ids": [
        "source:bts:route-map",
        "source:bts:timetable"
      ],
      "station_id": "transit:bts:e7"
    },
    "transit:bts:e8": {
      "code": "E8",
      "coordinates": {
        "basis": "editorial point aligned to the operator map",
        "lat": 13.7151,
        "lng": 100.5914
      },
      "line_id": "transit:bts:sukhumvit",
      "mode": "bts",
      "names": {
        "en": "Phra Khanong",
        "he": "פרה קנונג",
        "th": "พระโขนง"
      },
      "source_ids": [
        "source:bts:route-map",
        "source:bts:timetable"
      ],
      "station_id": "transit:bts:e8"
    },
    "transit:bts:e9": {
      "code": "E9",
      "coordinates": {
        "basis": "editorial point aligned to the operator map",
        "lat": 13.7056,
        "lng": 100.601
      },
      "line_id": "transit:bts:sukhumvit",
      "mode": "bts",
      "names": {
        "en": "On Nut",
        "he": "און נוט",
        "th": "อ่อนนุช"
      },
      "source_ids": [
        "source:bts:route-map",
        "source:bts:timetable"
      ],
      "station_id": "transit:bts:e9"
    },
    "transit:bts:n5": {
      "code": "N5",
      "coordinates": {
        "basis": "editorial point aligned to the operator map",
        "lat": 13.7797,
        "lng": 100.5447
      },
      "line_id": "transit:bts:sukhumvit",
      "mode": "bts",
      "names": {
        "en": "Ari",
        "he": "ארי",
        "th": "อารีย์"
      },
      "source_ids": [
        "source:bts:route-map",
        "source:bts:timetable"
      ],
      "station_id": "transit:bts:n5"
    },
    "transit:bts:s2": {
      "code": "S2",
      "coordinates": {
        "basis": "editorial point aligned to the operator map",
        "lat": 13.7285,
        "lng": 100.534
      },
      "line_id": "transit:bts:silom",
      "mode": "bts",
      "names": {
        "en": "Sala Daeng",
        "he": "סאלה דאנג",
        "th": "ศาลาแดง"
      },
      "source_ids": [
        "source:bts:route-map",
        "source:bts:timetable"
      ],
      "station_id": "transit:bts:s2"
    },
    "transit:bts:s3": {
      "code": "S3",
      "coordinates": {
        "basis": "editorial point aligned to the operator map",
        "lat": 13.7237,
        "lng": 100.5293
      },
      "line_id": "transit:bts:silom",
      "mode": "bts",
      "names": {
        "en": "Chong Nonsi",
        "he": "צ׳ונג נונסי",
        "th": "ช่องนนทรี"
      },
      "source_ids": [
        "source:bts:route-map",
        "source:bts:timetable"
      ],
      "station_id": "transit:bts:s3"
    },
    "transit:bts:s4": {
      "code": "S4",
      "coordinates": {
        "basis": "operator station point",
        "lat": 13.7210655983,
        "lng": 100.5269959967
      },
      "line_id": "transit:bts:silom",
      "mode": "bts",
      "names": {
        "en": "Saint Louis",
        "he": "סנט לואיס",
        "th": "เซนต์หลุยส์"
      },
      "source_ids": [
        "source:bts:route-map",
        "source:bts:timetable"
      ],
      "station_id": "transit:bts:s4"
    },
    "transit:bts:s5": {
      "code": "S5",
      "coordinates": {
        "basis": "editorial point aligned to the operator map",
        "lat": 13.7191,
        "lng": 100.5214
      },
      "line_id": "transit:bts:silom",
      "mode": "bts",
      "names": {
        "en": "Surasak",
        "he": "סוראסאק",
        "th": "สุรศักดิ์"
      },
      "source_ids": [
        "source:bts:route-map",
        "source:bts:timetable"
      ],
      "station_id": "transit:bts:s5"
    },
    "transit:mrt:bl17": {
      "code": "BL17",
      "coordinates": {
        "basis": "editorial point aligned to the operator map",
        "lat": 13.7895,
        "lng": 100.5742
      },
      "line_id": "transit:mrt:blue",
      "mode": "mrt",
      "names": {
        "en": "Sutthisan",
        "he": "סוטיסאן",
        "th": "สุทธิสาร"
      },
      "source_ids": [
        "source:bem:blue-line",
        "source:mrta:blue-line"
      ],
      "station_id": "transit:mrt:bl17"
    },
    "transit:mrt:bl18": {
      "code": "BL18",
      "coordinates": {
        "basis": "editorial point aligned to the operator map",
        "lat": 13.7786,
        "lng": 100.5737
      },
      "line_id": "transit:mrt:blue",
      "mode": "mrt",
      "names": {
        "en": "Huai Khwang",
        "he": "הואי קוואנג",
        "th": "ห้วยขวาง"
      },
      "source_ids": [
        "source:bem:blue-line",
        "source:mrta:blue-line"
      ],
      "station_id": "transit:mrt:bl18"
    },
    "transit:mrt:bl19": {
      "code": "BL19",
      "coordinates": {
        "basis": "editorial point aligned to the operator map",
        "lat": 13.7662,
        "lng": 100.5702
      },
      "line_id": "transit:mrt:blue",
      "mode": "mrt",
      "names": {
        "en": "Thailand Cultural Centre",
        "he": "מרכז התרבות של תאילנד",
        "th": "ศูนย์วัฒนธรรมแห่งประเทศไทย"
      },
      "source_ids": [
        "source:bem:blue-line",
        "source:mrta:blue-line"
      ],
      "station_id": "transit:mrt:bl19"
    },
    "transit:mrt:bl20": {
      "code": "BL20",
      "coordinates": {
        "basis": "editorial point aligned to the operator map",
        "lat": 13.757,
        "lng": 100.5659
      },
      "line_id": "transit:mrt:blue",
      "mode": "mrt",
      "names": {
        "en": "Phra Ram 9",
        "he": "פרה ראם 9",
        "th": "พระราม 9"
      },
      "source_ids": [
        "source:bem:blue-line",
        "source:mrta:blue-line"
      ],
      "station_id": "transit:mrt:bl20"
    },
    "transit:mrt:bl22": {
      "code": "BL22",
      "coordinates": {
        "basis": "editorial point aligned to the operator map",
        "lat": 13.7385,
        "lng": 100.5615
      },
      "line_id": "transit:mrt:blue",
      "mode": "mrt",
      "names": {
        "en": "Sukhumvit",
        "he": "סוקומוויט",
        "th": "สุขุมวิท"
      },
      "source_ids": [
        "source:bem:blue-line",
        "source:mrta:blue-line"
      ],
      "station_id": "transit:mrt:bl22"
    },
    "transit:mrt:bl25": {
      "code": "BL25",
      "coordinates": {
        "basis": "editorial point aligned to the operator map",
        "lat": 13.7257,
        "lng": 100.545
      },
      "line_id": "transit:mrt:blue",
      "mode": "mrt",
      "names": {
        "en": "Lumphini",
        "he": "לומפיני",
        "th": "ลุมพินี"
      },
      "source_ids": [
        "source:bem:blue-line",
        "source:mrta:blue-line"
      ],
      "station_id": "transit:mrt:bl25"
    },
    "transit:mrt:bl26": {
      "code": "BL26",
      "coordinates": {
        "basis": "editorial point aligned to the operator map",
        "lat": 13.7292,
        "lng": 100.5365
      },
      "line_id": "transit:mrt:blue",
      "mode": "mrt",
      "names": {
        "en": "Si Lom",
        "he": "סי לום",
        "th": "สีลม"
      },
      "source_ids": [
        "source:bem:blue-line",
        "source:mrta:blue-line"
      ],
      "station_id": "transit:mrt:bl26"
    },
    "transit:mrt:bl27": {
      "code": "BL27",
      "coordinates": {
        "basis": "editorial point aligned to the operator map",
        "lat": 13.7323,
        "lng": 100.5298
      },
      "line_id": "transit:mrt:blue",
      "mode": "mrt",
      "names": {
        "en": "Sam Yan",
        "he": "סאם יאן",
        "th": "สามย่าน"
      },
      "source_ids": [
        "source:bem:blue-line",
        "source:mrta:blue-line"
      ],
      "station_id": "transit:mrt:bl27"
    }
  }
}
THAILAND_PLATFORM_BANGKOK_RENTAL_JSON,
	true,
	512,
	JSON_THROW_ON_ERROR
);
