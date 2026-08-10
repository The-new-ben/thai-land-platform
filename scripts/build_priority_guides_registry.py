#!/usr/bin/env python3
"""Validate and compile the priority guides contract deterministically."""

from __future__ import annotations

import argparse
import hashlib
import json
import sys
import unicodedata
from datetime import date
from pathlib import Path
from typing import Any, Iterable

try:
    from build_content_registry import (
        CompileResult,
        RegistryError,
        SchemaValidator,
        canonical_json,
        iter_strings,
        load_json,
        normalize_term,
        sha256_lf,
    )
except ImportError:
    from scripts.build_content_registry import (  # type: ignore
        CompileResult,
        RegistryError,
        SchemaValidator,
        canonical_json,
        iter_strings,
        load_json,
        normalize_term,
        sha256_lf,
    )


ROOT = Path(__file__).resolve().parents[1]
DEFAULT_SOURCE = ROOT / "data" / "content" / "priority-guides.json"
DEFAULT_SCHEMA = ROOT / "data" / "content" / "priority-guides.schema.json"
DEFAULT_OUTPUT = ROOT / "resources" / "content" / "priority-guides.php"
DEFAULT_SEO_REGISTRY = ROOT / "data" / "seo" / "ownership-registry.json"

EXPECTED_SCHEMA_ID = (
    "https://thai-land.co.il/schemas/content/priority-guides-v1.schema.json"
)
EXPECTED_SCHEMA_SHA256 = (
    "f80326f8f541df9e652df17c375284139e905321bdccec125f591393826a39c3"
)
EXPECTED_CONTRACT_ID = "thailand-priority-guides-v1"
EXPECTED_ASSET_KEYS = (
    "visas-entry-thailand-v1",
    "cannabis-law-thailand-v1",
)
EXPECTED_WIDTHS = (720, 1200, 1717)
EXPECTED_BINDINGS: dict[str, dict[str, Any]] = {
    "thailand-visas": {
        "post_id": 846,
        "post_type": "page",
        "path": "/ויזות-לתאילנד/",
        "parent": "home",
        "kind": "collection",
        "state_policy": "draft_canary_or_published_live",
    },
    "thailand-law-and-tax": {
        "post_id": 848,
        "post_type": "page",
        "path": "/חוקים-ומסים-בתאילנד/",
        "parent": "home",
        "kind": "collection",
        "state_policy": "draft_canary_or_published_live",
    },
    "thailand-entry-requirements": {
        "post_id": 1,
        "post_type": "post",
        "path": "/hello-world/",
        "parent": "thailand-visas",
        "kind": "guide",
        "state_policy": "published_only",
    },
    "thailand-entry-april-2022": {
        "post_id": 62,
        "post_type": "post",
        "path": "/החל-מאפריל-2022-מטיילים-יורשו-להיכנס-לתאי/",
        "parent": "thailand-visas",
        "kind": "guide",
        "state_policy": "published_only",
    },
    "thailand-cannabis-law": {
        "post_id": 102,
        "post_type": "post",
        "path": "/תאילנד-וחוק-אי-הפללת-קנאביס-פנאי/",
        "parent": "thailand-law-and-tax",
        "kind": "guide",
        "state_policy": "published_only",
    },
    "thailand-tourist-visa": {
        "post_id": 243,
        "post_type": "post",
        "path": "/ויזת-תיירים-תאילנד/",
        "parent": "thailand-visas",
        "kind": "guide",
        "state_policy": "published_only",
    },
    "thailand-permanent-residence": {
        "post_id": 132,
        "post_type": "post",
        "path": "/permanent-residence-thailand/",
        "parent": "thailand-visas",
        "kind": "guide",
        "state_policy": "published_only",
    },
}
EXPECTED_TITLES = {
    "thailand-entry-requirements": (
        "כניסה לתאילנד לישראלים: ויזה, TDAC וכל הדרישות"
    ),
    "thailand-cannabis-law": (
        "קנאביס בתאילנד: החוק לתיירים, מרשם ואיסורים ב-2026"
    ),
    "thailand-tourist-visa": (
        "ויזת תייר לתאילנד לישראלים: תנאים, מסמכים והגשה"
    ),
    "thailand-permanent-residence": (
        "תושבות קבע בתאילנד: תנאים, מכסה, עלויות ותהליך"
    ),
}
FORBIDDEN_CODEPOINTS = {
    "\u200b": "zero width space",
    "\u2013": "en dash",
    "\u2014": "em dash",
}
FORBIDDEN_PUBLIC_PHRASES = {
    "טיוטה",
    "תוכן זמני",
    "טרם אומת",
    "לא אומת",
    "לשימוש פנימי",
    "הפרויקט שלנו",
    "placeholder",
    "verified",
    "unverified",
}


