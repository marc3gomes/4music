<?php
namespace Blueskytechco\CustomCatalog\Controller\Notifystock;

use Magento\Framework\Controller\ResultFactory;

class Save extends \Magento\Framework\App\Action\Action
{
    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var \Magento\Customer\Model\CustomerFactory
     */
    protected $customerFactory;

    /**
     * @var \Magento\Catalog\Api\ProductRepositoryInterface
     */
    protected $productRepository;

    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    protected $_objectManager;

    /**
     * @var \Magento\Framework\Data\Form\FormKey\Validator
     */
    protected $_formKeyValidator;

    /**
     * @param \Magento\Framework\App\Action\Context      $context
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Customer\Model\CustomerFactory    $customerFactory
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Customer\Model\CustomerFactory $customerFactory,
        \Magento\Framework\ObjectManagerInterface $objectmanager,
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
        \Magento\Framework\Data\Form\FormKey\Validator $formKeyValidator
    ) {
        $this->storeManager     = $storeManager;
        $this->productRepository = $productRepository;
        $this->customerFactory  = $customerFactory;
        $this->_objectManager = $objectmanager;
        $this->_formKeyValidator = $formKeyValidator;

        parent::__construct($context);
    }

    public function execute()
    {
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        if (!$this->_formKeyValidator->validate($this->getRequest())) {
            $this->messageManager->addErrorMessage(
                __('Your session has expired')
            );
            $resultRedirect->setUrl($this->_redirect->getRefererUrl());
            return $resultRedirect;
        }
        $email = $this->getRequest()->getPost('notify_email');
        $product_id = $this->getRequest()->getPost('product_id');
        if (!$email) {
            $this->messageManager->addErrorMessage(
                __('Email is incorrect')
            );
            $resultRedirect->setUrl($this->_redirect->getRefererUrl());
            return $resultRedirect;
        }
        // Get Website ID
        $websiteId  = $this->storeManager->getWebsite()->getWebsiteId();
        
        try {
            // Instantiate object (this is the most important part)
            $customer = $this->customerFactory->create();
            $customer->setWebsiteId($websiteId);
            if($customer->loadByEmail($email)) {
                $customerId = $customer->getId(); 
                if (!$customerId) {
                    $this->messageManager->addErrorMessage(__('Your email has not registered an account to be able to use this function. Please register an account to use the function.'));
                    $resultRedirect->setPath('customer/account/create');
                    return $resultRedirect;
                }
            }

            /* @var $product \Magento\Catalog\Model\Product */
            $product = $this->productRepository->getById($product_id);
            $store = $this->storeManager->getStore();
            /** @var \Magento\ProductAlert\Model\Stock $model */
            $model = $this->_objectManager->create(\Magento\ProductAlert\Model\Stock::class)
                ->setCustomerId($customerId)
                ->setProductId($product->getId())
                ->setWebsiteId($store->getWebsiteId())
                ->setStoreId($store->getId());
            $model->save();
            $this->messageManager->addSuccessMessage(__('Alert subscription has been saved.'));
        } catch (\Exception $e) {
            $this->messageManager->addExceptionMessage(
                $e,
                __("An unknown error.")
            );
        }
        $resultRedirect->setUrl($this->_redirect->getRefererUrl());
        return $resultRedirect;
    }
}