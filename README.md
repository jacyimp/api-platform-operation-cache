# API Platform Operation Cache

[![CI](https://github.com/jacyimp/api-platform-operation-cache/actions/workflows/ci.yml/badge.svg)](https://github.com/jacyimp/api-platform-operation-cache/actions/workflows/ci.yml)
[![Coverage](https://img.shields.io/badge/coverage-100%25-brightgreen)](https://github.com/jacyimp/api-platform-operation-cache)
[![Infection MSI](https://img.shields.io/badge/Infection%20MSI-100%25-brightgreen)](https://github.com/jacyimp/api-platform-operation-cache/actions/workflows/ci.yml)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%20max-brightgreen)](https://phpstan.org/)

Cache individual API Platform Get/GetCollection HTTP operations in Symfony or Laravel. Cache hits serve the stored response and skip downstream provider/controller processing. Operations without `OperationCache` run normally.

## Cache Product detail and collection responses

For an existing `Product` resource, add `OperationCache` to the read operations you want to cache. Keep your existing fields, persistence mapping, and other operations.

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

The first cacheable response is stored; matching requests reuse it until expiry. `ttl` is required, in seconds, and must be greater than zero.

With the default resource routes, `/products/42` is cached for five minutes and `/products` for one minute. Query parameters are part of the cache key, so collection pages and filters get separate entries automatically.

If expiry is enough for your data, this is all the operation metadata you need. For user-specific content, [separate entries by authenticated identity](#cache-user-specific-responses).

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

Composer package discovery registers the provider, which adds the cache middleware to API Platform's default middleware configuration. Uses Laravel's default cache store.

[Choose another cache store →](docs/laravel.md)

## Refresh Product caches after writes

When changes should appear before the TTL expires, assign groups to your cached reads and invalidate them on successful writes. This example uses `products` for collection responses and `product:{id}` for each detail response:

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

Creating a product invalidates cached collections, including their pages and filters. Updating or deleting a product also invalidates its detail response. Failed operations do not invalidate caches. The next matching read rebuilds the response.

`{id}` comes from the operation's URI variables; use your own variable name if it differs. If your resource exposes `Put`, add the same invalidation rules as `Patch`. Add these rules to custom write operations too, such as a publish action. Writes outside these operations need explicit invalidation.

[Advanced groups, conditional invalidation, and invalidating from application code](docs/cache-groups.md)

## Separate responses by language or currency

The cache key already includes the operation, host, method, path, query parameters, and request format. Add headers that change your response:

```php
new OperationCache(
    ttl: 300,
    varyByHeaders: ['Accept-Language', 'X-Currency'],
)
```

Application configuration may provide default vary headers. Operations include them unless `includeDefaultVary: false` is set. The built-in list is empty; configure `Accept-Language` only when responses are localized, and use `varyByAuth` instead of the raw `Authorization` header.

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
