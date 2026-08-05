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
use Magebit\Mcp\Model\Tool\Schema\Preset\FieldSelection;
use Magebit\Mcp\Model\Tool\Schema\Preset\Filters;
use Magebit\Mcp\Model\Tool\Schema\Preset\Pagination;
use Magebit\Mcp\Model\Tool\Schema\Preset\Sort;
use Magebit\Mcp\Model\Tool\Schema\Schema;
use Magebit\Mcp\Model\Tool\ToolResult;
use Magebit\Mcp\Model\Tool\WriteMode;
use Magebit\Mcp\Model\Util\ResolverPipeline;
use Magebit\McpCmsTools\Api\PageFieldResolverInterface;
use Magebit\McpCmsTools\Model\Search\PageSearchCriteriaBuilder;
use Magento\Cms\Api\PageRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;

/**
 * `cms.page.list` MCP tool. Rows are composed by the same resolver pipeline
 * as `cms.page.get`, so 3rd-party resolvers surface in both.
 */
class PageList implements ToolInterface
{
    public const TOOL_NAME = 'cms.page.list';
    public const ACL_RESOURCE = 'Magebit_McpCmsTools::tool_cms_page_list';

    /**
     * @param PageRepositoryInterface $pageRepository
     * @param PageSearchCriteriaBuilder $searchBuilder
     * @param ResolverPipeline $pipeline
     * @param PageFieldResolverInterface[] $fieldResolvers
     */
    public function __construct(
        private readonly PageRepositoryInterface $pageRepository,
        private readonly PageSearchCriteriaBuilder $searchBuilder,
        private readonly ResolverPipeline $pipeline,
        private readonly array $fieldResolvers = []
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
        return 'List CMS Pages';
    }

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return 'Search CMS pages with optional filters (identifier, title, '
            . 'is_active, store_id, created_at range, update_time range) and '
            . 'paging. Each row is the same shape as `cms.page.get`; use '
            . '`fields`/`exclude` to narrow.';
    }

    /**
     * @inheritDoc
     */
    public function getInputSchema(): array
    {
        return Schema::object()
            ->with(Filters::describing(
                'Filter clauses. Scalar or array values (array ⇒ IN); '
                . '`website_id` is expanded to the matching store-view ids.',
                [
                    'identifier' => ['type' => ['string', 'array'], 'description' => 'URL key: exact, `*glob*` wildcard, or array ⇒ IN.'],
                    'title' => ['type' => 'string', 'description' => 'Substring match on page title.'],
                    'is_active' => ['type' => 'boolean', 'description' => 'Enabled (true) or disabled (false).'],
                    'store_id' => ['type' => ['integer', 'array'], 'description' => 'Store view id(s).'],
                    'website_id' => ['type' => ['integer', 'array'], 'description' => 'Website id(s), expanded to store-view ids.'],
                    'created_at_from' => ['type' => 'string', 'description' => 'Created ISO date/datetime lower bound.'],
                    'created_at_to' => ['type' => 'string', 'description' => 'Created ISO date/datetime upper bound.'],
                    'update_time_from' => ['type' => 'string', 'description' => 'Updated ISO date/datetime lower bound.'],
                    'update_time_to' => ['type' => 'string', 'description' => 'Updated ISO date/datetime upper bound.'],
                ]
            ))
            ->with(Sort::fields(PageSearchCriteriaBuilder::SORTABLE_FIELDS, 'update_time'))
            ->with(Pagination::maxPageSize(PageSearchCriteriaBuilder::MAX_PAGE_SIZE))
            ->with(FieldSelection::describing(
                'Whitelist of resolver keys per row.',
                'Resolver keys to drop from each row. '
                . 'Exclude `content` on large listings.'
            ))
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
    public function getWriteMode(): WriteMode
    {
        return WriteMode::READ;
    }

    /**
     * @inheritDoc
     */
    public function getConfirmationRequired(): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function execute(array $arguments): ToolResultInterface
    {
        $criteria = $this->searchBuilder->build($arguments);
        $result = $this->pageRepository->getList($criteria);

        $plan = $this->pipeline->plan($this->fieldResolvers, $arguments);

        $rows = [];
        foreach ($result->getItems() as $page) {
            $row = [];
            foreach ($plan as $resolver) {
                $row[$resolver->getKey()] = $resolver->resolve($page, $arguments);
            }
            $rows[] = $row;
        }

        $payload = [
            'items' => $rows,
            'total_count' => (int) $result->getTotalCount(),
            'page_size' => $criteria->getPageSize() ?? PageSearchCriteriaBuilder::DEFAULT_PAGE_SIZE,
            'current_page' => $criteria->getCurrentPage() ?? 1,
        ];

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new LocalizedException(__('Failed to encode page list as JSON.'));
        }

        return new ToolResult(
            content: [['type' => 'text', 'text' => $json]],
            auditSummary: [
                'row_count' => count($rows),
                'total_count' => (int) $result->getTotalCount(),
                'page' => $criteria->getCurrentPage(),
                'page_size' => $criteria->getPageSize(),
            ]
        );
    }
}
