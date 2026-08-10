#!/usr/bin/env python3
"""Build the reviewed SEO ownership registry from immutable URL inventories."""

from __future__ import annotations

import argparse
import csv
import hashlib
import json
import unicodedata
from pathlib import Path
from typing import Any
from urllib.parse import unquote, urlsplit


ROOT = Path(__file__).resolve().parents[1]
OUTPUT = ROOT / "data" / "seo" / "ownership-registry.json"
SITEMAP_INVENTORY = (
    ROOT
    / "data"
    / "seo"
    / "inventory"
    / "current-public-url-metadata.2026-08-08.csv"
)
CATEGORY_INVENTORY = (
    ROOT
    / "data"
    / "seo"
    / "inventory"
    / "indexable-category-surfaces.2026-08-08.csv"
)
MANAGED_LIVE_EVIDENCE_RELATIVE = (
    "data/seo/evidence/managed-live-routes.0.3.5.json"
)
MANAGED_LIVE_EVIDENCE = ROOT / MANAGED_LIVE_EVIDENCE_RELATIVE
GUIDES_PRIVATE_CANARY_EVIDENCE_RELATIVE = (
    "data/seo/evidence/priority-guides-private-canary.0.4.0.json"
)
GUIDES_PRIVATE_CANARY_EVIDENCE = ROOT / GUIDES_PRIVATE_CANARY_EVIDENCE_RELATIVE

FROZEN_ROUTE_INDEXING_OVERRIDES = {
    "thailand-entry-april-2022": "noindex",
}

EXPECTED_MANAGED_ROUTES = [
    {
        "route_id": "thailand-real-estate",
        "seo_owner_id": "thailand-real-estate",
        "canonical_url": "/נדלן-בתאילנד/",
        "post_id": 841,
        "post_type": "page",
    },
    {
        "route_id": "thailand-property-financing",
        "seo_owner_id": "thailand-property-financing",
        "canonical_url": "/אפשרויות-משכנתא-ומימון-נכסים-בתאילנד/",
        "post_id": 65,
        "post_type": "post",
    },
    {
        "route_id": "thailand-property-buying-mistakes",
        "seo_owner_id": "thailand-property-due-diligence-mistakes",
        "canonical_url": "/5-הטעויות-המובילות-שיש-להימנע-מהן-בעת/",
        "post_id": 69,
        "post_type": "post",
    },
    {
        "route_id": "bangkok-apartment-rental",
        "seo_owner_id": "bangkok-apartment-rental-guide",
        "canonical_url": "/מדריך-להשכרת-דירה-בבנגקוק/",
        "post_id": 118,
        "post_type": "post",
    },
    {
        "route_id": "buy-property-thailand",
        "seo_owner_id": "buy-property-thailand",
        "canonical_url": "/קניית-נכס-בתאילנד/",
        "post_id": 336,
        "post_type": "post",
    },
    {
        "route_id": "foreign-condo-ownership-thailand",
        "seo_owner_id": "foreign-condo-ownership-thailand",
        "canonical_url": "/זכויות-בית-משותף-נכס-בתאילנד/",
        "post_id": 474,
        "post_type": "post",
    },
    {
        "route_id": "thailand-property-management",
        "seo_owner_id": "property-management-thailand",
        "canonical_url": "/property-management/",
        "post_id": 609,
        "post_type": "post",
    },
    {
        "route_id": "thailand-property-prices",
        "seo_owner_id": "thailand-property-prices",
        "canonical_url": "/price/",
        "post_id": 810,
        "post_type": "post",
    },
]

EXPECTED_GUIDES_CANARY_ROUTES = [
    {
        "route_id": "thailand-cannabis-law",
        "seo_owner_id": "thailand-cannabis-law",
        "expected_canonical_path": "/תאילנד-וחוק-אי-הפללת-קנאביס-פנאי/",
        "post_id": 102,
        "post_type": "post",
        "wordpress_status_at_observation": "publish",
        "page_schema_type": "Article",
    },
    {
        "route_id": "thailand-entry-april-2022",
        "seo_owner_id": "thailand-entry-april-2022",
        "expected_canonical_path": "/החל-מאפריל-2022-מטיילים-יורשו-להיכנס-לתאי/",
        "post_id": 62,
        "post_type": "post",
        "wordpress_status_at_observation": "publish",
        "page_schema_type": "Article",
    },
    {
        "route_id": "thailand-entry-requirements",
        "seo_owner_id": "thailand-entry-requirements",
        "expected_canonical_path": "/hello-world/",
        "post_id": 1,
        "post_type": "post",
        "wordpress_status_at_observation": "publish",
        "page_schema_type": "Article",
    },
    {
        "route_id": "thailand-law-and-tax",
        "seo_owner_id": "thailand-law-and-tax",
        "expected_canonical_path": "/חוקים-ומסים-בתאילנד/",
        "post_id": 848,
        "post_type": "page",
        "wordpress_status_at_observation": "draft",
        "page_schema_type": "CollectionPage",
    },
    {
        "route_id": "thailand-permanent-residence",
        "seo_owner_id": "thailand-permanent-residence",
        "expected_canonical_path": "/permanent-residence-thailand/",
        "post_id": 132,
        "post_type": "post",
        "wordpress_status_at_observation": "publish",
        "page_schema_type": "Article",
    },
    {
        "route_id": "thailand-tourist-visa",
        "seo_owner_id": "thailand-tourist-visa",
        "expected_canonical_path": "/ויזת-תיירים-תאילנד/",
        "post_id": 243,
        "post_type": "post",
        "wordpress_status_at_observation": "publish",
        "page_schema_type": "Article",
    },
    {
        "route_id": "thailand-visas",
        "seo_owner_id": "thailand-visas",
        "expected_canonical_path": "/ויזות-לתאילנד/",
        "post_id": 846,
        "post_type": "page",
        "wordpress_status_at_observation": "draft",
        "page_schema_type": "CollectionPage",
    },
]

GUIDES_PARENT_OWNER_IDS = ("thailand-visas", "thailand-law-and-tax")
GUIDES_CHILD_OWNER_IDS = (
    "thailand-entry-requirements",
    "thailand-entry-april-2022",
    "thailand-cannabis-law",
    "thailand-tourist-visa",
    "thailand-permanent-residence",
)

SNAPSHOTS = (
    {
        "snapshot_id": "yoast-sitemaps-2026-08-08",
        "path": "data/seo/inventory/current-public-url-metadata.2026-08-08.csv",
        "captured_at": "2026-08-08T00:31:01+03:00",
        "origin": "https://thai-land.co.il",
        "digest_algorithm": "sha256-lf-v1",
        "content_sha256": "6e34e459d0772ecc227d848bc1dfe42260c2df6dcaefe65457bb6dcb8698816c",
        "row_count": 40,
        "protected_url_count": 40,
        "url_column": "Url",
        "decoded_path_column": "DecodedPath",
        "scope": "כל הכתובות שנשלחו בשלוש מפות האתר של Yoast",
    },
    {
        "snapshot_id": "indexable-category-surfaces-2026-08-08",
        "path": "data/seo/inventory/indexable-category-surfaces.2026-08-08.csv",
        "captured_at": "2026-08-08T03:20:00+03:00",
        "origin": "https://thai-land.co.il",
        "digest_algorithm": "sha256-lf-v1",
        "content_sha256": "7844b78efc75533803496799099176cd7b8a31f57c915d5746d3ade8ed37cc65",
        "row_count": 3,
        "protected_url_count": 3,
        "url_column": "Url",
        "decoded_path_column": "DecodedPath",
        "scope": "קטגוריות ציבוריות אינדקסביליות שאינן מופיעות במפת האתר",
    },
)


def public(
    owner_id: str,
    intent_id: str,
    name: str,
    primary_keyword: str,
    synonyms: list[str],
    primary_intent: str,
    parent_owner_id: str,
    entity_type: str,
    intent_class: str,
    freshness_class: str,
    migration_action: str = "keep_rewrite",
    subject_entity_ids: list[str] | None = None,
    unique_contribution: str | None = None,
) -> dict[str, Any]:
    """Create one reviewed public owner declaration."""
    return {
        "owner_id": owner_id,
        "intent_id": intent_id,
        "name": name,
        "primary_keyword": primary_keyword,
        "intent_synonyms": synonyms,
        "primary_intent": primary_intent,
        "parent_owner_id": parent_owner_id,
        "entity_type": entity_type,
        "intent_class": intent_class,
        "freshness_class": freshness_class,
        "migration_action": migration_action,
        "subject_entity_ids": subject_entity_ids or ["geo:th:country"],
        "unique_contribution": unique_contribution
        or f"מענה ייחודי למשימת המשתמש: {primary_intent}",
    }


