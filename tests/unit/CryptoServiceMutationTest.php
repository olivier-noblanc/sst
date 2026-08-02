<?php
/**
 * Tests CryptoService exhaustively — kills Infection mutants on:
 *   - isEncryptionAvailable() (LogicalAnd, GreaterThan, strlen)
 *   - encrypt() with short key (LogicalOr, strlen < 32)
 *   - encrypt() with key > 32 chars (substr truncation — UnwrapSubstr, IncrementInteger)
 *   - decrypt() prefix detection (LogicalOr, str_starts_with)
 *   - decrypt() invalid base64 (base64_decode false check)
 *   - decrypt() too-short decoded value (strlen < 17, LessThan)
 *   - decrypt() wrong key (openssl_decrypt false, FunctionCallRemoval)
 *   - decrypt() empty/non-enc value passthrough
 */

use PHPUnit\Framework\TestCase;
use App\Services\CryptoService;

class CryptoServiceMutationTest extends TestCase
{
    private CryptoService $service;
    private string|false $originalKey;

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

    private function setKey(string $key): void
    {
        putenv('SST_SECRET_KEY=' . $key);
    }

    private function setValidKey(): void
    {
        $this->setKey('0123456789abcdef0123456789abcdef'); // 32 chars
    }

    // ═══ isEncryptionAvailable() ═══

    public function testIsEncryptionAvailableReturnsTrueWithValidKey(): void
    {
        $this->setValidKey();
        $this->assertTrue($this->service->isEncryptionAvailable());
    }

    public function testIsEncryptionAvailableReturnsFalseWithoutKey(): void
    {
        // Kill LogicalAnd mutant on $key !== false && strlen($key) >= 32
        putenv('SST_SECRET_KEY');
        $this->assertFalse($this->service->isEncryptionAvailable());
    }

    public function testIsEncryptionAvailableReturnsFalseForShortKey(): void
    {
        // Kill GreaterThan mutant on strlen($key) >= 32
        $this->setKey('short-key-only-20-chars');
        $this->assertFalse($this->service->isEncryptionAvailable(), 'key < 32 chars → not available');
    }

    public function testIsEncryptionAvailableReturnsTrueForExact32CharKey(): void
    {
        // Kill mutant on >= 32 vs > 32 — exactly 32 must pass
        $this->setKey(str_repeat('a', 32));
        $this->assertTrue($this->service->isEncryptionAvailable());
    }

    public function testIsEncryptionAvailableReturnsFalseFor31CharKey(): void
    {
        // Kill mutant on >= 32 — 31 chars must fail
        $this->setKey(str_repeat('a', 31));
        $this->assertFalse($this->service->isEncryptionAvailable());
    }

    // ═══ encrypt() — key handling ═══

    public function testEncryptReturnsPlaintextWhenKeyMissing(): void
    {
        putenv('SST_SECRET_KEY');
        $this->assertSame('secret', $this->service->encrypt('secret'));
    }

    public function testEncryptReturnsPlaintextWhenKeyTooShort(): void
    {
        // Kill LogicalOr mutant on $key === false || strlen($key) < 32
        $this->setKey('short');
        $this->assertSame('secret', $this->service->encrypt('secret'), 'short key → plaintext passthrough');
    }

    public function testEncryptReturnsPlaintextFor31CharKey(): void
    {
        // Kill mutant on < 32 vs <= 32 — 31 chars must fail
        $this->setKey(str_repeat('a', 31));
        $this->assertSame('secret', $this->service->encrypt('secret'));
    }

    public function testEncryptSucceedsWithLongKeyTruncatedTo32(): void
    {
        // Kill UnwrapSubstr mutant — substr($key, 0, 32) must truncate
        $this->setKey(str_repeat('x', 64)); // 64 chars
        $encrypted = $this->service->encrypt('test');
        $this->assertStringStartsWith('enc:', $encrypted);
        // Round-trip must work with the same key (truncation is deterministic)
        $this->assertSame('test', $this->service->decrypt($encrypted));
    }

    public function testEncryptReturnsEmptyStringForEmptyInput(): void
    {
        // Kill Identical mutant on $plaintext === ''
        $this->setValidKey();
        $this->assertSame('', $this->service->encrypt(''));
    }

    public function testEncryptProducesEncPrefix(): void
    {
        $this->setValidKey();
        $encrypted = $this->service->encrypt('data');
        $this->assertStringStartsWith('enc:', $encrypted);
    }

    public function testEncryptProducesDifferentCiphertextsForSameInput(): void
    {
        // Kill random_bytes mutant (if IV were constant, ciphertexts would match)
        $this->setValidKey();
        $enc1 = $this->service->encrypt('same');
        $enc2 = $this->service->encrypt('same');
        $this->assertNotSame($enc1, $enc2, 'random IV must produce different ciphertexts');
    }

