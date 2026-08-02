<?php
/**
 * Tests SessionUser DTO exhaustively — kills Infection mutants on:
 *   - fromRow() : CastInt, CastString on every (int)/(string) cast (lines 44-57)
 *   - fromSession() : Coalesce, CastInt, CastString (lines 82-97)
 *   - toArray() : ArrayItem, ArrayItemRemoval (lines 54-72)
 *   - withRole() : property propagation (lines 102-120)
 *   - fromArray() : defaults, merge (lines 130-149)
 *   - ArrayAccess : offsetExists, offsetGet, offsetSet, offsetUnset
 */

use PHPUnit\Framework\TestCase;
use App\DTO\SessionUser;

class SessionUserMutationTest extends TestCase
{
    /** @return array{id: int, username: string, nom: string, prenom: string, email: ?string, role: string, site_id: ?int, is_active: int, created_at: string, updated_at: ?string, site_code: ?string, site_nom: ?string, site_chosen_at: ?string, sessions_invalid_before: ?string} */
    private function sampleRow(): array
    {
        return [
            'id' => 42,
            'username' => 'jean.dupont',
            'nom' => 'Dupont',
            'prenom' => 'Jean',
            'email' => 'jean@gouv.fr',
            'role' => 'agent',
            'site_id' => 5,
            'is_active' => 1,
            'created_at' => '2026-01-15 10:00:00',
            'updated_at' => '2026-02-01 12:00:00',
            'site_code' => 'UR21',
            'site_nom' => 'UR Test',
            'site_chosen_at' => '2026-01-20 08:00:00',
            'sessions_invalid_before' => '2026-03-01 00:00:00',
        ];
    }

    // ═══ fromRow() ═══

    public function testFromRowSetsAllPropertiesWithCorrectTypes(): void
    {
        $user = SessionUser::fromRow($this->sampleRow());

        // Kill CastInt on (int) $row['id']
        $this->assertSame(42, $user->id);
        $this->assertIsInt($user->id);

        // Kill CastString on (string) $row['username']
        $this->assertSame('jean.dupont', $user->username);
        $this->assertIsString($user->username);

        // Kill CastString on (string) $row['nom']
        $this->assertSame('Dupont', $user->nom);

        // Kill CastString on (string) $row['prenom']
        $this->assertSame('Jean', $user->prenom);

        // Kill null check on email
        $this->assertSame('jean@gouv.fr', $user->email);

        // Kill CastString on (string) $row['role']
        $this->assertSame('agent', $user->role);

        // Kill CastInt on site_id
        $this->assertSame(5, $user->siteId);
        $this->assertIsInt($user->siteId);

        // Kill CastInt on is_active
        $this->assertSame(1, $user->isActive);
        $this->assertIsInt($user->isActive);

        // Kill CastString on created_at
        $this->assertSame('2026-01-15 10:00:00', $user->createdAt);

        // Kill null check on updated_at
        $this->assertSame('2026-02-01 12:00:00', $user->updatedAt);

        // Kill null check on site_code
        $this->assertSame('UR21', $user->siteCode);

        // Kill null check on site_nom
        $this->assertSame('UR Test', $user->siteNom);

        // Kill null check on site_chosen_at
        $this->assertSame('2026-01-20 08:00:00', $user->siteChosenAt);

        // Kill null check on sessions_invalid_before
        $this->assertSame('2026-03-01 00:00:00', $user->sessionsInvalidBefore);
    }

    public function testFromRowHandlesNullOptionals(): void
    {
        // Kill null check mutants — all nullable fields set to null
        $row = $this->sampleRow();
        $row['email'] = null;
        $row['site_id'] = null;
        $row['updated_at'] = null;
        $row['site_code'] = null;
        $row['site_nom'] = null;
        $row['site_chosen_at'] = null;
        $row['sessions_invalid_before'] = null;

        $user = SessionUser::fromRow($row);

        $this->assertNull($user->email);
        $this->assertNull($user->siteId);
        $this->assertNull($user->updatedAt);
        $this->assertNull($user->siteCode);
        $this->assertNull($user->siteNom);
        $this->assertNull($user->siteChosenAt);
        $this->assertNull($user->sessionsInvalidBefore);
    }

    public function testFromRowCastsStringIdsToInt(): void
    {
        // Kill CastInt mutants — DB may return strings
        $row = $this->sampleRow();
        $row['id'] = '42';
        $row['site_id'] = '5';
        $row['is_active'] = '1';

        $user = SessionUser::fromRow($row);
        $this->assertSame(42, $user->id);
        $this->assertSame(5, $user->siteId);
        $this->assertSame(1, $user->isActive);
    }

    // ═══ toArray() ═══

