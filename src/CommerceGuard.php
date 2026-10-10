<?php

declare(strict_types=1);

namespace Flex\ShippingBar;

use Flex\Extensions\{Plugin, PluginManager};

final class CommerceGuard
{
    public function active(): bool
    {
        try {
            $plugin = Plugin::query()->find('flex/commerce');

            return $plugin instanceof Plugin
                && $plugin->getAttribute('status') === PluginManager::STATUS_ACTIVE;
        } catch (\Throwable) {
            return false;
        }
    }

    public function assertReady(): void
    {
        if (!$this->active()) {
            throw new \RuntimeException('Първо инсталирайте и активирайте Flex Commerce.');
        }
    }
}
