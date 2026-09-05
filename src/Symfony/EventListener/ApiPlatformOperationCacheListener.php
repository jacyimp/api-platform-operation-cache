<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformOperationCache\Symfony\EventListener;

use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use JacyImp\ApiPlatformOperationCache\Core\OperationCacheContext;
use JacyImp\ApiPlatformOperationCache\Core\OperationCacheHandler;
use JacyImp\ApiPlatformOperationCache\Core\OperationCacheInvalidator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * @internal
 */
final readonly class ApiPlatformOperationCacheListener
{
    private const CONTEXT_ATTRIBUTE = '_jacyimp_operation_cache_context';

    private const EXECUTED_OPERATION_ATTRIBUTE = '_jacyimp_operation_cache_executed_operation';

    public function __construct(
        private OperationCacheHandler $handler,
        private ?ResourceMetadataCollectionFactoryInterface $resourceMetadataCollectionFactory = null,
        private ?OperationCacheInvalidator $invalidator = null,
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

        $request->attributes->set(
            self::EXECUTED_OPERATION_ATTRIBUTE,
            $operation,
        );

        if ($lookup->context === null) {
            return;
        }

        $request->attributes->set(
            self::CONTEXT_ATTRIBUTE,
            $lookup->context,
        );
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $operation = $request->attributes->get(
            self::EXECUTED_OPERATION_ATTRIBUTE,
        );

        $request->attributes->remove(
            self::EXECUTED_OPERATION_ATTRIBUTE,
        );

        if (
            $operation instanceof HttpOperation
            && $this->invalidator !== null
        ) {
            $this->invalidator->invalidate(
                $operation,
                $request,
                $event->getResponse(),
            );
        }

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

        // @codeCoverageIgnoreStart
        if (!$operation instanceof HttpOperation) {
            return null;
        }
        // @codeCoverageIgnoreEnd

        $request->attributes->set(
            '_api_operation',
            $operation,
        );

        return $operation;
    }
}
