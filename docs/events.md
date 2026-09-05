# Cache lifecycle events

The package dispatches immutable PSR-14 events after cache actions succeed:

* `CacheHitEvent` after a cached response is reconstructed and before it is returned;
* `CacheMissEvent` after a cacheable request lookup finds no entry;
* `CacheStoredEvent` after a response is written to the cache;
* `CacheGroupsInvalidatedEvent` after all normalized, deduplicated group generations are changed.

Bypassed requests do not produce hit or miss events. Responses rejected by the cache policy do not produce stored events, and failed writes do not produce invalidation events. Group generation writes are sequential, so an adapter failure can leave earlier targets changed; the success event is emitted only when the complete batch succeeds. The events are observational; cache behavior remains configured through the package's explicit metadata and contracts.

## Symfony

Symfony uses the application's existing event dispatcher. Register a normal listener or subscriber:

```php
use JacyImp\ApiPlatformOperationCache\Event\CacheHitEvent;
use JacyImp\ApiPlatformOperationCache\Event\CacheStoredEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

final class CacheMetricsListener
{
    #[AsEventListener]
    public function onHit(CacheHitEvent $event): void
    {
        // Record a hit using $event->operation and $event->request.
    }

    #[AsEventListener]
    public function onStored(CacheStoredEvent $event): void
    {
        // Record a store using $event->response and $event->ttl.
    }
}
```

## Laravel

The package forwards PSR-14 events to Laravel's existing event dispatcher, so ordinary Laravel listener registration works:

```php
use Illuminate\Support\Facades\Event;
use JacyImp\ApiPlatformOperationCache\Event\CacheGroupsInvalidatedEvent;

Event::listen(
    CacheGroupsInvalidatedEvent::class,
    function (CacheGroupsInvalidatedEvent $event): void {
        // Audit $event->groups.
    },
);
```
