[Back to README](../README.md)

# Cache groups and invalidation

Add concrete memberships to a cached operation:

```php
new OperationCache(
    ttl: 300,
    groups: ['products', 'product:{id}'],
    groupResolver: ProductCacheGroupResolver::class,
)
```

URI variables come from request attributes and values are URI-encoded. Missing, empty, invalid, or unresolved values raise `InvalidCacheGroupException`. Memberships cannot contain `*`. `CacheGroupResolverInterface::resolve(Request)` returns additional concrete groups; all results are deduplicated and sorted.

One `OperationCacheInvalidation` is one rule. Numeric `extraProperties` entries allow several rules on custom operations:

```php
new Post(
    uriTemplate: '/products/{id}/publish',
    extraProperties: [
        new OperationCacheInvalidation(group: 'product:{id}'),
        new OperationCacheInvalidation(group: 'products', when: CollectionChanged::class),
        new OperationCacheInvalidation(groupResolver: AffectedProductGroups::class),
    ],
)
```

Conditions implement `CacheInvalidationConditionInterface` and receive the request and successful response. Dynamic resolvers implement `CacheInvalidationGroupResolverInterface` and receive the request, response, and operation. Static and dynamic targets are additive. Failed operations and cache hits do not invalidate.

Targets may be exact (`product:42`), terminal namespace wildcards (`product:*`, `vendor:12:*`), or global `*`. Arbitrary globs are rejected.

For `vendor:12:product:42`, a response depends on `*`, `vendor:*`, `vendor:12:*`, `vendor:12:product:*`, and the exact group. Invalidation changes one generation record in O(1). It never scans or enumerates response keys; unreachable responses expire with their original TTL.

Inject `CacheInvalidatorInterface` in Symfony or resolve it from Laravel:

```php
$invalidator->invalidateGroups(['product:42', 'products']);
```