def parse_iso_date(value: str, label: str) -> date:
    """Return a strict calendar date or fail with a useful label."""
    try:
        return date.fromisoformat(value)
    except ValueError as error:
        raise RegistryError(f"invalid date for {label}: {value}") from error


def public_values(source: dict[str, Any]) -> Iterable[str]:
    """Yield only contract values that may be rendered to visitors."""
    yield from iter_strings(source["parent_catalog"])
    yield from iter_strings(source["source_catalog"])
    for route in source["routes"]:
        for field in (
            "public",
            "breadcrumbs",
            "sections",
            "faqs",
            "contextual_links",
        ):
            yield from iter_strings(route[field])


def validate_schema_contract(schema: dict[str, Any], schema_path: Path) -> str:
    """Pin compilation to the reviewed schema bytes."""
    if schema.get("$id") != EXPECTED_SCHEMA_ID:
        raise RegistryError("schema ID drift")
    if schema.get("properties", {}).get("schema_version", {}).get("const") != 1:
        raise RegistryError("schema version drift")
    if (
        schema.get("properties", {}).get("contract_id", {}).get("const")
        != EXPECTED_CONTRACT_ID
    ):
        raise RegistryError("schema contract ID drift")
    digest = sha256_lf(schema_path)
    if digest != EXPECTED_SCHEMA_SHA256:
        raise RegistryError(
            f"schema drift: expected {EXPECTED_SCHEMA_SHA256}, got {digest}"
        )
    return digest


def validate_text_policy(source: dict[str, Any]) -> None:
    """Reject forbidden typography everywhere and build language in public copy."""
    for value in iter_strings(source):
        for character, label in FORBIDDEN_CODEPOINTS.items():
            if character in value:
                raise RegistryError(f"forbidden {label} in guides contract")
    for value in public_values(source):
        folded = unicodedata.normalize("NFKC", value).casefold()
        for phrase in FORBIDDEN_PUBLIC_PHRASES:
            if phrase.casefold() in folded:
                raise RegistryError(f"forbidden public phrase: {phrase}")


def validate_contracts(source: dict[str, Any]) -> None:
    """Require exact body and image readiness contracts."""
    if source["body_contract"] != {
        "mode": "full_replacement",
        "content_source": "compiled_priority_guides_registry",
        "stored_wordpress_body": "untouched",
        "wordpress_content_filter": "not_called",
        "public_punctuation_policy": "replace_long_dashes_with_hyphen",
    }:
        raise RegistryError("stored WordPress body contract drift")
    if source["asset_contract"] != {
        "directory": "assets/guides/images",
        "filename_pattern": "{asset_key}-{width}.webp",
        "format": "webp",
        "widths": list(EXPECTED_WIDTHS),
        "allowed_asset_keys": list(EXPECTED_ASSET_KEYS),
        "readiness": "all_variants_required",
    }:
        raise RegistryError("guide asset contract drift")


def unique_catalog(
    items: list[dict[str, Any]], key: str, label: str
) -> dict[str, dict[str, Any]]:
    """Return a sorted catalog and reject duplicate IDs."""
    result: dict[str, dict[str, Any]] = {}
    for item in items:
        item_id = item[key]
        if item_id in result:
            raise RegistryError(f"duplicate {label}: {item_id}")
        result[item_id] = item
    return {item_id: result[item_id] for item_id in sorted(result)}


