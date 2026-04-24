# 后台确认式自动更新设计

## 背景

当前发布流程已经能在推送 tag 后由 GitHub Actions 构建完整发布包，例如 `vpay-v2.1.1.zip`。本地安装与升级流程也已经具备数据库迁移能力，并支持没有 SQL migration 的补丁版本写回 `schema_version` 和 `app_version`。

目标是在“只需要推送 GitHub”的前提下，让已部署站点在后台检测最新 Release，并由管理员选择是否在线更新。自动更新必须以安全和可恢复为优先，不允许普通访客触发安装，也不允许在校验失败或预检失败时覆盖线上文件。

## 范围

第一版实现“后台管理员确认式自动更新”：

- 管理员登录后台后可以检查 GitHub 最新正式 Release。
- 有新版本时显示版本号、发布时间、包大小、更新说明和风险提示。
- 管理员手动点击后，系统执行预检、下载、校验、备份、覆盖、迁移和清理。
- 更新过程有锁和状态文件，刷新页面后可继续查看进度或失败原因。
- 更新失败进入恢复状态，展示失败步骤、错误信息、备份路径和下一步建议。

第一版不实现这些能力：

- 普通访客访问首页触发更新。
- 无人值守自动安装。
- 降级安装。
- 插件或主题独立更新。
- 完整数据库自动回滚。

## 推荐流程

### 1. Release 检测

后台调用 `GET /admin/index/checkUpdate`。后端服务请求 GitHub Releases API：

```text
https://api.github.com/repos/YAOmeihah/vpay/releases/latest
```

服务只接受正式 Release：

- `draft=false`
- `prerelease=false`
- tag 符合 `vX.Y.Z`
- Release 资产中存在 `vpay-vX.Y.Z.zip`
- Release 资产中存在 `vpay-vX.Y.Z.zip.sha256`

比较规则：

- 当前版本等于最新版本：返回 `up_to_date`。
- 当前版本高于远程版本：返回 `ahead`，禁止降级。
- 当前版本低于远程版本：返回 `update_available`。
- GitHub 请求失败、超时、限流：返回 `check_failed`，不影响当前系统运行。

### 2. 环境预检

管理员点击“检查更新环境”后，后端执行 `UpdatePreflightService`。

预检项：

- `runtime/update/` 可创建、可写。
- 项目根目录可写。
- `public/`、`app/`、`config/`、`database/`、`route/`、`vendor/`、`view/` 可写。
- `.env` 可读但不会覆盖。
- PHP 启用 `curl` 或允许 `file_get_contents` 访问 HTTPS。
- PHP 启用 `ZipArchive`。
- 当前没有 `runtime/update/update.lock`。
- 当前没有安装器 `runtime/install/lock.json`。
- 当前没有安装或升级失败的 `runtime/install/last-error.json`。
- 磁盘剩余空间至少为发布包大小的 3 倍。
- 当前数据库可连接，`setting` 表可访问。

预检失败时，不下载、不解压、不覆盖任何文件。

### 3. 下载与校验

`UpdatePackageService` 下载两个文件：

```text
runtime/update/downloads/vpay-vX.Y.Z.zip
runtime/update/downloads/vpay-vX.Y.Z.zip.sha256
```

校验规则：

- 下载临时文件使用 `.part` 后缀，下载完成后再原子重命名。
- 校验 zip 文件 SHA256 必须等于 `.sha256` 内容。
- zip 必须能解压。
- zip 根目录必须是 `vpay-vX.Y.Z/`。
- 包内必须存在：
  - `release-manifest.json`
  - `config/app.php`
  - `public/index.php`
  - `public/index.html`
  - `vendor/autoload.php`
  - `database/migrations/`
- `release-manifest.json.version` 必须等于 Release tag。
- 包内 `config/app.php` 的 `ver` 必须等于去掉 `v` 前缀后的版本号。

校验失败时删除临时解压目录，保留错误状态，不进入覆盖阶段。

### 4. 备份策略

`UpdateBackupService` 在覆盖前创建备份：

```text
runtime/update/backups/vX.Y.Z-from-vA.B.C-YYYYmmdd-HHMMSS.zip
```

备份内容：

- 当前代码目录：
  - `app/`
  - `config/`
  - `database/`
  - `extend/`
  - `public/`
  - `route/`
  - `vendor/`
  - `view/`
  - `composer.json`
  - `composer.lock`
  - `think`
  - `vmq.sql`
- 当前 `.env`
- 当前 `release-manifest.json`，如果存在

备份排除：

- `runtime/`
- `public/runtime/`
- `runtime/update/`
- `runtime/install/lock.json`
- 缓存文件和日志文件

