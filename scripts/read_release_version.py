#!/usr/bin/env python3
"""Print the strictly validated Thailand Platform release version."""

from __future__ import annotations

import json
import re
import sys
from pathlib import Path
from typing import Any


EXPECTED_FIELDS = {
    "author",
    "download_url",
    "homepage",
    "last_updated",
    "name",
    "requires",
    "requires_php",
    "sections",
    "slug",
    "tested",
    "version",
}


def reject_duplicates(pairs: list[tuple[str, Any]]) -> dict[str, Any]:
    output: dict[str, Any] = {}
    for key, value in pairs:
        if key in output:
            raise ValueError(f"duplicate JSON key: {key}")
        output[key] = value
    return output


def reject_constant(value: str) -> None:
    raise ValueError(f"non-finite JSON value: {value}")


def main() -> int:
    if len(sys.argv) != 2:
        raise ValueError("expected one release.json path")

    path = Path(sys.argv[1]).resolve()
    data = json.loads(
        path.read_text(encoding="utf-8"),
        object_pairs_hook=reject_duplicates,
        parse_constant=reject_constant,
    )
    if not isinstance(data, dict) or set(data) != EXPECTED_FIELDS:
        raise ValueError("release manifest fields are missing or unexpected")

    version = data.get("version")
    if not isinstance(version, str) or not re.fullmatch(r"[0-9]+(?:\.[0-9]+)+", version):
        raise ValueError("release version is invalid")

    print(version)
    return 0


if __name__ == "__main__":
    try:
        raise SystemExit(main())
    except (OSError, ValueError, json.JSONDecodeError) as error:
        raise SystemExit(f"REJECT: {error}")
