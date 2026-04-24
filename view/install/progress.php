<?php
$installShell = [
    'title' => $title,
    'state' => 'locked',
    'message' => $message,
    'mode' => 'progress',
    'active_step' => 2,
];
include __DIR__ . DIRECTORY_SEPARATOR . '_shell_start.php';
?>
<section class="installer-panel">
  <span class="installer-badge">执行中</span>
  <h2><?= install_e($title) ?></h2>
  <p>当前状态：<?= install_e($message) ?></p>
  <div class="installer-alert installer-alert--warning">
    正在执行，请勿关闭或刷新页面。若页面长时间无响应，请稍后访问恢复页查看状态。
  </div>
  <?php if ($steps !== []): ?>
    <ol>
      <?php foreach ($steps as $step): ?>
        <li><?= install_e($step) ?></li>
      <?php endforeach; ?>
    </ol>
  <?php else: ?>
    <p>执行尚未接入实时进度，系统会在操作结束后返回结果。</p>
  <?php endif; ?>
</section>
<?php include __DIR__ . DIRECTORY_SEPARATOR . '_shell_end.php'; ?>
