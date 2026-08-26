<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class CryptoTest extends TestCase
{
    public function test_encrypt_and_decrypt_round_trip(): void
    {
        $plain = 'LICENSE-ABC-123';
        $encrypted = Lacvo_Core_Crypto::encrypt($plain);

        self::assertStringStartsWith('v1:', $encrypted);
        self::assertNotSame($plain, $encrypted);
        self::assertSame($plain, Lacvo_Core_Crypto::decrypt($encrypted));
    }

    public function test_encryption_uses_a_fresh_nonce(): void
    {
        $plain = 'LICENSE-ABC-123';

        self::assertNotSame(
            Lacvo_Core_Crypto::encrypt($plain),
            Lacvo_Core_Crypto::encrypt($plain)
        );
    }

    public function test_fingerprint_is_deterministic_without_exposing_plaintext(): void
    {
        $plain = 'LICENSE-ABC-123';
        $fingerprint = Lacvo_Core_Crypto::fingerprint($plain);

        self::assertSame($fingerprint, Lacvo_Core_Crypto::fingerprint($plain));
        self::assertSame(64, strlen($fingerprint));
        self::assertStringNotContainsString($plain, $fingerprint);
    }
}
