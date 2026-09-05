# API Platform Operation Cache

Cache individual API Platform HTTP operations in Symfony or Laravel. Cache hits serve the stored response and skip downstream provider/controller processing. Operations without `OperationCache` run normally.

## Install

```bash
composer require jacyimp/api-platform-operation-cache
```

Requires PHP `^8.2`, API Platform Metadata `^3.4 || ^4.0`, and Symfony components `^6.4 || ^7.0 || ^8.0`. Laravel apps also need `api-platform/laravel`.

### Symfony

Register the bundle in `config/bundles.php`:

```php
use JacyImp\ApiPlatformOperationCache\Symfony\ApiPlatformOperationCacheBundle;

return [
    // Existing bundles...
    ApiPlatformOperationCacheBundle::class => ['all' => true],
];
```

Enable API Platform's Symfony listeners:

```yaml
# config/packages/api_platform.yaml
api_platform:
    use_symfony_listeners: true
```

Uses `cache.app` by default. [Choose a cache pool or configure services →](docs/symfony.md)

### Laravel

Composer package discovery registers the provider, which adds the cache middleware to API Platform's default middleware configuration. Uses Laravel's default cache store.

[Choose another cache store →](docs/laravel.md)

## Cache an operation

```php
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use JacyImp\ApiPlatformOperationCache\Metadata\OperationCache;

#[ApiResource(operations: [
    new Get(extraProperties: [
        OperationCache::class => new OperationCache(ttl: 300),
    ]),
    new GetCollection(extraProperties: [
        OperationCache::class => new OperationCache(ttl: 60),
    ]),
])]
final class Product
{
    // Your resource fields...
}
```

The first cacheable response is stored; matching requests reuse it until expiry. `ttl` is required, in seconds, and must be greater than zero. Resource writes do not automatically invalidate cached responses.

## Separate responses by language or currency

The cache key already includes the operation, host, method, path, query parameters, and request format. Add headers that change your response:

```php
new OperationCache(
    ttl: 300,
    varyByHeaders: ['Accept-Language', 'X-Currency'],
)
```

## Cache user-specific responses

Enable `varyByAuth` for responses that depend on the authenticated user:

```php
new OperationCache(
    ttl: 300,
    varyByAuth: true,
    varyByHeaders: ['Accept-Language'],
)
```

Each authenticated identity gets separate entries; anonymous requests share an anonymous identity. Symfony uses `getUserIdentifier()` (requires Symfony Security); Laravel uses `getAuthIdentifier()`.

`varyByAuth` defaults to `false`, so configure it or an equivalent custom variation for user-specific content.

[Vary by tenant, account, or custom context →](docs/custom-variation.md)

## Customize caching

| Use case | Guide |
| --- | --- |
| Skip caching for previews or other requests | [Conditional caching](docs/conditional-caching.md) |
| Exclude request IDs or trace headers | [Response headers](docs/response-handling.md#excluding-response-headers) |
| Modify the stored response or add headers on cache hits | [Response mutators](docs/response-handling.md#response-mutation) |
| Inspect cache keys, exclusions, and option defaults | [Cache behavior and options](docs/cache-behavior.md) |

Only successful `GET`/`HEAD` responses are stored. Streams, binary files, and responses with `no-store`, `Set-Cookie`, or `Vary: *` are skipped.

## Development

```bash
composer check
```

Runs Composer validation, coding standards, static analysis, and tests.

## License

[MIT](LICENSE).
