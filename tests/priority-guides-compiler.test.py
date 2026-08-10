#!/usr/bin/env python3
"""Focused dependency-free tests for the priority guides compiler."""

from __future__ import annotations

import copy
import json
import sys
import tempfile
from pathlib import Path
from typing import Callable


ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT / "scripts"))

import build_priority_guides_registry as guides  # noqa: E402


def check(condition: bool, message: str) -> None:
    if not condition:
        raise AssertionError(message)


SOURCE = guides.load_json(guides.DEFAULT_SOURCE)
SEO_SOURCE = guides.load_json(guides.DEFAULT_SEO_REGISTRY)


def compile_mutation(
    mutate: Callable[[dict], None], expected_fragment: str
) -> None:
    candidate = copy.deepcopy(SOURCE)
    mutate(candidate)
    with tempfile.TemporaryDirectory() as temporary:
        source_path = Path(temporary) / "priority-guides.json"
        source_path.write_text(
            json.dumps(candidate, ensure_ascii=False, indent=2),
            encoding="utf-8",
        )
        try:
            guides.compile_registry(source_path=source_path)
        except guides.RegistryError as error:
            check(
                expected_fragment in str(error),
                f"wrong failure for mutation: {error}",
            )
            return
    raise AssertionError(f"mutation did not fail: {expected_fragment}")


def compile_seo_mutation(
    mutate: Callable[[dict], None], expected_fragment: str
) -> None:
    candidate = copy.deepcopy(SEO_SOURCE)
    mutate(candidate)
    with tempfile.TemporaryDirectory() as temporary:
        registry_path = Path(temporary) / "ownership-registry.json"
        registry_path.write_text(
            json.dumps(candidate, ensure_ascii=False, indent=2),
            encoding="utf-8",
        )
        try:
            guides.compile_registry(seo_registry_path=registry_path)
        except guides.RegistryError as error:
            check(
                expected_fragment in str(error),
                f"wrong SEO alignment failure: {error}",
            )
            return
    raise AssertionError(f"SEO mutation did not fail: {expected_fragment}")


def seo_owner(candidate: dict, owner_id: str) -> dict:
    return next(
        owner
        for owner in candidate["intent_owners"]
        if owner["owner_id"] == owner_id
    )


def seo_contextual_requirement(
    candidate: dict,
    owner_id: str,
    field: str,
    target_owner_id: str,
) -> dict:
    return next(
        requirement
        for requirement in seo_owner(candidate, owner_id)[field]
        if requirement["target_owner_id"] == target_owner_id
        and requirement["placement"] == "contextual_body"
    )


first = guides.compile_registry()
second = guides.compile_registry()
check(first.artifact == second.artifact, "compiler output is not deterministic")
check(
    guides.DEFAULT_OUTPUT.read_bytes() == first.artifact,
    "compiled priority guides artifact is stale",
)

registry = first.registry
routes = registry["routes_by_id"]
parents = registry["parents_by_id"]
check(registry["contract_id"] == "thailand-priority-guides-v1", "contract drift")
check(len(routes) == 7, "registry must contain exactly seven routes")
check(
    registry["body_contract"]["stored_wordpress_body"] == "untouched"
    and registry["body_contract"]["wordpress_content_filter"] == "not_called",
    "stored WordPress body contract drift",
)
check(
    registry["asset_contract"]["allowed_asset_keys"]
    == ["visas-entry-thailand-v1", "cannabis-law-thailand-v1"],
    "asset keys drift",
)
check(
    registry["asset_contract"]["widths"] == [720, 1200, 1717],
    "asset widths drift",
)

for route_id, binding in guides.EXPECTED_BINDINGS.items():
    route = routes[route_id]
    check(route["wordpress"]["post_id"] == binding["post_id"], route_id + " ID drift")
    check(route["wordpress"]["post_type"] == binding["post_type"], route_id + " type drift")
    check(route["path"] == binding["path"], route_id + " path drift")
    check(route["parent_owner_id"] == binding["parent"], route_id + " parent drift")
    check(route["kind"] == binding["kind"], route_id + " kind drift")
    check(
        route["wordpress"]["state_policy"] == binding["state_policy"],
        route_id + " state policy drift",
    )

check(
    routes["thailand-visas"]["wordpress"]["post_id"] == 846
    and routes["thailand-law-and-tax"]["wordpress"]["post_id"] == 848,
    "protected collection identities drift",
)
check(
    routes["thailand-entry-april-2022"]["indexing"]
    == {"policy": "noindex", "follow": True, "max_image_preview": "large"},
    "historical indexing policy drift",
)

