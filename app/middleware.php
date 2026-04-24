<?php
// 全局中间件定义文件
return [
    // 安全中间件
    \app\middleware\Security::class,
    // Session初始化
    \think\middleware\SessionInit::class,
    // 安装状态守卫
    \app\middleware\EnsureSystemInstalled::class,
    // 全局请求缓存
    // \think\middleware\CheckRequestCache::class,
    // 多语言加载
    // \think\middleware\LoadLangPack::class,
];
