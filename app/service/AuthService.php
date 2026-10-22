<?php
declare(strict_types=1);

namespace app\service;

use app\exception\ValidationException;
use think\facade\Db;
use think\facade\Session;

final class AuthService
{
    public function current(): ?array
    {
        $id = Session::get('auth.id');
        if (!$id) {
            return null;
        }
        if (time() - (int) Session::get('auth.last_activity', 0) > 7200
            || time() - (int) Session::get('auth.started_at', 0) > 28800) {
            Session::destroy();
            return null;
        }
        $user = Db::name('users')->field('id,name,email,session_version')->where('id', $id)->find();
        if (!$user || (int) $user['session_version'] !== (int) Session::get('auth.version')) {
            Session::destroy();
            return null;
        }
        Session::set('auth.last_activity', time());
        return $user;
    }

    public function login(array $input, string $ip): bool
    {
        $email = is_string($input['email'] ?? null) ? strtolower(trim($input['email'])) : '';
        $password = is_string($input['password'] ?? null) ? $input['password'] : '';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190 || strlen($password) > 72) {
            return false;
        }
        // Both counters are shared across sessions; changing email cannot bypass the IP limit.
        $identityKey = hash('sha256', 'identity:' . $ip . ':' . $email);
        $ipKey = hash('sha256', 'ip:' . $ip);
        $ipCount = $this->consumeAttempt($ipKey);
        $identityCount = $this->consumeAttempt($identityKey);
        if ($ipCount > 30 || $identityCount > 5) {
            throw new ValidationException(['login' => 'Terlalu banyak percobaan. Coba lagi setelah 15 menit.']);
        }
        $user = Db::name('users')->where('email', $email)->find();
        $dummyHash = '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi';
        $valid = password_verify($password, $user['password'] ?? $dummyHash);
        if (!$user || !$valid) {
            return false;
        }
        $updates = ['last_login_at' => date('Y-m-d H:i:s')];
        if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
            $updates['password'] = password_hash($password, PASSWORD_DEFAULT);
        }
        Db::name('users')->where('id', $user['id'])->update($updates);
        Db::name('login_attempts')->where('throttle_key', $identityKey)->delete();
        Db::name('login_attempts')->where('window_started', '<', time() - 86400)->delete();
        Session::regenerate(true);
        Session::set('auth', [
            'id' => (int) $user['id'], 'version' => (int) $user['session_version'],
            'started_at' => time(), 'last_activity' => time(),
        ]);
        Session::set('csrf_token', bin2hex(random_bytes(32)));
        return true;
    }

    private function consumeAttempt(string $key): int
    {
        $pdo = Db::connect()->connect();
        $cutoff = time() - 900;
        $upsert = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'sqlite'
            ? 'ON CONFLICT(throttle_key) DO UPDATE SET attempts = CASE WHEN window_started <= ? THEN 1 ELSE attempts + 1 END, window_started = CASE WHEN window_started <= ? THEN ? ELSE window_started END'
            : 'ON DUPLICATE KEY UPDATE attempts = CASE WHEN window_started <= ? THEN 1 ELSE attempts + 1 END, window_started = CASE WHEN window_started <= ? THEN ? ELSE window_started END';
        $statement = $pdo->prepare('INSERT INTO login_attempts (throttle_key, attempts, window_started) VALUES (?, 1, ?) ' . $upsert);
        $statement->execute([$key, time(), $cutoff, $cutoff, time()]);
        return (int) Db::name('login_attempts')->where('throttle_key', $key)->value('attempts');
    }

    public static function validatePassword(string $password, string $confirmation): void
    {
        if (strlen($password) < 12 || strlen($password) > 72 || $password !== $confirmation) {
            throw new ValidationException(['password' => 'Password harus 12–72 byte dan konfirmasinya harus sama.']);
        }
    }

    public function changePassword(int $id, array $input): void
    {
        $old = is_string($input['current_password'] ?? null) ? $input['current_password'] : '';
        $password = is_string($input['password'] ?? null) ? $input['password'] : '';
        $confirmation = is_string($input['password_confirmation'] ?? null) ? $input['password_confirmation'] : '';
        self::validatePassword($password, $confirmation);
        $user = Db::name('users')->where('id', $id)->find();
        if (!$user || !password_verify($old, $user['password'])) {
            throw new ValidationException(['current_password' => 'Password saat ini tidak sesuai.']);
        }
        Db::name('users')->where('id', $id)->update([
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'session_version' => Db::raw('session_version + 1'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        Session::destroy();
    }
}
