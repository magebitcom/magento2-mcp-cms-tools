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
use Magebit\Mcp\Model\Tool\Schema\Builder\ArrayBuilder;
use Magebit\Mcp\Model\Tool\Schema\Builder\BooleanBuilder;
use Magebit\Mcp\Model\Tool\Schema\Builder\IntegerBuilder;
use Magebit\Mcp\Model\Tool\Schema\Builder\StringBuilder;
use Magebit\Mcp\Model\Tool\Schema\Schema;
use Magebit\Mcp\Model\Tool\ToolResult;
use Magebit\Mcp\Model\Tool\WriteMode;
use Magento\Cms\Api\BlockRepositoryInterface;
use Magento\Cms\Api\Data\BlockInterfaceFactory;
use Magento\Cms\Model\Block as BlockModel;
use Magento\Framework\Exception\LocalizedException;

/**
 * `cms.block.create` MCP tool. `stores` defaults to `[0]` ("All Store Views").
 */
class BlockCreate implements ToolInterface, UnderlyingAclAwareInterface
{
    public const TOOL_NAME = 'cms.block.create';
    public const ACL_RESOURCE = 'Magebit_McpCmsTools::tool_cms_block_create';

    /**
     * @param BlockRepositoryInterface $blockRepository
     * @param BlockInterfaceFactory $blockFactory
     * @param BlockFieldApplier $fieldApplier
     */
    public function __construct(
        private readonly BlockRepositoryInterface $blockRepository,
        private readonly BlockInterfaceFactory $blockFactory,
        private readonly BlockFieldApplier $fieldApplier
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
        return 'Create CMS Block';
    }

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return 'Create a new CMS block. `identifier` and `title` are required. '
            . '`content` holds the block HTML. `stores` defaults to `[0]` '
            . '(All Store Views).';
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
            ->boolean('is_active', fn (BooleanBuilder $b) => $b)
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
        $block = $this->blockFactory->create();
        if (!$block instanceof BlockModel) {
            throw new LocalizedException(__(
                'Cannot create CMS block — expected %1, got %2. Store assignment '
                . 'is not exposed on the interface, so a custom preference '
                . 'breaks this tool.',
                BlockModel::class,
                get_class($block)
            ));
        }

        $this->fieldApplier->applyOptional($block, $arguments);

        $stores = $this->fieldApplier->extractStores($arguments) ?? [0];
        $block->setStores($stores);

        $saved = $this->blockRepository->save($block);

        $payload = [
            'block_id' => (int) $saved->getId(),
            'identifier' => (string) $saved->getIdentifier(),
            'title' => (string) $saved->getTitle(),
            'is_active' => (bool) $saved->isActive(),
            'stores' => $stores,
        ];

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new LocalizedException(__('Failed to encode created block as JSON.'));
        }

        return new ToolResult(
            content: [['type' => 'text', 'text' => $json]],
            auditSummary: [
                'block_id' => (int) $saved->getId(),
                'identifier' => (string) $saved->getIdentifier(),
                'is_active' => (bool) $saved->isActive(),
                'stores' => $stores,
            ]
        );
    }
}
