from pathlib import Path
import sys
root=Path(__file__).resolve().parents[1]
errors=[]
for platform in ('modx2','modx3'):
    base=root/'_build'/'platform'/platform
    for name in ('bootstrap.php','release.php'):
        if not (base/name).is_file(): errors.append(f'missing {platform}/{name}')
common=(root/'core/components/modxmcp/src')
for p in common.rglob('*.php'):
    rel=p.relative_to(root)
    text=p.read_text(encoding='utf-8')
    if '_build/platform/' in text:
        errors.append(f'common runtime depends on build platform: {rel}')
if errors:
    print('BUILD_ARCHITECTURE_FAIL')
    for e in errors: print('-',e)
    sys.exit(1)
print('BUILD_ARCHITECTURE_OK')
