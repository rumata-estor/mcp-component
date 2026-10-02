from pathlib import Path
import re
import sys

root=Path(__file__).resolve().parents[1]
shared=root/'core/components/modxmcp/src'
errors=[]

source_legacy_fallback=root/'core/components/modxmcp/legacy/modx2/modxmcp.class.php'
release_model=root/'core/components/modxmcp/model/modxmcp.class.php'
build_platform=(root/'BUILD_PLATFORM').read_text(encoding='utf-8').strip() if (root/'BUILD_PLATFORM').is_file() else ''
if build_platform and build_platform != 'modx2':
    errors.append(f'MODX2 compatibility test cannot run against BUILD_PLATFORM={build_platform!r}')
fallback=source_legacy_fallback if source_legacy_fallback.is_file() else release_model

# Keep the shared modular layer free from syntax/runtime helpers that would
# force MODX 2 onto a recent PHP runtime.
patterns={
    r'\?\?':'null-coalescing operator',
    r'\?->':'nullsafe operator',
    r'\bfn\s*\(':'arrow function',
    r'\bmatch\s*\(':'match expression',
    r'\breadonly\s+':'readonly',
    r'\benum\s+':'enum',
    r'\bstr_contains\s*\(':'str_contains',
    r'\bstr_starts_with\s*\(':'str_starts_with',
    r'\bstr_ends_with\s*\(':'str_ends_with',
    r'\barray_is_list\s*\(':'array_is_list',
}
for p in shared.rglob('*.php'):
    s=p.read_text(encoding='utf-8')
    for pat,label in patterns.items():
        if re.search(pat,s):
            errors.append(f'{p.relative_to(root)} uses {label}')

s=fallback.read_text(encoding='utf-8')
constructor=s.split('private function resolveServiceUser',1)[0]
if 'catch (\\Exception $e)' not in constructor:
    errors.append('MODX2 modular bootstrap does not catch Exception')
if 'catch (\\Throwable $e)' not in constructor:
    errors.append('MODX2 modular bootstrap does not retain Throwable catch for PHP 7+')

if errors:
    print('MODX2_PHP_COMPAT_FAIL')
    for e in errors: print('-',e)
    sys.exit(1)
print('MODX2_PHP_COMPAT_OK')
