<?php
require_once __DIR__ . DIRECTORY_SEPARATOR . '_helpers.php';

$stateName = (string) ($state['state'] ?? 'not_installed');
$isUpgrade = $stateName === 'upgrade_required';
$failedChecks = array_values(array_filter($checks, static fn (array $check): bool => ($check['ok'] ?? false) !== true));
$installShell = [
    'title' => $title,
    'state' => $stateName,
    'message' => (string) ($state['message'] ?? ''),
    'mode' => $isUpgrade ? 'upgrade' : 'install',
    'active_step' => $isUpgrade ? 1 : 0,
];
include __DIR__ . DIRECTORY_SEPARATOR . '_shell_start.php';
?>
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
