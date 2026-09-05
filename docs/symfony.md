[Back to README](../README.md)

# Symfony setup

Register the bundle if it is not registered automatically:

```php
// config/bundles.php

use JacyImp\ApiPlatformOperationCache\Symfony\ApiPlatformOperationCacheBundle;

return [
    // ...
    ApiPlatformOperationCacheBundle::class => ['all' => true],
];
```

The bundle registers its own Symfony HttpKernel listeners, so it does not
require API Platform's `use_symfony_listeners` option.

The package uses `cache.app` by default.

To select another PSR-6 cache pool:

```yaml
# config/packages/api_platform_operation_cache.yaml

api_platform_operation_cache:
    cache_pool: cache.api_platform
    vary_by_headers:
        - Accept-Language
        - X-Currency
```

With normal Symfony service autoconfiguration enabled, implementations of these interfaces are discovered automatically:

* `VaryResolverInterface`
* `AuthIdentityResolverInterface`
* `CacheConditionInterface`
* `ResponseMutatorInterface`
* `CacheGroupResolverInterface`
* `CacheInvalidationConditionInterface`
* `CacheInvalidationGroupResolverInterface`

For example:

```yaml
services:
    App\:
        resource: '../src/'
        autowire: true
        autoconfigure: true
```

No public services or package-specific tags are required for normal application classes.