数据库备份第一版不强制自动执行，因为跨环境调用 `mysqldump` 不稳定，也可能因数据量过大导致更新失败。更新前 UI 必须提示管理员先备份数据库。后续版本可以增加“检测到 `mysqldump` 可用时自动生成 SQL 备份”的增强能力。

### 5. 更新锁与维护状态

开始覆盖前创建：

```text
runtime/update/update.lock
runtime/update/status.json
```

`update.lock` 内容包括：

- `from_version`
- `target_version`
- `started_at`
- `operator`
- `stage`

维护策略：

- 更新锁存在时，后台除更新状态查询接口外，其它写操作返回维护中。
- 支付、监控、商户接口在文件覆盖阶段应尽量短暂停止，返回明确的维护响应，避免半更新状态继续处理交易。
- 状态查询接口允许管理员刷新页面后继续查看进度。

如果请求中断但锁还在，下一次进入后台应显示“更新可能未完成”，并引导查看恢复信息。

### 6. 文件覆盖策略

`UpdateApplyService` 按目录覆盖发布包内容。

保留本地文件和目录：

- `.env`
- `runtime/`
- `public/runtime/`
- `runtime/update/`
- `runtime/install/`

覆盖规则：

- 先记录将要覆盖的文件清单。
- 每个目标文件覆盖前记录旧文件 SHA256 和备份位置。
- 复制新文件时先写到同目录临时文件，再重命名为正式文件。
- 删除旧版本中存在但新发布包中不存在的文件，只允许删除发布包受管目录内的文件。
- 不删除保留列表内的任何文件。
- Windows 下如果文件被占用导致无法替换，立即停止并进入恢复状态。

### 7. 数据库迁移

文件覆盖完成后执行：

```php
MigrationRunner::runPending($currentVersion, $targetVersion)
```

迁移规则：

- 跨版本更新按 `database/migrations/` 目录自然排序执行。
- migration 失败后记录失败 SQL 文件、错误信息和当前版本。
- 没有 SQL migration 的补丁版本也必须写入 `schema_version` 和 `app_version`。
- 迁移失败不自动回滚数据库，因为 SQL 迁移通常不是完全可逆的。
- 迁移失败后进入恢复状态，管理员可以修复数据库后重试。

### 8. 成功收尾

全部成功后：

- 删除 `runtime/update/update.lock`。
- 写入 `runtime/update/last-success.json`。
- 删除临时下载和解压目录。
- 清理应用缓存。
- 返回成功结果：
  - `from_version`
  - `target_version`
  - `backup_path`
  - `migrations`
  - `completed_at`

前端提示管理员刷新后台。前端已有 chunk 加载恢复逻辑，刷新后会加载新构建资源。

### 9. 失败恢复

失败时写入：

```text
runtime/update/last-error.json
```

错误内容：

- `stage`
- `from_version`
- `target_version`
- `message`
- `backup_path`
- `failed_file`
- `failed_migration`
- `created_at`

恢复页面提供：

- 查看失败步骤。
- 查看备份包路径。
- 下载或复制备份路径。
- 如果失败发生在数据库迁移前，可以执行文件恢复。
- 如果失败发生在数据库迁移后，默认不自动恢复文件，避免代码版本和数据库结构继续错位；页面提示先根据失败 migration 修复数据库，再重试更新。

## 后端组件

### `app/service/update/GitHubReleaseClient.php`

职责：

- 请求 GitHub Release API。
- 支持超时。
- 支持可选 GitHub token。
- 解析 Release JSON。
- 只返回内部需要的字段。

### `app/service/update/UpdateReleaseService.php`

职责：

- 比较当前版本和最新版本。
- 筛选 zip 与 sha256 asset。
- 输出后台展示用 payload。

### `app/service/update/UpdatePreflightService.php`

职责：

- 运行更新前环境检查。
- 返回逐项检查结果。
- 不执行任何下载和覆盖。

### `app/service/update/UpdatePackageService.php`

职责：

- 下载 zip 和 sha256。
- 校验 digest。
- 解压到临时目录。
- 校验包结构与版本一致性。

### `app/service/update/UpdateBackupService.php`

职责：

- 创建文件备份 zip。
- 管理备份目录。
- 提供可恢复文件清单。

### `app/service/update/UpdateApplyService.php`

职责：

- 创建更新锁。
- 切换维护状态。
- 覆盖文件。
- 执行 migration。
- 清缓存。
- 写成功或失败状态。

### `app/controller/admin/Update.php`

职责：

- `check()`：检查最新版本。
- `preflight()`：环境预检。
- `start()`：开始更新。
- `status()`：查看进度和锁状态。
- `recover()`：查看恢复信息。

