# Security

- Each device gets its own Nextcloud app password, revocable under
  *Settings → Security → Devices & sessions*. Logging out in the app revokes it.
- The password is stored encrypted on the device. Logging out deletes it and all cached lists.
- Every request checks your access to the list. Sharing follows your Nextcloud's sharing settings.
- Strict Content Security Policy, no third-party resources, no tracking.
- Rate and size limits against abuse.

# Security policy

## Reporting a vulnerability

Please **do not** open a public issue for security problems.

Report them privately through GitHub instead:
[Security → Report a vulnerability](https://github.com/kammarc/nextcloud-groceries/security/advisories/new).

Please include the Groceries and Nextcloud versions, the steps to reproduce, and what an attacker
could achieve. You will get an answer as soon as possible; this is a volunteer project, so please
allow some days.

## Supported versions

Only the latest release receives security fixes.
