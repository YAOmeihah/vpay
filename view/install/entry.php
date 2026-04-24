<?php
require_once __DIR__ . DIRECTORY_SEPARATOR . '_helpers.php';

$installShell = [
    'title' => $title,
    'state' => $state,
    'message' => $message,
    'mode' => install_mode_from_state((string) $state),
    'active_step' => 0,
];
include __DIR__ . DIRECTORY_SEPARATOR . '_shell_start.php';
?>
<section class="installer-panel">
  <span class="installer-badge"><?= install_e(install_state_label((string) $state)) ?></span>
  <h2><?= install_e($title) ?></h2>
  <p data-install-state="<?= install_e($state) ?>">
    <?= install_e($message) ?>
  </p>
  <p>
    安装前会先检查服务器环境；升级前会确认版本、Migration 和管理员身份。
  </p>
  <?php if ($actions !== []): ?>
    <div class="installer-actions">
      <?php foreach ($actions as $action): ?>
        <a class="installer-button" href="<?= install_e($action['href']) ?>">
          <?= install_e($action['label']) ?>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>
<?php include __DIR__ . DIRECTORY_SEPARATOR . '_shell_end.php'; ?>
