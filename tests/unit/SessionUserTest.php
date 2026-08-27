<?php
/**
 * SessionUser DTO Unit Tests
 *
 * Tests src/DTO/SessionUser.php:
 * - fromRow() hydrates from DB row array
 * - toArray() serializes back to array
 * - fromSession() hydrates from $_SESSION data
 * - Round-trip: fromRow -> toArray -> fromSession preserves all fields
 * - Nullable fields default correctly
 */

use PHPUnit\Framework\TestCase;
use App\DTO\SessionUser;

class SessionUserTest extends TestCase
{
    private array $fullRow = [
        'id' => 42,
        'username' => 'jean.martin',
        'nom' => 'Martin',
        'prenom' => 'Jean',
        'email' => 'jean.martin@example.fr',
        'role' => 'superviseur',
        'site_id' => 7,
        'is_active' => 1,
        'created_at' => '2025-01-15 10:30:00',
        'updated_at' => '2025-06-01 14:00:00',
        'site_code' => 'UD_21',
        'site_nom' => 'DREETS 21',
        'site_chosen_at' => '2025-02-01 09:00:00',
        'sessions_invalid_before' => null,
    ];

    // ═══════════════════════════════════════════════════════════════════════════════
    // fromRow()
    // ═══════════════════════════════════════════════════════════════════════════════

    public function testFromRowHydratesAllFields(): void
    {
        $user = SessionUser::fromRow($this->fullRow);

        $this->assertSame(42, $user->id);
        $this->assertSame('jean.martin', $user->username);
        $this->assertSame('Martin', $user->nom);
        $this->assertSame('Jean', $user->prenom);
        $this->assertSame('jean.martin@example.fr', $user->email);
        $this->assertSame('superviseur', $user->role);
        $this->assertSame(7, $user->siteId);
        $this->assertSame(1, $user->isActive);
        $this->assertSame('2025-01-15 10:30:00', $user->createdAt);
        $this->assertSame('2025-06-01 14:00:00', $user->updatedAt);
        $this->assertSame('UD_21', $user->siteCode);
        $this->assertSame('DREETS 21', $user->siteNom);
        $this->assertSame('2025-02-01 09:00:00', $user->siteChosenAt);
        $this->assertNull($user->sessionsInvalidBefore);
    }

    public function testFromRowHandlesNullables(): void
    {
        $row = $this->fullRow;
        $row['email'] = null;
        $row['site_id'] = null;
        $row['updated_at'] = null;
        $row['site_code'] = null;
        $row['site_nom'] = null;
        $row['site_chosen_at'] = null;
        $row['sessions_invalid_before'] = '2025-07-01 00:00:00';

        $user = SessionUser::fromRow($row);

        $this->assertNull($user->email);
        $this->assertNull($user->siteId);
        $this->assertNull($user->updatedAt);
        $this->assertNull($user->siteCode);
        $this->assertNull($user->siteNom);
        $this->assertNull($user->siteChosenAt);
        $this->assertSame('2025-07-01 00:00:00', $user->sessionsInvalidBefore);
    }

