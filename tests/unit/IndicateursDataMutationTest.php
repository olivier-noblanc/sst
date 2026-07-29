<?php
/**
 * Tests IndicateursData exhaustively — kills Infection mutants on:
 *   - DecrementInteger / IncrementInteger on default values (lines 15, 16)
 *   - UnwrapStrReplace on str_replace('-', '_', ...) (line 23)
 *   - Concat / ConcatOperandRemoval on 'total_' . $code (line 23)
 *   - Coalesce on ?? 0 (line 23)
 */

use PHPUnit\Framework\TestCase;
use App\DTO\IndicateursData;

class IndicateursDataMutationTest extends TestCase
{
    public function testDefaultTotalsAreZeroWhenNotProvided(): void
    {
        $d = new IndicateursData(
            totalReports: 10,
            totalNouveau: 2,
            totalEnCours: 3,
            totalTraite: 4,
        );
        // Kill DecrementInteger/IncrementInteger mutants — defaults MUST be exactly 0, not -1 or 1.
        $this->assertSame(0, $d->totalAbandonne, 'totalAbandonne default must be 0');
        $this->assertSame(0, $d->totalReouvert, 'totalReouvert default must be 0');
        $this->assertSame([], $d->registryTotals, 'registryTotals default must be empty array');
    }

    public function testExplicitValuesPropagated(): void
    {
        $d = new IndicateursData(
            totalReports: 10,
            totalNouveau: 2,
            totalEnCours: 3,
            totalTraite: 4,
            totalAbandonne: 1,
            totalReouvert: 5,
            registryTotals: ['total_rsst' => 8],
        );
        $this->assertSame(1, $d->totalAbandonne);
        $this->assertSame(5, $d->totalReouvert);
        $this->assertSame(['total_rsst' => 8], $d->registryTotals);
    }

    public function testGetRegistryTotalForSimpleCode(): void
    {
        $d = new IndicateursData(
            totalReports: 0, totalNouveau: 0, totalEnCours: 0, totalTraite: 0,
            registryTotals: ['total_rsst' => 5, 'total_rami' => 3, 'total_dgi' => 2],
        );
        // Kill Concat mutant — key must be 'total_rsst', not 'rsst' or 'total_rsst_rsst'
        $this->assertSame(5, $d->getRegistryTotal('rsst'));
        $this->assertSame(3, $d->getRegistryTotal('rami'));
        $this->assertSame(2, $d->getRegistryTotal('dgi'));
    }

    public function testGetRegistryTotalForCustomCodeWithHyphen(): void
    {
        $d = new IndicateursData(
            totalReports: 0, totalNouveau: 0, totalEnCours: 0, totalTraite: 0,
            registryTotals: ['total_harcelement_sexuel' => 7],
        );
        // Kill UnwrapStrReplace mutant — 'harcelement-sexuel' must become 'harcelement_sexuel'
        $this->assertSame(7, $d->getRegistryTotal('harcelement-sexuel'), 'hyphen must be replaced with underscore');
    }

    public function testGetRegistryTotalReturnsZeroForUnknownCode(): void
    {
        $d = new IndicateursData(
            totalReports: 0, totalNouveau: 0, totalEnCours: 0, totalTraite: 0,
            registryTotals: ['total_rsst' => 5],
        );
        // Kill Coalesce mutant — missing key must return 0, not null
        $this->assertSame(0, $d->getRegistryTotal('unknown'));
        $this->assertSame(0, $d->getRegistryTotal(''));
    }

    public function testGetRegistryTotalReturnsZeroWhenRegistryTotalsEmpty(): void
    {
        $d = new IndicateursData(
            totalReports: 0, totalNouveau: 0, totalEnCours: 0, totalTraite: 0,
        );
        $this->assertSame(0, $d->getRegistryTotal('rsst'));
    }
}
