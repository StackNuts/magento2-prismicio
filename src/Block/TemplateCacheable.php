<?php
declare(strict_types=1);

namespace Elgentos\PrismicIO\Block;

use Elgentos\PrismicIO\ViewModel\DocumentResolver;
use Elgentos\PrismicIO\ViewModel\LinkResolver;
use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\View\Element\Template\Context;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Drop-in Template replacement for opting a slice/block into block-HTML caching
 * (prismicio/block_cache admin config) via a layout XML class swap.
 */
class TemplateCacheable extends Template implements IdentityInterface
{
    use DocumentCacheableTrait;

    public function __construct(
        Context $context,
        DocumentResolver $documentResolver,
        LinkResolver $linkResolver,
        StoreManagerInterface $storeManager,
        array $data = []
    ) {
        $this->storeManager = $storeManager;

        parent::__construct($context, $documentResolver, $linkResolver, $data);
    }
}
