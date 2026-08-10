"""Build and validate the deterministic Thai-Land content migration ledger."""

from __future__ import annotations

import argparse
import csv
import hashlib
import json
import sys
import unicodedata
from pathlib import Path
from typing import Any
from urllib.parse import unquote, urlsplit


ROOT = Path(__file__).resolve().parents[1]
LEDGER_PATH = (
    ROOT
    / "data"
    / "content"
    / "migration"
    / "migration-ledger.2026-08-10.json"
)
SCHEMA_PATH = LEDGER_PATH.with_name("migration-ledger.schema.json")
PUBLIC_INVENTORY_PATH = (
    ROOT / "data" / "seo" / "inventory" / "current-public-url-metadata.2026-08-08.csv"
)
CATEGORY_INVENTORY_PATH = (
    ROOT / "data" / "seo" / "inventory" / "indexable-category-surfaces.2026-08-08.csv"
)
DRAFT_METADATA_PATH = (
    ROOT / "data" / "content" / "inventory" / "draft-content-metadata.2026-08-08.csv"
)
DRAFT_DISPOSITION_PATH = (
    ROOT / "data" / "content" / "inventory" / "draft-content-disposition.2026-08-08.csv"
)
SOURCE_REVIEW_PATH = (
    ROOT / "data" / "content" / "migration" / "urgent-source-review.2026-08-10.json"
)
OWNERSHIP_REGISTRY_PATH = ROOT / "data" / "seo" / "ownership-registry.json"
MANAGED_ROUTES_PATH = (
    ROOT / "data" / "seo" / "evidence" / "managed-live-routes.0.3.5.json"
)
GUIDES_CANARY_EVIDENCE_PATH = (
    ROOT
    / "data"
    / "seo"
    / "evidence"
    / "priority-guides-private-canary.0.4.0.json"
)
HOMEPAGE_EVIDENCE_PATH = (
    ROOT / "output" / "playwright" / "homepage-live-0.3.6-acceptance.json"
)

GENERATED_ON = "2026-08-10"
RELEASED_DRAFT_IDS = {841}
PROMOTED_GUIDE_HUB_IDS = {"thailand-visas", "thailand-law-and-tax"}
SYSTEM_ENRICHMENT_HUBS = {
    "israeli-store-thailand",
    "services-in-thailand",
    "thailand-map",
    "thailand-property-projects",
}
CANDIDATE_OWNERS = {
    "candidate-cannabis-business-thailand": {
        "parent_owner_id": "business-in-thailand",
        "proposed_url": "/פתיחת-עסק-קנאביס-בתאילנד/",
    },
    "candidate-privacy-policy": {
        "parent_owner_id": "home",
        "proposed_url": "/מדיניות-פרטיות/",
    },
    "candidate-thailand-family-travel": {
        "parent_owner_id": "thailand-tourism",
        "proposed_url": "/תאילנד-עם-ילדים/",
    },
}
URGENT_ROUTES = {
    "/hello-world/",
    "/החל-מאפריל-2022-מטיילים-יורשו-להיכנס-לתאי/",
    "/המחירים-הזולים-ביותר-תאילנד-2025/",
    "/מלונות-חדשים-בקו-סמוי-2022/",
    "/תאילנד-וחוק-אי-הפללת-קנאביס-פנאי/",
    "/5-דברים-לעשות-ביום-גשום-בתאילנד-2022/",
}
FINAL_ACCEPTANCE_ROUTES = {"/category/תאילנד-כללי/"}

MIGRATION_STATUSES = {
    "blocked_evidence",
    "complete",
    "discard_review_pending",
    "evidence_update_pending",
    "extraction_pending",
    "identity_created_content_pending",
    "offer_and_content_review_pending",
    "planned_not_started",
    "rebuild_pending",
    "rewrite_pending",
    "split_plan_pending",
}

COMPLETION_FIELDS = (
    "content_migration_receipt_path",
    "source_material_receipt_path",
    "disposition_receipt_path",
    "target_content_sha256",
    "release_version",
    "release_receipt_path",
    "live_url",
    "http_status",
    "canonical_verified",
    "breadcrumb_verified",
    "internal_links_verified",
    "structured_data_verified",
    "indexability_verified",
    "desktop_qa_artifact",
    "mobile_qa_artifact",
    "verified_at",
)


def reject_duplicate_keys(pairs: list[tuple[str, Any]]) -> dict[str, Any]:
    result: dict[str, Any] = {}
    for key, value in pairs:
        if key in result:
            raise ValueError(f"duplicate JSON key: {key}")
        result[key] = value
    return result


def reject_non_finite(value: str) -> None:
    raise ValueError(f"non-finite JSON number: {value}")


def load_json(path: Path) -> dict[str, Any]:
    with path.open("r", encoding="utf-8") as handle:
        value = json.load(
            handle,
            object_pairs_hook=reject_duplicate_keys,
            parse_constant=reject_non_finite,
        )
    if not isinstance(value, dict):
        raise ValueError(f"JSON root must be an object: {path}")
    return value


def load_csv(path: Path) -> list[dict[str, str]]:
    with path.open("r", encoding="utf-8-sig", newline="") as handle:
        reader = csv.DictReader(handle)
        if reader.fieldnames is None:
            raise ValueError(f"CSV has no header: {path}")
        return list(reader)


