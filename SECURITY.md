# Security policy

## Reporting a vulnerability

Please do not open a public issue for security problems. Report them privately,
either by e-mail to [dev@bannerstop.com](mailto:dev@bannerstop.com) or through
GitHub: open the **Security** tab of this repository and choose **Report a
vulnerability**. We will get back to you and keep you posted until a fix is
released.

## Supported versions

There is one major version for all supported PHP versions (see the README). Security
fixes go into the latest minor release of the current major version.

## What the library guarantees

- The API key, the client secret and access tokens are never written to exception
  messages, and the configuration object hides them from `var_dump()`.
- Names of Queries and Routines are checked before they become part of a URL, so
  a name cannot change the path of a call.
- Routines, which write data, are never retried automatically.
- The access token of client credentials is cached under a key derived from the token
  URL, the client id and the scope, and it is renewed before it expires.

## What you have to do

- Call mention-api and the token endpoint over HTTPS only.
- Keep the API key and the client secret out of version control.
- Use a PSR-16 cache that only your application can read, because it holds access tokens.
- Set a timeout on your HTTP client.
