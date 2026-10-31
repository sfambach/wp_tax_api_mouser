# Taxmod Mouser

Mouser connection for the [Taxonomy Modeller](https://github.com/sfambach/wp-taxonomy-modeler) (WordPress plugin).

**Status 0.1.0 (needs modeller 0.1.81 and [Taxmod Mail](https://github.com/sfambach/wp_tax_mail) 0.6.0 or later):**

- **Mail:** reads order confirmations, shipment notices and cancellations from the mailbox of Taxmod Mail and hands them to the modeller's inbox.
- **Search API** (D-1027 E6): with the API key from My Mouser › APIs (tab «Mouser», stored encrypted in the modeller's vault,
  or `TAXMOUSER_API_KEY` in wp-config.php) the purchasing page «Einkauf» of
  [Taxmod Stock](https://github.com/sfambach/wp_tax_stock) 0.4.2+ shows Mouser offers per position — Mouser number, maker,
  stock, the price of the break the quantity reaches — through the filter `taxmod_supplier_offers`. A search is kept for
  an hour. The tab has a test search.
- **Not yet:** cart and order history (same key, waiting for Mouser to unlock them).

Every shop connection is a module of its own, whether it reads mails or an API; Taxmod Mail only fetches and files
the mails (D-1006, [concept](https://github.com/sfambach/wp-taxonomy-modeler/blob/main/docs/pakete/import/module-schnitt.md)).
The reader moved here from Taxmod Mail unchanged.

## Setup

1. Install and set up Taxmod Mail (mailbox access).
2. Activate this module. *Taxonomy Modeller → Configuration → Mouser* shows under «Mail» which mails it reads.
3. For the search, enter the API key under «API» there and try the test search.

## Development

`composer install`, then `vendor/bin/phpunit` (core suite, no WordPress). The sample mails in `tests/Core/fixtures`
are synthetic.

## License

Copyright (C) 2026 Stefan Fambach (SteFam), https://www.fambach.net — [PolyForm Noncommercial 1.0.0](LICENSE.md).
Commercial use needs the owner's prior consent.

## Authors

- **Stefan Fambach** (SteFam) – idea and concept, review and testing – https://www.fambach.net
- **Claude** (Anthropic AI) – implementation, via Claude Code

## AI disclosure

This project is built with AI assistance. Stefan Fambach had the idea, wrote the concept and reviews and tests every change; the implementation (code and documentation) is written largely by Claude (Anthropic) via Claude Code.