def relative_path(path: Path) -> str:
    return path.resolve().relative_to(ROOT.resolve()).as_posix()


def sha256_lf(path: Path) -> str:
    payload = path.read_bytes().replace(b"\r\n", b"\n").replace(b"\r", b"\n")
    return hashlib.sha256(payload).hexdigest()


def normalize_route(value: str) -> str:
    value = unicodedata.normalize("NFC", value.strip())
    split = urlsplit(value)
    path = unquote(split.path if split.scheme or split.netloc else value.partition("?")[0])
    path = unicodedata.normalize("NFC", path or "/")
    if not path.startswith("/"):
        raise ValueError(f"route is not site relative: {value}")
    if path != "/" and not path.endswith("/") and "." not in path.rsplit("/", 1)[-1]:
        path += "/"
    return path


def integer(value: str | None) -> int:
    cleaned = (value or "").strip()
    return int(cleaned) if cleaned else 0


def sanitize(value: Any) -> Any:
    if isinstance(value, str):
        return value.replace(chr(0x2013), "-").replace(chr(0x2014), "-")
    if isinstance(value, list):
        return [sanitize(item) for item in value]
    if isinstance(value, dict):
        return {key: sanitize(item) for key, item in value.items()}
    return value


def source_descriptor(path: Path, kind: str, record_count: int) -> dict[str, Any]:
    return {
        "path": relative_path(path),
        "kind": kind,
        "sha256_lf": sha256_lf(path),
        "record_count": record_count,
    }


def full_hierarchy(owner_id: str, owners: dict[str, dict[str, Any]]) -> list[str]:
    result: list[str] = []
    current: str | None = owner_id
    while current is not None:
        if current in result:
            raise ValueError(f"owner hierarchy cycle at {owner_id}")
        if current not in owners:
            raise ValueError(f"unknown hierarchy owner: {current}")
        result.append(current)
        current = owners[current]["parent_owner_id"]
    result.reverse()
    if not result or result[0] != "home":
        raise ValueError(f"owner hierarchy does not begin at home: {owner_id}")
    return result


def candidate_hierarchy(
    candidate_id: str, owners: dict[str, dict[str, Any]]
) -> list[str]:
    candidate = CANDIDATE_OWNERS[candidate_id]
    return [*full_hierarchy(candidate["parent_owner_id"], owners), candidate_id]


def completion_evidence() -> dict[str, Any]:
    return {
        "content_migration_receipt_path": None,
        "source_material_receipt_path": None,
        "disposition_receipt_path": None,
        "target_content_sha256": None,
        "release_version": None,
        "release_receipt_path": None,
        "live_url": None,
        "http_status": None,
        "canonical_verified": False,
        "breadcrumb_verified": False,
        "internal_links_verified": False,
        "structured_data_verified": False,
        "indexability_verified": False,
        "desktop_qa_artifact": None,
        "mobile_qa_artifact": None,
        "verified_at": None,
    }


def release_waves() -> list[dict[str, Any]]:
    return [
        {
            "wave_id": "wave-01-urgent-accuracy",
            "sequence": 1,
            "name": "Urgent legal, entry and expired date corrections",
            "depends_on": [],
            "exit_gate": "Every changed public route passes content, canonical, breadcrumb, link, schema, indexability and mobile checks.",
        },
        {
            "wave_id": "wave-02-parent-hubs",
            "sequence": 2,
            "name": "Missing parent hubs",
            "depends_on": ["wave-01-urgent-accuracy"],
            "exit_gate": "Each hub has a distinct query owner, useful body content and crawlable links to its live or planned children.",
        },
        {
            "wave_id": "wave-03-core-rebuilds",
            "sequence": 3,
            "name": "Core pillars, destinations and live legacy pages",
            "depends_on": ["wave-02-parent-hubs"],
            "exit_gate": "Tourism, business, Bangkok, Phuket, Koh Samui, hotels, relocation and their live spokes meet the release evidence contract.",
        },
        {
            "wave_id": "wave-04-draft-consolidation",
            "sequence": 4,
            "name": "Draft extraction, rewriting and consolidation",
            "depends_on": ["wave-03-core-rebuilds"],
            "exit_gate": "Every draft has an extraction, integration or discard receipt and no raw duplicate draft is published.",
        },
        {
            "wave_id": "wave-05-platform-systems",
            "sequence": 5,
            "name": "Map, service directory, shop and property project database",
            "depends_on": ["wave-04-draft-consolidation"],
            "exit_gate": "Structured entities, filters, prices and transaction paths have source, freshness and user acceptance evidence.",
        },
        {
            "wave_id": "wave-06-sitewide-seo-acceptance",
            "sequence": 6,
            "name": "Sitewide SEO and migration acceptance",
            "depends_on": ["wave-05-platform-systems"],
            "exit_gate": "All protected routes, redirects, canonicals, breadcrumbs, internal links, schema, sitemaps and mobile views pass final acceptance.",
        },
    ]


def legacy_wave(url: str) -> str:
    if url in URGENT_ROUTES:
        return "wave-01-urgent-accuracy"
    if url in FINAL_ACCEPTANCE_ROUTES:
        return "wave-06-sitewide-seo-acceptance"
    return "wave-03-core-rebuilds"


