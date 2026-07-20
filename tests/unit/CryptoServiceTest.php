<?php
/**
 * CryptoService Unit Tests — AES-256-CBC encrypt/decrypt
 *
 * Tests CryptoService from src/Services/CryptoService.php:
 * - encrypt()/decrypt() round-trip with SST_SECRET_KEY
 * - encrypt() of empty string returns empty
 * - Different inputs produce different ciphertexts
 * - decrypt() of non-encrypted value returns it unchanged
 */

use PHPUnit\Framework\TestCase;
use App\Services\CryptoService;

class CryptoServiceTest extends TestCase
{
    private CryptoService $service;
    private string $originalKey;

    protected function setUp(): void
    {
        $this->service = new CryptoService();
        $this->originalKey = getenv('SST_SECRET_KEY');
    }

    protected function tearDown(): void
    {
        if ($this->originalKey !== false) {
            putenv('SST_SECRET_KEY=' . $this->originalKey);
        } else {
            putenv('SST_SECRET_KEY');
        }
    }

    private function setTestKey(): void
    {
        putenv('SST_SECRET_KEY=0123456789abcdef0123456789abcdef');
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // encrypt() / decrypt() round-trip
    // ═══════════════════════════════════════════════════════════════════════════════

    public function testEncryptDecryptRoundTrip(): void
    {
        $this->setTestKey();
        $plaintext = 'Mot de passe secret';
        $encrypted = $this->service->encrypt($plaintext);
        $this->assertNotEquals($plaintext, $encrypted);
        $this->assertStringStartsWith('enc:', $encrypted);
        $this->assertEquals($plaintext, $this->service->decrypt($encrypted));
    }

    public function testEncryptReturnsEmptyStringForEmptyInput(): void
    {
        $this->assertEquals('', $this->service->encrypt(''));
    }

    public function testDecryptReturnsEmptyStringForEmptyInput(): void
    {
        $this->assertEquals('', $this->service->decrypt(''));
    }

    public function testDifferentInputsProduceDifferentCiphertexts(): void
    {
        $this->setTestKey();
        $enc1 = $this->service->encrypt('value_a');
        $enc2 = $this->service->encrypt('value_b');
        $this->assertNotEquals($enc1, $enc2);
    }

    public function testSameInputProducesDifferentCiphertextsDueToRandomIv(): void
    {
        $this->setTestKey();
        $enc1 = $this->service->encrypt('same_value');
        $enc2 = $this->service->encrypt('same_value');
        $this->assertNotEquals($enc1, $enc2);
        $this->assertEquals('same_value', $this->service->decrypt($enc1));
        $this->assertEquals('same_value', $this->service->decrypt($enc2));
    }

    public function testDecryptNonEncryptedValueReturnsUnchanged(): void
    {
        $this->assertEquals('plain_value', $this->service->decrypt('plain_value'));
    }

    public function testEncryptWithoutKeyReturnsPlaintext(): void
    {
        putenv('SST_SECRET_KEY');
        $plaintext = 'no_key_here';
        $encrypted = $this->service->encrypt($plaintext);
        $this->assertEquals($plaintext, $encrypted);
    }

    public function testDecryptWithoutKeyReturnsEncPrefixUnchanged(): void
    {
        putenv('SST_SECRET_KEY');
        $fake = 'enc:' . base64_encode('some-data-that-is-long-enough-to-pass-the-length-check');
        $this->assertEquals($fake, $this->service->decrypt($fake));
    }

    public function testEncryptSpecialCharactersRoundTrip(): void
    {
        $this->setTestKey();
        $plaintext = 'Caractères spéciaux: éàùç €';
        $encrypted = $this->service->encrypt($plaintext);
        $this->assertEquals($plaintext, $this->service->decrypt($encrypted));
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // Service instantiation
    // ═══════════════════════════════════════════════════════════════════════════════

    public function testServiceCanBeInstantiated(): void
    {
        $service = new CryptoService();
        $this->assertInstanceOf(CryptoService::class, $service);
    }
}