for route_id, route in routes.items():
    if route["kind"] == "guide":
        parent = routes[route["parent_owner_id"]]
        crumb = route["breadcrumbs"][1]
        check(crumb["path"] == parent["path"], route_id + " breadcrumb path drift")
        check(crumb["availability"] == "managed_draft", route_id + " parent availability drift")

target_anchors = {
    "home": parents["home"]["primary_keyword"],
    **{
        route_id: route["ownership"]["primary_keyword"]
        for route_id, route in routes.items()
    },
}
for route_id, route in routes.items():
    targets = [item["target_owner_id"] for item in route["contextual_links"]]
    check(targets, route_id + " contextual links missing")
    check(len(targets) == len(set(targets)), route_id + " contextual targets duplicate")
    check(
        route["parent_owner_id"] in targets,
        route_id + " canonical parent contextual link missing",
    )
    for item in route["contextual_links"]:
        check(
            item["anchor_text"] == target_anchors[item["target_owner_id"]],
            route_id + " contextual anchor drift",
        )

for hub_id in ("thailand-visas", "thailand-law-and-tax"):
    check(
        "home"
        in {
            item["target_owner_id"]
            for item in routes[hub_id]["contextual_links"]
        },
        hub_id + " home contextual link missing",
    )
check(
    "thailand-entry-requirements"
    in {
        item["target_owner_id"]
        for item in routes["thailand-entry-april-2022"]["contextual_links"]
    },
    "historical route current-entry contextual link missing",
)

check(
    routes["thailand-entry-requirements"]["public"]["h1"]
    == "כניסה לתאילנד לישראלים: ויזה, TDAC וכל הדרישות",
    "entry H1 drift",
)
check(
    routes["thailand-cannabis-law"]["public"]["h1"]
    == "קנאביס בתאילנד: החוק לתיירים, מרשם ואיסורים ב-2026",
    "cannabis H1 drift",
)
check(
    "לעסקים" not in routes["thailand-cannabis-law"]["public"]["meta_description"],
    "visitor cannabis meta owns business intent",
)
check(
    "business-rules"
    not in {
        section["section_id"]
        for section in routes["thailand-cannabis-law"]["sections"]
    },
    "visitor cannabis route contains an operating-business section",
)

all_text = guides.DEFAULT_SOURCE.read_text(encoding="utf-8")
check("\u2013" not in all_text and "\u2014" not in all_text, "forbidden long dash")


def duplicate_term(candidate: dict) -> None:
    candidate["routes"][2]["ownership"]["synonyms"][0] = candidate["routes"][0][
        "ownership"
    ]["primary_keyword"]


def wrong_identity(candidate: dict) -> None:
    candidate["routes"][0]["wordpress"]["post_id"] = 999


def unsafe_asset(candidate: dict) -> None:
    candidate["routes"][0]["asset_key"] = "../../../image"


def index_history(candidate: dict) -> None:
    for route in candidate["routes"]:
        if route["route_id"] == "thailand-entry-april-2022":
            route["indexing"]["policy"] = "index"


def expose_draft(candidate: dict) -> None:
    candidate["routes"][0]["wordpress"]["state_policy"] = "published_only"


def unknown_contextual_target(candidate: dict) -> None:
    candidate["routes"][0]["contextual_links"][0]["target_owner_id"] = (
        "unknown-owner"
    )


def self_contextual_target(candidate: dict) -> None:
    candidate["routes"][0]["contextual_links"][0]["target_owner_id"] = (
        "thailand-visas"
    )
    candidate["routes"][0]["contextual_links"][0]["anchor_text"] = (
        "ויזות לתאילנד"
    )


def duplicate_contextual_target(candidate: dict) -> None:
    duplicate = copy.deepcopy(candidate["routes"][0]["contextual_links"][0])
    duplicate["leading_text"] += " למידע נוסף"
    candidate["routes"][0]["contextual_links"].append(duplicate)


def wrong_contextual_anchor(candidate: dict) -> None:
    candidate["routes"][2]["contextual_links"][0]["anchor_text"] = (
        "מידע על ויזות"
    )


def omit_contextual_parent(candidate: dict) -> None:
    historical = candidate["routes"][3]
    historical["contextual_links"] = [
        item
        for item in historical["contextual_links"]
        if item["target_owner_id"] != historical["parent_owner_id"]
    ]


def omit_current_entry_context(candidate: dict) -> None:
    historical = candidate["routes"][3]
    historical["contextual_links"] = [
        item
        for item in historical["contextual_links"]
        if item["target_owner_id"] != "thailand-entry-requirements"
    ]


