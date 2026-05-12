<?php
require_once __DIR__ . DIRECTORY_SEPARATOR . '_helpers.php';

$stateName = (string) ($state['state'] ?? 'not_installed');
$isUpgrade = $stateName === 'upgrade_required';
$failedChecks = array_values(array_filter($checks, static fn (array $check): bool => ($check['ok'] ?? false) !== true));
$lastError = is_array($lastError ?? null) ? $lastError : [];
$hasLastError = trim((string) ($lastError['message'] ?? '')) !== '';
$hasLastErrorEnv = trim((string) ($lastError['env']['content'] ?? '')) !== '';
$installShell = [
    'title' => $title,
    'state' => $stateName,
    'message' => (string) ($state['message'] ?? ''),
    'mode' => $isUpgrade ? 'upgrade' : 'install',
    'active_step' => $isUpgrade ? 1 : 0,
];
include __DIR__ . DIRECTORY_SEPARATOR . '_shell_start.php';
?>
<?php if ($hasLastError): ?>
  <section class="installer-panel" style="margin-bottom: 18px;">
    <span class="installer-badge">上次失败</span>
    <h2>上次执行失败</h2>
    <div class="installer-alert" role="alert">
      <strong>失败步骤：</strong><?= install_e($lastError['step'] ?? '') ?><br />
      <strong>错误信息：</strong><?= install_e($lastError['message'] ?? '') ?>
    </div>
    <?php if ($hasLastErrorEnv): ?>
      <div class="installer-panel" style="margin-top: 16px;">
        <h3>手工写入 `.env`</h3>
        <p>目标文件：<code><?= install_e($lastError['env']['path'] ?? '') ?></code></p>
        <p>手工写入以下内容：</p>
        <button class="installer-copy" type="button" data-copy-target="manual-env-content">复制配置内容</button>
        <pre id="manual-env-content" class="installer-code"><?= install_e($lastError['env']['content'] ?? '') ?></pre>
      </div>
    <?php endif; ?>
  </section>
<?php endif; ?>
<div class="installer-grid">
  <aside class="installer-panel" aria-label="环境检查结果">
    <h2>环境检查</h2>
    <p><?= $failedChecks === [] ? '服务器环境已满足执行条件，可以继续。' : '存在未通过的环境项，修复后刷新此页重新检查。' ?></p>
    <div class="installer-check-list">
      <?php foreach ($checks as $check): ?>
        <div class="installer-panel" style="margin-top: 12px; padding: 14px;">
          <strong><?= install_e((string) $check['label']) ?></strong>
          <span class="installer-badge"><?= ($check['ok'] ?? false) ? '通过' : '失败' ?></span>
        </div>
      <?php endforeach; ?>
    </div>
    <?php if ($failedChecks !== []): ?>
      <div class="installer-alert" role="alert" style="margin-top: 16px;">
        请先修复失败的 PHP 扩展或版本要求，再刷新本页面继续。
      </div>
    <?php endif; ?>
  </aside>
  <section class="installer-panel">
    <h2><?= $isUpgrade ? '升级确认' : '安装配置' ?></h2>
    <p><?= $isUpgrade ? '确认版本变更并验证管理员身份后执行数据库 Migration。' : '填写数据库连接和管理员账号后开始安装。' ?></p>
    <?php if ($stateName === 'not_installed'): ?>
      <?php include __DIR__ . DIRECTORY_SEPARATOR . 'form.php'; ?>
    <?php endif; ?>
    <?php if ($stateName === 'upgrade_required'): ?>
      <?php include __DIR__ . DIRECTORY_SEPARATOR . 'confirm.php'; ?>
    <?php endif; ?>
  </section>
</div>
<?php include __DIR__ . DIRECTORY_SEPARATOR . '_shell_end.php'; ?>