def draft_wave(target_owner_id: str) -> str:
    if target_owner_id == "thailand-cannabis-law":
        return "wave-01-urgent-accuracy"
    if target_owner_id in {"services-in-thailand", "thailand-property-projects"}:
        return "wave-05-platform-systems"
    return "wave-04-draft-consolidation"


def draft_status(disposition: str) -> str:
    return {
        "discard": "discard_review_pending",
        "discard_after_extract": "extraction_pending",
        "extract_entity": "extraction_pending",
        "extract_merge": "extraction_pending",
        "keep_rewrite": "rewrite_pending",
        "legal_rewrite": "evidence_update_pending",
        "rebuild_tool": "rebuild_pending",
        "service_rewrite": "offer_and_content_review_pending",
        "split_rewrite": "split_plan_pending",
        "trust_rewrite": "rewrite_pending",
    }[disposition]


def draft_material_class(disposition: str) -> str:
    return {
        "discard": "no_reusable_material",
        "discard_after_extract": "limited_extractable_material",
        "extract_entity": "entity_facts_extractable",
        "extract_merge": "sections_extractable",
        "keep_rewrite": "substantial_rewrite_source",
        "legal_rewrite": "substantial_rewrite_source_requires_current_evidence",
        "rebuild_tool": "concept_only_rebuild_required",
        "service_rewrite": "substantial_rewrite_source_requires_live_offer",
        "split_rewrite": "multi_intent_source_requires_split",
        "trust_rewrite": "substantial_rewrite_source",
    }[disposition]


def build_legacy_surfaces(
    public_rows: list[dict[str, str]],
    category_rows: list[dict[str, str]],
    registry: dict[str, Any],
    managed: dict[str, Any],
    source_reviews: dict[int, dict[str, Any]],
) -> tuple[list[dict[str, Any]], list[dict[str, Any]]]:
    owners = {item["owner_id"]: item for item in registry["intent_owners"]}
    routes = {normalize_route(item["url"]): item for item in registry["routes"]}
    observed: dict[str, tuple[dict[str, str], Path]] = {}
    for row, path in [
        *((row, PUBLIC_INVENTORY_PATH) for row in public_rows),
        *((row, CATEGORY_INVENTORY_PATH) for row in category_rows),
    ]:
        url = normalize_route(row["DecodedPath"])
        if url in observed:
            raise ValueError(f"duplicate observed public route: {url}")
        observed[url] = (row, path)

    managed_original_urls = {"/"}
    managed_by_url: dict[str, dict[str, Any]] = {}
    for item in managed["managed_routes"]:
        url = normalize_route(item["canonical_url"])
        managed_by_url[url] = item
        if url in observed:
            managed_original_urls.add(url)

    exclusions: list[dict[str, Any]] = []
    for url in sorted(managed_original_urls):
        if url not in observed:
            raise ValueError(f"managed exclusion is not in frozen inventory: {url}")
        if url == "/":
            exclusions.append(
                {
                    "url": url,
                    "owner_id": "home",
                    "evidence_path": relative_path(HOMEPAGE_EVIDENCE_PATH),
                }
            )
        else:
            exclusions.append(
                {
                    "url": url,
                    "owner_id": managed_by_url[url]["seo_owner_id"],
                    "evidence_path": relative_path(MANAGED_ROUTES_PATH),
                }
            )

    source_reviews_by_url: dict[str, dict[str, Any]] = {}
    for review in source_reviews.values():
        if review["status"] != "publish":
            continue
        review_url = normalize_route("/" + review["slug"] + "/")
        if review_url in source_reviews_by_url:
            raise ValueError(f"duplicate source review route: {review_url}")
        source_reviews_by_url[review_url] = review

    records: list[dict[str, Any]] = []
    for url in sorted(set(observed) - managed_original_urls):
        row, inventory_path = observed[url]
        route = routes.get(url)
        if route is None:
            raise ValueError(f"legacy route is missing from ownership registry: {url}")
        assignment = route["assignment"]
        if assignment["kind"] == "canonical_owner":
            current_owner_id = assignment["owner_id"]
            candidate_owner_id = None
            release_owner_id = current_owner_id
            assignment_state = "canonical_owner"
            required_resolution_evidence: list[str] = []
        else:
            current_owner_id = assignment["current_owner_id"]
            candidate_owner_id = assignment["candidate_owner_id"]
            release_owner_id = candidate_owner_id or current_owner_id
            assignment_state = f"migration_gate:{assignment['state']}"
            required_resolution_evidence = assignment["required_evidence"]

        owner = owners[current_owner_id]
        release_owner = owners[release_owner_id]
        source_review = source_reviews_by_url.get(url)
        source_reviewed = source_review is not None
        blocked = (
            assignment["kind"] == "migration_gate"
            or owner["review_state"] == "evidence_pending"
        )
        records.append(
            {
                "record_id": f"legacy:{route['route_id']}",
                "source_kind": row["SitemapKind"].removesuffix("-extra"),
                "source_url": url,
                "source_absolute_url": row["Url"].strip(),
                "source_title": row["Title"].strip(),
                "canonical_owner_id": current_owner_id,
                "candidate_owner_id": candidate_owner_id,
                "owner_assignment_state": assignment_state,
                "canonical_target_url": owner["canonical_url"],
                "hierarchy_parent_owner_id": owner["parent_owner_id"],
                "hierarchy_path_owner_ids": full_hierarchy(current_owner_id, owners),
                "disposition": owner["migration_action"],
                "migration_status": "blocked_evidence" if blocked else "rewrite_pending",
                "release_target": {
                    "wave_id": legacy_wave(url),
                    "target_owner_id": release_owner_id,
                    "target_url": release_owner["canonical_url"],
                    "target_state": f"registered_{release_owner['lifecycle']}",
                    "target_kind": (
                        "merge_or_preserve_decision"
                        if assignment["kind"] == "migration_gate"
                        else "historical_page_review"
                        if owner["migration_action"] == "preserve_historical"
                        else "canonical_hub_rebuild"
                        if owner["migration_action"] == "extract_then_review"
                        else "canonical_page_rebuild"
                    ),
                    "blocked": blocked,
                    "hierarchy_path_owner_ids": full_hierarchy(release_owner_id, owners),
                    "system_enrichment_wave_id": None,
                },
                "source_material_status": (
                    "live_body_reviewed_no_body_stored"
                    if source_reviewed
                    else "live_html_retrieval_required"
                ),
                "source_metrics": {
                    "http_status": integer(row["Status"]),
                    "content_sha256": row["ContentSha256"].strip(),
                    "approximate_main_word_count": integer(row["ApproximateMainWordCount"]),
                    "main_h1_count": integer(row["MainH1Count"]),
                    "main_h2_count": integer(row["MainH2Count"]),
                    "main_unique_internal_destinations": integer(
                        row["MainUniqueInternalDestinations"]
                    ),
                    "meta_description_length": integer(row["MetaDescriptionLength"]),
                    "image_count": integer(row["ImageCount"]),
                },
                "evidence": {
                    "source_inventory_path": relative_path(inventory_path),
                    "source_inventory_row_key": url,
                    "ownership_registry_path": relative_path(OWNERSHIP_REGISTRY_PATH),
                    "ownership_route_id": route["route_id"],
                    "owner_review_state": owner["review_state"],
                    "source_body_snapshot_status": (
                        "reviewed_no_body_stored"
                        if source_reviewed
                        else "not_in_repository"
                    ),
                    "source_review_path": (
                        relative_path(SOURCE_REVIEW_PATH) if source_reviewed else None
                    ),
                    "source_review_record_key": (
                        source_review["post_id"] if source_reviewed else None
                    ),
                    "source_review_decision": (
                        source_review["decision"] if source_reviewed else None
                    ),
                    "required_resolution_evidence": required_resolution_evidence,
                },
                "completion_profile": "public_route_release",
                "completion_evidence": completion_evidence(),
            }
        )
    return records, exclusions


