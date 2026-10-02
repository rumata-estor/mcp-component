from pathlib import Path
import re
import sys

root = Path(__file__).resolve().parents[1]
client = (root / 'client' / 'index.js').read_text(encoding='utf-8')
tools_dir = root / 'core' / 'components' / 'modxmcp' / 'src' / 'Tools'
catalog_path = root / 'core' / 'components' / 'modxmcp' / 'src' / 'Registry' / 'MutationProcessorCatalog.php'
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

server_actions = {name[len('modx_'):] for name in client_tools}
server_actions.add('get_capabilities')

read_only = {name[len('modx_'):] for name in client_tools if is_read_only(name)}
read_only.add('get_capabilities')
mutations = server_actions - read_only

migrated = set()
for path in tools_dir.glob('*Tool.php'):
    text = path.read_text(encoding='utf-8')
    match = re.search(r"function name\(\) \{ return '([^']+)'; \}", text)
    if match:
        migrated.add(match.group(1))

if catalog_path.is_file():
    catalog = catalog_path.read_text(encoding='utf-8')
    migrated.update(re.findall(
        r"^\s*'([a-z0-9_]+)'\s*=>\s*array\('group'\s*=>",
        catalog,
        flags=re.MULTILINE,
    ))

unexpected = sorted(migrated - server_actions)
if unexpected:
    errors.append(
        'Modular registry contains unknown server actions: ' + ', '.join(unexpected)
    )

read_migrated = migrated & read_only
mutation_migrated = migrated & mutations
read_pending = sorted(read_only - migrated)
mutation_pending = sorted(mutations - migrated)

def marker(name):
    match = re.search(
        name + r':\s*(\d+)\s*/\s*(\d+)',
        docs,
    )
    return match

read_marker = marker('READONLY_MIGRATION_COVERAGE')
if not read_marker:
    errors.append('docs/VALIDATION.md has no READONLY_MIGRATION_COVERAGE marker')
else:
    documented = (int(read_marker.group(1)), int(read_marker.group(2)))
    actual = (len(read_migrated), len(read_only))
    if documented != actual:
        errors.append(
            'read-only coverage marker is stale: documented %d/%d, actual %d/%d'
            % (documented[0], documented[1], actual[0], actual[1])
        )

mutation_marker = marker('MUTATION_MIGRATION_COVERAGE')
if not mutation_marker:
    errors.append('docs/VALIDATION.md has no MUTATION_MIGRATION_COVERAGE marker')
else:
    documented = (int(mutation_marker.group(1)), int(mutation_marker.group(2)))
    actual = (len(mutation_migrated), len(mutations))
    if documented != actual:
        errors.append(
            'mutation coverage marker is stale: documented %d/%d, actual %d/%d'
            % (documented[0], documented[1], actual[0], actual[1])
        )

if len(server_actions) != 182:
    errors.append(
        'expected 182 server actions including internal get_capabilities, got %d'
        % len(server_actions)
    )

if errors:
    print('MIGRATION_COVERAGE_FAIL')
    for error in errors:
        print('-', error)
    sys.exit(1)

print(
    'MIGRATION_COVERAGE_OK read %d/%d; mutations %d/%d; total %d/%d'
    % (
        len(read_migrated),
        len(read_only),
        len(mutation_migrated),
        len(mutations),
        len(migrated),
        len(server_actions),
    )
)
print('READ_PENDING', len(read_pending))
for name in read_pending:
    print('-', name)
print('MUTATION_PENDING', len(mutation_pending))
for name in mutation_pending:
    print('-', name)
