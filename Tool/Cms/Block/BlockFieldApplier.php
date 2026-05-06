<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpCmsTools\Tool\Cms\Block;

use Magento\Cms\Api\Data\BlockInterface;

/**
 * Block-side sibling of {@see \Magebit\McpCmsTools\Tool\Cms\Page\PageFieldApplier},
 * scoped to BlockInterface (identifier / title / content / is_active + stores).
 */
class BlockFieldApplier
{
    /**
     * Returns the list of keys actually applied — use for the audit
     * `fields_changed` summary so the log reflects what persisted, not what
     * the caller requested.
     *
     * @param BlockInterface $block
     * @param array $args
     * @phpstan-param array<string, mixed> $args
     * @return array<int, string>
     */
    public function applyOptional(BlockInterface $block, array $args): array
    {
        $applied = [];
        if (array_key_exists('identifier', $args) && is_string($args['identifier'])) {
            $block->setIdentifier($args['identifier']);
            $applied[] = 'identifier';
        }
        if (array_key_exists('title', $args) && is_string($args['title'])) {
            $block->setTitle($args['title']);
            $applied[] = 'title';
        }
        if (array_key_exists('content', $args) && is_string($args['content'])) {
            $block->setContent($args['content']);
            $applied[] = 'content';
        }
        if (array_key_exists('is_active', $args) && is_bool($args['is_active'])) {
            $block->setIsActive($args['is_active']);
            $applied[] = 'is_active';
        }
        return $applied;
    }

    /**
     * @param array $args
     * @phpstan-param array<string, mixed> $args
     * @return array<int, int>|null
     */
    public function extractStores(array $args): ?array
    {
        if (!array_key_exists('stores', $args)) {
            return null;
        }
        $raw = $args['stores'];
        if (!is_array($raw)) {
            return null;
        }
        $stores = [];
        foreach ($raw as $id) {
            if (is_numeric($id) && (int) $id >= 0) {
                $stores[] = (int) $id;
            }
        }
        return array_values(array_unique($stores));
    }
}
