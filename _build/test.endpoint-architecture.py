from pathlib import Path
import sys
root=Path(__file__).resolve().parents[1]
common=root/'core/components/modxmcp/endpoint/api.common.php'
errors=[]
text=common.read_text(encoding='utf-8')
for bad in ('MODX\\Revolution', "model/modx/modx.class.php", 'vendor/autoload.php', 'new modX()', '::getInstance()'):
    if bad in text:
        errors.append(f'common endpoint contains platform bootstrap detail: {bad}')
for required in ('modxmcp.require_https','modxmcp.trust_proxy_https','modxmcp.allowed_ips','modxmcp.api_token','modxmcp.max_payload_bytes'):
    if required not in text:
        errors.append(f'common endpoint missing security control: {required}')
for platform in ('modx2','modx3'):
    p=root/'_build/platform'/platform/'api.php'
    if not p.is_file(): errors.append(f'missing {platform} api wrapper')
if errors:
    print('ENDPOINT_ARCHITECTURE_FAIL')
    for e in errors: print('-',e)
    sys.exit(1)
print('ENDPOINT_ARCHITECTURE_OK')
