<?php
/**
 * Copyright © 2021 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Blueskytechco\MenuBuilder\Model;

use Magento\Framework\Model\AbstractModel;
use Blueskytechco\MenuBuilder\Model\ResourceModel\MenuBuilderItemMeta as MenuBuilderItemMetaResourceModel;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;

class MenuBuilderItemMeta extends AbstractModel
{
    
    /**
     * Pattern constructor.
     * @param Context $context
     * @param Registry $registry
     */
    public function __construct(
        Context $context,
        Registry $registry
    ) {
        parent::__construct(
            $context,
            $registry
        );
    }

    /**
     * @return void
     */
    public function _construct()
    {
        $this->_init(MenuBuilderItemMetaResourceModel::class);
    }
    
    /**
     * Create Menu Builder
     * @return \Blueskytechco\MenuBuilder\Model\MenuBuilderItemMeta
     */
    public function createMenuBuilderItemMeta($menu_item_id, $meta_key, $meta_value)
    {
        $this->setData([
            'menu_item_id' => $menu_item_id,
            'meta_key' => $meta_key,
            'meta_value' => $meta_value,
        ])->setId(null)->save();
        return $this;
    }
}
