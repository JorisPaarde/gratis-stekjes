#!/usr/bin/env python3
"""Controleer interne href/src in HTML (relatief)."""
from __future__ import annotations

import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
SKIP_PREFIX = ("http://", "https://", "mailto:", "tel:", "#", "data:")
href_re = re.compile(r"""(?:href|src)=["']([^"']+)["']""", re.I)

missing = []
checked = 0

for html in ROOT.rglob("*.html"):
    if ".git" in html.parts or "placeholder" in html.parts:
        continue
    text = html.read_text(encoding="utf-8", errors="ignore")
    for m in href_re.finditer(text):
        url = m.group(1).strip()
        if url.startswith(SKIP_PREFIX) or url.startswith("//"):
            continue
        path = url.split("?", 1)[0].split("#", 1)[0]
        if not path or path.endswith(".php"):
            # PHP endpoints bestaan als bestanden; check file
            if path.endswith(".php"):
                target = ROOT / path.lstrip("/")
                checked += 1
                if not target.is_file():
                    missing.append((str(html.relative_to(ROOT)), path))
            continue
        if path.startswith("/"):
            target = ROOT / path.lstrip("/")
            if path.endswith("/"):
                target = target / "index.html"
            checked += 1
            if not target.exists():
                # directory index
                if (ROOT / path.lstrip("/")).is_dir() and (ROOT / path.lstrip("/") / "index.html").exists():
                    continue
                missing.append((str(html.relative_to(ROOT)), path))

print(f"checked {checked} local refs")
if missing:
    for src, path in missing[:50]:
        print(f"MISSING {path} (from {src})")
    sys.exit(1)
print("linkcheck OK")
