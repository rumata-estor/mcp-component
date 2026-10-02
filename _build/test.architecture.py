from pathlib import Path
import sys

root = Path(__file__).resolve().parents[1]
src = root / 'core' / 'components' / 'modxmcp' / 'src'
errors = []

# Common layers must stay platform-neutral.
for rel in ('Core', 'Registry', 'Tools', 'Legacy', 'Extras'):
    for p in (src / rel).rglob('*.php'):
        text = p.read_text(encoding='utf-8')
        if 'MODX\\Revolution' in text:
            errors.append(f'{p.relative_to(root)} directly references MODX 3 classes')
        if "'modResource'" in text or '"modResource"' in text:
            errors.append(f'{p.relative_to(root)} directly references MODX 2 class names')

# Version-specific knowledge must live in its platform adapter.
modx2 = (src / 'Platform' / 'Modx2Platform.php').read_text(encoding='utf-8')
modx3 = (src / 'Platform' / 'Modx3Platform.php').read_text(encoding='utf-8')
if 'MODX\\\\Revolution' in modx2:
    errors.append('Modx2Platform contains MODX 3 namespaced classes')
if 'MODX\\\\Revolution' not in modx3:
    errors.append('Modx3Platform is missing namespaced MODX 3 class mappings')
if "return 'modx2'" not in modx2 or "return 'modx3'" not in modx3:
    errors.append('Platform keys are missing')

# Pilot migration must remain fail-open and legacy-compatible.
legacy = (root / 'core' / 'components' / 'modxmcp' / 'model' / 'modxmcp.class.php').read_text(encoding='utf-8')
for needle in (
    'private $modularRuntime = null;',
    'registry()->get($action)',
    '->supports($this->modularRuntime->context())',
    '->execute($this->modularRuntime->context()',
    'catch (\\Throwable $e)',
):
    if needle not in legacy:
        errors.append(f'Legacy bridge invariant missing: {needle}')

registry_pos = legacy.find('registry()->get($action)')
legacy_dispatch_pos = legacy.find('$this->resolveActionSpec($action)')
if registry_pos < 0 or legacy_dispatch_pos < 0 or registry_pos > legacy_dispatch_pos:
    errors.append('Modular registry must get first refusal before legacy action dispatch')

# Element tools receive `type` as a separate HTTP/processRequest argument; both
# platform fallbacks must bridge it into modular tool data.
for rel in (
    'core/components/modxmcp/model/modxmcp.class.php',
    'core/components/modxmcp/legacy/modx2/modxmcp.class.php',
):
    text = (root / rel).read_text(encoding='utf-8')
    if "$toolData['type'] = $elementType" not in text:
        errors.append(f'{rel}: modular element type bridge missing')

# Filesystem media-source reads must remain behind the explicit security setting.
media_support = (src / 'Tools' / 'MediaSourceSupport.php').read_text(encoding='utf-8')
if 'modxmcp.allow_root_filesystem_read' not in media_support or 'assertReadAllowed' not in media_support:
    errors.append('MediaSourceSupport filesystem-read security gate missing')
for rel in ('MediaSourceFilesTool.php', 'MediaSourceFileReadTool.php'):
    text = (src / 'Tools' / rel).read_text(encoding='utf-8')
    if 'MediaSourceSupport::assertReadAllowed' not in text:
        errors.append(f'{rel}: filesystem-read security gate call missing')

