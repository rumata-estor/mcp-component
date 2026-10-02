<?php
namespace ModxMcp\Tools;

class BulkResourcesTool implements ToolInterface
{
    public function name() { return 'bulk_resources'; }
    public function group() { return 'elements'; }
    public function isMutation() { return true; }
    public function supports($context) { return $context && $context->modx() && $context->platform(); }

    public function execute($context, array $data)
    {
        $operation = isset($data['operation']) ? (string)$data['operation'] : '';
        $operations = array('publish', 'unpublish', 'set_template', 'move', 'delete');
        if (!in_array($operation, $operations, true)) {
            throw new \ModxMCPClientException(
                'bulk_resources: operation must be one of: '
                . implode(', ', $operations) . '.'
            );
        }

        $limit = isset($data['limit']) ? max(1, (int)$data['limit']) : 200;
        $ids = array();

        if (isset($data['ids']) && is_array($data['ids'])) {
            foreach ($data['ids'] as $id) {
                $id = (int)$id;
                if ($id > 0) { $ids[] = $id; }
            }
        } elseif (
            isset($data['parent'])
            || !empty($data['context'])
            || !empty($data['query'])
        ) {
            $listed = (new ResourceListTool())->execute(
                $context,
                array(
                    'parent' => isset($data['parent']) ? $data['parent'] : '',
                    'context' => isset($data['context']) ? $data['context'] : '',
                    'query' => isset($data['query']) ? $data['query'] : '',
                    'limit' => $limit,
                )
            );
            foreach ($listed['results'] as $resource) {
                $ids[] = (int)$resource['id'];
            }
        }

        $ids = array_values(array_unique($ids));
        if (!$ids) {
            throw new \ModxMCPClientException(
                'bulk_resources: no targets — pass "ids" or a parent/context/query filter.'
            );
        }
        if (count($ids) > $limit) {
            $ids = array_slice($ids, 0, $limit);
        }

        if ($operation === 'set_template') {
            $valid = array_key_exists('template', $data)
                && $data['template'] !== null
                && $data['template'] !== ''
                && filter_var($data['template'], FILTER_VALIDATE_INT) !== false
                && (int)$data['template'] >= 0;
            if (!$valid) {
                throw new \ModxMCPClientException(
                    'bulk_resources: set_template requires "template" as a non-negative integer (0 = no template).'
                );
            }
        }

        if (
            $operation === 'move'
            && !isset($data['parent_to'])
            && empty($data['context_to'])
        ) {
            throw new \ModxMCPClientException(
                'bulk_resources: move requires "parent_to" and/or "context_to".'
            );
        }

        $dryRun = !empty($data['dry_run']);
        $results = array();
        $resourceClass = $context->platform()->className('resource');

        foreach ($ids as $id) {
            $resource = $context->modx()->getObject($resourceClass, $id);
            if (!$resource) {
                $results[] = array('id' => $id, 'status' => 'not_found');
                continue;
            }

            $row = array(
                'id' => $id,
                'pagetitle' => $resource->get('pagetitle'),
            );

            if ($dryRun) {
                if ($operation === 'publish') {
                    $row['change'] = 'published: '
                        . (int)$resource->get('published') . ' -> 1';
                } elseif ($operation === 'unpublish') {
                    $row['change'] = 'published: '
                        . (int)$resource->get('published') . ' -> 0';
                } elseif ($operation === 'set_template') {
                    $row['change'] = 'template: '
                        . (int)$resource->get('template')
                        . ' -> ' . (int)$data['template'];
                } elseif ($operation === 'move') {
                    $row['change'] = 'parent: ' . (int)$resource->get('parent')
                        . (isset($data['parent_to'])
                            ? ' -> ' . (int)$data['parent_to']
                            : '')
                        . (!empty($data['context_to'])
                            ? '; context -> ' . $data['context_to']
                            : '');
                } elseif ($operation === 'delete') {
                    $row['change'] = 'DELETE';
                    $row['child_resources'] = (int)$context->modx()->getCount(
                        $resourceClass,
                        array('parent' => $id)
                    );
                }
                $results[] = $row;
                continue;
            }

            try {
                if ($operation === 'delete') {
                    (new ElementDeleteTool())->execute(
                        $context,
                        array('type' => 'resource', 'id' => $id)
                    );
                    $row['status'] = 'deleted';
                } else {
                    $update = array('type' => 'resource', 'id' => $id);
                    if ($operation === 'publish') {
                        $update['published'] = 1;
                    } elseif ($operation === 'unpublish') {
                        $update['published'] = 0;
                    } elseif ($operation === 'set_template') {
                        $update['template'] = (int)$data['template'];
                    } elseif ($operation === 'move') {
                        if (isset($data['parent_to'])) {
                            $update['parent'] = (int)$data['parent_to'];
                        }
                        if (!empty($data['context_to'])) {
                            $update['context_key'] = (string)$data['context_to'];
                        }
                    }
                    (new ElementUpdateTool())->execute($context, $update);
                    $row['status'] = 'ok';
                }
            } catch (\Exception $e) {
                $row['status'] = 'error';
                $row['error'] = $e->getMessage();
            }
            $results[] = $row;
        }

        if (!$dryRun) {
            ElementMutationSupport::refreshCache($context);
        }
        AuditSupport::log(
            $context,
            $this->name(),
            'resource',
            array(
                'operation' => $operation,
                'count' => count($ids),
                'dry_run' => $dryRun,
            )
        );

        return array(
            'operation' => $operation,
            'dry_run' => $dryRun,
            'count' => count($results),
            'results' => $results,
        );
    }
}
