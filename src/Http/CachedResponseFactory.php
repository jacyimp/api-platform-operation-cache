<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Http;

use JacyImp\ApiPlatformOperationCache\Core\CachedResponse;
use LogicException;
use Symfony\Component\HttpFoundation\Response;

final class CachedResponseFactory
{
    /**
     * Headers that are request-specific, transport-specific, or must be
     * regenerated when the cached response is replayed.
     *
     * @var list<string>
     */
    private const EXCLUDED_HEADERS = [
        'age',
        'connection',
        'content-length',
        'date',
        'keep-alive',
        'proxy-authenticate',
        'proxy-authorization',
        'set-cookie',
        'te',
        'trailer',
        'transfer-encoding',
        'upgrade',
    ];

    public function capture(Response $response): CachedResponse
    {
        $content = $response->getContent();

        if ($content === false) {
            throw new LogicException(
                'Cannot cache a response without materialized content.',
            );
        }

        return new CachedResponse(
            content: $content,
            statusCode: $response->getStatusCode(),
            headers: $this->cacheableHeaders($response),
        );
    }

    public function restore(CachedResponse $cached): Response
    {
        return new Response(
            content: $cached->content,
            status: $cached->statusCode,
            headers: $cached->headers,
        );
    }

    /**
     * @return array<string, list<string>>
     */
    private function cacheableHeaders(Response $response): array
    {
        $excluded = array_fill_keys(
            self::EXCLUDED_HEADERS,
            true,
        );

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
            $normalizedName = strtolower($name);

            if (isset($excluded[$normalizedName])) {
                continue;
            }

            $headers[$normalizedName] = $values;
        }

        ksort($headers);

        return $headers;
    }
}
