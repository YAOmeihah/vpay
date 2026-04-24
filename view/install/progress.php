<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></title>
</head>
<body>
  <main>
    <h1><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h1>
    <p>当前状态：<?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>
    <?php if ($steps !== []): ?>
      <ul>
        <?php foreach ($steps as $step): ?>
          <li><?= htmlspecialchars((string) $step, ENT_QUOTES, 'UTF-8') ?></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </main>
</body>
</html>
