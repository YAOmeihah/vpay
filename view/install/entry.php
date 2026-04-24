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
    <p data-install-state="<?= htmlspecialchars($state, ENT_QUOTES, 'UTF-8') ?>">
      <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
    </p>
    <?php foreach ($actions as $action): ?>
      <a href="<?= htmlspecialchars($action['href'], ENT_QUOTES, 'UTF-8') ?>">
        <?= htmlspecialchars($action['label'], ENT_QUOTES, 'UTF-8') ?>
      </a>
    <?php endforeach; ?>
  </main>
</body>
</html>
