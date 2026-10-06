<?php
// Modelis tabulai `categories`. Katrs vaicājums satur user_id,
// lai lietotājs var piekļūt tikai savām kategorijām.
class Category extends Model
{
    // Kategorijas ar uzdevumu skaitu katrā (JOIN ar tasks)
    public function allWithCounts(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT c.id, c.name, COUNT(t.id) AS task_count
             FROM categories c
             LEFT JOIN tasks t ON t.category_id = c.id
             WHERE c.user_id = ?
             GROUP BY c.id, c.name
             ORDER BY c.name'
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function all(int $userId): array
    {
        $stmt = $this->db->prepare('SELECT id, name FROM categories WHERE user_id = ? ORDER BY name');
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function belongsTo(int $id, int $userId): bool
    {
        $stmt = $this->db->prepare('SELECT id FROM categories WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
        return (bool)$stmt->fetch();
    }

    public function create(int $userId, string $name): int
    {
        $stmt = $this->db->prepare('INSERT INTO categories (user_id, name) VALUES (?, ?)');
        $stmt->execute([$userId, $name]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, int $userId, string $name): bool
    {
        $stmt = $this->db->prepare('UPDATE categories SET name = ? WHERE id = ? AND user_id = ?');
        $stmt->execute([$name, $id, $userId]);
        return $stmt->rowCount() > 0;
    }

    // Uzdevumi paliek - to category_id kļūst NULL (ON DELETE SET NULL)
    public function delete(int $id, int $userId): bool
    {
        $stmt = $this->db->prepare('DELETE FROM categories WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
        return $stmt->rowCount() > 0;
    }

    // $exceptId - pārdēvējot pašu kategoriju neuzskatām par dublikātu
    public function validate(string $name, int $userId, ?int $exceptId = null): array
    {
        if ($name === '' || mb_strlen($name) > 50) {
            return ['Kategorijas nosaukums ir obligāts (līdz 50 simboliem).'];
        }
        $stmt = $this->db->prepare('SELECT id FROM categories WHERE user_id = ? AND name = ? AND id <> ?');
        $stmt->execute([$userId, $name, $exceptId ?? 0]);
        if ($stmt->fetch()) {
            return ["Kategorija \"$name\" jau ir."];
        }
        return [];
    }
}
