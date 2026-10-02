<?php
namespace ModxMcp\Tools;

class Ms2ProductOptionsGetTool implements ToolInterface
{
    public function name() { return 'ms2_get_product_options'; }
    public function group() { return 'minishop2'; }
    public function isMutation() { return false; }
    public function supports($context) { return $context && $context->modx() && $context->platform(); }

    public function execute($context, array $data)
    {
        $productId = MiniShop2Support::positiveInt($data, 'product_id');
        MiniShop2Support::assertProduct($context, $productId);
        MiniShop2Support::load($context);

        $modx = $context->modx();
        $query = $modx->newQuery('msProductOption');
        $query->leftJoin('msOption', 'Option', 'Option.key = msProductOption.key');
        $query->where(array('msProductOption.product_id' => $productId));
        $query->sortby('msProductOption.key', 'ASC');
        $query->select($modx->getSelectColumns('msProductOption', 'msProductOption'));
        $query->select(array(
            'caption' => 'Option.caption',
            'type' => 'Option.type',
            'measure_unit' => 'Option.measure_unit',
        ));

        $rows = array();
        if ($query->prepare() && $query->stmt->execute()) {
            $rows = $query->stmt->fetchAll(\PDO::FETCH_ASSOC);
        }
        return array('product_id' => $productId, 'options' => $rows);
    }
}
