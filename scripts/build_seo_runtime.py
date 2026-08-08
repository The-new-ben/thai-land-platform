#!/usr/bin/env python3
"""Compile deterministic SEO migration gates for the WordPress runtime."""

from __future__ import annotations

import argparse
import hashlib
import json
import sys
from pathlib import Path
from typing import Any


ROOT = Path(__file__).resolve().parents[1]
DEFAULT_REGISTRY = ROOT / "data" / "seo" / "ownership-registry.json"
DEFAULT_OUTPUT = ROOT / "resources" / "seo" / "migration-gates.php"
EXPECTED_GATE_COUNT = 2


class ContractError(ValueError):
    """Raised when registry data cannot produce a safe runtime contract."""


def reject_duplicate_keys(pairs: list[tuple[str, Any]]) -> dict[str, Any]:
    result: dict[str, Any] = {}
    for key, value in pairs:
        if key in result:
            raise ContractError(f"Duplicate JSON key: {key}")
        result[key] = value
    return result


def reject_non_finite(value: str) -> None:
    raise ContractError(f"Non-finite JSON number: {value}")


def require_mapping(value: Any, label: str) -> dict[str, Any]:
    if not isinstance(value, dict):
        raise ContractError(f"{label} must be an object")
    return value


def require_list(value: Any, label: str) -> list[Any]:
    if not isinstance(value, list):
        raise ContractError(f"{label} must be an array")
    return value


def require_string(value: Any, label: str) -> str:
    if not isinstance(value, str) or not value:
        raise ContractError(f"{label} must be a non-empty string")
    if any(ord(character) < 32 for character in value):
        raise ContractError(f"{label} contains a control character")
    return value


def load_registry(path: Path) -> tuple[dict[str, Any], bytes]:
    try:
        payload = path.read_bytes()
    except OSError as error:
        raise ContractError(f"Could not read registry: {path}") from error

    try:
        text = payload.decode("utf-8")
    except UnicodeDecodeError as error:
        raise ContractError("SEO ownership registry must be UTF-8") from error

    try:
        registry = json.loads(
            text,
            object_pairs_hook=reject_duplicate_keys,
            parse_constant=reject_non_finite,
        )
    except json.JSONDecodeError as error:
        raise ContractError(f"Invalid SEO ownership registry JSON: {error}") from error

    return require_mapping(registry, "registry"), payload


def compile_contract(registry: dict[str, Any], source_payload: bytes) -> dict[str, Any]:
    registry_version = require_string(
        registry.get("registry_version"), "registry.registry_version"
    )
    owners = require_list(registry.get("intent_owners"), "registry.intent_owners")
    routes = require_list(registry.get("routes"), "registry.routes")

    owners_by_id: dict[str, dict[str, Any]] = {}
    for index, raw_owner in enumerate(owners):
        owner = require_mapping(raw_owner, f"intent_owners[{index}]")
        owner_id = require_string(owner.get("owner_id"), f"intent_owners[{index}].owner_id")
        if owner_id in owners_by_id:
            raise ContractError(f"Duplicate owner_id: {owner_id}")
        require_string(
            owner.get("canonical_url"), f"intent_owners[{index}].canonical_url"
        )
        owners_by_id[owner_id] = owner

    gates_by_path: dict[str, dict[str, Any]] = {}
    route_ids: set[str] = set()

    for index, raw_route in enumerate(routes):
        route = require_mapping(raw_route, f"routes[{index}]")
        assignment = require_mapping(
            route.get("assignment"), f"routes[{index}].assignment"
        )
        if assignment.get("kind") != "migration_gate":
            continue

        route_id = require_string(route.get("route_id"), f"routes[{index}].route_id")
        route_path = require_string(route.get("url"), f"routes[{index}].url")
        if route.get("route_kind") != "exact":
            raise ContractError(f"Migration gate {route_id} must use an exact route")
        if route.get("lifecycle") != "live":
            raise ContractError(f"Migration gate {route_id} must remain live")
        if not route_path.startswith("/") or not route_path.endswith("/"):
            raise ContractError(f"Migration gate {route_id} must use a rooted slash path")
        if route_id in route_ids:
            raise ContractError(f"Duplicate migration gate route_id: {route_id}")
        if route_path in gates_by_path:
            raise ContractError(f"Duplicate migration gate path: {route_path}")

        current_owner_id = require_string(
            assignment.get("current_owner_id"),
            f"routes[{index}].assignment.current_owner_id",
        )
        if current_owner_id not in owners_by_id:
            raise ContractError(
                f"Migration gate {route_id} has unknown current owner {current_owner_id}"
            )

        candidate = assignment.get("candidate_owner_id")
        if candidate is not None:
            candidate = require_string(
                candidate, f"routes[{index}].assignment.candidate_owner_id"
            )
            if candidate not in owners_by_id:
                raise ContractError(
                    f"Migration gate {route_id} has unknown candidate {candidate}"
                )

        state = require_string(
            assignment.get("state"), f"routes[{index}].assignment.state"
        )
        if assignment.get("release_blocked") is not True:
            raise ContractError(f"Migration gate {route_id} must be release blocked")

        indexing_policy = require_string(
            route.get("indexing_policy"), f"routes[{index}].indexing_policy"
        )
        if indexing_policy not in {"index", "redirect"}:
            raise ContractError(
                f"Migration gate {route_id} has unsupported indexing policy {indexing_policy}"
            )

        current_canonical = require_string(
            owners_by_id[current_owner_id].get("canonical_url"),
            f"owner {current_owner_id}.canonical_url",
        )
        redirect_target = route.get("redirect_target")
        if indexing_policy == "redirect":
            expected_redirect_target = require_string(
                redirect_target, f"routes[{index}].redirect_target"
            )
            if expected_redirect_target != current_canonical:
                raise ContractError(
                    f"Migration gate {route_id} must redirect to its current owner"
                )
            if expected_redirect_target == route_path:
                raise ContractError(f"Migration gate {route_id} redirects to itself")
        else:
            if redirect_target is not None:
                raise ContractError(
                    f"Indexed migration gate {route_id} cannot declare a redirect target"
                )
            if route_path != current_canonical:
                raise ContractError(
                    f"Indexed migration gate {route_id} must be self-canonical"
                )
            expected_redirect_target = None

        route_ids.add(route_id)
        gates_by_path[route_path] = {
            "route_id": route_id,
            "current_owner_id": current_owner_id,
            "candidate": candidate,
            "state": state,
            "release_blocked": True,
            "indexing_policy": indexing_policy,
            "expected_redirect_target": expected_redirect_target,
        }

    if len(gates_by_path) != EXPECTED_GATE_COUNT:
        raise ContractError(
            f"Expected {EXPECTED_GATE_COUNT} migration gates, found {len(gates_by_path)}"
        )

    return {
        "contract_version": "1.0.0",
        "source_registry_version": registry_version,
        "source_sha256": hashlib.sha256(source_payload).hexdigest(),
        "migration_gates": {
            route_path: gates_by_path[route_path]
            for route_path in sorted(gates_by_path)
        },
    }


