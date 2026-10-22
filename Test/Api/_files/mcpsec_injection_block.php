<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

/**
 * Fixture: one active CMS block `mcpsec_injection` for all store views, carrying the injection text.
 */

use Magebit\Mcp\Test\Api\Security\InjectionPayload;
use Magento\Cms\Api\BlockRepositoryInterface;
use Magento\Cms\Model\Block;
use Magento\TestFramework\Helper\Bootstrap;

// Remove a leftover from an interrupted run first.
require __DIR__ . '/mcpsec_injection_block_rollback.php';

$om = Bootstrap::getObjectManager();
/** @var Block $block */
$block = $om->create(Block::class);
$block->setIdentifier(InjectionPayload::BLOCK_IDENTIFIER)
    ->setTitle('MCPSEC injection block')
    ->setContent(InjectionPayload::TEXT)
    ->setIsActive(true);
$block->setData('stores', [0]);
/** @var BlockRepositoryInterface $repository */
$repository = $om->get(BlockRepositoryInterface::class);
$repository->save($block);