PUBLIC: dict[str, dict[str, Any]] = {
    "/מלונות-חדשים-בקו-סמוי-2022/": public(
        "koh-samui-new-hotels-2022",
        "he|hotel|koh-samui|openings-2022|historical",
        "מלונות חדשים בקוסמוי בשנת 2022",
        "מלונות חדשים בקוסמוי 2022",
        ["פתיחות מלונות בקוסמוי 2022", "new hotels Koh Samui 2022"],
        "לבדוק אילו מלונות נפתחו בקוסמוי בשנת 2022",
        "koh-samui",
        "historical_update",
        "historical",
        "static_annual",
        "preserve_historical",
        ["geo:th:province:84"],
    ),
    "/החל-מאפריל-2022-מטיילים-יורשו-להיכנס-לתאי/": public(
        "thailand-entry-april-2022",
        "he|entry|thailand|april-2022|historical",
        "כללי הכניסה לתאילנד באפריל 2022",
        "כניסה לתאילנד באפריל 2022",
        [
            "כללי קורונה תאילנד אפריל 2022",
            "ביטול PCR לפני טיסה לתאילנד 2022",
        ],
        "להבין את השינוי ההיסטורי שחל בכללי הכניסה לתאילנד באפריל 2022",
        "thailand-visas",
        "historical_update",
        "historical",
        "static_annual",
        "preserve_historical",
    ),
    "/אפשרויות-משכנתא-ומימון-נכסים-בתאילנד/": public(
        "thailand-property-financing",
        "he|real-estate|thailand|finance|learn",
        "משכנתא ומימון נכס בתאילנד לזרים",
        "משכנתא בתאילנד לזרים",
        ["מימון נכס בתאילנד לזרים", "mortgage Thailand foreigners"],
        "להשוות דרכי מימון, זכאות וחלופות לרכישת נכס בתאילנד",
        "thailand-real-estate",
        "property_guide",
        "evaluation",
        "price_sensitive_monthly",
        unique_contribution="מלווים, תנאי זכאות, הון עצמי, חלופות ועלות מימון עם תאריך בדיקה",
    ),
    "/5-הטעויות-המובילות-שיש-להימנע-מהן-בעת/": public(
        "thailand-property-due-diligence-mistakes",
        "he|real-estate|thailand|due-diligence|learn",
        "בדיקת נאותות וטעויות בקניית נכס בתאילנד",
        "טעויות בקניית נכס בתאילנד",
        ["בדיקת נאותות לנכס בתאילנד", "property due diligence Thailand"],
        "להימנע מסיכונים לפני חתימה ורכישת נכס בתאילנד",
        "thailand-real-estate",
        "property_guide",
        "risk_reduction",
        "regulated_monthly",
        unique_contribution="רשימת בדיקות מעשית לפני מקדמה, חוזה, העברה ורישום",
    ),
    "/5-המיתוסים-הגדולים-ביותר-על-הזמנת-כרטיס/": public(
        "flight-booking-myths",
        "he|flights|thailand|booking-myths|learn",
        "מיתוסים וטעויות בהזמנת טיסות לתאילנד",
        "מיתוסים על הזמנת טיסות",
        ["טעויות בהזמנת טיסות לתאילנד", "flight booking myths Thailand"],
        "לבדוק אילו עצות נפוצות להזמנת טיסות נכונות",
        "thailand-flights",
        "article_guide",
        "learning",
        "editorial_quarterly",
        "conditional_merge",
    ),
    "/14-מקומות-מובילים-לביקור-בתאילנד/": public(
        "thailand-places-to-visit",
        "he|destinations|thailand|places-to-visit|evaluate",
        "מקומות מומלצים לביקור בתאילנד",
        "מקומות לבקר בתאילנד",
        ["יעדים מומלצים בתאילנד", "best places in Thailand"],
        "לבחור יעדים מתאימים לחופשה בתאילנד",
        "thailand-destinations",
        "destination_guide",
        "evaluation",
        "editorial_quarterly",
        "conditional_merge",
    ),
    "/מלונות-היוקרה-הטובים-ביותר-בקוסמוי/": public(
        "koh-samui-luxury-hotels",
        "he|hotel|koh-samui|luxury|evaluate",
        "מלונות יוקרה בקוסמוי",
        "מלונות יוקרה בקוסמוי",
        ["מלונות 5 כוכבים בקוסמוי", "luxury hotels Koh Samui"],
        "להשוות מלונות יוקרה בקוסמוי",
        "koh-samui",
        "booking_guide",
        "comparison",
        "price_sensitive_monthly",
        subject_entity_ids=["geo:th:province:84"],
    ),
    "/לעבוד-מבתי-קפה-10-טיפים-לעשות-זאת-ביעילו/": public(
        "remote-work-cafes-thailand",
        "he|remote-work|thailand|cafes|learn",
        "עבודה מבתי קפה בתאילנד",
        "עבודה מבתי קפה בתאילנד",
        ["בתי קפה לנוודים דיגיטליים בתאילנד", "remote work cafes Thailand"],
        "לעבוד ביעילות ובאחריות מבתי קפה בתאילנד",
        "living-in-thailand",
        "article_guide",
        "learning",
        "editorial_quarterly",
    ),
    "/permanent-residence-thailand/": public(
        "thailand-permanent-residence",
        "he|immigration|thailand|permanent-residence|learn",
        "תושבות קבע בתאילנד",
        "תושבות קבע בתאילנד",
        ["מעמד תושב קבע בתאילנד", "Permanent Residence Thailand"],
        "להבין זכאות, מסלולים, מסמכים, מועדים ועלויות לפני בקשת תושבות קבע בתאילנד",
        "thailand-visas",
        "article_guide",
        "learning",
        "regulated_monthly",
    ),
    "/תאילנד-קישורים-שימושיים/": public(
        "useful-thailand-links",
        "he|planning|thailand|useful-links|navigate",
        "קישורים וכלים שימושיים לתאילנד",
        "קישורים שימושיים לתאילנד",
        ["אתרים שימושיים לתאילנד", "Thailand planning resources"],
        "למצוא כלים חיצוניים שימושיים לתכנון שהייה בתאילנד",
        "thailand-tourism",
        "resource_directory",
        "navigation",
        "editorial_quarterly",
    ),
    "/בנגקוק-תאילנד/": public(
        "bangkok",
        "he|place|bangkok|orient",
        "בנגקוק",
        "בנגקוק",
        ["Bangkok בעברית", "กรุงเทพมหานคร למטייל הישראלי"],
        "להכיר את בנגקוק לפי שכונות, תחבורה ומטרות שהייה",
        "thailand-destinations",
        "destination_hub",
        "orientation",
        "dynamic_weekly",
        subject_entity_ids=["geo:th:province:10"],
    ),
    "/אודות/": public(
        "about",
        "he|site|about|trust",
        "אודות Thai-Land.co.il",
        "אודות Thai-Land.co.il",
        ["מי מפעיל את Thai-Land.co.il", "על האתר Thai-Land"],
        "להבין מי מפעיל את האתר, כיצד התוכן נוצר ואיך פונים לתיקון",
        "home",
        "organization_page",
        "trust",
        "static_annual",
    ),
    "/מדריך-להשכרת-דירה-בבנגקוק/": public(
        "bangkok-apartment-rental-guide",
        "he|real-estate|bangkok|rent-long-term|learn",
        "השכרת דירה בבנגקוק",
        "השכרת דירה בבנגקוק",
        ["דירות להשכרה בבנגקוק לטווח ארוך", "Bangkok apartment rent"],
        "לבחור שכונה ולהבין חוזה ועלויות שכירות בבנגקוק",
        "thailand-real-estate",
        "property_guide",
        "planning",
        "price_sensitive_monthly",
        subject_entity_ids=["geo:th:province:10"],
        unique_contribution="טווחי שכירות לפי שכונה, תנאי חוזה, פיקדון, חשבונות ובדיקת דירה",
    ),
    "/כלכלת-תאילנד/": public(
        "thailand-economy",
        "he|business|thailand|economy|learn",
        "כלכלת תאילנד",
        "כלכלת תאילנד",
        ["המשק התאילנדי", "Thailand economy"],
        "להבין מגמות, ענפים ונתוני מאקרו בכלכלת תאילנד",
        "business-in-thailand",
        "article_guide",
        "learning",
        "dynamic_weekly",
    ),
    "/תיירות-בתאילנד/": public(
        "thailand-tourism",
        "he|travel|thailand|plan",
        "תיירות בתאילנד",
        "תיירות בתאילנד",
        ["טיול בתאילנד", "Thailand travel guide"],
        "לתכנן טיול שלם ולבחור מסלול המשך בתאילנד",
        "home",
        "national_hub",
        "planning",
        "editorial_quarterly",
    ),
    "/מהו-הזמן-הטוב-ביותר-לחופשה-בתאילנד/": public(
        "best-time-for-thailand",
        "he|travel|thailand|best-time|plan",
        "מתי לטוס לתאילנד",
        "מתי לטוס לתאילנד",
        ["העונה בתאילנד", "best time Thailand"],
        "לבחור חודש ואזור לפי מזג אוויר, ים ועומס",
        "thailand-tourism",
        "seasonal_guide",
        "planning",
        "seasonal_monthly",
    ),
    "/תאילנד-המדריך-המלא-הכל-על-תאילנד-והמלצ/": public(
        "thailand-first-trip-guide",
        "he|travel|thailand|first-trip|plan",
        "מדריך לתאילנד למטייל בפעם הראשונה",
        "מדריך לתאילנד למטייל",
        ["תאילנד למתחילים", "first trip Thailand"],
        "להתכונן לנסיעה הראשונה לתאילנד",
        "thailand-tourism",
        "article_guide",
        "planning",
        "editorial_quarterly",
        "conditional_merge",
    ),
    "/ויזת-תיירים-תאילנד/": public(
        "thailand-tourist-visa",
        "he|immigration|thailand|tourist-visa|learn",
        "ויזת תייר לתאילנד",
        "ויזת תייר לתאילנד",
        ["אשרת תייר לתאילנד", "ויזה לתאילנד לישראלים"],
        "להחליט אם נדרשת אשרת תייר ולהכין בקשת e-Visa מלאה מחוץ לתאילנד",
        "thailand-visas",
        "article_guide",
        "learning",
        "regulated_monthly",
    ),
    "/הנפקת-ויזה-לתאילנד-סוגי-ויזות-ואשרות-ת/": public(
        "thailand-visa-service",
        "he|service|thailand|visa-assistance|transact",
        "שירות וסיוע בהנפקת ויזה לתאילנד",
        "שירות ויזה לתאילנד",
        ["סיוע בהנפקת ויזה לתאילנד", "visa service Thailand"],
        "למצוא סיוע מקצועי בהגשת בקשת ויזה לתאילנד",
        "services-in-thailand",
        "service_directory",
        "transaction",
        "regulated_monthly",
    ),
    "/אישור-עבודה-בתאילנד/": public(
        "thailand-work-permit",
        "he|employment|thailand|work-permit|learn",
        "אישור עבודה בתאילנד",
        "אישור עבודה בתאילנד",
        ["היתר עבודה בתאילנד", "work permit Thailand"],
        "להבין מי צריך אישור עבודה וכיצד מגישים",
        "business-in-thailand",
        "article_guide",
        "learning",
        "regulated_monthly",
    ),
    "/פוקט-או-קו-סמוי/": public(
        "phuket-or-samui",
        "he|compare|phuket|koh-samui|travel",
        "פוקט או קוסמוי",
        "פוקט או קוסמוי",
        ["קוסמוי או פוקט", "Phuket vs Koh Samui"],
        "להחליט בין פוקט לקוסמוי לפי אופי החופשה",
        "thailand-tourism",
        "comparison_guide",
        "comparison",
        "editorial_quarterly",
        subject_entity_ids=["geo:th:province:83", "geo:th:province:84"],
    ),
    "/מעבר-לתאילנד-רילוקיישן-לתאילנד-מדריך/": public(
        "relocation-to-thailand",
        "he|living|thailand|relocation|plan",
        "רילוקיישן ומעבר לתאילנד",
        "רילוקיישן לתאילנד",
        ["מעבר לתאילנד", "relocation Thailand"],
        "לתכנן מעבר, נחיתה והתארגנות לחיים בתאילנד",
        "living-in-thailand",
        "article_guide",
        "planning",
        "regulated_monthly",
    ),
    "/עסקים-בתאילנד-סקירה-כללית/": public(
        "business-in-thailand",
        "he|business|thailand|start-operate|learn",
        "עסקים בתאילנד",
        "עסקים בתאילנד",
        ["פתיחת עסק בתאילנד", "business Thailand"],
        "להבין הקמה, רישוי ותפעול של עסק בתאילנד",
        "home",
        "national_hub",
        "learning",
        "regulated_monthly",
    ),
    "/hello-world/": public(
        "thailand-entry-requirements",
        "he|entry|thailand|current-requirements|stay-current",
        "דרישות כניסה לתאילנד",
        "כניסה לתאילנד לישראלים",
        ["דרישות כניסה לתאילנד", "האם תאילנד פתוחה לתיירים"],
        "לבדוק מה ישראלים צריכים להכין עכשיו לפני טיסה וביקורת גבולות בתאילנד",
        "thailand-visas",
        "article_guide",
        "current_status",
        "regulated_monthly",
        "conditional_merge",
    ),
    "/5-טריקים-לטיסות-סופר-זולות-לתאילנד/": public(
        "cheap-flight-tips-legacy",
        "he|flights|thailand|cheap-flight-tips|learn",
        "טריקים להוזלת טיסות לתאילנד",
        "טריקים לטיסות זולות לתאילנד",
        ["טיפים לטיסות זולות לתאילנד", "cheap Thailand flight tips"],
        "ללמוד פעולות מעשיות שיכולות להוזיל טיסה לתאילנד",
        "thailand-flights",
        "article_guide",
        "learning",
        "editorial_quarterly",
        "conditional_merge",
        unique_contribution="פעולות ממוקדות להוזלת כרטיס, בנפרד ממדריך השוואת הטיסות הראשי",
    ),
    "/איך-אומרים-בתאילנדית/": public(
        "useful-thai-language",
        "he|language|thai|daily-phrases|learn",
        "תאילנדית שימושית",
        "איך אומרים בתאילנדית",
        ["תאילנדית למטייל", "Thai phrases"],
        "ללמוד ביטויים שימושיים בתאילנדית לפי מצב",
        "living-in-thailand",
        "language_guide",
        "learning",
        "editorial_quarterly",
    ),
    "/זכויות-בית-משותף-נכס-בתאילנד/": public(
        "foreign-condo-ownership-thailand",
        "he|real-estate|thailand|condo-ownership-law|learn",
        "בעלות זרים בקונדומיניום בתאילנד",
        "בעלות זרים בדירה בתאילנד",
        ["קונדומיניום לזרים בתאילנד", "foreign condo ownership Thailand"],
        "להבין זכויות ומגבלות בעלות זרים בקונדומיניום",
        "thailand-real-estate",
        "property_guide",
        "learning",
        "regulated_monthly",
        unique_contribution="מגבלת הבעלות הזרה, מסמכי העברה, רישום וזכויות בבית משותף",
    ),
    "/המדריך-האולטימטיבי-למציאת-טיסות-זולו/": public(
        "thailand-flights",
        "he|flights|thailand|search-compare|transact",
        "טיסות זולות לתאילנד",
        "טיסות זולות לתאילנד",
        ["השוואת טיסות לתאילנד", "cheap flights Thailand"],
        "להשוות מחיר, מסלול ותנאי כרטיס לטיסה לתאילנד",
        "thailand-tourism",
        "booking_guide",
        "transaction",
        "dynamic_weekly",
    ),
    "/תאילנד-וחוק-אי-הפללת-קנאביס-פנאי/": public(
        "thailand-cannabis-law",
        "he|law|thailand|cannabis|stay-current",
        "חוקי קנאביס בתאילנד",
        "קנאביס בתאילנד",
        [
            "חוקי קנאביס בתאילנד",
            "האם קנאביס חוקי בתאילנד",
            "קנאביס בתאילנד לתיירים",
        ],
        "להבין מה מותר ומה אסור לתיירים לפי כללי הקנאביס העדכניים בתאילנד",
        "thailand-law-and-tax",
        "article_guide",
        "current_status",
        "regulated_monthly",
        "conditional_merge",
    ),
    "/ביטוח-נסיעות-לחול-ביטוח-רילוקיישן-בי/": public(
        "thailand-insurance-comparison",
        "he|insurance|thailand|choose-coverage|compare",
        "ביטוח נסיעות, בריאות ורילוקיישן לתאילנד",
        "ביטוח לתאילנד",
        ["ביטוח נסיעות לתאילנד", "Thailand travel insurance comparison"],
        "לבחור סוג כיסוי מתאים למבקר או לתושב בתאילנד",
        "health-in-thailand",
        "comparison_guide",
        "comparison",
        "price_sensitive_monthly",
        "conditional_merge",
    ),
    "/קניית-נכס-בתאילנד/": public(
        "buy-property-thailand",
        "he|real-estate|thailand|buy-process|learn",
        "קניית נכס בתאילנד",
        "קניית נכס בתאילנד",
        ["רכישת דירה בתאילנד", "buying property Thailand"],
        "להבין את תהליך הרכישה, הבעלות והעלויות",
        "thailand-real-estate",
        "property_guide",
        "learning",
        "regulated_monthly",
        unique_contribution="תהליך רכישה מלא מהגדרת צורך ועד רישום וקבלת הנכס",
    ),
    "/property-management/": public(
        "property-management-thailand",
        "he|real-estate|thailand|property-management|evaluate",
        "ניהול נכסים בתאילנד",
        "ניהול נכסים בתאילנד",
        ["חברת ניהול נכסים בתאילנד", "property management Thailand"],
        "להבין עלויות ולבחור שירות לניהול נכס בתאילנד",
        "thailand-real-estate",
        "property_guide",
        "evaluation",
        "price_sensitive_monthly",
        unique_contribution="תפעול לאחר הקנייה, תחזוקה, שוכרים, דיווח ועלויות ניהול",
    ),
    "/המחירים-הזולים-ביותר-תאילנד-2025/": public(
        "thailand-family-holiday-costs",
        "he|travel|thailand|family-budget|plan",
        "עלות חופשה משפחתית בתאילנד",
        "עלות חופשה בתאילנד למשפחה",
        ["תקציב חופשה משפחתית בתאילנד", "Thailand family holiday cost"],
        "להעריך עלות חופשה משפחתית בתאילנד",
        "thailand-tourism",
        "article_guide",
        "planning",
        "price_sensitive_monthly",
        "conditional_merge",
    ),
    "/מחשבון-תכנון-חופשה-בתאילנד/": public(
        "thailand-trip-budget-calculator",
        "he|tool|thailand|trip-budget|calculate",
        "מחשבון תקציב חופשה בתאילנד",
        "מחשבון תקציב חופשה בתאילנד",
        ["כמה עולה חופשה בתאילנד מחשבון", "Thailand trip budget calculator"],
        "לחשב תקציב לפי אנשים, ימים וסגנון חופשה",
        "thailand-tourism",
        "tool",
        "calculation",
        "price_sensitive_monthly",
    ),
    "/price/": public(
        "thailand-property-prices",
        "he|real-estate|thailand|market-prices|evaluate",
        "מחירי נדל״ן בתאילנד",
        "מחירי נדל״ן בתאילנד",
        ["מחירי דירות בתאילנד", "property prices Thailand"],
        "להשוות מחירי נכסים לפי אזור וסוג נכס",
        "thailand-real-estate",
        "property_guide",
        "evaluation",
        "price_sensitive_monthly",
        unique_contribution="טווחי מחיר, מחיר למטר, מתודולוגיה, מקור ותאריך לכל אזור",
    ),
    "/lawyer-thailand/": public(
        "thai-lawyer-hebrew-service",
        "he|services|thailand|lawyer-hebrew|find-local",
        "עורך דין דובר עברית בתאילנד",
        "עורך דין ישראלי בתאילנד",
        ["עורך דין דובר עברית בתאילנד", "Hebrew lawyer Thailand"],
        "למצוא ולבחור שירות משפטי בעברית בתאילנד",
        "services-in-thailand",
        "service_directory",
        "local_service",
        "regulated_monthly",
        unique_contribution="בחירת עורך דין, בדיקת רישיון, תחומי התמחות, שכר טרחה ושאלות לפגישה",
    ),
    "/טיול-בבנגקוק-ליומיים-3-ימים-או-4-ימים-מדר/": public(
        "bangkok-itinerary-two-to-four-days",
        "he|itinerary|bangkok|two-four-days|plan",
        "מסלול בבנגקוק ליומיים עד ארבעה ימים",
        "מסלול בבנגקוק",
        ["בנגקוק 3 ימים", "Bangkok itinerary"],
        "לבנות מסלול בבנגקוק ליומיים עד ארבעה ימים",
        "bangkok",
        "itinerary_guide",
        "planning",
        "dynamic_weekly",
        subject_entity_ids=["geo:th:province:10"],
    ),
    "/5-דברים-לעשות-ביום-גשום-בתאילנד-2022/": public(
        "thailand-rainy-day-activities",
        "he|travel|thailand|rainy-day-activities|plan",
        "מה לעשות ביום גשום בתאילנד",
        "מה לעשות ביום גשום בתאילנד",
        ["אטרקציות בגשם בתאילנד", "rainy day Thailand"],
        "למצוא פעילויות חלופיות בזמן גשם בתאילנד",
        "thailand-tourism",
        "seasonal_guide",
        "planning",
        "seasonal_monthly",
    ),
    "/": {
        **public(
            "home",
            "he|brand|thailand|orient",
            "תאילנד לישראלים",
            "תאילנד",
            ["תאילנד לישראלים", "Thailand בעברית"],
            "לבחור את מסלול המידע המתאים לכל צורך מרכזי בתאילנד",
            "",
            "homepage",
            "orientation",
            "editorial_quarterly",
            "keep",
        ),
        "parent_owner_id": None,
    },
    "/category/תאילנד-כללי/": public(
        "thailand-general-archive",
        "he|archive|thailand|all-guides|navigate",
        "כל המדריכים לתאילנד",
        "מדריכים לתאילנד",
        ["כתבות על תאילנד", "כל המדריכים לתאילנד"],
        "לדפדף בכל המדריכים הקיימים על תאילנד",
        "home",
        "archive",
        "navigation",
        "editorial_quarterly",
        "extract_then_review",
    ),
    "/category/מלון-כל-סוגי-המלונות/": public(
        "thailand-hotels",
        "he|hotels|thailand|choose-accommodation|learn",
        "מלונות בתאילנד",
        "מלונות בתאילנד",
        ["לינה בתאילנד", "hotels Thailand"],
        "לבחור סוג לינה ואזור בתאילנד",
        "thailand-tourism",
        "booking_guide",
        "evaluation",
        "price_sensitive_monthly",
        "extract_then_review",
    ),
    "/category/פוקט-phuket/": public(
        "phuket",
        "he|place|phuket|orient",
        "פוקט",
        "פוקט",
        ["פוקט תאילנד", "Phuket guide"],
        "להכיר את פוקט ולבחור אזור שהייה",
        "thailand-destinations",
        "destination_hub",
        "orientation",
        "dynamic_weekly",
        "extract_then_review",
        ["geo:th:province:83"],
    ),
    "/category/קו-סמוי-ko-samui-תאילנד/": public(
        "koh-samui",
        "he|place|koh-samui|orient",
        "קוסמוי",
        "קוסמוי",
        ["קו סמוי תאילנד", "Koh Samui guide"],
        "להכיר את קוסמוי ולבחור אזור שהייה",
        "thailand-destinations",
        "destination_hub",
        "orientation",
        "dynamic_weekly",
        "extract_then_review",
        ["geo:th:province:84"],
    ),
}


