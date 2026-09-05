[Back to README](../README.md)

# Cache behavior and options

## What gets cached?

Only `GET` and `HEAD` requests are considered.

Responses are stored only when all of the following are true:

* the response is successful;
* the response is not streamed;
* the response is not a binary file response;
* the response does not contain `Cache-Control: no-store`;
* the response does not set cookies;
* the response does not contain `Vary: *`.

Redirects, client errors, and server errors are therefore not cached by the current policy.

## Cache keys

Cache keys include the API Platform operation identity and request identity.

The key currently varies automatically by:

* resource class;
* operation name;
* URI template;
* scheme and host;
* HTTP method;
* request path;
* query parameters;
* request format.

Configured variation dimensions are then added through:

* `varyByHeaders`;
* `varyByAuth`;
* `varyByResolver`.

Associative query parameters are canonicalized before hashing, so equivalent query parameter ordering produces the same key.

## Expiration

Entries expire after `ttl` seconds. Resource writes do not invalidate them automatically; the package provides no tagging or invalidation API. Choose a TTL that matches how long stale responses are acceptable.

## All options

```php
new OperationCache(
    ttl: 300,
    varyByHeaders: [],
    varyByAuth: false,
    varyByResolver: null,
    when: null,
    excludeResponseHeaders: [],
    excludeDefaultResponseHeaders: true,
    responseMutator: null,
)
```

### `ttl`

Cache lifetime in seconds.

```php
new OperationCache(
    ttl: 600,
)
```

The value must be greater than zero.

Header names are case-insensitive. Empty names and duplicate declarations within either header list are rejected.