def build_draft_records(
    metadata_rows: list[dict[str, str]],
    disposition_rows: list[dict[str, str]],
    registry: dict[str, Any],
    source_reviews: dict[int, dict[str, Any]],
) -> list[dict[str, Any]]:
    owners = {item["owner_id"]: item for item in registry["intent_owners"]}
    metadata_by_id = {integer(item["PostId"]): item for item in metadata_rows}
    disposition_by_id = {integer(item["PostId"]): item for item in disposition_rows}
    if len(metadata_by_id) != len(metadata_rows):
        raise ValueError("duplicate post id in draft metadata")
    if len(disposition_by_id) != len(disposition_rows):
        raise ValueError("duplicate post id in draft dispositions")
    if set(metadata_by_id) != set(disposition_by_id):
        raise ValueError("draft metadata and disposition ids differ")
    released = {
        post_id
        for post_id, item in disposition_by_id.items()
        if item["Disposition"].strip() == "publish_release"
    }
    if released != RELEASED_DRAFT_IDS:
        raise ValueError(f"unexpected released draft ids: {sorted(released)}")

    records: list[dict[str, Any]] = []
    for post_id in sorted(set(metadata_by_id) - released):
        metadata = metadata_by_id[post_id]
        disposition_row = disposition_by_id[post_id]
        if metadata["PostType"].strip() != disposition_row["PostType"].strip():
            raise ValueError(f"draft post type mismatch: {post_id}")
        if metadata["Title"].strip() != disposition_row["Title"].strip():
            raise ValueError(f"draft title mismatch: {post_id}")

        source_target_id = disposition_row["TargetOwnerId"].strip()
        source_review = source_reviews.get(post_id)
        target_id = (
            source_review["target_owner_id"]
            if source_review is not None
            else source_target_id
        )
        disposition = disposition_row["Disposition"].strip()
        if target_id in owners:
            owner = owners[target_id]
            canonical_owner_id: str | None = target_id
            candidate_owner_id: str | None = None
            parent_owner_id = owner["parent_owner_id"]
            hierarchy = full_hierarchy(target_id, owners)
            target_url = owner["canonical_url"]
            target_state = f"registered_{owner['lifecycle']}"
        elif target_id in CANDIDATE_OWNERS:
            canonical_owner_id = None
            candidate_owner_id = target_id
            parent_owner_id = CANDIDATE_OWNERS[target_id]["parent_owner_id"]
            hierarchy = candidate_hierarchy(target_id, owners)
            target_url = CANDIDATE_OWNERS[target_id]["proposed_url"]
            target_state = "candidate_not_registered"
        else:
            raise ValueError(f"draft {post_id} has unknown target owner: {target_id}")

        is_discard = disposition == "discard"
        records.append(
            {
                "record_id": f"draft:{post_id}",
                "source_post_id": post_id,
                "source_post_type": metadata["PostType"].strip(),
                "source_status": metadata["Status"].strip(),
                "source_title": metadata["Title"].strip(),
                "source_slug": metadata["Slug"].strip() or None,
                "source_modified_display": metadata["ModifiedDisplay"].strip() or None,
                "canonical_owner_id": canonical_owner_id,
                "candidate_owner_id": candidate_owner_id,
                "owner_assignment_state": target_state,
                "hierarchy_parent_owner_id": parent_owner_id,
                "hierarchy_path_owner_ids": hierarchy,
                "disposition": disposition,
                "migration_status": draft_status(disposition),
                "release_target": {
                    "wave_id": draft_wave(target_id),
                    "target_owner_id": target_id,
                    "target_url": target_url,
                    "target_state": target_state,
                    "target_kind": (
                        "discard_receipt"
                        if is_discard
                        else "candidate_page_definition"
                        if candidate_owner_id is not None
                        else "canonical_content_integration"
                    ),
                    "blocked": (
                        disposition in {"legal_rewrite", "service_rewrite"}
                        or candidate_owner_id is not None
                    ),
                    "hierarchy_path_owner_ids": hierarchy,
                    "system_enrichment_wave_id": None,
                },
                "source_material_status": (
                    "draft_body_reviewed_no_body_stored"
                    if source_review is not None
                    else "draft_body_retrieval_required"
                ),
                "source_material_class": draft_material_class(disposition),
                "source_metrics": {
                    "characters": integer(disposition_row["Characters"]),
                    "words": integer(disposition_row["Words"]),
                    "h1_count": integer(disposition_row["H1Count"]),
                    "h2_count": integer(disposition_row["H2Count"]),
                    "h3_count": integer(disposition_row["H3Count"]),
                    "link_count": integer(disposition_row["LinkCount"]),
                    "image_count": integer(disposition_row["ImageCount"]),
                    "shortcode_count": integer(disposition_row["ShortcodeCount"]),
                    "source_long_dash_count": integer(disposition_row["LongDashCount"]),
                },
                "evidence": {
                    "metadata_inventory_path": relative_path(DRAFT_METADATA_PATH),
                    "metadata_inventory_row_key": str(post_id),
                    "disposition_inventory_path": relative_path(DRAFT_DISPOSITION_PATH),
                    "disposition_inventory_row_key": str(post_id),
                    "ownership_registry_path": relative_path(OWNERSHIP_REGISTRY_PATH),
                    "source_target_owner_id": source_target_id,
                    "source_body_snapshot_status": (
                        "reviewed_no_body_stored"
                        if source_review is not None
                        else "not_in_repository"
                    ),
                    "source_review_path": (
                        relative_path(SOURCE_REVIEW_PATH)
                        if source_review is not None
                        else None
                    ),
                    "source_review_record_key": (
                        post_id if source_review is not None else None
                    ),
                    "source_review_decision": (
                        source_review["decision"]
                        if source_review is not None
                        else None
                    ),
                    "disposition_rationale": disposition_row["Rationale"].strip(),
                },
                "completion_profile": "discard" if is_discard else "source_material_integration",
                "completion_evidence": completion_evidence(),
            }
        )
    return records


