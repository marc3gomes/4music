<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Blueskytechco\MenuBuilder\Model;

use Magento\Framework\App\Cache\Type\Config;
use Magento\Framework\DataObject;
use Magento\Framework\Escaper;
use Magento\Framework\Math\Random;
use Magento\Framework\View\Asset\Repository;
use Magento\Framework\View\Asset\Source;
use Magento\Framework\View\FileSystem;
use Magento\Widget\Helper\Conditions;
use Magento\Widget\Model\Config\Data;

/**
 * Widget model for different purposes
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 *
 * @api
 * @since 100.0.2
 */
class Widget
{
    /**
     * @var Data
     */
    protected $dataStorage;

    /**
     * @var Config
     */
    protected $configCacheType;

    /**
     * @var Repository
     */
    protected $assetRepo;

    /**
     * @var Source
     */
    protected $assetSource;

    /**
     * @var FileSystem
     */
    protected $viewFileSystem;

    /**
     * @var Escaper
     */
    protected $escaper;

    /**
     * @var array
     */
    protected $widgetsArray = [];

    /**
     * @var Conditions
     */
    protected $conditionsHelper;

    /**
     * @var Random
     */
    private $mathRandom;

    /**
     * @var string[]
     */
    private $reservedChars = ['}', '{'];
    
    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    private $_storeManager;
    
    /**
     * @var \Magento\Cms\Model\BlockFactory
     */
    private $_blockFactory;

    /**
     * @param Escaper $escaper
     * @param Data $dataStorage
     * @param Repository $assetRepo
     * @param Source $assetSource
     * @param FileSystem $viewFileSystem
     * @param Conditions $conditionsHelper
     */
    public function __construct(
        Escaper $escaper,
        Data $dataStorage,
        Repository $assetRepo,
        Source $assetSource,
        FileSystem $viewFileSystem,
        Conditions $conditionsHelper,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Cms\Model\BlockFactory $blockFactory
    ) {
        $this->escaper = $escaper;
        $this->dataStorage = $dataStorage;
        $this->assetRepo = $assetRepo;
        $this->assetSource = $assetSource;
        $this->viewFileSystem = $viewFileSystem;
        $this->conditionsHelper = $conditionsHelper;
        $this->_storeManager = $storeManager;
        $this->_blockFactory = $blockFactory;
    }

    /**
     * Return widget config based on its class type
     *
     * @param string $type Widget type
     * @return null|array
     */
    public function getWidgetByClassType($type)
    {
        $widgets = $this->getWidgets();
        /** @var array $widget */
        foreach ($widgets as $widget) {
            if (isset($widget['@']['type']) && $type === $widget['@']['type']) {
                return $widget;
            }
        }

        return null;
    }

    /**
     * Return widget XML configuration as \Magento\Framework\DataObject and makes some data preparations
     *
     * @param string $type Widget type
     * @return \Magento\Framework\DataObject
     */
    public function getConfigAsObject($type)
    {
        $widget = $this->getWidgetByClassType($type);

        $object = new \Magento\Framework\DataObject();
        if ($widget === null) {
            return $object;
        }
        $widget = $this->getAsCanonicalArray($widget);

        // Save all nodes to object data
        $object->setData($widget);
        $object->setType($type);

        // Correct widget parameters and convert its data to objects
        $newParams = $this->prepareWidgetParameters($object);
        $object->setData('parameters', $newParams);

        return $object;
    }

    /**
     * Prepare widget parameters
     *
     * @param \Magento\Framework\DataObject $object
     * @return array
     */
    protected function prepareWidgetParameters(\Magento\Framework\DataObject $object)
    {
        $params = $object->getData('parameters');
        $newParams = [];
        if (is_array($params)) {
            $sortOrder = 0;
            foreach ($params as $key => $data) {
                if (is_array($data)) {
                    $data = $this->prepareDropDownValues($data, $key, $sortOrder);
                    $data = $this->prepareHelperBlock($data);

                    $newParams[$key] = new \Magento\Framework\DataObject($data);
                    $sortOrder++;
                }
            }
        }
        uasort($newParams, [$this, 'sortParameters']);

        return $newParams;
    }

    /**
     * Prepare drop-down values
     *
     * @param array $data
     * @param string $key
     * @param int $sortOrder
     * @return array
     */
    protected function prepareDropDownValues(array $data, $key, $sortOrder)
    {
        $data['key'] = $key;
        $data['sort_order'] = isset($data['sort_order']) ? (int)$data['sort_order'] : $sortOrder;

        $values = [];
        if (isset($data['values']) && is_array($data['values'])) {
            foreach ($data['values'] as $value) {
                if (isset($value['label']) && isset($value['value'])) {
                    $values[] = $value;
                }
            }
        }
        $data['values'] = $values;

        return $data;
    }

    /**
     * Prepare helper block
     *
     * @param array $data
     * @return array
     */
    protected function prepareHelperBlock(array $data)
    {
        if (isset($data['helper_block'])) {
            $helper = new \Magento\Framework\DataObject();
            if (isset($data['helper_block']['data']) && is_array($data['helper_block']['data'])) {
                $helper->addData($data['helper_block']['data']);
            }
            if (isset($data['helper_block']['type'])) {
                $helper->setType($data['helper_block']['type']);
            }
            $data['helper_block'] = $helper;
        }

        return $data;
    }

