<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpCmsTools\Model\Search;

use Magebit\Mcp\Model\Util\WebsiteStoreResolver;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SortOrder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;

/**
 * Shared filter / sort / paging plumbing for the CMS list search builders.
 * Subclasses bind entity-specific field names + filter translators.
 */
abstract class AbstractCmsSearchCriteriaBuilder
{
    public const MAX_PAGE_SIZE = 100;
    public const DEFAULT_PAGE_SIZE = 25;

    /**
     * @param SearchCriteriaBuilder $criteriaBuilder
     * @param SortOrderBuilder $sortBuilder
     * @param WebsiteStoreResolver $websiteStoreResolver
     */
    public function __construct(
        protected readonly SearchCriteriaBuilder $criteriaBuilder,
        protected readonly SortOrderBuilder $sortBuilder,
        protected readonly WebsiteStoreResolver $websiteStoreResolver
    ) {
    }

    /**
     * @param array $args
     * @phpstan-param array<string, mixed> $args
     * @return SearchCriteriaInterface
     * @throws LocalizedException
     */
    public function build(array $args): SearchCriteriaInterface
    {
        $filtersRaw = $args['filters'] ?? [];
        if (!is_array($filtersRaw)) {
            throw new LocalizedException(__('Filter payload must be an object.'));
        }

        foreach ($filtersRaw as $key => $value) {
            if (!is_string($key) || $key === '') {
                throw new LocalizedException(__('Filter keys must be non-empty strings.'));
            }
            $this->applyFilter($key, $value);
        }

        $this->applySort($args);
        $this->applyPaging($args);

        return $this->criteriaBuilder->create();
    }

    /**
     * @return array<int, string>
     */
    abstract protected function sortableFields(): array;

    /**
     * @return string
     */
    abstract protected function defaultSortField(): string;

    /**
     * @return string
     */
    abstract protected function identifierField(): string;

    /**
     * @return string
     */
    abstract protected function titleField(): string;

    /**
     * @return string
     */
    abstract protected function isActiveField(): string;

    /**
     * @return string
     */
    abstract protected function creationTimeField(): string;

    /**
     * @return string
     */
    abstract protected function updateTimeField(): string;

    /**
     * Returns true if some registered translator claimed the key.
     *
     * @param string $key
     * @param mixed $value
     * @return bool
     * @throws LocalizedException
     */
    abstract protected function delegateUnknownFilter(string $key, mixed $value): bool;

    /**
     * @param string $key
     * @return Phrase
     */
    abstract protected function unknownFilterMessage(string $key): Phrase;

    /**
     * @param string $key
     * @param mixed $value
     * @return void
     * @throws LocalizedException
     */
    private function applyFilter(string $key, mixed $value): void
    {
        if ($key === $this->identifierField()) {
            $this->addIdentifierFilter($value);
            return;
        }
        if ($key === $this->titleField()) {
            $this->addLikeFilter($this->titleField(), $value);
            return;
        }
        if ($key === $this->isActiveField()) {
            $this->addBooleanFilter($this->isActiveField(), $value);
            return;
        }
        if ($key === 'store_id') {
            $this->addEqualsOrIn('store_id', $value);
            return;
        }
        if ($key === 'website_id') {
            $storeIds = $this->websiteStoreResolver->resolveStoreIds($value);
            if ($storeIds === []) {
                $this->criteriaBuilder->addFilter('store_id', 0);
                return;
            }
            $this->criteriaBuilder->addFilter('store_id', $storeIds, 'in');
            return;
        }
        if ($key === 'created_at_from') {
            $this->addRangeBoundary($this->creationTimeField(), 'gteq', $value);
            return;
        }
        if ($key === 'created_at_to') {
            $this->addRangeBoundary($this->creationTimeField(), 'lteq', $value);
            return;
        }
        if ($key === 'update_time_from') {
            $this->addRangeBoundary($this->updateTimeField(), 'gteq', $value);
            return;
        }
        if ($key === 'update_time_to') {
            $this->addRangeBoundary($this->updateTimeField(), 'lteq', $value);
            return;
        }

        if ($this->delegateUnknownFilter($key, $value)) {
            return;
        }

        throw new LocalizedException($this->unknownFilterMessage($key));
    }

    /**
     * `*` in the value is translated to SQL `%` for `like` matching.
     *
     * @param mixed $value
     * @return void
     * @throws LocalizedException
     */
    private function addIdentifierFilter(mixed $value): void
    {
        $field = $this->identifierField();
        if (is_array($value)) {
            $this->addEqualsOrIn($field, $value);
            return;
        }
        if (!is_scalar($value) || (string) $value === '') {
            throw new LocalizedException(__('Filter "identifier" requires a non-empty value.'));
        }
        $str = (string) $value;
        if (str_contains($str, '*') || str_contains($str, '%')) {
            $this->criteriaBuilder->addFilter($field, str_replace('*', '%', $str), 'like');
            return;
        }
        $this->criteriaBuilder->addFilter($field, $str);
    }