def php_string(value: str) -> str:
    if any(ord(character) < 32 for character in value):
        raise ContractError("Runtime contract strings cannot contain control characters")
    return "'" + value.replace("\\", "\\\\").replace("'", "\\'") + "'"


def php_scalar(value: Any) -> str:
    if value is None:
        return "null"
    if value is True:
        return "true"
    if value is False:
        return "false"
    if isinstance(value, str):
        return php_string(value)
    raise ContractError(f"Unsupported PHP scalar type: {type(value).__name__}")


def render_php(contract: dict[str, Any]) -> bytes:
    lines = [
        "<?php",
        "declare(strict_types=1);",
        "",
        "/**",
        " * Generated by scripts/build_seo_runtime.py.",
        " * Source: data/seo/ownership-registry.json.",
        " * Do not edit this file directly.",
        " */",
        "",
        "return array(",
        f"    'contract_version' => {php_scalar(contract['contract_version'])},",
        "    'source' => array(",
        f"        'registry_version' => {php_scalar(contract['source_registry_version'])},",
        f"        'sha256' => {php_scalar(contract['source_sha256'])},",
        "    ),",
        "    'migration_gates' => array(",
    ]

    gates = require_mapping(contract.get("migration_gates"), "migration_gates")
    for route_path, raw_gate in gates.items():
        gate = require_mapping(raw_gate, f"migration_gates[{route_path}]")
        lines.extend(
            [
                f"        {php_string(route_path)} => array(",
                f"            'route_id' => {php_scalar(gate['route_id'])},",
                f"            'current_owner_id' => {php_scalar(gate['current_owner_id'])},",
                f"            'candidate' => {php_scalar(gate['candidate'])},",
                f"            'state' => {php_scalar(gate['state'])},",
                f"            'release_blocked' => {php_scalar(gate['release_blocked'])},",
                f"            'indexing_policy' => {php_scalar(gate['indexing_policy'])},",
                "            'expected_redirect_target' => "
                f"{php_scalar(gate['expected_redirect_target'])},",
                "        ),",
            ]
        )

    lines.extend(
        [
            "    ),",
            ");",
            "",
        ]
    )
    return "\n".join(lines).encode("utf-8")


def expected_output(registry_path: Path) -> bytes:
    registry, source_payload = load_registry(registry_path)
    return render_php(compile_contract(registry, source_payload))


def display_path(path: Path) -> str:
    try:
        return path.resolve().relative_to(ROOT).as_posix()
    except ValueError:
        return str(path.resolve())


def build(registry_path: Path, output_path: Path, check: bool = False) -> None:
    payload = expected_output(registry_path)
    if check:
        if not output_path.is_file() or output_path.read_bytes() != payload:
            raise ContractError(
                "SEO runtime migration gates are stale; run "
                "scripts/build_seo_runtime.py"
            )
        print(f"SEO runtime migration gates are current: {display_path(output_path)}")
        return

    output_path.parent.mkdir(parents=True, exist_ok=True)
    output_path.write_bytes(payload)
    print(f"Built SEO runtime migration gates: {display_path(output_path)}")


def main() -> int:
    parser = argparse.ArgumentParser(
        description="Compile migration gates from the SEO ownership registry."
    )
    parser.add_argument("--check", action="store_true")
    parser.add_argument("--registry", type=Path, default=DEFAULT_REGISTRY)
    parser.add_argument("--output", type=Path, default=DEFAULT_OUTPUT)
    args = parser.parse_args()

    try:
        build(args.registry, args.output, check=args.check)
    except ContractError as error:
        print(f"SEO runtime build failed: {error}", file=sys.stderr)
        return 1
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
