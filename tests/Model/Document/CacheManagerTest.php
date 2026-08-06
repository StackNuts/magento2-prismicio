<?php declare(strict_types=1);

namespace Elgentos\PrismicIO\Model\Document;

use Elgentos\PrismicIO\Model\CacheTypes;
use Magento\Framework\App\Cache\StateInterface;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\Stdlib\CookieManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use stdClass;

class CacheManagerTest extends TestCase
{
    private CacheInterface&MockObject $cache;

    private SerializerInterface&MockObject $serializer;

    private StateInterface&MockObject $cacheState;

    private CookieManagerInterface&MockObject $cookieManager;

    private CacheManager $cacheManager;

    protected function setUp(): void
    {
        $this->cache = $this->createMock(CacheInterface::class);
        $this->serializer = $this->createMock(SerializerInterface::class);
        $this->cacheState = $this->createMock(StateInterface::class);
        $this->cookieManager = $this->createMock(CookieManagerInterface::class);

        $this->cacheManager = new CacheManager(
            $this->cache,
            $this->serializer,
            $this->cacheState,
            $this->cookieManager,
            ['ttl' => 86400]
        );
    }

    public function testItReturnsNothingWhenTheCacheTypeIsDisabled(): void
    {
        $this->cacheState->method('isEnabled')->willReturn(false);
        $this->cache->expects($this->never())->method('load');

        $this->assertNull($this->cacheManager->get('landing_page', 'home', 'nl-nl', 1, 1));
    }

    public function testItDoesNotStoreWhenTheCacheTypeIsDisabled(): void
    {
        $this->cacheState->method('isEnabled')->willReturn(false);
        $this->cache->expects($this->never())->method('save');

        $this->cacheManager->set(new stdClass(), 'landing_page', 'home', 'nl-nl', 1, 1);
    }

    /**
     * An editor previewing content must see the preview, not whatever visitors get served
     */
    public function testItIsBypassedWhileAPreviewCookieIsSet(): void
    {
        $this->cacheState->method('isEnabled')->willReturn(true);
        $this->cookieManager->method('getCookie')->willReturn('a-preview-ref');
        $this->cache->expects($this->never())->method('load');
        $this->cache->expects($this->never())->method('save');

        $this->assertNull($this->cacheManager->get('landing_page', 'home', 'nl-nl', 1, 1));
        $this->cacheManager->set(new stdClass(), 'landing_page', 'home', 'nl-nl', 1, 1);
    }

    public function testItStoresPerStoreAndWebsiteWithTheConfiguredLifetime(): void
    {
        $document = new stdClass();
        $document->uid = 'home';

        $this->cacheState->method('isEnabled')->willReturn(true);
        $this->cookieManager->method('getCookie')->willReturn(null);
        $this->serializer->method('serialize')->willReturn('{"uid":"home"}');

        $this->cache->expects($this->once())
            ->method('save')
            ->with(
                '{"uid":"home"}',
                'prismic_doc_store_2_website_3_landing_page_home_en-us',
                [
                    CacheTypes::TAG_DOCUMENTS,
                    'PRISMICIO_DOC_ITEM_store_2_website_3_landing_page_home',
                ],
                86400
            );

        $this->cacheManager->set($document, 'landing_page', 'home', 'en-us', 2, 3);
    }

    public function testItReadsTheDocumentBackForTheSameStore(): void
    {
        $this->cacheState->method('isEnabled')->willReturn(true);
        $this->cookieManager->method('getCookie')->willReturn(null);

        $this->cache->expects($this->once())
            ->method('load')
            ->with('prismic_doc_store_2_website_3_landing_page_home_en-us')
            ->willReturn('{"uid":"home"}');

        // A document survives serialization as an array, it has to come back as an object
        $this->serializer->method('unserialize')->willReturn(['uid' => 'home']);

        $document = $this->cacheManager->get('landing_page', 'home', 'en-us', 2, 3);

        $this->assertInstanceOf(stdClass::class, $document);
        $this->assertSame('home', $document->uid);
    }

    public function testAMissIsNotADocument(): void
    {
        $this->cacheState->method('isEnabled')->willReturn(true);
        $this->cookieManager->method('getCookie')->willReturn(null);
        $this->cache->method('load')->willReturn(false);

        $this->assertNull($this->cacheManager->get('landing_page', 'home', 'nl-nl', 1, 1));
    }
}