第一版可以继续挂在 `route/admin.php` 的登录保护组内。

## 前端组件

### API

新增：

```text
frontend/admin/src/api/admin/update.ts
```

接口：

- `checkUpdate()`
- `preflightUpdate()`
- `startUpdate()`
- `getUpdateStatus()`
- `getUpdateRecovery()`

### UI

在系统设置页新增 `SystemUpdateCard`：

- 当前版本。
- 最新版本。
- 检查更新时间。
- Release notes 简要展示。
- 预检结果列表。
- “检查更新”按钮。
- “开始更新”按钮。
- 更新中禁用其它更新操作。
- 失败后显示恢复说明。

后台仪表盘可以显示轻量提示条，但第一版不需要在仪表盘执行更新。

## 数据与文件

运行时目录：

```text
runtime/update/
runtime/update/downloads/
runtime/update/extracted/
runtime/update/backups/
runtime/update/update.lock
runtime/update/status.json
runtime/update/last-success.json
runtime/update/last-error.json
```

Release workflow 需要额外生成并上传：

```text
vpay-vX.Y.Z.zip.sha256
```

`release-manifest.json` 建议增加：

```json
{
  "name": "vpay",
  "version": "vX.Y.Z",
  "generated_at": "2026-04-25T00:00:00Z",
  "contains_vendor": true,
  "contains_console_build": true,
  "app_version": "X.Y.Z"
}
```

## 安全策略

- 只有已登录管理员可以检查和执行更新。
- 更新执行接口必须是 `POST`。
- 更新开始前必须先通过最新一次预检。
- 下载地址必须来自 GitHub Release API，不接受前端传入任意 URL。
- 只允许安装比当前版本高的版本。
- 不安装 draft 和 prerelease。
- zip 必须通过 SHA256 校验。
- 解压时拒绝包含 `../`、绝对路径或 Windows 盘符路径的 zip entry。
- 覆盖时所有目标路径必须位于项目根目录内。
- 保留 `.env` 和运行时目录。
- 更新锁防止并发执行。

## 测试策略

后端单元测试：

- Release JSON 能解析出最新正式版本。
- draft / prerelease 被忽略。
- 当前版本等于最新版本返回已是最新版。
- 当前版本高于远程版本禁止降级。
- 缺少 zip asset 返回错误。
- 缺少 sha256 asset 返回错误。
- sha256 不匹配拒绝安装。
- zip-slip 路径被拒绝。
- 包结构缺失被拒绝。
- 预检发现不可写目录时失败。
- 已有更新锁时不能开始更新。
- 备份排除 `runtime/`。
- 覆盖时保留 `.env`。
- 没有 SQL migration 的补丁更新会写入新版本。
- migration 失败会写 `last-error.json`。

后端集成测试：

- 使用临时项目目录模拟 `2.1.1 -> 2.1.2` 更新。
- 解压测试包、覆盖文件、执行 migration、清理锁。
- 模拟覆盖失败，验证保留错误状态。
- 模拟 migration 失败，验证恢复信息。

前端测试：

- 更新卡片显示当前版本和最新版本。
- 预检失败时禁用开始更新按钮。
- 更新中显示进度和锁定提示。
- 更新失败显示恢复说明。

手工验收：

- 在本地 phpEnv 环境用 Release zip 验证从旧版本升级。
- 验证 `.env` 未被覆盖。
- 验证 `public/index.html` 保留。
- 验证后台刷新后加载新前端资源。
- 验证更新失败后恢复页面可读。

## 风险与缓解

- 文件覆盖中断：通过更新锁、状态文件和备份包恢复。
- 数据库 migration 不可逆：不做自动数据库回滚，UI 要求更新前备份数据库。
- Windows 文件占用：复制失败立即停止，不继续覆盖后续文件。
- GitHub API 限流：支持可选 token，失败不影响业务。
- 前端缓存旧 chunk：沿用现有 chunk reload recovery，成功后提示刷新。
- 当前请求运行旧代码：更新执行过程只依赖当前已加载服务和 SQL migration，完成后提示刷新进入新代码。

## 实施顺序建议

1. 扩展 Release workflow，上传 `.sha256` 并在 manifest 写入 `app_version`。
2. 实现 Release 检测服务和后台检查接口。
3. 实现预检服务和后台预检接口。
4. 实现包下载、校验和 zip-slip 防护。
5. 实现备份服务。
6. 实现覆盖与迁移服务。
7. 实现状态、失败恢复和锁处理。
8. 实现后台系统更新卡片。
9. 增加集成测试和本地手工验收。
