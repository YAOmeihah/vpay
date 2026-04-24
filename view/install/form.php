<section>
  <h2>数据库配置</h2>
  <?php if (($install['errors'] ?? []) !== []): ?>
    <ul>
      <?php foreach (($install['errors'] ?? []) as $error): ?>
        <li><?= htmlspecialchars((string) $error, ENT_QUOTES, 'UTF-8') ?></li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
  <form method="post" action="/install/run">
    <input type="hidden" name="env[APP_DEBUG]" value="<?= htmlspecialchars((string) (($install['env']['APP_DEBUG'] ?? 'false')), ENT_QUOTES, 'UTF-8') ?>" />
    <input type="hidden" name="env[DB_TYPE]" value="<?= htmlspecialchars((string) (($install['env']['DB_TYPE'] ?? 'mysql')), ENT_QUOTES, 'UTF-8') ?>" />
    <div>
      <label>
        数据库主机
        <input type="text" name="env[DB_HOST]" value="<?= htmlspecialchars((string) (($install['env']['DB_HOST'] ?? '')), ENT_QUOTES, 'UTF-8') ?>" />
      </label>
    </div>
    <div>
      <label>
        数据库名称
        <input type="text" name="env[DB_NAME]" value="<?= htmlspecialchars((string) (($install['env']['DB_NAME'] ?? '')), ENT_QUOTES, 'UTF-8') ?>" />
      </label>
    </div>
    <div>
      <label>
        数据库账号
        <input type="text" name="env[DB_USER]" value="<?= htmlspecialchars((string) (($install['env']['DB_USER'] ?? '')), ENT_QUOTES, 'UTF-8') ?>" />
      </label>
    </div>
    <div>
      <label>
        数据库密码
        <input type="password" name="env[DB_PASS]" value="" />
      </label>
    </div>
    <div>
      <label>
        数据库端口
        <input type="text" name="env[DB_PORT]" value="<?= htmlspecialchars((string) (($install['env']['DB_PORT'] ?? '3306')), ENT_QUOTES, 'UTF-8') ?>" />
      </label>
    </div>
    <div>
      <label>
        字符集
        <input type="text" name="env[DB_CHARSET]" value="<?= htmlspecialchars((string) (($install['env']['DB_CHARSET'] ?? 'utf8mb4')), ENT_QUOTES, 'UTF-8') ?>" />
      </label>
    </div>
    <div>
      <label>
        默认语言
        <input type="text" name="env[DEFAULT_LANG]" value="<?= htmlspecialchars((string) (($install['env']['DEFAULT_LANG'] ?? 'zh-cn')), ENT_QUOTES, 'UTF-8') ?>" />
      </label>
    </div>

    <h2>管理员配置</h2>
    <div>
      <label>
        管理员账号
        <input type="text" name="admin_user" value="<?= htmlspecialchars((string) (($install['admin_user'] ?? '')), ENT_QUOTES, 'UTF-8') ?>" />
      </label>
    </div>
    <div>
      <label>
        管理员密码
        <input type="password" name="admin_pass" value="<?= htmlspecialchars((string) (($install['admin_pass'] ?? '')), ENT_QUOTES, 'UTF-8') ?>" />
      </label>
    </div>
    <div>
      <label>
        确认管理员密码
        <input type="password" name="admin_pass_confirm" value="<?= htmlspecialchars((string) (($install['admin_pass_confirm'] ?? '')), ENT_QUOTES, 'UTF-8') ?>" />
      </label>
    </div>
    <button type="submit" <?= !($install['can_run'] ?? false) ? 'disabled' : '' ?>>确认安装并执行</button>
  </form>
</section>
