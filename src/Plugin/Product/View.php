<?php

namespace M2Boilerplate\ProductBreadcrumbs\Plugin\Product;

use Magento\Catalog\Controller\Product\View as MagentoView;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Product;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Result\PageFactory;
use Magento\Store\Model\StoreManager;
use Magento\Framework\Registry;
use Magento\Framework\Exception\LocalizedException;
use Magento\Catalog\Model\ResourceModel\Category\Collection;
use Magento\Framework\View\Result\Page;
use Magento\Theme\Block\Html\Breadcrumbs;
use Magento\Theme\Block\Html\Title;

class View
{

    /**
     * @var Product
     */
    protected $product;

    /**
     * @var Registry
     */
    protected $registry;

    /**
     * @var Collection
     */
    protected $collection;

    /**
     * @var PageFactory
     */
    protected $resultPage;

    /**
     * @var UrlInterface
     */
    protected $url;


    /**
     * View constructor.
     *
     * @param UrlInterface $url
     * @param Registry     $registry
     * @param Collection   $collection
     * @param PageFactory  $resultPage
     */
    public function __construct(
        UrlInterface $url,
        Registry $registry,
        Collection $collection,
        PageFactory $resultPage)
    {
        $this->registry = $registry;
        $this->collection = $collection;
        $this->resultPage = $resultPage;
        $this->url = $url;
    }

    public function afterExecute(MagentoView $subject, $result)
    {
        if(!$result instanceof Page){
            return $result;
        }

        $resultPage = $this->resultPage->create();
        /** @var Breadcrumbs $breadcrumbsBlock */
        $breadcrumbsBlock = $resultPage->getLayout()->getBlock('breadcrumbs');
        if(!$breadcrumbsBlock || !isset($breadcrumbsBlock)){
            return $result;
        }
        $breadcrumbsBlock->addCrumb(
            'home',
            [
                'label' => __('Home'),
                'title' => __('Go to Home Page'),
                'link' => $this->url->getUrl('/')
            ]
        );

        try {
            $product = $this->getProduct();
        } catch (LocalizedException $e) {
            return $result;
        }

        /** @var Title $pageMainTitle */
        $pageMainTitle = $resultPage->getLayout()->getBlock('page.main.title');
        if ($pageMainTitle) {
            $pageMainTitle->setPageTitle($product->getName());
        }
        $categoryPath = null;
        if (!$product->getCategory()) {
            /** @var \Magento\Catalog\Model\ResourceModel\Category\Collection $categoryCollection */
            try {
                $categoryCollection = $product->getCategoryCollection()->addFieldToFilter('is_active', true);
                $currentLevel = 0;
                foreach ($categoryCollection->getItems() as $category) {
                    if ($category->isInRootCategoryList() && $currentLevel < $category->getLevel()) {
                        $categoryPath = $category->getPath();
                        $currentLevel = $category->getLevel();
                    }
                }
            } catch (LocalizedException $e) {
                // do nothing
            }
        } else {
            $categoryPath = $product->getCategory()->getPath();
        }
        if ($categoryPath) {
            $categoryIds = explode('/', $categoryPath);

            $categoriesCollection = null;
            try {
                $categoriesCollection = $this->collection
                    ->addFieldToFilter('entity_id', array('in' => $categoryIds))
                    ->addAttributeToSelect('name')
                    ->addAttributeToSelect('url_key')
                    ->addAttributeToSelect('include_in_menu')
                    ->addAttributeToSelect('is_active')
                    ->addAttributeToSelect('is_anchor')
                ;
            } catch (LocalizedException $e) {
                $breadcrumbsBlock->addCrumb(
                    'product',
                    [
                        'label' => $product->getName(),
                        'title' => $product->getName(),
                    ]
                );
                return $result;
            }
            foreach ($categoryIds as $id) {
                $category = $categoriesCollection->getItemById($id);
                /** @var $category Category */
                if ($category && $category->getIsActive() && $category->isInRootCategoryList()) {
                    $categoryId = $category->getId();
                    $path = [
                        'label' => $category->getName(),
                        'link' => $category->getUrl() ? $category->getUrl() : ''
                    ];
                    $breadcrumbsBlock->addCrumb('category' . $categoryId, $path);
                }
            }
        }

        $breadcrumbsBlock->addCrumb(
            'product',
            [
                'label' => $product->getName(),
                'title' => $product->getName(),
            ]
        );

        return $result;
    }

    /**
     * @return Product
     * @throws LocalizedException
     */
    protected function getProduct()
    {
        if (is_null($this->product)) {
            $this->product = $this->registry->registry('product');

            if (!$this->product->getId()) {
                throw new LocalizedException(__('Failed to initialize product'));
            }
        }

        return $this->product;
    }
}
