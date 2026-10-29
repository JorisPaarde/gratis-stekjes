#!/usr/bin/env python3
"""Genereer manifest.webmanifest uit build-config / env. Geen hardcoded domein."""
from __future__ import annotations
import json
import os
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
cfg = json.loads((ROOT / "build-config.json").read_text(encoding="utf-8"))
name = os.environ.get("SITE_NAME") or cfg.get("site_name") or "Gratis stekjes"
# Relatieve start_url — domein komt uit hosting/DNS
manifest = {
    "name": name,
    "short_name": name[:12],
    "description": cfg.get("description") or "Gratis stekjes, een praatje als enige prijs.",
    "start_url": "/",
    "scope": "/",
    "display": "standalone",
    "background_color": cfg.get("background_color") or "#eef4ea",
    "theme_color": cfg.get("theme_color") or "#2f6b45",
    "lang": "nl",
    "icons": [
        {"src": "/assets/icons/icon-192.png", "sizes": "192x192", "type": "image/png", "purpose": "any maskable"},
        {"src": "/assets/icons/icon-512.png", "sizes": "512x512", "type": "image/png", "purpose": "any maskable"},
    ],
}
(ROOT / "manifest.webmanifest").write_text(json.dumps(manifest, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
print("wrote manifest.webmanifest")
