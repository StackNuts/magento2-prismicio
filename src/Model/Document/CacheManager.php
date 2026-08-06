<?php
/**
 * Copyright Elgentos BV. All rights reserved.
 * https://www.elgentos.nl/
 */
declare(strict_types=1);

namespace Elgentos\PrismicIO\Model\Document;

use Elgentos\PrismicIO\Model\CacheTypes;
use Exception;
use Magento\Framework\App\Cache\StateInterface;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Prismic\Api as PrismicApi;
use stdClass;

class CacheManager
{
    private const CACHE_KEY_PATTERN = 'prismic_doc_store_%s_website_%s_%s_%s_%s';
    private const CACHE_TAG_ITEM_PATTERN = 'PRISMICIO_DOC_ITEM_store_%s_website_%s_%s_%s';

    public function __construct(
        private readonly CacheInterface $cache,
        private readonly SerializerInterface $serializer,
        private readonly StateInterface $cacheState,
        private readonly CookieManagerInterface $cookieManager,
        private readonly array $defaultConfig = []
    ) {
    }

    public function get(
        string $type,
        string $uid,
        string $lang,
        int $storeId,
        int $websiteId
    ): mixed {
        if (!$this->isEnabled()) {
            return null;
        }

        try {
            $key = $this->buildKey($type, $uid, $lang, $storeId, $websiteId);
            $cached = $this->cache->load($key);

            if ($cached === false) {
                return null;
            }

            $unserialized = $this->serializer->unserialize($cached);

            // Convert array back to stdClass if needed (from JSON serialization)
            if (is_array($unserialized)) {
                $unserialized = json_decode(json_encode($unserialized));
            }

            return $unserialized;
        } catch (Exception $e) {

            return null;
        }
    }

    public function set(
        StdClass $document,
        string $type,
        string $uid,
        string $lang,
        int $storeId,
        int $websiteId
    ): void {
        if (!$this->isEnabled()) {
            return;
        }

        try {
            $key = $this->buildKey(
                $type,
                $uid,
                $lang,
                $storeId,
                $websiteId
            );

            $tags = $this->buildTags($type, $uid, $storeId, $websiteId);
            $ttl = (int)($this->defaultConfig['ttl'] ?? 86400); // Default 1 day

            /** @var array|bool|float|int|null|string $document */
            $serialized = $this->serializer->serialize($document);
            $this->cache->save(
                $serialized,
                $key,
                $tags,
                $ttl
            );
        } catch (Exception) {
        }
    }

    /**
     * Documents are only cached for regular visitors.
     *
     * In a preview the SDK resolves a ref from the preview cookie, which the cache key does not
     * know about, so caching would show an editor the published document instead of the preview.
     */
    private function isEnabled(): bool
    {
        if (!$this->cacheState->isEnabled(CacheTypes::TYPE_DOCUMENTS)) {
            return false;
        }

        return !$this->hasPreviewCookie();
    }

    private function hasPreviewCookie(): bool
    {
        foreach ([str_replace(['.', ' '], '_', PrismicApi::PREVIEW_COOKIE), PrismicApi::PREVIEW_COOKIE] as $name) {
            if ($this->cookieManager->getCookie($name) !== null) {
                return true;
            }
        }

        return false;
    }

    private function buildKey(
        string $type,
        string $uid,
        string $lang,
        int $storeId,
        int $websiteId
    ): string {
        return sprintf(self::CACHE_KEY_PATTERN, $storeId, $websiteId, $type, $uid, $lang);
    }

    private function buildTags(string $type, string $uid, int $storeId, int $websiteId): array
    {
        return [
            CacheTypes::TAG_DOCUMENTS,
            $this->buildItemTag($type, $uid, $storeId, $websiteId),
        ];
    }

    private function buildItemTag(string $type, string $uid, int $storeId, int $websiteId): string
    {
        return sprintf(self::CACHE_TAG_ITEM_PATTERN, $storeId, $websiteId, $type, $uid);
    }
}