PLANNED = {
    "/יעדים-בתאילנד/": public(
        "thailand-destinations",
        "he|destinations|thailand|choose-place|orient",
        "יעדים בתאילנד",
        "יעדים בתאילנד",
        ["ערים ואיים בתאילנד", "Thailand destinations"],
        "לבחור עיר, אי, חוף או אזור לפי אופי השהייה",
        "home",
        "national_hub",
        "orientation",
        "dynamic_weekly",
        "create",
    ),
    "/מפת-תאילנד/": public(
        "thailand-map",
        "he|map|thailand|discover|interact",
        "מפת תאילנד",
        "מפת תאילנד",
        ["מפה אינטראקטיבית תאילנד", "Thailand interactive map"],
        "לגלות מקומות, שירותים, נכסים ותחבורה על מפה",
        "home",
        "tool",
        "discovery",
        "generated_daily",
        "create",
    ),
    "/חיים-בתאילנד/": public(
        "living-in-thailand",
        "he|living|thailand|daily-life|orient",
        "חיים בתאילנד",
        "חיים בתאילנד",
        ["לגור בתאילנד", "living in Thailand"],
        "להבין מגורים וחיי יום יום בתאילנד לפי אזור ותקציב",
        "home",
        "national_hub",
        "orientation",
        "editorial_quarterly",
        "create",
    ),
    "/נדלן-בתאילנד/": public(
        "thailand-real-estate",
        "he|real-estate|thailand|market-orient|evaluate",
        "נדל״ן בתאילנד",
        "נדל״ן בתאילנד",
        ["השקעות נדל״ן בתאילנד", "Thailand real estate"],
        "לבחור מסלול מידע לרכישה, מחיר, מימון, בעלות וניהול נכס",
        "home",
        "national_hub",
        "evaluation",
        "price_sensitive_monthly",
        "create",
        unique_contribution="מפת החלטה ארצית שמחברת שוק, מקום, סוג נכס, תהליך, עלות וסיכון",
    ),
    "/פרויקטים-נדלן-בתאילנד/": public(
        "thailand-property-projects",
        "he|real-estate|thailand|projects|compare",
        "פרויקטים של נדל״ן בתאילנד",
        "פרויקטים נדל״ן בתאילנד",
        ["פרויקטים חדשים בתאילנד", "Thailand property projects"],
        "למצוא ולהשוות פרויקטים לפי אזור, מחיר ומפרט",
        "thailand-real-estate",
        "property_directory",
        "comparison",
        "dynamic_weekly",
        "create",
    ),
    "/שירותים-בתאילנד/": public(
        "services-in-thailand",
        "he|services|thailand|find-provider|local",
        "שירותים בתאילנד",
        "שירותים בתאילנד",
        ["נותני שירות בתאילנד", "Thailand service directory"],
        "למצוא נותני שירות לפי תחום ומיקום",
        "home",
        "service_directory",
        "local_service",
        "dynamic_weekly",
        "create",
    ),
    "/ישראלים-בתאילנד/": public(
        "israelis-in-thailand",
        "he|community|thailand|israeli-services|local",
        "ישראלים בתאילנד",
        "ישראלים בתאילנד",
        ["קהילה ישראלית בתאילנד", "Israelis in Thailand"],
        "למצוא קהילות, מקומות ומידע שימושי לישראלים לפי אזור",
        "home",
        "community_hub",
        "local_service",
        "dynamic_weekly",
        "create",
    ),
    "/חנות-לישראלים-בתאילנד/": public(
        "israeli-store-thailand",
        "he|commerce|thailand|israeli-shop|transact",
        "חנות לישראלים בתאילנד",
        "חנות לישראלים בתאילנד",
        ["מוצרים לישראלים בתאילנד", "Israeli shop Thailand"],
        "לקנות מוצרים ושירותים הזמינים לישראלים ברחבי תאילנד",
        "home",
        "storefront",
        "transaction",
        "dynamic_weekly",
        "create",
    ),
    "/ויזות-לתאילנד/": public(
        "thailand-visas",
        "he|immigration|thailand|visa-overview|learn",
        "ויזות לתאילנד",
        "ויזות לתאילנד",
        ["סוגי ויזות לתאילנד", "אשרות ושהייה בתאילנד"],
        "לבחור מסלול כניסה או שהייה מתאים לתאילנד ולהבין את הפעולות לפני הטיסה ואחריה",
        "home",
        "national_hub",
        "learning",
        "regulated_monthly",
        "create",
    ),
    "/חוקים-ומסים-בתאילנד/": public(
        "thailand-law-and-tax",
        "he|law-tax|thailand|overview|learn",
        "חוקים ומסים בתאילנד",
        "חוקים בתאילנד לישראלים",
        ["חוקים ומסים בתאילנד", "כללים משפטיים בתאילנד"],
        "להבין כללים מרכזיים לפני מגורים, עבודה, עסק, השקעה או טיול בתאילנד ולמצוא מדריך מדויק",
        "home",
        "national_hub",
        "learning",
        "regulated_monthly",
        "create",
    ),
    "/בריאות-בתאילנד/": public(
        "health-in-thailand",
        "he|health|thailand|care-overview|learn",
        "בריאות בתאילנד",
        "בריאות בתאילנד",
        ["רפואה בתאילנד", "healthcare Thailand"],
        "להבין טיפול רפואי, חירום, ביטוח ובחירת מסגרת",
        "home",
        "national_hub",
        "support",
        "regulated_monthly",
        "create",
    ),
    "/תחבורה-בתאילנד/": public(
        "transport-in-thailand",
        "he|transport|thailand|route-plan|plan",
        "תחבורה בתאילנד",
        "תחבורה בתאילנד",
        ["נסיעות בתוך תאילנד", "Thailand transport"],
        "לתכנן תנועה בין ערים, איים ואזורים",
        "home",
        "national_hub",
        "planning",
        "dynamic_weekly",
        "create",
    ),
}