# Migrated tools must be explicit and centrally registered.
tools = sorted(p.name for p in (src / 'Tools').glob('*Tool.php') if p.name != 'ToolInterface.php')
expected_tools = [
    'AccessPermissionListTool.php',
    'AccessPolicyListTool.php',
    'AccessPolicyTemplateListTool.php',
    'AuditLogReadTool.php',
    'CapabilitiesTool.php',
    'CheckIntegrationsTool.php',
    'ComponentFileReadTool.php',
    'ComponentFilesTool.php',
    'ContextAccessListTool.php',
    'ContextGetTool.php',
    'ContextListTool.php',
    'ContextSettingGetTool.php',
    'ContextSettingListTool.php',
    'DependencyGraphTool.php',
    'DescribeObjectTool.php',
    'ElementGetTool.php',
    'ElementListTool.php',
    'ElementViewTool.php',
    'ErrorLogReadTool.php',
    'FindUsagesTool.php',
    'HelpTool.php',
    'InstalledComponentsTool.php',
    'LexiconEntryListTool.php',
    'LexiconTopicListTool.php',
    'ListActionsTool.php',
    'MediaSourceFileReadTool.php',
    'MediaSourceFilesTool.php',
    'MediaSourceGetTool.php',
    'MediaSourceListTool.php',
    'MigxConfigGetTool.php',
    'MigxConfigListTool.php',
    'Ms2CategoryListTool.php',
    'Ms2LinkTypeGetTool.php',
    'Ms2LinkTypeListTool.php',
    'Ms2OptionGetTool.php',
    'Ms2OptionListTool.php',
    'Ms2OptionTypeListTool.php',
    'Ms2OrderGetTool.php',
    'Ms2OrderListTool.php',
    'Ms2ProductLinkListTool.php',
    'Ms2ProductOptionsGetTool.php',
    'NamespaceListTool.php',
    'PackageSearchTool.php',
    'ProjectOverviewTool.php',
    'PropertySetGetTool.php',
    'PropertySetListTool.php',
    'ProviderListTool.php',
    'ResourceGroupAccessListTool.php',
    'ResourceGroupListTool.php',
    'ResourceListTool.php',
    'ResourceTvListTool.php',
    'RoleGetTool.php',
    'RoleListTool.php',
    'SearchCodeTool.php',
    'SystemInfoTool.php',
    'SystemSettingGetTool.php',
    'SystemSettingListTool.php',
    'TvInputTypeListTool.php',
    'TvTypeSuggestTool.php',
    'TvValueListTool.php',
    'UserGetTool.php',
    'UserGroupGetTool.php',
    'UserGroupListTool.php',
    'UserGroupMemberListTool.php',
    'UserListTool.php',
    'VersionXVersionGetTool.php',
    'VersionXVersionListTool.php',
    'VirtualPageEventGetTool.php',
    'VirtualPageEventListTool.php',
    'VirtualPageHandlerGetTool.php',
    'VirtualPageHandlerListTool.php',
    'VirtualPageRouteGetTool.php',
    'VirtualPageRouteListTool.php',
    'VirtualPageRouteResolveTool.php',
]
if tools != expected_tools:
    errors.append(f'Unexpected migrated tool set: {tools}')

if not (src / 'Tools' / 'FilesystemSupport.php').is_file():
    errors.append('FilesystemSupport.php is required by modular filesystem tools')
if not (src / 'Tools' / 'ComponentSupport.php').is_file():
    errors.append('ComponentSupport.php is required by modular component tools')
if not (src / 'Tools' / 'MediaSourceSupport.php').is_file():
    errors.append('MediaSourceSupport.php is required by modular media source tools')
if not (src / 'Tools' / 'ElementSupport.php').is_file():
    errors.append('ElementSupport.php is required by modular element tools')
if not (src / 'Tools' / 'ProcessorSupport.php').is_file():
    errors.append('ProcessorSupport.php is required by modular processor tools')
if not (src / 'Tools' / 'PackageSupport.php').is_file():
    errors.append('PackageSupport.php is required by modular package tools')
if not (src / 'Tools' / 'AclProcessorSupport.php').is_file():
    errors.append('AclProcessorSupport.php is required by modular ACL tools')
if not (src / 'Tools' / 'ObjectSupport.php').is_file():
    errors.append('ObjectSupport.php is required by describe_object')
if not (src / 'Tools' / 'SearchSupport.php').is_file():
    errors.append('SearchSupport.php is required by modular search tools')
if not (src / 'Tools' / 'ElementContentSupport.php').is_file():
    errors.append('ElementContentSupport.php is required by view_element')
if not (src / 'Tools' / 'DependencyGraphService.php').is_file():
    errors.append('DependencyGraphService.php is required by dependency_graph')
if not (src / 'Tools' / 'VersionXSupport.php').is_file():
    errors.append('VersionXSupport.php is required by VersionX tools')
if not (src / 'Tools' / 'MigxSupport.php').is_file():
    errors.append('MigxSupport.php is required by MIGX tools')
if not (src / 'Tools' / 'MiniShop2Support.php').is_file():
    errors.append('MiniShop2Support.php is required by miniShop2 tools')
if not (src / 'Tools' / 'VirtualPageSupport.php').is_file():
    errors.append('VirtualPageSupport.php is required by VirtualPage tools')

