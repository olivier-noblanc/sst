<?php
/**
 * SiteId Value Object Tests — Application SST DREETS BFC
 */

use PHPUnit\Framework\TestCase;
use App\DTO\SiteId;

class SiteIdTest extends TestCase
{
    public function testFromInputZeroBecomesNone(): void
    {
        $siteId = SiteId::fromInput(0);
        $this->assertTrue($siteId->isNone());
        $this->assertNull($siteId->toSql());
    }

    public function testFromInputPositiveValueIsKept(): void
    {
        $siteId = SiteId::fromInput(5);
        $this->assertFalse($siteId->isNone());
        $this->assertSame(5, $siteId->toSql());
    }

    public function testFromInputNegativeBecomesNone(): void
    {
        // Defensive: a negative site_id is never valid, treat like "none"
        // rather than persist a value the CHECK constraint would reject.
        $siteId = SiteId::fromInput(-1);
        $this->assertTrue($siteId->isNone());
    }

    public function testFromDatabaseNullStaysNull(): void
    {
        $siteId = SiteId::fromDatabase(null);
        $this->assertTrue($siteId->isNone());
        $this->assertNull($siteId->toNullableInt());
    }

    public function testFromDatabasePositiveValueIsKept(): void
    {
        $siteId = SiteId::fromDatabase(7);
        $this->assertFalse($siteId->isNone());
        $this->assertSame(7, $siteId->toNullableInt());
    }

    public function testFromDatabaseZeroBecomesNone(): void
    {
        // Defensive: a legacy row with a literal 0 (pre-CHECK-constraint
        // database) should not resurrect as a fake site — same treatment
        // as null.
        $siteId = SiteId::fromDatabase(0);
        $this->assertTrue($siteId->isNone());
    }

    public function testNoneFactoryIsAlwaysNone(): void
    {
        $siteId = SiteId::none();
        $this->assertTrue($siteId->isNone());
        $this->assertNull($siteId->toSql());
        $this->assertNull($siteId->toNullableInt());
    }
}