def validate_catalogs(
    source: dict[str, Any],
) -> tuple[dict[str, dict[str, Any]], dict[str, dict[str, Any]]]:
    """Validate parent and official source catalogs."""
    parents = unique_catalog(source["parent_catalog"], "owner_id", "parent owner")
    sources = unique_catalog(source["source_catalog"], "source_id", "source")
    expected_parents = {
        "home": ("/", "live", "תאילנד"),
        "thailand-visas": (
            "/ויזות-לתאילנד/",
            "managed_draft",
            "ויזות לתאילנד",
        ),
        "thailand-law-and-tax": (
            "/חוקים-ומסים-בתאילנד/",
            "managed_draft",
            "חוקים בתאילנד לישראלים",
        ),
    }
    actual_parents = {
        key: (item["path"], item["availability"], item["primary_keyword"])
        for key, item in parents.items()
    }
    if actual_parents != expected_parents:
        raise RegistryError("parent catalog identity drift")

    seen_urls: set[str] = set()
    for source_id, item in sources.items():
        if item["url"] in seen_urls:
            raise RegistryError(f"duplicate source URL: {item['url']}")
        seen_urls.add(item["url"])
        if parse_iso_date(item["checked_on"], source_id) > date(2026, 8, 10):
            raise RegistryError(f"source checked in the future: {source_id}")
    return parents, sources


def validate_breadcrumbs(
    route: dict[str, Any], parents: dict[str, dict[str, Any]]
) -> None:
    """Require one real tree path from home through the direct parent."""
    route_id = route["route_id"]
    breadcrumbs = route["breadcrumbs"]
    expected_length = 2 if route["kind"] == "collection" else 3
    if len(breadcrumbs) != expected_length:
        raise RegistryError(f"breadcrumb length mismatch for {route_id}")
    if breadcrumbs[0] != {
        "label": "דף הבית",
        "path": "/",
        "availability": "live",
        "current": False,
    }:
        raise RegistryError(f"home breadcrumb mismatch for {route_id}")
    current = breadcrumbs[-1]
    expected_current_availability = (
        parents[route_id]["availability"]
        if route["kind"] == "collection"
        else "live"
    )
    if (
        current["path"] != route["path"]
        or current["current"] is not True
        or current["availability"] != expected_current_availability
    ):
        raise RegistryError(f"current breadcrumb mismatch for {route_id}")
    if route["kind"] == "guide":
        parent = parents[route["parent_owner_id"]]
        middle = breadcrumbs[1]
        if middle != {
            "label": parent["label"],
            "path": parent["path"],
            "availability": parent["availability"],
            "current": False,
        }:
            raise RegistryError(f"parent breadcrumb mismatch for {route_id}")


