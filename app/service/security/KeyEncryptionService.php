<?php
declare(strict_types=1);

namespace app\service\security;

/**
 * AES-256-GCM encryption for sensitive setting values (e.g. merchant sign key).
 * Master key is read from APP_KEY env var (64 hex chars = 32 bytes).
 *
 * Ciphertext format: base64( iv[12] . tag[16] . ciphertext )
 * Prefix "enc:" distinguishes encrypted values from legacy plaintext.
 */
class KeyEncryptionService
{
    private const CIPHER    = 'aes-256-gcm';
    private const IV_LEN    = 12;
    private const TAG_LEN   = 16;
    private const PREFIX    = 'enc:';

    public function encrypt(string $plaintext): string
    {
        $masterKey = $this->masterKey();
        $iv = random_bytes(self::IV_LEN);
        $tag = '';

        $ciphertext = openssl_encrypt(
            $plaintext,
            self::CIPHER,
            $masterKey,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            self::TAG_LEN
        );

        if ($ciphertext === false) {
            throw new \RuntimeException('签名密钥加密失败');
        }

        return self::PREFIX . base64_encode($iv . $tag . $ciphertext);
    }

    public function decrypt(string $value): string
    {
        if (!str_starts_with($value, self::PREFIX)) {
            // Legacy plaintext — return as-is so existing installs keep working.
            return $value;
        }

        $raw = base64_decode(substr($value, strlen(self::PREFIX)), true);
        if ($raw === false || strlen($raw) <= self::IV_LEN + self::TAG_LEN) {
            throw new \RuntimeException('签名密钥格式无效');
        }

        $iv         = substr($raw, 0, self::IV_LEN);
        $tag        = substr($raw, self::IV_LEN, self::TAG_LEN);
        $ciphertext = substr($raw, self::IV_LEN + self::TAG_LEN);

        $plaintext = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            $this->masterKey(),
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($plaintext === false) {
            throw new \RuntimeException('签名密钥解密失败，请检查 APP_KEY 是否正确');
        }

        return $plaintext;
    }

    public function isEncrypted(string $value): bool
    {
        return str_starts_with($value, self::PREFIX);
    }

    private function masterKey(): string
    {
        $hex = trim((string) env('APP_KEY', ''));

        if ($hex === '') {
            throw new \RuntimeException('APP_KEY 未配置，无法保护签名密钥');
        }

        if (!ctype_xdigit($hex) || strlen($hex) !== 64) {
            throw new \RuntimeException('APP_KEY 格式无效，需为 64 位十六进制字符串');
        }

        return hex2bin($hex);
    }
}
