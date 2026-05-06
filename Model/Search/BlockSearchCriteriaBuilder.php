<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpCmsTools\Model\Search;

use Magebit\Mcp\Model\Util\WebsiteStoreResolver;
use Magebit\McpCmsTools\Api\BlockFilterTranslatorInterface;
use Magento\Cms\Api\Data\BlockInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Framework\Phrase;

/**
 * `cms.block.list` filter → `SearchCriteriaInterface` translator.
 */
class BlockSearchCriteriaBuilder extends AbstractCmsSearchCriteriaBuilder
{
    /** @var array<int, string> */
    public const SORTABLE_FIELDS = [
        BlockInterface::UPDATE_TIME,
        BlockInterface::CREATION_TIME,
        BlockInterface::BLOCK_ID,
        BlockInterface::IDENTIFIER,
        BlockInterface::TITLE,
    ];

    /**
     * @param SearchCriteriaBuilder $criteriaBuilder
     * @param SortOrderBuilder $sortBuilder
     * @param WebsiteStoreResolver $websiteStoreResolver
     * @param BlockFilterTranslatorInterface[] $filterTranslators
     */
    public function __construct(
        SearchCriteriaBuilder $criteriaBuilder,
        SortOrderBuilder $sortBuilder,
        WebsiteStoreResolver $websiteStoreResolver,
        private readonly array $filterTranslators = []
    ) {
        parent::__construct($criteriaBuilder, $sortBuilder, $websiteStoreResolver);
    }

    /**
     * @inheritDoc
     */
    protected function sortableFields(): array
    {
        return self::SORTABLE_FIELDS;
    }

    /**
     * @inheritDoc
     */
    protected function defaultSortField(): string
    {
        return BlockInterface::UPDATE_TIME;
    }

    /**
     * @inheritDoc
     */
    protected function identifierField(): string
    {
        return BlockInterface::IDENTIFIER;
    }

    /**
     * @inheritDoc
     */
    protected function titleField(): string
    {
        return BlockInterface::TITLE;
    }

    /**
     * @inheritDoc
     */
    protected function isActiveField(): string
    {
        return BlockInterface::IS_ACTIVE;
    }

    /**
     * @inheritDoc
     */
    protected function creationTimeField(): string
    {
        return BlockInterface::CREATION_TIME;
    }

    /**
     * @inheritDoc
     */
    protected function updateTimeField(): string
    {
        return BlockInterface::UPDATE_TIME;
    }

    /**
     * @inheritDoc
     */
    protected function delegateUnknownFilter(string $key, mixed $value): bool
    {
        foreach ($this->filterTranslators as $translator) {
            if ($translator->supports($key)) {
                $translator->translate($key, $value, $this->criteriaBuilder);
                return true;
            }
        }
        return false;
    }

    /**
     * @inheritDoc
     */
    protected function unknownFilterMessage(string $key): Phrase
    {
        return __('Unknown block filter: "%1".', $key);
    }
}