def validate_routes(
    source: dict[str, Any],
    parents: dict[str, dict[str, Any]],
    sources: dict[str, dict[str, Any]],
) -> dict[str, dict[str, Any]]:
    """Validate exact identities, ownership, dates, hierarchy, and links."""
    routes = source["routes"]
    if len(routes) != len(EXPECTED_BINDINGS):
        raise RegistryError(f"expected seven routes, got {len(routes)}")
    routes_by_id = unique_catalog(routes, "route_id", "route")
    if set(routes_by_id) != set(EXPECTED_BINDINGS):
        raise RegistryError("guide route set drift")

    paths: dict[str, str] = {}
    post_ids: dict[int, str] = {}
    owners: dict[str, str] = {}
    terms: dict[str, str] = {}
    meta_descriptions: dict[str, str] = {}
    for route_id, route in routes_by_id.items():
        binding = EXPECTED_BINDINGS[route_id]
        wordpress = route["wordpress"]
        actual = {
            "post_id": wordpress["post_id"],
            "post_type": wordpress["post_type"],
            "path": route["path"],
            "parent": route["parent_owner_id"],
            "kind": route["kind"],
            "state_policy": wordpress["state_policy"],
        }
        if actual != binding:
            raise RegistryError(
                f"route identity drift for {route_id}: expected {binding!r}, got {actual!r}"
            )
        if route["seo_owner_id"] != route_id:
            raise RegistryError(f"SEO owner mismatch for {route_id}")
        if wordpress["identity_policy"] != "id_and_path_exact":
            raise RegistryError(f"identity policy mismatch for {route_id}")
        if wordpress["body_mode"] != "full_replacement":
            raise RegistryError(f"body mode mismatch for {route_id}")
        if route["asset_key"] not in EXPECTED_ASSET_KEYS:
            raise RegistryError(f"invalid asset key for {route_id}")
        if route["path"] in paths:
            raise RegistryError(f"duplicate route path: {route['path']}")
        if wordpress["post_id"] in post_ids:
            raise RegistryError(f"duplicate WordPress post ID: {wordpress['post_id']}")
        if route["seo_owner_id"] in owners:
            raise RegistryError(f"duplicate SEO owner: {route['seo_owner_id']}")
        paths[route["path"]] = route_id
        post_ids[wordpress["post_id"]] = route_id
        owners[route["seo_owner_id"]] = route_id

        route_terms = [
            route["ownership"]["primary_keyword"],
            *route["ownership"]["synonyms"],
        ]
        normalized_route_terms: set[str] = set()
        for term in route_terms:
            normalized = normalize_term(term)
            if not normalized or normalized in normalized_route_terms:
                raise RegistryError(f"duplicate ownership term inside {route_id}: {term}")
            if normalized in terms:
                raise RegistryError(
                    f"keyword ownership collision: {term!r} on {terms[normalized]} and {route_id}"
                )
            normalized_route_terms.add(normalized)
            terms[normalized] = route_id
        for field in ("h1", "seo_title"):
            title = normalize_term(route["public"][field])
            if not any(title.startswith(term) for term in normalized_route_terms):
                raise RegistryError(f"{field} is not keyword-led for {route_id}")
        meta = normalize_term(route["public"]["meta_description"])
        if meta in meta_descriptions:
            raise RegistryError(f"duplicate meta description for {route_id}")
        meta_descriptions[meta] = route_id

        if route_id in EXPECTED_TITLES and route["public"]["h1"] != EXPECTED_TITLES[route_id]:
            raise RegistryError(f"reviewed H1 drift for {route_id}")

        published = parse_iso_date(route["published_on"], route_id + " published")
        modified = parse_iso_date(route["modified_on"], route_id + " modified")
        checked = parse_iso_date(route["freshness"]["checked_on"], route_id + " checked")
        next_review = parse_iso_date(
            route["freshness"]["next_review_on"], route_id + " next review"
        )
        if published > modified or checked > next_review:
            raise RegistryError(f"date ordering mismatch for {route_id}")
        if route["freshness"]["review_interval_days"] <= 0:
            raise RegistryError(f"invalid review interval for {route_id}")

        section_ids: set[str] = set()
        for section in route["sections"]:
            if section["section_id"] in section_ids:
                raise RegistryError(f"duplicate section ID for {route_id}")
            section_ids.add(section["section_id"])
        questions = [normalize_term(item["question"]) for item in route["faqs"]]
        if len(questions) != len(set(questions)):
            raise RegistryError(f"duplicate visible question for {route_id}")

        for source_id in route["source_ids"]:
            if source_id not in sources:
                raise RegistryError(f"unknown source {source_id} on {route_id}")
        validate_breadcrumbs(route, parents)

    for route_id, route in routes_by_id.items():
        for related_id in route["related_route_ids"]:
            if related_id == route_id or related_id not in routes_by_id:
                raise RegistryError(f"invalid related route {related_id} on {route_id}")

    target_keywords = {
        "home": parents["home"]["primary_keyword"],
        **{
            route_id: route["ownership"]["primary_keyword"]
            for route_id, route in routes_by_id.items()
        },
    }
    for hub_id in ("thailand-visas", "thailand-law-and-tax"):
        if parents[hub_id]["primary_keyword"] != target_keywords[hub_id]:
            raise RegistryError(f"parent primary keyword mismatch for {hub_id}")

    for route_id, route in routes_by_id.items():
        seen_targets: set[str] = set()
        for item in route["contextual_links"]:
            target_owner_id = item["target_owner_id"]
            if target_owner_id not in target_keywords:
                raise RegistryError(
                    f"unknown contextual target {target_owner_id} on {route_id}"
                )
            if target_owner_id == route_id:
                raise RegistryError(f"self contextual target on {route_id}")
            if target_owner_id in seen_targets:
                raise RegistryError(
                    f"duplicate contextual target {target_owner_id} on {route_id}"
                )
            seen_targets.add(target_owner_id)
            if item["anchor_text"] != target_keywords[target_owner_id]:
                raise RegistryError(
                    f"contextual anchor mismatch for {target_owner_id} on {route_id}"
                )
        if route["parent_owner_id"] not in seen_targets:
            raise RegistryError(f"missing canonical parent contextual link on {route_id}")

    historical_targets = {
        item["target_owner_id"]
        for item in routes_by_id["thailand-entry-april-2022"]["contextual_links"]
    }
    if "thailand-entry-requirements" not in historical_targets:
        raise RegistryError("historical route missing current-entry contextual link")

    noindex = {
        route_id
        for route_id, route in routes_by_id.items()
        if route["indexing"]["policy"] == "noindex"
    }
    if noindex != {"thailand-entry-april-2022"}:
        raise RegistryError("historical noindex route drift")
    historical = {
        route_id
        for route_id, route in routes_by_id.items()
        if route["freshness"]["historical"]
    }
    if historical != {"thailand-entry-april-2022"}:
        raise RegistryError("historical route set drift")
    for route_id, route in routes_by_id.items():
        if route["indexing"]["follow"] is not True:
            raise RegistryError(f"follow policy mismatch for {route_id}")

    for hub_id in ("thailand-visas", "thailand-law-and-tax"):
        direct_children = {
            route_id
            for route_id, route in routes_by_id.items()
            if route["parent_owner_id"] == hub_id
        }
        if not direct_children.issubset(set(routes_by_id[hub_id]["related_route_ids"])):
            raise RegistryError(f"collection is missing a direct child: {hub_id}")

    cannabis = routes_by_id["thailand-cannabis-law"]
    if "business-rules" in {
        section["section_id"] for section in cannabis["sections"]
    }:
        raise RegistryError("visitor cannabis owner contains business operations section")
    if "לעסקים" in cannabis["public"]["meta_description"]:
        raise RegistryError("visitor cannabis meta owns business intent")
    return routes_by_id


