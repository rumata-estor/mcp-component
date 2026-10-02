from pathlib import Path
import re
import subprocess
import tempfile
import sys

root = Path(__file__).resolve().parents[1]
errors = []
expected_version = '1.1.0'
expected_packages = {
    'modx2': 'MODXMCP',
    'modx3': 'MODX3MCP',
}

def php_define(name, text):
    match = re.search(
        r"define\('" + re.escape(name) + r"',\s*'([^']+)'\)",
        text,
    )
    return None if not match else match.group(1)

with tempfile.TemporaryDirectory() as td:
    for platform in ('modx2', 'modx3'):
        out = Path(td) / platform
        subprocess.run(
            [
                sys.executable,
                str(root / '_build/prepare-release.py'),
                '--platform',
                platform,
                '--output',
                str(out),
            ],
            check=True,
            capture_output=True,
            text=True,
        )

        api = (out / 'assets/components/modxmcp/api.php').read_text(
            encoding='utf-8'
        )
        model = (
            out / 'core/components/modxmcp/model/modxmcp.class.php'
        ).read_text(encoding='utf-8')
        builder = (out / '_build/build.transport.php').read_text(
            encoding='utf-8'
        )
        build_config = (out / '_build/build.config.php').read_text(
            encoding='utf-8'
        )

        if (out / 'core/components/modxmcp/legacy').exists():
            errors.append(
                f'{platform}: legacy snapshots leaked into release'
            )
        if (out / '_build/platform').exists():
            errors.append(
                f'{platform}: platform templates leaked into release'
            )
        if f"$modxmcpVariant = '{platform}';" not in api:
            errors.append(f'{platform}: wrong API wrapper')

        staged_version = php_define('PKG_VERSION', build_config)
        if staged_version != expected_version:
            errors.append(
                f'{platform}: staged PKG_VERSION={staged_version!r}, '
                f'expected {expected_version!r}'
            )
        staged_name = php_define('PKG_NAME', build_config)
        if staged_name != expected_packages[platform]:
            errors.append(
                f'{platform}: staged PKG_NAME={staged_name!r}, '
                f'expected {expected_packages[platform]!r}'
            )
        if "const VERSION = '" + expected_version + "';" not in model:
            errors.append(f'{platform}: connector version mismatch')

        if platform == 'modx2':
            if "const VARIANT = 'modx2';" not in model:
                errors.append('modx2: wrong fallback model')
            if "model/modx/modx.class.php" not in builder:
                errors.append('modx2: wrong builder bootstrap')
            if ">=2.8.0,<3.0.0" not in builder:
                errors.append('modx2: missing transport requirement')
        else:
            if "const VARIANT = 'modx3';" not in model:
                errors.append('modx3: wrong fallback model')
            if "vendor/autoload.php" not in builder:
                errors.append('modx3: wrong builder bootstrap')
            if ">=3.0.0,<4.0.0" not in builder:
                errors.append('modx3: missing transport requirement')

if errors:
    print('RELEASE_STAGING_FAIL')
    for error in errors:
        print('-', error)
    sys.exit(1)

print(
    'RELEASE_STAGING_OK '
    'modx2=MODXMCP/1.1.0 modx3=MODX3MCP/1.1.0'
)