MANAGED_LIVE_PATHS = (
    "/נדלן-בתאילנד/",
    "/ויזות-לתאילנד/",
    "/חוקים-ומסים-בתאילנד/",
)
MANAGED_LIVE = {path: PLANNED[path] for path in MANAGED_LIVE_PATHS}
MANAGED_LIVE_EVIDENCE_BY_OWNER = {
    "thailand-real-estate": MANAGED_LIVE_EVIDENCE_RELATIVE,
    "thailand-visas": GUIDES_PRIVATE_CANARY_EVIDENCE_RELATIVE,
    "thailand-law-and-tax": GUIDES_PRIVATE_CANARY_EVIDENCE_RELATIVE,
}
PLANNED = {
    url: definition
    for url, definition in PLANNED.items()
    if url not in MANAGED_LIVE
}


TECHNICAL = {
    "/?s={query}": public(
        "site-search",
        "he|site|search|query|support",
        "חיפוש באתר",
        "חיפוש באתר",
        ["חיפוש Thai-Land", "site search Thai-Land"],
        "לחפש יעד, מדריך או נושא בתוך האתר",
        "home",
        "site_search",
        "support",
        "generated_daily",
        "technical",
        [],
    ),
    "/sitemap_index.xml": public(
        "sitemap-index",
        "technical|sitemap|index",
        "מפת האתר למנועי חיפוש",
        "מפת אתר XML",
        ["Yoast sitemap index"],
        "לאפשר למנועי חיפוש לגלות את הכתובות הקנוניות",
        "home",
        "sitemap",
        "technical",
        "generated_daily",
        "technical",
        [],
    ),
    "/wp-json/thailand-platform/v1/health": public(
        "platform-health",
        "technical|api|platform-health",
        "בדיקת תקינות המערכת",
        "Thailand Platform health API",
        ["platform health endpoint"],
        "להחזיר למערכות ניטור את גרסת הרכיב ומצב התקינות",
        "home",
        "api_endpoint",
        "technical",
        "release_bound",
        "technical",
        [],
    ),
    "/wp-json/thailand-platform/v1/geography": public(
        "platform-geography",
        "technical|api|thailand-geography",
        "ממשק הגאוגרפיה של תאילנד",
        "Thailand geography API",
        ["Thailand provinces API"],
        "להחזיר את המדינה, האזורים הסטטיסטיים ו-77 המחוזות",
        "home",
        "api_endpoint",
        "technical",
        "release_bound",
        "technical",
        ["geo:th:country"],
    ),
}


