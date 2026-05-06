<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpCmsTools\Model\FieldResolver\Block;

use Magebit\McpCmsTools\Api\BlockFieldResolverInterface;
use Magento\Cms\Api\Data\BlockInterface;
use Magento\Cms\Model\Block as BlockModel;

/**
 * `stores` slice: store_ids[]. `[0]` = "All Store Views".
 *
 * The association isn't on BlockInterface, so we read it from the concrete
 * {@see BlockModel}.
 */
class StoresResolver implements BlockFieldResolverInterface
{
    public const KEY = 'stores';

    /**
     * @inheritDoc
     */
    public function getKey(): string
    {
        return self::KEY;
    }

    /**
     * @inheritDoc
     */
    public function getSortOrder(): int
    {
        return 60;
    }

    /**
     * @inheritDoc
     */
    public function resolve(BlockInterface $block, array $args): array
    {
        unset($args);
        $storeIds = [];
        if ($block instanceof BlockModel) {
            $raw = $block->getStores();
            if (is_array($raw)) {
                foreach ($raw as $storeId) {
                    if (is_numeric($storeId)) {
                        $storeIds[] = (int) $storeId;
                    }
                }
            }
        }
        return [
            'store_ids' => array_values(array_unique($storeIds)),
        ];
    }
}
