<?php
/**
 * Generated real-estate content registry.
 *
 * Run scripts/build_content_registry.py to rebuild this file.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return json_decode(
	<<<'THAILAND_PLATFORM_CONTENT_JSON'
{
  "body_contract": {
    "mode": "preserve_wordpress_body",
    "mutation_allowed": false,
    "prefix_components": [
      "breadcrumb",
      "route_intro"
    ],
    "public_punctuation_policy": "replace_long_dashes_with_hyphen",
    "source_field": "post_content",
    "suffix_components": [
      "continuations",
      "freshness",
      "sources"
    ],
    "wordpress_filter": "the_content"
  },
  "children_by_parent": {
    "thailand-real-estate": [
      "bangkok-apartment-rental",
      "buy-property-thailand",
      "foreign-condo-ownership-thailand",
      "thailand-property-buying-mistakes",
      "thailand-property-financing",
      "thailand-property-management",
      "thailand-property-prices"
    ]
  },
  "contract_id": "thailand-real-estate-v1",
  "freshness_by_id": {
    "market-monthly": {
      "detail": "ריבית, שערי מטבע, עמלות ומחירי שוק משתנים לאורך הזמן",
      "freshness_id": "market-monthly",
      "label": "מחירים ומימון"
    },
    "rental-monthly": {
      "detail": "מחירי פרסום, מלאי דירות, תנאי חוזה, פיקדונות וחיובים יכולים להשתנות",
      "freshness_id": "rental-monthly",
      "label": "מחירי שכירות וכללי חוזה"
    },
    "rules-monthly": {
      "detail": "חוקים, נהלים, מסמכים ואגרות יכולים להשתנות",
      "freshness_id": "rules-monthly",
      "label": "בעלות ורישום"
    },
    "service-quarterly": {
      "detail": "זמינות שירותים, דמי ניהול, תחזוקה ועלויות תפעול משתנים",
      "freshness_id": "service-quarterly",
      "label": "ניהול ושכירות"
    }
  },
  "hub_experience": {
    "cards": [
      {
        "action_label": "להבין את תהליך הקנייה",
        "eyebrow": "מסלול רכישה",
        "route_id": "buy-property-thailand",
        "summary": "כל השלבים מהגדרת התקציב ועד רישום ומסירה.",
        "title": "קניית נכס בתאילנד"
      },
      {
        "action_label": "לעבור על הבדיקות",
        "eyebrow": "בדיקות לפני חתימה",
        "route_id": "thailand-property-buying-mistakes",
        "summary": "נקודות הסיכון שצריך לבדוק לפני התחייבות ותשלום.",
        "title": "טעויות שכדאי למנוע"
      },
      {
        "action_label": "להכיר את כללי הבעלות",
        "eyebrow": "בעלות ורישום",
        "route_id": "foreign-condo-ownership-thailand",
        "summary": "כללי בית משותף, מסמכי בעלות ורישום הזכויות.",
        "title": "זכויות זרים בדירה"
      },
      {
        "action_label": "לבנות מסגרת תקציב",
        "eyebrow": "תקציב מלא",
        "route_id": "thailand-property-prices",
        "summary": "מחיר הנכס לצד מסים, אגרות והוצאות החזקה.",
        "title": "מחירים ועלויות עסקה"
      },
      {
        "action_label": "להשוות אפשרויות מימון",
        "eyebrow": "הון ואשראי",
        "route_id": "thailand-property-financing",
        "summary": "אפשרויות מימון, מסמכים, ריבית והחזר חודשי.",
        "title": "משכנתא ומימון"
      },
      {
        "action_label": "לבחור דירה ושכונה",
        "eyebrow": "חיים בבנגקוק",
        "route_id": "bangkok-apartment-rental",
        "summary": "שכונות, חוזה, פיקדון, חשבונות ובדיקת הדירה.",
        "title": "השכרת דירה בבנגקוק"
      },
      {
        "action_label": "לתכנן ניהול שוטף",
        "eyebrow": "אחרי המסירה",
        "route_id": "thailand-property-management",
        "summary": "תחזוקה, גבייה, דיווח ובקרה גם מרחוק.",
        "title": "ניהול נכס ושוכרים"
      }
    ],
    "decision_paths": [
      {
        "choices": [
          {
            "detail": "להכיר את המסלול המלא לפני שמתחילים לחפש עסקה",
            "label": "לקנות נכס",
            "target_route_id": "buy-property-thailand"
          },
          {
            "detail": "לבחור שכונה, להבין חוזה ולחשב עלות חודשית",
            "label": "לשכור בבנגקוק",
            "target_route_id": "bangkok-apartment-rental"
          },
          {
            "detail": "לבנות שגרה לשוכרים, גבייה, תחזוקה ודיווח",
            "label": "לנהל נכס קיים",
            "target_route_id": "thailand-property-management"
          }
        ],
        "decision_id": "choose-goal",
        "prompt": "מה אתם רוצים לעשות עכשיו?"
      },
      {
        "choices": [
          {
            "detail": "לסדר מסמכים ובדיקות לפני מקדמה או חתימה",
            "label": "בדיקות וסיכונים",
            "target_route_id": "thailand-property-buying-mistakes"
          },
          {
            "detail": "להבין זכויות זרים בדירה בבית משותף",
            "label": "בעלות ורישום",
            "target_route_id": "foreign-condo-ownership-thailand"
          },
          {
            "detail": "לבדוק כיצד מבנה המימון משפיע על העסקה",
            "label": "מימון והון עצמי",
            "target_route_id": "thailand-property-financing"
          }
        ],
        "decision_id": "reduce-risk",
        "prompt": "מה חשוב לבדוק לפני עסקה?"
      },
      {
        "choices": [
          {
            "detail": "להעריך מחיר נכס ולהוסיף מסים והוצאות נלוות",
            "label": "מתחילים במחירי השוק",
            "target_route_id": "thailand-property-prices"
          },
          {
            "detail": "לחבר הון עצמי, ריבית ועלות אשראי לתקציב",
            "label": "מחשבים את המימון",
            "target_route_id": "thailand-property-financing"
          },
          {
            "detail": "להכניס לתקציב בדיקות, תשלומים, רישום ומסירה",
            "label": "מחברים את כל שלבי הקנייה",
            "target_route_id": "buy-property-thailand"
          }
        ],
        "decision_id": "build-budget",
        "prompt": "איך בונים תקציב אמיתי?"
      }
    ],
    "section_heading": "בחרו את השלב שמתאים לכם",
    "sections": [
      {
        "description": "מתחילים בתהליך הקנייה, ממשיכים לבדיקות ומבינים כיצד נרשמות זכויות בדירה.",
        "route_ids": [
          "buy-property-thailand",
          "thailand-property-buying-mistakes",
          "foreign-condo-ownership-thailand"
        ],
        "section_id": "buy-safely",
        "title": "קונים בצורה מסודרת"
      },
      {
        "description": "מחברים בין מחיר הנכס, ההוצאות הנלוות, ההון העצמי ועלות האשראי.",
        "route_ids": [
          "thailand-property-prices",
          "thailand-property-financing"
        ],
        "section_id": "budget-and-finance",
        "title": "בונים תקציב ומימון"
      },
      {
        "description": "בוחרים דירה בבנגקוק או מתכננים שוכרים, תחזוקה וגבייה לנכס קיים.",
        "route_ids": [
          "bangkok-apartment-rental",
          "thailand-property-management"
        ],
        "section_id": "rent-and-manage",
        "title": "שוכרים או מנהלים נכס"
      }
    ]
  },
  "hub_route_id": "thailand-real-estate",
  "public_labels": {
    "breadcrumbs_aria": "פירורי לחם",
    "card_action": "למדריך המלא",
    "continuations_heading": "עוד מדריכים בנושא",
    "freshness_heading": "פרטים שמשתנים",
    "hub_return": "לכל מדריכי הנדל״ן בתאילנד",
    "sources_heading": "מקורות שימושיים"
  },
  "registry_sha256": "e344c053f81ce996ebbb3b6fa86ee4138076332f0bfaa8a038a1f4f073f57051",
  "rendering_owners": {
    "breadcrumb": "content_template_once",
    "h1": "content_template",
    "metadata": "yoast_filters",
    "schema": "none_in_v1"
  },
  "route_id_by_path": {
    "/5-הטעויות-המובילות-שיש-להימנע-מהן-בעת/": "thailand-property-buying-mistakes",
    "/price/": "thailand-property-prices",
    "/property-management/": "thailand-property-management",
    "/אפשרויות-משכנתא-ומימון-נכסים-בתאילנד/": "thailand-property-financing",
    "/זכויות-בית-משותף-נכס-בתאילנד/": "foreign-condo-ownership-thailand",
    "/מדריך-להשכרת-דירה-בבנגקוק/": "bangkok-apartment-rental",
    "/נדלן-בתאילנד/": "thailand-real-estate",
    "/קניית-נכס-בתאילנד/": "buy-property-thailand"
  },
  "route_id_by_post_id": {
    "118": "bangkok-apartment-rental",
    "336": "buy-property-thailand",
    "474": "foreign-condo-ownership-thailand",
    "609": "thailand-property-management",
    "65": "thailand-property-financing",
    "69": "thailand-property-buying-mistakes",
    "810": "thailand-property-prices",
    "841": "thailand-real-estate"
  },
  "route_id_by_seo_owner_id": {
    "bangkok-apartment-rental-guide": "bangkok-apartment-rental",
    "buy-property-thailand": "buy-property-thailand",
    "foreign-condo-ownership-thailand": "foreign-condo-ownership-thailand",
    "property-management-thailand": "thailand-property-management",
    "thailand-property-due-diligence-mistakes": "thailand-property-buying-mistakes",
    "thailand-property-financing": "thailand-property-financing",
    "thailand-property-prices": "thailand-property-prices",
    "thailand-real-estate": "thailand-real-estate"
  },
  "routes_by_id": {
    "bangkok-apartment-rental": {
      "breadcrumbs": [
        {
          "label": "ראשי",
          "path": "/",
          "route_id": null
        },
        {
          "label": "נדל״ן בתאילנד",
          "path": "/נדלן-בתאילנד/",
          "route_id": "thailand-real-estate"
        },
        {
          "label": "השכרת דירה בבנגקוק",
          "path": "/מדריך-להשכרת-דירה-בבנגקוק/",
          "route_id": "bangkok-apartment-rental"
        }
      ],
      "continuations": [
        {
          "context": "ראו כיצד מטפלים בחוזה, גבייה, תחזוקה ותקשורת שוטפת בין בעל נכס לשוכר.",
          "label": "ניהול דירה ושוכרים בתאילנד",
          "target_route_id": "thailand-property-management"
        },
        {
          "context": "השוו את מסלול השכירות לשלבי הרכישה ולעלויות שמתווספות לבעלות על נכס.",
          "label": "קניית נכס בתאילנד",
          "target_route_id": "buy-property-thailand"
        }
      ],
      "freshness_id": "rental-monthly",
      "kind": "spoke",
      "ownership": {
        "intent": "לבחור אזור ודירה בבנגקוק ולהבין מחירים, חוזה, פיקדון, חשבונות ועלויות שכירות",
        "primary_keyword": "השכרת דירה בבנגקוק",
        "synonyms": [
          "דירות להשכרה בבנגקוק לטווח ארוך",
          "חוזה שכירות בבנגקוק",
          "Bangkok apartment rent"
        ]
      },
      "parent_link": {
        "context": "חזרו למפת הדרכים המלאה כדי להשוות בין שכירות, קנייה וניהול נכס בתאילנד.",
        "label": "לכל מדריכי הנדל״ן בתאילנד",
        "target_route_id": "thailand-real-estate"
      },
      "parent_route_id": "thailand-real-estate",
      "path": "/מדריך-להשכרת-דירה-בבנגקוק/",
      "public": {
        "h1": "השכרת דירה בבנגקוק: מחירים, אזורים וחוזה",
        "meta_description": "השכרת דירה בבנגקוק עם טווחי מחיר ב-10 אזורים, השוואת BTS ו-MRT, פיקדון, חשבונות וכללי חוזה שכדאי להכיר לפני כניסה.",
        "seo_title": "השכרת דירה בבנגקוק: מחירים, אזורים וחוזה",
        "summary": "השוו 10 אזורי מגורים לפי תקציב ורכבת, חשבו את הכסף הדרוש לחתימה והכירו את כללי החוזה לפני שבוחרים דירה."
      },
      "route_id": "bangkok-apartment-rental",
      "seo_owner_id": "bangkok-apartment-rental-guide",
      "source_ids": [
        "bangkok-metropolitan-administration",
        "consumer-protection-board",
        "thai-revenue-department"
      ],
      "wordpress": {
        "body_mode": "preserve",
        "identity_policy": "id_and_path_exact",
        "post_id": 118,
        "post_type": "post"
      }
    },
    "buy-property-thailand": {
      "breadcrumbs": [
        {
          "label": "ראשי",
          "path": "/",
          "route_id": null
        },
        {
          "label": "נדל״ן בתאילנד",
          "path": "/נדלן-בתאילנד/",
          "route_id": "thailand-real-estate"
        },
        {
          "label": "קניית נכס בתאילנד",
          "path": "/קניית-נכס-בתאילנד/",
          "route_id": "buy-property-thailand"
        }
      ],
      "continuations": [
        {
          "context": "התאימו את מבנה העסקה להון העצמי, למטבע, לריבית ולחלופות המימון הזמינות.",
          "label": "אפשרויות משכנתא ומימון",
          "target_route_id": "thailand-property-financing"
        },
        {
          "context": "עברו על נקודות הסיכון החשובות לפני מקדמה, חתימה והעברת זכויות.",
          "label": "בדיקות שמונעות טעויות ברכישה",
          "target_route_id": "thailand-property-buying-mistakes"
        },
        {
          "context": "השוו שכירות ארוכת טווח לרכישה כאשר עדיין בוחנים אזור ואורח חיים.",
          "label": "השכרה בבנגקוק לפני קנייה",
          "target_route_id": "bangkok-apartment-rental"
        },
        {
          "context": "בדקו כיצד נרשמות זכויות בבית משותף ומה צריך להופיע במסמכי העסקה.",
          "label": "כללי בעלות זרים בדירה",
          "target_route_id": "foreign-condo-ownership-thailand"
        },
        {
          "context": "הכניסו לתקציב גם שוכרים, תחזוקה, גבייה ודיווח לאחר השלמת הקנייה.",
          "label": "ניהול הנכס אחרי המסירה",
          "target_route_id": "thailand-property-management"
        },
        {
          "context": "בנו תקציב שכולל את מחיר הנכס, מסים, אגרות והוצאות ההחזקה.",
          "label": "מחירי נכסים ועלויות נלוות",
          "target_route_id": "thailand-property-prices"
        }
      ],
      "freshness_id": "rules-monthly",
      "kind": "spoke",
      "ownership": {
        "intent": "להבין את תהליך רכישת הנכס בתאילנד משלב התקציב והבדיקות ועד חוזה ורישום",
        "primary_keyword": "קניית נכס בתאילנד",
        "synonyms": [
          "רכישת נכס בתאילנד",
          "איך לקנות נכס בתאילנד",
          "buy property Thailand"
        ]
      },
      "parent_link": {
        "context": "חזרו למפת הדרכים המלאה כדי לבחור את המדריך הבא לפי סוג העסקה והשלב שבו אתם נמצאים.",
        "label": "לכל מדריכי הנדל״ן בתאילנד",
        "target_route_id": "thailand-real-estate"
      },
      "parent_route_id": "thailand-real-estate",
      "path": "/קניית-נכס-בתאילנד/",
      "public": {
        "h1": "קניית נכס בתאילנד: תהליך, בעלות ועלויות",
        "meta_description": "קניית נכס בתאילנד צעד אחר צעד: בחירת סוג נכס, בדיקות, מבנה בעלות, חוזה, תשלומים, מסים, רישום ומסירה מסודרת של הנכס.",
        "seo_title": "קניית נכס בתאילנד: מדריך לתהליך ולעלויות",
        "summary": "מסלול קנייה מסודר שמחבר בין החלטת התקציב, בדיקת הנכס, מסמכי העסקה, העברת הכסף ורישום הזכויות."
      },
      "route_id": "buy-property-thailand",
      "seo_owner_id": "buy-property-thailand",
      "source_ids": [
        "royal-gazette",
        "thai-land-department",
        "thai-revenue-department"
      ],
      "wordpress": {
        "body_mode": "preserve",
        "identity_policy": "id_and_path_exact",
        "post_id": 336,
        "post_type": "post"
      }
    },
    "foreign-condo-ownership-thailand": {
      "breadcrumbs": [
        {
          "label": "ראשי",
          "path": "/",
          "route_id": null
        },
        {
          "label": "נדל״ן בתאילנד",
          "path": "/נדלן-בתאילנד/",
          "route_id": "thailand-real-estate"
        },
        {
          "label": "בעלות זרים על דירה",
          "path": "/זכויות-בית-משותף-נכס-בתאילנד/",
          "route_id": "foreign-condo-ownership-thailand"
        }
      ],
      "continuations": [
        {
          "context": "הפכו את כללי הבעלות לרשימת מסמכים ובדיקות שצריך להשלים לפני העסקה.",
          "label": "בדיקות נאותות לפני חתימה",
          "target_route_id": "thailand-property-buying-mistakes"
        },
        {
          "context": "ראו היכן בדיקת הזכויות והרישום משתלבים במסלול הקנייה המלא.",
          "label": "תהליך רכישת דירה בתאילנד",
          "target_route_id": "buy-property-thailand"
        }
      ],
      "freshness_id": "rules-monthly",
      "kind": "spoke",
      "ownership": {
        "intent": "להבין כיצד זרים מחזיקים זכויות בדירה בבית משותף ואילו מסמכים נדרשים לרישום",
        "primary_keyword": "בעלות זרים על דירה בתאילנד",
        "synonyms": [
          "זכויות זרים בבית משותף בתאילנד",
          "רישום קונדומיניום על שם זר",
          "foreign condo ownership Thailand"
        ]
      },
      "parent_link": {
        "context": "חזרו למפת הדרכים המלאה כדי לחבר את כללי הבעלות לתהליך הקנייה, המימון והניהול.",
        "label": "לכל מדריכי הנדל״ן בתאילנד",
        "target_route_id": "thailand-real-estate"
      },
      "parent_route_id": "thailand-real-estate",
      "path": "/זכויות-בית-משותף-נכס-בתאילנד/",
      "public": {
        "h1": "בעלות זרים על דירה בתאילנד: זכויות בבית משותף",
        "meta_description": "בעלות זרים על דירה בתאילנד: הבינו את כללי הבית המשותף, מסמכי הבעלות, רישום הזכויות, מכסות הבעלות והבדיקות לפני רכישה.",
        "seo_title": "בעלות זרים על דירה בתאילנד: כללי רישום וזכויות",
        "summary": "הסבר ממוקד על מבנה הזכויות בדירה בבית משותף ועל המסמכים שצריכים להתחבר לעסקה ולרישום."
      },
      "route_id": "foreign-condo-ownership-thailand",
      "seo_owner_id": "foreign-condo-ownership-thailand",
      "source_ids": [
        "royal-gazette",
        "thai-land-department"
      ],
      "wordpress": {
        "body_mode": "preserve",
        "identity_policy": "id_and_path_exact",
        "post_id": 474,
        "post_type": "post"
      }
    },
    "thailand-property-buying-mistakes": {
      "breadcrumbs": [
        {
          "label": "ראשי",
          "path": "/",
          "route_id": null
        },
        {
          "label": "נדל״ן בתאילנד",
          "path": "/נדלן-בתאילנד/",
          "route_id": "thailand-real-estate"
        },
        {
          "label": "טעויות בקניית נכס",
          "path": "/5-הטעויות-המובילות-שיש-להימנע-מהן-בעת/",
          "route_id": "thailand-property-buying-mistakes"
        }
      ],
      "continuations": [
        {
          "context": "מקמו כל בדיקה בנקודה הנכונה לאורך תהליך הקנייה, מהצעה ועד רישום.",
          "label": "שלבי קניית נכס בתאילנד",
          "target_route_id": "buy-property-thailand"
        },
        {
          "context": "העמיקו בכללי הבעלות לזרים ובמסמכים שחשוב לבדוק בדירה בבית משותף.",
          "label": "זכויות בעלות בבית משותף",
          "target_route_id": "foreign-condo-ownership-thailand"
        }
      ],
      "freshness_id": "rules-monthly",
      "kind": "spoke",
      "ownership": {
        "intent": "לזהות סיכונים ולבצע בדיקות לפני מקדמה, חוזה, העברת כסף ורישום נכס בתאילנד",
        "primary_keyword": "טעויות בקניית נכס בתאילנד",
        "synonyms": [
          "בדיקת נאותות לנכס בתאילנד",
          "סיכונים ברכישת נכס בתאילנד",
          "Thailand property due diligence"
        ]
      },
      "parent_link": {
        "context": "חזרו למפת הדרכים המלאה כדי לראות כיצד הבדיקות משתלבות בבחירת נכס, מימון וניהול.",
        "label": "לכל מדריכי הנדל״ן בתאילנד",
        "target_route_id": "thailand-real-estate"
      },
      "parent_route_id": "thailand-real-estate",
      "path": "/5-הטעויות-המובילות-שיש-להימנע-מהן-בעת/",
      "public": {
        "h1": "טעויות בקניית נכס בתאילנד: 5 בדיקות לפני חתימה",
        "meta_description": "טעויות בקניית נכס בתאילנד עלולות להתחיל עוד לפני החוזה. הנה הבדיקות החשובות לבעלות, מסמכים, תשלומים, מסירה ורישום הזכויות.",
        "seo_title": "טעויות בקניית נכס בתאילנד: מה לבדוק לפני חוזה",
        "summary": "רשימת בדיקות ממוקדת שמסדרת את נקודות הסיכון לפני שמתחייבים לעסקה או מעבירים כסף."
      },
      "route_id": "thailand-property-buying-mistakes",
      "seo_owner_id": "thailand-property-due-diligence-mistakes",
      "source_ids": [
        "consumer-protection-board",
        "royal-gazette",
        "thai-land-department"
      ],
      "wordpress": {
        "body_mode": "preserve",
        "identity_policy": "id_and_path_exact",
        "post_id": 69,
        "post_type": "post"
      }
    },
    "thailand-property-financing": {
      "breadcrumbs": [
        {
          "label": "ראשי",
          "path": "/",
          "route_id": null
        },
        {
          "label": "נדל״ן בתאילנד",
          "path": "/נדלן-בתאילנד/",
          "route_id": "thailand-real-estate"
        },
        {
          "label": "משכנתא ומימון",
          "path": "/אפשרויות-משכנתא-ומימון-נכסים-בתאילנד/",
          "route_id": "thailand-property-financing"
        }
      ],
      "continuations": [
        {
          "context": "חברו את בחירת המימון לשלבי הקנייה, הבדיקות, החוזה ורישום הזכויות.",
          "label": "תהליך קניית נכס בתאילנד",
          "target_route_id": "buy-property-thailand"
        },
        {
          "context": "העריכו את מחיר הנכס ואת ההוצאות הנלוות לפני קביעת סכום המימון.",
          "label": "מחירי נכסים ועלויות עסקה",
          "target_route_id": "thailand-property-prices"
        }
      ],
      "freshness_id": "market-monthly",
      "kind": "spoke",
      "ownership": {
        "intent": "להבין אפשרויות מימון, דרישות הון עצמי ועלויות אשראי לפני רכישת נכס בתאילנד",
        "primary_keyword": "משכנתא בתאילנד לזרים",
        "synonyms": [
          "מימון נכס בתאילנד לזרים",
          "הלוואה לקניית דירה בתאילנד",
          "property finance Thailand"
        ]
      },
      "parent_link": {
        "context": "חזרו למפת הדרכים המלאה כדי לחבר בין מימון, בחירת נכס, זכויות ועלויות עסקה.",
        "label": "לכל מדריכי הנדל״ן בתאילנד",
        "target_route_id": "thailand-real-estate"
      },
      "parent_route_id": "thailand-real-estate",
      "path": "/אפשרויות-משכנתא-ומימון-נכסים-בתאילנד/",
      "public": {
        "h1": "משכנתא בתאילנד לזרים: מימון לקניית נכס",
        "meta_description": "משכנתא בתאילנד לזרים: הכירו מסלולי מימון אפשריים, דרישות הון עצמי, מסמכים, עלויות אשראי וחלופות לתכנון רכישת נכס.",
        "seo_title": "משכנתא בתאילנד לזרים: מסלולי מימון ועלויות",
        "summary": "מפת אפשרויות למימון העסקה, עם השאלות שצריך לשאול על זכאות, בטוחות, מטבע, ריבית והחזר חודשי."
      },
      "route_id": "thailand-property-financing",
      "seo_owner_id": "thailand-property-financing",
      "source_ids": [
        "bank-of-thailand",
        "thai-land-department",
        "thai-revenue-department"
      ],
      "wordpress": {
        "body_mode": "preserve",
        "identity_policy": "id_and_path_exact",
        "post_id": 65,
        "post_type": "post"
      }
    },
    "thailand-property-management": {
      "breadcrumbs": [
        {
          "label": "ראשי",
          "path": "/",
          "route_id": null
        },
        {
          "label": "נדל״ן בתאילנד",
          "path": "/נדלן-בתאילנד/",
          "route_id": "thailand-real-estate"
        },
        {
          "label": "ניהול נכסים בתאילנד",
          "path": "/property-management/",
          "route_id": "thailand-property-management"
        }
      ],
      "continuations": [
        {
          "context": "הכירו את ציפיות השוכרים, תנאי החוזה והעלויות שמנהל הנכס צריך להביא בחשבון.",
          "label": "חוזי שכירות ודירות בבנגקוק",
          "target_route_id": "bangkok-apartment-rental"
        },
        {
          "context": "שלבו תחזוקה, ביקוש לשכירות ועלויות ניהול בבחירת הנכס ובתקציב העסקה.",
          "label": "תכנון הניהול כבר בשלב הקנייה",
          "target_route_id": "buy-property-thailand"
        }
      ],
      "freshness_id": "service-quarterly",
      "kind": "spoke",
      "ownership": {
        "intent": "לתכנן ניהול שוכרים, גבייה, תחזוקה, דיווח ובקרה על נכס שנמצא בתאילנד",
        "primary_keyword": "ניהול נכסים בתאילנד",
        "synonyms": [
          "ניהול דירה בתאילנד",
          "חברת ניהול נכסים בתאילנד",
          "property management Thailand"
        ]
      },
      "parent_link": {
        "context": "חזרו למפת הדרכים המלאה כדי לחבר את הניהול השוטף להחלטות הקנייה, השכירות והתקציב.",
        "label": "לכל מדריכי הנדל״ן בתאילנד",
        "target_route_id": "thailand-real-estate"
      },
      "parent_route_id": "thailand-real-estate",
      "path": "/property-management/",
      "public": {
        "h1": "ניהול נכסים בתאילנד: תחזוקה, שוכרים ועלויות",
        "meta_description": "ניהול נכסים בתאילנד כולל איתור שוכרים, חוזים, גבייה, תחזוקה, ביקורות ודיווח. כך בונים שגרת ניהול ברורה גם כשנמצאים מחוץ למדינה.",
        "seo_title": "ניהול נכסים בתאילנד: שוכרים, תחזוקה וגבייה",
        "summary": "מסגרת עבודה לבחירת שירותי ניהול, חלוקת אחריות, מעקב אחר הכנסות וטיפול בתקלות לאורך חיי הנכס."
      },
      "route_id": "thailand-property-management",
      "seo_owner_id": "property-management-thailand",
      "source_ids": [
        "consumer-protection-board",
        "thai-land-department",
        "thai-revenue-department"
      ],
      "wordpress": {
        "body_mode": "preserve",
        "identity_policy": "id_and_path_exact",
        "post_id": 609,
        "post_type": "post"
      }
    },
    "thailand-property-prices": {
      "breadcrumbs": [
        {
          "label": "ראשי",
          "path": "/",
          "route_id": null
        },
        {
          "label": "נדל״ן בתאילנד",
          "path": "/נדלן-בתאילנד/",
          "route_id": "thailand-real-estate"
        },
        {
          "label": "מחירי נדל״ן בתאילנד",
          "path": "/price/",
          "route_id": "thailand-property-prices"
        }
      ],
      "continuations": [
        {
          "context": "תרגמו את מחיר היעד לסכום הון עצמי, עלות אשראי והחזר חודשי אפשרי.",
          "label": "מימון והון עצמי לרכישה",
          "target_route_id": "thailand-property-financing"
        },
        {
          "context": "חברו בין התקציב לשלבי הבדיקה, החוזה, התשלומים, הרישום והמסירה.",
          "label": "תהליך קנייה לפי תקציב",
          "target_route_id": "buy-property-thailand"
        }
      ],
      "freshness_id": "market-monthly",
      "kind": "spoke",
      "ownership": {
        "intent": "להעריך טווחי מחיר ועלויות עסקה והחזקה לפני קביעת תקציב לרכישה בתאילנד",
        "primary_keyword": "מחירי נדל״ן בתאילנד",
        "synonyms": [
          "מחירי דירות בתאילנד",
          "כמה עולה נכס בתאילנד",
          "Thailand property prices"
        ]
      },
      "parent_link": {
        "context": "חזרו למפת הדרכים המלאה כדי להפוך את מסגרת התקציב למסלול קנייה, מימון או שכירות.",
        "label": "לכל מדריכי הנדל״ן בתאילנד",
        "target_route_id": "thailand-real-estate"
      },
      "parent_route_id": "thailand-real-estate",
      "path": "/price/",
      "public": {
        "h1": "מחירי נדל״ן בתאילנד: דירות, בתים ועלויות עסקה",
        "meta_description": "מחירי נדל״ן בתאילנד לפי סוג נכס והקשר מקומי, יחד עם מסים, אגרות, מימון והוצאות החזקה שכדאי להוסיף לתקציב הרכישה.",
        "seo_title": "מחירי נדל״ן בתאילנד: מדריך לתקציב ועלויות",
        "summary": "דרך מסודרת לבנות תקציב מלא, להפריד בין מחיר הנכס להוצאות העסקה ולהבין מה משפיע על הפערים בין אזורים."
      },
      "route_id": "thailand-property-prices",
      "seo_owner_id": "thailand-property-prices",
      "source_ids": [
        "bank-of-thailand",
        "thai-land-department",
        "thai-revenue-department"
      ],
      "wordpress": {
        "body_mode": "preserve",
        "identity_policy": "id_and_path_exact",
        "post_id": 810,
        "post_type": "post"
      }
    },
    "thailand-real-estate": {
      "breadcrumbs": [
        {
          "label": "ראשי",
          "path": "/",
          "route_id": null
        },
        {
          "label": "נדל״ן בתאילנד",
          "path": "/נדלן-בתאילנד/",
          "route_id": "thailand-real-estate"
        }
      ],
      "continuations": [
        {
          "context": "בדקו אילו מסלולי מימון עשויים להתאים וכיצד לחשב הון עצמי ועלויות אשראי.",
          "label": "משכנתא ומימון נכס בתאילנד",
          "target_route_id": "thailand-property-financing"
        },
        {
          "context": "עברו על הבדיקות שכדאי להשלים לפני מקדמה, חתימה והעברת זכויות.",
          "label": "טעויות נפוצות בקניית נכס",
          "target_route_id": "thailand-property-buying-mistakes"
        },
        {
          "context": "השוו שכונות, תנאי חוזה והוצאות שוטפות לפני בחירת דירה בבנגקוק.",
          "label": "השכרת דירה בבנגקוק",
          "target_route_id": "bangkok-apartment-rental"
        },
        {
          "context": "הכירו את שלבי הקנייה מהגדרת התקציב ועד רישום הזכויות וקבלת הנכס.",
          "label": "קניית נכס בתאילנד",
          "target_route_id": "buy-property-thailand"
        },
        {
          "context": "הבינו כיצד פועלת בעלות בבית משותף ואילו מסמכים חשוב לבדוק.",
          "label": "בעלות זרים על דירה בתאילנד",
          "target_route_id": "foreign-condo-ownership-thailand"
        },
        {
          "context": "תכננו טיפול בשוכרים, תחזוקה, גבייה ודיווח כאשר הנכס כבר בבעלותכם.",
          "label": "ניהול נכסים בתאילנד",
          "target_route_id": "thailand-property-management"
        },
        {
          "context": "בנו מסגרת תקציב לפי סוג נכס והוסיפו את עלויות העסקה וההחזקה.",
          "label": "מחירי נדל״ן בתאילנד",
          "target_route_id": "thailand-property-prices"
        }
      ],
      "freshness_id": "market-monthly",
      "kind": "hub",
      "ownership": {
        "intent": "לקבל תמונה מלאה ולבחור מסלול מתאים לקנייה, שכירות, מימון או ניהול נכס בתאילנד",
        "primary_keyword": "נדל״ן בתאילנד",
        "synonyms": [
          "נכסים בתאילנד",
          "שוק הנדל״ן בתאילנד",
          "Thailand real estate"
        ]
      },
      "parent_link": null,
      "parent_route_id": null,
      "path": "/נדלן-בתאילנד/",
      "public": {
        "h1": "נדל״ן בתאילנד: קנייה, שכירות, מחירים וניהול נכסים",
        "meta_description": "נדל״ן בתאילנד במקום אחד: תהליך קנייה, כללי בעלות לזרים, מחירים, מימון, שכירות בבנגקוק וניהול נכסים, עם מסלול ברור לכל צורך.",
        "seo_title": "נדל״ן בתאילנד: מדריך לקנייה, שכירות והשקעה",
        "summary": "מתחילים מהמטרה שלכם ומתקדמים אל המדריך המדויק, מהיכרות עם השוק ועד חוזה, מימון, רישום וניהול שוטף."
      },
      "route_id": "thailand-real-estate",
      "seo_owner_id": "thailand-real-estate",
      "source_ids": [
        "bank-of-thailand",
        "royal-gazette",
        "thai-land-department",
        "thai-revenue-department"
      ],
      "wordpress": {
        "body_mode": "preserve",
        "identity_policy": "id_and_path_exact",
        "post_id": 841,
        "post_type": "page"
      }
    }
  },
  "schema_sha256": "47ff52e08576c17e9849889120a77238ba833e8fe5abf715088f76d52a70fa60",
  "schema_version": 1,
  "seo_registry_sha256": "d362fe1af89fe72c850f9db68736ad8b4587c2d4bc15ffe7f9324b827e032936",
  "site": {
    "direction": "rtl",
    "locale": "he-IL",
    "origin": "https://thai-land.co.il"
  },
  "source_sha256": "32ed049f1f8725a09c14a3fada20af03ac612db73d782e6771e41ce8fba4d96b",
  "sources_by_id": {
    "bangkok-metropolitan-administration": {
      "label": "עיריית בנגקוק",
      "scope_label": "שירותים עירוניים ומידע מקומי בבנגקוק",
      "source_id": "bangkok-metropolitan-administration",
      "url": "https://main.bangkok.go.th/"
    },
    "bank-of-thailand": {
      "label": "הבנק המרכזי של תאילנד",
      "scope_label": "ריבית, אשראי, מטבע ונתוני שוק",
      "source_id": "bank-of-thailand",
      "url": "https://www.bot.or.th/en/home.html"
    },
    "consumer-protection-board": {
      "label": "המשרד להגנת הצרכן בתאילנד",
      "scope_label": "זכויות צרכניות, חוזים ופניות ציבור",
      "source_id": "consumer-protection-board",
      "url": "https://www.ocpb.go.th/"
    },
    "royal-gazette": {
      "label": "הרשומות הרשמיות של תאילנד",
      "scope_label": "חוקים, תקנות והודעות רשמיות",
      "source_id": "royal-gazette",
      "url": "https://ratchakitcha.soc.go.th/"
    },
    "thai-land-department": {
      "label": "מחלקת הקרקעות של תאילנד",
      "scope_label": "רישום מקרקעין, בתים משותפים והעברת זכויות",
      "source_id": "thai-land-department",
      "url": "https://www.dol.go.th/"
    },
    "thai-revenue-department": {
      "label": "רשות המסים של תאילנד",
      "scope_label": "מסים, אגרות וחובות דיווח",
      "source_id": "thai-revenue-department",
      "url": "https://www.rd.go.th/english/"
    }
  }
}
THAILAND_PLATFORM_CONTENT_JSON,
	true,
	512,
	JSON_THROW_ON_ERROR
);