def validate_seo_alignment(
    routes_by_id: dict[str, dict[str, Any]],
    seo_registry_path: Path,
) -> str:
    """Confirm the complete Guides ownership contract in the SEO registry."""
    registry = load_json(seo_registry_path)
    owner_items = registry.get("intent_owners", [])
    if not isinstance(owner_items, list):
        raise RegistryError("SEO intent owners must be an array")
    for field in ("owner_id", "canonical_url", "intent_id", "primary_intent"):
        values = [
            item.get(field)
            for item in owner_items
            if isinstance(item, dict)
        ]
        if (
            len(values) != len(owner_items)
            or any(not isinstance(value, str) or not value for value in values)
            or len(values) != len(set(values))
        ):
            raise RegistryError(f"SEO owner {field} values must be unique strings")
    owners = {
        item["owner_id"]: item
        for item in owner_items
        if isinstance(item, dict) and isinstance(item.get("owner_id"), str)
    }
    for route_id, route in routes_by_id.items():
        owner = owners.get(route["seo_owner_id"])
        if owner is None:
            raise RegistryError(f"missing SEO owner for {route_id}")
        if owner.get("canonical_url") != route["path"]:
            raise RegistryError(f"canonical URL differs for {route_id}")
        if owner.get("parent_owner_id") != route["parent_owner_id"]:
            raise RegistryError(f"SEO parent differs for {route_id}")
        expected_lifecycle = "planned" if route["kind"] == "collection" else "live"
        if owner.get("lifecycle") != expected_lifecycle:
            raise RegistryError(f"SEO owner lifecycle differs for {route_id}")
        ownership = route["ownership"]
        if owner.get("primary_keyword") != ownership["primary_keyword"]:
            raise RegistryError(f"SEO primary keyword differs for {route_id}")
        actual_synonyms = owner.get("intent_synonyms")
        expected_synonyms = ownership["synonyms"]
        if (
            not isinstance(actual_synonyms, list)
            or any(not isinstance(item, str) for item in actual_synonyms)
            or len(actual_synonyms) != len(expected_synonyms)
            or set(actual_synonyms) != set(expected_synonyms)
        ):
            raise RegistryError(f"SEO synonym set differs for {route_id}")
        if owner.get("primary_intent") != ownership["intent"]:
            raise RegistryError(f"SEO primary intent differs for {route_id}")

        expected_owner_ids = (
            ["home", route["seo_owner_id"]]
            if route["kind"] == "collection"
            else ["home", route["parent_owner_id"], route["seo_owner_id"]]
        )
        expected_chain = list(
            zip(
                expected_owner_ids,
                [breadcrumb["path"] for breadcrumb in route["breadcrumbs"]],
            )
        )
        actual_breadcrumbs = owner.get("breadcrumb_chain")
        if not isinstance(actual_breadcrumbs, list) or any(
            not isinstance(item, dict) for item in actual_breadcrumbs
        ):
            raise RegistryError(f"SEO owner hierarchy differs for {route_id}")
        actual_chain = [
            (item.get("owner_id"), item.get("url"))
            for item in actual_breadcrumbs
        ]
        if actual_chain != expected_chain:
            raise RegistryError(f"SEO owner hierarchy differs for {route_id}")

        authored_by_target = {
            item["target_owner_id"]: item
            for item in route["contextual_links"]
        }
        contextual_requirements: dict[str, tuple[dict[str, Any], str]] = {}
        all_requirement_placements: dict[str, set[str]] = {}
        for field, requirement_state in (
            ("internal_link_requirements", "current"),
            ("planned_internal_link_requirements", "planned"),
        ):
            requirements = owner.get(field)
            if not isinstance(requirements, list) or any(
                not isinstance(item, dict) for item in requirements
            ):
                raise RegistryError(
                    f"SEO {requirement_state} link requirements differ for {route_id}"
                )
            for requirement in requirements:
                target_owner_id = requirement.get("target_owner_id")
                placement = requirement.get("placement")
                if isinstance(target_owner_id, str) and isinstance(placement, str):
                    all_requirement_placements.setdefault(
                        target_owner_id, set()
                    ).add(placement)
                if placement != "contextual_body":
                    continue
                if (
                    not isinstance(target_owner_id, str)
                    or not target_owner_id
                    or target_owner_id not in owners
                ):
                    raise RegistryError(
                        f"SEO contextual target differs for {route_id}"
                    )
                if target_owner_id in contextual_requirements:
                    raise RegistryError(
                        f"duplicate SEO contextual target {target_owner_id} on {route_id}"
                    )
                minimum = requirement.get("minimum_occurrences")
                if type(minimum) is not int or minimum < 1:
                    raise RegistryError(
                        f"SEO contextual minimum differs for {target_owner_id} on {route_id}"
                    )
                anchor_terms = requirement.get("anchor_terms")
                if (
                    not isinstance(anchor_terms, list)
                    or len(anchor_terms) != 1
                    or not isinstance(anchor_terms[0], str)
                    or not anchor_terms[0]
                ):
                    raise RegistryError(
                        f"SEO contextual anchor terms differ for {target_owner_id} on {route_id}"
                    )
                contextual_requirements[target_owner_id] = (
                    requirement,
                    requirement_state,
                )

        for target_owner_id, (requirement, requirement_state) in (
            contextual_requirements.items()
        ):
            matches = [
                item
                for item in route["contextual_links"]
                if item["target_owner_id"] == target_owner_id
            ]
            if not matches:
                raise RegistryError(
                    f"missing {requirement_state} SEO contextual target "
                    f"{target_owner_id} on {route_id}"
                )
            if len(matches) < requirement["minimum_occurrences"]:
                raise RegistryError(
                    f"SEO contextual minimum not met for {target_owner_id} on {route_id}"
                )
            if any(
                item["anchor_text"] not in requirement["anchor_terms"]
                for item in matches
            ):
                raise RegistryError(
                    f"SEO contextual anchor differs for {target_owner_id} on {route_id}"
                )

        for target_owner_id in authored_by_target:
            if target_owner_id in contextual_requirements:
                continue
            if target_owner_id in all_requirement_placements:
                raise RegistryError(
                    f"SEO contextual placement differs for {target_owner_id} on {route_id}"
                )
            raise RegistryError(
                f"unbound SEO contextual target {target_owner_id} on {route_id}"
            )
    return sha256_lf(seo_registry_path)