CONFLICTS = {
    "home": ["thailand-tourism", "thailand-first-trip-guide", "thailand-general-archive", "business-in-thailand", "thailand-real-estate"],
    "thailand-tourism": ["home", "thailand-first-trip-guide", "thailand-places-to-visit", "thailand-general-archive"],
    "thailand-first-trip-guide": ["thailand-tourism", "home"],
    "thailand-flights": ["flight-booking-myths", "cheap-flight-tips-legacy"],
    "cheap-flight-tips-legacy": ["thailand-flights", "flight-booking-myths"],
    "thailand-entry-requirements": ["thailand-visas", "thailand-entry-april-2022", "thailand-tourist-visa"],
    "thailand-tourist-visa": ["thailand-visas", "thailand-entry-requirements", "thailand-visa-service"],
    "thailand-visas": ["thailand-entry-requirements", "thailand-entry-april-2022", "thailand-tourist-visa", "thailand-permanent-residence"],
    "thailand-entry-april-2022": ["thailand-visas", "thailand-entry-requirements"],
    "thailand-permanent-residence": ["thailand-visas"],
    "thailand-law-and-tax": ["thailand-cannabis-law"],
    "thailand-cannabis-law": ["thailand-law-and-tax"],
    "business-in-thailand": ["thailand-economy", "thailand-work-permit", "thai-lawyer-hebrew-service"],
    "thai-lawyer-hebrew-service": ["business-in-thailand", "thailand-tourist-visa", "buy-property-thailand"],
    "thailand-real-estate": ["buy-property-thailand", "thailand-property-prices"],
    "buy-property-thailand": ["thailand-property-financing", "thailand-property-prices", "foreign-condo-ownership-thailand", "thailand-property-due-diligence-mistakes"],
    "thailand-property-prices": ["buy-property-thailand", "thailand-property-financing"],
    "thailand-property-financing": ["buy-property-thailand", "thailand-property-prices"],
    "foreign-condo-ownership-thailand": ["buy-property-thailand", "thailand-property-due-diligence-mistakes"],
    "phuket": ["phuket-or-samui", "thailand-places-to-visit"],
    "koh-samui": ["phuket-or-samui", "koh-samui-luxury-hotels", "koh-samui-new-hotels-2022"],
    "thailand-hotels": ["koh-samui-luxury-hotels", "koh-samui-new-hotels-2022"],
}

REAL_ESTATE_SPOKES = [
    "buy-property-thailand",
    "thailand-property-prices",
    "thailand-property-financing",
    "foreign-condo-ownership-thailand",
    "thailand-property-due-diligence-mistakes",
    "property-management-thailand",
    "bangkok-apartment-rental-guide",
]


def normalize_route(value: str) -> str:
    """Normalize one route for exact inventory comparisons."""
    value = unicodedata.normalize("NFC", value.strip())
    if not value.startswith("/"):
        raise ValueError(f"route must be site-relative: {value}")
    if "?" not in value and not value.endswith("/"):
        value += "/"
    return value


def read_inventory(path: Path) -> list[dict[str, str]]:
    with path.open("r", encoding="utf-8-sig", newline="") as handle:
        return list(csv.DictReader(handle))


def lf_digest(path: Path) -> str:
    payload = path.read_bytes().replace(b"\r\n", b"\n").replace(b"\r", b"\n")
    return hashlib.sha256(payload).hexdigest()


def read_json(path: Path) -> dict[str, Any]:
    """Read one local evidence object without network access."""
    try:
        value = json.loads(path.read_text(encoding="utf-8"))
    except (OSError, json.JSONDecodeError) as error:
        raise ValueError(f"cannot read JSON evidence {path}: {error}") from error
    if not isinstance(value, dict):
        raise ValueError(f"JSON evidence root must be an object: {path}")
    return value


def validate_file_claim(
    claim: dict[str, Any], path_key: str, bytes_key: str, sha_key: str
) -> Path:
    """Validate one already captured local artifact claim."""
    relative = claim.get(path_key)
    if not isinstance(relative, str) or not relative:
        raise ValueError(f"managed-live evidence lacks {path_key}")
    path = (ROOT / relative).resolve()
    try:
        path.relative_to(ROOT.resolve())
    except ValueError as error:
        raise ValueError(f"managed-live evidence escapes repository: {relative}") from error
    if not path.is_file():
        raise ValueError(f"managed-live evidence file is missing: {relative}")
    if path.stat().st_size != claim.get(bytes_key):
        raise ValueError(f"managed-live evidence byte count changed: {relative}")
    digest = hashlib.sha256(path.read_bytes()).hexdigest()
    if digest != claim.get(sha_key):
        raise ValueError(f"managed-live evidence digest changed: {relative}")
    return path


