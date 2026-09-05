<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Tests\Integration\Laravel\Fixture;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Patch;
use Closure;
use Illuminate\Http\Request;
use JacyImp\ApiPlatformOperationCache\Metadata\OperationCache;
use JacyImp\ApiPlatformOperationCache\Metadata\OperationCacheInvalidation;
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
        $routeId = $request->route('id');
        if (is_string($routeId)) {
            $request->attributes->set('id', $routeId);
        }

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

            'no-default-vary' => new OperationCache(
                ttl: 300,
                includeDefaultVary: false,
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

            default => new OperationCache(ttl: 300, groups: ['product:{id}'],),
        };

        if ($scenario === 'write' || $scenario === 'failed-write') {
            $request->attributes->set(
                '_api_operation',
                new Patch(
                    name: 'write_product_' . $scenario,
                    uriTemplate: '/cached-products/{id}',
                    extraProperties: [
                        new OperationCacheInvalidation(group: 'product:{id}'),
                    ],
                ),
            );

            return $next($request);
        }

        $request->attributes->set(
            '_api_operation',
            new Get(
                name: 'cached_product_' . $scenario,
                uriTemplate: '/cached-products/{id}',
                extraProperties: [
                    $cache,
                ],
            ),
        );

        return $next($request);
    }
}
