<?php
namespace ModxMcp\Tools;

class ProjectOverviewTool implements ToolInterface
{
    public function name() { return 'project_overview'; }
    public function group() { return 'ops'; }
    public function isMutation() { return false; }

    public function supports($context)
    {
        if (!$context || !$context->modx() || !$context->platform()) { return false; }
        $key = $context->platform()->key();
        return $key === 'modx2' || $key === 'modx3';
    }

    public function execute($context, array $data)
    {
        $modx = $context->modx();
        $platform = $context->platform();
        $resourceClass = $platform->className('resource');
        $templateClass = $platform->className('template');
        $tvClass = $platform->className('tv');
        $chunkClass = $platform->className('chunk');
        $snippetClass = $platform->className('snippet');
        $pluginClass = $platform->className('plugin');
        $categoryClass = $platform->className('category');
        $contextClass = $platform->className('context');
        $userClass = $platform->className('user');
        $tvtClass = $platform->className('template_var_template');
        $contentTypeClass = $platform->className('content_type');
        $mediaSourceClass = $platform->className('media_source');

        $all = array('modx', 'counts', 'templates', 'tvs', 'resource_tree', 'resources_by_template', 'resources_by_context', 'element_categories', 'content_types', 'contexts', 'integrations');
        $req = (isset($data['sections']) && is_array($data['sections']) && $data['sections'])
            ? array_values(array_intersect($all, $data['sections']))
            : $all;
        $want = array_flip($req);
        $cap = 200;
        $maxTree = isset($data['max_tree_nodes']) ? max(1, (int) $data['max_tree_nodes']) : 50;
        $cnt = function ($class, $crit = null) use ($modx) { return (int) $modx->getCount($class, $crit); };
        $out = array();

        if (isset($want['modx'])) {
            $v = method_exists($modx, 'getVersionData') ? $modx->getVersionData() : array();
            $out['modx'] = array(
                'version'         => isset($v['full_version']) ? $v['full_version'] : (isset($v['version']) ? $v['version'] : null),
                'site_url'        => $modx->getOption('site_url'),
                'modxmcp_version' => $context->connectorVersion(),
            );
        }

        if (isset($want['counts'])) {
            $out['counts'] = array(
                'resources'           => $cnt($resourceClass),
                'published_resources' => $cnt($resourceClass, array('published' => 1, 'deleted' => 0)),
                'deleted_resources'   => $cnt($resourceClass, array('deleted' => 1)),
                'templates'           => $cnt($templateClass),
                'tvs'                 => $cnt($tvClass),
                'chunks'              => $cnt($chunkClass),
                'snippets'            => $cnt($snippetClass),
                'plugins'             => $cnt($pluginClass),
                'categories'          => $cnt($categoryClass),
                'contexts'            => $cnt($contextClass),
                'users'               => $cnt($userClass),
                'media_sources'       => $cnt($mediaSourceClass),
            );
            $products = $cnt($resourceClass, array('class_key' => 'msProduct'));
            if ($products > 0) { $out['counts']['ms2_products'] = $products; }
        }

        // Build the template list once (reused by templates + resources_by_template).
        $tplRows = null;
        if (isset($want['templates']) || isset($want['resources_by_template'])) {
            $tplRows = array();
            $q = $modx->newQuery($templateClass);
            $q->select(array('id', 'templatename'));
            $q->sortby('templatename', 'ASC');
            $q->limit($cap);
            foreach ($modx->getCollection($templateClass, $q) as $t) {
                $tplRows[(int) $t->get('id')] = $t->get('templatename');
            }
        }

        if (isset($want['templates'])) {
            $tplTotal = $cnt($templateClass);
            // tv names + template→tv attachments (bounded by #templates × #tvs).
            $tvName = array();
            $tq = $modx->newQuery($tvClass);
            $tq->select(array('id', 'name'));
            foreach ($modx->getCollection($tvClass, $tq) as $tv) { $tvName[(int) $tv->get('id')] = $tv->get('name'); }
            $attach = array();
            $lq = $modx->newQuery($tvtClass);
            $lq->select(array('templateid', 'tmplvarid'));
            foreach ($modx->getCollection($tvtClass, $lq) as $l) {
                $attach[(int) $l->get('templateid')][] = (int) $l->get('tmplvarid');
            }
            $items = array();
            foreach ($tplRows as $tid => $name) {
                $tvids = isset($attach[$tid]) ? $attach[$tid] : array();
                $names = array();
                foreach ($tvids as $i) { if (isset($tvName[$i])) { $names[] = $tvName[$i]; } }
                $items[] = array('id' => $tid, 'name' => $name, 'tv_ids' => $tvids, 'tv_names' => $names, 'resource_count' => $cnt($resourceClass, array('template' => $tid)));
            }
            $out['templates'] = array('total' => $tplTotal, 'truncated' => $tplTotal > count($items), 'items' => $items);
        }

        if (isset($want['resources_by_template'])) {
            $rbt = array();
            foreach ($tplRows as $tid => $name) { $rbt[] = array('template_id' => $tid, 'template_name' => $name, 'count' => $cnt($resourceClass, array('template' => $tid))); }
            $out['resources_by_template'] = $rbt;
        }

        if (isset($want['tvs'])) {
            $tvTotal = $cnt($tvClass);
            $items = array();
            $q = $modx->newQuery($tvClass);
            $q->select(array('id', 'name', 'type', 'caption'));
            $q->sortby('name', 'ASC');
            $q->limit($cap);
            foreach ($modx->getCollection($tvClass, $q) as $tv) {
                $items[] = array('id' => (int) $tv->get('id'), 'name' => $tv->get('name'), 'type' => $tv->get('type'), 'caption' => $tv->get('caption'));
            }
            $out['tvs'] = array('total' => $tvTotal, 'truncated' => $tvTotal > count($items), 'items' => $items);
        }

        if (isset($want['resource_tree'])) {
            $rootTotal = $cnt($resourceClass, array('parent' => 0, 'deleted' => 0));
            $q = $modx->newQuery($resourceClass, array('parent' => 0, 'deleted' => 0));
            $q->select(array('id', 'pagetitle', 'context_key', 'template', 'published', 'isfolder'));
            $q->sortby('context_key', 'ASC');
            $q->sortby('menuindex', 'ASC');
            $q->limit($maxTree);
            $roots = array();
            foreach ($modx->getCollection($resourceClass, $q) as $r) {
                $rid = (int) $r->get('id');
                $roots[] = array(
                    'id'          => $rid,
                    'pagetitle'   => $r->get('pagetitle'),
                    'context_key' => $r->get('context_key'),
                    'template'    => (int) $r->get('template'),
                    'published'   => (bool) $r->get('published'),
                    'isfolder'    => (bool) $r->get('isfolder'),
                    'child_count' => $cnt($resourceClass, array('parent' => $rid, 'deleted' => 0)),
                );
            }
            $out['resource_tree'] = array('depth' => 1, 'total_roots' => $rootTotal, 'truncated' => $rootTotal > count($roots), 'note' => 'Roots + child counts only. Drill down with list_resources(parent=...).', 'roots' => $roots);
        }

        if (isset($want['resources_by_context'])) {
            $rbc = array();
            $cq = $modx->newQuery($contextClass);
            $cq->select(array('key'));
            foreach ($modx->getCollection($contextClass, $cq) as $ctx) {
                $k = $ctx->get('key');
                $rbc[] = array('context' => $k, 'count' => $cnt($resourceClass, array('context_key' => $k, 'deleted' => 0)));
            }
            $out['resources_by_context'] = $rbc;
        }

        if (isset($want['element_categories'])) {
            $catTotal = $cnt($categoryClass);
            $items = array();
            $q = $modx->newQuery($categoryClass);
            $q->select(array('id', 'category'));
            $q->sortby('category', 'ASC');
            $q->limit($cap);
            foreach ($modx->getCollection($categoryClass, $q) as $c) { $items[] = array('id' => (int) $c->get('id'), 'name' => $c->get('category')); }
            $out['element_categories'] = array('total' => $catTotal, 'truncated' => $catTotal > count($items), 'items' => $items);
        }

        if (isset($want['content_types'])) {
            $items = array();
            $q = $modx->newQuery($contentTypeClass);
            $q->select(array('id', 'name', 'mime_type', 'file_extensions'));
            $q->limit($cap);
            foreach ($modx->getCollection($contentTypeClass, $q) as $ct) {
                $items[] = array('id' => (int) $ct->get('id'), 'name' => $ct->get('name'), 'mime' => $ct->get('mime_type'), 'extensions' => $ct->get('file_extensions'));
            }
            $out['content_types'] = $items;
        }

        if (isset($want['contexts'])) {
            $items = array();
            $q = $modx->newQuery($contextClass);
            $q->select(array('key', 'name'));
            foreach ($modx->getCollection($contextClass, $q) as $ctx) { $items[] = array('key' => $ctx->get('key'), 'name' => $ctx->get('name')); }
            $out['contexts'] = $items;
        }

        if (isset($want['integrations'])) {
            $rep = $context->extras() ? $context->extras()->report($context) : array('integrations' => array());
            $out['integrations'] = isset($rep['integrations']) ? $rep['integrations'] : array();
        }

        return $out;

    }
}
