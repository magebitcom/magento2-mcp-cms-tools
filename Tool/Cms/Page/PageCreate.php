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
use Magento\Cms\Api\Data\PageInterface;
use Magento\Cms\Api\Data\PageInterfaceFactory;
use Magento\Cms\Api\PageRepositoryInterface;
use Magento\Cms\Model\Page as PageModel;
use Magento\Framework\Exception\LocalizedException;

/**
 * `cms.page.create` MCP tool. `stores` defaults to `[0]` ("All Store Views")
 * to match the admin-UI default for new pages.
 */
class PageCreate implements ToolInterface, UnderlyingAclAwareInterface
{
    public const TOOL_NAME = 'cms.page.create';
    public const ACL_RESOURCE = 'Magebit_McpCmsTools::tool_cms_page_create';

    /**
     * @param PageRepositoryInterface $pageRepository
     * @param PageInterfaceFactory $pageFactory
     * @param PageFieldApplier $fieldApplier
     */
    public function __construct(
        private readonly PageRepositoryInterface $pageRepository,
        private readonly PageInterfaceFactory $pageFactory,
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
        return 'Create CMS Page';
    }

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return 'Create a new CMS page. `identifier` (URL key) and `title` are '
            . 'required. `content` holds the body HTML. `stores` is an array '
            . 'of store view ids the page should be assigned to (defaults to '
            . '`[0]` = "All Store Views").';
    }

    /**
     * @inheritDoc
     */
    public function getInputSchema(): array
    {
        return Schema::object()
            ->string('identifier', fn (StringBuilder $s) => $s->minLength(1)->required())
            ->string('title', fn (StringBuilder $s) => $s->minLength(1)->required())
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
                ->description('Store view ids. `[0]` = All Store Views.')
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
        $page = $this->pageFactory->create();
        if (!$page instanceof PageModel) {
            throw new LocalizedException(__(
                'Cannot create CMS page — expected %1, got %2. Store assignment '
                . 'is not exposed on the interface, so a custom preference '
                . 'breaks this tool.',
                PageModel::class,
                get_class($page)
            ));
        }

        $this->fieldApplier->applyOptional($page, $arguments);

        $stores = $this->fieldApplier->extractStores($arguments) ?? [0];
        $page->setStores($stores);

        $saved = $this->pageRepository->save($page);

        $payload = [
            'page_id' => (int) $saved->getId(),
            'identifier' => (string) $saved->getIdentifier(),
            'title' => (string) $saved->getTitle(),
            'is_active' => (bool) $saved->isActive(),
            'stores' => $stores,
        ];

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new LocalizedException(__('Failed to encode created page as JSON.'));
        }

        return new ToolResult(
            content: [['type' => 'text', 'text' => $json]],
            auditSummary: [
                'page_id' => (int) $saved->getId(),
                'identifier' => (string) $saved->getIdentifier(),
                'is_active' => (bool) $saved->isActive(),
                'stores' => $stores,
            ]
        );
    }
}
