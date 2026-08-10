#!/usr/bin/env python3
"""Dependency-free validation for queued expired-page replacement contracts."""

from __future__ import annotations

import json
import re
import unittest
from datetime import date
from pathlib import Path
from typing import Any, Iterable


ROOT = Path(__file__).resolve().parents[1]
QUEUE = ROOT / "data" / "content" / "queued"
SEO_REGISTRY_PATH = ROOT / "data" / "seo" / "ownership-registry.json"

EXPECTED = {
    "post-17-koh-samui-new-hotels-2022.json": {
        "post_id": 17,
        "path": "/מלונות-חדשים-בקו-סמוי-2022/",
        "owner_id": "koh-samui-new-hotels-2022",
        "parent_owner_id": "koh-samui",
        "kind": "publish_ready_article",
        "content_key": "public_content",
        "minimum_sections": 8,
        "minimum_sources": 8,
        "minimum_words": 850,
    },
    "post-136-thailand-rainy-day-activities.json": {
        "post_id": 136,
        "path": "/5-דברים-לעשות-ביום-גשום-בתאילנד-2022/",
        "owner_id": "thailand-rainy-day-activities",
        "parent_owner_id": "thailand-tourism",
        "kind": "publish_ready_article",
        "content_key": "public_content",
        "minimum_sections": 10,
        "minimum_sources": 8,
        "minimum_words": 1000,
    },
    "post-732-thailand-family-holiday-costs.json": {
        "post_id": 732,
        "path": "/המחירים-הזולים-ביותר-תאילנד-2025/",
        "owner_id": "thailand-family-holiday-costs",
        "parent_owner_id": "thailand-tourism",
        "kind": "publish_ready_article",
        "content_key": "public_content",
        "minimum_sections": 12,
        "minimum_sources": 7,
        "minimum_words": 1200,
    },
    "post-810-thailand-property-prices-plan.json": {
        "post_id": 810,
        "path": "/price/",
        "owner_id": "thailand-property-prices",
        "parent_owner_id": "thailand-real-estate",
        "kind": "source_dated_replacement_plan",
        "content_key": "publishable_copy",
        "minimum_sections": 12,
        "minimum_sources": 10,
        "minimum_words": 850,
    },
}

FACTUAL_SECTION_IDS = {
    "post-17-koh-samui-new-hotels-2022.json": {
        "kimpton-kitalay",
        "avani-chaweng",
        "holiday-inn-bophut",
        "outrigger-lamai",
    },
    "post-136-thailand-rainy-day-activities.json": {
        "weather-first",
        "bangkok",
        "phuket",
        "koh-samui",
        "chiang-mai",
        "low-cost",
        "time-blocks",
        "avoid",
    },
    "post-732-thailand-family-holiday-costs.json": {
        "inputs-first",
        "international-flights",
        "rooms",
        "route-cost",
        "domestic-transport",
        "food",
        "activities",
        "insurance-connectivity",
        "budget-styles",
        "dated-cost-examples",
        "itinerary-models",
        "price-log",
        "reduce-cost",
    },
}

FORBIDDEN_PUBLIC_TERMS = (
    "placeholder",
    "todo",
    "tbd",
    "migration",
    "project status",
    "content owner",
    "להשלמה",
    "טיוטה",
    "פרויקט תוכן",
    "נוצר על ידי בינה מלאכותית",
    "בתור מודל שפה",
)

PRICE_NUMBER_PATTERN = re.compile(
    r"(?:฿|₪|\$|\bTHB\b|\bUSD\b|באט|שקל(?:ים)?|ש[\"״]?ח)\s*[:=]?\s*\d"
    r"|\d[\d,.]*\s*(?:฿|₪|\$|\bTHB\b|\bUSD\b|באט|שקל(?:ים)?|ש[\"״]?ח)",
    re.IGNORECASE,
)


def reject_duplicate_keys(pairs: list[tuple[str, Any]]) -> dict[str, Any]:
    result: dict[str, Any] = {}
    for key, value in pairs:
        if key in result:
            raise ValueError(f"duplicate JSON key: {key}")
        result[key] = value
    return result


def read_contract(path: Path) -> dict[str, Any]:
    value = json.loads(
        path.read_text(encoding="utf-8"),
        object_pairs_hook=reject_duplicate_keys,
        parse_constant=lambda token: (_ for _ in ()).throw(
            ValueError(f"non-finite JSON number: {token}")
        ),
    )
    if not isinstance(value, dict):
        raise AssertionError(f"JSON root must be an object: {path}")
    return value