def validate_managed_live_evidence() -> dict[str, Any]:
    """Validate the frozen release, health, and acceptance evidence bundle."""
    evidence = read_json(MANAGED_LIVE_EVIDENCE)
    if evidence.get("schema_version") != 1:
        raise ValueError("managed-live evidence schema mismatch")
    if evidence.get("evidence_id") != "thailand-platform-real-estate-0.3.5-managed-live":
        raise ValueError("managed-live evidence ID mismatch")
    if evidence.get("origin") != "https://thai-land.co.il":
        raise ValueError("managed-live evidence origin mismatch")

    expected_release = {
        "version": "0.3.5",
        "receipt_path": "plugin-dist/0.3.5/thailand-platform-0.3.5.receipt.json",
        "receipt_bytes": 17061,
        "receipt_sha256": "1ff19beb7378d9f0bc56a487d4656bf07b0ae96b45b7c9440a7e9d9c534b4ada",
        "artifact_path": "plugin-dist/0.3.5/thailand-platform-0.3.5.zip",
        "artifact_bytes": 677753,
        "artifact_sha256": "765f000e17656d513cd53e530207a656827261ef77559b036e2a7ae68cb1d070",
        "source_commit": "b71a77f243bbeb8d5e17a96c08c5742e48d5ddce",
    }
    release = evidence.get("release")
    if release != expected_release:
        raise ValueError("managed-live release claim mismatch")
    receipt_path = validate_file_claim(
        release, "receipt_path", "receipt_bytes", "receipt_sha256"
    )
    validate_file_claim(release, "artifact_path", "artifact_bytes", "artifact_sha256")
    receipt = read_json(receipt_path)
    for key, expected in (
        ("version", release["version"]),
        ("source_commit", release["source_commit"]),
        ("bytes", release["artifact_bytes"]),
        ("sha256", release["artifact_sha256"]),
        ("deterministic_zip", True),
    ):
        if receipt.get(key) != expected:
            raise ValueError(f"managed-live receipt field mismatch: {key}")

    expected_health = {
        "url": "https://thai-land.co.il/wp-json/thailand-platform/v1/health",
        "observed_at": "2026-08-08T19:32:22.941Z",
        "http_status": 200,
        "response": {
            "name": "thailand-platform",
            "version": "0.3.5",
            "status": "ok",
        },
    }
    if evidence.get("health") != expected_health:
        raise ValueError("managed-live health claim mismatch")

    expected_acceptance = {
        "path": "output/playwright/real-estate-live-0.3.5-acceptance.json",
        "bytes": 403730,
        "sha256": "b6932b273175505f4e8f86a6b6c11aeac2a43891901c88c2c367da5ffbe2681a",
        "contract_id": "thailand-real-estate-v1",
        "route_count": 8,
        "passed": True,
        "passed_count": 374,
        "failed_count": 0,
    }
    acceptance_claim = evidence.get("acceptance")
    if acceptance_claim != expected_acceptance:
        raise ValueError("managed-live acceptance claim mismatch")
    acceptance_path = validate_file_claim(
        acceptance_claim, "path", "bytes", "sha256"
    )
    acceptance = read_json(acceptance_path)
    acceptance_result = acceptance.get("acceptance", {})
    for key, expected in (
        ("release", release["version"]),
        ("contract_id", acceptance_claim["contract_id"]),
        ("route_count", acceptance_claim["route_count"]),
    ):
        if acceptance.get(key) != expected:
            raise ValueError(f"managed-live acceptance field mismatch: {key}")
    for key in ("passed", "passed_count", "failed_count"):
        if acceptance_result.get(key) != acceptance_claim[key]:
            raise ValueError(f"managed-live acceptance result mismatch: {key}")

    managed_routes = evidence.get("managed_routes")
    if managed_routes != EXPECTED_MANAGED_ROUTES:
        raise ValueError("managed-live route bindings changed")
    acceptance_routes = acceptance.get("routes")
    if not isinstance(acceptance_routes, dict) or set(acceptance_routes) != {
        route["route_id"] for route in EXPECTED_MANAGED_ROUTES
    }:
        raise ValueError("managed-live acceptance route set mismatch")
    for route in EXPECTED_MANAGED_ROUTES:
        route_result = acceptance_routes[route["route_id"]]
        for viewport in ("desktop", "mobile"):
            result = route_result.get(viewport, {})
            if result.get("http_status") != 200:
                raise ValueError(
                    f"managed-live acceptance HTTP mismatch: {route['route_id']} {viewport}"
                )
            canonical = result.get("inspection", {}).get("canonical", "")
            canonical_path = unicodedata.normalize(
                "NFC", unquote(urlsplit(canonical).path)
            )
            if normalize_route(canonical_path) != route["canonical_url"]:
                raise ValueError(
                    f"managed-live acceptance canonical mismatch: {route['route_id']} {viewport}"
                )
    return evidence


def validate_guides_private_canary_evidence() -> dict[str, Any]:
    """Validate the redacted private Canary record without claiming public release."""
    evidence = read_json(GUIDES_PRIVATE_CANARY_EVIDENCE)
    if set(evidence) != {
        "schema_version",
        "evidence_id",
        "origin",
        "recorded_at",
        "evidence_scope",
        "public_live_verified",
        "privacy",
        "release",
        "production_state",
        "acceptance",
    }:
        raise ValueError("Guides private Canary evidence shape mismatch")
    expected_recorded_at = "2026-08-10T17:37:37+03:00"
    for key, expected in (
        ("schema_version", 1),
        ("evidence_id", "thailand-platform-priority-guides-0.4.0-private-canary"),
        ("origin", "https://thai-land.co.il"),
        ("recorded_at", expected_recorded_at),
        ("evidence_scope", "authenticated_manual_canary"),
        ("public_live_verified", False),
    ):
        if evidence.get(key) != expected:
            raise ValueError(f"Guides private Canary evidence mismatch: {key}")

    expected_privacy = {
        "authenticated_request_locator": "redacted",
        "cookies_recorded": False,
        "credentials_recorded": False,
        "screenshots_claimed": False,
        "acceptance_artifact_claimed": False,
    }
    if evidence.get("privacy") != expected_privacy:
        raise ValueError("Guides private Canary redaction contract mismatch")

    expected_release = {
        "version": "0.4.0",
        "receipt_path": "plugin-dist/0.4.0/thailand-platform-0.4.0.receipt.json",
        "receipt_bytes": 21933,
        "receipt_sha256": "d8424e85f80a47ba0f536c1066ddacfa192cac0ff4ec8f1c233c177e9cf46146",
        "artifact_path": "plugin-dist/0.4.0/thailand-platform-0.4.0.zip",
        "artifact_bytes": 1340922,
        "artifact_sha256": "26f5f289be5cdfcd0a3ce840a511e91803fdaf6d0ace10b54b11f8a776ffbe19",
        "source_commit": "1ec757d2455921f164358f10d234a181cf794b51",
    }
    release = evidence.get("release")
    if release != expected_release:
        raise ValueError("Guides private Canary release claim mismatch")
    receipt_path = validate_file_claim(
        release, "receipt_path", "receipt_bytes", "receipt_sha256"
    )
    validate_file_claim(release, "artifact_path", "artifact_bytes", "artifact_sha256")
    receipt = read_json(receipt_path)
    for key, expected in (
        ("version", release["version"]),
        ("source_commit", release["source_commit"]),
        ("bytes", release["artifact_bytes"]),
        ("sha256", release["artifact_sha256"]),
        ("deterministic_zip", True),
    ):
        if receipt.get(key) != expected:
            raise ValueError(f"Guides private Canary receipt field mismatch: {key}")

    expected_production_state = {
        "health": {
            "url": "https://thai-land.co.il/wp-json/thailand-platform/v1/health",
            "observed_at": expected_recorded_at,
            "http_status": 200,
            "cache_control": "no-store",
            "response": {
                "name": "thailand-platform",
                "version": "0.4.0",
                "status": "ok",
            },
        },
        "active_plugin_basename": "thailand-platform-live-040/thailand-platform.php",
        "inactive_predecessor_basename": "thailand-platform-live-036/thailand-platform.php",
        "guides_mode": "canary",
    }
    if evidence.get("production_state") != expected_production_state:
        raise ValueError("Guides private Canary production state mismatch")

    acceptance = evidence.get("acceptance")
    if not isinstance(acceptance, dict) or set(acceptance) != {
        "method",
        "observed_at",
        "passed",
        "route_count",
        "public_live_verified",
        "robots",
        "canonical_behavior",
        "assertions",
        "routes",
        "anonymous_isolation",
    }:
        raise ValueError("Guides private Canary acceptance shape mismatch")
    expected_acceptance_fields = {
        "method": "authenticated_manual_canary",
        "observed_at": expected_recorded_at,
        "passed": True,
        "route_count": 7,
        "public_live_verified": False,
        "robots": "noindex,nofollow",
        "canonical_behavior": "intentionally_absent_in_private_canary",
    }
    for key, expected in expected_acceptance_fields.items():
        if acceptance.get(key) != expected:
            raise ValueError(f"Guides private Canary acceptance mismatch: {key}")
    expected_assertions = {
        "exact_route_and_owner_markers": True,
        "one_h1": True,
        "one_main": True,
        "one_current_breadcrumb": True,
        "breadcrumb_count_min": 2,
        "breadcrumb_count_max": 3,
        "section_count_min": 5,
        "section_count_max": 7,
        "contextual_target_count_min": 1,
        "contextual_target_count_max": 3,
        "unlinked_contextual_target_count": 0,
        "official_source_link_count_min": 2,
        "official_source_link_count_max": 8,
        "breadcrumb_schema_present": True,
        "versioned_css_and_javascript_present": True,
        "hero_image_loaded": True,
        "duplicate_id_count": 0,
        "unnamed_link_count": 0,
        "horizontal_overflow": False,
    }
    if acceptance.get("assertions") != expected_assertions:
        raise ValueError("Guides private Canary assertions mismatch")
    if acceptance.get("routes") != EXPECTED_GUIDES_CANARY_ROUTES:
        raise ValueError("Guides private Canary route bindings changed")
    expected_isolation = {
        "published_route_canary_probe_http_status": 404,
        "draft_route_canary_probe_http_status": 404,
        "normal_published_route_http_status": 200,
        "normal_published_route_guides_marker_present": False,
    }
    if acceptance.get("anonymous_isolation") != expected_isolation:
        raise ValueError("Guides private Canary anonymous isolation mismatch")
    return evidence