def build_runtime_registry(
    source: dict[str, Any],
    routes_by_id: dict[str, dict[str, Any]],
    parents: dict[str, dict[str, Any]],
    sources: dict[str, dict[str, Any]],
    source_digest: str,
    schema_digest: str,
    seo_digest: str,
) -> dict[str, Any]:
    """Build sorted lookup indexes while preserving authored route content."""
    ordered_routes = {
        route_id: routes_by_id[route_id] for route_id in sorted(routes_by_id)
    }
    children: dict[str, list[str]] = {}
    for parent_id in sorted(parents):
        values = sorted(
            route_id
            for route_id, route in routes_by_id.items()
            if route["parent_owner_id"] == parent_id
        )
        if values:
            children[parent_id] = values
    registry: dict[str, Any] = {
        "schema_version": source["schema_version"],
        "contract_id": source["contract_id"],
        "source_sha256": source_digest,
        "schema_sha256": schema_digest,
        "seo_registry_sha256": seo_digest,
        "site": source["site"],
        "body_contract": source["body_contract"],
        "asset_contract": source["asset_contract"],
        "parents_by_id": parents,
        "sources_by_id": sources,
        "routes_by_id": ordered_routes,
        "route_id_by_post_id": {
            str(route["wordpress"]["post_id"]): route_id
            for route_id, route in sorted(
                routes_by_id.items(), key=lambda item: item[1]["wordpress"]["post_id"]
            )
        },
        "route_id_by_path": {
            route["path"]: route_id
            for route_id, route in sorted(
                routes_by_id.items(), key=lambda item: item[1]["path"]
            )
        },
        "route_id_by_seo_owner_id": {
            route["seo_owner_id"]: route_id
            for route_id, route in sorted(
                routes_by_id.items(), key=lambda item: item[1]["seo_owner_id"]
            )
        },
        "children_by_parent": children,
    }
    registry["registry_sha256"] = hashlib.sha256(canonical_json(registry)).hexdigest()
    return registry


