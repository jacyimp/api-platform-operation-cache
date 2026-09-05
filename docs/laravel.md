[Back to README](../README.md)

# Laravel setup

The Laravel service provider is registered through Composer package discovery.

No package configuration is required when using Laravel's default cache store.

To publish the configuration:

```bash
php artisan vendor:publish --tag=api-platform-operation-cache-config
```

The published configuration contains:

```php
return [
    'store' => null,
];
```

`null` uses Laravel's default cache store.

To use another configured Laravel store:

```php
return [
    'store' => 'redis',
];
```

The package automatically adds its middleware to API Platform's default Laravel middleware configuration.

Concrete condition, auth, variation, and response-mutator classes can normally be referenced directly from `OperationCache`; Laravel resolves them through its service container.