def strings(value: Any) -> Iterable[str]:
    if isinstance(value, str):
        yield value
    elif isinstance(value, dict):
        for child in value.values():
            yield from strings(child)
    elif isinstance(value, list):
        for child in value:
            yield from strings(child)


def normalized_keyword(value: str) -> str:
    return " ".join(value.lower().split())


class QueuedExpiredContentTest(unittest.TestCase):
    maxDiff = None

    @classmethod
    def setUpClass(cls) -> None:
        cls.paths = [QUEUE / name for name in EXPECTED]
        cls.contracts = {path.name: read_contract(path) for path in cls.paths}

    def test_exact_files_ids_paths_owners_and_kinds(self) -> None:
        self.assertEqual(set(EXPECTED), set(self.contracts))
        for name, expected in EXPECTED.items():
            contract = self.contracts[name]
            with self.subTest(name=name):
                self.assertEqual("1.0.0", contract["contract_version"])
                self.assertEqual("he-IL", contract["language"])
                self.assertEqual("rtl", contract["direction"])
                self.assertEqual("2026-08-10", contract["checked_on"])
                self.assertEqual(expected["kind"], contract["content_kind"])
                self.assertEqual(expected["post_id"], contract["wordpress"]["post_id"])
                self.assertEqual("post", contract["wordpress"]["post_type"])
                self.assertEqual(expected["path"], contract["wordpress"]["protected_path"])
                self.assertEqual(expected["path"], contract["wordpress"]["canonical_path"])
                self.assertEqual(expected["owner_id"], contract["ownership"]["owner_id"])
                self.assertEqual(
                    expected["parent_owner_id"],
                    contract["hierarchy"]["parent_owner_id"],
                )

    def test_future_urls_are_strictly_evidence_gated(self) -> None:
        for name, contract in self.contracts.items():
            future = contract["wordpress"]["future_clean_path"]
            with self.subTest(name=name):
                self.assertEqual("evidence_gated", future["status"])
                self.assertTrue(future["path"].startswith("/"))
                self.assertTrue(future["path"].endswith("/"))
                self.assertNotEqual(
                    contract["wordpress"]["protected_path"], future["path"]
                )
                self.assertGreaterEqual(len(future["requirements"]), 5)
                requirements = " ".join(future["requirements"])
                self.assertIn("301", requirements)
                self.assertIn("canonical", requirements)
                self.assertIn("אינדוקס", requirements)

    def test_canonical_bindings_match_the_existing_seo_registry(self) -> None:
        registry = read_contract(SEO_REGISTRY_PATH)
        owners = {owner["owner_id"]: owner for owner in registry["intent_owners"]}
        route_by_owner = {
            route["assignment"]["owner_id"]: route
            for route in registry["routes"]
            if route["assignment"]["kind"] == "canonical_owner"
        }
        for name, expected in EXPECTED.items():
            contract = self.contracts[name]
            owner = owners[expected["owner_id"]]
            route = route_by_owner[expected["owner_id"]]
            with self.subTest(name=name):
                self.assertEqual(expected["path"], owner["canonical_url"])
                self.assertEqual(expected["parent_owner_id"], owner["parent_owner_id"])
                self.assertEqual(expected["path"], route["url"])
                self.assertEqual("live", route["lifecycle"])
                self.assertEqual("index", route["indexing_policy"])
                self.assertEqual(
                    contract["ownership"]["primary_keyword"],
                    owner["primary_keyword"],
                )

    def test_keyword_led_titles_and_mobile_meta(self) -> None:
        for name, contract in self.contracts.items():
            primary = normalized_keyword(contract["ownership"]["primary_keyword"])
            seo = contract["seo"]
            with self.subTest(name=name):
                self.assertTrue(normalized_keyword(seo["title"]).startswith(primary))
                self.assertTrue(normalized_keyword(seo["h1"]).startswith(primary))
                self.assertGreaterEqual(len(seo["meta_description"]), 100)
                self.assertLessEqual(len(seo["meta_description"]), 180)
                self.assertGreaterEqual(len(seo["cannibalization_boundaries"]), 4)

        self.assertNotIn(
            "2022",
            json.dumps(
                self.contracts["post-136-thailand-rainy-day-activities.json"]["seo"],
                ensure_ascii=False,
            ),
        )
        self.assertNotIn(
            "2025",
            json.dumps(
                self.contracts["post-732-thailand-family-holiday-costs.json"]["seo"],
                ensure_ascii=False,
            ),
        )

    def test_hierarchy_breadcrumbs_and_parent_links_are_complete(self) -> None:
        for name, expected in EXPECTED.items():
            contract = self.contracts[name]
            hierarchy = contract["hierarchy"]
            breadcrumbs = hierarchy["breadcrumbs"]
            links = contract["internal_links"]
            with self.subTest(name=name):
                self.assertEqual("home", hierarchy["owner_path"][0])
                self.assertEqual(expected["owner_id"], hierarchy["owner_path"][-1])
                self.assertIn(expected["parent_owner_id"], hierarchy["owner_path"])
                self.assertEqual(expected["owner_id"], breadcrumbs[-1]["owner_id"])
                self.assertEqual(expected["path"], breadcrumbs[-1]["path"])
                self.assertEqual(
                    expected["parent_owner_id"], breadcrumbs[-2]["owner_id"]
                )
                self.assertEqual(
                    1,
                    sum(
                        link["target_owner_id"] == expected["parent_owner_id"]
                        and link["relationship"] == "parent_hub"
                        for link in links
                    ),
                )
                targets = [link["target_owner_id"] for link in links]
                self.assertEqual(len(targets), len(set(targets)))
                for link in links:
                    self.assertTrue(link["path"].startswith("/"))
                    self.assertTrue(link["anchor_text"].strip())

    def test_publishable_copy_is_deep_and_section_ids_are_unique(self) -> None:
        for name, expected in EXPECTED.items():
            contract = self.contracts[name]
            content = contract[expected["content_key"]]
            sections = content["sections"]
            public_words = sum(len(value.split()) for value in strings(content))
            with self.subTest(name=name):
                self.assertGreaterEqual(len(content["lead"]), 2)
                self.assertGreaterEqual(len(sections), expected["minimum_sections"])
                self.assertGreaterEqual(public_words, expected["minimum_words"])
                section_ids = [section["section_id"] for section in sections]
                self.assertEqual(len(section_ids), len(set(section_ids)))
                for section in sections:
                    self.assertTrue(section["heading"].strip())
                    self.assertGreaterEqual(len(section["body"]), 2)
                    self.assertTrue(all(paragraph.strip() for paragraph in section["body"]))
                self.assertGreaterEqual(len(content["faq"]), 4)
                self.assertTrue(
                    all(item["question"].strip() and item["answer"].strip() for item in content["faq"])
                )

    def test_sources_are_dated_unique_and_not_from_the_future(self) -> None:
        for name, expected in EXPECTED.items():
            contract = self.contracts[name]
            checked = date.fromisoformat(contract["checked_on"])
            sources = contract["source_catalog"]
            source_ids = [source["source_id"] for source in sources]
            with self.subTest(name=name):
                self.assertGreaterEqual(len(sources), expected["minimum_sources"])
                self.assertEqual(len(source_ids), len(set(source_ids)))
                for source in sources:
                    self.assertTrue(source["url"].startswith("https://"))
                    self.assertEqual(contract["checked_on"], source["checked_on"])
                    self.assertTrue(source["publisher"].strip())
                    self.assertTrue(source["supports"].strip())
                    for field in ("published_on", "source_updated_on"):
                        if field in source:
                            self.assertLessEqual(date.fromisoformat(source[field]), checked)

    def test_measurement_gates_prevent_unsourced_numeric_prices(self) -> None:
        for name, contract in self.contracts.items():
            requirements = contract["measurement_requirements"]
            fields = [requirement["field"] for requirement in requirements]
            content_key = EXPECTED[name]["content_key"]
            public_content = contract[content_key]
            if name == "post-732-thailand-family-holiday-costs.json":
                public_content = dict(public_content)
                self.assertIn("budget_examples", public_content)
                public_content.pop("budget_examples")
            public_text = "\n".join(strings(public_content))
            with self.subTest(name=name):
                self.assertGreaterEqual(len(requirements), 3)
                self.assertEqual(len(fields), len(set(fields)))
                self.assertIsNone(PRICE_NUMBER_PATTERN.search(public_text))
                for requirement in requirements:
                    self.assertIn(
                        requirement["status"],
                        {
                            "required_before_price_publication",
                            "required_before_numeric_publication",
                            "required_before_schedule_publication",
                            "required_before_each_refresh",
                            "required_before_shekel_publication",
                            "required_before_transaction_price_publication",
                            "required_before_appraisal_publication",
                            "required_before_cost_calculation",
                            "required_before_project_cost_publication",
                            "live_lookup_required",
                            "source_dated_observation_refresh_required",
                            "source_dated_planning_component_refresh_required",
                            "source_dated_cross_rate_refresh_required",
                        },
                    )
                    self.assertGreaterEqual(requirement["minimum_observations"], 1)
                    self.assertGreaterEqual(requirement["refresh_days"], 1)

    def test_named_factual_sections_use_resolvable_sources(self) -> None:
        for name, expected_section_ids in FACTUAL_SECTION_IDS.items():
            contract = self.contracts[name]
            known_source_ids = {
                source["source_id"] for source in contract["source_catalog"]
            }
            sections = {
                section["section_id"]: section
                for section in contract[EXPECTED[name]["content_key"]]["sections"]
            }
            with self.subTest(name=name):
                self.assertTrue(expected_section_ids.issubset(sections))
                for section_id in expected_section_ids:
                    bound_source_ids = sections[section_id].get("source_ids", [])
                    self.assertTrue(bound_source_ids, section_id)
                    self.assertEqual(
                        len(bound_source_ids), len(set(bound_source_ids)), section_id
                    )
                    self.assertTrue(
                        set(bound_source_ids).issubset(known_source_ids), section_id
                    )
                for section in sections.values():
                    if "source_ids" in section:
                        self.assertTrue(
                            set(section["source_ids"]).issubset(known_source_ids),
                            section["section_id"],
                        )

    def test_hotel_and_place_entities_are_map_ready(self) -> None:
        entity_groups = (
            (
                "post-17-koh-samui-new-hotels-2022.json",
                "hotel_entities",
            ),
            (
                "post-136-thailand-rainy-day-activities.json",
                "place_entities",
            ),
        )
        all_entity_ids: list[str] = []
        for name, entity_key in entity_groups:
            contract = self.contracts[name]
            known_source_ids = {
                source["source_id"] for source in contract["source_catalog"]
            }
            canonical_path = contract["wordpress"]["canonical_path"]
            entities = contract[entity_key]
            with self.subTest(name=name, entity_key=entity_key):
                self.assertTrue(entities)
                for entity in entities:
                    entity_id = entity["entity_id"]
                    all_entity_ids.append(entity_id)
                    self.assertTrue(entity_id.strip())
                    self.assertGreaterEqual(len(entity["address"].strip()), 10)
                    self.assertTrue(
                        entity["province_entity_id"].startswith("geo:th:province:")
                    )
                    self.assertTrue(
                        entity["canonical_page_url"].startswith(canonical_path)
                    )
                    self.assertIn("#", entity["canonical_page_url"])
                    self.assertEqual("map_record", entity["map_state"])
                    coordinates = entity["coordinates"]
                    for axis in ("lat", "lng"):
                        self.assertIsInstance(coordinates[axis], (int, float))
                        self.assertNotIsInstance(coordinates[axis], bool)
                    self.assertGreaterEqual(coordinates["lat"], 5.0)
                    self.assertLessEqual(coordinates["lat"], 21.0)
                    self.assertGreaterEqual(coordinates["lng"], 97.0)
                    self.assertLessEqual(coordinates["lng"], 106.0)
                    self.assertTrue(entity["source_ids"])
                    self.assertEqual(
                        len(entity["source_ids"]), len(set(entity["source_ids"]))
                    )
                    self.assertTrue(
                        set(entity["source_ids"]).issubset(known_source_ids)
                    )
        self.assertEqual(len(all_entity_ids), len(set(all_entity_ids)))

    def test_hotel_entities_are_exact_and_source_bound(self) -> None:
        contract = self.contracts["post-17-koh-samui-new-hotels-2022.json"]
        source_ids = {source["source_id"] for source in contract["source_catalog"]}
        entities = contract["hotel_entities"]
        self.assertEqual(4, len(entities))
        self.assertEqual(
            {
                "hotel:th:koh-samui:kimpton-kitalay",
                "hotel:th:koh-samui:avani-chaweng",
                "hotel:th:koh-samui:holiday-inn-bophut",
                "hotel:th:koh-samui:outrigger-lamai",
            },
            {entity["entity_id"] for entity in entities},
        )
        for entity in entities:
            self.assertGreater(entity["room_count"], 0)
            self.assertGreaterEqual(len(entity["source_ids"]), 2)
            self.assertTrue(set(entity["source_ids"]).issubset(source_ids))

    def test_family_cost_amounts_are_source_bound_and_reproducible(self) -> None:
        contract = self.contracts["post-732-thailand-family-holiday-costs.json"]
        budget = contract["public_content"]["budget_examples"]
        sources = {
            source["source_id"]: source for source in contract["source_catalog"]
        }
        self.assertEqual(
            {
                "heading",
                "observed_at",
                "method_note",
                "route",
                "family_profiles",
                "adult_equivalent_method",
                "evidence_binding",
                "season_windows",
                "observed_price_samples",
                "planning_rate_cards",
                "calculation",
                "currency_snapshot",
                "ground_estimate_matrix",
                "international_flight_snapshot",
                "how_to_read",
            },
            set(budget),
        )

        def walked_values(value: Any, path: tuple[Any, ...] = ()) -> Iterable[tuple[tuple[Any, ...], Any]]:
            if isinstance(value, dict):
                for key, child in value.items():
                    yield from walked_values(child, (*path, key))
            elif isinstance(value, list):
                for index, child in enumerate(value):
                    yield from walked_values(child, (*path, index))
            else:
                yield path, value

        def source_bindings(
            value: Any, path: tuple[Any, ...] = ()
        ) -> Iterable[tuple[tuple[Any, ...], list[str]]]:
            if isinstance(value, dict):
                if "source_ids" in value:
                    yield (*path, "source_ids"), value["source_ids"]
                for key, child in value.items():
                    yield from source_bindings(child, (*path, key))
            elif isinstance(value, list):
                for index, child in enumerate(value):
                    yield from source_bindings(child, (*path, index))

        for path, bound_source_ids in source_bindings(budget):
            self.assertTrue(bound_source_ids, path)
            self.assertEqual(len(bound_source_ids), len(set(bound_source_ids)), path)
            self.assertTrue(set(bound_source_ids).issubset(sources), path)

        price_string_paths = [
            path
            for path, value in walked_values(budget)
            if isinstance(value, str) and PRICE_NUMBER_PATTERN.search(value)
        ]
        self.assertEqual([("calculation", "formula")], price_string_paths)
        money_key_pattern = re.compile(
            r"(?:^|_)(?:amount|price|thb|ils|usd|fare|surcharge|credit|rate)(?:_|$)",
            re.IGNORECASE,
        )
        allowed_numeric_evidence_roots = {
            "observed_price_samples",
            "planning_rate_cards",
            "calculation",
            "currency_snapshot",
            "ground_estimate_matrix",
            "international_flight_snapshot",
        }
        for path, value in walked_values(budget):
            if (
                path
                and isinstance(value, (int, float))
                and not isinstance(value, bool)
                and money_key_pattern.search(str(path[-1]))
            ):
                self.assertIn(path[0], allowed_numeric_evidence_roots, path)

        profiles = {
            profile["family_profile_id"]: profile
            for profile in budget["family_profiles"]
        }
        self.assertEqual(
            {
                "family_2a_1c": ([5], 3, 2.55),
                "family_2a_2c": ([5, 10], 4, 3.25),
                "family_2a_3c": ([4, 9, 14], 5, 4.15),
            },
            {
                profile_id: (
                    profile["child_ages"],
                    profile["occupied_airline_seats"],
                    profile["adult_equivalent_units"],
                )
                for profile_id, profile in profiles.items()
            },
        )
        self.assertTrue(all(profile["adults"] == 2 for profile in profiles.values()))

        night_splits = {
            split["nights"]: split for split in budget["route"]["night_splits"]
        }
        self.assertEqual({10, 14, 21}, set(night_splits))
        for nights, split in night_splits.items():
            self.assertEqual(
                nights, split["bangkok"] + split["chiang_mai"] + split["phuket"]
            )

        season_windows = {
            season["season_id"]: season for season in budget["season_windows"]
        }
        self.assertEqual({"low", "shoulder", "peak"}, set(season_windows))
        self.assertEqual(
            {"low": 1.0, "shoulder": 1.15, "peak": 1.3},
            {
                season_id: season["planning_factor"]
                for season_id, season in season_windows.items()
            },
        )
        for season in season_windows.values():
            self.assertEqual(
                "route_wide_planning_factor_not_market_average",
                season["value_type"],
            )
            self.assertTrue(set(season["source_ids"]).issubset(sources))
            self.assertEqual(
                {10, 14, 21},
                {sample["nights"] for sample in season["sample_travel_dates"]},
            )
            for sample in season["sample_travel_dates"]:
                self.assertEqual(
                    sample["nights"],
                    (
                        date.fromisoformat(sample["end"])
                        - date.fromisoformat(sample["start"])
                    ).days,
                )

        observations = {
            observation["observation_id"]: observation
            for observation in budget["observed_price_samples"]
        }
        self.assertEqual(
            {
                "obs:etihad:tlv-bkk:2026-10-17",
                "obs:thai:bkk-cnx:2026-09-01",
                "obs:thai:bkk-hkt:2026-09-06",
                "obs:ibis-silom:direct-deal:2026-09-01",
                "obs:kantary-hills:2br-low:2026-09-01",
                "obs:kantary-hills:2br-high:2026-11-10",
                "obs:kantary-hills:2br-peak-surcharge:2026-12-24",
                "obs:raya-heritage:one-price:2026-10-01",
                "obs:mk:family-set:2026-09-01",
                "obs:grand-palace:foreigner-ticket:2026-09-01",
                "obs:phuket-aquarium:family-entry:2026-09-01",
                "obs:bts:max-adult-fare:2026-09-01",
            },
            set(observations),
        )
        for observation in observations.values():
            source_id = observation["source_id"]
            self.assertIn(source_id, sources)
            self.assertEqual(sources[source_id]["url"], observation["source_url"])
            self.assertEqual(budget["observed_at"], observation["observed_at"])
            self.assertLessEqual(
                date.fromisoformat(observation["travel_dates"]["start"]),
                date.fromisoformat(observation["travel_dates"]["end"]),
            )
            self.assertGreaterEqual(observation["family_profile"]["adults"], 0)
            self.assertIsInstance(observation["family_profile"]["child_ages"], list)
            self.assertGreaterEqual(observation["sample_size"], 1)
            self.assertTrue(observation["sample_unit"].strip())
            self.assertGreater(observation["price"]["amount"], 0)
            self.assertIn(observation["price"]["currency"], {"THB", "USD"})
            self.assertTrue(observation["price"]["basis"].strip())
            for field in (
                "supplier",
                "product",
                "inclusions",
                "taxes",
                "cancellation",
                "availability",
            ):
                self.assertTrue(observation[field].strip(), observation["observation_id"])

        evidence_binding = budget["evidence_binding"]
        evidence_binding_id = evidence_binding["evidence_binding_id"]
        self.assertEqual("family-cost-evidence-2026-08-10", evidence_binding_id)
        self.assertEqual(budget["observed_at"], evidence_binding["observed_at"])
        self.assertEqual(len(observations), evidence_binding["sample_size"])
        self.assertEqual(set(observations), set(evidence_binding["observation_ids"]))
        self.assertTrue(set(evidence_binding["source_ids"]).issubset(sources))
        self.assertEqual({"USD", "THB", "ILS"}, set(evidence_binding["currencies"]))
        for field in (
            "family_profile_binding",
            "travel_date_binding",
            "inclusions",
            "taxes",
            "cancellation",
        ):
            self.assertTrue(evidence_binding[field].strip())

        rate_cards = {
            rate_card["style_id"]: rate_card
            for rate_card in budget["planning_rate_cards"]
        }
        self.assertEqual({"value", "comfortable", "premium"}, set(rate_cards))
        component_keys = {
            "lodging_thb_per_family_night",
            "food_thb_per_adult_equivalent_day",
            "activities_thb_per_adult_equivalent_day",
            "local_transport_thb_per_family_day",
            "connectivity_laundry_thb_per_trip",
        }
        for rate_card in rate_cards.values():
            self.assertEqual(
                {
                    "style_id",
                    "label",
                    "value_type",
                    "evidence_binding_id",
                    "components",
                    "assumption_basis",
                    "source_ids",
                },
                set(rate_card),
            )
            self.assertEqual(
                "planning_assumption_not_market_quote", rate_card["value_type"]
            )
            self.assertEqual(evidence_binding_id, rate_card["evidence_binding_id"])
            self.assertEqual(component_keys, set(rate_card["components"]))
            self.assertTrue(rate_card["assumption_basis"].strip())
            self.assertTrue(rate_card["source_ids"])
            self.assertTrue(set(rate_card["source_ids"]).issubset(sources))
            components = rate_card["components"]
            for field in (
                "lodging_thb_per_family_night",
                "local_transport_thb_per_family_day",
                "connectivity_laundry_thb_per_trip",
            ):
                self.assertEqual(set(profiles), set(components[field]))
                self.assertTrue(all(value > 0 for value in components[field].values()))
            self.assertGreater(components["food_thb_per_adult_equivalent_day"], 0)
            self.assertGreater(
                components["activities_thb_per_adult_equivalent_day"], 0
            )

        calculation = budget["calculation"]
        self.assertEqual(
            "transparent_planning_estimate", calculation["value_type"]
        )
        self.assertEqual(evidence_binding_id, calculation["evidence_binding_id"])
        self.assertTrue(set(calculation["source_ids"]).issubset(sources))
        self.assertNotIn("source:etihad:tlv-bkk", calculation["source_ids"])
        self.assertEqual(9460, calculation["domestic_roundtrip_fare_allowance_thb_per_occupied_seat"])
        self.assertEqual(0.1, calculation["contingency_rate"])

        fx = budget["currency_snapshot"]
        self.assertEqual(evidence_binding_id, fx["evidence_binding_id"])
        self.assertEqual("2026-08-07", fx["rate_date"])
        self.assertEqual(budget["observed_at"], fx["observed_at"])
        self.assertEqual(
            {"source:bot:daily-fx", "source:boi:usd-ils-2026-08-07"},
            set(fx["source_ids"]),
        )
        self.assertAlmostEqual(
            fx["boi_ils_per_usd_representative_rate"]
            / fx["bot_thb_per_usd_mid_rate"],
            fx["calculated_ils_per_thb"],
            places=14,
        )
        self.assertAlmostEqual(
            1 / fx["calculated_ils_per_thb"],
            fx["calculated_thb_per_ils"],
            places=12,
        )
        self.assertFalse(fx["provider_fee_included"])

        matrix = {
            (row["family_profile_id"], row["nights"]): row
            for row in budget["ground_estimate_matrix"]
        }
        self.assertEqual(
            {(profile_id, nights) for profile_id in profiles for nights in (10, 14, 21)},
            set(matrix),
        )
        for (profile_id, nights), row in matrix.items():
            self.assertEqual(
                {
                    "family_profile_id",
                    "nights",
                    "calculation_id",
                    "evidence_binding_id",
                    "totals",
                },
                set(row),
            )
            self.assertEqual(calculation["calculation_id"], row["calculation_id"])
            self.assertEqual(evidence_binding_id, row["evidence_binding_id"])
            self.assertEqual(set(rate_cards), set(row["totals"]))
            profile = profiles[profile_id]
            for style_id, rate_card in rate_cards.items():
                components = rate_card["components"]
                self.assertEqual(set(season_windows), set(row["totals"][style_id]))
                for season_id, season in season_windows.items():
                    subtotal = (
                        components["lodging_thb_per_family_night"][profile_id]
                        * nights
                        * season["planning_factor"]
                        + profile["adult_equivalent_units"]
                        * (
                            components["food_thb_per_adult_equivalent_day"]
                            + components["activities_thb_per_adult_equivalent_day"]
                        )
                        * nights
                        + components["local_transport_thb_per_family_day"][profile_id]
                        * nights
                        + calculation[
                            "domestic_roundtrip_fare_allowance_thb_per_occupied_seat"
                        ]
                        * profile["occupied_airline_seats"]
                        + components["connectivity_laundry_thb_per_trip"][profile_id]
                    )
                    expected_thb = (
                        round(
                            subtotal
                            * (1 + calculation["contingency_rate"])
                            / 100
                        )
                        * 100
                    )
                    total = row["totals"][style_id][season_id]
                    self.assertEqual({"thb", "ils"}, set(total))
                    self.assertEqual(expected_thb, total["thb"])
                    self.assertEqual(
                        round(expected_thb * fx["calculated_ils_per_thb"]),
                        total["ils"],
                    )

        international = budget["international_flight_snapshot"]
        self.assertEqual(
            {
                "evidence_binding_id",
                "observation_id",
                "travel_dates",
                "fare_per_occupied_seat",
                "family_totals",
                "calculation_note",
                "source_ids",
            },
            set(international),
        )
        self.assertEqual(evidence_binding_id, international["evidence_binding_id"])
        etihad = observations[international["observation_id"]]
        self.assertEqual(
            {
                "source:etihad:tlv-bkk",
                "source:bot:daily-fx",
                "source:boi:usd-ils-2026-08-07",
            },
            set(international["source_ids"]),
        )
        self.assertEqual(etihad["travel_dates"], international["travel_dates"])
        per_seat = international["fare_per_occupied_seat"]
        self.assertEqual(etihad["price"]["amount"], per_seat["usd"])
        self.assertEqual(
            round(per_seat["usd"] * fx["bot_thb_per_usd_mid_rate"]),
            per_seat["thb"],
        )
        self.assertEqual(
            round(per_seat["usd"] * fx["boi_ils_per_usd_representative_rate"]),
            per_seat["ils"],
        )
        flight_totals = {
            item["family_profile_id"]: item
            for item in international["family_totals"]
        }
        self.assertEqual(set(profiles), set(flight_totals))
        for profile_id, total in flight_totals.items():
            seats = profiles[profile_id]["occupied_airline_seats"]
            self.assertEqual(seats, total["occupied_seats"])
            self.assertEqual(per_seat["usd"] * seats, total["usd"])
            self.assertEqual(
                round(per_seat["usd"] * seats * fx["bot_thb_per_usd_mid_rate"]),
                total["thb"],
            )
            self.assertEqual(
                round(
                    per_seat["usd"]
                    * seats
                    * fx["boi_ils_per_usd_representative_rate"]
                ),
                total["ils"],
            )

    def test_property_plan_cannot_release_numeric_market_claims_yet(self) -> None:
        contract = self.contracts["post-810-thailand-property-prices-plan.json"]
        gate = contract["release_gate"]
        observation = contract["price_observation_contract"]
        required = set(observation["required_fields"])
        self.assertEqual(
            "measurement_required_before_numeric_market_claims", gate["status"]
        )
        self.assertTrue(gate["explanatory_copy_ready"])
        self.assertFalse(gate["numeric_market_sections_ready"])
        self.assertGreaterEqual(len(gate["requirements"]), 6)
        self.assertTrue(
            {
                "source_url",
                "observed_at",
                "price_basis",
                "currency",
                "price_total",
                "market_area_id",
                "property_type",
                "floor_area_sqm",
                "price_per_sqm_when_computable",
                "duplicate_key",
            }.issubset(required)
        )
        self.assertGreaterEqual(len(observation["aggregation_rules"]), 7)
        asking = next(
            item
            for item in contract["measurement_requirements"]
            if item["field"] == "area_asking_price_band"
        )
        self.assertGreaterEqual(asking["minimum_observations"], 12)

    def test_schema_policies_fail_closed(self) -> None:
        for name, contract in self.contracts.items():
            policy = contract["schema_policy"]
            with self.subTest(name=name):
                self.assertIn("Article", policy["allowed"])
                self.assertIn("BreadcrumbList", policy["allowed"])
                self.assertGreaterEqual(len(policy["conditional"]), 1)
                self.assertGreaterEqual(len(policy["forbidden"]), 4)
                forbidden = " ".join(policy["forbidden"])
                self.assertIn("Offer", forbidden)
                self.assertIn("AggregateRating", forbidden)

    def test_public_copy_has_no_internal_language_or_long_dashes(self) -> None:
        for name, expected in EXPECTED.items():
            contract = self.contracts[name]
            public_text = "\n".join(strings(contract[expected["content_key"]])).lower()
            with self.subTest(name=name):
                for term in FORBIDDEN_PUBLIC_TERMS:
                    self.assertNotIn(term, public_text)
                self.assertNotIn(chr(0x2013), public_text)
                self.assertNotIn(chr(0x2014), public_text)
                self.assertGreaterEqual(len(contract["forbidden_stale_claims"]), 6)

    def test_public_copy_avoids_sitewide_presentation_phrases(self) -> None:
        runtime_test = (ROOT / "tests" / "run.php").read_text(encoding="utf-8")
        start = runtime_test.index("$presentation_phrases = array(")
        end = runtime_test.index(");", start)
        phrases = re.findall(r"'([^']*)'", runtime_test[start:end])
        self.assertEqual(50, len(phrases))
        for name, expected in EXPECTED.items():
            contract = self.contracts[name]
            public_text = "\n".join(strings(contract[expected["content_key"]]))
            with self.subTest(name=name):
                for phrase in phrases:
                    self.assertNotIn(phrase, public_text)

    def test_all_contract_and_test_files_exclude_forbidden_long_dashes(self) -> None:
        for path in [*self.paths, Path(__file__)]:
            payload = path.read_text(encoding="utf-8")
            with self.subTest(path=path.name):
                self.assertNotIn(chr(0x2013), payload)
                self.assertNotIn(chr(0x2014), payload)


if __name__ == "__main__":
    unittest.main(verbosity=2)
