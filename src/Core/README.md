# src/Core

The Mouser mail reader (`MouserMailParser`): recognises Mouser's mails and reads order confirmations, shipment notices and cancellations into an order proposal.
Plain PHP 8.2, **no WordPress call**; it implements the modeller's contract `Taxmod\Core\Import\Mail\OrderMailParser`.
Concept: [`docs/pakete/import/module-schnitt.md`](https://github.com/sfambach/wp-taxonomy-modeler/blob/main/docs/pakete/import/module-schnitt.md).
