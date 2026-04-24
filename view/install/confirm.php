<section>
  <h2>升级信息</h2>
  <?php if (($upgrade['errors'] ?? []) !== []): ?>
    <ul>
      <?php foreach (($upgrade['errors'] ?? []) as $error): ?>
        <li><?= htmlspecialchars((string) $error, ENT_QUOTES, 'UTF-8') ?></li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
  <p>当前版本：<?= htmlspecialchars((string) ($upgrade['current_version'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
  <p>目标版本：<?= htmlspecialchars((string) ($upgrade['target_version'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
  <h3>待执行 Migration</h3>
  <ul>
    <?php foreach (($upgrade['migrations'] ?? []) as $migration): ?>
      <li><?= htmlspecialchars((string) ($migration['relative_path'] ?? ''), ENT_QUOTES, 'UTF-8') ?></li>
    <?php endforeach; ?>
  </ul>
  <form method="post" action="/install/run">
    <h3>管理员确认</h3>
    <div>
      <label>
        管理员账号
        <input type="text" name="upgrade_admin_user" value="<?= htmlspecialchars((string) ($upgrade['admin_user'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" />
      </label>
    </div>
    <div>
      <label>
        管理员密码
        <input type="password" name="upgrade_admin_pass" value="" />
      </label>
    </div>
    <button type="submit" <?= !($upgrade['can_run'] ?? false) ? 'disabled' : '' ?>>确认升级并执行</button>
  </form>
</section>