def build_planned_hubs(
    registry: dict[str, Any],
    legacy_records: list[dict[str, Any]],
    draft_records: list[dict[str, Any]],
    source_reviews: dict[int, dict[str, Any]],
) -> list[dict[str, Any]]:
    owners = {item["owner_id"]: item for item in registry["intent_owners"]}
    planned = [item for item in registry["intent_owners"] if item["lifecycle"] == "planned"]
    records: list[dict[str, Any]] = []
    for owner in sorted(planned, key=lambda item: item["owner_id"]):
        owner_id = owner["owner_id"]
        identity_reviews = [
            review
            for review in source_reviews.values()
            if review["target_owner_id"] == owner_id
            and review["decision"] == "new_parent_identity_frozen"
        ]
        if len(identity_reviews) > 1:
            raise ValueError(f"multiple draft identities for planned hub: {owner_id}")
        identity_review = identity_reviews[0] if identity_reviews else None
        legacy_ids = sorted(
            item["record_id"]
            for item in legacy_records
            if owner_id in item["hierarchy_path_owner_ids"]
        )
        draft_ids = sorted(
            item["record_id"]
            for item in draft_records
            if owner_id in item["release_target"]["hierarchy_path_owner_ids"]
        )
        if owner_id == "thailand-map":
            material_status = "structured_geography_available_content_required"
            source_paths = [
                "data/geography/registry.json",
                "data/geography/regions.json",
                "data/geography/provinces.csv",
            ]
        elif legacy_ids or draft_ids:
            material_status = "partial_sources_body_retrieval_required"
            source_paths = []
        else:
            material_status = "research_and_content_build_required"
            source_paths = []
        records.append(
            {
                "record_id": f"hub:{owner_id}",
                "owner_id": owner_id,
                "canonical_url": owner["canonical_url"],
                "primary_keyword": owner["primary_keyword"],
                "hierarchy_parent_owner_id": owner["parent_owner_id"],
                "hierarchy_path_owner_ids": full_hierarchy(owner_id, owners),
                "disposition": owner["migration_action"],
                "migration_status": (
                    "identity_created_content_pending"
                    if identity_review is not None
                    else "planned_not_started"
                ),
                "release_target": {
                    "wave_id": "wave-02-parent-hubs",
                    "target_owner_id": owner_id,
                    "target_url": owner["canonical_url"],
                    "target_state": (
                        "production_draft_identity"
                        if identity_review is not None
                        else "registered_planned"
                    ),
                    "target_kind": "parent_hub_release",
                    "blocked": False,
                    "hierarchy_path_owner_ids": full_hierarchy(owner_id, owners),
                    "system_enrichment_wave_id": (
                        "wave-05-platform-systems"
                        if owner_id in SYSTEM_ENRICHMENT_HUBS
                        else None
                    ),
                },
                "source_material_status": material_status,
                "supporting_legacy_record_ids": legacy_ids,
                "supporting_draft_record_ids": draft_ids,
                "evidence": {
                    "ownership_registry_path": relative_path(OWNERSHIP_REGISTRY_PATH),
                    "owner_review_state": owner["review_state"],
                    "owner_source_evidence": owner["source_evidence"],
                    "structured_source_paths": source_paths,
                    "draft_identity_review_path": (
                        relative_path(SOURCE_REVIEW_PATH)
                        if identity_review is not None
                        else None
                    ),
                    "draft_identity_post_id": (
                        identity_review["post_id"]
                        if identity_review is not None
                        else None
                    ),
                    "draft_identity_slug": (
                        identity_review["slug"]
                        if identity_review is not None
                        else None
                    ),
                    "draft_identity_status": (
                        identity_review["status"]
                        if identity_review is not None
                        else None
                    ),
                    "draft_identity_content_sha256": (
                        identity_review["content_sha256"]
                        if identity_review is not None
                        else None
                    ),
                },
                "completion_profile": "planned_hub_release",
                "completion_evidence": completion_evidence(),
            }
        )
    return records