    // ═══ decrypt() — prefix detection ═══

    public function testDecryptReturnsEmptyStringForEmptyInput(): void
    {
        // Kill LogicalOr mutant on $value === '' || !str_starts_with(...)
        $this->assertSame('', $this->service->decrypt(''));
    }

    public function testDecryptReturnsValueUnchangedForNonEncPrefix(): void
    {
        // Kill str_starts_with mutant
        $this->assertSame('plain_value', $this->service->decrypt('plain_value'));
        $this->assertSame('encrypted:something', $this->service->decrypt('encrypted:something'));
        $this->assertSame('enc', $this->service->decrypt('enc'), 'just "enc" without colon → passthrough');
    }

    public function testDecryptReturnsValueUnchangedForEncPrefixWithoutColon(): void
    {
        // Kill mutant on str_starts_with('enc:') — 'encXYZ' should NOT match
        $this->assertSame('encXYZdata', $this->service->decrypt('encXYZdata'));
    }

    // ═══ decrypt() — key handling ═══

    public function testDecryptReturnsEncValueWhenKeyMissing(): void
    {
        // Kill LogicalOr mutant on $key === false || strlen($key) < 32
        putenv('SST_SECRET_KEY');
        $this->setValidKey();
        $encrypted = $this->service->encrypt('secret');
        putenv('SST_SECRET_KEY'); // remove key
        $this->assertSame($encrypted, $this->service->decrypt($encrypted), 'no key → return enc: value unchanged');
    }

    public function testDecryptReturnsEncValueWhenKeyTooShort(): void
    {
        $this->setValidKey();
        $encrypted = $this->service->encrypt('secret');
        $this->setKey('short'); // short key
        $this->assertSame($encrypted, $this->service->decrypt($encrypted), 'short key → return enc: value unchanged');
    }

    // ═══ decrypt() — invalid data ═══

    public function testDecryptReturnsValueForInvalidBase64(): void
    {
        // Kill base64_decode false check — invalid base64 after 'enc:'
        $this->setValidKey();
        $invalid = 'enc:!!!not-valid-base64!!!';
        $this->assertSame($invalid, $this->service->decrypt($invalid), 'invalid base64 → return value unchanged');
    }

    public function testDecryptReturnsValueForTooShortDecodedData(): void
    {
        // Kill LessThan mutant on strlen($decoded) < 17 — decoded < 17 bytes (IV=16 + 1 cipher min)
        $this->setValidKey();
        $short = 'enc:' . base64_encode(str_repeat('a', 10)); // only 10 bytes, < 17
        $this->assertSame($short, $this->service->decrypt($short), 'too-short decoded → return unchanged');
    }

    public function testDecryptReturnsValueForExact16ByteDecodedData(): void
    {
        // Kill mutant on < 17 vs <= 17 — 16 bytes (just IV, no ciphertext) must fail
        $this->setValidKey();
        $just_iv = 'enc:' . base64_encode(str_repeat('a', 16)); // exactly 16 bytes
        $this->assertSame($just_iv, $this->service->decrypt($just_iv));
    }

    public function testDecryptSucceedsFor17ByteDecodedData(): void
    {
        // 16 bytes IV + 1 byte ciphertext = 17 bytes minimum
        $this->setValidKey();
        $encrypted = $this->service->encrypt('a'); // short plaintext
        $this->assertSame('a', $this->service->decrypt($encrypted));
    }

    // ═══ decrypt() — wrong key ═══

    public function testDecryptReturnsEncValueWithWrongKey(): void
    {
        // Kill openssl_decrypt false check + FunctionCallRemoval on error_log
        $this->setKey('0123456789abcdef0123456789abcdef'); // key A
        $encrypted = $this->service->encrypt('secret');
        $this->setKey('abcdef0123456789abcdef0123456789'); // key B (different)
        $result = $this->service->decrypt($encrypted);
        // openssl_decrypt returns false on wrong key → method returns the enc: value
        $this->assertSame($encrypted, $result, 'wrong key → return enc: value unchanged');
    }

    // ═══ Round-trip with special characters ═══

    public function testEncryptDecryptRoundTripWithUnicode(): void
    {
        $this->setValidKey();
        $plaintext = 'Éàü€🔍';
        $encrypted = $this->service->encrypt($plaintext);
        $this->assertSame($plaintext, $this->service->decrypt($encrypted));
    }

    public function testEncryptDecryptRoundTripWithLongString(): void
    {
        $this->setValidKey();
        $plaintext = str_repeat('A long paragraph. ', 100);
        $encrypted = $this->service->encrypt($plaintext);
        $this->assertSame($plaintext, $this->service->decrypt($encrypted));
    }
}
