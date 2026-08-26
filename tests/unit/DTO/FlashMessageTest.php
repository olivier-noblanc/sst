<?php

use PHPUnit\Framework\TestCase;
use App\DTO\FlashMessage;

class FlashMessageTest extends TestCase
{
    public function testFromSessionWithIntTypeReturnsStringCast(): void
    {
        $data = ['type' => 42, 'message' => 'test message'];
        $flash = FlashMessage::fromSession($data);
        $this->assertEquals('42', $flash->type);
        $this->assertEquals('test message', $flash->message);
    }

    public function testFromSessionWithNullMessageReturnsEmptyString(): void
    {
        $data = ['type' => 'success', 'message' => null];
        $flash = FlashMessage::fromSession($data);
        $this->assertEquals('success', $flash->type);
        $this->assertEquals('', $flash->message);
    }

    public function testFromSessionWithEmptyArrayReturnsEmptyStrings(): void
    {
        $data = [];
        $flash = FlashMessage::fromSession($data);
        $this->assertEquals('', $flash->type);
        $this->assertEquals('', $flash->message);
    }

    public function testFromSessionWithTypeAndMessage(): void
    {
        $data = ['type' => 'warning', 'message' => 'warning message'];
        $flash = FlashMessage::fromSession($data);
        $this->assertEquals('warning', $flash->type);
        $this->assertEquals('warning message', $flash->message);
    }
}