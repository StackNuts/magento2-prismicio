<?php declare(strict_types=1);

namespace Elgentos\PrismicIO\Plugin\Response;

use Elgentos\PrismicIO\Model\Api\State;
use Magento\Framework\App\Response\Http;

/**
 * Keep Varnish and browsers from storing a page that is missing Prismic content, for the same
 * reason the built in full page cache skips it.
 */
class NoStoreForIncompleteResponse
{
    public function __construct(
        private readonly State $state
    ) {
    }

    /**
     * @param Http $subject
     * @return void
     */
    public function beforeSendResponse(Http $subject): void
    {
        if (!$this->state->isContentUnavailable()) {
            return;
        }

        $subject->setNoCacheHeaders();
    }
}
