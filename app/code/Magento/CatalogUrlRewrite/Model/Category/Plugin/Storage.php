<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\CatalogUrlRewrite\Model\Category\Plugin;

use Magento\CatalogUrlRewrite\Model\ProductUrlRewriteGenerator;
use Magento\UrlRewrite\Model\StorageInterface;
use Magento\UrlRewrite\Model\UrlFinderInterface;
use Magento\UrlRewrite\Service\V1\Data\UrlRewrite;
use Magento\CatalogUrlRewrite\Model\ResourceModel\Category\Product;

/**
 * Storage Plugin
 */
class Storage
{
    /**
     * @var \Magento\UrlRewrite\Model\UrlFinderInterface
     */
    private $urlFinder;

    /**
     * @var \Magento\CatalogUrlRewrite\Model\ResourceModel\Category\Product
     */
    private $productResource;

    /**
     * @param UrlFinderInterface $urlFinder
     * @param Product $productResource
     */
    public function __construct(
        UrlFinderInterface $urlFinder,
        Product $productResource
    ) {
        $this->urlFinder = $urlFinder;
        $this->productResource = $productResource;
    }

    /**
     * Save product/category urlRewrite association
     *
     * @param \Magento\UrlRewrite\Model\StorageInterface $object
     * @param \Magento\UrlRewrite\Service\V1\Data\UrlRewrite[] $result
     * @param \Magento\UrlRewrite\Service\V1\Data\UrlRewrite[] $urls
     * @return \Magento\UrlRewrite\Service\V1\Data\UrlRewrite[]
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterReplace(StorageInterface $object, array $result, array $urls)
    {
        $toSave = [];
        foreach ($this->filterUrls($result) as $record) {
            $metadata = $record->getMetadata();
            $toSave[] = [
                'url_rewrite_id' => $record->getUrlRewriteId(),
                'category_id' => $metadata['category_id'],
                'product_id' => $record->getEntityId(),
            ];
        }
        if (count($toSave) > 0) {
//            Fix Commented out because we try to produce a failing test
//            $this->productResource->removeMultiple(array_column($toSave, 'url_rewrite_id'));
            $this->productResource->saveMultiple($toSave);
        }
        return $result;
    }

    /**
     * Remove product/category urlRewrite association
     *
     * @param \Magento\UrlRewrite\Model\StorageInterface $object
     * @param array $data
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function beforeDeleteByData(StorageInterface $object, array $data)
    {
        $this->productResource->removeMultipleByProductCategory($data);
    }

    /**
     * Filter urls
     *
     * @param \Magento\UrlRewrite\Service\V1\Data\UrlRewrite[] $urls
     * @return \Magento\UrlRewrite\Service\V1\Data\UrlRewrite[]
     */
    protected function filterUrls(array $urls)
    {
        $filteredUrls = [];
        /** @var UrlRewrite $url */
        foreach ($urls as $url) {
            if ($this->isCorrectUrl($url)) {
                $filteredUrls[] = $url;
            }
        }
        $data = [];
        foreach ($filteredUrls as $url) {
            foreach ([UrlRewrite::REQUEST_PATH, UrlRewrite::STORE_ID] as $key) {
                $fieldValue = $url->getByKey($key);
                if (!isset($data[$key]) || !in_array($fieldValue, $data[$key])) {
                    $data[$key][] = $fieldValue;
                }
            }
        }
        return $data ? $this->urlFinder->findAllByData($data) : [];
    }

    /**
     * Check if url is correct
     *
     * @param UrlRewrite $url
     * @return bool
     */
    protected function isCorrectUrl(UrlRewrite $url)
    {
        $metadata = $url->getMetadata();
        return $url->getEntityType() == ProductUrlRewriteGenerator::ENTITY_TYPE
        && !empty($metadata['category_id'])
        && $url->getIsAutogenerated();
    }
}