def build_promoted_hubs(
    registry: dict[str, Any],
    legacy_records: list[dict[str, Any]],
    draft_records: list[dict[str, Any]],
    source_reviews: dict[int, dict[str, Any]],
    canary: dict[str, Any],
) -> list[dict[str, Any]]:
    """Keep promoted parent hubs accountable until public acceptance is recorded."""
    owners = {item["owner_id"]: item for item in registry["intent_owners"]}
    if canary.get("evidence_scope") != "authenticated_manual_canary":
        raise ValueError("Guides Canary evidence scope differs")
    if canary.get("public_live_verified") is not False:
        raise ValueError("Guides Canary evidence must not claim public live verification")
    acceptance = canary.get("acceptance")
    if not isinstance(acceptance, dict) or acceptance.get("passed") is not True:
        raise ValueError("Guides private Canary acceptance is missing")
    if acceptance.get("public_live_verified") is not False:
        raise ValueError("Guides acceptance must remain private-only evidence")
    canary_routes = acceptance.get("routes")
    if not isinstance(canary_routes, list):
        raise ValueError("Guides private Canary route records are missing")
    canary_by_owner = {
        item.get("seo_owner_id"): item
        for item in canary_routes
        if isinstance(item, dict) and isinstance(item.get("seo_owner_id"), str)
    }

    records: list[dict[str, Any]] = []
    for owner_id in sorted(PROMOTED_GUIDE_HUB_IDS):
        owner = owners.get(owner_id)
        if owner is None or owner.get("lifecycle") != "live":
            raise ValueError(f"promoted Guides hub is not a live owner: {owner_id}")
        expected_source = relative_path(GUIDES_CANARY_EVIDENCE_PATH)
        if owner.get("source_evidence") != [expected_source]:
            raise ValueError(f"promoted Guides hub evidence differs: {owner_id}")
        identity_reviews = [
            review
            for review in source_reviews.values()
            if review["target_owner_id"] == owner_id
            and review["decision"] == "new_parent_identity_frozen"
        ]
        if len(identity_reviews) != 1:
            raise ValueError(f"promoted Guides hub needs one draft identity: {owner_id}")
        identity_review = identity_reviews[0]
        observed = canary_by_owner.get(owner_id)
        if (
            not isinstance(observed, dict)
            or observed.get("post_id") != identity_review["post_id"]
            or observed.get("post_type") != "page"
            or observed.get("wordpress_status_at_observation") != "draft"
            or observed.get("expected_canonical_path") != owner["canonical_url"]
        ):
            raise ValueError(f"promoted Guides hub Canary identity differs: {owner_id}")
        legacy_ids = sorted(
            item["record_id"]
            for item in legacy_records
            if owner_id in item["hierarchy_path_owner_ids"]
        )
        draft_ids = sorted(
            item["record_id"]
            for item in draft_records
            if owner_id in item["release_target"]["hierarchy_path_owner_ids"]
        )
        hierarchy = full_hierarchy(owner_id, owners)
        records.append(
            {
                "record_id": f"hub:{owner_id}",
                "owner_id": owner_id,
                "canonical_url": owner["canonical_url"],
                "primary_keyword": owner["primary_keyword"],
                "hierarchy_parent_owner_id": owner["parent_owner_id"],
                "hierarchy_path_owner_ids": hierarchy,
                "disposition": owner["migration_action"],
                "migration_status": "evidence_update_pending",
                "release_target": {
                    "wave_id": "wave-02-parent-hubs",
                    "target_owner_id": owner_id,
                    "target_url": owner["canonical_url"],
                    "target_state": "registered_live_owner_private_canary_passed_publication_pending",
                    "target_kind": "parent_hub_public_release",
                    "blocked": False,
                    "hierarchy_path_owner_ids": hierarchy,
                    "system_enrichment_wave_id": None,
                },
                "source_material_status": "managed_content_private_canary_passed_publication_pending",
                "supporting_legacy_record_ids": legacy_ids,
                "supporting_draft_record_ids": draft_ids,
                "evidence": {
                    "ownership_registry_path": relative_path(OWNERSHIP_REGISTRY_PATH),
                    "owner_review_state": owner["review_state"],
                    "owner_source_evidence": owner["source_evidence"],
                    "structured_source_paths": [],
                    "draft_identity_review_path": relative_path(SOURCE_REVIEW_PATH),
                    "draft_identity_post_id": identity_review["post_id"],
                    "draft_identity_slug": identity_review["slug"],
                    "draft_identity_status": identity_review["status"],
                    "draft_identity_content_sha256": identity_review["content_sha256"],
                },
                "completion_profile": "planned_hub_release",
                "completion_evidence": completion_evidence(),
            }
        )
    return records


