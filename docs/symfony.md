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

API Platform must use its Symfony listener integration:

```yaml
# config/packages/api_platform.yaml

api_platform:
    use_symfony_listeners: true
```

The package uses `cache.app` by default.

To select another PSR-6 cache pool:

```yaml
# config/packages/api_platform_operation_cache.yaml

api_platform_operation_cache:
    cache_pool: cache.api_platform
```

With normal Symfony service autoconfiguration enabled, implementations of these interfaces are discovered automatically:

* `VaryResolverInterface`
* `AuthIdentityResolverInterface`
* `CacheConditionInterface`
* `ResponseMutatorInterface`

For example:

```yaml
services:
    App\:
        resource: '../src/'
        autowire: true
        autoconfigure: true
```

No public services or package-specific tags are required for normal application classes.
