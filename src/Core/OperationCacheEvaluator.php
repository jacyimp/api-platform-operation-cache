<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Core;

use JacyImp\ApiPlatformOperationCache\Contract\AuthIdentityResolverInterface;
use JacyImp\ApiPlatformOperationCache\Metadata\OperationCache;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
final readonly class OperationCacheEvaluator
{
    /** @var list<string> */
    private array $defaultVaryByHeaders;
/**
     * @param list<string> $defaultVaryByHeaders
     */
    public function __construct(
        private AuthIdentityResolverInterface $defaultAuthIdentityResolver,
        private CacheStrategyRegistry $strategyRegistry,
        array $defaultVaryByHeaders = [],
    ) {
        $headers = [];
        foreach ($defaultVaryByHeaders as $header) {
            $normalized = strtolower(trim($header));
            if ($normalized === '') {
                throw new \InvalidArgumentException('Default vary-by header cannot be empty.',);
            }

            $headers[$normalized] = $normalized;
        }

        $this->defaultVaryByHeaders = array_values($headers);
    }

    /**
     * @return array<string, string>|null
     */
    public function evaluate(
        OperationCache $cache,
        Request $request,
    ): ?array {
        if (!$this->conditionMatches($cache, $request)) {
            return null;
        }

        $variation = $this->resolveHeaders($cache, $request);

        $this->resolveAuth($cache, $request, $variation);
        $this->resolveCustomVariation($cache, $request, $variation);

        ksort($variation);

        return $variation;
    }

    private function conditionMatches(
        OperationCache $cache,
        Request $request,
    ): bool {
        if ($cache->when === null) {
            return true;
        }

        return $this->strategyRegistry
            ->condition($cache->when)
            ->matches($request);
    }

    /**
     * @return array<string, string>
     */
    private function resolveHeaders(
        OperationCache $cache,
        Request $request,
    ): array {
        $variation = [];

        $headers = $cache->includeDefaultVary
            ? [...$this->defaultVaryByHeaders, ...$cache->varyByHeaders]
            : $cache->varyByHeaders;
        foreach ($headers as $header) {
            $normalized = strtolower(trim($header));
            $variation[sprintf('header:%s', $normalized)] = json_encode(
                $request->headers->all($header),
                JSON_THROW_ON_ERROR,
            );
        }

        return $variation;
    }

    /**
     * @param array<string, string> $variation
     */
    private function resolveAuth(
        OperationCache $cache,
        Request $request,
        array &$variation,
    ): void {
        if ($cache->varyByAuth === false) {
            return;
        }

        $resolver = $cache->varyByAuth === true
            ? $this->defaultAuthIdentityResolver
            : $this->strategyRegistry->authIdentityResolver($cache->varyByAuth);

        $identity = $resolver->resolve($request);

        $variation['auth'] = $identity === null
            ? 'anonymous'
            : sprintf('user:%s', $identity);
    }

    /**
     * @param array<string, string> $variation
     */
    private function resolveCustomVariation(
        OperationCache $cache,
        Request $request,
        array &$variation,
    ): void {
        if ($cache->varyByResolver === null) {
            return;
        }

        $variation[sprintf(
            'resolver:%s',
            $cache->varyByResolver,
        )] = $this->strategyRegistry
            ->varyResolver($cache->varyByResolver)
            ->resolve($request);
    }
}