def build_ledger() -> dict[str, Any]:
    public_rows = load_csv(PUBLIC_INVENTORY_PATH)
    category_rows = load_csv(CATEGORY_INVENTORY_PATH)
    metadata_rows = load_csv(DRAFT_METADATA_PATH)
    disposition_rows = load_csv(DRAFT_DISPOSITION_PATH)
    registry = load_json(OWNERSHIP_REGISTRY_PATH)
    managed = load_json(MANAGED_ROUTES_PATH)
    guides_canary = load_json(GUIDES_CANARY_EVIDENCE_PATH)
    source_review = load_json(SOURCE_REVIEW_PATH)
    source_review_records = source_review.get("records")
    if not isinstance(source_review_records, list):
        raise ValueError("source review records must be an array")
    source_reviews = {
        integer(str(item.get("post_id"))): item
        for item in source_review_records
        if isinstance(item, dict)
    }
    if len(source_reviews) != len(source_review_records):
        raise ValueError("source review post ids must be unique positive integers")

    legacy_records, managed_exclusions = build_legacy_surfaces(
        public_rows, category_rows, registry, managed, source_reviews
    )
    draft_records = build_draft_records(
        metadata_rows, disposition_rows, registry, source_reviews
    )
    hub_records = build_planned_hubs(
        registry, legacy_records, draft_records, source_reviews
    )
    promoted_hub_records = build_promoted_hubs(
        registry,
        legacy_records,
        draft_records,
        source_reviews,
        guides_canary,
    )

    ledger = {
        "$schema": "./migration-ledger.schema.json",
        "ledger_version": "1.0.0",
        "generated_on": GENERATED_ON,
        "site": {
            "name": registry["site"]["name"],
            "base_url": registry["site"]["base_url"],
            "default_language": registry["site"]["default_language"],
        },
        "policy": {
            "canonical_owner_rule": "One primary query has one registered canonical owner and one canonical route.",
            "completion_rule": "A record can become complete only after its completion profile has all required release evidence.",
            "publication_rule": "This ledger records work and evidence. It does not authorize publication by itself.",
            "migration_status_values": sorted(MIGRATION_STATUSES),
        },
        "release_waves": release_waves(),
        "sources": [
            source_descriptor(PUBLIC_INVENTORY_PATH, "csv_public_inventory", len(public_rows)),
            source_descriptor(CATEGORY_INVENTORY_PATH, "csv_public_inventory", len(category_rows)),
            source_descriptor(DRAFT_METADATA_PATH, "csv_draft_inventory", len(metadata_rows)),
            source_descriptor(
                DRAFT_DISPOSITION_PATH, "csv_draft_disposition", len(disposition_rows)
            ),
            source_descriptor(
                SOURCE_REVIEW_PATH,
                "json_authenticated_source_review",
                len(source_review_records),
            ),
            source_descriptor(
                OWNERSHIP_REGISTRY_PATH,
                "json_ownership_registry",
                len(registry["intent_owners"]) + len(registry["routes"]),
            ),
            source_descriptor(
                MANAGED_ROUTES_PATH,
                "json_managed_route_evidence",
                len(managed["managed_routes"]),
            ),
            source_descriptor(
                GUIDES_CANARY_EVIDENCE_PATH,
                "json_authenticated_private_canary_evidence",
                len(guides_canary["acceptance"]["routes"]),
            ),
        ],
        "scope": {
            "frozen_public_surface_count": len(public_rows) + len(category_rows),
            "platform_managed_original_surface_count": len(managed_exclusions),
            "legacy_public_surface_count": len(legacy_records),
            "remaining_draft_record_count": len(draft_records),
            "excluded_released_draft_ids": sorted(RELEASED_DRAFT_IDS),
            "planned_hub_count": len(hub_records),
            "promoted_hub_count": len(promoted_hub_records),
            "platform_managed_original_surfaces": managed_exclusions,
        },
        "legacy_public_surfaces": legacy_records,
        "draft_records": draft_records,
        "planned_hubs": hub_records,
        "promoted_hubs": promoted_hub_records,
    }
    return sanitize(ledger)


