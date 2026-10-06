<?php
// Modelis tabulai `users`
class User extends Model
{
    // bcrypt ņem vērā tikai pirmos 72 baitus - garākas paroles nepieņemam, lai nebūtu pārsteigumu
    private const PASSWORD_MAX_BYTES = 72;

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT id, username, email, created_at FROM users WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function findByUsername(string $username): ?array
    {
        $stmt = $this->db->prepare('SELECT id, username, password_hash FROM users WHERE username = ?');
        $stmt->execute([$username]);
        return $stmt->fetch() ?: null;
    }

    public function exists(string $username, string $email): bool
    {
        $stmt = $this->db->prepare('SELECT id FROM users WHERE username = ? OR email = ?');
        $stmt->execute([$username, $email]);
        return (bool)$stmt->fetch();
    }

    public function create(string $username, string $email, string $password): int
    {
        // Parole tiek glabāta tikai kā jaucējvērtība (bcrypt ar nejaušu sāli)
        $hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $this->db->prepare('INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)');
        $stmt->execute([$username, $email, $hash]);
        return (int)$this->db->lastInsertId();
    }

    // Pieteikšanās pārbaude. Atgriež lietotāju vai null.
    public function authenticate(string $username, string $password): ?array
    {
        $user = $this->findByUsername($username);

        // Ja lietotāja nav, tik un tā pārbaudām paroli pret izdomātu jaucējvērtību (ar to pašu bcrypt
        // "cenu") - tad atbilde aizņem tikpat ilgu laiku un pēc ātruma nevar uzminēt, kuri lietotāji eksistē
        $cost = defined('PASSWORD_BCRYPT_DEFAULT_COST') ? PASSWORD_BCRYPT_DEFAULT_COST : 10;
        $hash = $user['password_hash'] ?? sprintf('$2y$%02d$', $cost) . 'abcdefghijklmnopqrstuuJ9r0B8WdqMUYbv4a8Xc5VBPqAk0bAPm';
        if (!password_verify($password, $hash) || !$user) {
            return null;
        }

        // Ja PHP noklusējuma algoritms kļuvis stiprāks - pārrēķinām jaucējvērtību
        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            $this->updatePassword((int)$user['id'], $password);
        }
        return $user;
    }

    public function checkPassword(int $id, string $password): bool
    {
        $stmt = $this->db->prepare('SELECT password_hash FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $hash = $stmt->fetchColumn();
        return $hash !== false && password_verify($password, $hash);
    }

    public function updatePassword(int $id, string $password): void
    {
        $stmt = $this->db->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $stmt->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
    }

    // Statistika profila lapai
    public function stats(int $id): array
    {
        $stmt = $this->db->prepare(
            "SELECT (SELECT COUNT(*) FROM tasks WHERE user_id = ?) AS tasks,
                    (SELECT COUNT(*) FROM tasks WHERE user_id = ? AND status = 'pabeigts') AS done,
                    (SELECT COUNT(*) FROM categories WHERE user_id = ?) AS categories,
                    (SELECT COUNT(*) FROM task_shares WHERE user_id = ?) AS shared_with_me,
                    (SELECT COUNT(*) FROM task_files WHERE user_id = ?) AS files,
                    (SELECT COUNT(*) FROM task_activity WHERE user_id = ? AND type = 'comment') AS comments"
        );
        $stmt->execute(array_fill(0, 6, $id));
        return array_map('intval', $stmt->fetch());
    }

    // Konta dzēšana. Datubāzē viss pārējais (uzdevumi, kategorijas, soļi, faili, komentāri,
    // kopīgošana) izdzēšas pats (ON DELETE CASCADE), bet failus no diska jāizdzēš mums.
    public function delete(int $id): void
    {
        $stmt = $this->db->prepare(
            'SELECT stored_name FROM task_files
             WHERE user_id = ? OR task_id IN (SELECT id FROM tasks WHERE user_id = ?)'
        );
        $stmt->execute([$id, $id]);
        $files = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $this->db->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);

        foreach ($files as $name) {
            $path = UPLOAD_DIR . '/' . basename($name);
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    // Reģistrācijas datu validācija. Atgriež kļūdu masīvu (tukšs = viss kārtībā).
    public function validate(string $username, string $email, string $password, string $password2): array
    {
        $errors = [];
        if (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $username)) {
            $errors[] = 'Lietotājvārdam jābūt 3–30 simboliem (burti, cipari, _).';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) {
            $errors[] = 'Nederīga e-pasta adrese.';
        }
        $errors = array_merge($errors, $this->validatePassword($password, $password2));
        if (!$errors && $this->exists($username, $email)) {
            $errors[] = 'Lietotājvārds vai e-pasts jau ir aizņemts.';
        }
        return $errors;
    }

    // Jaunas paroles noteikumi (reģistrācijai un paroles maiņai)
    public function validatePassword(string $password, string $password2): array
    {
        $errors = [];
        if (strlen($password) < 8) {
            $errors[] = 'Parolei jābūt vismaz 8 simbolus garai.';
        } elseif (strlen($password) > self::PASSWORD_MAX_BYTES) {
            $errors[] = 'Parole ir par garu (līdz 72 simboliem).';
        }
        if ($password !== $password2) {
            $errors[] = 'Paroles nesakrīt.';
        }
        return $errors;
    }
}
