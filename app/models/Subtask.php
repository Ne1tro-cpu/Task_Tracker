<?php
// Modelis tabulai `subtasks` - uzdevuma soļi.
// Piekļuves pārbaudi (vai lietotājs drīkst strādāt ar uzdevumu) veic kontrolieris ar Task::canAccess().
class Subtask extends Model
{
    public function forTask(int $taskId): array
    {
        $stmt = $this->db->prepare('SELECT id, title, is_done FROM subtasks WHERE task_id = ? ORDER BY id');
        $stmt->execute([$taskId]);
        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT id, task_id, title, is_done FROM subtasks WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function create(int $taskId, string $title): int
    {
        $stmt = $this->db->prepare('INSERT INTO subtasks (task_id, title) VALUES (?, ?)');
        $stmt->execute([$taskId, $title]);
        return (int)$this->db->lastInsertId();
    }

    // Pārslēdz izpildīts / neizpildīts
    public function toggle(int $id): void
    {
        $stmt = $this->db->prepare('UPDATE subtasks SET is_done = 1 - is_done WHERE id = ?');
        $stmt->execute([$id]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM subtasks WHERE id = ?');
        $stmt->execute([$id]);
    }

    public function validate(string $title): array
    {
        if ($title === '' || mb_strlen($title) > 100) {
            return ['Soļa nosaukums ir obligāts (līdz 100 simboliem).'];
        }
        return [];
    }
}
