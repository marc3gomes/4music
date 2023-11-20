<?php
/**
 * Copyright © 2021 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Blueskytechco\MenuBuilder\Controller\Adminhtml\Builder;

use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Json\Helper\Data as DataHelper;
use Magento\Framework\DataObjectFactory;
use Magento\Store\Api\StoreRepositoryInterface;
use Magento\Cms\Model\PageFactory;
use Blueskytechco\MenuBuilder\Helper\Admin as MenuHelper;

class Ajax extends \Magento\Backend\App\Action
{
    
    private $dataHelper;
    
    private $_jsonEncoder;
    
    protected $customerSession;
    
    protected $dataObjectFactory;
    
    protected $_storeManager;
    
    protected $_storeRepository;
    
    protected $pageFactory;
    
    /**
     * @var \Blueskytechco\MenuBuilder\Model\MenuBuilderItemFactory
     */
    protected $menuBuilderItemFactory;

    /**
     * @var \Blueskytechco\MenuBuilder\Model\MenuBuilderItemMetaFactory
     */
    protected $menuBuilderItemMetaFactory;
    
    /**
     * @var \Magento\Catalog\Model\CategoryRepository
     */
    protected $_categoryRepository;
    
    /**
     * @var MenuHelper
     */
    protected $menuHelper;

    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\Framework\Json\EncoderInterface $jsonEncoder,
        DataObjectFactory $dataObjectFactory,
        StoreRepositoryInterface $storeRepository,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Customer\Model\Session $customerSession,
        DataHelper $dataHelper,
        MenuHelper $menuHelper,
        PageFactory $pageFactory,
        \Blueskytechco\MenuBuilder\Model\MenuBuilderItemFactory $menuBuilderItemFactory,
        \Blueskytechco\MenuBuilder\Model\MenuBuilderItemMetaFactory $menuBuilderItemMetaFactory,
        \Magento\Catalog\Model\CategoryRepository $categoryRepository
    ) {
        $this->dataHelper = $dataHelper;
        $this->customerSession = $customerSession;
        $this->_storeManager = $storeManager;
        $this->_storeRepository = $storeRepository;
        $this->dataObjectFactory = $dataObjectFactory;
        $this->_jsonEncoder = $jsonEncoder;
        $this->menuHelper = $menuHelper;
        $this->pageFactory = $pageFactory;
        $this->_categoryRepository = $categoryRepository;
        $this->menuBuilderItemFactory = $menuBuilderItemFactory;
        $this->menuBuilderItemMetaFactory = $menuBuilderItemMetaFactory;
        parent::__construct($context);
    }
    
    public function execute()
    {
        $id_menu = $this->getRequest()->getPost('id_menu');
        $type = $this->getRequest()->getPost('type');
        $html = '';
        $array_items = [];
        if (!$id_menu) {
            return $html;
        }
        if ($type == 'cmspage' || $type == 'category') {
            $id_menu_items = $this->getRequest()->getPost('id');
            foreach ($id_menu_items as $item_object_id) {
                if ($type == 'cmspage') {
                    $html .= $this->getMenuItemCmsPageHtml($item_object_id, $id_menu, $type);
                } else {
                    $depth = 0;
                    $parent_item = 0;
                    $category_id = $item_object_id['category'];
                    $parent_id = $item_object_id['parent_id'];
                    $depth = $this->getDepthCategory($parent_id, $id_menu_items, $depth);
                    $id_menu_item = $this->getIdMenuItemCategory($category_id, $id_menu, $type, $depth);
                    $array_items[] = ['category'=>$category_id,'item_id'=>$id_menu_item];
                    foreach ($array_items as $item) {
                        if ($parent_id == $item['category']) {
                            $parent_item = $item['item_id'];
                            break;
                        }
                    }
                    $html .= $this->getMenuItemCategoryHtml(
                        $id_menu_item,
                        $category_id,
                        $id_menu,
                        $type,
                        $depth,
                        $parent_item
                    );
                }
            }
        } else {
            $name = $this->getRequest()->getPost('name');
            $url = $this->getRequest()->getPost('url');
            if ($name && $url) {
                $html .= $this->getMenuItemCustomLink($name, $url, $id_menu, $type);
            }
        }
        $resultLayout = $this->resultFactory->create(ResultFactory::TYPE_LAYOUT);
        return $this->resultFactory->create(ResultFactory::TYPE_JSON)->setData(['html' => $html]);
    }
    
    public function getDepthCategory($parent_id, $id_menu_items, $depth)
    {
        foreach ($id_menu_items as $item_object_id) {
            if ($parent_id == $item_object_id['category']) {
                if ($item_object_id['parent_id'] == 0) {
                    ++$depth;
                } else {
                    $depth = $this->getDepthCategory($item_object_id['parent_id'], $id_menu_items, $depth+1);
                }
                break;
            }
        }
        return $depth;
    }
    
    public function getMenuItemCmsPageHtml($item_object_id, $id_menu, $type)
    {
        $html = '';
        $page = $this->pageFactory->create()->load($item_object_id);
        if ($page) {
            $create_item = $this->menuBuilderItemFactory->create();
            $create_item_meta = $this->menuBuilderItemMetaFactory->create();
            $create_item->createMenuBuilderItem($id_menu, $item_object_id, null, $type);
            $id_menu_item = $create_item->getId();
            $array_field = $this->getArrayMetaField();
            foreach ($array_field as $field) {
                $value = '';
                if ($field == 'title') {
                    $value = $page->getTitle();
                } else if ($field == 'url') {
                    continue;
                } else if ($field == 'data_db_id'){
                    $value = $id_menu_item;
                } else if ($field == 'parent_id'){
                    $value = 0;
                }
                $create_item_meta->createMenuBuilderItemMeta($id_menu_item, $field, $value);
            }
            $html .= '<li id="menu-item-'.$id_menu_item.'" class="menu-item menu-item-depth-0">';
                $html .= '<div class="menu-item-bar">';
                    $html .= '<div class="menu-item-handle">';
                        $html .= '<div class="item-title">
                            <div class="menu-item-title">'.$page->getTitle().' ( '.__('Cms Page').' )</div>
                        </div>';
                        $html .= '<div class="menu-controls">';
                            $html .= '<div class="item-controls">';
                                $html .= '<a class="item-edit" href="#" data-id="'.$id_menu_item.'"
                                    data-type="'.$type.'" data-depth="0">
                                    <span class="screen-reader-text">'.__('Edit').'</span>
                                    </a>';
                                $html .= '<a class="item-delete" href="#" data-id="'.$id_menu_item.'">
                                    <span class="screen-reader-text">'.__('Remove').'</span>
                                    </a>';
                            $html .= '</div>';
                        $html .= '</div>';
                    $html .= '</div>';
                $html .= '</div>';
                $html .= $this->menuHelper->getMenuItemSettingsHtml(
                    $page->getTitle(),
                    null,
                    $type,
                    $id_menu_item,
                    $id_menu,
                    0,
                    0
                );
                $html .= '<ul class="menu-item-transport" style=""></ul>';
            $html .= '</li>';
        }
        return $html;
    }
    
    public function getIdMenuItemCategory(
        $category_id,
        $id_menu,
        $type,
        $depth
    ) {
        $create_item = $this->menuBuilderItemFactory->create();
        $create_item->createMenuBuilderItem($id_menu, $category_id, null, $type);
        $id_menu_item = $create_item->getId();
        return $id_menu_item;
    }
    
    public function getMenuItemCategoryHtml($id_menu_item, $category_id, $id_menu, $type, $depth, $parent_item)
    {
        $html = '';
        $create_item_meta = $this->menuBuilderItemMetaFactory->create();
        $category = $this->_categoryRepository->get($category_id);
        if ($category) {
            $array_field = $this->getArrayMetaField();
            foreach ($array_field as $field) {
                $value = '';
                if ($field == 'title') {
                    $value = $category->getName();
                } else if ($field == 'url') {
                    continue;
                } else if ($field == 'data_db_id'){
                    $value = $id_menu_item;
                } else if ($field == 'parent_id'){
                    $value = 0;
                }
                $create_item_meta->createMenuBuilderItemMeta($id_menu_item, $field, $value);
            }
            $html .= '<li id="menu-item-'.$id_menu_item.'" class="menu-item menu-item-depth-'.$depth.'">';
                $html .= '<div class="menu-item-bar">';
                    $html .= '<div class="menu-item-handle">';
                        $html .= '<div class="item-title">
                            <div class="menu-item-title">'.$category->getName().' ( '.__('Category').' )</div>
                        </div>';
                        $html .= '<div class="menu-controls">';
                            $html .= '<div class="item-controls">';
                                $html .= '<a class="item-edit" href="#" data-id="'.$id_menu_item.'"
                                    data-type="'.$type.'" data-depth="0">
                                    <span class="screen-reader-text">'.__('Edit').'</span>
                                    </a>';
                                $html .= '<a class="item-delete" href="#" data-id="'.$id_menu_item.'">
                                    <span class="screen-reader-text">'.__('Remove').'</span>
                                    </a>';
                            $html .= '</div>';
                        $html .= '</div>';
                    $html .= '</div>';
                $html .= '</div>';
                $html .= $this->menuHelper->getMenuItemSettingsHtml(
                    $category->getName(),
                    null,
                    $type,
                    $id_menu_item,
                    $id_menu,
                    $depth,
                    $parent_item
                );
                $html .= '<ul class="menu-item-transport" style=""></ul>';
            $html .= '</li>';
        }
        return $html;
    }
    
    public function getMenuItemCustomLink($name, $url, $id_menu, $type)
    {
        $html = '';
        $create_item = $this->menuBuilderItemFactory->create();
        $create_item_meta = $this->menuBuilderItemMetaFactory->create();
        $create_item->createMenuBuilderItem($id_menu, null, $name, $type);
        $id_menu_item = $create_item->getId();
        $array_field = $this->getArrayMetaField();
        foreach ($array_field as $field) {
            $value = '';
            if ($field == 'title') {
                $value = $name;
            } else if ($field == 'url') {
                $value = $url;
            } else if ($field == 'data_db_id'){
                $value = $id_menu_item;
            } else if ($field == 'parent_id'){
                $value = 0;
            }
            $create_item_meta->createMenuBuilderItemMeta($id_menu_item, $field, $value);
        }
        
        $html .= '<li id="menu-item-'.$id_menu_item.'" class="menu-item menu-item-depth-0">';
            $html .= '<div class="menu-item-bar">';
                $html .= '<div class="menu-item-handle">';
                    $html .= '<div class="item-title">
                        <div class="menu-item-title">'.$name.'  ( '.__('Custom Link').' )</div>
                    </div>';
                    $html .= '<div class="menu-controls">';
                        $html .= '<div class="item-controls">';
                            $html .= '<a class="item-edit" href="#" data-id="'.$id_menu_item.'"
                                data-type="'.$type.'" data-depth="0">
                                <span class="screen-reader-text">'.__('Edit').'</span>
                                </a>';
                            $html .= '<a class="item-delete" href="#" data-id="'.$id_menu_item.'">
                                <span class="screen-reader-text">'.__('Remove').'</span>
                                </a>';
                        $html .= '</div>';
                    $html .= '</div>';
                $html .= '</div>';
            $html .= '</div>';
            $html .= $this->menuHelper->getMenuItemSettingsHtml(
                $name,
                $url,
                $type,
                $id_menu_item,
                $id_menu,
                0,
                0
            );
            $html .= '<ul class="menu-item-transport" style=""></ul>';
        $html .= '</li>';
        return $html;
    }

    public function getArrayMetaField()
    {
        $array_key = ['title','url','classes','lable','lable_color','icon_image',
            'submenu_type','full_width_multicolunm','full_width_block_content',
            'submenu_columns','dynamic_content_mul','dynamic_block_content',
            'submenu_bg_image','background_repeat','background_position','background_size',
            'block_content','block_top','block_bottom','block_left',
            'block_right','block_left_width','block_right_width','data_db_id','parent_id','font_icon'
        ];
        return $array_key;
    }
}
