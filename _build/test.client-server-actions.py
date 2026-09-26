#!/usr/bin/env python3
from pathlib import Path
import re
import sys

ROOT = Path(__file__).resolve().parents[1]
model = (ROOT / "core/components/modxmcp/model/modxmcp.class.php").read_text()
client = (ROOT / "client/index.js").read_text()

m = re.search(r"private function actionRegistry\(\)\s*\{(.*?)\n    \}\n\n    /\*\* Flatten", model, re.S)
if not m:
    print("Could not isolate actionRegistry()", file=sys.stderr)
    sys.exit(2)

registry = m.group(1)
server_actions = set()
for line in registry.splitlines():
    # Group keys are indented 12 spaces; action keys are indented 16 spaces.
    mm = re.match(r"^ {16}'([a-z][a-z0-9_]+)'\s*=>", line)
    if mm:
        server_actions.add(mm.group(1))

client_tools = set(re.findall(r'name:\s*"modx_([a-z][a-z0-9_]+)"', client))

internal_server_actions = {"get_capabilities"}

missing_server = sorted(client_tools - server_actions)
missing_client = sorted((server_actions - internal_server_actions) - client_tools)

# The public contract should be exact. Internal actions are explicit above.
if missing_server or missing_client:
    print("MODX3 MCP client/server action contract FAILED:")
    if missing_server:
        print(" - client tools without server action:", ", ".join(missing_server))
    if missing_client:
        print(" - server actions without client tool:", ", ".join(missing_client))
    sys.exit(1)

print(f"MODX3 MCP client/server action contract passed: {len(server_actions)} actions.")