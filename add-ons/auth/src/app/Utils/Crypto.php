<?php

declare(strict_types=1);

namespace app\Utils;

use Random\RandomException;
use RuntimeException;
use SensitiveParameter;
use SodiumException;

/**
 * Authenticated symmetric encryption for data at rest, keyed by APP_KEY. Built on libsodium
 * secretbox, so a tampered, truncated or wrong-key payload fails to decrypt instead of
 * returning garbage.
 */
class Crypto
{
    /**
     * Encrypt a string into a base64 token safe to store in a text column.
     *
     * @param string $plaintext
     *
     * @return string
     * @throws SodiumException|RandomException
     */
    public static function encrypt(#[SensitiveParameter] string $plaintext): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        return base64_encode($nonce . sodium_crypto_secretbox($plaintext, $nonce, self::key()));
    }

    /**
     * The raw 32-byte key from APP_KEY, cached per request.
     *
     * @return string
     *
     * @throws RuntimeException When APP_KEY is missing or invalid.
     */
    private static function key(): string
    {
        static $key = null;
        if ($key !== null) return $key;

        $decoded = is_string(APP_KEY) && strlen(APP_KEY) === SODIUM_CRYPTO_SECRETBOX_KEYBYTES * 2
            ? @hex2bin(APP_KEY)
            : false;

        if ($decoded === false || strlen($decoded) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            throw new RuntimeException('APP_KEY is missing or invalid. Run "composer key:regenerate".');
        }

        return $key = $decoded;
    }

    /**
     * Decrypt a token from encrypt(), or null when it is malformed or fails authentication.
     *
     * @param string $payload
     *
     * @return string|null
     * @throws SodiumException
     */
    public static function decrypt(#[SensitiveParameter] string $payload): ?string
    {
        $decoded = base64_decode($payload, true);

        if ($decoded === false || strlen($decoded) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) return null;

        $nonce = substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plaintext = sodium_crypto_secretbox_open(substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $nonce, self::key());

        return $plaintext === false ? null : $plaintext;
    }
}
