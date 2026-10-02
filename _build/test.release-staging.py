from pathlib import Path
import subprocess
import tempfile
import sys
root=Path(__file__).resolve().parents[1]
errors=[]
with tempfile.TemporaryDirectory() as td:
    for platform in ('modx2','modx3'):
        out=Path(td)/platform
        subprocess.run([sys.executable,str(root/'_build/prepare-release.py'),'--platform',platform,'--output',str(out)],check=True,capture_output=True,text=True)
        api=(out/'assets/components/modxmcp/api.php').read_text(encoding='utf-8')
        model=(out/'core/components/modxmcp/model/modxmcp.class.php').read_text(encoding='utf-8')
        builder=(out/'_build/build.transport.php').read_text(encoding='utf-8')
        if (out/'core/components/modxmcp/legacy').exists(): errors.append(f'{platform}: legacy snapshots leaked into release')
        if (out/'_build/platform').exists(): errors.append(f'{platform}: platform templates leaked into release')
        if f"$modxmcpVariant = '{platform}';" not in api: errors.append(f'{platform}: wrong API wrapper')
        if "const VERSION = '1.1.0';" not in model: errors.append(f'{platform}: connector version mismatch')
        if platform=='modx2':
            if "const VARIANT = 'modx2';" not in model: errors.append('modx2: wrong fallback model')
            if "model/modx/modx.class.php" not in builder: errors.append('modx2: wrong builder bootstrap')
            if ">=2.8.0,<3.0.0" not in builder: errors.append('modx2: missing transport requirement')
        else:
            if "const VARIANT = 'modx3';" not in model: errors.append('modx3: wrong fallback model')
            if "vendor/autoload.php" not in builder: errors.append('modx3: wrong builder bootstrap')
            if ">=3.0.0,<4.0.0" not in builder: errors.append('modx3: missing transport requirement')
if errors:
    print('RELEASE_STAGING_FAIL')
    for e in errors: print('-',e)
    sys.exit(1)
print('RELEASE_STAGING_OK')
