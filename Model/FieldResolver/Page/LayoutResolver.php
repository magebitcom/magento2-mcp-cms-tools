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
 * `layout` slice: Page Design tab — page_layout, layout_update_xml, custom
 * theme overrides, custom-theme date range.
 */
class LayoutResolver implements PageFieldResolverInterface
{
    public const KEY = 'layout';

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
        return 50;
    }

    /**
     * @inheritDoc
     */
    public function resolve(PageInterface $page, array $args): array
    {
        unset($args);
        return [
            'page_layout' => $page->getPageLayout() !== null
                ? (string) $page->getPageLayout()
                : null,
            'layout_update_xml' => $page->getLayoutUpdateXml() !== null
                ? (string) $page->getLayoutUpdateXml()
                : null,
            'custom_theme' => $page->getCustomTheme() !== null
                ? (string) $page->getCustomTheme()
                : null,
            'custom_root_template' => $page->getCustomRootTemplate() !== null
                ? (string) $page->getCustomRootTemplate()
                : null,
            'custom_layout_update_xml' => $page->getCustomLayoutUpdateXml() !== null
                ? (string) $page->getCustomLayoutUpdateXml()
                : null,
            'custom_theme_from' => $page->getCustomThemeFrom() !== null
                ? (string) $page->getCustomThemeFrom()
                : null,
            'custom_theme_to' => $page->getCustomThemeTo() !== null
                ? (string) $page->getCustomThemeTo()
                : null,
        ];
    }
}
