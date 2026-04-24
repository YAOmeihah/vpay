<?php
$hasEnvContent = trim((string) ($context['env']['content'] ?? '')) !== '';
$installShell = [
    'title' => $title,
    'state' => 'recovery_required',
    'message' => '安装或升级失败，等待恢复',
    'mode' => 'recovery',
    'active_step' => 1,
];
include __DIR__ . DIRECTORY_SEPARATOR . '_shell_start.php';
?>
<section class="installer-panel">
  <span class="installer-badge">需恢复</span>
  <h2><?= install_e($title) ?></h2>
  <div class="installer-alert" role="alert">
    <strong>失败步骤：</strong><?= install_e($context['step'] ?? '') ?><br />
    <strong>错误信息：</strong><?= install_e($context['message'] ?? '') ?>
  </div>
  <?php if ($hasEnvContent): ?>
    <div class="installer-panel" style="margin-top: 16px;">
      <h3>手工写入 `.env`</h3>
      <p>目标文件：<code><?= install_e($context['env']['path'] ?? '') ?></code></p>
      <p>手工写入以下内容：</p>
      <button class="installer-copy" type="button" data-copy-target="manual-env-content">复制配置内容</button>
      <pre id="manual-env-content" class="installer-code"><?= install_e($context['env']['content'] ?? '') ?></pre>
    </div>
  <?php endif; ?>
  <div class="installer-actions">
    <a class="installer-button" href="/install/check">返回检查页</a>
    <a class="installer-button installer-button--secondary" href="/install/recover">刷新恢复信息</a>
  </div>
</section>
<?php include __DIR__ . DIRECTORY_SEPARATOR . '_shell_end.php'; ?>
