<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpCmsTools\Tool\Cms\Page;

use Magebit\Mcp\Api\ToolInterface;
use Magebit\Mcp\Api\ToolResultInterface;
use Magebit\Mcp\Api\UnderlyingAclAwareInterface;
use Magebit\Mcp\Model\Tool\Schema\Builder\ArrayBuilder;
use Magebit\Mcp\Model\Tool\Schema\Builder\BooleanBuilder;
use Magebit\Mcp\Model\Tool\Schema\Builder\IntegerBuilder;
use Magebit\Mcp\Model\Tool\Schema\Builder\StringBuilder;
use Magebit\Mcp\Model\Tool\Schema\Schema;
use Magebit\Mcp\Model\Tool\ToolResult;
use Magebit\Mcp\Model\Tool\WriteMode;
use Magebit\McpCmsTools\Api\EntityFinderInterface;
use Magento\Cms\Api\PageRepositoryInterface;
use Magento\Cms\Model\Page as PageModel;
use Magento\Framework\Exception\LocalizedException;

/**
 * `cms.page.update` MCP tool. PATCH-style: only fields present in the
 * payload are touched, so omitting `stores` preserves the existing
 * assignment rather than clearing it.
 */
class PageUpdate implements ToolInterface, UnderlyingAclAwareInterface
{
    public const TOOL_NAME = 'cms.page.update';
    public const ACL_RESOURCE = 'Magebit_McpCmsTools::tool_cms_page_update';

    /**
     * @param EntityFinderInterface $entityFinder
     * @param PageRepositoryInterface $pageRepository
     * @param PageFieldApplier $fieldApplier
     */
    public function __construct(
        private readonly EntityFinderInterface $entityFinder,
        private readonly PageRepositoryInterface $pageRepository,
        private readonly PageFieldApplier $fieldApplier
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return self::TOOL_NAME;
    }

    /**
     * @inheritDoc
     */
    public function getTitle(): string
    {
        return 'Update CMS Page';
    }

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return 'Partial update of a CMS page. Identify the page with either '
            . '`id` or `identifier` (+ optional `store_id` for identifier '
            . 'lookup). Only fields you provide are touched; omit `stores` '
            . 'to keep the existing assignment.';
    }

    /**
     * @inheritDoc
     */
    public function getInputSchema(): array
    {
        return Schema::object()
            ->integer('id', fn (IntegerBuilder $i) => $i->minimum(1))
            ->string('identifier', fn (StringBuilder $s) => $s->minLength(1))
            ->integer('store_id', fn (IntegerBuilder $i) => $i->minimum(0))
            ->string('title', fn (StringBuilder $s) => $s->minLength(1))
            ->string('new_identifier', fn (StringBuilder $s) => $s
                ->minLength(1)
                ->description('Rename the identifier. Use with care — '
                    . 'URLs pointing to the old identifier will 404.')
            )
            ->string('content', fn (StringBuilder $s) => $s)
            ->string('content_heading', fn (StringBuilder $s) => $s)
            ->string('meta_title', fn (StringBuilder $s) => $s)
            ->string('meta_keywords', fn (StringBuilder $s) => $s)
            ->string('meta_description', fn (StringBuilder $s) => $s)
            ->string('page_layout', fn (StringBuilder $s) => $s)
            ->string('layout_update_xml', fn (StringBuilder $s) => $s)
            ->string('custom_theme', fn (StringBuilder $s) => $s)
            ->string('custom_root_template', fn (StringBuilder $s) => $s)
            ->string('custom_layout_update_xml', fn (StringBuilder $s) => $s)
            ->string('custom_theme_from', fn (StringBuilder $s) => $s)
            ->string('custom_theme_to', fn (StringBuilder $s) => $s)
            ->boolean('is_active', fn (BooleanBuilder $b) => $b)
            ->integer('sort_order', fn (IntegerBuilder $i) => $i->minimum(0))
            ->array('stores', fn (ArrayBuilder $a) => $a
                ->ofIntegers(fn (IntegerBuilder $i) => $i->minimum(0))
            )
            ->toArray();
    }

    /**
     * @inheritDoc
     */
    public function getAclResource(): string
    {
        return self::ACL_RESOURCE;
    }

    /**
     * @inheritDoc
     */
    public function getUnderlyingAclResource(): ?string
    {
        return 'Magento_Cms::save';
    }

    /**
     * @inheritDoc
     */
    public function getWriteMode(): WriteMode
    {
        return WriteMode::WRITE;
    }

    /**
     * @inheritDoc
     */
    public function getConfirmationRequired(): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function execute(array $arguments): ToolResultInterface
    {
        $page = $this->entityFinder->pageFrom($arguments);

        $patch = $arguments;
        if (array_key_exists('new_identifier', $patch)) {
            $patch['identifier'] = $patch['new_identifier'];
            unset($patch['new_identifier']);
        } else {
            unset($patch['identifier']);
        }
        unset($patch['id'], $patch['store_id']);

        $applied = $this->fieldApplier->applyOptional($page, $patch);

        $stores = $this->fieldApplier->extractStores($patch);
        if ($stores !== null) {
            if (!$page instanceof PageModel) {
                throw new LocalizedException(__(
                    'Cannot apply store assignment — page model is %1, expected %2.',
                    get_class($page),
                    PageModel::class
                ));
            }
            $page->setStores($stores);
            $applied[] = 'stores';
        }

        $saved = $this->pageRepository->save($page);

        $payload = [
            'page_id' => (int) $saved->getId(),
            'identifier' => (string) $saved->getIdentifier(),
            'title' => (string) $saved->getTitle(),
            'is_active' => (bool) $saved->isActive(),
        ];

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new LocalizedException(__('Failed to encode updated page as JSON.'));
        }

        return new ToolResult(
            content: [['type' => 'text', 'text' => $json]],
            auditSummary: [
                'page_id' => (int) $saved->getId(),
                'identifier' => (string) $saved->getIdentifier(),
                'is_active' => (bool) $saved->isActive(),
                'fields_changed' => $applied,
            ]
        );
    }
}
