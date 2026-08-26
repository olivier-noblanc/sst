<?php

use PHPUnit\Framework\TestCase;
use App\DTO\FlashMessage;

class FlashMessageTest extends TestCase
{
    public function testFromSessionWithIntTypeReturnsStringCast(): void
    {
        $data = ['type' => 42, 'message' => 'test message'];
        $flash = FlashMessage::fromSession($data);
        $this->assertSame('42', $flash->type);
        $this->assertSame('test message', $flash->message);
    }

    public function testFromSessionWithNullMessageReturnsEmptyString(): void
    {
        $data = ['type' => 'success', 'message' => null];
        $flash = FlashMessage::fromSession($data);
        $this->assertSame('success', $flash->type);
        $this->assertSame('', $flash->message);
    }

    public function testFromSessionWithIntMessageReturnsStringCast(): void
    {
        $data = ['type' => null, 'message' => 456];
        $flash = FlashMessage::fromSession($data);
        $this->assertSame('', $flash->type);
        $this->assertSame('456', $flash->message);
    }

    public function testFromSessionWithIntTypeAndNullMessage(): void
    {
        $data = ['type' => 123, 'message' => null];
        $flash = FlashMessage::fromSession($data);
        $this->assertSame('123', $flash->type);
        $this->assertSame('', $flash->message);
    }

    public function testFromSessionWithEmptyArrayReturnsEmptyStrings(): void
    {
        $data = [];
        $flash = FlashMessage::fromSession($data);
        $this->assertSame('', $flash->type);
        $this->assertSame('', $flash->message);
    }

    public function testFromSessionWithTypeAndMessage(): void
    {
        $data = ['type' => 'warning', 'message' => 'warning message'];
        $flash = FlashMessage::fromSession($data);
        $this->assertSame('warning', $flash->type);
        $this->assertSame('warning message', $flash->message);
    }
}
