<?php
// Modelis tabulai `task_activity` - uzdevuma vēsture un komentāri.
//   type = 'event'   -> automātisks ieraksts ("pievienoja soli", "mainīja statusu" ...)
//   type = 'comment' -> lietotāja komentārs
class Activity extends Model
{
    public function forTask(int $taskId): array
    {
        $stmt = $this->db->prepare(
            'SELECT a.id, a.user_id, a.type, a.body, a.created_at, u.username
             FROM task_activity a
             JOIN users u ON u.id = a.user_id
             WHERE a.task_id = ?
             ORDER BY a.created_at DESC, a.id DESC
             LIMIT 50'
        );
        $stmt->execute([$taskId]);
        return $stmt->fetchAll();
    }

    public function event(int $taskId, int $userId, string $text): void
    {
        $this->add($taskId, $userId, 'event', $text);
    }

    public function comment(int $taskId, int $userId, string $text): void
    {
        $this->add($taskId, $userId, 'comment', $text);
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT id, task_id, user_id, type FROM task_activity WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    // Dzēst var tikai savu komentāru
    public function deleteComment(int $id, int $userId): bool
    {
        $stmt = $this->db->prepare("DELETE FROM task_activity WHERE id = ? AND user_id = ? AND type = 'comment'");
        $stmt->execute([$id, $userId]);
        return $stmt->rowCount() > 0;
    }

    public function validateComment(string $text): array
    {
        if ($text === '' || mb_strlen($text) > 1000) {
            return ['Komentārs nevar būt tukšs vai garāks par 1000 simboliem.'];
        }
        return [];
    }

    private function add(int $taskId, int $userId, string $type, string $text): void
    {
        $stmt = $this->db->prepare('INSERT INTO task_activity (task_id, user_id, type, body, created_at) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$taskId, $userId, $type, mb_substr($text, 0, 1000), date('Y-m-d H:i:s')]);
    }
}
