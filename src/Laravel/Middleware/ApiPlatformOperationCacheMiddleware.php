<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Laravel\Middleware;

use ApiPlatform\Metadata\HttpOperation;
use Closure;
use Illuminate\Http\Request;
use JacyImp\ApiPlatformOperationCache\Core\OperationCacheHandler;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
final readonly class ApiPlatformOperationCacheMiddleware
{
    public function __construct(
        private OperationCacheHandler $handler,
    ) {
    }

    /**
     * @param Closure(Request): Response $next
     */
    public function handle(
        Request $request,
        Closure $next,
    ): Response {
        $operation = $request->attributes->get(
            '_api_operation',
        );

        if (!$operation instanceof HttpOperation) {
            return $next($request);
        }

        $lookup = $this->handler->lookup(
            $operation,
            $request,
        );

        if ($lookup->response !== null) {
            return $lookup->response;
        }

        $response = $next($request);

        if ($lookup->context !== null) {
            $this->handler->store(
                $lookup->context,
                $request,
                $response,
            );
        }

        return $response;
    }
}
