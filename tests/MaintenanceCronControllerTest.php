<?php
declare(strict_types=1);

namespace tests;

use app\controller\maintenance\Cron;
use app\model\PayOrder;
use think\facade\Db;

final class MaintenanceCronControllerTest extends TestCase
{
    public function test_rejects_request_when_maintenance_endpoint_is_disabled_by_default(): void
    {
        $response = $this->runMaintenanceRequest('127.0.0.1', 'secret-token');
        $payload = $this->decodeResponse($response);

        self::assertSame(403, $response->getCode());
        self::assertSame(40303, $payload['code']);
        self::assertSame('维护接口未启用', $payload['msg']);
    }

    public function test_rejects_request_when_token_is_invalid(): void
    {
        $this->seedSettings([
            'maintenance_enabled' => '1',
            'maintenance_token' => 'secret-token',
            'maintenance_allowed_ips' => '127.0.0.1',
        ]);

        $response = $this->runMaintenanceRequest('127.0.0.1', 'wrong-token');
        $payload = $this->decodeResponse($response);

        self::assertSame(403, $response->getCode());
        self::assertSame(40301, $payload['code']);
        self::assertSame('维护接口认证失败', $payload['msg']);
    }

    public function test_rejects_request_when_ip_is_not_allowed(): void
    {
        $this->seedSettings([
            'maintenance_enabled' => '1',
            'maintenance_token' => 'secret-token',
            'maintenance_allowed_ips' => '127.0.0.1',
        ]);

        $response = $this->runMaintenanceRequest('10.0.0.5', 'secret-token');
        $payload = $this->decodeResponse($response);

        self::assertSame(403, $response->getCode());
        self::assertSame(40302, $payload['code']);
        self::assertSame('维护接口 IP 不允许', $payload['msg']);
    }

