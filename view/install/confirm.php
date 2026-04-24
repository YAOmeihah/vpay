<?php require_once __DIR__ . DIRECTORY_SEPARATOR . '_helpers.php'; ?>
<section>
  <?php if (($upgrade['errors'] ?? []) !== []): ?>
    <div class="installer-alert" role="alert" data-error-summary>
      <strong>升级前需要处理以下问题：</strong>
      <ul>
        <?php foreach (($upgrade['errors'] ?? []) as $error): ?>
          <li><?= install_e($error) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <div class="installer-alert installer-alert--warning">
    <strong>升级前请先完成备份。</strong>
    请确认已经备份旧站点代码、旧数据库和旧 `.env` 文件。升级会执行待升级版本对应的数据库 Migration。
  </div>

  <div class="installer-panel" style="margin-top: 16px;">
    <h3>版本变更</h3>
    <p>
      当前版本：<strong><?= install_e($upgrade['current_version'] ?? '') ?></strong>
      <span aria-hidden="true"> → </span>
      目标版本：<strong><?= install_e($upgrade['target_version'] ?? '') ?></strong>
    </p>
  </div>

  <div class="installer-panel" style="margin-top: 16px;">
    <h3>待执行 Migration</h3>
    <?php if (($upgrade['migrations'] ?? []) === []): ?>
      <p>当前没有待执行的 Migration。</p>
    <?php else: ?>
      <ul>
        <?php foreach (($upgrade['migrations'] ?? []) as $migration): ?>
          <li><code><?= install_e($migration['relative_path'] ?? '') ?></code></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>

  <form method="post" action="/install/run" data-install-form>
    <h3 style="margin-top: 28px;">管理员验证</h3>
    <div class="installer-field">
      <label for="upgrade-admin-user">管理员账号</label>
      <input id="upgrade-admin-user" type="text" name="upgrade_admin_user" value="<?= install_e($upgrade['admin_user'] ?? '') ?>" autocomplete="username" />
    </div>
    <div class="installer-field">
      <label for="upgrade-admin-pass">管理员密码</label>
      <div class="installer-password-row">
        <input id="upgrade-admin-pass" type="password" name="upgrade_admin_pass" value="" autocomplete="current-password" />
        <button class="installer-password-toggle" type="button" data-password-toggle="upgrade-admin-pass">显示</button>
      </div>
    </div>
    <div class="installer-actions">
      <button class="installer-button" type="submit" data-loading-text="正在升级，请勿刷新" <?= !($upgrade['can_run'] ?? false) ? 'disabled' : '' ?>>
        确认升级并执行
      </button>
    </div>
  </form>
</section>
