[Back to README](../README.md)

# Custom cache variation

Register these classes as services with [Symfony autoconfiguration](symfony.md) or resolve them through the [Laravel container](laravel.md).

## Use an account identity

You can replace the framework's normal authentication identity with an application-specific resolver:

```php
use JacyImp\ApiPlatformOperationCache\Contract\AuthIdentityResolverInterface;
use Symfony\Component\HttpFoundation\Request;

final class AccountIdentityResolver implements AuthIdentityResolverInterface
{
    public function resolve(Request $request): ?string
    {
        $account = $request->attributes->get('account');

        return $account === null ? null : (string) $account->id;
    }
}
```

Then reference it from the operation:

```php
new OperationCache(
    ttl: 300,
    varyByAuth: AccountIdentityResolver::class,
)
```

## Vary by tenant or application context

For tenant IDs, pricing contexts, feature sets, locales, or other application-specific dimensions, implement `VaryResolverInterface`:

```php
use JacyImp\ApiPlatformOperationCache\Contract\VaryResolverInterface;
use Symfony\Component\HttpFoundation\Request;

final class TenantVaryResolver implements VaryResolverInterface
{
    public function resolve(Request $request): string
    {
        return (string) $request->headers->get(
            'X-Tenant',
            'default',
        );
    }
}
```

Use it on the operation:

```php
new OperationCache(
    ttl: 300,
    varyByResolver: TenantVaryResolver::class,
)
```

A custom resolver receives the complete request and returns one stable cache-key fragment.

If several values are required, combine them inside the resolver:

```php
public function resolve(Request $request): string
{
    return sprintf(
        '%s:%s',
        $request->headers->get('X-Tenant', 'default'),
        $request->headers->get('X-Currency', 'USD'),
    );
}
```

`varyByHeaders`, `varyByAuth`, and `varyByResolver` are additive and can be used together.
