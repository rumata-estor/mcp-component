#!/usr/bin/env node
'use strict';

const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '..');
const read = (p) => fs.readFileSync(path.join(root, p), 'utf8');
const fail = (message) => {
  console.error('PORTABILITY CHECK FAILED: ' + message);
  process.exitCode = 1;
};

const transport = read('_build/data/transport.settings.php');
const headless = read('_build/install.headless.php');
const model = read('core/components/modxmcp/model/modxmcp.class.php');
const api = read('assets/components/modxmcp/api.php');
const managerController = read('core/components/modxmcp/controllers/index.class.php');
const managerTemplate = read('core/components/modxmcp/templates/home.tpl');
const builder = read('_build/build.transport.php');
const transportInstaller = read('_build/install.transport.php');
const tokenResolver = read('_build/resolvers/resolve.token.php');
const serviceUserResolver = read('_build/resolvers/resolve.service_user.php');
const ru = read('core/components/modxmcp/lexicon/ru/setting.inc.php');
const en = read('core/components/modxmcp/lexicon/en/setting.inc.php');

function uniqueMatches(text, regex) {
  return [...new Set([...text.matchAll(regex)].map((m) => m[1]))].sort();
}

const transportKeys = uniqueMatches(
  transport,
  /array\(\s*['"](modxmcp\.[a-zA-Z0-9_]+)['"]\s*,/g,
);
const headlessKeys = uniqueMatches(
  headless,
  /['"](modxmcp\.[a-zA-Z0-9_]+)['"]\s*=>\s*array\(/g,
);

if (JSON.stringify(transportKeys) !== JSON.stringify(headlessKeys)) {
  fail(
    'transport and headless installers define different system settings.\n' +
      'transport=' + JSON.stringify(transportKeys) + '\n' +
      'headless=' + JSON.stringify(headlessKeys),
  );
}

for (const key of transportKeys) {
  for (const [lang, text] of [['ru', ru], ['en', en]]) {
    const escaped = key.replace(/[.*+?^\${}()|[\]\\]/g, '\\$&');
    const label = new RegExp('\\$_lang\\[[\'"]setting_' + escaped + '[\'"]\\]\\s*=');
    const desc = new RegExp('\\$_lang\\[[\'"]setting_' + escaped + '_desc[\'"]\\]\\s*=');
    if (!label.test(text)) fail(lang + ' lexicon is missing label for ' + key);
    if (!desc.test(text)) fail(lang + ' lexicon is missing description for ' + key);
  }
}

const safeDefaults = [
  ['service_user_id', '0'],
  ['auto_static', '0'],
  ['require_https', '1'],
  ['debug', '0'],
  ['allow_run_processor', '0'],
  ['allow_root_filesystem_read', '0'],
];

for (const [suffix, expected] of safeDefaults) {
  const key = 'modxmcp.' + suffix;
  const escaped = key.replace(/[.*+?^\${}()|[\]\\]/g, '\\$&');
  const transportRe = new RegExp(
    'array\\(\\s*[\'"]' + escaped + '[\'"]\\s*,\\s*' + expected + '\\s*,',
  );
  const headlessRe = new RegExp(
    '[\'"]' + escaped + '[\'"]\\s*=>\\s*array\\(\\s*' + expected + '\\s*,',
  );
  if (!transportRe.test(transport)) fail('transport default for ' + key + ' must be ' + expected);
  if (!headlessRe.test(headless)) fail('headless default for ' + key + ' must be ' + expected);
}

if (!/getOption\(\s*['"]modxmcp\.service_user_id['"]\s*,\s*null\s*,\s*0\s*\)/.test(model)) {
  fail('runtime service_user_id fallback must be 0, not a hard-coded administrator ID');
}
if (/->set\(\s*['"]sudo['"]\s*,\s*(?:1|true)\s*\)/i.test(model)) {
  fail('runtime must never elevate a configured MODX user to sudo');
}

if (!api.includes('modxmcp.trusted_proxy_ips')) {
  fail('HTTPS proxy handling must require an explicit trusted proxy setting');
}
if (/HTTP_X_FORWARDED_FOR/.test(api)) {
  fail('endpoint must not trust X-Forwarded-For for client authorization');
}

if (!/PHP_SAPI\s*!==\s*['"]cli['"]/.test(builder)) {
  fail('transport package builder must remain CLI-only');
}
if (/\$_GET|\$_POST|QUERY_STRING/.test(builder)) {
  fail('transport package builder must not expose a web-triggered build path');
}
if (!/DOCUMENT_ROOT/.test(builder) || !/dirname\(\$config\)/.test(builder)) {
  fail('transport package builder must provide a CLI DOCUMENT_ROOT fallback for config.core.php');
}
if (!/PHP_SAPI\s*!==\s*['"]cli['"]/.test(transportInstaller)) {
  fail('transport test installer must remain CLI-only');
}
if (/\$_GET|\$_POST/.test(transportInstaller)) {
  fail('transport test installer must not accept web request parameters');
}
if (!/--show-token/.test(headless) || !/--show-token/.test(transportInstaller)) {
  fail('full token output must require explicit --show-token in release helpers');
}
if ((builder.match(/['"]permissions['"]\s*=>\s*['"]settings['"]/g) || []).length < 2) {
  fail('both transport manager menu entries must require the settings permission');
}
if ((headless.match(/['"]permissions['"]\s*=>\s*['"]settings['"]/g) || []).length < 2) {
  fail('both headless manager menu entries must require the settings permission');
}
if (/\$showToken\s*=\s*\$tokenGenerated/.test(headless)) {
  fail('headless installer must not print a newly generated full token implicitly');
}
if (!/modxmcp\.enabled/.test(tokenResolver) || !/set\(['"]value['"]\s*,\s*0\)/.test(tokenResolver)) {
  fail('token resolver must fail closed by disabling the API when token setup fails');
}
if (!/modxmcp\.enabled/.test(serviceUserResolver) || !/set\(['"]value['"]\s*,\s*0\)/.test(serviceUserResolver)) {
  fail('service-user resolver must fail closed when no safe service user exists');
}
if (/token_full/.test(managerController) || /token_full/.test(managerTemplate)) {
  fail('manager dashboard must never expose the full MCP API token');
}
if (!/hasPermission\(['"]settings['"]\)/.test(managerController)) {
  fail('manager dashboard token actions must be gated by the settings permission');
}

const forbidden = [
  'test.alex-palochkin',
  '130.17.9.68',
  '/home/codexbot',
  '/home/c/ck33540',
  'lukigo-timeweb',
];

const scanRoots = ['_build', 'assets', 'client', 'core'];
function walk(dir) {
  const abs = path.join(root, dir);
  for (const entry of fs.readdirSync(abs, { withFileTypes: true })) {
    const rel = path.join(dir, entry.name);
    if (entry.isDirectory()) {
      walk(rel);
      continue;
    }
    if (rel === path.join('_build', 'test.portability.js')) continue;
    const text = fs.readFileSync(path.join(root, rel), 'utf8');
    for (const marker of forbidden) {
      if (text.includes(marker)) fail(rel + ' contains project-specific marker: ' + marker);
    }
  }
}
for (const dir of scanRoots) walk(dir);

if (!process.exitCode) {
  console.log(
    'Portability check passed: ' + transportKeys.length +
    ' settings aligned; safe defaults, lexicons, service-user policy and project-specific markers verified.',
  );
}