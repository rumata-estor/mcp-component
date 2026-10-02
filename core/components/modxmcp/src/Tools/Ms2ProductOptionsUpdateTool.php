<?php
namespace ModxMcp\Tools;

class Ms2ProductOptionsUpdateTool implements ToolInterface
{
    public function name() { return 'ms2_update_product_options'; }
    public function group() { return 'minishop2'; }
    public function isMutation() { return true; }
    public function supports($context) { return $context && $context->modx(); }

    public function execute($context, array $data)
    {
        $productId = MiniShop2Support::positiveInt($data, 'product_id');
        MiniShop2Support::assertProduct($context, $productId);

        if (empty($data['options']) || !is_array($data['options'])) {
            throw new \ModxMCPClientException(
                'options payload must be a non-empty object.'
            );
        }

        MiniShop2Support::load($context);
        $changed = array();

        return ElementMutationSupport::transaction(
            $context,
            function () use ($context, $productId, $data, &$changed) {
                foreach ($data['options'] as $key => $value) {
                    $key = trim((string)$key);
                    if ($key === '') { continue; }

                    if (!$context->modx()->getObject(
                        'msOption',
                        array('key' => $key)
                    )) {
                        throw new \ModxMCPClientException(
                            'miniShop2 option not found: ' . $key . '.'
                        );
                    }

                    if ($value === null) {
                        $context->modx()->removeCollection(
                            'msProductOption',
                            array(
                                'product_id' => $productId,
                                'key' => $key,
                            )
                        );
                        $changed[$key] = null;
                        continue;
                    }

                    if (is_array($value)) {
                        $value = implode(
                            '||',
                            array_map('strval', $value)
                        );
                    } elseif (is_bool($value)) {
                        $value = $value ? '1' : '0';
                    } else {
                        $value = (string)$value;
                    }

                    $context->modx()->removeCollection(
                        'msProductOption',
                        array(
                            'product_id' => $productId,
                            'key' => $key,
                        )
                    );

                    $option = $context->modx()->newObject('msProductOption');
                    $option->set('product_id', $productId);
                    $option->set('key', $key);
                    $option->set('value', $value);
                    if (!$option->save()) {
                        throw new \ModxMCPClientException(
                            'Could not save product option: ' . $key . '.'
                        );
                    }
                    $changed[$key] = $value;
                }

                ElementMutationSupport::refreshCache($context);
                AuditSupport::log(
                    $context,
                    $this->name(),
                    'ms2_product_option',
                    array(
                        'product_id' => $productId,
                        'keys' => array_keys($changed),
                    )
                );

                return (new Ms2ProductOptionsGetTool())->execute(
                    $context,
                    array('product_id' => $productId)
                );
            }
        );
    }
}
