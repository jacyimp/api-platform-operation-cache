[Back to README](../README.md)

# Cache only matching requests

Use `when` when an operation should only participate in caching for certain requests.

Implement `CacheConditionInterface`:

```php
use JacyImp\ApiPlatformOperationCache\Contract\CacheConditionInterface;
use Symfony\Component\HttpFoundation\Request;

final class CachePublicProducts implements CacheConditionInterface
{
    public function matches(Request $request): bool
    {
        return !$request->query->getBoolean('preview');
    }
}
```

Then:

```php
new OperationCache(
    ttl: 300,
    when: CachePublicProducts::class,
)
```

When the condition returns `false`, both cache lookup and cache storage are skipped.

Without `when`, the operation always participates in caching subject to the normal request and response cacheability rules.

Register the condition through [Symfony autoconfiguration](symfony.md) or the [Laravel container](laravel.md).
