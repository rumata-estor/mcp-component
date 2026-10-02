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
    'BulkResourcesTool.php',
    'CapabilitiesTool.php',
    'CheckIntegrationsTool.php',
    'ClearCacheTool.php',
    'ComponentFileReadTool.php',
    'ComponentFilesTool.php',
    'ContextAccessListTool.php',
    'ContextGetTool.php',
    'ContextListTool.php',
    'ContextSettingGetTool.php',
    'ContextSettingListTool.php',
    'DependencyGraphTool.php',
    'DescribeObjectTool.php',
    'ElementCreateTool.php',
    'ElementDeleteTool.php',
    'ElementDuplicateTool.php',
    'ElementEditLinesTool.php',
    'ElementGetTool.php',
    'ElementListTool.php',
    'ElementMakeStaticTool.php',
    'ElementUpdateTool.php',
    'ElementViewTool.php',
    'ErrorLogReadTool.php',
    'FindUsagesTool.php',
    'FlushPermissionsTool.php',
    'HelpTool.php',
    'InstalledComponentsTool.php',
    'LexiconEntryListTool.php',
    'LexiconTopicListTool.php',
    'ListActionsTool.php',
    'MediaFileCreateTool.php',
    'MediaFileDeleteTool.php',
    'MediaFileRenameTool.php',
    'MediaFileUpdateTool.php',
    'MediaFolderCreateTool.php',
    'MediaFolderDeleteTool.php',
    'MediaSourceCreateTool.php',
    'MediaSourceDeleteTool.php',
    'MediaSourceFileReadTool.php',
    'MediaSourceFilesTool.php',
    'MediaSourceGetTool.php',
    'MediaSourceListTool.php',
    'MediaSourceUpdateTool.php',
    'MigxConfigCreateTool.php',
    'MigxConfigDeleteTool.php',
    'MigxConfigGetTool.php',
    'MigxConfigListTool.php',
    'MigxConfigUpdateTool.php',
    'Ms2CategoryCreateTool.php',
    'Ms2CategoryListTool.php',
    'Ms2CategoryUpdateTool.php',
    'Ms2LinkTypeCreateTool.php',
    'Ms2LinkTypeDeleteTool.php',
    'Ms2LinkTypeGetTool.php',
    'Ms2LinkTypeListTool.php',
    'Ms2LinkTypeUpdateTool.php',
    'Ms2OptionAssignCategoryTool.php',
    'Ms2OptionCreateTool.php',
    'Ms2OptionGetTool.php',
    'Ms2OptionListTool.php',
    'Ms2OptionTypeListTool.php',
    'Ms2OptionUpdateTool.php',
    'Ms2OrderGetTool.php',
    'Ms2OrderListTool.php',
    'Ms2OrderUpdateTool.php',
    'Ms2ProductLinkCreateTool.php',
    'Ms2ProductLinkDeleteTool.php',
    'Ms2ProductLinkListTool.php',
    'Ms2ProductOptionsGetTool.php',
    'Ms2ProductOptionsUpdateTool.php',
    'NamespaceListTool.php',
    'PackageInstallTool.php',
    'PackageSearchTool.php',
    'PackageUninstallTool.php',
    'ProcessorMutationTool.php',
    'ProjectOverviewTool.php',
    'PropertySetAssignTool.php',
    'PropertySetCreateTool.php',
    'PropertySetDeleteTool.php',
    'PropertySetGetTool.php',
    'PropertySetListTool.php',
    'PropertySetUnassignTool.php',
    'PropertySetUpdateTool.php',
    'ProviderCreateTool.php',
    'ProviderDeleteTool.php',
    'ProviderListTool.php',
    'ProviderUpdateTool.php',
    'RefreshUrisTool.php',
    'RegenerateTokenTool.php',
    'RemoveLocksTool.php',
    'ReplaceAcrossTool.php',
    'ResourceDuplicateTool.php',
    'ResourceGroupAccessListTool.php',
    'ResourceGroupListTool.php',
    'ResourceListTool.php',
    'ResourceRecycleBinEmptyTool.php',
    'ResourceReorderTool.php',
    'ResourceTvListTool.php',
    'ResourceTvUpdateTool.php',
    'ResourceUndeleteTool.php',
    'RoleGetTool.php',
    'RoleListTool.php',
    'RunProcessorTool.php',
    'SearchCodeTool.php',
    'SystemInfoTool.php',
    'SystemSettingCreateTool.php',
    'SystemSettingDeleteTool.php',
    'SystemSettingGetTool.php',
    'SystemSettingListTool.php',
    'SystemSettingUpdateTool.php',
    'TvInputTypeListTool.php',
    'TvTypeSuggestTool.php',
    'TvValueClearTool.php',
    'TvValueListTool.php',
    'UserGetTool.php',
    'UserGroupGetTool.php',
    'UserGroupListTool.php',
    'UserGroupMemberListTool.php',
    'UserListTool.php',
    'VersionXVersionGetTool.php',
    'VersionXVersionListTool.php',
    'VersionXVersionRevertTool.php',
    'VirtualPageClearCacheTool.php',
    'VirtualPageEventCreateTool.php',
    'VirtualPageEventDeleteTool.php',
    'VirtualPageEventGetTool.php',
    'VirtualPageEventListTool.php',
    'VirtualPageEventUpdateTool.php',
    'VirtualPageHandlerCreateTool.php',
    'VirtualPageHandlerDeleteTool.php',
    'VirtualPageHandlerGetTool.php',
    'VirtualPageHandlerListTool.php',
    'VirtualPageHandlerUpdateTool.php',
    'VirtualPageRouteCreateTool.php',
    'VirtualPageRouteDeleteTool.php',
    'VirtualPageRouteGetTool.php',
    'VirtualPageRouteListTool.php',
    'VirtualPageRouteResolveTool.php',
    'VirtualPageRouteUpdateTool.php',
]
if tools != expected_tools:
    errors.append(f'Unexpected migrated tool set: {tools}')

