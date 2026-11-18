# Changelog

All notable changes to this package are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the package follows
[Semantic Versioning](https://semver.org/).

## [Unreleased]

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
