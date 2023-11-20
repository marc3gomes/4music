<?php
/**
 * Copyright © 2019 Blueskytechco. All rights reserved. 
 */

namespace Blueskytechco\StoreLocator\Controller\Store;

class View extends \Blueskytechco\StoreLocator\Controller\Index
{
    /**
     * @return \Magento\Framework\View\Result\Page
     */
    public function execute()
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->getConfig()->getTitle()->set(__('Store Locator'));

        return $resultPage;
    }
}
