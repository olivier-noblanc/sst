<?php

use PHPUnit\Framework\TestCase;
use App\DTO\RegistryCardData;

class RegistryCardDataTest extends TestCase
{
    public function testCreateWithDefaultCountReturnsZero(): void
    {
        $card = RegistryCardData::create('rsst');
        $this->assertSame(0, $card->count);
    }

    public function testCreateWithExplicitCountReturnsExplicitValue(): void
    {
        $card = RegistryCardData::create('rsst', count: 5);
        $this->assertSame(5, $card->count);
    }

    public function testCreateWithDefaultCardClassBuildsFromType(): void
    {
        $card = RegistryCardData::create('violences');
        $this->assertSame('registry-card--violences', $card->cardClass);
    }

    public function testCreateWithExplicitCardClassPreservesIt(): void
    {
        $card = RegistryCardData::create('rsst', cardClass: 'custom-card');
        $this->assertSame('custom-card', $card->cardClass);
    }
}
