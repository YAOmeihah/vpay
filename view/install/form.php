<?php require_once __DIR__ . DIRECTORY_SEPARATOR . '_helpers.php'; ?>
<section>
  <?php if (($install['errors'] ?? []) !== []): ?>
    <div class="installer-alert" role="alert" data-error-summary>
      <strong>请修复以下问题后再继续：</strong>
      <ul>
        <?php foreach (($install['errors'] ?? []) as $error): ?>
          <li><?= install_e($error) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>
  <form method="post" action="/install/run" data-install-form>
    <input type="hidden" name="env[APP_DEBUG]" value="<?= install_e($install['env']['APP_DEBUG'] ?? 'false') ?>" />
    <input type="hidden" name="env[DB_TYPE]" value="<?= install_e($install['env']['DB_TYPE'] ?? 'mysql') ?>" />

    <h3>数据库配置</h3>
    <p class="installer-hint">数据库连接信息用于初始化 VPay 数据表。</p>
    <div class="installer-field">
      <label for="install-db-host">数据库主机</label>
      <input id="install-db-host" type="text" name="env[DB_HOST]" value="<?= install_e($install['env']['DB_HOST'] ?? '') ?>" autocomplete="off" />
      <p class="installer-hint">通常为 127.0.0.1；云数据库请填写内网或公网连接地址。</p>
    </div>
    <div class="installer-field">
      <label for="install-db-name">数据库名称</label>
      <input id="install-db-name" type="text" name="env[DB_NAME]" value="<?= install_e($install['env']['DB_NAME'] ?? '') ?>" autocomplete="off" />
    </div>
    <div class="installer-field">
      <label for="install-db-user">数据库账号</label>
      <input id="install-db-user" type="text" name="env[DB_USER]" value="<?= install_e($install['env']['DB_USER'] ?? '') ?>" autocomplete="off" />
    </div>
    <div class="installer-field">
      <label for="install-db-pass">数据库密码</label>
      <div class="installer-password-row">
        <input id="install-db-pass" type="password" name="env[DB_PASS]" value="" autocomplete="new-password" />
        <button class="installer-password-toggle" type="button" data-password-toggle="install-db-pass">显示</button>
      </div>
      <p class="installer-hint">为了安全，提交失败后不会回填数据库密码。</p>
    </div>
    <div class="installer-field">
      <label for="install-db-port">数据库端口</label>
      <input id="install-db-port" type="text" name="env[DB_PORT]" value="<?= install_e($install['env']['DB_PORT'] ?? '3306') ?>" inputmode="numeric" />
    </div>
    <div class="installer-field">
      <label for="install-db-charset">字符集</label>
      <input id="install-db-charset" type="text" name="env[DB_CHARSET]" value="<?= install_e($install['env']['DB_CHARSET'] ?? 'utf8mb4') ?>" />
    </div>
    <div class="installer-field">
      <label for="install-default-lang">默认语言</label>
      <input id="install-default-lang" type="text" name="env[DEFAULT_LANG]" value="<?= install_e($install['env']['DEFAULT_LANG'] ?? 'zh-cn') ?>" />
    </div>

    <h3 style="margin-top: 28px;">管理员账号</h3>
    <div class="installer-field">
      <label for="install-admin-user">管理员账号</label>
      <input id="install-admin-user" type="text" name="admin_user" value="<?= install_e($install['admin_user'] ?? '') ?>" autocomplete="username" />
    </div>
    <div class="installer-field">
      <label for="install-admin-pass">管理员密码</label>
      <div class="installer-password-row">
        <input id="install-admin-pass" type="password" name="admin_pass" value="" autocomplete="new-password" />
        <button class="installer-password-toggle" type="button" data-password-toggle="install-admin-pass">显示</button>
      </div>
    </div>
    <div class="installer-field">
      <label for="install-admin-pass-confirm">确认管理员密码</label>
      <div class="installer-password-row">
        <input id="install-admin-pass-confirm" type="password" name="admin_pass_confirm" value="" autocomplete="new-password" />
        <button class="installer-password-toggle" type="button" data-password-toggle="install-admin-pass-confirm">显示</button>
      </div>
    </div>
    <div class="installer-actions">
      <button class="installer-button" type="submit" data-loading-text="正在安装，请勿刷新" <?= !($install['can_run'] ?? false) ? 'disabled' : '' ?>>
        确认安装并执行
      </button>
    </div>
  </form>
</section>
