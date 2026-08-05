<?php declare(strict_types=1);

namespace Elgentos\PrismicIO\Plugin\PageCache;

use Elgentos\PrismicIO\Model\Api\State;
use Magento\Framework\App\PageCache\Kernel;
use Magento\Framework\App\Response\Http;

/**
 * A page that is missing Prismic content must not be stored in the full page cache.
 *
 * Without this the empty version of a page survives the outage: it stays in the cache until its
 * lifetime expires or someone flushes, so visitors keep seeing holes long after the repository
 * answers again.
 */
class SkipIncompleteResponse
{
    public function __construct(
        private readonly State $state
    ) {
    }

    /**
     * @param Kernel $subject
     * @param callable $proceed
     * @param Http $response
     * @return void
     */
    public function aroundProcess(Kernel $subject, callable $proceed, Http $response): void
    {
        if ($this->state->isContentUnavailable()) {
            return;
        }

        $proceed($response);
    }
}
