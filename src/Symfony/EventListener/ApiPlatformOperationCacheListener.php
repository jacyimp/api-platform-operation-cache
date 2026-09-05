<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Symfony\EventListener;

use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use JacyImp\ApiPlatformOperationCache\Core\OperationCacheContext;
use JacyImp\ApiPlatformOperationCache\Core\OperationCacheHandler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * @internal
 */
final readonly class ApiPlatformOperationCacheListener
{
    private const CONTEXT_ATTRIBUTE = '_jacyimp_operation_cache_context';

    public function __construct(
        private OperationCacheHandler $handler,
        private ?ResourceMetadataCollectionFactoryInterface $resourceMetadataCollectionFactory = null,
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $operation = $this->resolveOperation($request);

        if ($operation === null) {
            return;
        }

        $lookup = $this->handler->lookup(
            $operation,
            $request,
        );

        if ($lookup->response !== null) {
            $event->setResponse($lookup->response);

            return;
        }

        if ($lookup->context !== null) {
            $request->attributes->set(
                self::CONTEXT_ATTRIBUTE,
                $lookup->context,
            );
        }
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $context = $request->attributes->get(
            self::CONTEXT_ATTRIBUTE,
        );

        if (!$context instanceof OperationCacheContext) {
            return;
        }

        $request->attributes->remove(
            self::CONTEXT_ATTRIBUTE,
        );

        $this->handler->store(
            $context,
            $request,
            $event->getResponse(),
        );
    }

    private function resolveOperation(Request $request): ?HttpOperation
    {
        $operation = $request->attributes->get(
            '_api_operation',
        );

        if ($operation instanceof HttpOperation) {
            return $operation;
        }

        $resourceClass = $request->attributes->get(
            '_api_resource_class',
        );
        $operationName = $request->attributes->get(
            '_api_operation_name',
        );

        if (
            $this->resourceMetadataCollectionFactory === null
            || !is_string($resourceClass)
            || $resourceClass === ''
            || !is_string($operationName)
            || $operationName === ''
        ) {
            return null;
        }

        $operation = $this
            ->resourceMetadataCollectionFactory
            ->create($resourceClass)
            ->getOperation($operationName);

        if (!$operation instanceof HttpOperation) {
            return null;
        }

        $request->attributes->set(
            '_api_operation',
            $operation,
        );

        return $operation;
    }
}
