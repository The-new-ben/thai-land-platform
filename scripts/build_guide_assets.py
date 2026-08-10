#!/usr/bin/env python3
"""Build deterministic responsive hero images for managed Guides routes."""

from __future__ import annotations

import argparse
import hashlib
from io import BytesIO
from pathlib import Path

from PIL import Image, ImageOps


ROOT = Path(__file__).resolve().parents[1]
SOURCE_DIR = ROOT / "assets" / "guides" / "sources"
OUTPUT_DIR = ROOT / "assets" / "guides" / "images"

EXPECTED_SOURCE_SIZE = (1672, 941)
FOCAL_POINT = (0.50, 0.50)
WEBP_QUALITY = 82
WEBP_METHOD = 6

SOURCES = {
    "visas-entry-thailand-v1": {
        "filename": "visas-entry-thailand-v1-master.png",
        "sha256": "80482d702b3b96d427cd907e7b308d4e8ca380241197438633ad570a3cf9b672",
    },
    "cannabis-law-thailand-v1": {
        "filename": "cannabis-law-thailand-v1-master.png",
        "sha256": "ccc2ef768c289230307b5fb2deaf70df664de6c611a9d01eda4a533f4167db92",
    },
}

VARIANTS = {
    "720": (720, 384),
    "1200": (1200, 640),
    "1717": (1717, 916),
}


def digest(path: Path) -> str:
    return hashlib.sha256(path.read_bytes()).hexdigest()


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
    outputs: dict[Path, bytes] = {}

    for asset_key, specification in SOURCES.items():
        source_path = SOURCE_DIR / specification["filename"]
        if not source_path.is_file():
            raise ValueError(f"Missing reviewed guide source image: {source_path}")
        if digest(source_path) != specification["sha256"]:
            raise ValueError(f"Guide source checksum does not match: {asset_key}")

        with Image.open(source_path) as opened:
            source = ImageOps.exif_transpose(opened)
            if source.size != EXPECTED_SOURCE_SIZE:
                raise ValueError(
                    f"Guide source dimensions changed for {asset_key}: "
                    f"expected {EXPECTED_SOURCE_SIZE}, got {source.size}"
                )
            source.load()
            for suffix, size in VARIANTS.items():
                output_path = OUTPUT_DIR / f"{asset_key}-{suffix}.webp"
                outputs[output_path] = render_variant(source, size)

    return outputs


def build(check: bool = False) -> None:
    outputs = expected_outputs()

    if check:
        stale = [
            path.relative_to(ROOT).as_posix()
            for path, payload in outputs.items()
            if not path.is_file() or path.read_bytes() != payload
        ]
        if stale:
            raise ValueError("Generated guide assets are stale: " + ", ".join(stale))
        print("Guide assets match the reviewed sources")
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
