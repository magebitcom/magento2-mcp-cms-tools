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
use Magebit\McpCmsTools\Api\EntityFinderInterface;
use Magento\Cms\Api\BlockRepositoryInterface;
use Magento\Cms\Model\Block as BlockModel;
use Magento\Framework\Exception\LocalizedException;

/**
 * `cms.block.update` MCP tool. PATCH-style: omitting `stores` preserves the
 * existing assignment rather than clearing it.
 */
class BlockUpdate implements ToolInterface, UnderlyingAclAwareInterface
{
    public const TOOL_NAME = 'cms.block.update';
    public const ACL_RESOURCE = 'Magebit_McpCmsTools::tool_cms_block_update';

    /**
     * @param EntityFinderInterface $entityFinder
     * @param BlockRepositoryInterface $blockRepository
     * @param BlockFieldApplier $fieldApplier
     */
    public function __construct(
        private readonly EntityFinderInterface $entityFinder,
        private readonly BlockRepositoryInterface $blockRepository,
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
        return 'Update CMS Block';
    }

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return 'Partial update of a CMS block. Identify with `id` or '
            . '`identifier` (+ `store_id` for identifier lookup). Only fields '
            . 'you provide are touched; omit `stores` to keep the existing '
            . 'assignment.';
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
            ->string('new_identifier', fn (StringBuilder $s) => $s
                ->minLength(1)
                ->description('Rename the identifier.')
            )
            ->string('title', fn (StringBuilder $s) => $s->minLength(1))
            ->string('content', fn (StringBuilder $s) => $s)
            ->boolean('is_active', fn (BooleanBuilder $b) => $b)
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

        $patch = $arguments;
        if (array_key_exists('new_identifier', $patch)) {
            $patch['identifier'] = $patch['new_identifier'];
            unset($patch['new_identifier']);
        } else {
            unset($patch['identifier']);
        }
        unset($patch['id'], $patch['store_id']);

        $applied = $this->fieldApplier->applyOptional($block, $patch);

        $stores = $this->fieldApplier->extractStores($patch);
        if ($stores !== null) {
            if (!$block instanceof BlockModel) {
                throw new LocalizedException(__(
                    'Cannot apply store assignment — block model is %1, expected %2.',
                    get_class($block),
                    BlockModel::class
                ));
            }
            $block->setStores($stores);
            $applied[] = 'stores';
        }

        $saved = $this->blockRepository->save($block);

        $payload = [
            'block_id' => (int) $saved->getId(),
            'identifier' => (string) $saved->getIdentifier(),
            'title' => (string) $saved->getTitle(),
            'is_active' => (bool) $saved->isActive(),
        ];

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new LocalizedException(__('Failed to encode updated block as JSON.'));
        }

        return new ToolResult(
            content: [['type' => 'text', 'text' => $json]],
            auditSummary: [
                'block_id' => (int) $saved->getId(),
                'identifier' => (string) $saved->getIdentifier(),
                'is_active' => (bool) $saved->isActive(),
                'fields_changed' => $applied,
            ]
        );
    }
}