def completion_is_sufficient(record: dict[str, Any]) -> bool:
    evidence = record["completion_evidence"]
    profile = record["completion_profile"]
    if profile == "discard":
        return bool(evidence["disposition_receipt_path"] and evidence["verified_at"])
    required_strings = [
        "content_migration_receipt_path",
        "source_material_receipt_path",
        "target_content_sha256",
        "release_version",
        "release_receipt_path",
        "live_url",
        "desktop_qa_artifact",
        "mobile_qa_artifact",
        "verified_at",
    ]
    required_checks = [
        "canonical_verified",
        "breadcrumb_verified",
        "internal_links_verified",
        "structured_data_verified",
        "indexability_verified",
    ]
    return (
        all(evidence[field] for field in required_strings)
        and evidence["http_status"] == 200
        and all(evidence[field] is True for field in required_checks)
    )


def validate_ledger(ledger: dict[str, Any]) -> list[str]:
    errors: list[str] = []
    scope = ledger["scope"]
    expected_scope = {
        "frozen_public_surface_count": 43,
        "platform_managed_original_surface_count": 8,
        "legacy_public_surface_count": 35,
        "remaining_draft_record_count": 63,
        "planned_hub_count": 9,
        "promoted_hub_count": 2,
    }
    for key, expected in expected_scope.items():
        if scope.get(key) != expected:
            errors.append(f"scope {key} expected {expected}, got {scope.get(key)}")

    groups = (
        ledger["legacy_public_surfaces"],
        ledger["draft_records"],
        ledger["planned_hubs"],
        ledger["promoted_hubs"],
    )
    all_records = [record for group in groups for record in group]
    record_ids = [record["record_id"] for record in all_records]
    if len(record_ids) != len(set(record_ids)):
        errors.append("record ids are not unique")

    wave_ids = {item["wave_id"] for item in ledger["release_waves"]}
    if len(wave_ids) != len(ledger["release_waves"]):
        errors.append("release wave ids are not unique")
    sequences = [item["sequence"] for item in ledger["release_waves"]]
    if sequences != list(range(1, len(sequences) + 1)):
        errors.append("release wave sequences are not contiguous")

    for record in all_records:
        status = record["migration_status"]
        if status not in MIGRATION_STATUSES:
            errors.append(f"{record['record_id']}: unsupported migration status {status}")
        target = record["release_target"]
        if target["wave_id"] not in wave_ids:
            errors.append(f"{record['record_id']}: unknown release wave")
        enrichment = target["system_enrichment_wave_id"]
        if enrichment is not None and enrichment not in wave_ids:
            errors.append(f"{record['record_id']}: unknown enrichment wave")
        hierarchy = record["hierarchy_path_owner_ids"]
        if not hierarchy or hierarchy[0] != "home":
            errors.append(f"{record['record_id']}: hierarchy does not start at home")
        if set(record["completion_evidence"]) != set(COMPLETION_FIELDS):
            errors.append(f"{record['record_id']}: completion evidence shape differs")
        if status == "complete" and not completion_is_sufficient(record):
            errors.append(f"{record['record_id']}: complete without sufficient evidence")
        if status != "complete" and any(
            value not in (None, False) for value in record["completion_evidence"].values()
        ):
            errors.append(f"{record['record_id']}: pending record contains completion evidence")

    source_paths: set[str] = set()
    for source in ledger["sources"]:
        path = ROOT / source["path"]
        if not path.is_file():
            errors.append(f"missing source path: {source['path']}")
            continue
        if source["path"] in source_paths:
            errors.append(f"duplicate source path: {source['path']}")
        source_paths.add(source["path"])
        if sha256_lf(path) != source["sha256_lf"]:
            errors.append(f"source digest differs: {source['path']}")

    rendered = render_ledger(ledger)
    for character in (chr(0x2013), chr(0x2014)):
        if character in rendered:
            errors.append("forbidden dash character in rendered ledger")
    lowered = rendered.lower()
    for encoded in ("\\u" + "2013", "\\u" + "2014", "&#" + "8211;", "&#" + "8212;"):
        if encoded in lowered:
            errors.append("forbidden dash encoding in rendered ledger")
    return errors


def render_ledger(ledger: dict[str, Any]) -> str:
    return json.dumps(ledger, ensure_ascii=False, indent=2) + "\n"


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument(
        "--write",
        action="store_true",
        help="Write the generated ledger instead of checking the committed file.",
    )
    args = parser.parse_args()

    ledger = build_ledger()
    errors = validate_ledger(ledger)
    if errors:
        for error in errors:
            print(f"FAIL: {error}", file=sys.stderr)
        return 1
    expected = render_ledger(ledger)

    if args.write:
        LEDGER_PATH.parent.mkdir(parents=True, exist_ok=True)
        LEDGER_PATH.write_text(expected, encoding="utf-8", newline="\n")
        print(f"PASS: wrote {relative_path(LEDGER_PATH)}")
        return 0

    if not LEDGER_PATH.is_file():
        print(
            f"FAIL: {relative_path(LEDGER_PATH)} is missing; run with --write",
            file=sys.stderr,
        )
        return 1
    actual = LEDGER_PATH.read_text(encoding="utf-8")
    if actual != expected:
        print(
            f"FAIL: {relative_path(LEDGER_PATH)} is stale; run with --write",
            file=sys.stderr,
        )
        return 1
    print("PASS: content migration ledger is current")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
