<?php
// Modelis tabulai `task_shares` - ar kuriem lietotājiem uzdevums kopīgots.
class TaskShare extends Model
{
    public function forTask(int $taskId): array
    {
        $stmt = $this->db->prepare(
            'SELECT s.id, u.username
             FROM task_shares s
             JOIN users u ON u.id = s.user_id
             WHERE s.task_id = ?
             ORDER BY u.username'
        );
        $stmt->execute([$taskId]);
        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT s.id, s.task_id, s.user_id, u.username
             FROM task_shares s
             JOIN users u ON u.id = s.user_id
             WHERE s.id = ?'
        );
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function exists(int $taskId, int $userId): bool
    {
        $stmt = $this->db->prepare('SELECT id FROM task_shares WHERE task_id = ? AND user_id = ?');
        $stmt->execute([$taskId, $userId]);
        return (bool)$stmt->fetch();
    }

    public function create(int $taskId, int $userId): void
    {
        $stmt = $this->db->prepare('INSERT INTO task_shares (task_id, user_id) VALUES (?, ?)');
        $stmt->execute([$taskId, $userId]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM task_shares WHERE id = ?');
        $stmt->execute([$id]);
    }
}
