<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpCmsTools\Model;

use Magebit\McpCmsTools\Api\EntityFinderInterface;
use Magento\Cms\Api\BlockRepositoryInterface;
use Magento\Cms\Api\Data\BlockInterface;
use Magento\Cms\Api\Data\PageInterface;
use Magento\Cms\Api\GetBlockByIdentifierInterface;
use Magento\Cms\Api\GetPageByIdentifierInterface;
use Magento\Cms\Api\PageRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Default {@see EntityFinderInterface} implementation.
 */
class EntityFinder implements EntityFinderInterface
{
    /**
     * @param PageRepositoryInterface $pageRepository
     * @param BlockRepositoryInterface $blockRepository
     * @param GetPageByIdentifierInterface $getPageByIdentifier
     * @param GetBlockByIdentifierInterface $getBlockByIdentifier
     */
    public function __construct(
        private readonly PageRepositoryInterface $pageRepository,
        private readonly BlockRepositoryInterface $blockRepository,
        private readonly GetPageByIdentifierInterface $getPageByIdentifier,
        private readonly GetBlockByIdentifierInterface $getBlockByIdentifier
    ) {
    }

    /**
     * @inheritDoc
     */
    public function pageFrom(array $args): PageInterface
    {
        $id = $this->pickNumeric($args, ['id', 'page_id']);
        $identifier = $this->pickString($args, ['identifier']);
        $this->requireOneOf($id, $identifier, 'id/page_id', 'identifier');

        if ($id !== null) {
            try {
                return $this->pageRepository->getById($id);
            } catch (NoSuchEntityException $e) {
                throw new LocalizedException(__('CMS page %1 not found.', $id), $e);
            }
        }

        $storeId = $this->resolveStoreId($args);
        try {
            return $this->getPageByIdentifier->execute((string) $identifier, $storeId);
        } catch (NoSuchEntityException $e) {
            throw new LocalizedException(
                __('CMS page with identifier "%1" not found in store %2.', (string) $identifier, $storeId),
                $e
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function blockFrom(array $args): BlockInterface
    {
        $id = $this->pickNumeric($args, ['id', 'block_id']);
        $identifier = $this->pickString($args, ['identifier']);
        $this->requireOneOf($id, $identifier, 'id/block_id', 'identifier');

        if ($id !== null) {
            try {
                return $this->blockRepository->getById((string) $id);
            } catch (NoSuchEntityException $e) {
                throw new LocalizedException(__('CMS block %1 not found.', $id), $e);
            }
        }

        $storeId = $this->resolveStoreId($args);
        try {
            return $this->getBlockByIdentifier->execute((string) $identifier, $storeId);
        } catch (NoSuchEntityException $e) {
            throw new LocalizedException(
                __('CMS block with identifier "%1" not found in store %2.', (string) $identifier, $storeId),
                $e
            );
        }
    }

    /**
     * Defaulting to `0` here (rather than the current store) avoids silent
     * 404s on identifier-only lookups against pages assigned to "All Store
     * Views" — the admin-UI default for new CMS pages/blocks.
     *
     * @param array $args
     * @phpstan-param array<string, mixed> $args
     * @return int
     */
    private function resolveStoreId(array $args): int
    {
        if (array_key_exists('store_id', $args)) {
            $raw = $args['store_id'];
            if (is_int($raw) && $raw >= 0) {
                return $raw;
            }
            if (is_string($raw) && ctype_digit($raw)) {
                return (int) $raw;
            }
        }
        return 0;
    }

    /**
     * @param array $args
     * @phpstan-param array<string, mixed> $args
     * @param array $candidates
     * @phpstan-param array<int, string> $candidates
     * @return int|null
     */
    private function pickNumeric(array $args, array $candidates): ?int
    {
        foreach ($candidates as $key) {
            if (!array_key_exists($key, $args)) {
                continue;
            }
            $value = $args[$key];
            if (is_int($value) && $value > 0) {
                return $value;
            }
            if (is_string($value) && ctype_digit($value) && (int) $value > 0) {
                return (int) $value;
            }
        }
        return null;
    }

    /**
     * @param array $args
     * @phpstan-param array<string, mixed> $args
     * @param array $candidates
     * @phpstan-param array<int, string> $candidates
     * @return string|null
     */
    private function pickString(array $args, array $candidates): ?string
    {
        foreach ($candidates as $key) {
            if (!array_key_exists($key, $args)) {
                continue;
            }
            $value = $args[$key];
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }
        return null;
    }

    /**
     * @param int|null $id
     * @param string|null $identifier
     * @param string $idLabel
     * @param string $identifierLabel
     * @return void
     * @throws LocalizedException
     */
    private function requireOneOf(
        ?int $id,
        ?string $identifier,
        string $idLabel,
        string $identifierLabel
    ): void {
        if ($id === null && $identifier === null) {
            throw new LocalizedException(
                __('One of "%1" or "%2" is required.', $idLabel, $identifierLabel)
            );
        }
        if ($id !== null && $identifier !== null) {
            throw new LocalizedException(
                __('Provide either "%1" or "%2", not both.', $idLabel, $identifierLabel)
            );
        }
    }
}
