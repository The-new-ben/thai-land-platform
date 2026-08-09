#!/usr/bin/env python3
"""Build deterministic Bangkok rental atlas WebP variants."""

from __future__ import annotations

import argparse
import hashlib
from io import BytesIO
from pathlib import Path

from PIL import Image, ImageOps


ROOT = Path(__file__).resolve().parents[1]
SOURCE = ROOT / "prototype" / "assets" / "bangkok-rental-atlas-v1.png"
OUTPUT_DIR = ROOT / "assets" / "content" / "images"

EXPECTED_SOURCE_SIZE = (1822, 863)
EXPECTED_SOURCE_SHA256 = "166e400cfca5c1407e4d9e4fa503810881a80ac4b1c53e84f09cce960f0dca98"
FOCAL_POINT = (0.58, 0.50)
WEBP_QUALITY = 60
WEBP_METHOD = 6

VARIANTS = {
    "720": (720, 384),
    "1200": (1200, 640),
    "1717": (1717, 916),
}


def source_digest() -> str:
    return hashlib.sha256(SOURCE.read_bytes()).hexdigest()


def render_variant(source: Image.Image, size: tuple[int, int]) -> bytes:
    rendered = ImageOps.fit(
        source,
        size,
        method=Image.Resampling.LANCZOS,
        centering=FOCAL_POINT,
    ).convert("RGB")

    payload = BytesIO()
    rendered.save(
        payload,
        format="WEBP",
        quality=WEBP_QUALITY,
        method=WEBP_METHOD,
        lossless=False,
    )
    return payload.getvalue()


def expected_outputs() -> dict[Path, bytes]:
    if not SOURCE.is_file():
        raise ValueError(f"Missing reviewed source image: {SOURCE}")
    if source_digest() != EXPECTED_SOURCE_SHA256:
        raise ValueError("Bangkok rental atlas source checksum does not match the reviewed image")

    with Image.open(SOURCE) as opened:
        source = ImageOps.exif_transpose(opened)
        if source.size != EXPECTED_SOURCE_SIZE:
            raise ValueError(
                "Bangkok rental atlas source dimensions changed: "
                f"expected {EXPECTED_SOURCE_SIZE}, got {source.size}"
            )
        source.load()
        return {
            OUTPUT_DIR / f"bangkok-rental-atlas-v1-{suffix}.webp": render_variant(source, size)
            for suffix, size in VARIANTS.items()
        }


def build(check: bool = False) -> None:
    outputs = expected_outputs()

    if check:
        stale = [
            path.relative_to(ROOT).as_posix()
            for path, payload in outputs.items()
            if not path.is_file() or path.read_bytes() != payload
        ]
        if stale:
            raise ValueError("Generated Bangkok rental assets are stale: " + ", ".join(stale))
        print("Bangkok rental assets match the reviewed source")
        return

    OUTPUT_DIR.mkdir(parents=True, exist_ok=True)
    for path, payload in outputs.items():
        path.write_bytes(payload)
        print(f"Built {path.relative_to(ROOT).as_posix()} ({len(payload)} bytes)")


if __name__ == "__main__":
    parser = argparse.ArgumentParser()
    parser.add_argument("--check", action="store_true")
    arguments = parser.parse_args()
    build(check=arguments.check)
