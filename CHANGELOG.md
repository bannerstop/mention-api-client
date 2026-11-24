# Changelog

All notable changes to this package are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the package follows
[Semantic Versioning](https://semver.org/).

## [1.1.0] - 2026-11-24

### Added

- Support for PHP 7.2.5 and 7.3 (PHP 7.4 to 8.5 as before). The library no longer uses typed properties or any other
  syntax newer than PHP 7.2; the public API and the behaviour do not change.
- A PHP target `7.2` for the generator: plain classes whose properties are typed by their `@var` docblock only.
  It is chosen when the lowest PHP of `require.php` in your `composer.json` is below 7.4, or with `--php=7.2`. The
  targets `7.4` and `8.2` write the same code as before.

### Changed

- `require.php` is `^7.2.5 || ^8.0`. The generator itself runs on PHP 7.2 to 8.5.
- A `composer.json` that requires a lowest PHP below 7.4 (for example `>=7.1`) now gets the `7.2` target instead of `7.4`; run the generator once more, `--check` reports the difference.
- The tests run on PHP 7.2 and 7.3 as well, with PHPUnit 8.5 up to 12.

## [1.0.0] - 2026-11-18

### Added

- `MentionApiClient` for the `/v2` interface: `run`, `listQueries`, `describeQuery`, `runRoutine` and `listRoutines`.
- Authentication with an API key or with OAuth 2.0 client credentials, with the access token cached in a PSR-16 cache
  and renewed shortly before it expires.
- One exception class per problem code, all below `MentionApiException`, with the request id on every result and exception.
- A timeout setting (30 seconds by default) for HTTP clients that implement `TimeoutAware`. A Guzzle 7 client passed to
  `MentionApiClient` gets it automatically (`GuzzleTimeoutClient`), and `SymfonyTimeoutClient` applies it to Symfony
  HttpClient. Guzzle and Symfony HttpClient stay optional.
- With client credentials, a call that the server answers with 401 drops the cached token, fetches a new one and is sent
  again once, for Queries and Routines. This does not use up the retries of a Query.
- Retries for Query calls (up to 2 times on 429, 503 and connection errors, honouring `Retry-After` up to 10 seconds);
  Routines are never retried.
- Support for PHP 7.4 to 8.5.
- `MentionApiClient::openApi()` fetches the OpenAPI document of your Consumer.
- `vendor/bin/mention-api-client generate` writes typed wrapper classes from that document: per Query a Parameters class,
  a Row class and a typed method, the same for Routines. The output is deterministic, readonly classes are written for
  apps on PHP 8.2 or higher and plain classes for older PHP, and `--check` fails when the committed classes no longer
  match the document.

[1.1.0]: https://github.com/bannerstop/mention-api-client/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/bannerstop/mention-api-client/releases/tag/v1.0.0
