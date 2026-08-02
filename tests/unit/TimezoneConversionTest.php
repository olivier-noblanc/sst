<?php
/**
 * Timezone Conversion Tests — Application SST DREETS BFC
 *
 * Verifies that formatDateTimeFR() correctly converts UTC datetimes
 * from SQLite to Europe/Paris for display.
 */

use PHPUnit\Framework\TestCase;
use App\Services\FormattingService;

class TimezoneConversionTest extends TestCase
{
    private FormattingService $service;

    protected function setUp(): void
    {
        $this->service = new FormattingService();
    }

    public function testFormatDateTimeFRConvertsUtcToParis(): void
    {
        // 2025-01-15 10:00:00 UTC = 2025-01-15 11:00:00 CET (winter, +1h)
        $this->assertEquals('15/01/2025 à 11:00', $this->service->formatDateTimeFR('2025-01-15 10:00:00'));
    }

    public function testFormatDateTimeFRConvertsUtcToParisSummer(): void
    {
        // 2025-07-15 10:00:00 UTC = 2025-07-15 12:00:00 CEST (summer, +2h)
        $this->assertEquals('15/07/2025 à 12:00', $this->service->formatDateTimeFR('2025-07-15 10:00:00'));
    }

    public function testFormatDateTimeFRReturnsDashForEmpty(): void
    {
        $this->assertEquals('—', $this->service->formatDateTimeFR(''));
        $this->assertEquals('—', $this->service->formatDateTimeFR(null));
    }

    public function testFormatDateTimeFRRetainsFallbackForInvalid(): void
    {
        $this->assertEquals('not-a-date', $this->service->formatDateTimeFR('not-a-date'));
    }
}
