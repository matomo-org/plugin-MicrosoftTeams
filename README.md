# Matomo Microsoft Teams plugin

Deliver the right information to stakeholders automatically, exactly when they need it. Save time with scheduled delivery to Microsoft Teams channels, email, or mobile instead of manual sharing. Try now - it’s free!

[![Build Status](https://github.com/matomo-org/plugin-MicrosoftTeams/actions/workflows/matomo-tests.yml/badge.svg)](https://github.com/matomo-org/plugin-MicrosoftTeams/actions/workflows/matomo-tests.yml)

## Description

Bring Matomo directly into Microsoft Teams, where decisions actually happen. Zero cost. Zero friction. Real insight.

The Matomo plugin for Microsoft Teams delivers key analytics directly into daily workflows to surface real user behaviour, conversion trends, and anomalies where decisions already happen.

Integrating with collaboration tools like Microsoft Teams reduces reporting delays, fragmented communication,  and keeps insights anchored in the conversations that drive action. Faster visibility, shared understanding across teams, and zero budget risk make installation a rational choice, not an experiment.

## How the Matomo Microsoft Teams Plugin Works

### Automate and Deliver Reports Anywhere
- Integrate directly with Microsoft Teams so your team can access analytics where they already collaborate.
- Schedule delivery daily, weekly, or monthly to suit your reporting cycles.
- Export reports as PDF, CSV or TSV for different use cases.

### Seamless integration with Matomo
- Works across all standard and premium Matomo reports.
- Supports key Matomo features such as [Segments](https://matomo.org/faq/reporting-tools/about-segments-in-matomo/), [Goals](https://matomo.org/faq/reports/create-a-goal-in-matomo/), [Ecommerce](https://matomo.org/faq/reports/set-up-ecommerce-tracking-in-matomo/), and [Funnels](https://matomo.org/faq/reports/create-and-manage-funnels/).

### Managing Features
- Set up Matomo custom alerts and receive notifications in Microsoft Teams.
- Automate delivery of any Matomo report.
- Share reports via Microsoft Teams integration.
- Choose from multiple export formats (PDF, CSV or TSV).
- Schedule recurring reports daily, weekly, or monthly.
- Integrates seamlessly with the Matomo platform

## Get the Matomo Microsoft Teams Plugin Today for Free

Automate the way your organisation shares analytics. With the Microsoft Teams plugin, your insights will always reach the right people at the right time without manual effort.

## Installation

Install it via Matomo Marketplace

## FAQ

__Why are my Microsoft Teams messages not delivered on an install that uses an outgoing proxy?__

Matomo sends the webhook request over its SSRF safe fetch path, which resolves the webhook host itself
and connects to the address it validated. That path needs the curl PHP extension and cannot go through
a proxy, so on an install that only reaches the internet through a proxy configured in the `[proxy]`
section of `config/config.ini.php` every Teams message is refused. Such a refusal is written to the
Matomo log as `MicrosoftTeams webhook request to <host> was refused`. Sending Teams messages through a
proxy is not supported at the moment.

__Why is my webhook URL rejected as invalid?__

The webhook host has to be a name that resolves to a public address, because the plugin must not be
able to make Matomo call itself or another host on your network. An IP address, an encoded address such
as `127.1` or `0x7f000001`, `localhost` and the host Matomo itself is reached on are all rejected while
the report or alert is being saved.

__Can I use a Teams webhook that is served on a private address?__

Yes, if you allow that address explicitly. Add it, or the range it is in, to
`allowed_private_egress_ranges` in the `[General]` section of `config/config.ini.php`. The webhook still
has to be a named host, so an address written directly in the webhook URL stays rejected.

## License

GPL v3 or later