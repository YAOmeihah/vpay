# VPay 安装与升级说明

本文档适用于 GitHub Release 中的完整免构建发布包，例如 `vpay-v2.1.0.zip`。

发布包已经包含：

- PHP 生产依赖 `vendor/`
- 管理后台静态资源 `public/console/`
- 安装器、数据库初始化 SQL、升级 migration

服务器不需要安装 Composer、Node.js 或 pnpm。

## 环境要求

- PHP 8.0 或更高版本
- MySQL
- PHP PDO MySQL 扩展
- Web 服务器支持伪静态，推荐 Apache 或 Nginx
- 项目根目录下的 `runtime/` 可写
- 全新安装时项目根目录下的 `.env` 可写或可创建

## 全新安装

1. 解压发布包到服务器目录。
2. 将站点运行目录设置为发布包内的 `public/`。
3. 配置伪静态。
4. 创建一个空 MySQL 数据库。
5. 确保项目根目录可创建 `.env`，并确保 `runtime/` 可写。
6. 浏览器访问 `http://你的域名/`，系统会自动进入安装向导；也可以直接访问 `http://你的域名/install`。
7. 填写数据库配置和管理员账号密码。
8. 安装完成后进入 `http://你的域名/console/` 登录后台。

全新发布包默认不包含 `.env`。系统检测到 `.env` 不存在时，会自动开放安装入口，不需要手工创建 `runtime/install/enable.flag`。

## 升级已有系统

升级前请先备份：

- 旧站点代码
- 旧数据库
- 旧 `.env`

升级步骤：

1. 解压新版发布包。
2. 保留旧系统的 `.env`，不要用 `.example.env` 覆盖。
3. 用新版代码覆盖旧代码，或者解压到新目录后复制旧 `.env` 到新目录。
4. 确保站点运行目录仍然指向 `public/`。
5. 浏览器访问 `http://你的域名/`，系统会自动进入升级向导；也可以直接访问 `http://你的域名/install`。
6. 如果系统检测到需要升级，会进入升级确认页。
7. 输入旧系统管理员账号和密码。
8. 系统执行 `database/migrations/` 中的升级 SQL。
9. 升级完成后进入 `http://你的域名/console/`。

升级不会重新导入 `vmq.sql`，也不会重置管理员账号。升级只执行待升级版本对应的 migration。

## Apache 伪静态

发布包的 `public/.htaccess` 已内置 Apache 伪静态规则。请确保站点开启 `mod_rewrite`，并允许 `.htaccess` 生效。
其中默认首页顺序会优先执行 `index.php`，避免首次安装时被静态首页拦截。
安装或升级完成后，访问域名根路径会由 `index.php` 判断状态；未安装或需要升级时进入安装向导，已安装时渲染发布包内的 `public/index.html` 作为系统首页。

## Nginx 伪静态示例

请将 `root` 指向发布包内的 `public/`：

```nginx
server {
    listen 80;
    server_name example.com;
    root /path/to/vpay/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?s=$uri&$args;
    }

    location ~ \.php$ {
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}
```

## 目录权限

至少确保以下路径可写：

```text
runtime/
.env
```

如果 `.env` 不存在，需要确保项目根目录可创建 `.env`。

## 常见问题

### 访问 `/console/` 404

请确认你使用的是 Release 完整发布包，或已经执行前端构建。完整发布包应包含：

```text
public/console/index.html
public/console/static/
```

### 访问 `/install` 404

如果系统已经安装且不需要升级，安装入口会关闭，这是正常行为。

如果是全新安装，请确认项目根目录下不存在 `.env`，并确认站点运行目录指向 `public/`。

### 升级时没有进入升级页

请确认新版代码中的 `config/app.php` 版本号高于数据库中的 `schema_version`，并确认旧 `.env` 指向正确的旧数据库。

### 升级失败后业务接口不可用

系统会进入恢复状态，访问：

```text
http://你的域名/install/recover
```

查看错误信息，修复数据库或权限问题后重新执行升级。