    public function test_runs_terminal_offline_check_and_expired_order_cleanup(): void
    {
        $this->seedSettings([
            'close' => '30',
            'maintenance_enabled' => '1',
            'maintenance_token' => 'secret-token',
            'maintenance_allowed_ips' => '127.0.0.1',
        ]);

        $now = time();

        Db::name('monitor_terminal')->insert([
            'terminal_code' => 'stale-terminal',
            'terminal_name' => 'Stale Terminal',
            'online_state' => 'online',
            'monitor_key' => 'monitor-key-a',
            'last_heartbeat_at' => $now - 120,
            'last_paid_at' => 0,
            'last_ip' => '127.0.0.1',
            'created_at' => $now - 3600,
            'updated_at' => $now - 3600,
        ]);

        Db::name('monitor_terminal')->insert([
            'terminal_code' => 'fresh-terminal',
            'terminal_name' => 'Fresh Terminal',
            'online_state' => 'online',
            'monitor_key' => 'monitor-key-b',
            'last_heartbeat_at' => $now,
            'last_paid_at' => 0,
            'last_ip' => '127.0.0.1',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        Db::name('pay_order')->insert([
            'create_date' => $now - 3600,
            'notify_url' => 'https://merchant.example/notify',
            'order_id' => 'expired-order',
            'pay_id' => 'merchant-expired',
            'price' => '10.00',
            'really_price' => '10.00',
            'return_url' => 'https://merchant.example/return',
            'state' => PayOrder::STATE_UNPAID,
            'type' => PayOrder::TYPE_WECHAT,
        ]);

        Db::name('pay_order')->insert([
            'create_date' => $now,
            'notify_url' => 'https://merchant.example/notify',
            'order_id' => 'fresh-order',
            'pay_id' => 'merchant-fresh',
            'price' => '20.00',
            'really_price' => '20.00',
            'return_url' => 'https://merchant.example/return',
            'state' => PayOrder::STATE_UNPAID,
            'type' => PayOrder::TYPE_ALIPAY,
        ]);

        Db::name('tmp_price')->insert([
            'oid' => 'expired-order',
            'channel_id' => 1,
            'price' => '10.00',
        ]);

        Db::name('tmp_price')->insert([
            'oid' => 'orphan-order',
            'channel_id' => 1,
            'price' => '99.99',
        ]);

        $response = $this->runMaintenanceRequest('127.0.0.1', 'secret-token');
        $payload = $this->decodeResponse($response);

        self::assertSame(200, $response->getCode(), (string) $response->getContent());
        self::assertSame(1, $payload['code']);
        self::assertSame('维护完成', $payload['msg']);
        self::assertSame('terminal_offline_check', $payload['data']['tasks'][0]['name']);
        self::assertSame(1, $payload['data']['tasks'][0]['affected']);
        self::assertSame('expired_order_cleanup', $payload['data']['tasks'][1]['name']);
        self::assertSame(1, $payload['data']['tasks'][1]['affected']);

        self::assertSame(
            'offline',
            Db::name('monitor_terminal')->where('terminal_code', 'stale-terminal')->value('online_state')
        );
        self::assertSame(
            'online',
            Db::name('monitor_terminal')->where('terminal_code', 'fresh-terminal')->value('online_state')
        );
        self::assertSame(
            PayOrder::STATE_EXPIRED,
            (int) Db::name('pay_order')->where('order_id', 'expired-order')->value('state')
        );
        self::assertGreaterThan(
            0,
            (int) Db::name('pay_order')->where('order_id', 'expired-order')->value('close_date')
        );
        self::assertSame(
            PayOrder::STATE_UNPAID,
            (int) Db::name('pay_order')->where('order_id', 'fresh-order')->value('state')
        );
        self::assertSame(0, Db::name('tmp_price')->where('oid', 'expired-order')->count());
        self::assertSame(0, Db::name('tmp_price')->where('oid', 'orphan-order')->count());
    }

    public function test_skips_disabled_maintenance_tasks(): void
    {
        $this->seedSettings([
            'maintenance_enabled' => '1',
            'maintenance_token' => 'secret-token',
            'maintenance_allowed_ips' => '127.0.0.1',
            'maintenance_task_terminal_offline_check' => '0',
            'maintenance_task_expired_order_cleanup' => '0',
        ]);

        $response = $this->runMaintenanceRequest('127.0.0.1', 'secret-token');
        $payload = $this->decodeResponse($response);

        self::assertSame(200, $response->getCode());
        self::assertSame('skipped', $payload['data']['tasks'][0]['status']);
        self::assertSame('skipped', $payload['data']['tasks'][1]['status']);
    }

    public function test_expired_order_cleanup_does_not_run_disabled_terminal_offline_check(): void
    {
        $this->seedSettings([
            'close' => '30',
            'maintenance_enabled' => '1',
            'maintenance_token' => 'secret-token',
            'maintenance_allowed_ips' => '127.0.0.1',
            'maintenance_task_terminal_offline_check' => '0',
            'maintenance_task_expired_order_cleanup' => '1',
        ]);

        $now = time();
        Db::name('monitor_terminal')->insert([
            'terminal_code' => 'stale-terminal',
            'terminal_name' => 'Stale Terminal',
            'online_state' => 'online',
            'monitor_key' => 'monitor-key-a',
            'last_heartbeat_at' => $now - 120,
            'last_paid_at' => 0,
            'last_ip' => '127.0.0.1',
            'created_at' => $now - 3600,
            'updated_at' => $now - 3600,
        ]);

        $response = $this->runMaintenanceRequest('127.0.0.1', 'secret-token');
        $payload = $this->decodeResponse($response);

        self::assertSame(200, $response->getCode());
        self::assertSame('skipped', $payload['data']['tasks'][0]['status']);
        self::assertSame('ok', $payload['data']['tasks'][1]['status']);
        self::assertSame(
            'online',
            Db::name('monitor_terminal')->where('terminal_code', 'stale-terminal')->value('online_state')
        );
    }

    public function test_successful_run_records_last_run_result(): void
    {
        $this->seedSettings([
            'maintenance_enabled' => '1',
            'maintenance_token' => 'secret-token',
            'maintenance_allowed_ips' => '127.0.0.1',
        ]);

        $response = $this->runMaintenanceRequest('127.0.0.1', 'secret-token');

        self::assertSame(200, $response->getCode());
        self::assertNotSame('', \app\model\Setting::getConfigValue('maintenance_last_run_at'));
        self::assertStringContainsString(
            'terminal_offline_check',
            \app\model\Setting::getConfigValue('maintenance_last_run_result')
        );
    }

    public function test_route_is_registered(): void
    {
        $appRoute = (string) file_get_contents(dirname(__DIR__) . '/route/app.php');
        $maintenanceRoute = (string) file_get_contents(dirname(__DIR__) . '/route/maintenance.php');

        self::assertStringContainsString("require __DIR__ . '/maintenance.php';", $appRoute);
        self::assertStringContainsString("Route::post('maintenance/run', 'maintenance.Cron/run')", $maintenanceRoute);
    }

    private function runMaintenanceRequest(string $ip, string $token)
    {
        $request = (clone $this->app->request)
            ->withServer([
                'REQUEST_METHOD' => 'POST',
                'REMOTE_ADDR' => $ip,
                'PATH_INFO' => 'maintenance/run',
            ])
            ->withHeader(['X-Maintenance-Token' => $token])
            ->setMethod('POST');

        $this->app->instance('request', $request);

        return (new Cron($this->app))->run();
    }

    private function decodeResponse($response): array
    {
        return json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);
    }
}
