#!/usr/bin/env python3
"""Build deterministic, scoped homepage release assets from reviewed sources."""

from __future__ import annotations

import argparse
import re
from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]
SOURCE = ROOT / "prototype"
RESOURCE_DIR = ROOT / "resources"
ASSET_DIR = ROOT / "assets" / "homepage"
IMAGE_DIR = ASSET_DIR / "images"


def find_open_brace(css: str, start: int) -> int:
    quote = ""
    index = start
    while index < len(css):
        char = css[index]
        next_char = css[index + 1] if index + 1 < len(css) else ""
        if not quote and char == "/" and next_char == "*":
            end = css.find("*/", index + 2)
            return -1 if end < 0 else find_open_brace(css, end + 2)
        if quote:
            if char == "\\":
                index += 2
                continue
            if char == quote:
                quote = ""
        elif char in ("'", '"'):
            quote = char
        elif char == "{":
            return index
        index += 1
    return -1


def find_close_brace(css: str, opening: int) -> int:
    depth = 1
    quote = ""
    index = opening + 1
    while index < len(css):
        char = css[index]
        next_char = css[index + 1] if index + 1 < len(css) else ""
        if not quote and char == "/" and next_char == "*":
            end = css.find("*/", index + 2)
            if end < 0:
                raise ValueError("Unterminated CSS comment")
            index = end + 2
            continue
        if quote:
            if char == "\\":
                index += 2
                continue
            if char == quote:
                quote = ""
        elif char in ("'", '"'):
            quote = char
        elif char == "{":
            depth += 1
        elif char == "}":
            depth -= 1
            if depth == 0:
                return index
        index += 1
    raise ValueError("Unbalanced CSS block")


def split_selector_list(header: str) -> list[str]:
    selectors: list[str] = []
    start = 0
    quote = ""
    parens = 0
    brackets = 0
    for index, char in enumerate(header):
        if quote:
            if char == "\\":
                continue
            if char == quote:
                quote = ""
            continue
        if char in ("'", '"'):
            quote = char
        elif char == "(":
            parens += 1
        elif char == ")":
            parens = max(0, parens - 1)
        elif char == "[":
            brackets += 1
        elif char == "]":
            brackets = max(0, brackets - 1)
        elif char == "," and parens == 0 and brackets == 0:
            selectors.append(header[start:index])
            start = index + 1
    selectors.append(header[start:])
    return selectors


def prefix_selector(selector: str) -> str:
    leading = selector[: len(selector) - len(selector.lstrip())]
    trailing = selector[len(selector.rstrip()) :]
    value = selector.strip()
    if not value:
        return selector
    if value.startswith(".thp-home"):
        scoped = value
    elif value.startswith(":root"):
        scoped = "body.thailand-platform-home" + value[len(":root") :]
    elif re.match(r"^html(?=$|[\s.#:\[])", value):
        scoped = re.sub(
            r"^html",
            "html.thailand-platform-document",
            value,
            count=1,
        )
    elif re.match(r"^body(?=$|[\s.#:\[])", value):
        scoped = re.sub(
            r"^body",
            "body.thailand-platform-home",
            value,
            count=1,
        )
    else:
        scoped = ".thp-home " + value
    return leading + scoped + trailing


def scope_selector_header(header: str) -> str:
    return ",".join(prefix_selector(part) for part in split_selector_list(header))


def split_leading_trivia(header: str) -> tuple[str, str]:
    """Keep whitespace/comments outside the selector or at-rule header."""
    match = re.match(r"^((?:(?:\s+)|(?:/\*.*?\*/))*)(.*)$", header, flags=re.DOTALL)
    if not match:
        return "", header
    return match.group(1), match.group(2)


def scope_rules(css: str) -> str:
    output: list[str] = []
    cursor = 0
    recursive_at_rules = ("@media", "@supports", "@container", "@layer", "@scope")
    while cursor < len(css):
        opening = find_open_brace(css, cursor)
        if opening < 0:
            output.append(css[cursor:])
            break
        closing = find_close_brace(css, opening)
        header = css[cursor:opening]
        body = css[opening + 1 : closing]
        trivia, rule_header = split_leading_trivia(header)
        stripped = rule_header.lstrip()
        if stripped.startswith("@"):
            transformed_header = trivia + rule_header
            transformed_body = scope_rules(body) if stripped.startswith(recursive_at_rules) else body
        else:
            transformed_header = trivia + scope_selector_header(rule_header)
            transformed_body = body
        output.extend((transformed_header, "{", transformed_body, "}"))
        cursor = closing + 1
    return "".join(output)


def expected_outputs() -> dict[Path, bytes]:
    html = (SOURCE / "index.html").read_text(encoding="utf-8")
    match = re.search(r"<body[^>]*>(.*)</body>", html, flags=re.IGNORECASE | re.DOTALL)
    if not match:
        raise ValueError("Homepage source is missing a body element")

    css = (SOURCE / "styles.css").read_text(encoding="utf-8")
    css = css.replace('url("assets/', 'url("images/')
    scoped_css = ".thp-home { min-height: 100%; }\n" + scope_rules(css)
    if re.search(r"\.thp-home\s+(?:/\*.*?\*/\s*)?@(?:media|supports|container|layer|scope)\b", scoped_css, flags=re.DOTALL):
        raise ValueError("A recursive CSS at-rule was incorrectly prefixed as a selector")
    responsive_probe = scope_rules("/* Responsive */\n@media (max-width: 1px) { .probe { display: none; } }")
    if ".thp-home .probe" not in responsive_probe or ".thp-home /* Responsive */" in responsive_probe:
        raise ValueError("CSS scoper regression: commented responsive rules are not safely scoped")

    outputs = {
        RESOURCE_DIR / "homepage.html": (match.group(1).strip() + "\n").encode("utf-8"),
        ASSET_DIR / "homepage.css": scoped_css.encode("utf-8"),
        ASSET_DIR / "homepage.js": (SOURCE / "app.js").read_bytes(),
    }

    for width in (640, 1024, 1713):
        filename = f"homepage-hero-thailand-system-v1-{width}.webp"
        outputs[IMAGE_DIR / filename] = (SOURCE / "assets" / filename).read_bytes()

    return outputs


def build(check: bool = False) -> None:
    outputs = expected_outputs()

    if check:
        stale = [path.relative_to(ROOT).as_posix() for path, payload in outputs.items() if not path.is_file() or path.read_bytes() != payload]
        if stale:
            raise ValueError("Generated homepage assets are stale: " + ", ".join(stale))
        print("Homepage assets match reviewed sources")
        return

    RESOURCE_DIR.mkdir(parents=True, exist_ok=True)
    IMAGE_DIR.mkdir(parents=True, exist_ok=True)
    for path, payload in outputs.items():
        path.parent.mkdir(parents=True, exist_ok=True)
        path.write_bytes(payload)

    print("Built scoped homepage assets")


if __name__ == "__main__":
    parser = argparse.ArgumentParser()
    parser.add_argument("--check", action="store_true")
    args = parser.parse_args()
    build(check=args.check)
