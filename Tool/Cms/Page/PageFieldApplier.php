<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpCmsTools\Tool\Cms\Page;

use Magento\Cms\Api\Data\PageInterface;

/**
 * Optional-field applier shared by {@see PageCreate} and {@see PageUpdate}.
 * Injected as a DI collaborator so downstream modules can preference their
 * own implementation when adding proprietary page columns.
 */
class PageFieldApplier
{
    /**
     * Returns the list of keys actually applied — use for the audit
     * `fields_changed` summary so the log reflects what persisted, not what
     * the caller requested. Keys whose values fail the per-field type check
     * are silently skipped and excluded from the result.
     *
     * @param PageInterface $page
     * @param array $args
     * @phpstan-param array<string, mixed> $args
     * @return array<int, string>
     */
    public function applyOptional(PageInterface $page, array $args): array
    {
        $applied = [];
        if (array_key_exists('identifier', $args) && is_string($args['identifier'])) {
            $page->setIdentifier($args['identifier']);
            $applied[] = 'identifier';
        }
        if (array_key_exists('title', $args) && is_string($args['title'])) {
            $page->setTitle($args['title']);
            $applied[] = 'title';
        }
        if (array_key_exists('content', $args) && is_string($args['content'])) {
            $page->setContent($args['content']);
            $applied[] = 'content';
        }
        if (array_key_exists('content_heading', $args) && is_string($args['content_heading'])) {
            $page->setContentHeading($args['content_heading']);
            $applied[] = 'content_heading';
        }
        if (array_key_exists('meta_title', $args) && is_string($args['meta_title'])) {
            $page->setMetaTitle($args['meta_title']);
            $applied[] = 'meta_title';
        }
        if (array_key_exists('meta_keywords', $args) && is_string($args['meta_keywords'])) {
            $page->setMetaKeywords($args['meta_keywords']);
            $applied[] = 'meta_keywords';
        }
        if (array_key_exists('meta_description', $args) && is_string($args['meta_description'])) {
            $page->setMetaDescription($args['meta_description']);
            $applied[] = 'meta_description';
        }
        if (array_key_exists('page_layout', $args) && is_string($args['page_layout'])) {
            $page->setPageLayout($args['page_layout']);
            $applied[] = 'page_layout';
        }
        if (array_key_exists('layout_update_xml', $args) && is_string($args['layout_update_xml'])) {
            $page->setLayoutUpdateXml($args['layout_update_xml']);
            $applied[] = 'layout_update_xml';
        }
        if (array_key_exists('custom_theme', $args) && is_string($args['custom_theme'])) {
            $page->setCustomTheme($args['custom_theme']);
            $applied[] = 'custom_theme';
        }
        if (array_key_exists('custom_root_template', $args) && is_string($args['custom_root_template'])) {
            $page->setCustomRootTemplate($args['custom_root_template']);
            $applied[] = 'custom_root_template';
        }
        if (array_key_exists('custom_layout_update_xml', $args) && is_string($args['custom_layout_update_xml'])) {
            $page->setCustomLayoutUpdateXml($args['custom_layout_update_xml']);
            $applied[] = 'custom_layout_update_xml';
        }
        if (array_key_exists('custom_theme_from', $args) && is_string($args['custom_theme_from'])) {
            $page->setCustomThemeFrom($args['custom_theme_from']);
            $applied[] = 'custom_theme_from';
        }
        if (array_key_exists('custom_theme_to', $args) && is_string($args['custom_theme_to'])) {
            $page->setCustomThemeTo($args['custom_theme_to']);
            $applied[] = 'custom_theme_to';
        }
        if (array_key_exists('is_active', $args) && is_bool($args['is_active'])) {
            $page->setIsActive($args['is_active']);
            $applied[] = 'is_active';
        }
        if (array_key_exists('sort_order', $args) && is_numeric($args['sort_order'])) {
            $page->setSortOrder((string) (int) $args['sort_order']);
            $applied[] = 'sort_order';
        }
        return $applied;
    }

    /**
     * Returns null when the caller didn't supply `stores` — signals
     * "leave the assignment alone" on update.
     *
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
