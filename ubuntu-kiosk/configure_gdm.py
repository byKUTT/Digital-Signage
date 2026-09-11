#!/usr/bin/env python3
"""Minimal, comment-preserving GDM daemon-section editor."""

from __future__ import annotations

import pathlib
import sys


def configure_text(text: str, user: str) -> str:
    wanted = {
        "AutomaticLoginEnable": "true",
        "AutomaticLogin": user,
        "WaylandEnable": "false",
    }
    result: list[str] = []
    found: set[str] = set()
    in_daemon = False
    daemon_seen = False
    for line in text.splitlines():
        stripped = line.strip()
        if stripped.startswith("[") and stripped.endswith("]"):
            if in_daemon:
                for key, value in wanted.items():
                    if key not in found:
                        result.append(f"{key}={value}")
            in_daemon = stripped.lower() == "[daemon]"
            daemon_seen = daemon_seen or in_daemon
            result.append(line)
            continue
        key = stripped.lstrip("#").split("=", 1)[0].strip()
        if in_daemon and key in wanted:
            if key not in found:
                result.append(f"{key}={wanted[key]}")
                found.add(key)
            continue
        result.append(line)
    if in_daemon:
        for key, value in wanted.items():
            if key not in found:
                result.append(f"{key}={value}")
    if not daemon_seen:
        result.extend(["", "[daemon]"])
        result.extend(f"{key}={value}" for key, value in wanted.items())
    return "\n".join(result) + "\n"


def main() -> int:
    if len(sys.argv) != 3 or not sys.argv[2]:
        print("Usage: configure_gdm.py <custom.conf> <user>", file=sys.stderr)
        return 2
    path = pathlib.Path(sys.argv[1])
    original = path.read_text(encoding="utf-8") if path.exists() else ""
    path.write_text(configure_text(original, sys.argv[2]), encoding="utf-8")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
