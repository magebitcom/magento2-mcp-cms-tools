<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpCmsTools\Model\Search;

use Magebit\Mcp\Model\Util\WebsiteStoreResolver;
use Magebit\McpCmsTools\Api\PageFilterTranslatorInterface;
use Magento\Cms\Api\Data\PageInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Framework\Phrase;

/**
 * `cms.page.list` filter → `SearchCriteriaInterface` translator.
 */
class PageSearchCriteriaBuilder extends AbstractCmsSearchCriteriaBuilder
{
    /** @var array<int, string> */
    public const SORTABLE_FIELDS = [
        PageInterface::UPDATE_TIME,
        PageInterface::CREATION_TIME,
        PageInterface::PAGE_ID,
        PageInterface::IDENTIFIER,
        PageInterface::TITLE,
        PageInterface::SORT_ORDER,
    ];

    /**
     * @param SearchCriteriaBuilder $criteriaBuilder
     * @param SortOrderBuilder $sortBuilder
     * @param WebsiteStoreResolver $websiteStoreResolver
     * @param PageFilterTranslatorInterface[] $filterTranslators
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
        return PageInterface::UPDATE_TIME;
    }

    /**
     * @inheritDoc
     */
    protected function identifierField(): string
    {
        return PageInterface::IDENTIFIER;
    }

    /**
     * @inheritDoc
     */
    protected function titleField(): string
    {
        return PageInterface::TITLE;
    }

    /**
     * @inheritDoc
     */
    protected function isActiveField(): string
    {
        return PageInterface::IS_ACTIVE;
    }

    /**
     * @inheritDoc
     */
    protected function creationTimeField(): string
    {
        return PageInterface::CREATION_TIME;
    }

    /**
     * @inheritDoc
     */
    protected function updateTimeField(): string
    {
        return PageInterface::UPDATE_TIME;
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
        return __('Unknown page filter: "%1".', $key);
    }
}
