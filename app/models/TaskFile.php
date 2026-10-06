<?php
// Modelis tabulai `task_files` - pielikumi.
// Faili glabājas mapē uploads/ ar nejaušu nosaukumu (nevis lietotāja dotu),
// un tos var lejupielādēt tikai caur files/download, kas pārbauda piekļuvi.
class TaskFile extends Model
{
    public function forTask(int $taskId): array
    {
        $stmt = $this->db->prepare(
            'SELECT f.id, f.original_name, f.mime, f.size, f.created_at, u.username
             FROM task_files f
             JOIN users u ON u.id = f.user_id
             WHERE f.task_id = ?
             ORDER BY f.id DESC'
        );
        $stmt->execute([$taskId]);
        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM task_files WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    // Pārbauda augšupielādēto failu un saglabā to.
    // Atgriež ['errors' => [...], 'name' => attīrītais faila nosaukums]; tukšs errors = izdevās.
    public function store(int $taskId, int $userId, ?array $upload): array
    {
        $fail = fn(string $msg) => ['errors' => [$msg], 'name' => ''];
        $tooBig = 'Fails ir par lielu (maksimums ' . (UPLOAD_MAX_SIZE / 1024 / 1024) . ' MB).';

        if (!$upload || !isset($upload['error']) || is_array($upload['error'])) {
            return $fail('Fails netika atsūtīts.');
        }
        if ($upload['error'] === UPLOAD_ERR_INI_SIZE || $upload['error'] === UPLOAD_ERR_FORM_SIZE) {
            return $fail($tooBig);
        }
        if ($upload['error'] === UPLOAD_ERR_NO_FILE) {
            return $fail('Izvēlies failu.');
        }
        if ($upload['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($upload['tmp_name'])) {
            return $fail('Augšupielāde neizdevās.');
        }
        if ($upload['size'] > UPLOAD_MAX_SIZE) {
            return $fail($tooBig);
        }

        // Paplašinājums no nosaukuma + īstais tips no faila satura (finfo)
        $name = $this->cleanName((string)$upload['name']);
        $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($upload['tmp_name']);
        if (!isset(UPLOAD_TYPES[$ext]) || !in_array($mime, UPLOAD_TYPES[$ext], true)) {
            return $fail('Šāda veida failu nevar pievienot. Atļauti: ' . implode(', ', array_keys(UPLOAD_TYPES)) . '.');
        }

        ensure_private_dir(UPLOAD_DIR);
        $stored = bin2hex(random_bytes(16)) . '.' . $ext;
        if (!move_uploaded_file($upload['tmp_name'], UPLOAD_DIR . '/' . $stored)) {
            return $fail('Neizdevās saglabāt failu.');
        }

        $stmt = $this->db->prepare(
            'INSERT INTO task_files (task_id, user_id, original_name, stored_name, mime, size, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$taskId, $userId, $name, $stored, $mime, (int)$upload['size'], date('Y-m-d H:i:s')]);
        return ['errors' => [], 'name' => $name];
    }

    // Faila nosaukums tikai attēlošanai: bez ceļa, vadības simboliem un nederīga UTF-8, līdz 255 simboliem
    private function cleanName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        if (!mb_check_encoding($name, 'UTF-8')) {
            $name = 'fails.' . strtolower(pathinfo($name, PATHINFO_EXTENSION));
        }
        $name = trim(preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?? '');
        if (mb_strlen($name) > 255) {
            $ext = pathinfo($name, PATHINFO_EXTENSION);
            $name = mb_substr($name, 0, 250 - strlen($ext)) . '.' . $ext;
        }
        return $name !== '' ? $name : 'fails';
    }

    public function path(array $file): string
    {
        return UPLOAD_DIR . '/' . basename($file['stored_name']);
    }

    public function delete(array $file): void
    {
        $this->db->prepare('DELETE FROM task_files WHERE id = ?')->execute([$file['id']]);
        if (is_file($this->path($file))) {
            unlink($this->path($file));
        }
    }

    // Pirms uzdevuma dzēšanas izdzēšam arī failus no diska (DB ieraksti izdzēšas ar CASCADE)
    public function deleteFilesOfTask(int $taskId): void
    {
        $stmt = $this->db->prepare('SELECT stored_name FROM task_files WHERE task_id = ?');
        $stmt->execute([$taskId]);
        foreach ($stmt->fetchAll() as $f) {
            $path = UPLOAD_DIR . '/' . basename($f['stored_name']);
            if (is_file($path)) {
                unlink($path);
            }
        }
    }
}
