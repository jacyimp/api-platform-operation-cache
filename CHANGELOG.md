# Changelog

All notable changes to `jacyimp/api-platform-operation-cache` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project follows [Semantic Versioning](https://semver.org/).

## [0.2.0] - 2026-09-05

### Added

* Static, interpolated, and runtime cache groups.
* Generation-based exact, prefix, and global invalidation without cache scans.
* Conditional and dynamic `OperationCacheInvalidation` metadata.
* Imperative invalidation through `CacheInvalidatorInterface`.
* Application default vary headers with `includeDefaultVary` opt-out.
* Immutable PSR-14 lifecycle events for cache hits, misses, stores, and group invalidations.

### Changed

* Symfony integration no longer requires API Platform's `use_symfony_listeners` option.

## [0.1.0] - 2026-09-05

### Added

* Operation-level response caching for API Platform.
* Symfony and Laravel integrations backed by a shared cache runtime.
* `OperationCache` metadata for enabling caching directly on individual API Platform operations.
* Explicit per-operation TTL configuration.
* Automatic cache variation by:

    * request headers;
    * authenticated user identity;
    * custom application-defined resolvers.
* Conditional caching through `CacheConditionInterface`.
* Custom authentication identity resolution through `AuthIdentityResolverInterface`.
* Custom cache variation through `VaryResolverInterface`.
* Response mutation hooks through `ResponseMutatorInterface`.
* Response header exclusion controls.
* Configurable default response header exclusions.
* Safe mandatory exclusion of transport-sensitive headers and `Set-Cookie`.
* Deterministic cache keys based on operation identity, host, HTTP method, path, query parameters, request format, and configured variation dimensions.
* Symfony cache storage through a configurable PSR-6 cache pool.
* Laravel cache storage through a configurable Laravel cache store.
* Symfony strategy autoconfiguration for cache conditions, variation resolvers, auth identity resolvers, and response mutators.
* Laravel automatic resolution of class-based cache strategies.
* Symfony and Laravel integration coverage proving that cache hits bypass downstream API Platform/application processing.

### Cache policy

Cached responses are currently limited to:

* cacheable HTTP request methods;
* successful responses;
* materialized non-streamed responses;
* responses without `Cache-Control: no-store`;
* responses without `Set-Cookie`;
* responses without `Vary: *`.

Streamed responses and binary file responses are not cached.