    public function testToArrayReturnsAllKeysWithCorrectValues(): void
    {
        $user = SessionUser::fromRow($this->sampleRow());
        $arr = $user->toArray();

        $this->assertSame(42, $arr['id']);
        $this->assertSame('jean.dupont', $arr['username']);
        $this->assertSame('Dupont', $arr['nom']);
        $this->assertSame('Jean', $arr['prenom']);
        $this->assertSame('jean@gouv.fr', $arr['email']);
        $this->assertSame('agent', $arr['role']);
        $this->assertSame(5, $arr['siteId']);
        $this->assertSame(1, $arr['isActive']);
        $this->assertSame('2026-01-15 10:00:00', $arr['createdAt']);
        $this->assertSame('2026-02-01 12:00:00', $arr['updatedAt']);
        $this->assertSame('UR21', $arr['siteCode']);
        $this->assertSame('UR Test', $arr['siteNom']);
        $this->assertSame('2026-01-20 08:00:00', $arr['siteChosenAt']);
        $this->assertSame('2026-03-01 00:00:00', $arr['sessionsInvalidBefore']);
    }

    public function testToArrayContainsExactly14Keys(): void
    {
        // Kill ArrayItemRemoval mutants — every key must be present
        $user = SessionUser::fromRow($this->sampleRow());
        $arr = $user->toArray();
        $this->assertCount(14, $arr);
    }

    // ═══ fromSession() ═══

    public function testFromSessionAcceptsCamelCaseKeys(): void
    {
        $user = SessionUser::fromSession([
            'id' => 10,
            'username' => 'test',
            'nom' => 'Test',
            'prenom' => 'User',
            'email' => 'test@gouv.fr',
            'role' => 'superviseur',
            'siteId' => 3,
            'isActive' => 1,
            'createdAt' => '2026-01-01',
            'updatedAt' => null,
            'siteCode' => 'UR21',
            'siteNom' => 'UR Test',
            'siteChosenAt' => null,
            'sessionsInvalidBefore' => null,
        ]);

        $this->assertSame(10, $user->id);
        $this->assertSame('test', $user->username);
        $this->assertSame('superviseur', $user->role);
        $this->assertSame(3, $user->siteId);
        $this->assertSame(1, $user->isActive);
    }

    public function testFromSessionAcceptsSnakeCaseKeys(): void
    {
        // Kill Coalesce mutants on camelCase ?? snake_case fallback
        $user = SessionUser::fromSession([
            'id' => 20,
            'username' => 'snake',
            'nom' => 'Case',
            'prenom' => 'User',
            'email' => null,
            'role' => 'chsct',
            'site_id' => 7,
            'is_active' => 0,
            'created_at' => '2026-03-01',
            'updated_at' => '2026-04-01',
            'site_code' => 'UR25',
            'site_nom' => 'UR 25',
            'site_chosen_at' => '2026-03-15',
            'sessions_invalid_before' => '2026-05-01',
        ]);

        $this->assertSame(20, $user->id);
        $this->assertSame('snake', $user->username);
        $this->assertSame('chsct', $user->role);
        $this->assertSame(7, $user->siteId);
        $this->assertSame(0, $user->isActive);
        $this->assertSame('2026-03-01', $user->createdAt);
        $this->assertSame('2026-04-01', $user->updatedAt);
        $this->assertSame('UR25', $user->siteCode);
        $this->assertSame('UR 25', $user->siteNom);
        $this->assertSame('2026-03-15', $user->siteChosenAt);
        $this->assertSame('2026-05-01', $user->sessionsInvalidBefore);
    }

    public function testFromSessionUsesDefaultsForMissingKeys(): void
    {
        // Kill Coalesce mutants — missing keys should use ?? defaults
        $user = SessionUser::fromSession(['id' => 1]);

        $this->assertSame(1, $user->id);
        $this->assertSame('', $user->username);
        $this->assertSame('', $user->nom);
        $this->assertSame('', $user->prenom);
        $this->assertNull($user->email);
        $this->assertSame('', $user->role);
        $this->assertNull($user->siteId);
        $this->assertSame(1, $user->isActive, 'default is_active=1');
        $this->assertSame('', $user->createdAt);
        $this->assertNull($user->updatedAt);
    }

    // ═══ withRole() ═══

    public function testWithRoleReturnsNewInstanceWithChangedRole(): void
    {
        $user = SessionUser::fromRow($this->sampleRow());
        $newUser = $user->withRole('superviseur');

        $this->assertSame('superviseur', $newUser->role);
        $this->assertSame('agent', $user->role, 'original must be unchanged');
        $this->assertNotSame($user, $newUser, 'must return new instance');
    }

