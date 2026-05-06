<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpCmsTools\Model\FieldResolver\Page;

use Magebit\McpCmsTools\Api\PageFieldResolverInterface;
use Magento\Cms\Api\Data\PageInterface;

/**
 * `timestamps` slice: creation_time, update_time.
 */
class TimestampsResolver implements PageFieldResolverInterface
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
    public function resolve(PageInterface $page, array $args): array
    {
        unset($args);
        return [
            'creation_time' => $page->getCreationTime() !== null
                ? (string) $page->getCreationTime()
                : null,
            'update_time' => $page->getUpdateTime() !== null
                ? (string) $page->getUpdateTime()
                : null,
        ];
    }
}