def review_state(action: str, lifecycle: str) -> str:
    if lifecycle == "planned":
        return "planned"
    if action == "technical":
        return "technical"
    if action == "keep":
        return "upgrade_required"
    if action in {"preserve_historical", "conditional_merge", "extract_then_review"}:
        return "evidence_pending"
    return "upgrade_required"


def source_for_path(path: str, snapshot_paths: dict[str, set[str]]) -> tuple[list[str], list[str]]:
    observed = [
        snapshot_id
        for snapshot_id, paths in snapshot_paths.items()
        if path in paths
    ]
    evidence = [
        snapshot["path"]
        for snapshot in SNAPSHOTS
        if snapshot["snapshot_id"] in observed
    ]
    return observed, evidence


def build_registry() -> dict[str, Any]:
    validate_managed_live_evidence()
    validate_guides_private_canary_evidence()
    snapshot_files = {
        "yoast-sitemaps-2026-08-08": SITEMAP_INVENTORY,
        "indexable-category-surfaces-2026-08-08": CATEGORY_INVENTORY,
    }
    snapshot_paths: dict[str, set[str]] = {}
    for snapshot in SNAPSHOTS:
        file_path = ROOT / snapshot["path"]
        rows = read_inventory(file_path)
        if len(rows) != snapshot["row_count"]:
            raise ValueError(f"inventory row count changed: {snapshot['snapshot_id']}")
        if lf_digest(file_path) != snapshot["content_sha256"]:
            raise ValueError(f"inventory digest changed: {snapshot['snapshot_id']}")
        snapshot_paths[snapshot["snapshot_id"]] = {
            normalize_route(row["DecodedPath"]) for row in rows
        }

    protected = set().union(*snapshot_paths.values())
    if protected != set(PUBLIC):
        missing = sorted(protected - set(PUBLIC))
        unexpected = sorted(set(PUBLIC) - protected)
        raise ValueError(
            f"public ownership mapping differs from inventory: missing={missing}, unexpected={unexpected}"
        )

    definitions: dict[str, dict[str, Any]] = {}
    canonical_urls: dict[str, str] = {}
    lifecycle_by_owner: dict[str, str] = {}

    for url, definition in PUBLIC.items():
        owner_id = definition["owner_id"]
        definitions[owner_id] = dict(definition)
        canonical_urls[owner_id] = url
        lifecycle_by_owner[owner_id] = "live"

    for url, definition in MANAGED_LIVE.items():
        owner_id = definition["owner_id"]
        if owner_id in definitions:
            raise ValueError(f"duplicate owner definition: {owner_id}")
        definitions[owner_id] = dict(definition)
        canonical_urls[owner_id] = url
        lifecycle_by_owner[owner_id] = "live"

    for url, definition in PLANNED.items():
        owner_id = definition["owner_id"]
        if owner_id in definitions:
            raise ValueError(f"duplicate owner definition: {owner_id}")
        definitions[owner_id] = dict(definition)
        canonical_urls[owner_id] = url
        lifecycle_by_owner[owner_id] = "planned"

    for url, definition in TECHNICAL.items():
        owner_id = definition["owner_id"]
        if owner_id in definitions:
            raise ValueError(f"duplicate owner definition: {owner_id}")
        definitions[owner_id] = dict(definition)
        canonical_urls[owner_id] = url
        lifecycle_by_owner[owner_id] = "live"

    for owner_id, definition in definitions.items():
        parent = definition["parent_owner_id"]
        if parent is not None and parent not in definitions:
            raise ValueError(f"unknown parent owner: {owner_id} -> {parent}")

    def chain(owner_id: str) -> list[str]:
        result: list[str] = []
        current: str | None = owner_id
        while current is not None:
            if current in result:
                raise ValueError(f"parent cycle at {owner_id}")
            result.append(current)
            current = definitions[current]["parent_owner_id"]
        return list(reversed(result))

    link_requirements: dict[str, list[dict[str, Any]]] = {
        owner_id: [] for owner_id in definitions
    }
    planned_link_requirements: dict[str, list[dict[str, Any]]] = {
        owner_id: [] for owner_id in definitions
    }

    def add_link(
        source: str,
        target: str,
        relationship: str,
        placement: str,
        anchor_terms: list[str],
    ) -> None:
        key = (target, relationship, placement)
        bucket = (
            link_requirements
            if lifecycle_by_owner[source] == "live"
            and lifecycle_by_owner[target] == "live"
            else planned_link_requirements
        )
        existing = {
            (item["target_owner_id"], item["relationship"], item["placement"])
            for item in bucket[source]
        }
        if key in existing:
            return
        bucket[source].append(
            {
                "target_owner_id": target,
                "relationship": relationship,
                "placement": placement,
                "minimum_occurrences": 1,
                "anchor_terms": anchor_terms,
            }
        )

    technical_types = {"api_endpoint", "sitemap"}
    for owner_id, definition in definitions.items():
        parent = definition["parent_owner_id"]
        if parent is not None and definition["entity_type"] not in technical_types:
            add_link(
                owner_id,
                parent,
                "parent_hub",
                "contextual_body",
                [definitions[parent]["primary_keyword"]],
            )
            add_link(
                parent,
                owner_id,
                "child_spoke",
                "navigation",
                [definition["primary_keyword"]],
            )

    for owner_id, definition in definitions.items():
        if (
            lifecycle_by_owner[owner_id] != "live"
            or definition["entity_type"] in technical_types
            or owner_id == "home"
        ):
            continue
        live_chain = [
            chain_id
            for chain_id in chain(owner_id)
            if lifecycle_by_owner[chain_id] == "live"
        ]
        if len(live_chain) < 2:
            raise ValueError(f"live owner has no live breadcrumb parent: {owner_id}")
        live_parent = live_chain[-2]
        add_link(
            owner_id,
            live_parent,
            "parent_hub",
            "contextual_body",
            [definitions[live_parent]["primary_keyword"]],
        )
        add_link(
            live_parent,
            owner_id,
            "child_spoke",
            "navigation",
            [definition["primary_keyword"]],
        )

    for index, owner_id in enumerate(REAL_ESTATE_SPOKES):
        for offset in (1, 2):
            sibling = REAL_ESTATE_SPOKES[(index + offset) % len(REAL_ESTATE_SPOKES)]
            add_link(
                owner_id,
                sibling,
                "sibling",
                "contextual_body",
                [definitions[sibling]["primary_keyword"]],
            )

    add_link(
        "bangkok-apartment-rental-guide",
        "bangkok",
        "support",
        "contextual_body",
        ["מדריך בנגקוק"],
    )
    for owner_id in GUIDES_CHILD_OWNER_IDS:
        add_link(
            owner_id,
            "home",
            "support",
            "contextual_body",
            [definitions["home"]["primary_keyword"]],
        )
    add_link(
        "thailand-entry-april-2022",
        "thailand-entry-requirements",
        "support",
        "contextual_body",
        [definitions["thailand-entry-requirements"]["primary_keyword"]],
    )

    owners: list[dict[str, Any]] = []
    for owner_id in sorted(definitions):
        definition = definitions[owner_id]
        owner_chain = chain(owner_id)
        source_evidence: list[str]
        canonical_url = canonical_urls[owner_id]
        if lifecycle_by_owner[owner_id] == "planned":
            source_evidence = ["research/serp/2026-08-08-hebrew-thailand-serp.md"]
        elif canonical_url in PUBLIC:
            _, source_evidence = source_for_path(canonical_url, snapshot_paths)
        elif canonical_url in MANAGED_LIVE:
            source_evidence = [MANAGED_LIVE_EVIDENCE_BY_OWNER[owner_id]]
        else:
            source_evidence = ["README.md"]

        exclusions = []
        for other_id in CONFLICTS.get(owner_id, []):
            if other_id not in definitions:
                raise ValueError(f"unknown conflict owner: {owner_id} -> {other_id}")
            exclusions.append(
                {
                    "owner_id": other_id,
                    "intent_id": definitions[other_id]["intent_id"],
                }
            )

        owners.append(
            {
                "owner_id": owner_id,
                "lifecycle": lifecycle_by_owner[owner_id],
                "canonical_url": canonical_url,
                "intent_id": definition["intent_id"],
                "name": definition["name"],
                "primary_intent": definition["primary_intent"],
                "primary_keyword": definition["primary_keyword"],
                "intent_synonyms": definition["intent_synonyms"],
                "intent_class": definition["intent_class"],
                "entity_type": definition["entity_type"],
                "subject_entity_ids": definition["subject_entity_ids"],
                "parent_owner_id": definition["parent_owner_id"],
                "breadcrumb_chain": [
                    {
                        "owner_id": chain_id,
                        "name": definitions[chain_id]["name"],
                        "url": canonical_urls[chain_id],
                    }
                    for chain_id in owner_chain
                ],
                "internal_link_requirements": sorted(
                    link_requirements[owner_id],
                    key=lambda item: (
                        item["relationship"],
                        item["placement"],
                        item["target_owner_id"],
                    ),
                ),
                "planned_internal_link_requirements": sorted(
                    planned_link_requirements[owner_id],
                    key=lambda item: (
                        item["relationship"],
                        item["placement"],
                        item["target_owner_id"],
                    ),
                ),
                "freshness_class": definition["freshness_class"],
                "migration_action": definition["migration_action"],
                "review_state": review_state(
                    definition["migration_action"], lifecycle_by_owner[owner_id]
                ),
                "unique_contribution": definition["unique_contribution"],
                "source_evidence": source_evidence,
                "cannibalization_exclusions": sorted(
                    exclusions, key=lambda item: item["owner_id"]
                ),
            }
        )

    routes: list[dict[str, Any]] = []
    for url in sorted(PUBLIC):
        observed, evidence = source_for_path(url, snapshot_paths)
        definition = PUBLIC[url]
        if definition["owner_id"] == "cheap-flight-tips-legacy":
            assignment = {
                "kind": "migration_gate",
                "state": "evidence_pending",
                "release_blocked": True,
                "current_owner_id": "cheap-flight-tips-legacy",
                "candidate_owner_id": "thailand-flights",
                "required_evidence": [
                    "נתוני שאילתות נקיים ברמת כתובת",
                    "בדיקת קישורים חיצוניים והמרות",
                    "השוואת סעיפים מול בעל הטיסות הראשי",
                ],
            }
            route_id = "route-cheap-flight-tips-legacy"
        else:
            assignment = {
                "kind": "canonical_owner",
                "owner_id": definition["owner_id"],
            }
            route_id = f"route-{definition['owner_id']}"
        routes.append(
            {
                "route_id": route_id,
                "url": url,
                "route_kind": "exact",
                "lifecycle": "live",
                "indexing_policy": FROZEN_ROUTE_INDEXING_OVERRIDES.get(
                    definition["owner_id"], "index"
                ),
                "observed_in": observed,
                "source_evidence": evidence,
                "assignment": assignment,
            }
        )

    for url, definition in sorted(MANAGED_LIVE.items()):
        routes.append(
            {
                "route_id": f"route-{definition['owner_id']}",
                "url": url,
                "route_kind": "exact",
                "lifecycle": "live",
                "indexing_policy": "index",
                "observed_in": [],
                "source_evidence": [
                    MANAGED_LIVE_EVIDENCE_BY_OWNER[definition["owner_id"]]
                ],
                "assignment": {
                    "kind": "canonical_owner",
                    "owner_id": definition["owner_id"],
                },
            }
        )

    for url, definition in sorted(PLANNED.items()):
        routes.append(
            {
                "route_id": f"route-{definition['owner_id']}",
                "url": url,
                "route_kind": "exact",
                "lifecycle": "planned",
                "indexing_policy": "index",
                "observed_in": [],
                "source_evidence": ["research/serp/2026-08-08-hebrew-thailand-serp.md"],
                "assignment": {
                    "kind": "canonical_owner",
                    "owner_id": definition["owner_id"],
                },
            }
        )

    for url, definition in sorted(TECHNICAL.items()):
        routes.append(
            {
                "route_id": f"route-{definition['owner_id']}",
                "url": url,
                "route_kind": "pattern" if "{" in url else "exact",
                "lifecycle": "live",
                "indexing_policy": (
                    "noindex"
                    if definition["owner_id"] == "site-search"
                    else "technical"
                ),
                "observed_in": [],
                "source_evidence": ["README.md"],
                "assignment": {
                    "kind": "canonical_owner",
                    "owner_id": definition["owner_id"],
                },
            }
        )

    routes.append(
        {
            "route_id": "route-business-short-redirect",
            "url": "/עסקים-בתאילנד/",
            "route_kind": "exact",
            "lifecycle": "live",
            "indexing_policy": "redirect",
            "observed_in": [],
            "source_evidence": ["research/serp/2026-08-08-hebrew-thailand-serp.md"],
            "assignment": {
                "kind": "migration_gate",
                "state": "target_review_pending",
                "release_blocked": True,
                "current_owner_id": "business-in-thailand",
                "candidate_owner_id": "business-in-thailand",
                "required_evidence": [
                    "הכתובת מפנה כעת לבעל העסקים הקיים",
                    "אין לפרסם תוכן מתחרה על כתובת זו",
                ],
            },
            "redirect_target": "/עסקים-בתאילנד-סקירה-כללית/",
        }
    )

    routes.sort(key=lambda item: item["url"])

    return {
        "$schema": "./ownership-registry.schema.json",
        "registry_version": "2.0.0",
        "site": {
            "name": "Thai-Land.co.il",
            "base_url": "https://thai-land.co.il",
            "default_language": "he-IL",
        },
        "discovery": {
            "route_policy": "כל כתובת ציבורית מקבלת בעל כוונת חיפוש יחיד או שער הגירה חסום עם ראיות נדרשות.",
            "excluded_surfaces": [
                "מקטעי עמוד שמתחילים בסימן #",
                "קובצי CSS, JavaScript, תמונות וגופנים",
                "כתובות ניהול וקנרי פרטיות",
                "פרמטרי סינון, מיון ומצב מפה שאינם דפי יעד",
            ],
        },
        "research_evidence": [
            {
                "evidence_id": "google-ai-search-2026-07",
                "url": "https://developers.google.com/search/docs/fundamentals/ai-optimization-guide",
                "checked_on": "2026-08-08",
                "purpose": "מילים נרדפות, query fan-out, תוכן ייחודי והימנעות מפיצול מלאכותי",
                "authority": True,
            },
            {
                "evidence_id": "google-title-links",
                "url": "https://developers.google.com/search/docs/appearance/title-link",
                "checked_on": "2026-08-08",
                "purpose": "כותרות תיאוריות, קצרות וייחודיות ללא דחיסת מילות מפתח",
                "authority": True,
            },
            {
                "evidence_id": "google-link-practices",
                "url": "https://developers.google.com/search/docs/crawling-indexing/links-crawlable",
                "checked_on": "2026-08-08",
                "purpose": "קישורי HTML זחילים, עוגנים תיאוריים וקישורים הקשריים",
                "authority": True,
            },
            {
                "evidence_id": "google-breadcrumbs",
                "url": "https://developers.google.com/search/docs/appearance/structured-data/breadcrumb",
                "checked_on": "2026-08-08",
                "purpose": "פירורי לחם שמייצגים מסלול משתמש טיפוסי",
                "authority": True,
            },
            {
                "evidence_id": "google-sitemaps",
                "url": "https://developers.google.com/search/docs/crawling-indexing/sitemaps/build-sitemap",
                "checked_on": "2026-08-08",
                "purpose": "כתובות קנוניות ו-lastmod שמשקף שינוי משמעותי",
                "authority": True,
            },
            {
                "evidence_id": "google-commerce-structure",
                "url": "https://developers.google.com/search/docs/specialty/ecommerce/help-google-understand-your-ecommerce-site-structure",
                "checked_on": "2026-08-08",
                "purpose": "קישור בית לקטגוריה, תת קטגוריה ופריט במסחר",
                "authority": True,
            },
            {
                "evidence_id": "hebrew-thailand-serp-2026-08-08",
                "url": "research/serp/2026-08-08-hebrew-thailand-serp.md",
                "checked_on": "2026-08-08",
                "purpose": "השוואת כוונות, מתחרים ומבני תוצאה בעברית",
                "authority": False,
            },
        ],
        "inventory_snapshots": list(SNAPSHOTS),
        "intent_owners": owners,
        "routes": routes,
    }


def serialized_registry() -> str:
    return json.dumps(
        build_registry(),
        ensure_ascii=False,
        indent=2,
        sort_keys=False,
    ) + "\n"


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument(
        "--check",
        action="store_true",
        help="Fail when the compiled registry differs from reviewed source.",
    )
    args = parser.parse_args()
    expected = serialized_registry()
    if args.check:
        if not OUTPUT.is_file() or OUTPUT.read_text(encoding="utf-8") != expected:
            raise SystemExit("SEO ownership registry is stale; rebuild it.")
        print("PASS: SEO ownership registry is current")
        return 0
    OUTPUT.write_text(expected, encoding="utf-8", newline="\n")
    print(f"Wrote {OUTPUT.relative_to(ROOT)}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