    /**
     * Return filtered list of widgets
     *
     * @param array $filters Key-value array of filters for widget node properties
     * @return array
     */
    public function getWidgets($filters = [])
    {
        $widgets = $this->dataStorage->get();
        $result = $widgets;

        // filter widgets by params
        if (is_array($filters) && !empty($filters)) {
            foreach ($widgets as $code => $widget) {
                foreach ($filters as $field => $value) {
                    if (!isset($widget[$field]) || (string)$widget[$field] != $value) {
                        unset($result[$code]);
                        break;
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Return list of widgets as array
     *
     * @param array $filters Key-value array of filters for widget node properties
     * @return array
     */
    public function getWidgetsArray($filters = [])
    {
        if (empty($this->widgetsArray)) {
            $result = [];
            foreach ($this->getWidgets($filters) as $code => $widget) {
                $result[$widget['name']] = [
                    'name' => __((string)$widget['name']),
                    'code' => $code,
                    'type' => $widget['@']['type'],
                    'description' => __((string)$widget['description']),
                ];
            }
            usort($result, [$this, "sortWidgets"]);
            $this->widgetsArray = $result;
        }
        return $this->widgetsArray;
    }

    /**
     * Return widget presentation code in WYSIWYG editor
     *
     * @param string $type Widget Type
     * @param array $params Pre-configured Widget Params
     * @param bool $asIs Return result as widget directive(true) or as placeholder image(false)
     * @return string Widget directive ready to parse
     */
    public function getWidgetDeclaration($type, $params = [], $asIs = true)
    {
        $widget = $this->getConfigAsObject($type);

        $params = array_filter($params, function ($value) {
            return $value !== null && $value !== '';
        });

        $directiveParams = '';
        foreach ($params as $name => $value) {
            // Retrieve default option value if pre-configured
            $directiveParams .= $this->getDirectiveParam($widget, $name, $value);
        }

        $directive = sprintf(
            '{{block class="Magento\Cms\Block\Block" block_id="%s"}}',
            $this->getWidgetPageVarNameCmsBlock($type, $params)
        );
        
        return $directive;
    }

    /**
     * Returns directive param with prepared value
     *
     * @param DataObject $widget
     * @param string $name
     * @param string|array $value
     * @return string
     */
    private function getDirectiveParam(DataObject $widget, string $name, $value): string
    {
        if ($name === 'conditions') {
            $name = 'conditions_encoded';
            $value = $this->conditionsHelper->encode($value);
        } elseif (is_array($value)) {
            $value = implode(',', $value);
        } elseif (trim($value) === '') {
            $parameters = $widget->getParameters();
            if (isset($parameters[$name]) && is_object($parameters[$name])) {
                $value = $parameters[$name]->getValue();
            }
        } else {
            $value = $this->getPreparedValue($value);
        }

        return sprintf(' %s="%s"', $name, $this->escaper->escapeHtmlAttr($value, false));
    }

    /**
     * Returns encoded value if it contains reserved chars
     *
     * @param string $value
     * @return string
     */
    private function getPreparedValue(string $value): string
    {
        $pattern = sprintf('/%s/', implode('|', $this->reservedChars));

        return preg_match($pattern, $value) ? rawurlencode($value) : $value;
    }
    
    /**
     * Get widget page varname
     *
     * @param array $params
     * @return string
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function getWidgetPageVarNameCmsBlock($type, $params = [])
    {
        
        $pageVarName = '';
        if (isset($params['block_id']) && $params['block_id']) {
            $block_id = $params['block_id'];
            $storeId = $this->_storeManager->getStore()->getId();
            /** @var \Magento\Cms\Model\Block $block */
            $block = $this->_blockFactory->create();
            $block_data = $block->setStoreId($storeId)->load($block_id);
            $pageVarName = $block_data->getIdentifier();
        }

        return $pageVarName;
    }

    /**
     * Remove attributes from widget array and emulate work of \Magento\Framework\Simplexml\Element::asCanonicalArray
     *
     * @param array $inputArray
     * @return array
     */
    protected function getAsCanonicalArray($inputArray)
    {
        if (array_key_exists('@', $inputArray)) {
            unset($inputArray['@']);
        }
        foreach ($inputArray as $key => $value) {
            if (!is_array($value)) {
                continue;
            }
            $inputArray[$key] = $this->getAsCanonicalArray($value);
        }
        return $inputArray;
    }

    /**
     * Encode string to valid HTML id element, based on base64 encoding
     *
     * @param string $string
     * @return string
     */
    protected function idEncode($string)
    {
        return strtr(base64_encode($string), '+/=', ':_-');
    }

    /**
     * User-defined widgets sorting by Name
     *
     * @param array $firstElement
     * @param array $secondElement
     * @return bool
     */
    protected function sortWidgets($firstElement, $secondElement)
    {
        return strcmp($firstElement["name"], $secondElement["name"]);
    }

    /**
     * Widget parameters sort callback
     *
     * @param \Magento\Framework\DataObject $firstElement
     * @param \Magento\Framework\DataObject $secondElement
     * @return int
     */
    protected function sortParameters($firstElement, $secondElement)
    {
        $aOrder = (int)$firstElement->getData('sort_order');
        $bOrder = (int)$secondElement->getData('sort_order');
        return $aOrder < $bOrder ? -1 : ($aOrder > $bOrder ? 1 : 0);
    }
}
