<?php
declare(strict_types=1);

namespace app\service\update;

use RuntimeException;

final class GitHubReleaseClient
{
    public function __construct(
        private readonly string $repository = 'YAOmeihah/vpay',
        private readonly int $timeoutSeconds = 10,
        private readonly string $token = ''
    ) {
    }

    public function latest(): array
    {
        $url = 'https://api.github.com/repos/' . $this->repository . '/releases/latest';
        $headers = [
            'Accept: application/vnd.github+json',
            'User-Agent: VPay-Updater',
        ];
        if ($this->token !== '') {
            $headers[] = 'Authorization: Bearer ' . $this->token;
        }

        $body = $this->request($url, $headers);
        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('GitHub Release 响应不是有效 JSON');
        }

        return $decoded;
    }

    private function request(string $url, array $headers): string
    {
        if (function_exists('curl_init')) {
            $curl = curl_init($url);
            curl_setopt_array($curl, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_CONNECTTIMEOUT => $this->timeoutSeconds,
                CURLOPT_TIMEOUT => $this->timeoutSeconds,
                CURLOPT_HTTPHEADER => $headers,
            ]);
            $body = curl_exec($curl);
            $error = curl_error($curl);
            $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            curl_close($curl);

            if (!is_string($body) || $body === '' || $status >= 400) {
                throw new RuntimeException('GitHub Release 请求失败: ' . ($error !== '' ? $error : 'HTTP ' . $status));
            }

            return $body;
        }

        $context = stream_context_create([
            'http' => [
                'timeout' => $this->timeoutSeconds,
                'header' => implode("\r\n", $headers),
            ],
        ]);
        $body = @file_get_contents($url, false, $context);
        if (!is_string($body) || $body === '') {
            throw new RuntimeException('GitHub Release 请求失败');
        }

        return $body;
    }
}