def misalign_seo_keyword(candidate: dict) -> None:
    seo_owner(candidate, "thailand-entry-requirements")["primary_keyword"] = (
        "דרישות כניסה אחרות"
    )


def misalign_seo_synonyms(candidate: dict) -> None:
    seo_owner(candidate, "thailand-tourist-visa")["intent_synonyms"][0] = (
        "אשרת ביקור אחרת"
    )


def misalign_seo_intent(candidate: dict) -> None:
    seo_owner(candidate, "thailand-cannabis-law")["primary_intent"] = (
        "לבדוק נושא אחר"
    )


def truncate_seo_hierarchy(candidate: dict) -> None:
    seo_owner(candidate, "thailand-permanent-residence")["breadcrumb_chain"].pop(1)


def expose_seo_draft_hub(candidate: dict) -> None:
    seo_owner(candidate, "thailand-visas")["lifecycle"] = "live"


def miss_current_seo_contextual_target(candidate: dict) -> None:
    requirement = seo_contextual_requirement(
        candidate,
        "thailand-entry-requirements",
        "internal_link_requirements",
        "home",
    )
    requirement["target_owner_id"] = "thailand-cannabis-law"
    requirement["anchor_terms"] = ["קנאביס בתאילנד"]


def miss_planned_seo_contextual_target(candidate: dict) -> None:
    requirement = seo_contextual_requirement(
        candidate,
        "thailand-tourist-visa",
        "planned_internal_link_requirements",
        "thailand-visas",
    )
    requirement["target_owner_id"] = "thailand-law-and-tax"
    requirement["anchor_terms"] = ["חוקים בתאילנד לישראלים"]


def misplace_seo_contextual_target(candidate: dict) -> None:
    requirement = seo_contextual_requirement(
        candidate,
        "thailand-permanent-residence",
        "internal_link_requirements",
        "home",
    )
    requirement["placement"] = "navigation"


def misalign_seo_contextual_anchor(candidate: dict) -> None:
    requirement = seo_contextual_requirement(
        candidate,
        "thailand-cannabis-law",
        "planned_internal_link_requirements",
        "thailand-law-and-tax",
    )
    requirement["anchor_terms"] = ["מידע משפטי אחר"]


def raise_seo_contextual_minimum(candidate: dict) -> None:
    requirement = seo_contextual_requirement(
        candidate,
        "thailand-entry-april-2022",
        "internal_link_requirements",
        "thailand-entry-requirements",
    )
    requirement["minimum_occurrences"] = 2


compile_mutation(duplicate_term, "keyword ownership collision")
compile_mutation(wrong_identity, "route identity drift")
compile_mutation(unsafe_asset, "schema validation failed")
compile_mutation(index_history, "historical noindex route drift")
compile_mutation(expose_draft, "route identity drift")
compile_mutation(unknown_contextual_target, "unknown contextual target")
compile_mutation(self_contextual_target, "self contextual target")
compile_mutation(duplicate_contextual_target, "duplicate contextual target")
compile_mutation(wrong_contextual_anchor, "contextual anchor mismatch")
compile_mutation(omit_contextual_parent, "missing canonical parent contextual link")
compile_mutation(
    omit_current_entry_context,
    "historical route missing current-entry contextual link",
)
compile_seo_mutation(misalign_seo_keyword, "SEO primary keyword differs")
compile_seo_mutation(misalign_seo_synonyms, "SEO synonym set differs")
compile_seo_mutation(misalign_seo_intent, "SEO primary intent differs")
compile_seo_mutation(truncate_seo_hierarchy, "SEO owner hierarchy differs")
compile_seo_mutation(expose_seo_draft_hub, "SEO owner lifecycle differs")
compile_seo_mutation(
    miss_current_seo_contextual_target,
    "missing current SEO contextual target",
)
compile_seo_mutation(
    miss_planned_seo_contextual_target,
    "missing planned SEO contextual target",
)
compile_seo_mutation(
    misplace_seo_contextual_target,
    "SEO contextual placement differs",
)
compile_seo_mutation(
    misalign_seo_contextual_anchor,
    "SEO contextual anchor differs",
)
compile_seo_mutation(
    raise_seo_contextual_minimum,
    "SEO contextual minimum not met",
)

with tempfile.TemporaryDirectory() as temporary:
    duplicate_path = Path(temporary) / "duplicate.json"
    duplicate_path.write_text('{"value":1,"value":2}', encoding="utf-8")
    try:
        guides.load_json(duplicate_path)
    except guides.RegistryError as error:
        check("duplicate JSON key" in str(error), "duplicate-key failure drift")
    else:
        raise AssertionError("duplicate JSON key was accepted")

print("PASS: priority guides compiler tests")
