<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Tests\Integration\Laravel\Fixture;

use ApiPlatform\Metadata\Get;
use Closure;
use Illuminate\Http\Request;
use JacyImp\ApiPlatformOperationCache\Metadata\OperationCache;
use Symfony\Component\HttpFoundation\Response;

final class ApiPlatformOperationMiddleware
{
    /**
     * @param Closure(Request): Response $next
     */
    public function handle(
        Request $request,
        Closure $next,
    ): Response {
        $request->attributes->set(
            '_api_operation',
            new Get(
                name: 'cached_product',
                uriTemplate: '/cached-products/{id}',
                extraProperties: [
                    OperationCache::class => new OperationCache(
                        ttl: 300,
                    ),
                ],
            ),
        );

        return $next($request);
    }
}
