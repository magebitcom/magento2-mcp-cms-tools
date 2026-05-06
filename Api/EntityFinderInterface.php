<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpCmsTools\Api;

use Magento\Cms\Api\Data\BlockInterface;
use Magento\Cms\Api\Data\PageInterface;
use Magento\Framework\Exception\LocalizedException;

/** Id-or-identifier lookup for CMS pages and blocks. */
interface EntityFinderInterface
{
    /**
     * @param array $args
     * @phpstan-param array<string, mixed> $args
     * @return PageInterface
     * @throws LocalizedException
     */
    public function pageFrom(array $args): PageInterface;

    /**
     * @param array $args
     * @phpstan-param array<string, mixed> $args
     * @return BlockInterface
     * @throws LocalizedException
     */
    public function blockFrom(array $args): BlockInterface;
}
