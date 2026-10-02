#!/usr/bin/env python3
from __future__ import annotations
import argparse
from pathlib import Path
import json
import sys

ap=argparse.ArgumentParser()
ap.add_argument('--baseline',required=True)
ap.add_argument('--current',required=True)
args=ap.parse_args()
base=Path(args.baseline)
cur=Path(args.current)
names=['get_capabilities','system_info','project_overview','check_integrations','list_resources']
errors=[]
for name in names:
    bp=base/(name+'.json')
    cp=cur/(name+'.json')
    if not bp.is_file(): errors.append(f'{name}: baseline missing'); continue
    if not cp.is_file(): errors.append(f'{name}: current missing'); continue
    b=json.loads(bp.read_text(encoding='utf-8'))
    c=json.loads(cp.read_text(encoding='utf-8'))
    if b != c:
        errors.append(f'{name}: response differs')
        # compact top-level diagnostic; full files stay on disk
        if isinstance(b,dict) and isinstance(c,dict):
            bk=set(b); ck=set(c)
            if bk != ck:
                errors.append(f'  keys baseline-only={sorted(bk-ck)} current-only={sorted(ck-bk)}')
            for k in sorted(bk & ck):
                if b[k] != c[k]:
                    errors.append(f'  changed top-level field: {k}')
if errors:
    print('LIVE_REGRESSION_FAIL')
    for e in errors: print('-',e)
    sys.exit(1)
print('LIVE_REGRESSION_OK')
