[Back to README](../README.md)

# Customize cached responses

The package stores:

* response content;
* HTTP status code;
* cacheable response headers.

Transport-specific headers are not persisted.

### Excluding response headers

Application-specific headers can be removed from the cached representation:

```php
new OperationCache(
    ttl: 300,
    excludeResponseHeaders: [
        'X-Request-Id',
        'X-Trace-Id',
    ],
)
```

These headers may still be present on the original cache-miss response. They are simply not stored for later cache hits.

### Default exclusions

By default, the package does not persist:

* `Age`
* `Date`

You can disable those default exclusions:

```php
new OperationCache(
    ttl: 300,
    excludeDefaultResponseHeaders: false,
)
```

Some headers remain permanently excluded regardless of this setting:

* `Connection`
* `Content-Length`
* `Keep-Alive`
* `Proxy-Authenticate`
* `Proxy-Authorization`
* `Set-Cookie`
* `TE`
* `Trailer`
* `Transfer-Encoding`
* `Upgrade`
* headers named by the `Connection` header

`Set-Cookie` cannot be opted back into cached responses.

## Response mutation

For advanced response handling, implement `ResponseMutatorInterface`:

```php
use JacyImp\ApiPlatformOperationCache\Contract\ResponseMutatorInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class ProductResponseMutator implements ResponseMutatorInterface
{
    public function whenCaching(
        Response $response,
        Request $request,
    ): Response {
        $response->headers->set(
            'X-Cached-Representation',
            'yes',
        );

        return $response;
    }

    public function whenServingCachedResponse(
        Response $response,
        Request $request,
    ): Response {
        $response->headers->set(
            'X-Cache-Hit',
            'yes',
        );

        return $response;
    }
}
```

Then:

```php
new OperationCache(
    ttl: 300,
    responseMutator: ProductResponseMutator::class,
)
```

`whenCaching()` receives a clone of the live response.

Changes made there affect only the cached representation. The original cache-miss response returned to the client is not modified.

`whenServingCachedResponse()` runs after a cached response has been reconstructed.

The lifecycle is therefore:

```text
application response
        |
        +---------------------------> client
        |
      clone
        |
  whenCaching()
        |
  header filtering
        |
      cache
        |
   future hit
        |
whenServingCachedResponse()
        |
      client
```

Header exclusion is applied after `whenCaching()`, so mandatory and configured exclusions remain authoritative even when a mutator adds headers.

Register response mutators through [Symfony autoconfiguration](symfony.md) or the [Laravel container](laravel.md).
