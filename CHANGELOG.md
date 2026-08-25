## Changelog

6.0.1 - 2026-08-31
- Send Microsoft Teams webhook messages over Matomo's SSRF safe fetch path, so the webhook host must resolve to a public address, the connection is pinned to the validated address and every redirect is revalidated. This request now needs curl and cannot be routed through a configured outgoing proxy, and private ranges have to be allowed with the `[General] allowed_private_egress_ranges` setting.
- Rejected further webhook URLs that point at the Matomo installation itself, such as encoded address literals (`127.1`, `0x7f000001`) and a host written in another case, with a trailing dot or in an internationalised form.

6.0.0 - 2026-08-10
- Compatibility with Matomo 6

5.2.0 - 2026-07-20
- Encrypted Microsoft Teams system settings and added a migration for existing plaintext values.
- Added code to harden the URL check for Microsoft Teams

5.1.0 - 2025-05-25
- Replaced inline Microsoft Teams report expiry note with emails notices for client secret expiry.

5.0.6 - 2025-04-27
- Added code to harden the URL check for Microsoft Teams

5.0.5 - 2026-03-30
- Fixed exception when module, action and idsite is not defined when setting js variables

5.0.4 - 2026-03-30
- Added code to add notification if using deprecated Microsoft Teams webhook URL and also send an email via migration

5.0.3 - 2026-03-26
- Added code to ensure workflow webhook URL works as expected, as webhook URL is deprecated

5.0.2 - 2026-02-16
- Added code to disallow I/P as host in Microsoft Teams webhook URL

5.0.1 - 2026-01-09
- New release for plugin to show up correctly on marketplace

5.0.0 - 2026-01-05
- Initial release to send scheduled reports and Custom Alerts to a Microsoft Teams channel 
