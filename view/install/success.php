<?php
$isUpgrade = ($result['status'] ?? '') === 'upgraded';
$installShell = [
    'title' => $title ?? '完成',
    'state' => 'installed',
    'message' => $isUpgrade ? '升级流程已完成。' : '安装流程已完成。',
    'mode' => $isUpgrade ? 'upgrade' : 'install',
    'active_step' => 4,
];
include __DIR__ . DIRECTORY_SEPARATOR . '_shell_start.php';
?>
<section class="installer-panel">
  <div class="installer-alert installer-alert--success">
    <strong><?= $isUpgrade ? '升级已完成' : '安装已完成' ?></strong>
  </div>
  <h2><?= install_e($title ?? '完成') ?></h2>
  <?php if ($isUpgrade): ?>
    <p>升级前版本：<?= install_e($result['from_version'] ?? '') ?></p>
    <p>升级后版本：<?= install_e($result['to_version'] ?? '') ?></p>
    <p>执行数量：<?= count($result['migrations'] ?? []) ?></p>
  <?php else: ?>
    <p>管理员账号：<?= install_e($result['admin_user'] ?? '') ?></p>
    <p>环境配置文件：<?= install_e($result['env']['path'] ?? '') ?></p>
  <?php endif; ?>
  <div class="installer-actions">
    <a class="installer-button" href="/console/">进入管理后台</a>
  </div>
</section>
<?php include __DIR__ . DIRECTORY_SEPARATOR . '_shell_end.php'; ?>
