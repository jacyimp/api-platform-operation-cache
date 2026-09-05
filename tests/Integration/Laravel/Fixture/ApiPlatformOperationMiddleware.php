<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Tests\Integration\Laravel\Fixture;

use ApiPlatform\Metadata\Get;
use Closure;
use Illuminate\Http\Request;
use JacyImp\ApiPlatformOperationCache\Metadata\OperationCache;
use JacyImp\ApiPlatformOperationCache\Tests\Fixture\ResponseBehaviorMutator;
use Symfony\Component\HttpFoundation\Response;

final class ApiPlatformOperationMiddleware
{
    /**
     * @param Closure(Request): Response $next
     */
    public function handle(
        Request $request,
        Closure $next,
        string $scenario = 'plain',
    ): Response {
        $cache = match ($scenario) {
            'condition' => new OperationCache(
                ttl: 300,
                when: NeverCacheCondition::class,
            ),

            'header' => new OperationCache(
                ttl: 300,
                varyByHeaders: [
                    'Accept-Language',
                ],
            ),

            'auth' => new OperationCache(
                ttl: 300,
                varyByAuth: RequestHeaderAuthIdentityResolver::class,
            ),

            'resolver' => new OperationCache(
                ttl: 300,
                varyByResolver: TenantVaryResolver::class,
            ),

            'response' => new OperationCache(
                ttl: 300,
                responseMutator: ResponseBehaviorMutator::class,
            ),

            'response-exclusion' => new OperationCache(
                ttl: 300,
                excludeResponseHeaders: [
                    'X-Excluded',
                ],
                responseMutator: ResponseBehaviorMutator::class,
            ),

            'response-defaults' => new OperationCache(
                ttl: 300,
                excludeDefaultResponseHeaders: false,
                responseMutator: ResponseBehaviorMutator::class,
            ),

            default => new OperationCache(
                ttl: 300,
            ),
        };

        $request->attributes->set(
            '_api_operation',
            new Get(
                name: 'cached_product_' . $scenario,
                uriTemplate: '/cached-products/{id}',
                extraProperties: [
                    OperationCache::class => $cache,
                ],
            ),
        );

        return $next($request);
    }
}
