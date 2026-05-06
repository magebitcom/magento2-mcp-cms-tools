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
use Magebit\Mcp\Api\UnderlyingAclAwareInterface;
use Magebit\Mcp\Model\Tool\Schema\Builder\IntegerBuilder;
use Magebit\Mcp\Model\Tool\Schema\Builder\StringBuilder;
use Magebit\Mcp\Model\Tool\Schema\Schema;
use Magebit\Mcp\Model\Tool\ToolResult;
use Magebit\Mcp\Model\Tool\WriteMode;
use Magebit\McpCmsTools\Api\EntityFinderInterface;
use Magento\Cms\Api\BlockRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;

/**
 * `cms.block.delete` MCP tool. Non-reversible — sets `confirmationRequired`.
 */
class BlockDelete implements ToolInterface, UnderlyingAclAwareInterface
{
    public const TOOL_NAME = 'cms.block.delete';
    public const ACL_RESOURCE = 'Magebit_McpCmsTools::tool_cms_block_delete';

    /**
     * @param EntityFinderInterface $entityFinder
     * @param BlockRepositoryInterface $blockRepository
     */
    public function __construct(
        private readonly EntityFinderInterface $entityFinder,
        private readonly BlockRepositoryInterface $blockRepository
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
        return 'Delete CMS Block';
    }

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return 'Permanently delete a CMS block. Identify by `id` or '
            . '`identifier` (+ optional `store_id`).';
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
        return 'Magento_Cms::block';
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
        $block = $this->entityFinder->blockFrom($arguments);
        $blockId = (int) $block->getId();
        $identifier = (string) $block->getIdentifier();

        $deleted = $this->blockRepository->delete($block);

        $payload = [
            'deleted' => (bool) $deleted,
            'block_id' => $blockId,
            'identifier' => $identifier,
        ];

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new LocalizedException(__('Failed to encode delete result as JSON.'));
        }

        return new ToolResult(
            content: [['type' => 'text', 'text' => $json]],
            auditSummary: [
                'block_id' => $blockId,
                'identifier' => $identifier,
                'deleted' => (bool) $deleted,
            ]
        );
    }
}
