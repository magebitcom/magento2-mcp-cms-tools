<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

/**
 * Rollback: delete only blocks with the `mcpsec_injection` identifier.
 */

use Magebit\Mcp\Test\Api\Security\InjectionPayload;
use Magento\Cms\Api\BlockRepositoryInterface;
use Magento\Cms\Model\Block;
use Magento\Cms\Model\ResourceModel\Block\CollectionFactory;
use Magento\TestFramework\Helper\Bootstrap;

$om = Bootstrap::getObjectManager();
/** @var BlockRepositoryInterface $repository */
$repository = $om->get(BlockRepositoryInterface::class);
/** @var CollectionFactory $collectionFactory */
$collectionFactory = $om->get(CollectionFactory::class);
$collection = $collectionFactory->create()
    ->addFieldToFilter('identifier', InjectionPayload::BLOCK_IDENTIFIER);
/** @var Block $block */
foreach ($collection as $block) {
    $repository->delete($block);
}
