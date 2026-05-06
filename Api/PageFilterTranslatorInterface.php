<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpCmsTools\Api;

use Magento\Framework\Api\SearchCriteriaBuilder;

/**
 * Extension point for the `cms.page.list` filter argument.
 *
 * Translators are consulted in DI order; the first to return `true` from
 * `supports()` claims the key and short-circuits the built-in path.
 */
interface PageFilterTranslatorInterface
{
    /**
     * @param string $key
     * @return bool
     */
    public function supports(string $key): bool;

    /**
     * @param string $key
     * @param mixed $value
     * @param SearchCriteriaBuilder $builder
     * @return void
     */
    public function translate(string $key, mixed $value, SearchCriteriaBuilder $builder): void;
}
