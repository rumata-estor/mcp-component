#!/usr/bin/env python3
from __future__ import annotations
import argparse
from pathlib import Path
import re
import shutil
import sys

root = Path(__file__).resolve().parents[1]

ap = argparse.ArgumentParser()
ap.add_argument('--platform', choices=('modx2','modx3'), required=True)
ap.add_argument('--output', required=True)
args = ap.parse_args()
out = Path(args.output).expanduser().resolve()
if out == root or root in out.parents:
    raise SystemExit('output must be outside the source repository')
if out.exists():
    shutil.rmtree(out)

ignore = shutil.ignore_patterns('node_modules', '__pycache__', '*.pyc', '.git')
shutil.copytree(root, out, ignore=ignore)

core = out / 'core/components/modxmcp'
assets = out / 'assets/components/modxmcp'
platform = out / '_build/platform' / args.platform

if args.platform == 'modx2':
    fallback = core / 'legacy/modx2/modxmcp.class.php'
else:
    fallback = core / 'model/modxmcp.class.php'

if not fallback.is_file():
    raise SystemExit(f'missing fallback: {fallback}')
target_model = core / 'model/modxmcp.class.php'
if fallback.resolve() != target_model.resolve():
    shutil.copy2(fallback, target_model)
shutil.copy2(platform / 'api.php', assets / 'api.php')
if args.platform == 'modx2':
    shutil.copy2(platform / 'build.transport.php', out / '_build/build.transport.php')

# Optional platform overlay replaces only files that genuinely differ between
# MODX major versions (manager base classes/processors, etc.).
overlay = platform / 'overlay'
if overlay.is_dir():
    for src in overlay.rglob('*'):
        if not src.is_file():
            continue
        rel = src.relative_to(overlay)
        dst = out / rel
        dst.parent.mkdir(parents=True, exist_ok=True)
        shutil.copy2(src, dst)

# Legacy snapshots are source-only; release carries only the selected fallback.
legacy = core / 'legacy'
if legacy.exists():
    shutil.rmtree(legacy)

# Platform build templates are source-only after selection.
platform_root = out / '_build/platform'
if platform_root.exists():
    shutil.rmtree(platform_root)

# Keep the staged connector version aligned with the selected platform build.
# This deliberately derives the version from build.config.php so release staging
# does not introduce another hard-coded version source.
build_config = out / '_build/build.config.php'
build_text = build_config.read_text(encoding='utf-8')
match = re.search(r"define\('PKG_VERSION',\s*'([^']+)'\)", build_text)
if not match:
    raise SystemExit(f'PKG_VERSION not found in {build_config}')
pkg_version = match.group(1)

model = core / 'model/modxmcp.class.php'
text = model.read_text(encoding='utf-8')
text = re.sub(
    r"const VERSION = '[^']+';",
    "const VERSION = '" + pkg_version + "';",
    text,
    count=1,
)
model.write_text(text, encoding='utf-8')

(out / 'BUILD_PLATFORM').write_text(args.platform + '\n', encoding='utf-8')
print(out)
