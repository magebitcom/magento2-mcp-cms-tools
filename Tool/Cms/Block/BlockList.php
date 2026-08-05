<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpCmsTools\Tool\Cms\Block;

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
use Magebit\McpCmsTools\Api\BlockFieldResolverInterface;
use Magebit\McpCmsTools\Model\Search\BlockSearchCriteriaBuilder;
use Magento\Cms\Api\BlockRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;

/**
 * `cms.block.list` MCP tool.
 */
class BlockList implements ToolInterface
{
    public const TOOL_NAME = 'cms.block.list';
    public const ACL_RESOURCE = 'Magebit_McpCmsTools::tool_cms_block_list';

    /**
     * @param BlockRepositoryInterface $blockRepository
     * @param BlockSearchCriteriaBuilder $searchBuilder
     * @param ResolverPipeline $pipeline
     * @param BlockFieldResolverInterface[] $fieldResolvers
     */
    public function __construct(
        private readonly BlockRepositoryInterface $blockRepository,
        private readonly BlockSearchCriteriaBuilder $searchBuilder,
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
        return 'List CMS Blocks';
    }

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return 'Search CMS blocks with optional filters (identifier, title, '
            . 'is_active, store_id, created_at range, update_time range) and '
            . 'paging. Each row is the same shape as `cms.block.get`.';
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
                    'identifier' => ['type' => ['string', 'array'], 'description' => 'Block id string: exact, `*glob*` wildcard, or array ⇒ IN.'],
                    'title' => ['type' => 'string', 'description' => 'Substring match on block title.'],
                    'is_active' => ['type' => 'boolean', 'description' => 'Enabled (true) or disabled (false).'],
                    'store_id' => ['type' => ['integer', 'array'], 'description' => 'Store view id(s).'],
                    'website_id' => ['type' => ['integer', 'array'], 'description' => 'Website id(s), expanded to store-view ids.'],
                    'created_at_from' => ['type' => 'string', 'description' => 'Created ISO date/datetime lower bound.'],
                    'created_at_to' => ['type' => 'string', 'description' => 'Created ISO date/datetime upper bound.'],
                    'update_time_from' => ['type' => 'string', 'description' => 'Updated ISO date/datetime lower bound.'],
                    'update_time_to' => ['type' => 'string', 'description' => 'Updated ISO date/datetime upper bound.'],
                ]
            ))
            ->with(Sort::fields(BlockSearchCriteriaBuilder::SORTABLE_FIELDS, 'update_time'))
            ->with(Pagination::maxPageSize(BlockSearchCriteriaBuilder::MAX_PAGE_SIZE))
            ->with(FieldSelection::default())
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
        $result = $this->blockRepository->getList($criteria);

        $plan = $this->pipeline->plan($this->fieldResolvers, $arguments);

        $rows = [];
        foreach ($result->getItems() as $block) {
            $row = [];
            foreach ($plan as $resolver) {
                $row[$resolver->getKey()] = $resolver->resolve($block, $arguments);
            }
            $rows[] = $row;
        }

        $payload = [
            'items' => $rows,
            'total_count' => (int) $result->getTotalCount(),
            'page_size' => $criteria->getPageSize() ?? BlockSearchCriteriaBuilder::DEFAULT_PAGE_SIZE,
            'current_page' => $criteria->getCurrentPage() ?? 1,
        ];

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new LocalizedException(__('Failed to encode block list as JSON.'));
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