if not (src / 'Tools' / 'FilesystemSupport.php').is_file():
    errors.append('FilesystemSupport.php is required by modular filesystem tools')
if not (src / 'Tools' / 'ComponentSupport.php').is_file():
    errors.append('ComponentSupport.php is required by modular component tools')
if not (src / 'Tools' / 'MediaSourceSupport.php').is_file():
    errors.append('MediaSourceSupport.php is required by modular media source tools')
if not (src / 'Tools' / 'MediaSourceMutationSupport.php').is_file():
    errors.append('MediaSourceMutationSupport.php is required by media mutations')
if not (src / 'Tools' / 'ElementSupport.php').is_file():
    errors.append('ElementSupport.php is required by modular element tools')
if not (src / 'Tools' / 'ElementMutationSupport.php').is_file():
    errors.append('ElementMutationSupport.php is required by modular element mutations')
if not (src / 'Tools' / 'ProcessorSupport.php').is_file():
    errors.append('ProcessorSupport.php is required by modular processor tools')
if not (src / 'Tools' / 'PropertySetMutationSupport.php').is_file():
    errors.append('PropertySetMutationSupport.php is required by property-set mutations')
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
if not (src / 'Tools' / 'MigxMutationSupport.php').is_file():
    errors.append('MigxMutationSupport.php is required by MIGX mutations')
if not (src / 'Tools' / 'MiniShop2Support.php').is_file():
    errors.append('MiniShop2Support.php is required by miniShop2 tools')
if not (src / 'Tools' / 'MiniShop2MutationSupport.php').is_file():
    errors.append('MiniShop2MutationSupport.php is required by miniShop2 mutations')
if not (src / 'Tools' / 'VirtualPageSupport.php').is_file():
    errors.append('VirtualPageSupport.php is required by VirtualPage tools')
if not (src / 'Tools' / 'AuditSupport.php').is_file():
    errors.append('AuditSupport.php is required by modular mutations')
if not (src / 'Registry' / 'MutationProcessorCatalog.php').is_file():
    errors.append('MutationProcessorCatalog.php is required by modular processor mutations')

runtime_text = (src / 'Core' / 'Runtime.php').read_text(encoding='utf-8')
if 'MutationProcessorCatalog::specs()' not in runtime_text or 'new ProcessorMutationTool($name, $spec)' not in runtime_text:
    errors.append('Processor mutation catalog is not registered in Runtime')
