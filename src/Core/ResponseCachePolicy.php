<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Core;

use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * @internal
 */
final class ResponseCachePolicy
{
    public function allowsRequest(Request $request): bool
    {
        return $request->isMethodCacheable();
    }

    public function allowsResponse(Response $response): bool
    {
        if (!$response->isSuccessful()) {
            return false;
        }

        if (
            $response instanceof StreamedResponse
            || $response instanceof BinaryFileResponse
        ) {
            return false;
        }

        if ($response->headers->hasCacheControlDirective('no-store')) {
            return false;
        }

        if ($response->headers->has('Set-Cookie')) {
            return false;
        }

        return !$this->hasWildcardVary($response);
    }

    private function hasWildcardVary(Response $response): bool
    {
        foreach ($response->headers->all('Vary') as $value) {
            if ($value === null) {
                continue;
            }

            foreach (explode(',', $value) as $header) {
                if (trim($header) === '*') {
                    return true;
                }
            }
        }

        return false;
    }
}
