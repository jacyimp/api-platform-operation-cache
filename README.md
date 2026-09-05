# API Platform Operation Cache

[![CI](https://github.com/jacyimp/api-platform-operation-cache/actions/workflows/ci.yml/badge.svg)](https://github.com/jacyimp/api-platform-operation-cache/actions/workflows/ci.yml)
[![Coverage](https://img.shields.io/badge/coverage-100%25-brightgreen)](https://github.com/jacyimp/api-platform-operation-cache)
[![Infection MSI](https://img.shields.io/badge/Infection%20MSI-100%25-brightgreen)](https://github.com/jacyimp/api-platform-operation-cache/actions/workflows/ci.yml)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%20max-brightgreen)](https://phpstan.org/)

Cache API Platform `Get` and `GetCollection` responses in Symfony or Laravel. Cache hits skip provider/controller processing. Caching is opt-in per operation.

## Usage

Add `OperationCache` to the operations you want to cache:

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
    // Keep your existing write and custom operations here.
])]
final class Product
{
    // Your resource fields...
}
```

`ttl` is required and must be a positive number of seconds. Matching requests reuse the stored response until expiry. Query parameters give pages and filters separate entries. For user-specific content, [vary by authenticated identity](#cache-user-specific-responses).

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

The bundle registers its own Symfony HttpKernel listeners and does not require
API Platform's `use_symfony_listeners` option. It uses `cache.app` by default.
[Choose a cache pool or configure services →](docs/symfony.md)

### Laravel

Package discovery adds the cache middleware to API Platform's default middleware configuration. It uses Laravel's default cache store.

[Choose another cache store →](docs/laravel.md)

## Invalidate after writes

Assign groups to cached reads and invalidate them on successful writes:

```php
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use JacyImp\ApiPlatformOperationCache\Metadata\OperationCache;
use JacyImp\ApiPlatformOperationCache\Metadata\OperationCacheInvalidation;

#[ApiResource(operations: [
    new Get(extraProperties: [
        OperationCache::class => new OperationCache(ttl: 300, groups: ['product:{id}']),
    ]),
    new GetCollection(extraProperties: [
        OperationCache::class => new OperationCache(ttl: 60, groups: ['products']),
    ]),
    new Post(extraProperties: [
        new OperationCacheInvalidation(group: 'products'),
    ]),
    new Patch(extraProperties: [
        new OperationCacheInvalidation(group: 'product:{id}'),
        new OperationCacheInvalidation(group: 'products'),
    ]),
    new Delete(extraProperties: [
        new OperationCacheInvalidation(group: 'product:{id}'),
        new OperationCacheInvalidation(group: 'products'),
    ]),
])]
final class Product
{
    // Your existing resource fields and persistence mapping...
}
```

In this example, `Post` invalidates cached product collections; `Patch` and `Delete` also invalidate the product's detail response. Failed operations leave caches intact. The next matching read rebuilds the response.

`{id}` uses the operation's URI variable name. Apply the same rules to `Put` and custom writes as needed. Writes outside these operations require explicit invalidation.

[Advanced groups, conditional invalidation, and invalidating from application code](docs/cache-groups.md)

## Vary by headers

The cache key already includes the operation, host, method, path, query parameters, and request format. Add headers that change your response:

```php
new OperationCache(
    ttl: 300,
    varyByHeaders: ['Accept-Language', 'X-Currency'],
)
```

Default vary headers are empty. Operations inherit configured defaults unless `includeDefaultVary: false` is set. Use `varyByAuth` for authenticated identity.

## Cache user-specific responses

`varyByAuth` defaults to `false`. Enable it for responses that depend on the authenticated user:

```php
new OperationCache(
    ttl: 300,
    varyByAuth: true,
)
```

Each authenticated identity gets separate entries; anonymous requests share an anonymous identity. Symfony uses `getUserIdentifier()` (requires Symfony Security); Laravel uses `getAuthIdentifier()`.

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
