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

/**
 * `timestamps` slice: creation_time, update_time.
 */
class TimestampsResolver implements BlockFieldResolverInterface
{
    public const KEY = 'timestamps';

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
        return 90;
    }

    /**
     * @inheritDoc
     */
    public function resolve(BlockInterface $block, array $args): array
    {
        unset($args);
        return [
            'creation_time' => $block->getCreationTime() !== null
                ? (string) $block->getCreationTime()
                : null,
            'update_time' => $block->getUpdateTime() !== null
                ? (string) $block->getUpdateTime()
                : null,
        ];
    }
}
