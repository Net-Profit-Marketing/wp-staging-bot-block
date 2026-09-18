#!/usr/bin/env python3
"""Verify the approved WordPress.org PNGs without modifying them (Python 3.9+)."""

import argparse
import hashlib
from pathlib import Path
import struct
import subprocess
import sys
import zlib

ROOT = Path(__file__).resolve().parent.parent
ARTWORK = {
    "banner-772x250.png": ((772, 250), "604bf7b5e93eb5b97053f85cb928c986279b02a2cabbbf265051b5cfb9edfb38"),
    "banner-1544x500.png": ((1544, 500), "1af219870e1c5c062e8ae70dcb14376d2f2100bd674c4ed6c99b16129992c969"),
    "icon-128x128.png": ((128, 128), "d9ccffd326c4671493f7f51688c07d468cb344a33bcb1e718a3af5ad541e0602"),
    "icon-256x256.png": ((256, 256), "84e78999f7ce29a549afd49aa1949651b163b0e428bfa6852a14dd573867da84"),
    "screenshot-1.png": ((898, 1081), "e49b89b02bf9a1df2813f3160b17ce9cd7cce2b26ab46ae13ad74054e3cec436"),
}


def verify_png(path):
    """Check signature, chunk checksums, IHDR, image data and complete scanlines."""
    data = path.read_bytes()
    if not data.startswith(b"\x89PNG\r\n\x1a\n"):
        raise ValueError("Invalid PNG signature: " + str(path))
    offset, image_data, header, ended = 8, bytearray(), None, False
    while offset < len(data):
        if offset + 12 > len(data):
            raise ValueError("Truncated PNG chunk: " + str(path))
        length = struct.unpack(">I", data[offset:offset + 4])[0]
        kind = data[offset + 4:offset + 8]
        payload = data[offset + 8:offset + 8 + length]
        end = offset + 12 + length
        if end > len(data) or zlib.crc32(kind + payload) != struct.unpack(">I", data[end - 4:end])[0]:
            raise ValueError("Invalid PNG chunk checksum: " + str(path))
        if offset == 8 and kind != b"IHDR":
            raise ValueError("PNG must start with IHDR")
        if kind == b"IHDR":
            if header is not None or length != 13:
                raise ValueError("Invalid PNG IHDR")
            header = struct.unpack(">IIBBBBB", payload)
        elif kind == b"IDAT":
            image_data.extend(payload)
        elif kind == b"IEND":
            if length != 0 or end != len(data):
                raise ValueError("Unexpected PNG trailing data")
            ended = True
        offset = end
    if not header or not ended or not image_data:
        raise ValueError("PNG is missing required chunks")
    width, height, depth, color, compression, filtering, interlace = header
    channels = {0: 1, 2: 3, 3: 1, 4: 2, 6: 4}
    depths = {0: (1, 2, 4, 8, 16), 2: (8, 16), 3: (1, 2, 4, 8), 4: (8, 16), 6: (8, 16)}
    if (not width or not height or color not in channels or depth not in depths[color]
            or compression or filtering or interlace):
        raise ValueError("Unsupported or invalid PNG format for the approved assets")
    raw = zlib.decompress(image_data)
    stride = (width * channels[color] * depth + 7) // 8 + 1
    if len(raw) != stride * height or any(raw[row * stride] > 4 for row in range(height)):
        raise ValueError("Invalid PNG scanlines")
    return (width, height), hashlib.sha256(data).hexdigest()


def check_artwork(root=ROOT, require_tracked=False):
    directory = root / ".wordpress-org"
    if {path.name for path in directory.iterdir()} != set(ARTWORK):
        raise ValueError(".wordpress-org must contain exactly the five approved lowercase PNGs")
    for name, expected in ARTWORK.items():
        actual = verify_png(directory / name)
        if actual != expected:
            raise ValueError("Approved artwork dimensions or bytes changed: " + name)
        if require_tracked:
            subprocess.run(["git", "ls-files", "--error-unmatch", ".wordpress-org/" + name],
                           cwd=root, check=True, stdout=subprocess.DEVNULL)
        print("PNG OK: {} {}x{} SHA256 {}".format(name, *actual[0], actual[1]))


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--require-tracked", action="store_true")
    args = parser.parse_args()
    try:
        check_artwork(require_tracked=args.require_tracked)
    except (OSError, ValueError, zlib.error, subprocess.CalledProcessError) as error:
        sys.exit("Artwork validation failed: " + str(error))
