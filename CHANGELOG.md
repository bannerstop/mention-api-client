# Changelog

All notable changes to this package are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the package follows
[Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added

- `MentionApiClient` for the `/v2` interface: `run`, `listQueries`, `describeQuery`, `runRoutine` and `listRoutines`.
- Authentication with an API key or with Keycloak client credentials, with the access token cached in a PSR-16 cache
  and renewed shortly before it expires.
- One exception class per problem code, all below `MentionApiException`, with the request id on every result and exception.
- A timeout setting (30 seconds by default) for HTTP clients that implement `TimeoutAware`.
- Retries for Query calls (up to 2 times on 429, 503 and connection errors, honouring `Retry-After` up to 10 seconds);
  Routines are never retried.
- Support for PHP 7.4 to 8.5.
