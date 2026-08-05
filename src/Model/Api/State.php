<?php declare(strict_types=1);

namespace Elgentos\PrismicIO\Model\Api;

use Elgentos\PrismicIO\Model\CacheTypes;
use Magento\Framework\App\CacheInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Remembers that the repository is unreachable, so a single outage does not cost every request a
 * failing HTTP call per document, and so the responses it produced are not cached as if they were
 * complete.
 */
class State
{
    private const CACHE_ID = 'PRISMICIO_API_UNAVAILABLE';

    /**
     * Seconds to skip the API after a failure. Short on purpose: the next request after this window
     * probes the API again, so recovery needs no intervention.
     */
    private const BACKOFF = 60;

    private ?bool $unavailable = null;

    private bool $contentUnavailable = false;

    private bool $logged = false;

    public function __construct(
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Did a request to the repository fail recently?
     */
    public function isUnavailable(): bool
    {
        if ($this->unavailable === null) {
            $this->unavailable = (bool) $this->cache->load(self::CACHE_ID);
        }

        return $this->unavailable;
    }

    /**
     * Register that the repository could not be reached
     *
     * @param Throwable $exception
     * @return void
     */
    public function markUnavailable(Throwable $exception): void
    {
        $this->unavailable = true;
        $this->cache->save('1', self::CACHE_ID, [CacheTypes::TAG_DOCUMENTS], self::BACKOFF);

        if ($this->logged) {
            return;
        }

        // One line per request instead of one per document
        $this->logged = true;
        $this->logger->warning(
            'Prismic API is unavailable, serving cached content where possible: ' . $exception->getMessage()
        );
    }

    /**
     * Register that a document could not be delivered, so the response is incomplete
     *
     * @return void
     */
    public function markContentUnavailable(): void
    {
        $this->contentUnavailable = true;
    }

    /**
     * Was a document missing while building this response?
     */
    public function isContentUnavailable(): bool
    {
        return $this->contentUnavailable;
    }
}
