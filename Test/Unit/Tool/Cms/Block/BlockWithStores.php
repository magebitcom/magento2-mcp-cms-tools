<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpCmsTools\Test\Unit\Tool\Cms\Block;

use Magento\Cms\Model\Block;

class BlockWithStores extends Block
{
    /**
     * Set the store assignment.
     *
     * @param int[] $stores
     * @return self
     */
    public function setStores(array $stores): self
    {
        return $this->setData('stores', $stores);
    }
}
