<?php
declare(strict_types=1);

namespace app\service\notification;

use app\service\maintenance\MaintenanceConfig;

class TelegramNotificationChannel
{
    public function send(string $message): bool
    {
        $config = $this->config();
        $token = $config->telegramBotToken();
        $chatId = $config->telegramChatId();

        if (!$config->telegramEnabled() || $token === '' || $chatId === '' || !function_exists('curl_init')) {
            return false;
        }

        $curl = curl_init('https://api.telegram.org/bot' . $token . '/sendMessage');
        if ($curl === false) {
            return false;
        }

        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode([
                'chat_id' => $chatId,
                'text' => $message,
                'disable_web_page_preview' => true,
            ], JSON_UNESCAPED_UNICODE),
        ]);

        curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);

        return $status >= 200 && $status < 300;
    }

    protected function config(): MaintenanceConfig
    {
        return app()->make(MaintenanceConfig::class);
    }
}
