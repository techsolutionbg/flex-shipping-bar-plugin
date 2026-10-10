# Flex Free Shipping Bar

A top bar for Flex Commerce that shows how much is left until free shipping and sets shipping to zero once the cart subtotal reaches the threshold.

Version: **0.1.0** · ID: `flex/shipping-bar` · Minimum Flex CMS version: **0.1.56**.

GitHub repository: `techsolutionbg/flex-shipping-bar-plugin`.

## Requirements and installation

Requires an installed Flex CMS with PHP 8.3+, its database and Extension API V1. **Flex Commerce** must be installed and active before this plugin; installation and activation fail otherwise. No npm build is required.

Place the package in `plugins/flex/shipping-bar/`, with `plugin.json` directly inside that folder. From the Flex CMS root:

```sh
php bin/flex plugin:install flex/shipping-bar
php bin/flex plugin:activate flex/shipping-bar
```

With Docker, prefix the commands with `docker compose exec app`. Installation creates the `flex_shipping_bar_settings` table and takes the starting threshold from the existing Flex Commerce **Free shipping over** setting.

## What it adds

- A settings page **Free shipping** in the shop settings section of the administration.
- A bar at the top of every public page, outside `/admin`.
- A `commerce.checkout.quote` filter that sets shipping to zero once the subtotal reaches the threshold.

The bar has three states:

| State | When | Default message |
| --- | --- | --- |
| `empty` | The cart is empty | Free shipping on orders over `{threshold}` |
| `progress` | The subtotal is below the threshold | `{remaining}` left until free shipping |
| `reached` | The subtotal reaches the threshold | This order ships for free |

The default messages are in Bulgarian and can be changed. Messages accept the placeholders `{threshold}`, `{remaining}` and `{subtotal}`, formatted in the store currency (`EUR`, `BGN` or `USD`). A progress meter follows the subtotal.

The bar updates when Flex Commerce sends the `flex-commerce-cart` event, when another tab posts to the `flex-commerce-cart` broadcast channel, and when the window regains focus. It uses `role="status"` and `aria-live="polite"`.

## Settings

| Setting | Values |
| --- | --- |
| Show bar | on/off; off by default |
| Threshold | 0–1,000,000 in the store currency, stored in minor units; 0 disables free shipping |
| Messages | up to 180 characters each, no HTML |
| Background and text colour | `#rgb` or `#rrggbb` |
| Link | optional local path starting with `/` or an HTTP/HTTPS URL, up to 300 characters |

Saving the threshold also updates the Flex Commerce **Free shipping over** setting in the same transaction, so flat-rate shipping and Econt use the same threshold. Turning the bar off only hides the message; free shipping still applies while the threshold is above 0.

## Routes

| Method and path | Action |
| --- | --- |
| `GET /plugins/flex/shipping-bar/config` | Public bar configuration; returns `enabled: false` if Commerce is inactive |
| `GET /admin/plugins/flex/shipping-bar/settings` | Read settings, currency and Commerce status |
| `PUT /admin/plugins/flex/shipping-bar/settings` | Save settings; returns 409 if Commerce is inactive |

Administrative routes are protected by the platform authentication, super-admin and CSRF middleware. Validation errors return 422. JSON responses use `Cache-Control: no-store`.

## Uninstalling

Deactivation keeps the settings. Uninstalling removes the bar and drops `flex_shipping_bar_settings`, but leaves the threshold in the Flex Commerce settings. Set it to 0 in the shop settings to stop free shipping.

## Structure

```text
plugin.json                    Version, permissions, autoload and assets
src/ShippingBarPlugin.php      Registration, lifecycle and checkout filter
src/ShippingBarSettings.php    Schema, validation and Commerce threshold sync
src/FreeShippingQuote.php      Zero shipping above the threshold
src/CommerceGuard.php          Flex Commerce status check
src/AdminController.php        Administrative JSON API
src/PublicController.php       Public configuration
assets/admin.js                Settings page
assets/bar.js                  Public bar
assets/bar.css                 Public styles
tests/                         PHP and JavaScript tests
```

## Development checks

Run from the Flex CMS root with Composer dependencies installed:

```sh
node --test plugins/flex/shipping-bar/tests/bar.test.cjs
docker compose exec -T app vendor/bin/phpunit plugins/flex/shipping-bar/tests/ShippingBarSettingsTest.php
```

When this README was written, **1 JavaScript test** and **8 PHP tests with 40 assertions** passed.

## Package boundaries

The plugin uses Flex CMS classes and services and is not a standalone PHP application. It depends on Flex Commerce for the cart, checkout and store settings.

This repository has no separate `LICENSE` file and grants no licence to use, modify or distribute the code. Public access to the source code does not constitute such a licence.
