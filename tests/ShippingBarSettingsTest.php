<?php

declare(strict_types=1);

namespace Flex\ShippingBar\Tests;

use Flex\Configuration\ConfigurationRepository;
use Flex\Database\DatabaseManager;
use Flex\ShippingBar\FreeShippingQuote;
use Flex\ShippingBar\ShippingBarSettings;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../src/ShippingBarSettings.php';
require_once __DIR__ . '/../src/FreeShippingQuote.php';

final class ShippingBarSettingsTest extends TestCase
{
    private DatabaseManager $db;
    private ShippingBarSettings $settings;

    protected function setUp(): void
    {
        $this->db = new DatabaseManager(new ConfigurationRepository([
            'database' => ['default' => 'sqlite', 'connections' => ['sqlite' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]],
        ]));
        $connection = $this->db->connection();
        $connection->getSchemaBuilder()->create('flex_commerce_settings', static function (Blueprint $table): void {
            $table->unsignedInteger('id')->primary();
            $table->text('values');
            $table->unsignedInteger('version')->default(1);
        });
        $connection->table('flex_commerce_settings')->insert([
            'id' => 1,
            'values' => json_encode(['currency' => 'EUR', 'free_shipping_minor' => 2500, 'shipping_minor' => 500]),
            'version' => 3,
        ]);
        $this->settings = new ShippingBarSettings($connection);
        $this->settings->install();
    }

    protected function tearDown(): void
    {
        $this->db->disconnect();
    }

    public function testInstallCopiesTheCommerceThreshold(): void
    {
        $values = $this->settings->all();
        self::assertFalse($values['enabled']);
        self::assertSame(2500, $values['threshold_minor']);
        self::assertSame('EUR', $this->settings->currency());
    }

    public function testSaveSyncsTheThresholdAndKeepsCommerceFields(): void
    {
        $saved = $this->settings->save([
            'enabled' => true,
            'threshold_minor' => 5000,
            'message_progress' => 'Остават {remaining}',
            'background' => '#ABC',
            'link' => '/catalog',
        ]);
        self::assertTrue($saved['enabled']);
        self::assertSame(5000, $saved['threshold_minor']);
        self::assertSame('Остават {remaining}', $saved['message_progress']);
        self::assertSame('#aabbcc', $saved['background']);
        self::assertSame('/catalog', $saved['link']);

        $row = $this->db->connection()->table('flex_commerce_settings')->where('id', 1)->first();
        $commerce = json_decode($row->values, true);
        self::assertSame(5000, $commerce['free_shipping_minor']);
        self::assertSame(500, $commerce['shipping_minor']);
        self::assertSame('EUR', $commerce['currency']);
        self::assertSame(4, (int) $row->version);
    }

    public function testInvalidSettingsAreRejected(): void
    {
        foreach ([
            ['threshold_minor' => -1],
            ['threshold_minor' => 100000001],
            ['enabled' => 'yes'],
            ['message_empty' => ''],
            ['message_empty' => '<b>x</b>'],
            ['background' => 'green'],
            ['link' => 'javascript:alert(1)'],
            ['link' => '//evil.example'],
        ] as $input) {
            try {
                $this->settings->save($input);
                self::fail('Invalid settings accepted: ' . json_encode($input));
            } catch (\InvalidArgumentException $error) {
                self::assertSame(422, $error->getCode());
            }
        }
        $commerce = json_decode($this->db->connection()->table('flex_commerce_settings')->where('id', 1)->value('values'), true);
        self::assertSame(2500, $commerce['free_shipping_minor']);
        self::assertSame(3, (int) $this->db->connection()->table('flex_commerce_settings')->where('id', 1)->value('version'));
    }

    public function testPublicConfigHidesADisabledOrZeroThreshold(): void
    {
        self::assertFalse($this->settings->publicConfig()['enabled']);
        $this->settings->save(['enabled' => true]);
        $config = $this->settings->publicConfig();
        self::assertTrue($config['enabled']);
        self::assertSame(2500, $config['threshold_minor']);
        self::assertSame('EUR', $config['currency']);
        $this->settings->save(['threshold_minor' => 0]);
        self::assertFalse($this->settings->publicConfig()['enabled']);
    }

    public function testQuoteBecomesFreeAtTheThreshold(): void
    {
        $quote = new FreeShippingQuote($this->settings);
        $below = $quote->apply([
            'subtotal_minor' => 2499,
            'shipping_minor' => 700,
            'total_minor' => 3199,
            'shipping_meta' => ['provider' => 'econt'],
        ]);
        self::assertSame(700, $below['shipping_minor']);
        self::assertSame(3199, $below['total_minor']);

        $reached = $quote->apply([
            'subtotal_minor' => 2500,
            'shipping_minor' => 700,
            'total_minor' => 3200,
            'shipping_meta' => ['provider' => 'econt'],
        ]);
        self::assertSame(0, $reached['shipping_minor']);
        self::assertSame(2500, $reached['total_minor']);
        self::assertTrue($reached['shipping_meta']['free_shipping']);
        self::assertSame('econt', $reached['shipping_meta']['provider']);
        self::assertSame('unchanged', $quote->apply('unchanged'));
    }

    public function testZeroThresholdLeavesTheQuote(): void
    {
        $this->settings->save(['threshold_minor' => 0]);
        $quote = (new FreeShippingQuote($this->settings))->apply([
            'subtotal_minor' => 9000,
            'shipping_minor' => 400,
            'total_minor' => 9400,
        ]);
        self::assertSame(400, $quote['shipping_minor']);
        self::assertSame(9400, $quote['total_minor']);
    }

    public function testReinstallKeepsSavedSettings(): void
    {
        $this->settings->save(['enabled' => true, 'threshold_minor' => 8000]);
        $this->settings->install();
        $values = $this->settings->all();
        self::assertTrue($values['enabled']);
        self::assertSame(8000, $values['threshold_minor']);
    }

    public function testUninstallDropsOnlyTheBarTable(): void
    {
        $this->settings->uninstall();
        self::assertFalse($this->db->schema()->hasTable('flex_shipping_bar_settings'));
        self::assertTrue($this->db->schema()->hasTable('flex_commerce_settings'));
    }
}
