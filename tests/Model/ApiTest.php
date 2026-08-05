<?php declare(strict_types=1);

namespace Elgentos\PrismicIO\Model;

use Elgentos\PrismicIO\Api\ConfigurationInterface;
use Elgentos\PrismicIO\Exception\ApiUnavailableException;
use Elgentos\PrismicIO\Model\Api\CacheProxy;
use Elgentos\PrismicIO\Model\Api\State;
use Elgentos\PrismicIO\Model\Document\CacheManager;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use stdClass;

/**
 * The document cache is what keeps a store readable while the repository is unreachable, so these
 * tests pin down that a lookup is served from cache without touching the API, and that a failing
 * lookup degrades to "no document" instead of an error.
 */
class ApiTest extends TestCase
{
    private ConfigurationInterface&MockObject $configuration;

    private StoreManagerInterface&MockObject $storeManager;

    private CacheManager&MockObject $cacheManager;

    private State&MockObject $state;

    private Api $api;

    protected function setUp(): void
    {
        $this->configuration = $this->createMock(ConfigurationInterface::class);
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->cacheManager = $this->createMock(CacheManager::class);
        $this->state = $this->createMock(State::class);

        $store = $this->createMock(StoreInterface::class);
        $store->method('getId')->willReturn(2);
        $store->method('getWebsiteId')->willReturn(3);
        $this->storeManager->method('getStore')->willReturn($store);

        $this->configuration->method('getApiEnabled')->willReturn(true);
        $this->configuration->method('getContentLanguage')->willReturn('en-us');
        $this->configuration->method('getContentType')->willReturn('landing_page');
        $this->configuration->method('getApiEndpoint')->willReturn('https://example.cdn.prismic.io/api/v2');

        $this->api = new Api(
            $this->configuration,
            $this->storeManager,
            $this->createMock(CacheProxy::class),
            $this->createMock(LoggerInterface::class),
            $this->cacheManager,
            $this->state
        );
    }

    public function testADocumentByUidComesFromTheCacheWithoutCallingTheApi(): void
    {
        $cached = new stdClass();
        $cached->uid = 'home';

        // Would throw the moment the API is touched
        $this->state->method('isUnavailable')->willReturn(true);

        $this->cacheManager->expects($this->once())
            ->method('get')
            ->with('landing_page', 'home', 'en-us', 2, 3)
            ->willReturn($cached);
        $this->cacheManager->expects($this->never())->method('set');

        $this->assertSame($cached, $this->api->getDocumentByUid('home'));
    }

    public function testASingletonIsCachedUnderItsContentType(): void
    {
        $cached = new stdClass();

        $this->state->method('isUnavailable')->willReturn(true);
        $this->cacheManager->expects($this->once())
            ->method('get')
            ->with('global_footer', 'global_footer', 'en-us', 2, 3)
            ->willReturn($cached);

        $this->assertSame($cached, $this->api->getSingleton('global_footer'));
    }

    public function testADocumentByIdIsCachedSeparatelyFromDocumentsByUid(): void
    {
        $cached = new stdClass();

        $this->state->method('isUnavailable')->willReturn(true);
        $this->cacheManager->expects($this->once())
            ->method('get')
            ->with('by_id', 'XyZ123', 'en-us', 2, 3)
            ->willReturn($cached);

        $this->assertSame($cached, $this->api->getDocumentById('XyZ123'));
    }

    public function testTheApiIsNotCalledAgainWhileItIsKnownToBeUnreachable(): void
    {
        $this->state->method('isUnavailable')->willReturn(true);

        $this->expectException(ApiUnavailableException::class);

        $this->api->create();
    }

    /**
     * No cached document and no reachable repository has to mean "not found", never an error page
     */
    public function testAnUnreachableRepositoryLeavesTheDocumentMissingInsteadOfThrowing(): void
    {
        $this->state->method('isUnavailable')->willReturn(true);
        $this->cacheManager->method('get')->willReturn(null);
        $this->cacheManager->expects($this->never())->method('set');

        $this->state->expects($this->exactly(3))->method('markContentUnavailable');

        $this->assertNull($this->api->getDocumentByUid('home'));
        $this->assertNull($this->api->getSingleton('global_footer'));
        $this->assertNull($this->api->getDocumentById('XyZ123'));
    }
}
