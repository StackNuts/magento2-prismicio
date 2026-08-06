<?php declare(strict_types=1);

namespace Elgentos\PrismicIO\Model\Api;

use Elgentos\PrismicIO\Model\CacheTypes;
use Magento\Framework\App\CacheInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class StateTest extends TestCase
{
    private CacheInterface&MockObject $cache;

    private \Psr\Log\LoggerInterface&MockObject $logger;

    private State $state;

    protected function setUp(): void
    {
        $this->cache = $this->createMock(CacheInterface::class);
        $this->logger = $this->createMock(\Psr\Log\LoggerInterface::class);

        $this->state = new State($this->cache, $this->logger);
    }

    public function testItIsAvailableUntilSomethingFails(): void
    {
        $this->cache->method('load')->willReturn(false);

        $this->assertFalse($this->state->isUnavailable());
    }

    public function testItPicksUpAFailureFromAnEarlierRequest(): void
    {
        $this->cache->expects($this->once())
            ->method('load')
            ->with('PRISMICIO_API_UNAVAILABLE')
            ->willReturn('1');

        $this->assertTrue($this->state->isUnavailable());
        // The answer is kept for the rest of the request, one cache read is enough
        $this->assertTrue($this->state->isUnavailable());
    }

    public function testAFailureIsRememberedBrieflySoTheApiIsNotCalledAgainEveryTime(): void
    {
        $this->cache->expects($this->once())
            ->method('save')
            ->with('1', 'PRISMICIO_API_UNAVAILABLE', [CacheTypes::TAG_DOCUMENTS], 60);

        $this->state->markUnavailable(new RuntimeException('403 Forbidden'));

        $this->assertTrue($this->state->isUnavailable());
    }

    public function testAnOutageIsLoggedOncePerRequestInsteadOfOncePerDocument(): void
    {
        $this->logger->expects($this->once())
            ->method('warning')
            ->with($this->stringContains('403 Forbidden'));

        $this->state->markUnavailable(new RuntimeException('403 Forbidden'));
        $this->state->markUnavailable(new RuntimeException('403 Forbidden'));
        $this->state->markUnavailable(new RuntimeException('403 Forbidden'));
    }

    public function testAResponseIsCompleteUnlessADocumentWentMissing(): void
    {
        $this->assertFalse($this->state->isContentUnavailable());

        $this->state->markContentUnavailable();

        $this->assertTrue($this->state->isContentUnavailable());
    }
}
