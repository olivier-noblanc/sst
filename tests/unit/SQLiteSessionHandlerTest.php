<?php

use PHPUnit\Framework\TestCase;
use App\Services\SQLiteSessionHandler;

class SQLiteSessionHandlerTest extends TestCase
{
    private \PDO $pdo;
    private SQLiteSessionHandler $handler;

    protected function setUp(): void
    {
        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec("CREATE TABLE sessions (
            id TEXT PRIMARY KEY,
            data TEXT NOT NULL DEFAULT '',
            last_accessed INTEGER NOT NULL DEFAULT 0
        )");
        $this->handler = new SQLiteSessionHandler($this->pdo);
    }

    public function testReadReturnsEmptyStringForUnknownSession(): void
    {
        $result = $this->handler->read('nonexistent');
        $this->assertSame('', $result);
    }

    public function testWriteAndReadRoundTrip(): void
    {
        $this->handler->write('sess123', 'user_data');
        $result = $this->handler->read('sess123');
        $this->assertSame('user_data', $result);
    }

    public function testWriteOverwritesExistingSession(): void
    {
        $this->handler->write('sess123', 'old_data');
        $this->handler->write('sess123', 'new_data');
        $result = $this->handler->read('sess123');
        $this->assertSame('new_data', $result);
    }

    public function testDestroyRemovesSession(): void
    {
        $this->handler->write('sess123', 'data');
        $this->handler->destroy('sess123');
        $result = $this->handler->read('sess123');
        $this->assertSame('', $result);
    }

    public function testGcRemovesExpiredSessions(): void
    {
        // Write a session with old last_accessed
        $this->pdo->exec("INSERT INTO sessions (id, data, last_accessed) VALUES ('old', 'data', 100)");
        $this->handler->write('fresh', 'fresh_data');

        $removed = $this->handler->gc(3600);
        $this->assertGreaterThanOrEqual(1, $removed);
        $this->assertSame('', $this->handler->read('old'));
        $this->assertSame('fresh_data', $this->handler->read('fresh'));
    }

    public function testOpenAndCloseAlwaysReturnTrue(): void
    {
        $this->assertTrue($this->handler->open('/tmp', 'test'));
        $this->assertTrue($this->handler->close());
    }

    public function testWriteReturnsTrue(): void
    {
        $this->assertTrue($this->handler->write('sess', 'data'));
    }

    public function testDestroyReturnsTrue(): void
    {
        $this->handler->write('sess', 'data');
        $this->assertTrue($this->handler->destroy('sess'));
    }
}