    /**
     * @param string $field
     * @param mixed $value
     * @return void
     * @throws LocalizedException
     */
    private function addLikeFilter(string $field, mixed $value): void
    {
        if (!is_scalar($value) || (string) $value === '') {
            throw new LocalizedException(__('Filter "%1" requires a non-empty value.', $field));
        }
        $this->criteriaBuilder->addFilter($field, '%' . (string) $value . '%', 'like');
    }

    /**
     * @param string $field
     * @param mixed $value
     * @return void
     * @throws LocalizedException
     */
    private function addBooleanFilter(string $field, mixed $value): void
    {
        if (is_bool($value)) {
            $this->criteriaBuilder->addFilter($field, $value ? 1 : 0);
            return;
        }
        if (is_int($value) && ($value === 0 || $value === 1)) {
            $this->criteriaBuilder->addFilter($field, $value);
            return;
        }
        if (is_string($value)) {
            $lower = strtolower($value);
            if (in_array($lower, ['1', 'true', 'yes'], true)) {
                $this->criteriaBuilder->addFilter($field, 1);
                return;
            }
            if (in_array($lower, ['0', 'false', 'no'], true)) {
                $this->criteriaBuilder->addFilter($field, 0);
                return;
            }
        }
        throw new LocalizedException(__('Filter "%1" must be boolean.', $field));
    }

    /**
     * @param string $field
     * @param mixed $value
     * @return void
     * @throws LocalizedException
     */
    private function addEqualsOrIn(string $field, mixed $value): void
    {
        if (is_array($value)) {
            $list = array_values(array_filter(
                $value,
                fn($v): bool => is_scalar($v) && (string) $v !== ''
            ));
            if ($list === []) {
                return;
            }
            $this->criteriaBuilder->addFilter($field, $list, 'in');
            return;
        }
        if (!is_scalar($value) || (string) $value === '') {
            throw new LocalizedException(__('Filter "%1" requires a non-empty value.', $field));
        }
        $this->criteriaBuilder->addFilter($field, $value);
    }

    /**
     * @param string $field
     * @param string $condition
     * @param mixed $value
     * @return void
     * @throws LocalizedException
     */
    private function addRangeBoundary(string $field, string $condition, mixed $value): void
    {
        if (!is_scalar($value) || (string) $value === '') {
            throw new LocalizedException(__('Range boundary for "%1" must be scalar.', $field));
        }
        $this->criteriaBuilder->addFilter($field, (string) $value, $condition);
    }

    /**
     * Defaults to the entity's `update_time DESC` ("recently changed").
     *
     * @param array $args
     * @phpstan-param array<string, mixed> $args
     * @return void
     * @throws LocalizedException
     */
    private function applySort(array $args): void
    {
        $sortBy = $args['sort_by'] ?? $this->defaultSortField();
        if (!is_string($sortBy) || $sortBy === '') {
            throw new LocalizedException(__('"sort_by" must be a non-empty string.'));
        }
        if (!in_array($sortBy, $this->sortableFields(), true)) {
            throw new LocalizedException(__(
                '"sort_by" must be one of: %1.',
                implode(', ', $this->sortableFields())
            ));
        }

        $dirRaw = $args['sort_dir'] ?? 'desc';
        $dir = is_string($dirRaw) ? strtolower($dirRaw) : 'desc';
        if ($dir !== 'asc' && $dir !== 'desc') {
            throw new LocalizedException(__('"sort_dir" must be "asc" or "desc".'));
        }

        $this->sortBuilder->setField($sortBy);
        $this->sortBuilder->setDirection($dir === 'asc' ? SortOrder::SORT_ASC : SortOrder::SORT_DESC);
        $this->criteriaBuilder->addSortOrder($this->sortBuilder->create());
    }

    /**
     * @param array $args
     * @phpstan-param array<string, mixed> $args
     * @return void
     * @throws LocalizedException
     */
    private function applyPaging(array $args): void
    {
        $pageRaw = $args['page'] ?? 1;
        $page = is_numeric($pageRaw) ? max(1, (int) $pageRaw) : 1;

        $sizeRaw = $args['page_size'] ?? self::DEFAULT_PAGE_SIZE;
        if (!is_numeric($sizeRaw)) {
            throw new LocalizedException(__('"page_size" must be numeric.'));
        }
        $size = max(1, (int) $sizeRaw);
        if ($size > self::MAX_PAGE_SIZE) {
            $size = self::MAX_PAGE_SIZE;
        }

        $this->criteriaBuilder->setCurrentPage($page);
        $this->criteriaBuilder->setPageSize($size);
    }
}
