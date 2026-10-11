<?php

declare(strict_types=1);

namespace Flex\ShippingBar;

use Flex\Extension\V1\{BootablePluginInterface, PluginContext, PluginInterface, UninstallablePluginInterface, UpdatablePluginInterface};

final class ShippingBarPlugin implements PluginInterface, BootablePluginInterface, UninstallablePluginInterface, UpdatablePluginInterface
{
    public function install(PluginContext $context): void
    {
        (new CommerceGuard())->assertReady();
        (new ShippingBarSettings())->install();
    }

    public function activate(PluginContext $context): void
    {
        $this->install($context);
    }

    public function update(PluginContext $context, string $fromVersion): void
    {
        $this->install($context);
    }

    public function deactivate(PluginContext $context): void {}

    public function uninstall(PluginContext $context): void
    {
        (new ShippingBarSettings())->uninstall();
    }

    public function boot(PluginContext $context): void
    {
        $context->admin?->page('settings', 'Безплатна доставка', 'assets/admin.js', false, [
            'group' => 'commerce',
            'icon' => 'shopping-bag',
            'section' => 'settings',
            'order' => 3,
        ]);
        $context->routes?->admin(['GET', 'PUT'], '/settings', AdminController::class, 'settings');

        if (!(new CommerceGuard())->active()) {
            return;
        }

        $context->routes?->get('/config', PublicController::class, 'config');
        // The bar loads only while it is switched on; the setting is read only when a public page is rendered.
        ($context->assets ?? null)?->enqueueWhen('bar', static fn(): bool => (new ShippingBarSettings())->publicConfig()['enabled'] === true);
        $context->api->addFilter('commerce.checkout.quote', static function (mixed $quote, array $context = []): mixed {
            return (new FreeShippingQuote())->apply($quote);
        }, 100);
    }
}
