<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Core;

use ApiPlatform\Metadata\HttpOperation;
use JacyImp\ApiPlatformOperationCache\Metadata\VaryBy;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
final readonly class CacheKeyGenerator
{
    private const VERSION = 1;

    public function __construct(
        private VaryByEvaluator $varyByEvaluator,
    ) {
    }

    /**
     * @param list<VaryBy> $varyBy
     */
    public function generate(
        HttpOperation $operation,
        Request $request,
        array $varyBy = [],
    ): string {
        $payload = [
            'version' => self::VERSION,
            'operation' => [
                'class' => $operation->getClass(),
                'name' => $operation->getName(),
                'uriTemplate' => $operation->getUriTemplate(),
            ],
            'request' => [
                'host' => $request->getSchemeAndHttpHost(),
                'method' => $request->getMethod(),
                'path' => $request->getPathInfo(),
                'query' => $this->canonicalize($request->query->all()),
                'format' => $request->getRequestFormat(),
            ],
            'vary' => $this->varyByEvaluator->evaluate(
                $request,
                $varyBy,
            ),
        ];

        return sprintf(
            'api_platform_operation_cache.v%d.%s',
            self::VERSION,
            hash(
                'sha256',
                json_encode($payload, JSON_THROW_ON_ERROR),
            ),
        );
    }

    /**
     * @return mixed
     */
    private function canonicalize(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(
                fn (mixed $item): mixed => $this->canonicalize($item),
                $value,
            );
        }

        ksort($value, SORT_STRING);

        foreach ($value as $key => $item) {
            $value[$key] = $this->canonicalize($item);
        }

        return $value;
    }
}
