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
    <?php if (trim((string) ($context['env']['content'] ?? '')) !== ''): ?>
      <p>目标文件：<?= htmlspecialchars((string) ($context['env']['path'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
      <p>手工写入以下内容：</p>
      <pre><?= htmlspecialchars((string) ($context['env']['content'] ?? ''), ENT_QUOTES, 'UTF-8') ?></pre>
    <?php endif; ?>
  </main>
</body>
</html>