runtime_text = (src / 'Core' / 'Runtime.php').read_text(encoding='utf-8')
expected_registrations = {
    'AccessPermissionListTool.php': 'new AccessPermissionListTool()',
    'AccessPolicyListTool.php': 'new AccessPolicyListTool()',
    'AccessPolicyTemplateListTool.php': 'new AccessPolicyTemplateListTool()',
    'AuditLogReadTool.php': 'new AuditLogReadTool()',
    'CapabilitiesTool.php': 'new CapabilitiesTool()',
    'CheckIntegrationsTool.php': 'new CheckIntegrationsTool()',
    'ComponentFileReadTool.php': 'new ComponentFileReadTool()',
    'ComponentFilesTool.php': 'new ComponentFilesTool()',
    'ContextAccessListTool.php': 'new ContextAccessListTool()',
    'ContextSettingListTool.php': 'new ContextSettingListTool()',
    'ContextSettingGetTool.php': 'new ContextSettingGetTool()',
    'ContextListTool.php': 'new ContextListTool()',
    'ContextGetTool.php': 'new ContextGetTool()',
    'DependencyGraphTool.php': 'new DependencyGraphTool()',
    'DescribeObjectTool.php': 'new DescribeObjectTool()',
    'ElementGetTool.php': 'new ElementGetTool()',
    'ElementListTool.php': 'new ElementListTool()',
    'ElementViewTool.php': 'new ElementViewTool()',
    'ErrorLogReadTool.php': 'new ErrorLogReadTool()',
    'FindUsagesTool.php': 'new FindUsagesTool()',
    'HelpTool.php': 'new HelpTool()',
    'InstalledComponentsTool.php': 'new InstalledComponentsTool()',
    'LexiconEntryListTool.php': 'new LexiconEntryListTool()',
    'LexiconTopicListTool.php': 'new LexiconTopicListTool()',
    'ListActionsTool.php': 'new ListActionsTool()',
    'MediaSourceFileReadTool.php': 'new MediaSourceFileReadTool()',
    'MediaSourceFilesTool.php': 'new MediaSourceFilesTool()',
    'MediaSourceGetTool.php': 'new MediaSourceGetTool()',
    'MediaSourceListTool.php': 'new MediaSourceListTool()',
    'MigxConfigGetTool.php': 'new MigxConfigGetTool()',
    'MigxConfigListTool.php': 'new MigxConfigListTool()',
    'Ms2CategoryListTool.php': 'new Ms2CategoryListTool()',
    'Ms2LinkTypeGetTool.php': 'new Ms2LinkTypeGetTool()',
    'Ms2LinkTypeListTool.php': 'new Ms2LinkTypeListTool()',
    'Ms2OptionGetTool.php': 'new Ms2OptionGetTool()',
    'Ms2OptionListTool.php': 'new Ms2OptionListTool()',
    'Ms2OptionTypeListTool.php': 'new Ms2OptionTypeListTool()',
    'Ms2OrderGetTool.php': 'new Ms2OrderGetTool()',
    'Ms2OrderListTool.php': 'new Ms2OrderListTool()',
    'Ms2ProductLinkListTool.php': 'new Ms2ProductLinkListTool()',
    'Ms2ProductOptionsGetTool.php': 'new Ms2ProductOptionsGetTool()',
    'NamespaceListTool.php': 'new NamespaceListTool()',
    'PackageSearchTool.php': 'new PackageSearchTool()',
    'PropertySetGetTool.php': 'new PropertySetGetTool()',
    'PropertySetListTool.php': 'new PropertySetListTool()',
    'ProviderListTool.php': 'new ProviderListTool()',
    'ResourceGroupAccessListTool.php': 'new ResourceGroupAccessListTool()',
    'ResourceGroupListTool.php': 'new ResourceGroupListTool()',
    'ProjectOverviewTool.php': 'new ProjectOverviewTool()',
    'ResourceListTool.php': 'new ResourceListTool()',
    'ResourceTvListTool.php': 'new ResourceTvListTool()',
    'RoleGetTool.php': 'new RoleGetTool()',
    'RoleListTool.php': 'new RoleListTool()',
    'SearchCodeTool.php': 'new SearchCodeTool()',
    'SystemInfoTool.php': 'new SystemInfoTool()',
    'SystemSettingGetTool.php': 'new SystemSettingGetTool()',
    'SystemSettingListTool.php': 'new SystemSettingListTool()',
    'TvInputTypeListTool.php': 'new TvInputTypeListTool()',
    'TvTypeSuggestTool.php': 'new TvTypeSuggestTool()',
    'TvValueListTool.php': 'new TvValueListTool()',
    'UserGetTool.php': 'new UserGetTool()',
    'UserGroupGetTool.php': 'new UserGroupGetTool()',
    'UserGroupListTool.php': 'new UserGroupListTool()',
    'UserGroupMemberListTool.php': 'new UserGroupMemberListTool()',
    'UserListTool.php': 'new UserListTool()',
    'VersionXVersionGetTool.php': 'new VersionXVersionGetTool()',
    'VersionXVersionListTool.php': 'new VersionXVersionListTool()',
    'VirtualPageEventGetTool.php': 'new VirtualPageEventGetTool()',
    'VirtualPageEventListTool.php': 'new VirtualPageEventListTool()',
    'VirtualPageHandlerGetTool.php': 'new VirtualPageHandlerGetTool()',
    'VirtualPageHandlerListTool.php': 'new VirtualPageHandlerListTool()',
    'VirtualPageRouteGetTool.php': 'new VirtualPageRouteGetTool()',
    'VirtualPageRouteListTool.php': 'new VirtualPageRouteListTool()',
    'VirtualPageRouteResolveTool.php': 'new VirtualPageRouteResolveTool()',
}
for filename, needle in expected_registrations.items():
    if needle not in runtime_text:
        errors.append(f'{filename} exists but is not registered in Runtime')

if errors:
    print('ARCHITECTURE_TEST_FAIL')
    for e in errors:
        print('-', e)
    sys.exit(1)

print('ARCHITECTURE_TEST_OK')
