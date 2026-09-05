<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Http;

use JacyImp\ApiPlatformOperationCache\Core\CachedResponse;
use JacyImp\ApiPlatformOperationCache\Core\CacheStrategyRegistry;
use JacyImp\ApiPlatformOperationCache\Metadata\OperationCache;
use LogicException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class CachedResponseFactory
{
    /**
     * These are never persisted. They are transport-specific or may become
     * invalid when a cached response is reconstructed or mutated.
     *
     * @var list<string>
     */
    private const MANDATORY_EXCLUDED_HEADERS = [
        'connection',
        'content-length',
        'keep-alive',
        'proxy-authenticate',
        'proxy-authorization',
        'te',
        'trailer',
        'transfer-encoding',
        'upgrade',
    ];

    /**
     * Safe defaults that userland may explicitly opt out of.
     *
     * @var list<string>
     */
    private const DEFAULT_EXCLUDED_HEADERS = [
        'age',
        'date',
        'set-cookie',
    ];

    public function __construct(
        private CacheStrategyRegistry $strategyRegistry,
    ) {
    }

    public function capture(
        Response $response,
        Request $request,
        OperationCache $cache,
    ): CachedResponse {
        $response = $this->whenCaching(
            clone $response,
            $request,
            $cache,
        );

        $content = $response->getContent();

        if ($content === false) {
            throw new LogicException(
                'Cannot cache a response without materialized content.',
            );
        }

        return new CachedResponse(
            content: $content,
            statusCode: $response->getStatusCode(),
            headers: $this->cacheableHeaders(
                $response,
                $cache,
            ),
        );
    }

    public function restore(
        CachedResponse $cached,
        Request $request,
        OperationCache $cache,
    ): Response {
        $response = new Response(
            content: $cached->content,
            status: $cached->statusCode,
            headers: $cached->headers,
        );

        if ($cache->responseMutator === null) {
            return $response;
        }

        return $this->strategyRegistry
            ->responseMutator($cache->responseMutator)
            ->whenServingCachedResponse(
                $response,
                $request,
            );
    }

    private function whenCaching(
        Response $response,
        Request $request,
        OperationCache $cache,
    ): Response {
        if ($cache->responseMutator === null) {
            return $response;
        }

        return $this->strategyRegistry
            ->responseMutator($cache->responseMutator)
            ->whenCaching(
                $response,
                $request,
            );
    }

    /**
     * @return array<string, list<string>>
     */
    private function cacheableHeaders(
        Response $response,
        OperationCache $cache,
    ): array {
        $excluded = array_fill_keys(
            self::MANDATORY_EXCLUDED_HEADERS,
            true,
        );

        if ($cache->excludeDefaultResponseHeaders) {
            foreach (self::DEFAULT_EXCLUDED_HEADERS as $header) {
                $excluded[$header] = true;
            }
        }

        foreach ($cache->excludeResponseHeaders as $header) {
            $header = strtolower(trim($header));

            if ($header !== '') {
                $excluded[$header] = true;
            }
        }

        foreach ($response->headers->all('connection') as $value) {
            foreach (explode(',', $value) as $header) {
                $header = strtolower(trim($header));

                if ($header !== '') {
                    $excluded[$header] = true;
                }
            }
        }

        $headers = [];

        foreach ($response->headers->all() as $name => $values) {
            $name = strtolower($name);

            if (isset($excluded[$name])) {
                continue;
            }

            $headers[$name] = $values;
        }

        ksort($headers);

        return $headers;
    }
}
