<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Core;

use ApiPlatform\Metadata\HttpOperation;
use JacyImp\ApiPlatformOperationCache\ApiPlatform\OperationCacheMetadataExtractor;
use JacyImp\ApiPlatformOperationCache\Contract\CacheStoreInterface;
use JacyImp\ApiPlatformOperationCache\Event\CacheHitEvent;
use JacyImp\ApiPlatformOperationCache\Event\CacheMissEvent;
use JacyImp\ApiPlatformOperationCache\Event\CacheStoredEvent;
use JacyImp\ApiPlatformOperationCache\Http\CachedResponseFactory;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
final readonly class OperationCacheHandler
{
    public function __construct(
        private OperationCacheMetadataExtractor $metadataExtractor,
        private ResponseCachePolicy $cachePolicy,
        private CacheKeyGenerator $keyGenerator,
        private CacheStoreInterface $cacheStore,
        private CachedResponseFactory $responseFactory,
        private EventDispatcherInterface $eventDispatcher = new NullEventDispatcher(),
    ) {
    }

    public function lookup(
        HttpOperation $operation,
        Request $request,
    ): OperationCacheLookup {
        $cache = $this->metadataExtractor->extract($operation);

        if ($cache === null) {
            return OperationCacheLookup::bypass();
        }

        if (!$this->cachePolicy->allowsRequest($request)) {
            return OperationCacheLookup::bypass();
        }

        $key = $this->keyGenerator->generate(
            $operation,
            $request,
            $cache,
        );

        if ($key === null) {
            return OperationCacheLookup::bypass();
        }

        $context = new OperationCacheContext(
            operation: $operation,
            cache: $cache,
            key: $key,
        );

        $cached = $this->cacheStore->get($key);

        if ($cached === null) {
            $this->eventDispatcher->dispatch(new CacheMissEvent($operation, $request));

            return OperationCacheLookup::miss($context);
        }

        $response = $this->responseFactory->restore(
            $cached,
            $request,
            $cache,
        );
        $this->eventDispatcher->dispatch(new CacheHitEvent($operation, $request, $response));

        return OperationCacheLookup::hit($response);
    }

    public function store(
        OperationCacheContext $context,
        Request $request,
        Response $response,
    ): void {
        if (!$this->cachePolicy->allowsResponse($response)) {
            return;
        }

        $cached = $this->responseFactory->capture(
            $response,
            $request,
            $context->cache,
        );

        $this->cacheStore->put(
            $context->key,
            $cached,
            $context->cache->ttl,
        );

        $this->eventDispatcher->dispatch(new CacheStoredEvent(
            $context->operation,
            $request,
            $response,
            $context->cache->ttl,
        ));
    }
}
