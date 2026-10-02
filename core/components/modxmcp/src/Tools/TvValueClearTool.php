<?php
namespace ModxMcp\Tools;

class TvValueClearTool implements ToolInterface
{
    public function name() { return 'clear_tv_values'; }
    public function group() { return 'resource_tvs'; }
    public function isMutation() { return true; }
    public function supports($context) { return $context && $context->modx() && $context->platform(); }

    public function execute($context, array $data)
    {
        $tv = $this->resolveTv($context, $data);
        $tvId = (int)$tv->get('id');
        $tvName = (string)$tv->get('name');
        $valueClass = $context->platform()->className(
            'template_var_resource'
        );
        $criteria = array('tmplvarid' => $tvId);
        $total = (int)$context->modx()->getCount(
            $valueClass,
            $criteria
        );

        if (empty($data['confirm']) || $data['confirm'] !== true) {
            return array(
                'confirmed' => false,
                'tv' => array('id' => $tvId, 'name' => $tvName),
                'stored_values' => $total,
                'would_delete' => $total,
            );
        }

        return ElementMutationSupport::transaction(
            $context,
            function () use (
                $context,
                $valueClass,
                $criteria,
                $total,
                $tvId,
                $tvName
            ) {
                if ($total > 0) {
                    $ok = $context->modx()->removeCollection(
                        $valueClass,
                        $criteria
                    );
                    if ($ok === false) {
                        throw new \ModxMCPClientException(
                            'Could not clear stored values for TV '
                            . $tvName . '.'
                        );
                    }
                }

                $remaining = (int)$context->modx()->getCount(
                    $valueClass,
                    $criteria
                );
                if ($remaining !== 0) {
                    throw new \ModxMCPClientException(
                        'TV stored-value cleanup incomplete: '
                        . $remaining . ' rows remain.'
                    );
                }

                ElementMutationSupport::refreshCache($context);
                AuditSupport::log(
                    $context,
                    'clear_tv_values',
                    'tv_value',
                    array(
                        'tv_id' => $tvId,
                        'tv_name' => $tvName,
                        'deleted' => $total,
                    )
                );
                return array(
                    'confirmed' => true,
                    'tv' => array('id' => $tvId, 'name' => $tvName),
                    'deleted' => $total,
                    'remaining' => 0,
                );
            }
        );
    }

    private function resolveTv($context, array $data)
    {
        $class = $context->platform()->className('tv');
        $id = isset($data['tv_id'])
            ? (int)$data['tv_id']
            : (isset($data['id']) ? (int)$data['id'] : 0);
        $name = isset($data['tv_name'])
            ? trim((string)$data['tv_name'])
            : (isset($data['name']) ? trim((string)$data['name']) : '');

        if ($id > 0) {
            $tv = $context->modx()->getObject($class, $id);
        } elseif ($name !== '') {
            $tv = $context->modx()->getObject(
                $class,
                array('name' => $name)
            );
        } else {
            throw new \ModxMCPClientException(
                'tv_id or tv_name is required.'
            );
        }
        if (!$tv) {
            throw new \ModxMCPClientException('TV not found.');
        }
        return $tv;
    }
}
