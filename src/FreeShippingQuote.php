<?php

declare(strict_types=1);

namespace Flex\ShippingBar;

final class FreeShippingQuote
{
    public function __construct(private ?ShippingBarSettings $settings = null) {}

    public function apply(mixed $quote): mixed
    {
        if (!is_array($quote)) {
            return $quote;
        }
        try {
            $threshold = (int) (($this->settings ?? new ShippingBarSettings())->all()['threshold_minor'] ?? 0);
        } catch (\Throwable) {
            return $quote;
        }
        $subtotal = (int) ($quote['subtotal_minor'] ?? 0);
        if ($threshold <= 0 || $subtotal < $threshold) {
            return $quote;
        }
        $shipping = isset($quote['shipping_minor']) && is_numeric($quote['shipping_minor']) ? (int) $quote['shipping_minor'] : 0;
        $total = isset($quote['total_minor']) && is_numeric($quote['total_minor']) ? (int) $quote['total_minor'] : $subtotal + $shipping;
        $meta = is_array($quote['shipping_meta'] ?? null) ? $quote['shipping_meta'] : [];
        $meta['free_shipping'] = true;
        $quote['shipping_minor'] = 0;
        $quote['total_minor'] = max(0, $total - max(0, $shipping));
        $quote['shipping_meta'] = $meta;

        return $quote;
    }
}
