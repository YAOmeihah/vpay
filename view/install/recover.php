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
    <p>步骤：<?= htmlspecialchars((string) ($context['step'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
    <p>错误信息：<?= htmlspecialchars((string) ($context['message'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
  </main>
</body>
</html>
