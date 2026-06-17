<?php
/**
 * Crypto Helper Unit Tests — Application SST DREETS BFC
 *
 * Tests encryption/decryption from src/helpers/crypto.php:
 * - encryptConfigValue()
 * - decryptConfigValue()
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/helpers/crypto.php';

class CryptoHelperTest extends TestCase
{
    private string $originalKey;

    protected function setUp(): void
    {
        // Save the original SST_SECRET_KEY if set
        $this->originalKey = getenv('SST_SECRET_KEY') ?: '';
    }

    protected function tearDown(): void
    {
        // Restore the original key
        if ($this->originalKey !== '') {
            putenv("SST_SECRET_KEY=$this->originalKey");
        } else {
            putenv('SST_SECRET_KEY');
        }
    }

    private function setSecretKey(string $key): void
    {
        putenv("SST_SECRET_KEY=$key");
    }

    private function removeSecretKey(): void
    {
        putenv('SST_SECRET_KEY');
    }

    // ─── encryptConfigValue ─────────────────────────────────────────────────

    public function testEncryptEmptyStringReturnsEmpty(): void
    {
        $this->setSecretKey('this-is-a-32-byte-key-for-testing!!');
        $this->assertEquals('', encryptConfigValue(''));
    }

    public function testEncryptReturnsEncPrefixWhenKeyAvailable(): void
    {
        $this->setSecretKey('this-is-a-32-byte-key-for-testing!!');
        $result = encryptConfigValue('mypassword');
        $this->assertStringStartsWith('enc:', $result);
    }

    public function testEncryptReturnsPlaintextWhenNoKey(): void
    {
        $this->removeSecretKey();
        $result = encryptConfigValue('mypassword');
        $this->assertEquals('mypassword', $result);
    }

    public function testEncryptReturnsPlaintextWhenKeyTooShort(): void
    {
        $this->setSecretKey('short');
        $result = encryptConfigValue('mypassword');
        $this->assertEquals('mypassword', $result);
    }

    public function testEncryptProducesDifferentCiphertextEachTime(): void
    {
        $this->setSecretKey('this-is-a-32-byte-key-for-testing!!');
        $enc1 = encryptConfigValue('same-input');
        $enc2 = encryptConfigValue('same-input');
        // Different IV each time means different ciphertext
        $this->assertNotEquals($enc1, $enc2);
    }

    // ─── decryptConfigValue ─────────────────────────────────────────────────

    public function testDecryptEmptyStringReturnsEmpty(): void
    {
        $this->assertEquals('', decryptConfigValue(''));
    }

    public function testDecryptNonEncryptedValueReturnsAsIs(): void
    {
        $this->assertEquals('plaintext', decryptConfigValue('plaintext'));
    }

    public function testEncryptDecryptRoundTrip(): void
    {
        $this->setSecretKey('this-is-a-32-byte-key-for-testing!!');
        $original = 'MySMTPPassword123!';
        $encrypted = encryptConfigValue($original);
        $decrypted = decryptConfigValue($encrypted);
        $this->assertEquals($original, $decrypted);
    }

    public function testEncryptDecryptRoundTripWithUnicode(): void
    {
        $this->setSecretKey('this-is-a-32-byte-key-for-testing!!');
        $original = 'Mot de passe avec accents : éèêëàâ';
        $encrypted = encryptConfigValue($original);
        $decrypted = decryptConfigValue($encrypted);
        $this->assertEquals($original, $decrypted);
    }

    public function testDecryptWithWrongKeyReturnsEncryptedValue(): void
    {
        $this->setSecretKey('this-is-a-32-byte-key-for-testing!!');
        $encrypted = encryptConfigValue('secret-data');

        // Switch to a different key
        $this->setSecretKey('different-32-byte-key-for-testing!!');
        $result = decryptConfigValue($encrypted);

        // Should return the encrypted value as-is (can't decrypt with wrong key)
        $this->assertEquals($encrypted, $result);
    }

    public function testDecryptWithNoKeyReturnsEncryptedValue(): void
    {
        $this->setSecretKey('this-is-a-32-byte-key-for-testing!!');
        $encrypted = encryptConfigValue('secret-data');

        $this->removeSecretKey();
        $result = decryptConfigValue($encrypted);

        // Should return the encrypted value as-is
        $this->assertEquals($encrypted, $result);
    }

    public function testDecryptInvalidBase64ReturnsAsIs(): void
    {
        $this->setSecretKey('this-is-a-32-byte-key-for-testing!!');
        $result = decryptConfigValue('enc:not-valid-base64!!!');
        $this->assertEquals('enc:not-valid-base64!!!', $result);
    }
}
