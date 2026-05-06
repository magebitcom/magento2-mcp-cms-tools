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
use Magebit\Mcp\Model\Tool\Schema\Builder\ArrayBuilder;
use Magebit\Mcp\Model\Tool\Schema\Builder\IntegerBuilder;
use Magebit\Mcp\Model\Tool\Schema\Builder\StringBuilder;
use Magebit\Mcp\Model\Tool\Schema\Schema;
use Magebit\Mcp\Model\Tool\ToolResult;
use Magebit\Mcp\Model\Tool\WriteMode;
use Magebit\Mcp\Model\Util\ResolverPipeline;
use Magebit\McpCmsTools\Api\BlockFieldResolverInterface;
use Magebit\McpCmsTools\Api\EntityFinderInterface;
use Magento\Framework\Exception\LocalizedException;

/**
 * `cms.block.get` MCP tool. Response shape is composed from the
 * DI-injected `$fieldResolvers` array.
 */
class BlockGet implements ToolInterface
{
    public const TOOL_NAME = 'cms.block.get';
    public const ACL_RESOURCE = 'Magebit_McpCmsTools::tool_cms_block_get';

    /**
     * @param EntityFinderInterface $entityFinder
     * @param ResolverPipeline $pipeline
     * @param BlockFieldResolverInterface[] $fieldResolvers
     */
    public function __construct(
        private readonly EntityFinderInterface $entityFinder,
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
        return 'Get CMS Block';
    }

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return 'Fetch a single CMS block by numeric id or admin identifier. '
            . 'Use `fields` / `exclude` to narrow the payload.';
    }

    /**
     * @inheritDoc
     */
    public function getInputSchema(): array
    {
        return Schema::object()
            ->integer('id', fn (IntegerBuilder $i) => $i
                ->minimum(1)
                ->description('Numeric cms_block.block_id.')
            )
            ->string('identifier', fn (StringBuilder $s) => $s
                ->minLength(1)
                ->description('CMS block identifier, e.g. "footer_links_block".')
            )
            ->integer('store_id', fn (IntegerBuilder $i) => $i
                ->minimum(0)
                ->description('Store scope for identifier lookup. '
                    . 'Defaults to the current store.')
            )
            ->array('fields', fn (ArrayBuilder $a) => $a
                ->ofStrings()
                ->description('Whitelist of resolver keys to include.')
            )
            ->array('exclude', fn (ArrayBuilder $a) => $a
                ->ofStrings()
                ->description('Resolver keys to drop.')
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
        $block = $this->entityFinder->blockFrom($arguments);

        $response = [];
        foreach ($this->pipeline->plan($this->fieldResolvers, $arguments) as $resolver) {
            $response[$resolver->getKey()] = $resolver->resolve($block, $arguments);
        }

        $json = json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new LocalizedException(__('Failed to encode block payload as JSON.'));
        }

        return new ToolResult(
            content: [['type' => 'text', 'text' => $json]],
            auditSummary: [
                'block_id' => (int) $block->getId(),
                'identifier' => (string) $block->getIdentifier(),
                'is_active' => (bool) $block->isActive(),
            ]
        );
    }
}
