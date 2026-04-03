<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

/**
 * 系统设置模型
 */
class Setting extends Model
{
    // 设置表名
    protected $name = 'setting';
    
    // 设置主键
    protected $pk = 'vkey';
    
    // 设置字段信息
    protected $schema = [
        'vkey'   => 'string',
        'vvalue' => 'string',
    ];
    
    /**
     * 获取设置值（带缓存）
     * @param string $key 设置键
     * @param string $default 默认值
     * @return string
     */
    public static function getConfigValue(string $key, string $default = ''): string
    {
        // 先从缓存获取
        $value = \app\service\CacheService::getSetting($key);
        if ($value !== null) {
            return $value;
        }

        // 缓存未命中，从数据库获取
        $setting = static::where('vkey', $key)->find();
        $value = $setting ? $setting->vvalue : $default;

        // 存入缓存
        if ($value !== '') {
            \app\service\CacheService::cacheSetting($key, $value);
        }

        return $value;
    }

    /**
     * 设置值（带缓存更新）
     * @param string $key 设置键
     * @param string $value 设置值
     * @return bool
     */
    public static function setConfigValue(string $key, string $value): bool
    {
        $setting = static::where('vkey', $key)->find();
        $result = false;

        if ($setting) {
            $result = $setting->save(['vvalue' => $value]);
        } else {
            $result = static::create(['vkey' => $key, 'vvalue' => $value]) ? true : false;
        }

        // 更新缓存
        if ($result) {
            \app\service\CacheService::cacheSetting($key, $value);
        }

        return $result;
    }
}
