<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?></title>
    <style>
        :root {
            --bg-start: #eef6f0;
            --bg-end: #f6f7fb;
            --card-bg: rgba(255, 255, 255, 0.94);
            --card-border: rgba(15, 23, 42, 0.08);
            --text-strong: #122033;
            --text-base: #314158;
            --text-muted: #66758b;
            --accent: #1677ff;
            --accent-dark: #0958d9;
            --danger: #e5484d;
            --shadow-lg: 0 24px 80px rgba(16, 24, 40, 0.14);
            --radius-xl: 28px;
            --radius-lg: 22px;
        }

        * { box-sizing: border-box; }

        html, body { min-height: 100%; }

        body {
            margin: 0;
            font-family: "Segoe UI", "PingFang SC", "Hiragino Sans GB", "Microsoft YaHei", sans-serif;
            color: var(--text-base);
            background:
                radial-gradient(circle at top left, rgba(7, 193, 96, 0.14), transparent 32%),
                radial-gradient(circle at top right, rgba(22, 119, 255, 0.12), transparent 28%),
                linear-gradient(180deg, var(--bg-start), var(--bg-end));
        }

        button { font: inherit; }

        .payment-error-shell {
            min-height: 100vh;
            padding: 28px 20px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .payment-error-card {
            width: min(520px, 100%);
            padding: 32px 32px 30px;
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-lg);
            backdrop-filter: blur(18px);
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .payment-error-card::after {
            content: "";
            position: absolute;
            inset: auto -68px -72px auto;
            width: 220px;
            height: 220px;
            border-radius: 50%;
            background:
                radial-gradient(circle, rgba(229, 72, 77, 0.08) 0%, rgba(229, 72, 77, 0.02) 48%, rgba(229, 72, 77, 0) 74%);
            pointer-events: none;
        }

        .payment-error-header {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 18px;
            margin-bottom: 20px;
            position: relative;
            z-index: 1;
        }

        .payment-error-main {
            position: relative;
            z-index: 1;
        }

        .payment-error-heading-group {
            min-width: 0;
            position: relative;
            z-index: 1;
        }

        .payment-error-kicker {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 18px;
            padding: 10px 14px;
            border-radius: 999px;
            background: rgba(7, 193, 96, 0.10);
            color: #05964d;
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 0.08em;
        }

        .payment-error-kicker::before {
            content: "";
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #07c160;
            box-shadow: 0 0 0 4px rgba(7, 193, 96, 0.16);
        }

        .payment-error-icon {
            width: 92px;
            height: 92px;
            margin: 0;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(180deg, rgba(229, 72, 77, 0.14), rgba(255, 243, 242, 0.98));
            box-shadow: inset 0 0 0 1px rgba(229, 72, 77, 0.14);
        }

        .payment-error-icon svg {
            width: 42px;
            height: 42px;
            display: block;
            stroke: var(--danger);
            stroke-width: 2;
            fill: none;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .payment-error-title {
            margin: 0 0 12px;
            font-size: 32px;
            line-height: 1.15;
            color: var(--text-strong);
        }

        .payment-error-message {
            margin: 0 0 10px;
            font-size: 17px;
            line-height: 1.6;
            color: var(--text-base);
        }

        .payment-error-help {
            margin: 0 auto 26px;
            max-width: 360px;
            font-size: 14px;
            line-height: 1.7;
            color: var(--text-muted);
            position: relative;
            z-index: 1;
        }

        .payment-error-button {
            min-width: 180px;
            border: 0;
            border-radius: 16px;
            padding: 14px 24px;
            color: #fff;
            background: linear-gradient(180deg, var(--accent), var(--accent-dark));
            box-shadow: 0 16px 28px rgba(22, 119, 255, 0.22);
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease, filter 0.2s ease;
            position: relative;
            z-index: 1;
        }

        .payment-error-button:hover {
            transform: translateY(-1px);
            box-shadow: 0 20px 32px rgba(22, 119, 255, 0.26);
            filter: saturate(1.03);
        }

        .payment-error-button:focus-visible {
            outline: 3px solid rgba(22, 119, 255, 0.22);
            outline-offset: 3px;
        }

        .payment-error-watermark {
            display: none;
            position: absolute;
            right: 42px;
            top: 50%;
            width: 220px;
            height: 220px;
            transform: translateY(-50%);
            border-radius: 36px;
            align-items: center;
            justify-content: center;
            background:
                radial-gradient(circle at 30% 30%, rgba(255, 255, 255, 0.86), rgba(255, 242, 243, 0.72)),
                linear-gradient(180deg, rgba(255, 248, 248, 0.74), rgba(255, 242, 243, 0.48));
            border: 1px solid rgba(229, 72, 77, 0.08);
            box-shadow:
                inset 0 1px 0 rgba(255, 255, 255, 0.92),
                0 12px 24px rgba(229, 72, 77, 0.05);
            pointer-events: none;
            z-index: 0;
        }

        .payment-error-watermark::before {
            content: "";
            position: absolute;
            inset: 22px;
            border-radius: 28px;
            background: rgba(255, 255, 255, 0.3);
        }

        .payment-error-watermark svg {
            position: relative;
            z-index: 1;
            width: 84px;
            height: 84px;
            display: block;
            stroke: var(--danger);
            stroke-width: 1.8;
            fill: none;
            stroke-linecap: round;
            stroke-linejoin: round;
            opacity: 0.7;
        }

        @media (min-width: 768px) {
            .payment-error-card {
                width: min(820px, 100%);
                padding: 38px 42px 36px;
                text-align: left;
            }

            .payment-error-content {
                position: relative;
                z-index: 1;
                max-width: 470px;
            }

            .payment-error-header {
                flex-direction: row;
                align-items: flex-start;
                gap: 0;
                margin-bottom: 18px;
            }

            .payment-error-icon {
                display: none;
            }

            .payment-error-kicker {
                margin-bottom: 12px;
            }

            .payment-error-heading-group {
                flex: 1;
                max-width: none;
                padding-right: 0;
            }

            .payment-error-title,
            .payment-error-message,
            .payment-error-help {
                text-align: left;
                margin-left: 0;
                margin-right: 0;
            }

            .payment-error-title {
                margin-bottom: 10px;
                font-size: 36px;
            }

            .payment-error-message {
                margin-bottom: 0;
                font-size: 18px;
            }

            .payment-error-help {
                max-width: none;
                margin-bottom: 30px;
                padding-left: 0;
                padding-right: 32px;
            }

            .payment-error-button {
                margin-left: 0;
            }

            .payment-error-watermark {
                display: flex;
            }
        }

        @media (max-width: 640px) {
            .payment-error-shell {
                padding: 18px 14px;
            }

            .payment-error-card {
                padding: 24px 20px 22px;
                border-radius: var(--radius-lg);
            }

            .payment-error-card::after {
                width: 160px;
                height: 160px;
                inset: auto -46px -50px auto;
            }

            .payment-error-header {
                margin-bottom: 18px;
                gap: 16px;
            }

            .payment-error-kicker {
                margin-bottom: 0;
                padding: 9px 12px;
                font-size: 12px;
            }

            .payment-error-icon {
                width: 82px;
                height: 82px;
            }

            .payment-error-icon svg {
                width: 38px;
                height: 38px;
            }

            .payment-error-title {
                font-size: 28px;
            }

            .payment-error-message {
                font-size: 16px;
            }

            .payment-error-button {
                width: 100%;
                min-width: 0;
                min-height: 50px;
            }
        }
    </style>
</head>
<body>
    <div class="payment-error-shell">
        <main class="payment-error-card" role="alert" aria-live="polite">
            <div class="payment-error-main">
                <div class="payment-error-content">
                    <div class="payment-error-header">
                        <div class="payment-error-icon" aria-hidden="true">
                            <svg viewBox="0 0 48 48" focusable="false">
                                <path d="M24 7 11 12v9c0 8.5 5.6 15.3 13 17.7C31.4 36.3 37 29.5 37 21v-9L24 7Z"></path>
                                <path d="M24 17v8"></path>
                                <path d="M24 30.5h.01"></path>
                            </svg>
                        </div>
                        <div class="payment-error-heading-group">
                            <div class="payment-error-kicker">安全收银台</div>
                            <h1 class="payment-error-title"><?= $title ?></h1>
                            <p class="payment-error-message"><?= $message ?></p>
                        </div>
                    </div>
                    <p class="payment-error-help"><?= $helpText ?></p>
                    <button class="payment-error-button" type="button" onclick="history.back()"><?= $buttonText ?></button>
                </div>
            </div>
            <div class="payment-error-watermark" aria-hidden="true">
                <svg viewBox="0 0 48 48" focusable="false">
                    <path d="M24 7 11 12v9c0 8.5 5.6 15.3 13 17.7C31.4 36.3 37 29.5 37 21v-9L24 7Z"></path>
                    <path d="M24 17v8"></path>
                    <path d="M24 30.5h.01"></path>
                </svg>
            </div>
        </main>
    </div>
</body>
</html>
