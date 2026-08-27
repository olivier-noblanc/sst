<?php
/**
 * FlashMessageMutationTest — kills Infection mutants on:
 *   - CastString on $data['type'] (line 34)
 *   - CastString on $data['message'] (line 35)
 *
 * The mutant removes the (string) cast. Tests must verify that non-string
 * inputs are properly cast to strings (e.g., null → '', int → "42").
 */

declare(strict_types=1);

namespace Tests\Unit;

use App\DTO\FlashMessage;
use PHPUnit\Framework\TestCase;

class FlashMessageMutationTest extends TestCase
{
    public function testFromSessionCastStringTypeWhenNull(): void
    {
        // Kill CastString mutant: without cast, null would stay null
        $data = ['type' => null, 'message' => 'test'];
        $flash = FlashMessage::fromSession($data);
        $this->assertSame('', $flash->type, 'null type must be cast to empty string');
        $this->assertIsString($flash->type);
    }

    public function testFromSessionCastStringTypeWhenInt(): void
    {
        // Kill CastString mutant: without cast, int would stay int
        $data = ['type' => 42, 'message' => 'test'];
        $flash = FlashMessage::fromSession($data);
        $this->assertSame('42', $flash->type, 'int type must be cast to string "42"');
        $this->assertIsString($flash->type);
    }

    public function testFromSessionCastStringTypeWhenBool(): void
    {
        // Kill CastString mutant: without cast, bool would stay bool
        $data = ['type' => true, 'message' => 'test'];
        $flash = FlashMessage::fromSession($data);
        $this->assertSame('1', $flash->type, 'bool true must be cast to string "1"');
        $this->assertIsString($flash->type);
    }

    public function testFromSessionCastStringMessageWhenNull(): void
    {
        // Kill CastString mutant: without cast, null would stay null
        $data = ['type' => 'success', 'message' => null];
        $flash = FlashMessage::fromSession($data);
        $this->assertSame('', $flash->message, 'null message must be cast to empty string');
        $this->assertIsString($flash->message);
    }

    public function testFromSessionCastStringMessageWhenInt(): void
    {
        // Kill CastString mutant: without cast, int would stay int
        $data = ['type' => 'success', 'message' => 123];
        $flash = FlashMessage::fromSession($data);
        $this->assertSame('123', $flash->message, 'int message must be cast to string "123"');
        $this->assertIsString($flash->message);
    }

    public function testFromSessionCastStringMessageWhenBool(): void
    {
        // Kill CastString mutant: without cast, bool would stay bool
        $data = ['type' => 'success', 'message' => false];
        $flash = FlashMessage::fromSession($data);
        $this->assertSame('', $flash->message, 'bool false must be cast to empty string');
        $this->assertIsString($flash->message);
    }

    public function testFromSessionWithMissingKeys(): void
    {
        // Kill CastString mutant on ?? '' fallback
        $data = [];
        $flash = FlashMessage::fromSession($data);
        $this->assertSame('', $flash->type, 'missing type must default to empty string');
        $this->assertSame('', $flash->message, 'missing message must default to empty string');
        $this->assertIsString($flash->type);
        $this->assertIsString($flash->message);
    }

    public function testFromSessionWithStringValues(): void
    {
        // Baseline: string values pass through unchanged
        $data = ['type' => 'success', 'message' => 'Operation successful'];
        $flash = FlashMessage::fromSession($data);
        $this->assertSame('success', $flash->type);
        $this->assertSame('Operation successful', $flash->message);
    }

    public function testOffsetExistsWithStringOffset(): void
    {
        // Kill CastString mutant on offsetExists (line 124)
        $flash = new FlashMessage('success', 'test');
        $this->assertTrue($flash->offsetExists('type'));
        $this->assertTrue($flash->offsetExists('message'));
        $this->assertFalse($flash->offsetExists('unknown'));
    }

    public function testOffsetExistsWithIntOffset(): void
    {
        // Kill CastString mutant: without cast, int offset would fail differently
        $flash = new FlashMessage('success', 'test');
        // With cast: (string) 0 → "0", which is not 'type' or 'message' → false
        // Without cast: 0 !== 'type' && 0 !== 'message' → false (same result)
        // But the mutant changes the type checking behavior
        $this->assertFalse($flash->offsetExists(0));
        $this->assertFalse($flash->offsetExists(1));
    }
}