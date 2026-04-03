<?php
declare(strict_types=1);

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use app\service\CacheService;

/**
 * 缓存管理命令
 */
class CacheManage extends Command
{
    protected function configure()
    {
        $this->setName('cache:manage')
            ->addArgument('action', null, '操作类型: clear|stats|warmup')
            ->setDescription('缓存管理命令');
    }

    protected function execute(Input $input, Output $output)
    {
        $action = $input->getArgument('action');

        switch ($action) {
            case 'clear':
                $this->clearCache($output);
                break;
            case 'stats':
                $this->showStats($output);
                break;
            case 'warmup':
                $this->warmupCache($output);
                break;
            default:
                $output->writeln('请指定操作类型: clear|stats|warmup');
                $output->writeln('');
                $output->writeln('使用示例:');
                $output->writeln('  php think cache:manage clear   # 清除所有缓存');
                $output->writeln('  php think cache:manage stats   # 查看缓存统计');
                $output->writeln('  php think cache:manage warmup  # 预热缓存');
                return 1;
        }

        return 0;
    }

    /**
     * 清除缓存
     */
    private function clearCache(Output $output)
    {
        $output->writeln('正在清除所有缓存...');
        
        if (CacheService::clearAll()) {
            $output->writeln('✅ 缓存清除成功');
        } else {
            $output->writeln('❌ 缓存清除失败');
        }
    }

    /**
     * 显示缓存统计
     */
    private function showStats(Output $output)
    {
        $stats = CacheService::getCacheStats();
        
        $output->writeln('=== Redis缓存统计信息 ===');
        
        if (isset($stats['error'])) {
            $output->writeln('❌ ' . $stats['error']);
            return;
        }
        
        $output->writeln('连接客户端数: ' . ($stats['connected_clients'] ?? 'N/A'));
        $output->writeln('内存使用量: ' . ($stats['used_memory_human'] ?? 'N/A'));
        $output->writeln('缓存命中次数: ' . ($stats['keyspace_hits'] ?? 'N/A'));
        $output->writeln('缓存未命中次数: ' . ($stats['keyspace_misses'] ?? 'N/A'));
        $output->writeln('缓存命中率: ' . ($stats['hit_rate'] ?? 'N/A') . '%');
    }

    /**
     * 预热缓存
     */
    private function warmupCache(Output $output)
    {
        $output->writeln('正在预热缓存...');
        
        try {
            // 预热系统配置
            $settings = \app\model\Setting::select()->toArray();
            $settingCount = 0;
            foreach ($settings as $setting) {
                if (CacheService::cacheSetting($setting['vkey'], $setting['vvalue'])) {
                    $settingCount++;
                }
            }
            $output->writeln("✅ 预热系统配置: {$settingCount} 项");

            // 预热热门订单（最近100个）
            $orders = \app\model\PayOrder::order('id', 'desc')->limit(100)->select()->toArray();
            $orderCount = 0;
            foreach ($orders as $order) {
                $data = [
                    "payId" => $order['pay_id'],
                    "orderId" => $order['order_id'],
                    "payType" => $order['type'],
                    "price" => $order['price'],
                    "reallyPrice" => $order['really_price'],
                    "payUrl" => $order['pay_url'],
                    "isAuto" => $order['is_auto'],
                    "state" => $order['state'],
                    "timeOut" => \app\model\Setting::getConfigValue("close"),
                    "date" => $order['create_date']
                ];
                if (CacheService::cacheOrder($order['order_id'], $data)) {
                    $orderCount++;
                }
            }
            $output->writeln("✅ 预热订单数据: {$orderCount} 个");

            $output->writeln('✅ 缓存预热完成');
        } catch (\Exception $e) {
            $output->writeln('❌ 缓存预热失败: ' . $e->getMessage());
        }
    }
}
