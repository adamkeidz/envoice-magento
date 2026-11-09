# envoice LHDN e-Invoice for Magento

Files LHDN MyInvois e-invoices from Magento Open Source and Adobe Commerce orders through
[envoice](https://envoice.my). Paid orders are filed as individual e-invoices or pooled into the
monthly consolidated e-invoice, and credit memos become credit notes.

Free module, MIT licence. You need an envoice account; the Free plan is enough to start.

Supports Magento 2.4.7 to 2.4.9 on PHP 8.2 to 8.4.

## Install with Composer

```sh
composer require envoice/module-einvoice
bin/magento module:enable Envoice_Einvoice
bin/magento setup:upgrade
bin/magento cache:flush
```

In production mode, also run `bin/magento setup:di:compile` and `bin/magento setup:static-content:deploy`.

### From the downloaded zip, as a Composer artifact

1. Put `envoice-magento-<version>.zip` in a folder on the server, for example `artifacts/`.
2. `composer config repositories.envoice artifact ./artifacts`
3. `composer require envoice/module-einvoice`, then the `bin/magento` steps above.

### From the downloaded zip, into app/code

1. Unzip into `app/code/Envoice/Einvoice` (so `registration.php` sits at `app/code/Envoice/Einvoice/registration.php`).
2. Run the `bin/magento` steps above.

## Connect your store

1. Go to Stores > Configuration > envoice > LHDN e-Invoice.
2. Under Connection, each store URL is listed with its store views. Click Connect to envoice.
3. Sign in to envoice in the window that opens and pick the shop. The row turns Connected.

Each store URL pairs with its own envoice shop, so a store view with its own domain (or with store
codes in URLs) can file under a different business. Store views that share one URL share one pairing.

## Settings

The Automation group can be set per website or store view:

- Auto-submit paid orders, as an individual e-invoice or into the monthly consolidated e-invoice.
  Orders with a buyer TIN are always filed individually.
- Skip orders not in MYR, and a minimum order total.
- Credit notes for credit memos.
- Buyer TIN from VAT number. Set Customers > Customer Configuration > Create New Account Options >
  Show VAT Number on Storefront to Yes and buyers can enter their TIN at checkout.
- BRN or IC field at checkout (on by default). An optional field above the payment methods for the
  buyer's business registration number or IC. LHDN checks a company TIN against its BRN, so a
  business buyer who fills in both the VAT number and this field is filed without any edits on the
  order page. Orders where it is left empty pool into the consolidated e-invoice as before.

## How filing runs

Nothing calls envoice during checkout or while you save an invoice. A paid invoice or a credit memo
adds a row to the `envoice_queue` table, and Magento cron sends it within a minute. A failed call is
retried with growing gaps (1 minute up to a day). Magento cron must be running
(`bin/magento cron:install`).

Each order page has an LHDN e-Invoice section under Order & Account Information: the status, the
LHDN UUID, the MyInvois validation link, the receipt, buttons to submit, add to consolidated or
retry, credit note status per credit memo, the BRN or IC from checkout, and a form for the buyer's
TIN and ID number, prefilled from checkout. Look up TIN asks LHDN for the TIN of the ID in the form
and saves it, ready for Submit or Retry.

A validated e-invoice can be cancelled on MyInvois from the same section within 72 hours of
validation, with a reason. After 72 hours, or once a credit note is filed against it, refund with
a credit memo instead.

## Uninstall

```sh
bin/magento module:disable Envoice_Einvoice
composer remove envoice/module-einvoice
bin/magento setup:upgrade
```

The module's tables (`envoice_connection`, `envoice_document`, `envoice_credit_note`,
`envoice_queue`) and the `envoice_buyer_reg_no` column on `quote` and `sales_order` stay until you
drop them. Your e-invoices stay in envoice.

## Support

support@envoice.my. Privacy: https://envoice.my/en/privacy
