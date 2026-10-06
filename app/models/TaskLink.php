<?php
// Modelis tabulai `task_links` - saites pie uzdevuma (lapas, attēli, video).
class TaskLink extends Model
{
    public function forTask(int $taskId): array
    {
        $stmt = $this->db->prepare('SELECT id, url, title FROM task_links WHERE task_id = ? ORDER BY id');
        $stmt->execute([$taskId]);
        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT id, task_id, url, title FROM task_links WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function create(int $taskId, string $url, ?string $title): void
    {
        $stmt = $this->db->prepare('INSERT INTO task_links (task_id, url, title) VALUES (?, ?, ?)');
        $stmt->execute([$taskId, $url, $title]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM task_links WHERE id = ?');
        $stmt->execute([$id]);
    }

    public function validate(string $url, string $title): array
    {
        $errors = [];
        // Atļauti tikai http un https - lai saitē nevarētu ielikt "javascript:..."
        $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
        if (!filter_var($url, FILTER_VALIDATE_URL) || !in_array($scheme, ['http', 'https'], true) || strlen($url) > 500) {
            $errors[] = 'Nederīga saite. Tai jāsākas ar http:// vai https://';
        }
        if (mb_strlen($title) > 100) {
            $errors[] = 'Saites nosaukums nedrīkst pārsniegt 100 simbolus.';
        }
        return $errors;
    }
}