def render_php(registry: dict[str, Any]) -> bytes:
    """Render one guarded PHP artifact with a deterministic JSON payload."""
    payload = json.dumps(
        registry,
        ensure_ascii=False,
        sort_keys=True,
        indent=2,
        allow_nan=False,
    )
    document = f"""<?php
/**
 * Generated priority guides registry.
 *
 * Run scripts/build_priority_guides_registry.py to rebuild this file.
 */

if ( ! defined( 'ABSPATH' ) ) {{
\texit;
}}

return json_decode(
\t<<<'THAILAND_PLATFORM_GUIDES_JSON'
{payload}
THAILAND_PLATFORM_GUIDES_JSON,
\ttrue,
\t512,
\tJSON_THROW_ON_ERROR
);
"""
    return document.encode("utf-8")


def compile_registry(
    source_path: Path = DEFAULT_SOURCE,
    schema_path: Path = DEFAULT_SCHEMA,
    seo_registry_path: Path = DEFAULT_SEO_REGISTRY,
) -> CompileResult:
    """Compile only after every schema and semantic check passes."""
    source_path = Path(source_path)
    schema_path = Path(schema_path)
    seo_registry_path = Path(seo_registry_path)
    source = load_json(source_path)
    schema = load_json(schema_path)
    schema_digest = validate_schema_contract(schema, schema_path)
    errors = SchemaValidator(schema).validate(source)
    if errors:
        raise RegistryError("schema validation failed: " + "; ".join(errors))
    if source["contract_id"] != EXPECTED_CONTRACT_ID:
        raise RegistryError("guides contract ID mismatch")
    validate_text_policy(source)
    validate_contracts(source)
    parents, sources = validate_catalogs(source)
    routes = validate_routes(source, parents, sources)
    seo_digest = validate_seo_alignment(routes, seo_registry_path)
    registry = build_runtime_registry(
        source,
        routes,
        parents,
        sources,
        sha256_lf(source_path),
        schema_digest,
        seo_digest,
    )
    return CompileResult(registry=registry, artifact=render_php(registry))


def parse_args(argv: list[str] | None = None) -> argparse.Namespace:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--source", type=Path, default=DEFAULT_SOURCE)
    parser.add_argument("--schema", type=Path, default=DEFAULT_SCHEMA)
    parser.add_argument("--seo-registry", type=Path, default=DEFAULT_SEO_REGISTRY)
    parser.add_argument("--output", type=Path, default=DEFAULT_OUTPUT)
    parser.add_argument(
        "--check",
        action="store_true",
        help="fail when the compiled artifact is missing or stale",
    )
    return parser.parse_args(argv)


def main(argv: list[str] | None = None) -> int:
    arguments = parse_args(argv)
    try:
        result = compile_registry(
            arguments.source,
            arguments.schema,
            arguments.seo_registry,
        )
        if arguments.check:
            try:
                current = arguments.output.read_bytes()
            except OSError as error:
                raise RegistryError(
                    f"compiled guides registry is missing: {arguments.output}: {error}"
                ) from error
            if current != result.artifact:
                raise RegistryError(f"compiled guides registry is stale: {arguments.output}")
            print(f"PASS: priority guides registry is current: {arguments.output}")
            return 0

        arguments.output.parent.mkdir(parents=True, exist_ok=True)
        arguments.output.write_bytes(result.artifact)
        print(f"WROTE: {arguments.output}")
        print(f"SHA256: {hashlib.sha256(result.artifact).hexdigest()}")
        return 0
    except RegistryError as error:
        print(f"ERROR: {error}", file=sys.stderr)
        return 1


if __name__ == "__main__":
    raise SystemExit(main())
