# Security Policy

## Supported versions

| Version | Supported |
| --- | --- |
| 1.x | Yes |

## Reporting a vulnerability

Please do not open a public issue for security problems. Report them privately
to **tanvirbappy7706@gmail.com** with:

- a description of the vulnerability,
- steps to reproduce it,
- the package, Laravel and PHP versions you are using,
- any suggested fix.

You will receive an acknowledgement as soon as possible. Once the issue is
confirmed, a fix is prepared and released, and the reporter is credited unless
they prefer otherwise.

## Credentials

- API keys, secret keys and webhook bearer tokens are read from environment
  variables through the config file.
- The package never logs request headers or bodies.
- Webhook tokens are compared with `hash_equals()`.
- Do not commit `.env` files or credentials to your repository.
