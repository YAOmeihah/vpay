<?php
require_once __DIR__ . DIRECTORY_SEPARATOR . '_helpers.php';

$shell = install_shell_context($installShell ?? []);
$steps = install_steps_for_mode($shell['mode']);
$activeStep = min($shell['active_step'], max(0, count($steps) - 1));
?>
<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= install_e($shell['title']) ?></title>
  <style>
    :root {
      --installer-bg: #f0fdfa;
      --installer-surface: rgba(255, 255, 255, 0.94);
      --installer-surface-solid: #ffffff;
      --installer-text: #0f172a;
      --installer-muted: #475569;
      --installer-border: #dbeafe;
      --installer-primary: #0f766e;
      --installer-primary-strong: #115e59;
      --installer-action: #0369a1;
      --installer-danger: #b91c1c;
      --installer-warning: #b45309;
      --installer-success: #047857;
      --installer-shadow: 0 24px 80px rgba(15, 118, 110, 0.16);
      color-scheme: light;
      font-family: "Noto Sans SC", "Microsoft YaHei", "PingFang SC", "Hiragino Sans GB", sans-serif;
    }
    * { box-sizing: border-box; }
    body {
      margin: 0;
      min-height: 100vh;
      color: var(--installer-text);
      background:
        radial-gradient(circle at top left, rgba(20, 184, 166, 0.24), transparent 34rem),
        radial-gradient(circle at bottom right, rgba(3, 105, 161, 0.16), transparent 30rem),
        linear-gradient(135deg, #f8fafc 0%, var(--installer-bg) 100%);
    }
    a { color: var(--installer-action); }
    .installer-shell {
      width: min(1120px, calc(100% - 32px));
      margin: 0 auto;
      padding: 40px 0;
    }
    .installer-hero {
      display: grid;
      grid-template-columns: 1fr auto;
      gap: 24px;
      align-items: start;
      margin-bottom: 24px;
    }
    .installer-kicker {
      margin: 0 0 8px;
      color: var(--installer-primary-strong);
      font-size: 14px;
      font-weight: 700;
      letter-spacing: 0.08em;
      text-transform: uppercase;
    }
    .installer-title {
      margin: 0;
      font-size: clamp(30px, 5vw, 52px);
      line-height: 1.05;
      letter-spacing: -0.04em;
    }
    .installer-message {
      margin: 16px 0 0;
      max-width: 680px;
      color: var(--installer-muted);
      font-size: 17px;
      line-height: 1.8;
    }
    .installer-state-badge,
    .installer-badge {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      min-height: 34px;
      padding: 7px 12px;
      border: 1px solid rgba(15, 118, 110, 0.22);
      border-radius: 999px;
      color: var(--installer-primary-strong);
      background: rgba(240, 253, 250, 0.92);
      font-size: 13px;
      font-weight: 700;
      white-space: nowrap;
    }
    .installer-card {
      border: 1px solid rgba(148, 163, 184, 0.24);
      border-radius: 28px;
      background: var(--installer-surface);
      box-shadow: var(--installer-shadow);
      backdrop-filter: blur(16px);
      overflow: hidden;
    }
    .installer-steps {
      display: grid;
      grid-template-columns: repeat(var(--step-count), minmax(0, 1fr));
      gap: 10px;
      padding: 22px;
      border-bottom: 1px solid rgba(148, 163, 184, 0.22);
      background: rgba(255, 255, 255, 0.74);
    }
    .installer-step {
      position: relative;
      display: flex;
      align-items: center;
      gap: 10px;
      min-width: 0;
      color: var(--installer-muted);
      font-size: 14px;
      font-weight: 700;
    }
    .installer-step-number {
      display: grid;
      width: 30px;
      height: 30px;
      flex: 0 0 30px;
      place-items: center;
      border-radius: 50%;
      color: var(--installer-muted);
      background: #e2e8f0;
      font-size: 13px;
    }
    .installer-step.is-active { color: var(--installer-primary-strong); }
    .installer-step.is-active .installer-step-number {
      color: #ffffff;
      background: linear-gradient(135deg, var(--installer-primary), var(--installer-action));
    }
    .installer-step.is-complete .installer-step-number {
      color: #ffffff;
      background: var(--installer-success);
    }
    .installer-main {
      padding: clamp(20px, 4vw, 36px);
    }
    .installer-grid {
      display: grid;
      grid-template-columns: minmax(260px, 0.85fr) minmax(320px, 1.15fr);
      gap: 24px;
      align-items: start;
    }
    .installer-panel {
      border: 1px solid rgba(148, 163, 184, 0.22);
      border-radius: 22px;
      background: var(--installer-surface-solid);
      padding: 22px;
    }
    .installer-panel h2,
    .installer-panel h3 {
      margin: 0 0 14px;
      letter-spacing: -0.02em;
    }
    .installer-panel p {
      color: var(--installer-muted);
      line-height: 1.75;
    }
    .installer-actions {
      display: flex;
      flex-wrap: wrap;
      gap: 12px;
      margin-top: 22px;
    }
    .installer-button {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-height: 44px;
      padding: 10px 18px;
      border: 0;
      border-radius: 14px;
      color: #ffffff;
      background: linear-gradient(135deg, var(--installer-primary), var(--installer-action));
      box-shadow: 0 12px 24px rgba(3, 105, 161, 0.18);
      cursor: pointer;
      font-weight: 800;
      text-decoration: none;
      transition: transform 180ms ease, box-shadow 180ms ease, opacity 180ms ease;
    }
    .installer-button:hover { transform: translateY(-1px); box-shadow: 0 16px 30px rgba(3, 105, 161, 0.22); }
    .installer-button:disabled { cursor: not-allowed; opacity: 0.55; transform: none; box-shadow: none; }
    .installer-button--secondary {
      color: var(--installer-primary-strong);
      background: #ecfeff;
      border: 1px solid rgba(15, 118, 110, 0.18);
      box-shadow: none;
    }
    .installer-alert {
      border-radius: 18px;
      padding: 16px;
      border: 1px solid rgba(185, 28, 28, 0.2);
      color: var(--installer-danger);
      background: #fef2f2;
      line-height: 1.7;
    }
    .installer-alert--warning {
      border-color: rgba(180, 83, 9, 0.24);
      color: #92400e;
      background: #fffbeb;
    }
    .installer-alert--success {
      border-color: rgba(4, 120, 87, 0.24);
      color: var(--installer-success);
      background: #ecfdf5;
    }
    .installer-field {
      display: grid;
      gap: 8px;
      margin-top: 16px;
    }
    .installer-field label {
      color: #1e293b;
      font-weight: 800;
    }
    .installer-field input {
      width: 100%;
      min-height: 44px;
      border: 1px solid #cbd5e1;
      border-radius: 14px;
      padding: 10px 12px;
      color: var(--installer-text);
      background: #ffffff;
      font: inherit;
    }
    .installer-field input:focus,
    .installer-button:focus-visible,
    .installer-copy:focus-visible {
      outline: 3px solid rgba(20, 184, 166, 0.34);
      outline-offset: 2px;
    }
    .installer-hint {
      margin: 0;
      color: var(--installer-muted);
      font-size: 13px;
      line-height: 1.6;
    }
    .installer-password-row {
      display: grid;
      grid-template-columns: minmax(0, 1fr) auto;
      gap: 8px;
    }
    .installer-copy,
    .installer-password-toggle {
      min-height: 44px;
      border: 1px solid #cbd5e1;
      border-radius: 14px;
      color: var(--installer-primary-strong);
      background: #f8fafc;
      cursor: pointer;
      font-weight: 800;
    }
    .installer-code {
      overflow: auto;
      max-height: 360px;
      border-radius: 18px;
      padding: 16px;
      color: #d1fae5;
      background: #0f172a;
      line-height: 1.7;
    }
    @media (prefers-reduced-motion: reduce) {
      *, *::before, *::after {
        animation-duration: 0.001ms !important;
        transition-duration: 0.001ms !important;
        scroll-behavior: auto !important;
      }
    }
    @media (max-width: 760px) {
      .installer-shell {
        width: min(100% - 20px, 1120px);
        padding: 20px 0;
      }
      .installer-hero,
      .installer-grid {
        grid-template-columns: 1fr;
      }
      .installer-steps {
        grid-template-columns: 1fr;
      }
      .installer-step {
        padding: 8px 0;
      }
    }
  </style>
</head>
<body>
  <main class="installer-shell" data-install-state="<?= install_e($shell['state']) ?>">
    <header class="installer-hero">
      <div>
        <p class="installer-kicker">VPay Installer</p>
        <h1 class="installer-title">VPay 安装向导</h1>
        <p class="installer-message"><?= install_e($shell['message']) ?></p>
      </div>
      <span class="installer-state-badge"><?= install_e(install_state_label($shell['state'])) ?></span>
    </header>
    <section class="installer-card" aria-label="<?= install_e($shell['title']) ?>">
      <nav class="installer-steps" style="--step-count: <?= count($steps) ?>" aria-label="安装步骤">
        <?php foreach ($steps as $index => $step): ?>
          <div class="installer-step <?= $index === $activeStep ? 'is-active' : ($index < $activeStep ? 'is-complete' : '') ?>">
            <span class="installer-step-number"><?= $index + 1 ?></span>
            <span><?= install_e($step) ?></span>
          </div>
        <?php endforeach; ?>
      </nav>
      <div class="installer-main">
