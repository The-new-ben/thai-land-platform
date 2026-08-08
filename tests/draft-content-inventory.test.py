"""Contract tests for the reviewed WordPress draft-content disposition."""

from __future__ import annotations

import csv
import json
import unittest
from collections import Counter
from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]
METADATA_PATH = (
    ROOT
    / "data"
    / "content"
    / "inventory"
    / "draft-content-metadata.2026-08-08.csv"
)
DISPOSITION_PATH = (
    ROOT
    / "data"
    / "content"
    / "inventory"
    / "draft-content-disposition.2026-08-08.csv"
)
SEO_REGISTRY_PATH = ROOT / "data" / "seo" / "ownership-registry.json"

EXPECTED_TOTAL = 64
EXPECTED_POST_TYPES = {"post": 55, "page": 9}
ALLOWED_DISPOSITIONS = {
    "discard",
    "discard_after_extract",
    "extract_entity",
    "extract_merge",
    "keep_rewrite",
    "legal_rewrite",
    "publish_release",
    "rebuild_tool",
    "service_rewrite",
    "split_rewrite",
    "trust_rewrite",
}


def load_csv(path: Path) -> list[dict[str, str]]:
    with path.open("r", encoding="utf-8-sig", newline="") as handle:
        reader = csv.DictReader(handle)
        if reader.fieldnames is None:
            raise AssertionError(f"CSV has no header: {path}")
        return list(reader)


def duplicate_values(values: list[str]) -> set[str]:
    counts = Counter(values)
    return {value for value, count in counts.items() if count != 1}


class DraftContentInventoryTest(unittest.TestCase):
    @classmethod
    def setUpClass(cls) -> None:
        cls.metadata = load_csv(METADATA_PATH)
        cls.dispositions = load_csv(DISPOSITION_PATH)
        with SEO_REGISTRY_PATH.open("r", encoding="utf-8") as handle:
            registry = json.load(handle)
        cls.owner_ids = {
            owner["owner_id"] for owner in registry["intent_owners"]
        }

    def test_inventory_has_expected_scope(self) -> None:
        self.assertEqual(EXPECTED_TOTAL, len(self.metadata))
        self.assertEqual(
            EXPECTED_POST_TYPES,
            dict(Counter(row["PostType"].strip() for row in self.metadata)),
        )
        self.assertTrue(all(row["Status"].strip() == "draft" for row in self.metadata))

    def test_each_inventory_and_disposition_id_is_unique(self) -> None:
        metadata_ids = [row["PostId"].strip() for row in self.metadata]
        disposition_ids = [row["PostId"].strip() for row in self.dispositions]
        self.assertEqual(set(), duplicate_values(metadata_ids))
        self.assertEqual(set(), duplicate_values(disposition_ids))
        self.assertTrue(all(value.isdecimal() and int(value) > 0 for value in metadata_ids))
        self.assertTrue(all(value.isdecimal() and int(value) > 0 for value in disposition_ids))

    def test_every_inventory_item_has_exactly_one_disposition(self) -> None:
        metadata_ids = {row["PostId"].strip() for row in self.metadata}
        disposition_ids = {row["PostId"].strip() for row in self.dispositions}
        self.assertEqual(EXPECTED_TOTAL, len(self.dispositions))
        self.assertEqual(metadata_ids, disposition_ids)

    def test_dispositions_are_reviewed_and_supported(self) -> None:
        for row in self.dispositions:
            post_id = row["PostId"].strip()
            disposition = row["Disposition"].strip()
            target = row["TargetOwnerId"].strip()
            rationale = row["Rationale"].strip()

            self.assertNotEqual("unreviewed", disposition.casefold(), post_id)
            self.assertIn(disposition, ALLOWED_DISPOSITIONS, post_id)
            self.assertTrue(target, post_id)
            self.assertTrue(rationale, post_id)
            self.assertTrue(
                target in self.owner_ids or target.startswith("candidate-"),
                f"{post_id}: unknown target owner {target!r}",
            )

    def test_disposition_identity_matches_inventory(self) -> None:
        metadata_by_id = {
            row["PostId"].strip(): row for row in self.metadata
        }
        for disposition in self.dispositions:
            post_id = disposition["PostId"].strip()
            source = metadata_by_id[post_id]
            self.assertEqual(source["PostType"].strip(), disposition["PostType"].strip(), post_id)
            self.assertEqual(source["Title"].strip(), disposition["Title"].strip(), post_id)

    def test_real_estate_hub_release_assignment_is_exact(self) -> None:
        hub_rows = [
            row for row in self.dispositions if row["PostId"].strip() == "841"
        ]
        self.assertEqual(1, len(hub_rows))
        self.assertEqual("page", hub_rows[0]["PostType"].strip())
        self.assertEqual("publish_release", hub_rows[0]["Disposition"].strip())
        self.assertEqual("thailand-real-estate", hub_rows[0]["TargetOwnerId"].strip())


if __name__ == "__main__":
    unittest.main(verbosity=2)
