# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

Finance and admin staff at the TOP group (PT TOP, CV MMP, CV SAE) who close the books monthly, and the owner who reviews the result. Desktop-first, repeated monthly, high accuracy.

## Product Purpose

Combine live EspoCRM sales and purchase data with figures that exist only outside the CRM — target, bank balances, inventory, operating costs, and production rolls — into one monthly report that can be reviewed and closed.

Success: the finance operator can fill the missing figures quickly, see the combined result, catch omissions before close, and freeze a month that matches the signed report.

## Positioning

It is not a generic CRM dashboard. It is the bridge between the CRM's order data and the group's monthly financial statement, with explicit entity mapping and a month-close audit trail.

## Operating Context

- Two EspoCRM instances: TOP and MMP/SLP.
- Three report entities: PT TOP, CV MMP, CV SAE.
- The report follows the March 2026 management report (bubblewrap, lakban, bank, inventory, costs, laba/rugi).
- Plain PHP 8.1+, SQLite, deployed on shared hosting. No build step.

## Capabilities and Constraints

- API-derived: sales orders, purchase orders, customer/supplier names, order amounts, dates.
- Manual: sales target, bank balances, five inventory buckets, five cost lines per entity, production rolls, adjustments, account/supplier entity mapping.
- Month close freezes API sales/purchases in `monthly_actuals`; reopening returns to live data.
- Entity mapping cannot be read from a structured CRM field; names and suffixes are fallbacks only.
- Production log data in EspoCRM is unreliable for the report period.

## Brand Commitments

No logo or brand guide was supplied. The interface uses a typographic wordmark and the company names as written. The visual world is the packing docket / warehouse ledger, chosen by the user.

## Evidence on Hand

- `docs/LAPORAN MARET 2026.pdf` — the signed March 2026 report used to verify totals.
- Live EspoCRM data for sales/purchase reconciliation.
- `tests/store_test.php` — store and mapping checks.

## Product Principles

- Accuracy before decoration. Every figure must be traceable to an API row or a manual entry.
- Density is a feature. Finance users need more rows per screen, not more whitespace.
- One signal color. Signal red marks selection, totals, and focus; everything else is ink and paper.
- Familiar affordances, specific character. Standard tables and forms, in the grammar of factory paperwork.
- Nothing closes silently. Missing inputs, fallback mappings, and frozen months are visible.

## Accessibility & Inclusion

WCAG AA contrast, keyboard-operable forms, visible focus, 44px touch targets on small screens, reduced-motion support, and a print stylesheet for the report.
