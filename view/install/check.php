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
    <ul>
      <?php foreach ($checks as $check): ?>
        <li>
          <?= htmlspecialchars((string) $check['label'], ENT_QUOTES, 'UTF-8') ?>:
          <?= $check['ok'] ? '通过' : '失败' ?>
        </li>
      <?php endforeach; ?>
    </ul>
  </main>
</body>
</html>