    public function testWithRolePreservesAllOtherProperties(): void
    {
        $user = SessionUser::fromRow($this->sampleRow());
        $newUser = $user->withRole('chsct');

        $this->assertSame($user->id, $newUser->id);
        $this->assertSame($user->username, $newUser->username);
        $this->assertSame($user->nom, $newUser->nom);
        $this->assertSame($user->prenom, $newUser->prenom);
        $this->assertSame($user->email, $newUser->email);
        $this->assertSame($user->siteId, $newUser->siteId);
        $this->assertSame($user->isActive, $newUser->isActive);
        $this->assertSame($user->createdAt, $newUser->createdAt);
        $this->assertSame($user->updatedAt, $newUser->updatedAt);
        $this->assertSame($user->siteCode, $newUser->siteCode);
        $this->assertSame($user->siteNom, $newUser->siteNom);
        $this->assertSame($user->siteChosenAt, $newUser->siteChosenAt);
        $this->assertSame($user->sessionsInvalidBefore, $newUser->sessionsInvalidBefore);
    }

    // ═══ fromArray() ═══

    public function testFromArrayUsesDefaultsForMissingKeys(): void
    {
        $user = SessionUser::fromArray([]);

        $this->assertSame(0, $user->id);
        $this->assertSame('', $user->username);
        $this->assertSame('', $user->nom);
        $this->assertSame('', $user->prenom);
        $this->assertNull($user->email);
        $this->assertSame('agent', $user->role, 'default role=agent');
        $this->assertNull($user->siteId);
        $this->assertSame(1, $user->isActive, 'default is_active=1');
    }

    public function testFromArrayUsesProvidedOverrides(): void
    {
        $user = SessionUser::fromArray([
            'id' => 99,
            'username' => 'custom',
            'role' => 'superviseur',
            'site_id' => 3,
        ]);

        $this->assertSame(99, $user->id);
        $this->assertSame('custom', $user->username);
        $this->assertSame('superviseur', $user->role);
        $this->assertSame(3, $user->siteId);
    }

    // ═══ ArrayAccess ═══

    public function testArrayAccessSnakeCaseKeys(): void
    {
        $user = SessionUser::fromRow($this->sampleRow());

        // Kill offsetGet mutants — snake_case access
        $this->assertSame(42, $user['id']);
        $this->assertSame('jean.dupont', $user['username']);
        $this->assertSame('Dupont', $user['nom']);
        $this->assertSame('Jean', $user['prenom']);
        $this->assertSame('jean@gouv.fr', $user['email']);
        $this->assertSame('agent', $user['role']);
        $this->assertSame(5, $user['site_id']);
        $this->assertSame(1, $user['is_active']);
        $this->assertSame('2026-01-15 10:00:00', $user['created_at']);
        $this->assertSame('2026-02-01 12:00:00', $user['updated_at']);
        $this->assertSame('UR21', $user['site_code']);
        $this->assertSame('UR Test', $user['site_nom']);
        $this->assertSame('2026-01-20 08:00:00', $user['site_chosen_at']);
        $this->assertSame('2026-03-01 00:00:00', $user['sessions_invalid_before']);
    }

    public function testArrayAccessCamelCaseKeys(): void
    {
        $user = SessionUser::fromRow($this->sampleRow());

        $this->assertSame(5, $user['siteId']);
        $this->assertSame(1, $user['isActive']);
        $this->assertSame('2026-01-15 10:00:00', $user['createdAt']);
        $this->assertSame('UR21', $user['siteCode']);
    }

    public function testArrayAccessOffsetExists(): void
    {
        $user = SessionUser::fromRow($this->sampleRow());

        $this->assertTrue(isset($user['id']));
        $this->assertTrue(isset($user['username']));
        $this->assertTrue(isset($user['nom']));
        $this->assertTrue(isset($user['site_id']));
        $this->assertTrue(isset($user['is_active']));
        $this->assertTrue(isset($user['siteId']));
        $this->assertTrue(isset($user['isActive']));
        $this->assertFalse(isset($user['nonexistent_key']));
    }

    public function testArrayAccessOffsetGetReturnsNullForUnknownKey(): void
    {
        $user = SessionUser::fromRow($this->sampleRow());
        $this->assertNull($user['unknown_key']);
    }

    public function testArrayAccessOffsetSetThrowsError(): void
    {
        $user = SessionUser::fromRow($this->sampleRow());
        $this->expectException(\Error::class);
        $user['id'] = 999;
    }

    public function testArrayAccessOffsetUnsetThrowsError(): void
    {
        $user = SessionUser::fromRow($this->sampleRow());
        $this->expectException(\Error::class);
        unset($user['id']);
    }
}
