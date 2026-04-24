<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= htmlspecialchars($title ?? '完成', ENT_QUOTES, 'UTF-8') ?></title>
</head>
<body>
  <main>
    <h1><?= htmlspecialchars($title ?? '完成', ENT_QUOTES, 'UTF-8') ?></h1>
    <?php if (($result['status'] ?? '') === 'upgraded'): ?>
      <p>升级流程已完成。</p>
      <p>升级前版本：<?= htmlspecialchars((string) ($result['from_version'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
      <p>升级后版本：<?= htmlspecialchars((string) ($result['to_version'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
      <p>执行数量：<?= count($result['migrations'] ?? []) ?></p>
    <?php else: ?>
      <p>安装流程已完成。</p>
      <p>管理员账号：<?= htmlspecialchars((string) ($result['admin_user'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
      <p>环境配置文件：<?= htmlspecialchars((string) (($result['env']['path'] ?? '')), ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
  </main>
</body>
</html>
