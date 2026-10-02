from pathlib import Path
import re
import sys

root = Path(__file__).resolve().parents[1]
client = (root / 'client' / 'index.js').read_text(encoding='utf-8')
tools_dir = root / 'core' / 'components' / 'modxmcp' / 'src' / 'Tools'
docs = (root / 'docs' / 'VALIDATION.md').read_text(encoding='utf-8')
errors = []

client_tools = []
for match in re.finditer(r'name:\s*"(?P<name>modx_[a-z0-9_]+)"', client):
    name = match.group('name')
    if name not in client_tools:
        client_tools.append(name)

def set_block(start, end):
    chunk = client[client.find(start):client.find(end)]
    return set(re.findall(r'"(modx_[a-z0-9_]+)"', chunk))

explicit = set_block(
    'const PROJECT_LOCK_READ_ONLY_TOOLS',
    'function projectLockBootId'
)
exact = set_block(
    'const PROJECT_LOCK_READ_ONLY_EXACT',
    'const PROJECT_LOCK_READ_OPERATION'
)
read_pattern = re.compile(
    r'(?:^|_)(?:list|get|search|read|view|check|describe|find|suggest)(?:_|$)'
)

def is_read_only(tool):
    if tool in explicit or tool in exact:
        return True
    return bool(read_pattern.search(tool[len('modx_'):]))

read_only = {name[len('modx_'):] for name in client_tools if is_read_only(name)}
read_only.add('get_capabilities')

migrated = set()
for path in tools_dir.glob('*Tool.php'):
    text = path.read_text(encoding='utf-8')
    match = re.search(r"function name\(\) \{ return '([^']+)'; \}", text)
    if match:
        migrated.add(match.group(1))

unexpected = sorted(migrated - read_only)
if unexpected:
    errors.append('Modular registry contains non-read-only actions in this stage: ' + ', '.join(unexpected))

pending = sorted(read_only - migrated)
marker = re.search(r'READONLY_MIGRATION_COVERAGE:\s*(\d+)\s*/\s*(\d+)', docs)
if not marker:
    errors.append('docs/VALIDATION.md has no READONLY_MIGRATION_COVERAGE marker')
else:
    documented = (int(marker.group(1)), int(marker.group(2)))
    actual = (len(migrated), len(read_only))
    if documented != actual:
        errors.append('coverage marker is stale: documented %d/%d, actual %d/%d' % (
            documented[0], documented[1], actual[0], actual[1]
        ))
if len(client_tools) + 1 != 182:
    errors.append('expected 182 server actions including internal get_capabilities, got %d' % (
        len(client_tools) + 1
    ))

if errors:
    print('MIGRATION_COVERAGE_FAIL')
    for error in errors:
        print('-', error)
    sys.exit(1)

print('MIGRATION_COVERAGE_OK %d/%d read-only actions modular; %d pending' % (
    len(migrated), len(read_only), len(pending)
))
for name in pending:
    print('-', name)
