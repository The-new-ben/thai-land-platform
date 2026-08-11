#!/usr/bin/env python3
"""Dependency-free contract tests for the Koh Phangan digital-island seed."""

from __future__ import annotations

import copy
import hashlib
import importlib.util
import json
import subprocess
import sys
import unittest
from collections import Counter
from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]
SOURCE = ROOT / "data" / "digital-islands" / "koh-phangan.json"
SCHEMA = ROOT / "data" / "digital-islands" / "island-world.schema.json"
REGISTRY = ROOT / "resources" / "digital-islands" / "registry.php"
MANIFEST = ROOT / "resources" / "digital-islands" / "manifest.json"
BUILDER = ROOT / "scripts" / "build_digital_island_registry.py"
NOTICES = ROOT / "THIRD-PARTY-DATA-NOTICES.md"

spec = importlib.util.spec_from_file_location("digital_island_builder", BUILDER)
if spec is None or spec.loader is None:
    raise RuntimeError("Digital-island builder cannot be imported")
builder = importlib.util.module_from_spec(spec)
sys.modules[spec.name] = builder
spec.loader.exec_module(builder)


class DigitalIslandDataTest(unittest.TestCase):
    @classmethod
    def setUpClass(cls) -> None:
        cls.data = json.loads(SOURCE.read_text(encoding="utf-8"))
        cls.schema = json.loads(SCHEMA.read_text(encoding="utf-8"))
        cls.manifest = json.loads(MANIFEST.read_text(encoding="utf-8"))
        cls.sources = {item["source_id"]: item for item in cls.data["source_catalog"]}
        cls.layers = {item["layer_id"]: item for item in cls.data["layer_catalog"]}
        cls.entities = {item["entity_id"]: item for item in cls.data["entities"]}

    def test_builder_is_deterministic_and_current(self) -> None:
        first = builder.build()
        second = builder.build()
        self.assertEqual(first, second)
        self.assertEqual(REGISTRY.read_bytes(), first.registry_php)
        self.assertEqual(MANIFEST.read_bytes(), first.manifest_json)
        result = subprocess.run(
            [sys.executable, str(BUILDER), "--check"],
            cwd=ROOT,
            capture_output=True,
            text=True,
            check=False,
        )
        self.assertEqual(0, result.returncode, result.stderr)

    def test_schema_rejects_unknown_public_contract_fields(self) -> None:
        mutated = copy.deepcopy(self.data)
        mutated["canonical"]["mystery"] = True
        with self.assertRaises(builder.BuildError):
            builder.schema_validate(mutated, self.schema)

    def test_manifest_binds_exact_artifact(self) -> None:
        artifact = self.manifest["artifacts"]["resources/digital-islands/registry.php"]
        payload = REGISTRY.read_bytes()
        self.assertEqual(len(payload), artifact["bytes"])
        self.assertEqual(hashlib.sha256(payload).hexdigest(), artifact["sha256"])
        self.assertEqual(hashlib.sha256(SCHEMA.read_bytes()).hexdigest(), self.manifest["schema_sha256"])

    def test_live_projection_is_exact_and_manifest_bound(self) -> None:
        self.assertEqual("live", self.data["publication_state"])
        self.assertEqual("index", self.data["canonical"]["indexing_policy"])
        self.assertEqual(38, self.manifest["counts"]["sources"])
        self.assertEqual(49, self.manifest["counts"]["entities"])
        self.assertEqual(49, self.manifest["counts"]["canary_map_entities"])
        self.assertEqual(49, self.manifest["counts"]["public_map_entities"])

    def test_owner_url_and_breadcrumb_are_exact(self) -> None:
        canonical = self.data["canonical"]
        self.assertEqual("koh-phangan-map", canonical["owner_id"])
        self.assertEqual("/מפת-קופנגן/", canonical["canonical_path"])
        self.assertEqual(["home", "thailand-map", "koh-phangan-map"], canonical["breadcrumb_owner_ids"])

    def test_two_renderer_contract_is_shared(self) -> None:
        renderers = {item["renderer_id"]: item for item in self.data["renderer_contract"]}
        self.assertEqual({"immersive_3d", "practical_2d"}, set(renderers))
        self.assertTrue(all(item["library"] == "MapLibre GL JS" for item in renderers.values()))
        self.assertTrue(all(item["library_version"] == "5.18.0" for item in renderers.values()))
        self.assertTrue(all(item["delivery"] == "self_hosted_pinned" for item in renderers.values()))
        self.assertEqual(
            ["camera_presets", "entity_focus", "globe", "hillshade", "terrain", "building_extrusion", "satellite_imagery"],
            renderers["immersive_3d"]["capabilities"],
        )
        self.assertEqual(
            ["camera_presets", "entity_focus", "filters", "keyboard_list", "vector_basemap"],
            renderers["practical_2d"]["capabilities"],
        )
        self.assertEqual(
            ["webgl_unavailable", "data_saver", "user_choice"],
            renderers["immersive_3d"]["fallback_triggers"],
        )
        self.assertEqual([], renderers["practical_2d"]["fallback_triggers"])
        for item in renderers.values():
            claims = " ".join(item["capabilities"])
            for forbidden in ("measurement", "offline", "parcel", "buildability", "photorealism", "walking"):
                self.assertNotIn(forbidden, claims)
        self.assertIn("satellite_imagery", renderers["immersive_3d"]["capabilities"])
        self.assertNotIn("satellite_imagery", renderers["practical_2d"]["capabilities"])
        self.assertIn(builder.SATELLITE_SOURCE_ID, renderers["immersive_3d"]["source_ids"])
        self.assertNotIn(builder.SATELLITE_SOURCE_ID, renderers["practical_2d"]["source_ids"])
        self.assertNotIn("source:cesiumjs.docs", self.sources)
        builder.validate_renderer_contract(self.data["renderer_contract"], self.sources)

    def test_renderer_overclaim_fails_closed(self) -> None:
        mutated = copy.deepcopy(self.data["renderer_contract"])
        mutated[0]["capabilities"].append("measurement")
        with self.assertRaises(builder.BuildError):
            builder.validate_renderer_contract(mutated, self.sources)

    def test_local_map_attributions_and_licenses_are_preserved(self) -> None:
        notices = NOTICES.read_text(encoding="utf-8")
        self.assertIn("Protomaps © OpenStreetMap contributors", notices)
        self.assertIn(
            "Mapzen Terrain Tiles; SRTM and GMTED2010 data courtesy of the U.S. Geological",
            notices,
        )
        self.assertIn("ETOPO1 courtesy of NOAA/NCEI. Not for navigation.", notices)
        self.assertIn("ESA WorldCover 2021", notices)
        self.assertIn("CC BY 4.0", notices)
        self.assertIn("maplibre-gl/5.18.0/maplibre-gl.LICENSE.txt", notices)
        self.assertIn("pmtiles/4.5.0/pmtiles.LICENSE.txt", notices)

    def test_satellite_source_is_exact_historical_orientation_evidence(self) -> None:
        source = self.sources[builder.SATELLITE_SOURCE_ID]
        self.assertEqual(builder.EXPECTED_SATELLITE_SOURCE, source)
        imagery = source["imagery"]
        self.assertEqual("S2B_47PPL_20260326_0_L2A", imagery["item_id"])
        self.assertEqual("2026-03-26T03:55:36.171000Z", imagery["observed_at"])
        self.assertEqual(14.307985, imagery["tile_cloud_cover_percent"])
        self.assertEqual("source_tile_not_cropped_island", imagery["tile_cloud_metadata_scope"])
        self.assertEqual({"west": 99.92, "south": 9.63, "east": 100.12, "north": 9.84}, imagery["processed_bounds"])
        self.assertEqual("orientation_only", imagery["usage_scope"])
        self.assertEqual(
            ["not_current_evidence", "not_parcel_evidence", "not_title_evidence", "not_buildability_evidence"],
            imagery["limitations"],
        )
        notices = NOTICES.read_text(encoding="utf-8")
        self.assertIn("Contains modified Copernicus Sentinel data 2026", notices)
        self.assertIn("https://registry.opendata.aws/sentinel-2-l2a-cogs/", notices)
        self.assertIn("https://sentinels.copernicus.eu/documents/247904/690755/Sentinel_Data_Legal_Notice", notices)
        builder.validate_satellite_source(self.sources)

        mutated = copy.deepcopy(self.sources)
        mutated[builder.SATELLITE_SOURCE_ID]["imagery"]["tile_cloud_cover_percent"] = 1.0
        with self.assertRaises(builder.BuildError):
            builder.validate_satellite_source(mutated)

    def test_land_safety_policy_cannot_emit_a_verdict(self) -> None:
        policy = self.data["land_decision_policy"]
        self.assertFalse(policy["automatic_buildability_verdict"])
        self.assertFalse(policy["automatic_title_verdict"])
        self.assertEqual("external_official_lookup_only", policy["parcel_data_mode"])
        for required in ("parcel_reference_match", "seller_authority", "planning_classification", "building_permit"):
            self.assertIn(required, policy["required_dimensions"])

    def test_official_land_tools_are_source_bound_and_limited(self) -> None:
        tools = {item["tool_id"]: item for item in self.data["official_tools"]}
        self.assertEqual(
            {"lands-maps-parcel-lookup", "koh-phangan-land-office", "onep-environmental-rules"},
            set(tools),
        )
        dimensions = set(self.data["land_decision_policy"]["required_dimensions"])
        for tool in tools.values():
            source = self.sources[tool["source_id"]]
            self.assertEqual("current", source["access_state"])
            self.assertEqual(source["url"], tool["url"])
            self.assertTrue(set(tool["supports_dimensions"]).issubset(dimensions))
            self.assertTrue(tool["limitations_he"])
        self.assertFalse(self.data["land_decision_policy"]["automatic_buildability_verdict"])

    def test_fourteen_official_muban_are_preserved(self) -> None:
        settlements = [entity for entity in self.entities.values() if entity["entity_type"] == "settlement"]
        self.assertEqual(14, len(settlements))
        self.assertEqual(8, sum("geo:th:subdistrict:840501" in item["geo_ids"] for item in settlements))
        self.assertEqual(6, sum("geo:th:subdistrict:840502" in item["geo_ids"] for item in settlements))
        self.assertEqual(10, sum(item["coordinates"] is not None for item in settlements))

    def test_public_record_counts_are_exact(self) -> None:
        public_records = [entity for entity in self.entities.values() if entity["public_state"] == "map_only"]
        self.assertEqual(49, len(public_records))
        self.assertEqual(
            Counter(
                {
                    "banking": 2,
                    "education": 1,
                    "government": 9,
                    "health": 3,
                    "landmark": 7,
                    "postal": 1,
                    "property_project": 3,
                    "road": 4,
                    "settlement": 14,
                    "telecom": 1,
                    "transport": 2,
                    "utility": 2,
                }
            ),
            Counter(entity["entity_type"] for entity in public_records),
        )
        self.assertEqual(49, self.manifest["counts"]["canary_map_entities"])

    def test_entity_ids_and_normalized_identities_are_unique(self) -> None:
        self.assertEqual(len(self.data["entities"]), len(self.entities))
        identities = [builder.normalized_entity_identity(entity) for entity in self.entities.values()]
        self.assertEqual(len(identities), len(set(identities)))

    def test_public_source_contains_no_private_inventory(self) -> None:
        self.assertEqual(49, len(self.entities))
        self.assertNotIn("professional_service", {item["entity_type"] for item in self.entities.values()})
        self.assertNotIn("property_offer", {item["entity_type"] for item in self.entities.values()})
        self.assertNotIn("legal_overlay", {item["entity_type"] for item in self.entities.values()})
        self.assertTrue(all(item["public_state"] in {"map_only", "indexable"} for item in self.entities.values()))
        self.assertTrue(all(item["indexing_policy"] in {"map_only", "index"} for item in self.entities.values()))
        self.assertNotIn("unknown_private_only", {item["permitted_reuse"] for item in self.sources.values()})
        ais = self.entities["telecom:th:84:840501:ais-tourist-shop-koh-phangan"]
        self.assertEqual("map_only", ais["public_state"])
        self.assertIsNone(ais["coordinates"])

    def test_no_neighbor_island_or_off_island_provider_leakage(self) -> None:
        serialized = json.dumps(self.data["entities"], ensure_ascii=False).lower()
        for forbidden in ("koh tao", "ko tao", "koh samui", "ko samui"):
            self.assertNotIn(forbidden, serialized)

    def test_ko_tao_is_explicitly_outside_pilot(self) -> None:
        self.assertEqual(["geo:th:subdistrict:840503"], self.data["island"]["excluded_geo_ids"])
        serialized = json.dumps(self.data["entities"], ensure_ascii=False)
        self.assertNotIn("geo:th:subdistrict:840503", serialized)

    def test_project_seed_stays_safely_scoped(self) -> None:
        projects = [entity for entity in self.entities.values() if entity["entity_type"] == "property_project"]
        offers = [entity for entity in self.entities.values() if entity["entity_type"] == "property_offer"]
        self.assertEqual(3, len(projects))
        self.assertEqual(0, len(offers))
        self.assertTrue(all(item["public_state"] == "map_only" for item in projects))
        self.assertTrue(all(item["next_review_on"] == "2026-08-25" for item in projects))

    def test_every_live_fact_and_coordinate_has_reuse_eligible_evidence(self) -> None:
        for entity in self.entities.values():
            builder.validate_live_entity(entity, self.sources)
            if entity["coordinates"] is not None:
                self.assertTrue(
                    any(builder.public_geometry_source(self.sources[source_id]) for source_id in entity["coordinates"]["source_ids"])
                )
            for fact in entity["facts"]:
                if fact["public"]:
                    self.assertTrue(any(builder.public_fact_source(self.sources[source_id]) for source_id in fact["source_ids"]))

    def test_link_only_source_cannot_support_a_live_pin_or_fact(self) -> None:
        entity = copy.deepcopy(next(item for item in self.entities.values() if item["coordinates"] is not None))
        sources = copy.deepcopy(self.sources)
        for source_id in entity["coordinates"]["source_ids"]:
            sources[source_id]["permitted_reuse"] = "link_only"
            sources[source_id]["geometry_use"] = "orientation_only"
        with self.assertRaises(builder.BuildError):
            builder.validate_live_entity(entity, sources)

        entity = copy.deepcopy(next(item for item in self.entities.values() if any(fact["public"] for fact in item["facts"])))
        fact = next(fact for fact in entity["facts"] if fact["public"])
        sources = copy.deepcopy(self.sources)
        for source_id in fact["source_ids"]:
            sources[source_id]["permitted_reuse"] = "link_only"
        with self.assertRaises(builder.BuildError):
            builder.validate_live_entity(entity, sources)

    def test_no_attraction_entity_or_completeness_claim_exists(self) -> None:
        self.assertNotIn("attraction", {entity["entity_type"] for entity in self.entities.values()})
        serialized = SOURCE.read_text(encoding="utf-8").lower()
        self.assertNotIn("most complete", serialized)
        self.assertNotIn("complete map", serialized)

    def test_every_reference_resolves(self) -> None:
        for layer in self.layers.values():
            self.assertTrue(set(layer["source_ids"]).issubset(self.sources))
        for entity in self.entities.values():
            self.assertTrue(set(entity["source_ids"]).issubset(self.sources))
            self.assertTrue(set(entity["layer_ids"]).issubset(self.layers))
            if entity["coordinates"] is not None:
                self.assertTrue(set(entity["coordinates"]["source_ids"]).issubset(entity["source_ids"]))
            for fact in entity["facts"]:
                self.assertTrue(set(fact["source_ids"]).issubset(entity["source_ids"]))

    def test_wrong_island_coordinate_is_rejected(self) -> None:
        entity = copy.deepcopy(next(item for item in self.entities.values() if item["coordinates"] is not None))
        entity["coordinates"]["latitude"] = 18.7
        with self.assertRaises(builder.BuildError):
            builder.validate_entity(entity, self.sources, self.layers, self.data["island"])

    def test_accuracy_radius_above_class_cap_is_rejected(self) -> None:
        entity = copy.deepcopy(next(item for item in self.entities.values() if item["coordinates"] is not None))
        entity["coordinates"]["accuracy_class"] = "first_party_pin"
        entity["coordinates"]["accuracy_m"] = 101
        with self.assertRaises(builder.BuildError):
            builder.validate_entity(entity, self.sources, self.layers, self.data["island"])

    def test_retracted_evidence_cannot_support_map_entity(self) -> None:
        entity = copy.deepcopy(next(item for item in self.entities.values() if item["public_state"] == "map_only"))
        sources = copy.deepcopy(self.sources)
        for source_id in entity["source_ids"]:
            sources[source_id]["access_state"] = "retracted"
        with self.assertRaises(builder.BuildError):
            builder.validate_entity(entity, sources, self.layers, self.data["island"])

    def test_source_urls_require_safe_https_hosts(self) -> None:
        source = copy.deepcopy(next(iter(self.sources.values())))
        source["url"] = "https:///missing-host"
        with self.assertRaises(builder.BuildError):
            builder.validate_source(source)
        source["url"] = "https://user:secret@example.com/source"
        with self.assertRaises(builder.BuildError):
            builder.validate_source(source)

    def test_no_draft_body_secret_or_forbidden_dash(self) -> None:
        source_contract = SOURCE.read_text(encoding="utf-8") + SCHEMA.read_text(encoding="utf-8")
        implementation = source_contract + BUILDER.read_text(encoding="utf-8")
        self.assertNotRegex(implementation, "[\u2013\u2014\u200b]")
        for forbidden in ("post_content", "draft_body", "downloaded_body", "snapshot_body"):
            self.assertNotIn(f'"{forbidden}"', source_contract)

    def test_public_artifacts_have_no_private_record_shapes(self) -> None:
        public_artifacts = SOURCE.read_text(encoding="utf-8") + REGISTRY.read_text(encoding="utf-8")
        for forbidden in (
            '"entity_type": "professional_service"',
            '"entity_type": "property_offer"',
            '"entity_type": "legal_overlay"',
            "'entity_type' => 'professional_service'",
            "'entity_type' => 'property_offer'",
            "'entity_type' => 'legal_overlay'",
            '"permitted_reuse": "unknown_private_only"',
			'"holds"',
			"'holds' =>",
        ):
            self.assertNotIn(forbidden, public_artifacts)


if __name__ == "__main__":
    suite = unittest.defaultTestLoader.loadTestsFromTestCase(DigitalIslandDataTest)
    result = unittest.TextTestRunner(verbosity=2).run(suite)
    if not result.wasSuccessful():
        raise SystemExit(1)
    print(f"PASS: digital-island data tests ({result.testsRun})")
