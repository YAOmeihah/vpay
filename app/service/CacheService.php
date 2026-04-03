<?php
declare(strict_types=1);

namespace app\service;

use think\facade\Cache;

/**
 * 缓存服务类
 */
class CacheService
{
    // 缓存键前缀
    const PREFIX_ORDER = 'order:';
    const PREFIX_SETTING = 'setting:';
    const PREFIX_STATS = 'stats:';
    const PREFIX_QRCODE = 'qrcode:';
    
    // 缓存过期时间（秒）
    const TTL_ORDER = 3600;        // 订单缓存1小时
    const TTL_SETTING = 7200;      // 配置缓存2小时
    const TTL_STATS = 300;         // 统计缓存5分钟
    const TTL_QRCODE = 1800;       // 二维码缓存30分钟

    /**
     * 配置缓存管理
     */
    public static function cacheSetting(string $key, $value): bool
    {
        try {
            $cacheKey = self::PREFIX_SETTING . $key;
            return Cache::set($cacheKey, $value, self::TTL_SETTING);
        } catch (\Exception $e) {
            // 缓存失败不影响业务逻辑
            return false;
        }
    }

    public static function getSetting(string $key)
    {
        try {
            $cacheKey = self::PREFIX_SETTING . $key;
            return Cache::get($cacheKey);
        } catch (\Exception $e) {
            // 缓存失败返回null，让业务逻辑走数据库
            return null;
        }
    }

    public static function deleteSetting(string $key): bool
    {
        try {
            $cacheKey = self::PREFIX_SETTING . $key;
            return Cache::delete($cacheKey);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 订单缓存管理
     */
    public static function cacheOrder(string $orderId, array $orderData): bool
    {
        try {
            $key = self::PREFIX_ORDER . $orderId;
            return Cache::set($key, $orderData, self::TTL_ORDER);
        } catch (\Exception $e) {
            return false;
        }
    }

    public static function getOrder(string $orderId): ?array
    {
        try {
            $key = self::PREFIX_ORDER . $orderId;
            $data = Cache::get($key);
            return $data ?: null;
        } catch (\Exception $e) {
            return null;
        }
    }

    public static function deleteOrder(string $orderId): bool
    {
        try {
            $key = self::PREFIX_ORDER . $orderId;
            return Cache::delete($key);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 统计数据缓存管理
     */
    public static function cacheStats(string $type, $data): bool
    {
        try {
            $key = self::PREFIX_STATS . $type;
            return Cache::set($key, $data, self::TTL_STATS);
        } catch (\Exception $e) {
            return false;
        }
    }

    public static function getStats(string $type)
    {
        try {
            $key = self::PREFIX_STATS . $type;
            $data = Cache::get($key);
            return $data ?: null;
        } catch (\Exception $e) {
            return null;
        }
    }

    public static function deleteStats(string $type): bool
    {
        try {
            $key = self::PREFIX_STATS . $type;
            return Cache::delete($key);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 二维码缓存管理
     */
    public static function cacheQrcode(int $type, float $price, array $qrcodeData): bool
    {
        try {
            $key = self::PREFIX_QRCODE . $type . ':' . number_format($price, 2, '.', '');
            return Cache::set($key, $qrcodeData, self::TTL_QRCODE);
        } catch (\Exception $e) {
            return false;
        }
    }

    public static function getQrcode(int $type, float $price): ?array
    {
        try {
            $key = self::PREFIX_QRCODE . $type . ':' . number_format($price, 2, '.', '');
            $data = Cache::get($key);
            return $data ?: null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * 清除所有缓存
     */
    public static function clearAll(): bool
    {
        try {
            return Cache::clear();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 获取缓存统计信息
     */
    public static function getCacheStats(): array
    {
        try {
            $redis = Cache::store('redis')->handler();
            $info = $redis->info();
            
            return [
                'connected_clients' => $info['connected_clients'] ?? 0,
                'used_memory_human' => $info['used_memory_human'] ?? '0B',
                'keyspace_hits' => $info['keyspace_hits'] ?? 0,
                'keyspace_misses' => $info['keyspace_misses'] ?? 0,
                'hit_rate' => isset($info['keyspace_hits'], $info['keyspace_misses']) && 
                    ($info['keyspace_hits'] + $info['keyspace_misses']) > 0 ? 
                    round($info['keyspace_hits'] / ($info['keyspace_hits'] + $info['keyspace_misses']) * 100, 2) : 0
            ];
        } catch (\Exception $e) {
            return [
                'error' => '无法获取Redis统计信息: ' . $e->getMessage()
            ];
        }
    }
}
