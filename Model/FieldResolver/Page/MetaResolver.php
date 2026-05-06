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
 * `meta` slice: meta_title, meta_keywords, meta_description.
 */
class MetaResolver implements PageFieldResolverInterface
{
    public const KEY = 'meta';

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
        return 40;
    }

    /**
     * @inheritDoc
     */
    public function resolve(PageInterface $page, array $args): array
    {
        unset($args);
        return [
            'meta_title' => $page->getMetaTitle() !== null
                ? (string) $page->getMetaTitle()
                : null,
            'meta_keywords' => $page->getMetaKeywords() !== null
                ? (string) $page->getMetaKeywords()
                : null,
            'meta_description' => $page->getMetaDescription() !== null
                ? (string) $page->getMetaDescription()
                : null,
        ];
    }
}
