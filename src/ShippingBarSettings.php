<?php

declare(strict_types=1);

namespace Flex\ShippingBar;

use Illuminate\Database\{Connection, Eloquent\Model, Schema\Blueprint};

final class ShippingBarSettings
{
    public const DEFAULTS = [
        'enabled' => false,
        'threshold_minor' => 0,
        'message_empty' => 'Безплатна доставка при поръчка над {threshold}',
        'message_progress' => 'Остават {remaining} до безплатна доставка',
        'message_reached' => 'Имате безплатна доставка за тази поръчка',
        'background' => '#0f6b4c',
        'color' => '#ffffff',
        'link' => '',
    ];

    public function __construct(private ?Connection $db = null)
    {
        $this->db = $db ?? Model::resolveConnection();
    }

    public function install(): void
    {
        if (!$this->db->getSchemaBuilder()->hasTable('flex_commerce_settings')) {
            throw new \RuntimeException('Първо инсталирайте Flex Commerce.');
        }
        if ($this->db->getSchemaBuilder()->hasTable('flex_shipping_bar_settings')) {
            return;
        }
        $this->db->getSchemaBuilder()->create('flex_shipping_bar_settings', static function (Blueprint $table): void {
            $table->integer('id')->primary();
            $table->text('values');
        });
        $defaults = self::DEFAULTS;
        $defaults['threshold_minor'] = $this->commerceThreshold();
        $this->db->table('flex_shipping_bar_settings')->insert([
            'id' => 1,
            'values' => json_encode($defaults, JSON_UNESCAPED_UNICODE),
        ]);
    }

    public function uninstall(): void
    {
        $this->db->getSchemaBuilder()->dropIfExists('flex_shipping_bar_settings');
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        $row = $this->db->table('flex_shipping_bar_settings')->where('id', 1)->value('values');

        return array_replace(self::DEFAULTS, json_decode(is_string($row) ? $row : '{}', true) ?: []);
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    public function save(array $input): array
    {
        $values = $this->all();
        if (array_key_exists('enabled', $input)) {
            if (!is_bool($input['enabled'])) {
                throw new \InvalidArgumentException('Невалидна настройка: enabled', 422);
            }
            $values['enabled'] = $input['enabled'];
        }
        if (array_key_exists('threshold_minor', $input)) {
            $threshold = filter_var($input['threshold_minor'], FILTER_VALIDATE_INT);
            if (!is_int($threshold) || $threshold < 0 || $threshold > 100000000) {
                throw new \InvalidArgumentException('Прагът трябва да е между 0 и 1000000.', 422);
            }
            $values['threshold_minor'] = $threshold;
        }
        foreach (['message_empty', 'message_progress', 'message_reached'] as $key) {
            if (!array_key_exists($key, $input)) {
                continue;
            }
            if (!is_string($input[$key]) || trim($input[$key]) === '' || mb_strlen($input[$key]) > 180 || str_contains($input[$key], '<')) {
                throw new \InvalidArgumentException('Невалиден текст на лентата.', 422);
            }
            $values[$key] = trim($input[$key]);
        }
        foreach (['background', 'color'] as $key) {
            if (!array_key_exists($key, $input)) {
                continue;
            }
            if (!is_string($input[$key]) || preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $input[$key]) !== 1) {
                throw new \InvalidArgumentException('Невалиден цвят.', 422);
            }
            $values[$key] = self::hex($input[$key]);
        }
        if (array_key_exists('link', $input)) {
            if (!is_string($input['link']) || mb_strlen($input['link']) > 300) {
                throw new \InvalidArgumentException('Невалиден линк.', 422);
            }
            $link = trim($input['link']);
            if ($link !== '' && preg_match('~^(?:/(?!/)|https?://)~i', $link) !== 1) {
                throw new \InvalidArgumentException('Линкът трябва да е вътрешен път или HTTP/HTTPS адрес.', 422);
            }
            $values['link'] = $link;
        }

        $this->db->transaction(function () use ($values): void {
            $this->db->table('flex_shipping_bar_settings')->where('id', 1)->update([
                'values' => json_encode($values, JSON_UNESCAPED_UNICODE),
            ]);
            $this->syncCommerceThreshold((int) $values['threshold_minor']);
        });

        return $this->all();
    }

    /** @return array<string, mixed> */
    public function publicConfig(): array
    {
        $values = $this->all();
        $threshold = (int) $values['threshold_minor'];

        return [
            'enabled' => $values['enabled'] === true && $threshold > 0,
            'threshold_minor' => $threshold,
            'currency' => $this->currency(),
            'message_empty' => (string) $values['message_empty'],
            'message_progress' => (string) $values['message_progress'],
            'message_reached' => (string) $values['message_reached'],
            'background' => (string) $values['background'],
            'color' => (string) $values['color'],
            'link' => (string) $values['link'],
        ];
    }

    public function currency(): string
    {
        if (!$this->db->getSchemaBuilder()->hasTable('flex_commerce_settings')) {
            return 'EUR';
        }
        $raw = $this->db->table('flex_commerce_settings')->where('id', 1)->value('values');
        $decoded = json_decode(is_string($raw) ? $raw : '{}', true) ?: [];
        $currency = is_string($decoded['currency'] ?? null) ? $decoded['currency'] : 'EUR';

        return in_array($currency, ['EUR', 'BGN', 'USD'], true) ? $currency : 'EUR';
    }

    private static function hex(string $value): string
    {
        $hex = strtolower($value);
        if (strlen($hex) === 4) {
            return '#' . $hex[1] . $hex[1] . $hex[2] . $hex[2] . $hex[3] . $hex[3];
        }

        return $hex;
    }

    private function commerceThreshold(): int
    {
        $raw = $this->db->table('flex_commerce_settings')->where('id', 1)->value('values');
        $decoded = json_decode(is_string($raw) ? $raw : '{}', true) ?: [];
        $threshold = filter_var($decoded['free_shipping_minor'] ?? 0, FILTER_VALIDATE_INT);

        return is_int($threshold) && $threshold > 0 ? $threshold : 0;
    }

    private function syncCommerceThreshold(int $minor): void
    {
        if (!$this->db->getSchemaBuilder()->hasTable('flex_commerce_settings')) {
            throw new \RuntimeException('Първо инсталирайте Flex Commerce.');
        }
        $row = $this->db->table('flex_commerce_settings')->where('id', 1)->lockForUpdate()->first();
        if ($row === null) {
            throw new \RuntimeException('Първо инсталирайте Flex Commerce.');
        }
        $decoded = json_decode(is_string($row->values) ? $row->values : '{}', true) ?: [];
        $decoded['free_shipping_minor'] = $minor;
        $this->db->table('flex_commerce_settings')->where('id', 1)->update([
            'values' => json_encode($decoded, JSON_UNESCAPED_UNICODE),
            'version' => (int) $row->version + 1,
        ]);
    }
}
