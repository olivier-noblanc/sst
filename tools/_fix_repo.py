import sys

path = r'C:\Users\raver\source\repos\sst\src\Repository\ReportRepository.php'
content = open(path, 'rb').read()

# Fix 1: replaceLinkedAgents - wrap in transaction
old1 = (
    b"    public function replaceLinkedAgents(string $reportUuid, array $userIds): void\r\n"
    b"    {\r\n"
    b"        $this->pdo->prepare('DELETE FROM report_agents WHERE report_uuid = ?')->execute([$reportUuid]);\r\n"
    b"        $this->linkAgents($reportUuid, $userIds);\r\n"
    b"    }"
)

new1 = (
    b"    public function replaceLinkedAgents(string $reportUuid, array $userIds): void\r\n"
    b"    {\r\n"
    b"        $this->pdo->beginTransaction();\r\n"
    b"        try {\r\n"
    b"            $this->pdo->prepare('DELETE FROM report_agents WHERE report_uuid = ?')->execute([$reportUuid]);\r\n"
    b"            $this->linkAgents($reportUuid, $userIds);\r\n"
    b"            $this->pdo->commit();\r\n"
    b"        } catch (Exception $e) {\r\n"
    b"            if ($this->pdo->inTransaction()) {\r\n"
    b"                $this->pdo->rollBack();\r\n"
    b"            }\r\n"
    b"            throw $e;\r\n"
    b"        }\r\n"
    b"    }"
)

if old1 in content:
    content = content.replace(old1, new1, 1)
    print('replaceLinkedAgents: FIXED')
else:
    print('replaceLinkedAgents: NOT FOUND (checking LF)...')
    old1_lf = old1.replace(b'\r\n', b'\n')
    new1_lf = new1.replace(b'\r\n', b'\n')
    if old1_lf in content:
        content = content.replace(old1_lf, new1_lf, 1)
        print('replaceLinkedAgents: FIXED (LF)')
    else:
        print('replaceLinkedAgents: STILL NOT FOUND')

# Fix 2: confirmAgentInvite - wrap in transaction
old2 = (
    b"    public function confirmAgentInvite(string $token, int $userId): bool\r\n"
    b"    {\r\n"
    b"        $invite = $this->getAgentInviteByToken($token);\r\n"
    b"        if (!$invite) {\r\n"
    b"            return false;\r\n"
    b"        }\r\n"
    b'        $stmt = $this->pdo->prepare("' + b"\r\n"
    b"            UPDATE report_agent_invites SET confirmed = 1, confirmed_at = datetime('now') WHERE token = ?\r\n"
    b'        ");' + b"\r\n"
    b"        $stmt->execute([$token]);\r\n"
    b"        $this->linkAgents($invite['report_uuid'], [$userId]);\r\n"
    b"        return true;\r\n"
    b"    }"
)

new2 = (
    b"    public function confirmAgentInvite(string $token, int $userId): bool\r\n"
    b"    {\r\n"
    b"        $invite = $this->getAgentInviteByToken($token);\r\n"
    b"        if (!$invite) {\r\n"
    b"            return false;\r\n"
    b"        }\r\n"
    b"        $this->pdo->beginTransaction();\r\n"
    b"        try {\r\n"
    b'            $stmt = $this->pdo->prepare("' + b"\r\n"
    b"                UPDATE report_agent_invites SET confirmed = 1, confirmed_at = datetime('now') WHERE token = ?\r\n"
    b'            ");' + b"\r\n"
    b"            $stmt->execute([$token]);\r\n"
    b"            $this->linkAgents($invite['report_uuid'], [$userId]);\r\n"
    b"            $this->pdo->commit();\r\n"
    b"        } catch (Exception $e) {\r\n"
    b"            if ($this->pdo->inTransaction()) {\r\n"
    b"                $this->pdo->rollBack();\r\n"
    b"            }\r\n"
    b"            throw $e;\r\n"
    b"        }\r\n"
    b"        return true;\r\n"
    b"    }"
)

if old2 in content:
    content = content.replace(old2, new2, 1)
    print('confirmAgentInvite: FIXED')
else:
    print('confirmAgentInvite: NOT FOUND (checking LF)...')
    old2_lf = old2.replace(b'\r\n', b'\n')
    new2_lf = new2.replace(b'\r\n', b'\n')
    if old2_lf in content:
        content = content.replace(old2_lf, new2_lf, 1)
        print('confirmAgentInvite: FIXED (LF)')
    else:
        print('confirmAgentInvite: STILL NOT FOUND')

open(path, 'wb').write(content)
print('Done.')
