<?php
/**
 * User data access and admin operations.
 *
 * Location: /classes/User.php
 */

declare(strict_types=1);

final class User
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * Find user by id with role slug/name.
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT u.*, r.slug AS role_slug, r.name AS role_name
             FROM users u
             INNER JOIN roles r ON r.id = u.role_id
             WHERE u.id = ?
             LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Find by username OR email (login identifier).
     */
    public function findByLoginIdentifier(string $identifier): ?array
    {
        $identifier = trim($identifier);
        $stmt = $this->pdo->prepare(
            'SELECT u.*, r.slug AS role_slug, r.name AS role_name
             FROM users u
             INNER JOIN roles r ON r.id = u.role_id
             WHERE u.username = ? OR u.email = ?
             LIMIT 1'
        );
        $stmt->execute([$identifier, $identifier]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT u.*, r.slug AS role_slug, r.name AS role_name
             FROM users u
             INNER JOIN roles r ON r.id = u.role_id
             WHERE u.email = ?
             LIMIT 1'
        );
        $stmt->execute([trim($email)]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Create a user. Password is hashed unless already hashed (migration import).
     *
     * @return int new user id
     */
    public function create(array $data): int
    {
        $password = (string) $data['password'];
        if (!is_password_hashed($password)) {
            $password = hash_password($password);
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO users (username, email, password, phone, full_name, role_id, is_active, password_changed_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            $data['username'],
            $data['email'],
            $password,
            $data['phone'] ?? null,
            $data['full_name'] ?? '',
            (int) $data['role_id'],
            isset($data['is_active']) ? (int) (bool) $data['is_active'] : 1,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function updateProfile(int $id, array $data): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE users SET
                username = ?, email = ?, phone = ?, full_name = ?,
                role_id = ?, is_active = ?, updated_at = NOW()
             WHERE id = ?'
        );
        return $stmt->execute([
            $data['username'],
            $data['email'],
            $data['phone'] ?? null,
            $data['full_name'] ?? '',
            (int) $data['role_id'],
            (int) (bool) $data['is_active'],
            $id,
        ]);
    }

    public function setActive(int $id, bool $active): bool
    {
        $stmt = $this->pdo->prepare('UPDATE users SET is_active = ?, updated_at = NOW() WHERE id = ?');
        return $stmt->execute([$active ? 1 : 0, $id]);
    }

    public function setRole(int $id, int $roleId): bool
    {
        $stmt = $this->pdo->prepare('UPDATE users SET role_id = ?, updated_at = NOW() WHERE id = ?');
        return $stmt->execute([$roleId, $id]);
    }

    public function setPassword(int $id, string $plainPassword): bool
    {
        $hash = hash_password($plainPassword);
        $stmt = $this->pdo->prepare(
            'UPDATE users SET password = ?, password_changed_at = NOW(), failed_login_attempts = 0, locked_until = NULL, updated_at = NOW() WHERE id = ?'
        );
        return $stmt->execute([$hash, $id]);
    }

    public function recordFailedLogin(int $id, int $maxAttempts, int $lockoutMinutes): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE users SET
                failed_login_attempts = failed_login_attempts + 1,
                locked_until = CASE
                    WHEN failed_login_attempts + 1 >= ? THEN DATE_ADD(NOW(), INTERVAL ? MINUTE)
                    ELSE locked_until
                END,
                updated_at = NOW()
             WHERE id = ?'
        );
        $stmt->execute([$maxAttempts, $lockoutMinutes, $id]);
    }

    public function clearLockout(int $id): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE users SET failed_login_attempts = 0, locked_until = NULL, last_login_at = NOW(), updated_at = NOW() WHERE id = ?'
        );
        $stmt->execute([$id]);
    }

    public function isLocked(array $user): bool
    {
        if (empty($user['locked_until'])) {
            return false;
        }
        return strtotime((string) $user['locked_until']) > time();
    }

    /**
     * @return list<array>
     */
    public function listUsers(): array
    {
        $stmt = $this->pdo->query(
            'SELECT u.id, u.username, u.email, u.phone, u.full_name, u.role_id, u.is_active,
                    u.last_login_at, u.created_at, u.failed_login_attempts, u.locked_until,
                    r.name AS role_name, r.slug AS role_slug
             FROM users u
             INNER JOIN roles r ON r.id = u.role_id
             ORDER BY u.id ASC'
        );
        return $stmt->fetchAll() ?: [];
    }

    /**
     * @return list<array{id:int,name:string,slug:string}>
     */
    public function listRoles(): array
    {
        $stmt = $this->pdo->query('SELECT id, name, slug FROM roles ORDER BY id ASC');
        return $stmt->fetchAll() ?: [];
    }

    public function roleIdBySlug(string $slug): ?int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM roles WHERE slug = ? LIMIT 1');
        $stmt->execute([$slug]);
        $id = $stmt->fetchColumn();
        return $id === false ? null : (int) $id;
    }

    public function usernameExists(string $username, ?int $exceptId = null): bool
    {
        if ($exceptId) {
            $stmt = $this->pdo->prepare('SELECT id FROM users WHERE username = ? AND id != ? LIMIT 1');
            $stmt->execute([$username, $exceptId]);
        } else {
            $stmt = $this->pdo->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
            $stmt->execute([$username]);
        }
        return (bool) $stmt->fetchColumn();
    }

    public function emailExists(string $email, ?int $exceptId = null): bool
    {
        if ($exceptId) {
            $stmt = $this->pdo->prepare('SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1');
            $stmt->execute([$email, $exceptId]);
        } else {
            $stmt = $this->pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
        }
        return (bool) $stmt->fetchColumn();
    }

    public function countUsers(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    }
}