expected_registrations = {
    'AccessPermissionListTool.php': 'new AccessPermissionListTool()',
    'AccessPolicyListTool.php': 'new AccessPolicyListTool()',
    'AccessPolicyTemplateListTool.php': 'new AccessPolicyTemplateListTool()',
    'AuditLogReadTool.php': 'new AuditLogReadTool()',
    'CapabilitiesTool.php': 'new CapabilitiesTool()',
    'ClearCacheTool.php': 'new ClearCacheTool()',
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
    'ElementCreateTool.php': 'new ElementCreateTool()',
    'ElementDeleteTool.php': 'new ElementDeleteTool()',
    'ElementEditLinesTool.php': 'new ElementEditLinesTool()',
    'ElementDuplicateTool.php': 'new ElementDuplicateTool()',
    'ResourceDuplicateTool.php': 'new ResourceDuplicateTool()',
    'ResourceRecycleBinEmptyTool.php': 'new ResourceRecycleBinEmptyTool()',
    'ResourceReorderTool.php': 'new ResourceReorderTool()',
    'BulkResourcesTool.php': 'new BulkResourcesTool()',
    'ResourceUndeleteTool.php': 'new ResourceUndeleteTool()',
    'ElementMakeStaticTool.php': 'new ElementMakeStaticTool()',
    'ElementGetTool.php': 'new ElementGetTool()',
    'ElementUpdateTool.php': 'new ElementUpdateTool()',
    'ElementListTool.php': 'new ElementListTool()',
    'ElementViewTool.php': 'new ElementViewTool()',
    'ErrorLogReadTool.php': 'new ErrorLogReadTool()',
    'FindUsagesTool.php': 'new FindUsagesTool()',
    'ReplaceAcrossTool.php': 'new ReplaceAcrossTool()',
    'FlushPermissionsTool.php': 'new FlushPermissionsTool()',
    'HelpTool.php': 'new HelpTool()',
    'InstalledComponentsTool.php': 'new InstalledComponentsTool()',
    'LexiconEntryListTool.php': 'new LexiconEntryListTool()',
    'LexiconTopicListTool.php': 'new LexiconTopicListTool()',
    'ListActionsTool.php': 'new ListActionsTool()',
    'MediaSourceFileReadTool.php': 'new MediaSourceFileReadTool()',
    'MediaSourceFilesTool.php': 'new MediaSourceFilesTool()',
    'MediaSourceGetTool.php': 'new MediaSourceGetTool()',
    'MediaSourceListTool.php': 'new MediaSourceListTool()',
    'MediaFileCreateTool.php': 'new MediaFileCreateTool()',
    'MediaFileDeleteTool.php': 'new MediaFileDeleteTool()',
    'MediaFileRenameTool.php': 'new MediaFileRenameTool()',
    'MediaFileUpdateTool.php': 'new MediaFileUpdateTool()',
    'MediaFolderCreateTool.php': 'new MediaFolderCreateTool()',
    'MediaFolderDeleteTool.php': 'new MediaFolderDeleteTool()',
    'MediaSourceCreateTool.php': 'new MediaSourceCreateTool()',
    'MediaSourceDeleteTool.php': 'new MediaSourceDeleteTool()',
    'MediaSourceUpdateTool.php': 'new MediaSourceUpdateTool()',
    'MigxConfigGetTool.php': 'new MigxConfigGetTool()',
    'MigxConfigCreateTool.php': 'new MigxConfigCreateTool()',
    'MigxConfigDeleteTool.php': 'new MigxConfigDeleteTool()',
    'MigxConfigUpdateTool.php': 'new MigxConfigUpdateTool()',
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
    'Ms2CategoryCreateTool.php': 'new Ms2CategoryCreateTool()',
    'Ms2CategoryUpdateTool.php': 'new Ms2CategoryUpdateTool()',
    'Ms2LinkTypeCreateTool.php': 'new Ms2LinkTypeCreateTool()',
    'Ms2LinkTypeDeleteTool.php': 'new Ms2LinkTypeDeleteTool()',
    'Ms2LinkTypeUpdateTool.php': 'new Ms2LinkTypeUpdateTool()',
    'Ms2OptionAssignCategoryTool.php': 'new Ms2OptionAssignCategoryTool()',
    'Ms2OptionCreateTool.php': 'new Ms2OptionCreateTool()',
    'Ms2OptionUpdateTool.php': 'new Ms2OptionUpdateTool()',
    'Ms2OrderUpdateTool.php': 'new Ms2OrderUpdateTool()',
    'Ms2ProductLinkCreateTool.php': 'new Ms2ProductLinkCreateTool()',
    'Ms2ProductLinkDeleteTool.php': 'new Ms2ProductLinkDeleteTool()',
    'Ms2ProductOptionsUpdateTool.php': 'new Ms2ProductOptionsUpdateTool()',
    'NamespaceListTool.php': 'new NamespaceListTool()',
    'PackageSearchTool.php': 'new PackageSearchTool()',
    'PackageInstallTool.php': 'new PackageInstallTool()',
    'PackageUninstallTool.php': 'new PackageUninstallTool()',
    'ProviderCreateTool.php': 'new ProviderCreateTool()',
    'ProviderDeleteTool.php': 'new ProviderDeleteTool()',
    'ProviderUpdateTool.php': 'new ProviderUpdateTool()',
    'PropertySetGetTool.php': 'new PropertySetGetTool()',
    'PropertySetListTool.php': 'new PropertySetListTool()',
    'PropertySetAssignTool.php': 'new PropertySetAssignTool()',
    'PropertySetCreateTool.php': 'new PropertySetCreateTool()',
    'PropertySetDeleteTool.php': 'new PropertySetDeleteTool()',
    'PropertySetUnassignTool.php': 'new PropertySetUnassignTool()',
    'PropertySetUpdateTool.php': 'new PropertySetUpdateTool()',
    'ProviderListTool.php': 'new ProviderListTool()',
    'ResourceGroupAccessListTool.php': 'new ResourceGroupAccessListTool()',
    'ResourceGroupListTool.php': 'new ResourceGroupListTool()',
    'ProjectOverviewTool.php': 'new ProjectOverviewTool()',
    'ResourceListTool.php': 'new ResourceListTool()',
    'ResourceTvListTool.php': 'new ResourceTvListTool()',
    'ResourceTvUpdateTool.php': 'new ResourceTvUpdateTool()',
    'RunProcessorTool.php': 'new RunProcessorTool()',
    'RefreshUrisTool.php': 'new RefreshUrisTool()',
    'RegenerateTokenTool.php': 'new RegenerateTokenTool()',
    'RemoveLocksTool.php': 'new RemoveLocksTool()',
    'RoleGetTool.php': 'new RoleGetTool()',
    'RoleListTool.php': 'new RoleListTool()',
    'SearchCodeTool.php': 'new SearchCodeTool()',
    'SystemInfoTool.php': 'new SystemInfoTool()',
    'SystemSettingGetTool.php': 'new SystemSettingGetTool()',
    'SystemSettingCreateTool.php': 'new SystemSettingCreateTool()',
    'SystemSettingDeleteTool.php': 'new SystemSettingDeleteTool()',
    'SystemSettingListTool.php': 'new SystemSettingListTool()',
    'SystemSettingUpdateTool.php': 'new SystemSettingUpdateTool()',
    'TvInputTypeListTool.php': 'new TvInputTypeListTool()',
    'TvTypeSuggestTool.php': 'new TvTypeSuggestTool()',
    'TvValueListTool.php': 'new TvValueListTool()',
    'TvValueClearTool.php': 'new TvValueClearTool()',
    'UserGetTool.php': 'new UserGetTool()',
    'UserGroupGetTool.php': 'new UserGroupGetTool()',
    'UserGroupListTool.php': 'new UserGroupListTool()',
    'UserGroupMemberListTool.php': 'new UserGroupMemberListTool()',
    'UserListTool.php': 'new UserListTool()',
    'VersionXVersionGetTool.php': 'new VersionXVersionGetTool()',
    'VersionXVersionListTool.php': 'new VersionXVersionListTool()',
    'VirtualPageClearCacheTool.php': 'new VirtualPageClearCacheTool()',
    'VirtualPageEventCreateTool.php': 'new VirtualPageEventCreateTool()',
    'VirtualPageEventDeleteTool.php': 'new VirtualPageEventDeleteTool()',
    'VirtualPageEventUpdateTool.php': 'new VirtualPageEventUpdateTool()',
    'VirtualPageHandlerCreateTool.php': 'new VirtualPageHandlerCreateTool()',
    'VirtualPageHandlerDeleteTool.php': 'new VirtualPageHandlerDeleteTool()',
    'VirtualPageHandlerUpdateTool.php': 'new VirtualPageHandlerUpdateTool()',
    'VirtualPageRouteCreateTool.php': 'new VirtualPageRouteCreateTool()',
    'VirtualPageRouteDeleteTool.php': 'new VirtualPageRouteDeleteTool()',
    'VirtualPageRouteUpdateTool.php': 'new VirtualPageRouteUpdateTool()',
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
