# Jify Taxes

> Taiwan-oriented tax calculation controls for WooCommerce stores.

![License: GPLv2+](https://img.shields.io/badge/License-GPLv2%2B-blue.svg)
![WordPress 5.8+](https://img.shields.io/badge/WordPress-5.8%2B-21759b)
![PHP 7.4+](https://img.shields.io/badge/PHP-7.4%2B-777bb4)
![Stable](https://img.shields.io/badge/stable-2.4.0-brightgreen)

Jify Taxes provides product-level tax controls for WooCommerce stores that need Taiwan-specific checkout behavior, including configurable tax rates, tax-base adjustments, discount-aware calculations, shipping allocation, and localized presentation.

> This project is an engineering implementation, not legal or accounting advice. Stores should confirm tax treatment with a qualified professional before production use.

## The Problem

WooCommerce's global tax engine is flexible, but some stores need a narrower workflow that is easier to reason about for Taiwan operations. Common requirements include:

- Applying a configured tax rate only to selected products or variations.
- Calculating tax after item-level discounts.
- Optionally including shipping in the taxable basis.
- Avoiding duplicate output from WooCommerce's native tax rows.
- Presenting a localized tax label in the cart and checkout.

Jify Taxes implements that workflow as an explicit WooCommerce extension.

## Key Features

- **Product-level opt-in** — enable Jify tax behavior only for selected products or variations.
- **Configurable rate** — default to 5%, while allowing a per-product or per-variation rate.
- **Discount-aware basis** — optionally deduct item-level discount amounts before calculating tax.
- **Shipping allocation** — optionally distribute shipping across taxable line items by line-value proportion.
- **Per-item calculation** — compute the taxable basis from each eligible cart line.
- **Rounded final amount** — round the aggregate tax fee before it is added to the cart.
- **Native-tax suppression** — disable duplicate WooCommerce tax rendering when Jify tax is active.
- **Localized presentation** — show the resulting charge as `稅金` in checkout totals.

## Calculation Model

For each eligible cart item:

```text
basis = line total

if discount deduction is enabled:
    basis -= item discount

if shipping inclusion is enabled:
    basis += allocated shipping

item tax = basis × configured rate
```

The plugin sums the item-level values, rounds the result, and adds it to the WooCommerce cart as a fee.

### Example

```text
Taxable product subtotal: NT$1,000
Item discount:            NT$100
Allocated shipping:       NT$50
Configured rate:          5%

Tax basis: 1,000 - 100 + 50 = NT$950
Calculated tax: 950 × 5% = NT$47.5
Rounded cart fee: NT$48
```

The exact business and accounting treatment must be reviewed for the store's actual transaction model.

## Workflow

```text
Store manager enables Jify Taxes on a product or variation
        ↓
Customer adds eligible items to the cart
        ↓
Plugin suppresses native WooCommerce tax for the Jify path
        ↓
Plugin reads line totals and optional discount data
        ↓
Optional shipping amount is allocated proportionally
        ↓
Tax is calculated per eligible item
        ↓
Aggregate amount is rounded and added as a cart fee
```

## Architecture

The current plugin integrates with:

- WooCommerce product-data tabs.
- Product and variation metadata.
- Cart calculation hooks.
- Session data produced by the Jify Discount workflow.
- WooCommerce tax-status filters.
- Cart tax-total filters.
- Frontend cleanup for native tax rows.

```text
Configuration
├── enable flag
├── tax rate
├── include shipping
└── deduct discount

Runtime calculation
├── eligible-item detection
├── item discount lookup
├── shipping allocation
├── item-level tax calculation
└── aggregate rounding

Presentation
├── native tax suppression
└── localized cart fee
```

## Installation

1. Upload the plugin to `/wp-content/plugins/jify-taxes`.
2. Activate **Jify Taxes** from the WordPress Plugins screen.
3. Open a WooCommerce product or variation.
4. Enable the Jify tax option and configure its rate and basis adjustments.
5. Test representative carts before production rollout.

## Verification Checklist

Before enabling the plugin on a live store, verify:

- Eligible and ineligible products are separated correctly.
- Product variations inherit or override the intended configuration.
- Discounts reduce the taxable basis only when configured.
- Shipping is included only when configured.
- Mixed carts produce the expected aggregate tax.
- The cart does not display duplicate native WooCommerce tax rows.
- Rounding matches the store's accounting process.
- Refunds, partial refunds, coupons, and order edits are reviewed manually.
- The result has been checked by the store's accountant or tax advisor.

## Current Status

- Stable version: `2.4.0`.
- The implementation supports per-item tax after discount and optional shipping adjustments.
- It is best treated as a region-specific compatibility layer that requires store-level acceptance testing.
- The current implementation is compact and centralized; future maintenance can separate configuration, calculation, and presentation concerns.

## Known Limitations

- This repository does not provide legal, tax, invoice, or accounting certification.
- The current code does not implement a complete electronic-invoice issuance system.
- Checkout themes and third-party plugins may re-render tax rows differently.
- The plugin uses a cart fee to represent the calculated amount rather than WooCommerce's full native tax subsystem.
- Compatibility with refunds, subscriptions, multi-currency, and external accounting systems requires dedicated testing.
- Item discount integration depends on the expected session data being available.

## Contributor Opportunities

Useful contribution areas include:

- Extracting the calculation engine into a pure, testable component.
- Adding fixtures for coupons, shipping, mixed rates, and rounding boundaries.
- Improving compatibility with order edits and refunds.
- Adding import and export for product-level tax settings.
- Expanding localization and documentation.
- Building a documented compatibility matrix for WooCommerce versions and checkout implementations.

## Related Projects

- [Jify Cloud Website](https://github.com/yves01480/jify-cloud-website)
- [Jify Shipping](https://github.com/yves01480/jify-shipping)
- [Jify Discount](https://github.com/yves01480/jify-discount)
- [Jify Loyalty](https://github.com/yves01480/jify-loyalty)

Full product documentation and demos: <https://jify.cloud>

The canonical WordPress plugin metadata lives in [`readme.txt`](readme.txt).

## Ownership

I designed and implemented the product-level configuration, WooCommerce integration, discount-aware tax basis, shipping allocation, localized checkout behavior, and release packaging.

AI coding tools are used in parts of the development workflow. Product decisions, architecture, integration, review, deployment, and acceptance criteria remain my responsibility.

## License

GPL-2.0-or-later — see [`license.txt`](license.txt).
