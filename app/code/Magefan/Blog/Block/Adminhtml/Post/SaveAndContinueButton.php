<?php
/**
 * Copyright © Magefan (support@magefan.com). All rights reserved.
 * Please visit Magefan.com for license details (https://magefan.com/end-user-license-agreement).
 *
 * Glory to Ukraine! Glory to the heroes!
 */

namespace Magefan\Blog\Block\Adminhtml\Post;

/**
 * Class Save And ContinueButton Block
 */
class SaveAndContinueButton extends \Magefan\Community\Block\Adminhtml\Edit\SaveAndContinueButton
{
    /**
     * @return array|string
     */
    public function getButtonData()
    {
        if (!$this->authorization->isAllowed("Magefan_Blog::post_save")) {
            return [];
        }
        return parent::getButtonData();
    }
}