    public function testFromRowCastsTypesCorrectly(): void
    {
        $row = $this->fullRow;
        $row['id'] = '99'; // string that should become int
        $row['site_id'] = '3';
        $row['is_active'] = '1';

        $user = SessionUser::fromRow($row);

        $this->assertIsInt($user->id);
        $this->assertSame(99, $user->id);
        $this->assertIsInt($user->siteId);
        $this->assertSame(3, $user->siteId);
        $this->assertIsInt($user->isActive);
        $this->assertSame(1, $user->isActive);
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // toArray()
    // ═══════════════════════════════════════════════════════════════════════════════

    public function testToArrayReturnsAllFields(): void
    {
        $user = SessionUser::fromRow($this->fullRow);
        $array = $user->toArray();

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('username', $array);
        $this->assertArrayHasKey('nom', $array);
        $this->assertArrayHasKey('prenom', $array);
        $this->assertArrayHasKey('email', $array);
        $this->assertArrayHasKey('role', $array);
        $this->assertArrayHasKey('siteId', $array);
        $this->assertArrayHasKey('isActive', $array);
        $this->assertArrayHasKey('createdAt', $array);
        $this->assertArrayHasKey('updatedAt', $array);
        $this->assertArrayHasKey('siteCode', $array);
        $this->assertArrayHasKey('siteNom', $array);
        $this->assertArrayHasKey('siteChosenAt', $array);
        $this->assertArrayHasKey('sessionsInvalidBefore', $array);
    }

    public function testToArrayValuesMatchProperties(): void
    {
        $user = SessionUser::fromRow($this->fullRow);
        $array = $user->toArray();

        $this->assertSame(42, $array['id']);
        $this->assertSame('jean.martin', $array['username']);
        $this->assertSame('superviseur', $array['role']);
        $this->assertSame(7, $array['siteId']);
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // fromSession()
    // ═══════════════════════════════════════════════════════════════════════════════

    public function testFromSessionHydratesFromSessionArray(): void
    {
        $sessionData = [
            'id' => 42,
            'username' => 'jean.martin',
            'nom' => 'Martin',
            'prenom' => 'Jean',
            'email' => 'jean.martin@example.fr',
            'role' => 'superviseur',
            'siteId' => 7,
            'isActive' => 1,
            'createdAt' => '2025-01-15 10:30:00',
            'updatedAt' => '2025-06-01 14:00:00',
            'siteCode' => 'UD_21',
            'siteNom' => 'DREETS 21',
            'siteChosenAt' => '2025-02-01 09:00:00',
            'sessionsInvalidBefore' => null,
        ];

        $user = SessionUser::fromSession($sessionData);

        $this->assertSame(42, $user->id);
        $this->assertSame('jean.martin', $user->username);
        $this->assertSame('superviseur', $user->role);
        $this->assertSame(7, $user->siteId);
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // Round-trip
    // ═══════════════════════════════════════════════════════════════════════════════

    public function testRoundTripFromRowToSessionPreservesData(): void
    {
        $user = SessionUser::fromRow($this->fullRow);
        $sessionArray = $user->toArray();
        $restored = SessionUser::fromSession($sessionArray);

        $this->assertSame($user->id, $restored->id);
        $this->assertSame($user->username, $restored->username);
        $this->assertSame($user->nom, $restored->nom);
        $this->assertSame($user->prenom, $restored->prenom);
        $this->assertSame($user->email, $restored->email);
        $this->assertSame($user->role, $restored->role);
        $this->assertSame($user->siteId, $restored->siteId);
        $this->assertSame($user->isActive, $restored->isActive);
        $this->assertSame($user->createdAt, $restored->createdAt);
        $this->assertSame($user->updatedAt, $restored->updatedAt);
        $this->assertSame($user->siteCode, $restored->siteCode);
        $this->assertSame($user->siteNom, $restored->siteNom);
        $this->assertSame($user->siteChosenAt, $restored->siteChosenAt);
        $this->assertSame($user->sessionsInvalidBefore, $restored->sessionsInvalidBefore);
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // Immutability (readonly)
    // ═══════════════════════════════════════════════════════════════════════════════

    public function testSessionUserIsImmutable(): void
    {
        $user = SessionUser::fromRow($this->fullRow);
        $originalRole = $user->role;

        // readonly property — cannot be reassigned (would be compile error)
        // Verify the value stays the same after construction
        $this->assertSame($originalRole, $user->role);
        $this->assertSame('superviseur', $user->role);
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // withRole()
    // ═══════════════════════════════════════════════════════════════════════════════

    public function testWithRoleReturnsNewInstance(): void
    {
        $user = SessionUser::fromRow($this->fullRow);
        $promoted = $user->withRole('agent');

        $this->assertNotSame($user, $promoted);
        $this->assertSame('agent', $promoted->role);
        $this->assertSame('superviseur', $user->role); // original unchanged
    }

    public function testWithRolePreservesOtherFields(): void
    {
        $user = SessionUser::fromRow($this->fullRow);
        $changed = $user->withRole('chsct');

        $this->assertSame($user->id, $changed->id);
        $this->assertSame($user->username, $changed->username);
        $this->assertSame($user->nom, $changed->nom);
        $this->assertSame($user->prenom, $changed->prenom);
        $this->assertSame($user->email, $changed->email);
        $this->assertSame($user->siteId, $changed->siteId);
        $this->assertSame($user->isActive, $changed->isActive);
        $this->assertSame($user->createdAt, $changed->createdAt);
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // fromArray() — minimal overrides with defaults
    // ═══════════════════════════════════════════════════════════════════════════════

    public function testFromArrayCreatesUserWithDefaults(): void
    {
        $user = SessionUser::fromArray(['id' => 5, 'role' => 'agent']);
        $this->assertSame(5, $user->id);
        $this->assertSame('agent', $user->role);
        $this->assertSame('', $user->username);
        $this->assertSame(1, $user->isActive);
    }

    public function testFromArrayOverridesDefaults(): void
    {
        $user = SessionUser::fromArray(['id' => 10, 'username' => 'test', 'nom' => 'Test', 'prenom' => 'User', 'role' => 'superviseur', 'is_active' => 0]);
        $this->assertSame(10, $user->id);
        $this->assertSame('test', $user->username);
        $this->assertSame('superviseur', $user->role);
        $this->assertSame(0, $user->isActive);
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // fromSession() — id default (kills Decrement/IncrementInteger on `?? 0`)
    // ═══════════════════════════════════════════════════════════════════════════════

    public function testFromSessionIdAbsentDefaultsToZero(): void
    {
        $user = SessionUser::fromSession([]);
        $this->assertSame(0, $user->id);
    }

    public function testFromSessionIdNullDefaultsToZero(): void
    {
        $user = SessionUser::fromSession(['id' => null]);
        $this->assertSame(0, $user->id);
    }

    public function testFromSessionIdZeroStaysZero(): void
    {
        $user = SessionUser::fromSession(['id' => 0]);
        $this->assertSame(0, $user->id);
    }

    public function testFromSessionIdValueIsPreserved(): void
    {
        $user = SessionUser::fromSession(['id' => 42]);
        $this->assertSame(42, $user->id);
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // fromSession() — coalesce fields (isActive, createdAt, updatedAt, siteCode,
    // siteNom, siteChosenAt, sessionsInvalidBefore) : kills Coalesce mutants
    // (camelCase ?? snake_case ?? fallback)
    // ═══════════════════════════════════════════════════════════════════════════════

    public function testFromSessionWithCamelCaseKeys(): void
    {
        $user = SessionUser::fromSession([
            'id' => 1,
            'isActive' => 0,
            'createdAt' => '2026-01-01 00:00:00',
            'updatedAt' => '2026-01-02 00:00:00',
            'siteCode' => 'UR_A',
            'siteNom' => 'Site A',
            'siteChosenAt' => '2026-01-03 00:00:00',
            'sessionsInvalidBefore' => '2026-02-01 00:00:00',
        ]);

        $this->assertSame(0, $user->isActive);
        $this->assertSame('2026-01-01 00:00:00', $user->createdAt);
        $this->assertSame('2026-01-02 00:00:00', $user->updatedAt);
        $this->assertSame('UR_A', $user->siteCode);
        $this->assertSame('Site A', $user->siteNom);
        $this->assertSame('2026-01-03 00:00:00', $user->siteChosenAt);
        $this->assertSame('2026-02-01 00:00:00', $user->sessionsInvalidBefore);
    }

    public function testFromSessionWithSnakeCaseKeys(): void
    {
        $user = SessionUser::fromSession([
            'id' => 1,
            'is_active' => 0,
            'created_at' => '2026-03-01 00:00:00',
            'updated_at' => '2026-03-02 00:00:00',
            'site_code' => 'UR_B',
            'site_nom' => 'Site B',
            'site_chosen_at' => '2026-03-03 00:00:00',
            'sessions_invalid_before' => '2026-04-01 00:00:00',
        ]);

        $this->assertSame(0, $user->isActive);
        $this->assertSame('2026-03-01 00:00:00', $user->createdAt);
        $this->assertSame('2026-03-02 00:00:00', $user->updatedAt);
        $this->assertSame('UR_B', $user->siteCode);
        $this->assertSame('Site B', $user->siteNom);
        $this->assertSame('2026-03-03 00:00:00', $user->siteChosenAt);
        $this->assertSame('2026-04-01 00:00:00', $user->sessionsInvalidBefore);
    }

    public function testFromSessionPrefersCamelCaseOverSnakeCase(): void
    {
        $user = SessionUser::fromSession([
            'id' => 1,
            'isActive' => 1,
            'is_active' => 0,
            'createdAt' => '2026-C',
            'created_at' => '2026-S',
            'updatedAt' => '2026-UC',
            'updated_at' => '2026-US',
            'siteCode' => 'UR-C',
            'site_code' => 'UR-S',
            'siteNom' => 'Site C',
            'site_nom' => 'Site S',
            'siteChosenAt' => '2026-CC',
            'site_chosen_at' => '2026-CS',
            'sessionsInvalidBefore' => '2026-IC',
            'sessions_invalid_before' => '2026-IS',
        ]);

        $this->assertSame(1, $user->isActive);
        $this->assertSame('2026-C', $user->createdAt);
        $this->assertSame('2026-UC', $user->updatedAt);
        $this->assertSame('UR-C', $user->siteCode);
        $this->assertSame('Site C', $user->siteNom);
        $this->assertSame('2026-CC', $user->siteChosenAt);
        $this->assertSame('2026-IC', $user->sessionsInvalidBefore);
    }

    public function testFromSessionCoalesceFieldsFallbackWhenAbsent(): void
    {
        $user = SessionUser::fromSession(['id' => 1]);

        $this->assertSame(1, $user->isActive);   // fallback 1
        $this->assertSame('', $user->createdAt);  // fallback ''
        $this->assertNull($user->updatedAt);      // fallback null
        $this->assertNull($user->siteCode);
        $this->assertNull($user->siteNom);
        $this->assertNull($user->siteChosenAt);
        $this->assertNull($user->sessionsInvalidBefore);
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // ArrayAccess offsetGet() — kills MatchArmRemoval mutants on every match arm
    // ═══════════════════════════════════════════════════════════════════════════════

    public function testOffsetGetReturnsEveryProperty(): void
    {
        $user = SessionUser::fromRow($this->fullRow);

        $this->assertSame(42, $user->offsetGet('id'));
        $this->assertSame('jean.martin', $user->offsetGet('username'));
        $this->assertSame('Martin', $user->offsetGet('nom'));
        $this->assertSame('Jean', $user->offsetGet('prenom'));
        $this->assertSame('jean.martin@example.fr', $user->offsetGet('email'));
        $this->assertSame('superviseur', $user->offsetGet('role'));
        $this->assertSame(7, $user->offsetGet('site_id'));
        $this->assertSame(7, $user->offsetGet('siteId'));
        $this->assertSame(1, $user->offsetGet('is_active'));
        $this->assertSame(1, $user->offsetGet('isActive'));
        $this->assertSame('2025-01-15 10:30:00', $user->offsetGet('created_at'));
        $this->assertSame('2025-06-01 14:00:00', $user->offsetGet('updated_at'));
        $this->assertSame('UD_21', $user->offsetGet('site_code'));
        $this->assertSame('DREETS 21', $user->offsetGet('site_nom'));
        $this->assertSame('2025-02-01 09:00:00', $user->offsetGet('site_chosen_at'));
        $this->assertNull($user->offsetGet('sessions_invalid_before'));
    }

    public function testOffsetGetReturnsNullForUnknownKey(): void
    {
        $user = SessionUser::fromRow($this->fullRow);
        $this->assertNull($user->offsetGet('does_not_exist'));
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // ArrayAccess offsetSet()/offsetUnset() — kills Concat/Throw_ mutants
    // ═══════════════════════════════════════════════════════════════════════════════

    public function testOffsetSetThrowsErrorWithConcatenatedMessage(): void
    {
        $user = SessionUser::fromRow($this->fullRow);
        try {
            $user['id'] = 999;
            $this->fail('Expected \Error to be thrown');
        } catch (\Error $e) {
            $this->assertSame('Cannot modify readonly property id', $e->getMessage());
        }
    }

    public function testOffsetUnsetThrowsErrorWithConcatenatedMessage(): void
    {
        $user = SessionUser::fromRow($this->fullRow);
        try {
            unset($user['id']);
            $this->fail('Expected \Error to be thrown');
        } catch (\Error $e) {
            $this->assertSame('Cannot unset readonly property id', $e->getMessage());
        }
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // fromSession() Coalesce mutants - test snake_case vs camelCase keys
    // ═══════════════════════════════════════════════════════════════════════════════

    public function testFromSessionWithSnakeCaseIsActiveKey(): void
    {
        // Kill Coalesce mutant on line 101: $data['isActive'] ?? $data['is_active']
        $sessionData = [
            'id' => 42,
            'username' => 'test',
            'nom' => 'Test',
            'prenom' => 'User',
            'email' => 'test@example.fr',
            'role' => 'agent',
            'site_id' => 7,
            'is_active' => 0, // snake_case key
            'created_at' => '2025-01-15 10:30:00',
        ];

        $user = SessionUser::fromSession($sessionData);
        $this->assertSame(0, $user->isActive, 'isActive should use is_active snake_case key');
    }

    public function testFromSessionWithCamelCaseIsActiveKey(): void
    {
        // Kill Coalesce mutant on line 101: $data['isActive'] ?? $data['is_active']
        $sessionData = [
            'id' => 42,
            'username' => 'test',
            'nom' => 'Test',
            'prenom' => 'User',
            'email' => 'test@example.fr',
            'role' => 'agent',
            'site_id' => 7,
            'isActive' => 0, // camelCase key
            'created_at' => '2025-01-15 10:30:00',
        ];

        $user = SessionUser::fromSession($sessionData);
        $this->assertSame(0, $user->isActive, 'isActive should use isActive camelCase key');
    }

    public function testFromSessionWithSnakeCaseCreatedAtKey(): void
    {
        // Kill Coalesce mutant on line 102: $data['createdAt'] ?? $data['created_at']
        $sessionData = [
            'id' => 42,
            'username' => 'test',
            'nom' => 'Test',
            'prenom' => 'User',
            'email' => 'test@example.fr',
            'role' => 'agent',
            'site_id' => 7,
            'is_active' => 1,
            'created_at' => '2025-01-15 10:30:00', // snake_case key
        ];

        $user = SessionUser::fromSession($sessionData);
        $this->assertSame('2025-01-15 10:30:00', $user->createdAt, 'createdAt should use created_at snake_case key');
    }

    public function testFromSessionWithCamelCaseCreatedAtKey(): void
    {
        // Kill Coalesce mutant on line 102: $data['createdAt'] ?? $data['created_at']
        $sessionData = [
            'id' => 42,
            'username' => 'test',
            'nom' => 'Test',
            'prenom' => 'User',
            'email' => 'test@example.fr',
            'role' => 'agent',
            'site_id' => 7,
            'is_active' => 1,
            'createdAt' => '2025-02-20 15:00:00', // camelCase key
        ];

        $user = SessionUser::fromSession($sessionData);
        $this->assertSame('2025-02-20 15:00:00', $user->createdAt, 'createdAt should use createdAt camelCase key');
    }

    public function testFromSessionWithSnakeCaseUpdatedAtKey(): void
    {
        // Kill Coalesce mutant on line 103: $data['updatedAt'] ?? $data['updated_at']
        $sessionData = [
            'id' => 42,
            'username' => 'test',
            'nom' => 'Test',
            'prenom' => 'User',
            'email' => 'test@example.fr',
            'role' => 'agent',
            'site_id' => 7,
            'is_active' => 1,
            'created_at' => '2025-01-15 10:30:00',
            'updated_at' => '2025-06-01 14:00:00', // snake_case key
        ];

        $user = SessionUser::fromSession($sessionData);
        $this->assertSame('2025-06-01 14:00:00', $user->updatedAt, 'updatedAt should use updated_at snake_case key');
    }

    public function testFromSessionWithCamelCaseUpdatedAtKey(): void
    {
        // Kill Coalesce mutant on line 103: $data['updatedAt'] ?? $data['updated_at']
        $sessionData = [
            'id' => 42,
            'username' => 'test',
            'nom' => 'Test',
            'prenom' => 'User',
            'email' => 'test@example.fr',
            'role' => 'agent',
            'site_id' => 7,
            'is_active' => 1,
            'created_at' => '2025-01-15 10:30:00',
            'updatedAt' => '2025-07-01 09:00:00', // camelCase key
        ];

        $user = SessionUser::fromSession($sessionData);
        $this->assertSame('2025-07-01 09:00:00', $user->updatedAt, 'updatedAt should use updatedAt camelCase key');
    }

    public function testFromSessionWithSnakeCaseSiteCodeKey(): void
    {
        // Kill Coalesce mutant on line 104: $data['siteCode'] ?? $data['site_code']
        $sessionData = [
            'id' => 42,
            'username' => 'test',
            'nom' => 'Test',
            'prenom' => 'User',
            'email' => 'test@example.fr',
            'role' => 'agent',
            'site_id' => 7,
            'is_active' => 1,
            'created_at' => '2025-01-15 10:30:00',
            'site_code' => 'UD_21', // snake_case key
        ];

        $user = SessionUser::fromSession($sessionData);
        $this->assertSame('UD_21', $user->siteCode, 'siteCode should use site_code snake_case key');
    }

    public function testFromSessionWithCamelCaseSiteCodeKey(): void
    {
        // Kill Coalesce mutant on line 104: $data['siteCode'] ?? $data['site_code']
        $sessionData = [
            'id' => 42,
            'username' => 'test',
            'nom' => 'Test',
            'prenom' => 'User',
            'email' => 'test@example.fr',
            'role' => 'agent',
            'site_id' => 7,
            'is_active' => 1,
            'created_at' => '2025-01-15 10:30:00',
            'siteCode' => 'UD_99', // camelCase key
        ];

        $user = SessionUser::fromSession($sessionData);
        $this->assertSame('UD_99', $user->siteCode, 'siteCode should use siteCode camelCase key');
    }

    public function testFromSessionWithSnakeCaseSiteNomKey(): void
    {
        // Kill Coalesce mutant on line 105: $data['siteNom'] ?? $data['site_nom']
        $sessionData = [
            'id' => 42,
            'username' => 'test',
            'nom' => 'Test',
            'prenom' => 'User',
            'email' => 'test@example.fr',
            'role' => 'agent',
            'site_id' => 7,
            'is_active' => 1,
            'created_at' => '2025-01-15 10:30:00',
            'site_nom' => 'DREETS 21', // snake_case key
        ];

        $user = SessionUser::fromSession($sessionData);
        $this->assertSame('DREETS 21', $user->siteNom, 'siteNom should use site_nom snake_case key');
    }

    public function testFromSessionWithCamelCaseSiteNomKey(): void
    {
        // Kill Coalesce mutant on line 105: $data['siteNom'] ?? $data['site_nom']
        $sessionData = [
            'id' => 42,
            'username' => 'test',
            'nom' => 'Test',
            'prenom' => 'User',
            'email' => 'test@example.fr',
            'role' => 'agent',
            'site_id' => 7,
            'is_active' => 1,
            'created_at' => '2025-01-15 10:30:00',
            'siteNom' => 'DREETS 99', // camelCase key
        ];

        $user = SessionUser::fromSession($sessionData);
        $this->assertSame('DREETS 99', $user->siteNom, 'siteNom should use siteNom camelCase key');
    }

    public function testFromSessionWithSnakeCaseSiteChosenAtKey(): void
    {
        // Kill Coalesce mutant on line 106: $data['siteChosenAt'] ?? $data['site_chosen_at']
        $sessionData = [
            'id' => 42,
            'username' => 'test',
            'nom' => 'Test',
            'prenom' => 'User',
            'email' => 'test@example.fr',
            'role' => 'agent',
            'site_id' => 7,
            'is_active' => 1,
            'created_at' => '2025-01-15 10:30:00',
            'site_chosen_at' => '2025-02-01 09:00:00', // snake_case key
        ];

        $user = SessionUser::fromSession($sessionData);
        $this->assertSame('2025-02-01 09:00:00', $user->siteChosenAt, 'siteChosenAt should use site_chosen_at snake_case key');
    }

    public function testFromSessionWithCamelCaseSiteChosenAtKey(): void
    {
        // Kill Coalesce mutant on line 106: $data['siteChosenAt'] ?? $data['site_chosen_at']
        $sessionData = [
            'id' => 42,
            'username' => 'test',
            'nom' => 'Test',
            'prenom' => 'User',
            'email' => 'test@example.fr',
            'role' => 'agent',
            'site_id' => 7,
            'is_active' => 1,
            'created_at' => '2025-01-15 10:30:00',
            'siteChosenAt' => '2025-03-01 09:00:00', // camelCase key
        ];

        $user = SessionUser::fromSession($sessionData);
        $this->assertSame('2025-03-01 09:00:00', $user->siteChosenAt, 'siteChosenAt should use siteChosenAt camelCase key');
    }

    public function testFromSessionWithSnakeCaseSessionsInvalidBeforeKey(): void
    {
        // Kill Coalesce mutant on line 107: $data['sessionsInvalidBefore'] ?? $data['sessions_invalid_before']
        $sessionData = [
            'id' => 42,
            'username' => 'test',
            'nom' => 'Test',
            'prenom' => 'User',
            'email' => 'test@example.fr',
            'role' => 'agent',
            'site_id' => 7,
            'is_active' => 1,
            'created_at' => '2025-01-15 10:30:00',
            'sessions_invalid_before' => '2025-07-01 00:00:00', // snake_case key
        ];

        $user = SessionUser::fromSession($sessionData);
        $this->assertSame('2025-07-01 00:00:00', $user->sessionsInvalidBefore, 'sessionsInvalidBefore should use sessions_invalid_before snake_case key');
    }

    public function testFromSessionWithCamelCaseSessionsInvalidBeforeKey(): void
    {
        // Kill Coalesce mutant on line 107: $data['sessionsInvalidBefore'] ?? $data['sessions_invalid_before']
        $sessionData = [
            'id' => 42,
            'username' => 'test',
            'nom' => 'Test',
            'prenom' => 'User',
            'email' => 'test@example.fr',
            'role' => 'agent',
            'site_id' => 7,
            'is_active' => 1,
            'created_at' => '2025-01-15 10:30:00',
            'sessionsInvalidBefore' => '2025-08-01 00:00:00', // camelCase key
        ];

        $user = SessionUser::fromSession($sessionData);
        $this->assertSame('2025-08-01 00:00:00', $user->sessionsInvalidBefore, 'sessionsInvalidBefore should use sessionsInvalidBefore camelCase key');
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // offsetExists() — MatchArmRemoval mutants (line 172)
    // ═══════════════════════════════════════════════════════════════════════════════

    public function testOffsetExistsReturnsTrueForAllValidKeys(): void
    {
        $user = SessionUser::fromRow($this->fullRow);
        
        // Test all valid snake_case keys
        $this->assertTrue($user->offsetExists('id'));
        $this->assertTrue($user->offsetExists('username'));
        $this->assertTrue($user->offsetExists('nom'));
        $this->assertTrue($user->offsetExists('prenom'));
        $this->assertTrue($user->offsetExists('email'));
        $this->assertTrue($user->offsetExists('role'));
        $this->assertTrue($user->offsetExists('site_id'));
        $this->assertTrue($user->offsetExists('is_active'));
        $this->assertTrue($user->offsetExists('created_at'));
        $this->assertTrue($user->offsetExists('updated_at'));
        $this->assertTrue($user->offsetExists('site_code'));
        $this->assertTrue($user->offsetExists('site_nom'));
        $this->assertTrue($user->offsetExists('site_chosen_at'));
        $this->assertTrue($user->offsetExists('sessions_invalid_before'));
        
        // Test all valid camelCase keys
        $this->assertTrue($user->offsetExists('siteId'));
        $this->assertTrue($user->offsetExists('isActive'));
        $this->assertTrue($user->offsetExists('createdAt'));
        $this->assertTrue($user->offsetExists('updatedAt'));
        $this->assertTrue($user->offsetExists('siteCode'));
        $this->assertTrue($user->offsetExists('siteNom'));
        $this->assertTrue($user->offsetExists('siteChosenAt'));
        $this->assertTrue($user->offsetExists('sessionsInvalidBefore'));
    }

    public function testOffsetExistsReturnsFalseForInvalidKeys(): void
    {
        $user = SessionUser::fromRow($this->fullRow);
        
        $this->assertFalse($user->offsetExists('invalid_key'));
        $this->assertFalse($user->offsetExists('password'));
        $this->assertFalse($user->offsetExists('token'));
        $this->assertFalse($user->offsetExists(''));
        $this->assertFalse($user->offsetExists(null));
        $this->assertFalse($user->offsetExists(123));
    }
}
