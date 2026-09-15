<?php
/**
 * Integration test — production getDB() must configure a SQLite busy timeout.
 *
 * P0-2 audit premise: concurrent writers on the SQLite database "used to fail
 * immediately with database is locked (SQLITE_BUSY) because getDB() never set
 * PRAGMA busy_timeout, whose SQLite default is 0 ms". WAL mode still allows a
 * single writer at a time, and background work (lazy cron, backups, FTS
 * syncs) can collide with a web request. A busy timeout makes the loser wait
 * instead of crashing the request.
 *
 * That premise is only partly accurate on this runtime — see the Discovery
 * section below, which is the authoritative statement of what was measured.
 *
 * ─ Why a subprocess ────────────────────────────────────────────────────────
 * tests/bootstrap.php redefines getDB() to an in-memory PDO, so the
 * production implementation in src/database.php can never be exercised
 * in-process (redeclaration is a fatal error). This test spawns a real PHP
 * CLI process (tests/database_busy_timeout_runner.php) that boots the
 * production autoloader, calls the real getDB() on a real file-backed SQLite
 * database, and reports the live connection PRAGMAs.
 *
 * ─ Why a contract assertion rather than a concurrency race ─────────────────
 * Reproducing an actual SQLITE_BUSY contention deterministically needs two
 * processes, a held write lock and a timing race — inherently flaky in CI.
 * We therefore assert the configured contract on the real production
 * connection.
 *
 * ── Discovery during implementation (important) ─────────────────────────────
 * The audit premise assumed getDB() left busy_timeout at SQLite's default of
 * 0 ms. That is false on this runtime: PDO_SQLITE sets PDO::ATTR_TIMEOUT to
 * 60 s by default and maps it to sqlite3_busy_timeout(), so the *effective*
 * pre-fix value is 60000 ms, not 0. A ">= 5000" assertion is therefore green
 * before any change and demonstrates nothing.
 *
 * To get a genuine RED/GREEN signal this test pins the value the fix
 * explicitly configures (5000 ms). Pre-fix the connection reports 60000 and
 * the test fails; post-fix it reports exactly 5000 and passes. Note the net
 * effect: the explicit PRAGMA LOWERs the effective wait from the PDO implicit
 * 60 s to a bounded 5 s. That is a deliberate, reviewable behaviour change —
 * flagged to the owner for validation, not a bug fix for an absent timeout.
 */

use PHPUnit\Framework\TestCase;

final class DatabaseBusyTimeoutTest extends TestCase
{
    private const RUNNER_MARKER = 'SST_BUSY_PRAGMA_JSON:';

    /**
     * The value getDB() must explicitly pin.
     *
     * Deliberately stricter than the ">= 5000" minimum the task suggested:
     * because PDO already defaults to 60000, only an equality assertion can
     * prove the explicit configuration exists (and yields a real RED).
     */
    private const EXPECTED_BUSY_TIMEOUT_MS = 5000;

    /** @var list<string> */
    private array $tmpFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tmpFiles as $file) {
            foreach ([$file, $file . '-wal', $file . '-shm', $file . '-journal'] as $candidate) {
                if (is_file($candidate)) {
                    @unlink($candidate);
                }
            }
        }
        $this->tmpFiles = [];
    }

    /**
     * Run the production getDB() out-of-process and return its PRAGMA snapshot.
     *
     * @return array{busy_timeout: int, journal_mode: string, foreign_keys: int}
     */
    private function probeProductionGetDb(): array
    {
        // Brand-new file: getDB() must create the schema and seed it, exactly
        // as it does on a first production boot.
        $dbFile = tempnam(sys_get_temp_dir(), 'sst_busy_');
        self::assertIsString($dbFile, 'tempnam() must return a path');
        unlink($dbFile);
        $this->tmpFiles[] = $dbFile;

        $cmd = escapeshellarg(PHP_BINARY)
            . ' ' . escapeshellarg(__DIR__ . '/../database_busy_timeout_runner.php')
            . ' ' . escapeshellarg($dbFile);

        exec($cmd, $output, $exitCode);
        $raw = implode("\n", $output);

        $pos = strpos($raw, self::RUNNER_MARKER);
        self::assertNotFalse(
            $pos,
            'Production getDB() runner produced no marker. Raw output: ' . $raw
        );

        $json = trim(substr($raw, $pos + strlen(self::RUNNER_MARKER)));
        $data = json_decode($json, true);

        self::assertSame(
            0,
            $exitCode,
            'Production getDB() runner failed (exit ' . $exitCode . '). Payload: ' . $json
        );
        self::assertIsArray($data, 'Runner payload is not valid JSON. Raw output: ' . $raw);

        /** @var array{busy_timeout: int, journal_mode: string, foreign_keys: int} $data */
        return $data;
    }

    public function testProductionGetDbSetsBusyTimeout(): void
    {
        $data = $this->probeProductionGetDb();

        // Sanity checks: only the real production getDB() sets WAL and
        // foreign_keys, so passing these proves the code path under test is
        // the production one and not a test double.
        self::assertSame('wal', strtolower($data['journal_mode']));
        self::assertSame(1, $data['foreign_keys']);

        self::assertSame(
            self::EXPECTED_BUSY_TIMEOUT_MS,
            $data['busy_timeout'],
            'Production getDB() must explicitly pin PRAGMA busy_timeout to 5000 ms. '
            . 'Without the explicit PRAGMA the connection reports the PDO_SQLITE '
            . 'implicit default of 60000 ms (not 0), so this test also documents a '
            . 'deliberate reduction of the bounded lock wait to 5 s.'
        );
    }
}