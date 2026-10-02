<?php
namespace ModxMcp\Tools;

class MiniShop2MutationSupport
{
    public static function optionPayload(array $data, $requireId)
    {
        $payload = array();
        if ($requireId) {
            $payload['id'] = MiniShop2Support::positiveInt($data, 'id');
        }

        foreach (
            array('key', 'caption', 'description', 'measure_unit', 'category', 'type')
            as $field
        ) {
            if (array_key_exists($field, $data)) {
                $payload[$field] = $data[$field];
            }
        }

        if (!$requireId) {
            foreach (array('key', 'caption', 'type') as $field) {
                if (!isset($payload[$field]) || $payload[$field] === '') {
                    throw new \ModxMCPClientException(
                        $field . ' is required for miniShop2 option creation.'
                    );
                }
            }
            if (!array_key_exists('category', $payload)) {
                $payload['category'] = 0;
            }
        }

        if (array_key_exists('properties', $data)) {
            $payload['properties'] = is_array($data['properties'])
                ? json_encode($data['properties'], JSON_UNESCAPED_UNICODE)
                : $data['properties'];
        }

        if (!empty($data['category_ids']) && is_array($data['category_ids'])) {
            $categories = array();
            foreach ($data['category_ids'] as $categoryId) {
                $categoryId = (int)$categoryId;
                if ($categoryId > 0) {
                    $categories[$categoryId] = true;
                }
            }
            if (!empty($categories)) {
                $payload['categories'] = json_encode($categories);
            }
        }
        return $payload;
    }

    public static function category($context, $action, array $data)
    {
        $props = MiniShop2Support::cleanPayload($data);
        $props['class_key'] = 'msCategory';

        if ($action === 'create') {
            if (!isset($props['context_key'])) { $props['context_key'] = 'web'; }
            if (!isset($props['parent'])) { $props['parent'] = 0; }
            if (!isset($props['published'])) { $props['published'] = 1; }
        }

        $response = $context->platform()->runProcessor(
            $context->modx(),
            'resource/' . $action,
            $props
        );
        if (!$response || $response->isError()) {
            throw new \ModxMCPClientException(
                $response
                    ? ProcessorSupport::error($response)
                    : 'ms2 category: no response.'
            );
        }

        ElementMutationSupport::refreshCache($context);
        AuditSupport::log(
            $context,
            $action === 'create' ? 'ms2_create_category' : 'ms2_update_category',
            'ms2',
            array_intersect_key(
                $props,
                array_flip(array('id', 'pagetitle', 'parent'))
            )
        );
        return ProcessorSupport::normalize($response);
    }

    public static function auditProcessor(
        $context,
        $action,
        array $payload,
        array $keys,
        $refresh = false
    ) {
        if ($refresh) { ElementMutationSupport::refreshCache($context); }
        AuditSupport::log(
            $context,
            $action,
            'ms2',
            array_intersect_key($payload, array_flip($keys))
        );
    }
}
