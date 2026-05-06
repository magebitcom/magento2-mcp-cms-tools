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
 * `content` slice: content_heading, content (HTML body).
 */
class ContentResolver implements PageFieldResolverInterface
{
    public const KEY = 'content';

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
        return 30;
    }

    /**
     * @inheritDoc
     */
    public function resolve(PageInterface $page, array $args): array
    {
        unset($args);
        return [
            'content_heading' => $page->getContentHeading() !== null
                ? (string) $page->getContentHeading()
                : null,
            'content' => $page->getContent() !== null
                ? (string) $page->getContent()
                : null,
        ];
    }
}
