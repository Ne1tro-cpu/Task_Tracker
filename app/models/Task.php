<?php
// Modelis tabulai `tasks` - galvenā CRUD tabula.
// Piekļuves noteikumi:
//   - īpašnieks (tasks.user_id) var visu: skatīt, rediģēt, dzēst, kopīgot;
//   - lietotājs, ar kuru uzdevums kopīgots (task_shares), var to skatīt,
//     strādāt ar soļiem, saitēm, failiem un komentāriem, bet nevar rediģēt, dzēst vai kopīgot tālāk.
class Task extends Model
{
    // Kopīgā SELECT daļa sarakstam, dēlim un kalendāram
    private const LIST_SELECT =
        'SELECT t.id, t.title, t.description, t.due_date, t.status, t.priority, t.category_id, t.repeat_rule, t.completed_at,
                c.name AS category,
                (SELECT COUNT(*) FROM subtasks s WHERE s.task_id = t.id) AS subtasks_total,
                (SELECT COUNT(*) FROM subtasks s WHERE s.task_id = t.id AND s.is_done = 1) AS subtasks_done,
                (SELECT COUNT(*) FROM task_links l WHERE l.task_id = t.id) AS links_count,
                (SELECT COUNT(*) FROM task_files f WHERE f.task_id = t.id) AS files_count,
                CASE WHEN t.user_id = ? THEN NULL ELSE u.username END AS owner
         FROM tasks t
         JOIN users u ON u.id = t.user_id
         LEFT JOIN categories c ON c.id = t.category_id
         WHERE (t.user_id = ? OR t.id IN (SELECT task_id FROM task_shares WHERE user_id = ?))';

    // READ - savi + ar mani kopīgotie uzdevumi, ar filtru pēc statusa un meklēšanu.
    // $doneSince - ja norādīts, pabeigtos uzdevumus rāda tikai tos, kas pabeigti pēc šī laika
    public function all(int $userId, string $status = '', string $search = '', ?string $doneSince = null): array
    {
        $sql = self::LIST_SELECT;
        $params = [$userId, $userId, $userId];

        if ($status !== '') {
            $sql .= ' AND t.status = ?';
            $params[] = $status;
        }
        if ($search !== '') {
            // Parametrizēts LIKE - meklē nosaukumā un aprakstā
            $sql .= ' AND (t.title LIKE ? OR t.description LIKE ?)';
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
        }
        if ($doneSince !== null) {
            $sql .= " AND (t.status <> 'pabeigts' OR t.completed_at IS NULL OR t.completed_at >= ?)";
            $params[] = $doneSince;
        }

        // Vispirms ar termiņu (tuvākais augšā), tad pēc prioritātes
        $sql .= " ORDER BY t.due_date IS NULL, t.due_date,
                  CASE t.priority WHEN 'augsta' THEN 0 WHEN 'vidēja' THEN 1 ELSE 2 END,
                  t.id DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    // READ - uzdevumi ar termiņu norādītajā periodā (kalendāram)
    public function between(int $userId, string $from, string $to): array
    {
        $stmt = $this->db->prepare(self::LIST_SELECT . " AND t.due_date BETWEEN ? AND ?
            ORDER BY t.due_date, CASE t.priority WHEN 'augsta' THEN 0 WHEN 'vidēja' THEN 1 ELSE 2 END, t.id");
        $stmt->execute([$userId, $userId, $userId, $from, $to]);
        return $stmt->fetchAll();
    }

    // Cik uzdevumu pabeigti pirms $before - tie sarakstā paslēpti.
    // $ownOnly - tikai paša (dēlim), citādi arī ar mani kopīgotie (sarakstam)
    public function countDoneBefore(int $userId, string $before, bool $ownOnly = false): int
    {
        $owner = $ownOnly ? 't.user_id = ?' : '(t.user_id = ? OR t.id IN (SELECT task_id FROM task_shares WHERE user_id = ?))';
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM tasks t WHERE $owner AND t.status = 'pabeigts' AND t.completed_at < ?"
        );
        $stmt->execute($ownOnly ? [$userId, $before] : [$userId, $userId, $before]);
        return (int)$stmt->fetchColumn();
    }

    // Cik uzdevumu lietotājs pabeidza katrā dienā kopš $since (aktivitātes kartei)
    // Atgriež ['2026-10-05' => 3, ...]
    public function completedPerDay(int $userId, string $since): array
    {
        $stmt = $this->db->prepare(
            'SELECT DATE(completed_at) AS day, COUNT(*) AS n
             FROM tasks
             WHERE user_id = ? AND completed_at >= ?
             GROUP BY DATE(completed_at)'
        );
        $stmt->execute([$userId, $since]);
        return array_map('intval', array_column($stmt->fetchAll(), 'n', 'day'));
    }

    // Aktivitātes karte: pabeigto uzdevumu skaits pa dienām pēdējās $weeks nedēļās + sērija
    public function heatmap(int $userId, int $weeks = 26): array
    {
        $today  = new DateTimeImmutable('today');
        $start  = $today->modify('monday this week')->modify('-' . ($weeks - 1) . ' weeks');
        $counts = $this->completedPerDay($userId, $start->format('Y-m-d 00:00:00'));

        // Pašreizējā sērija: dienas pēc kārtas ar vismaz 1 pabeigtu uzdevumu.
        // Ja šodien vēl nekas nav pabeigts, sērija vēl nav pārtrūkusi - skaitām no vakardienas.
        $day = empty($counts[$today->format('Y-m-d')]) ? $today->modify('-1 day') : $today;
        $streak = 0;
        while (!empty($counts[$day->format('Y-m-d')])) {
            $streak++;
            $day = $day->modify('-1 day');
        }

        // Garākā sērija periodā
        $best = 0;
        $run = 0;
        for ($d = $start; $d <= $today; $d = $d->modify('+1 day')) {
            $run = empty($counts[$d->format('Y-m-d')]) ? 0 : $run + 1;
            $best = max($best, $run);
        }

        return [
            'start'  => $start,
            'weeks'  => $weeks,
            'counts' => $counts,
            'streak' => $streak,
            'best'   => $best,
            'total'  => array_sum($counts),
            'today'  => $counts[$today->format('Y-m-d')] ?? 0,
        ];
    }

    // Kopsavilkums sākumlapas logrīkiem (aprēķina no jau ielādētā saraksta, bez SQL)
    public static function summary(array $tasks): array
    {
        $counts = array_fill_keys(STATUSES, 0);
        $byCategory = [];
        $upcoming = [];
        $overdue = 0;
        $dueToday = 0;

        foreach ($tasks as $t) {
            $counts[$t['status']]++;
            $category = $t['category'] ?? 'Bez kategorijas';
            $byCategory[$category] = ($byCategory[$category] ?? 0) + 1;

            $days = days_left($t['due_date']);
            if ($t['status'] !== 'pabeigts' && $days !== null) {
                $upcoming[] = $t + ['days' => $days];
                $overdue  += $days < 0 ? 1 : 0;
                $dueToday += $days === 0 ? 1 : 0;
            }
        }
        usort($upcoming, fn($a, $b) => $a['days'] <=> $b['days']);
        arsort($byCategory);

        $total = count($tasks);
        return [
            'total'      => $total,
            'done'       => $counts['pabeigts'],
            'percent'    => $total ? (int)round($counts['pabeigts'] / $total * 100) : 0,
            'counts'     => $counts,
            'upcoming'   => array_slice($upcoming, 0, 4),
            'byCategory' => array_slice($byCategory, 0, 4, true),
            'maxCat'     => $byCategory ? max($byCategory) : 1,
            'overdue'    => $overdue,
            'dueToday'   => $dueToday,
        ];
    }

    // READ - viens uzdevums, tikai īpašniekam (rediģēšanai un dzēšanai)
    public function find(int $id, int $userId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM tasks WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
        return $stmt->fetch() ?: null;
    }

    // READ - uzdevums, ja lietotājs ir īpašnieks VAI ar viņu kopīgots.
    public function findAccessible(int $id, int $userId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT t.*, u.username AS owner_name, c.name AS category
             FROM tasks t
             JOIN users u ON u.id = t.user_id
             LEFT JOIN categories c ON c.id = t.category_id
             WHERE t.id = ?
               AND (t.user_id = ? OR EXISTS (SELECT 1 FROM task_shares s WHERE s.task_id = t.id AND s.user_id = ?))'
        );
        $stmt->execute([$id, $userId, $userId]);
        return $stmt->fetch() ?: null;
    }

    public function canAccess(int $id, int $userId): bool
    {
        return $this->findAccessible($id, $userId) !== null;
    }

    public function isOwner(int $id, int $userId): bool
    {
        return $this->find($id, $userId) !== null;
    }

    // CREATE
    public function create(int $userId, array $d): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO tasks (user_id, title, description, due_date, status, priority, repeat_rule, category_id, completed_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $userId, $d['title'], $d['description'], $d['due_date'], $d['status'], $d['priority'],
            $d['repeat_rule'], $d['category_id'], $d['status'] === 'pabeigts' ? date('Y-m-d H:i:s') : null,
        ]);
        return (int)$this->db->lastInsertId();
    }

    // UPDATE. completed_at: ieliek laiku, kad uzdevums kļūst pabeigts; notīra, ja vairs nav pabeigts.
    public function update(int $id, int $userId, array $d): void
    {
        $stmt = $this->db->prepare(
            "UPDATE tasks SET title = ?, description = ?, due_date = ?, status = ?, priority = ?,
                    repeat_rule = ?, category_id = ?,
                    completed_at = CASE WHEN ? = 'pabeigts' THEN COALESCE(completed_at, ?) ELSE NULL END
             WHERE id = ? AND user_id = ?"
        );
        $stmt->execute([
            $d['title'], $d['description'], $d['due_date'], $d['status'], $d['priority'],
            $d['repeat_rule'], $d['category_id'], $d['status'], date('Y-m-d H:i:s'), $id, $userId,
        ]);
    }

    // Tikai statusa maiņa (Kanban dēlis)
    public function setStatus(int $id, int $userId, string $status): void
    {
        $stmt = $this->db->prepare(
            "UPDATE tasks SET status = ?,
                    completed_at = CASE WHEN ? = 'pabeigts' THEN COALESCE(completed_at, ?) ELSE NULL END
             WHERE id = ? AND user_id = ?"
        );
        $stmt->execute([$status, $status, date('Y-m-d H:i:s'), $id, $userId]);
    }

    // Tikai termiņa maiņa (kalendārs)
    public function setDue(int $id, int $userId, ?string $date): void
    {
        $stmt = $this->db->prepare('UPDATE tasks SET due_date = ? WHERE id = ? AND user_id = ?');
        $stmt->execute([$date, $id, $userId]);
    }

    // Nākamais termiņš atkārtotam uzdevumam. Vienmēr nākotnē: ja uzdevums pabeigts ar nokavēšanos,
    // izlaižam pagājušās reizes (ikdienas uzdevums, kas nokavēts 5 dienas, nākamreiz būs rīt).
    // Mēneša uzdevums, kura diena nākamajā mēnesī neeksistē (31.), pāriet uz mēneša pēdējo dienu.
    public static function nextDueDate(?string $due, string $rule): string
    {
        $today = new DateTimeImmutable('today');
        $date  = $due ? new DateTimeImmutable($due) : $today;
        $dayOfMonth = (int)$date->format('j');

        for ($i = 0; $i < 1000 && ($i === 0 || $date <= $today); $i++) {
            if ($rule === 'monthly') {
                $next = $date->modify('first day of next month');
                $date = $next->setDate((int)$next->format('Y'), (int)$next->format('n'), min($dayOfMonth, (int)$next->format('t')));
            } else {
                $date = $date->modify($rule === 'daily' ? '+1 day' : '+1 week');
            }
        }
        return $date->format('Y-m-d');
    }

    // Atkārtots uzdevums: izveido nākamo kopiju ar jaunu termiņu un neizpildītiem soļiem.
    // Vecajam uzdevumam atkārtošanos noņem, lai, to atverot un pabeidzot vēlreiz, nerastos dublikāti.
    // Kontrolieris to izsauc transakcijā kopā ar statusa maiņu.
    public function spawnNext(array $task): ?int
    {
        if (empty($task['repeat_rule']) || !isset(REPEATS[$task['repeat_rule']])) {
            return null;
        }

        $newId = $this->create((int)$task['user_id'], [
            'title'       => $task['title'],
            'description' => $task['description'],
            'due_date'    => self::nextDueDate($task['due_date'], $task['repeat_rule']),
            'status'      => 'jauns',
            'priority'    => $task['priority'],
            'repeat_rule' => $task['repeat_rule'],
            'category_id' => $task['category_id'],
        ]);

        $this->db->prepare('INSERT INTO subtasks (task_id, title) SELECT ?, title FROM subtasks WHERE task_id = ? ORDER BY id')
                 ->execute([$newId, $task['id']]);
        $this->db->prepare('UPDATE tasks SET repeat_rule = NULL WHERE id = ?')->execute([$task['id']]);

        return $newId;
    }

    // DELETE (soļi, saites, faili, aktivitāte un kopīgošana izdzēšas līdzi - ON DELETE CASCADE)
    public function delete(int $id, int $userId): bool
    {
        $stmt = $this->db->prepare('DELETE FROM tasks WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
        return $stmt->rowCount() > 0;
    }

    // Datuma pārbaude formātā YYYY-MM-DD
    public static function validDate(string $date): bool
    {
        $d = DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }

    // Validācija. Tukšie lauki pārvērsti par NULL, lai tos varētu saglabāt datubāzē.
    public function validate(array &$d, int $userId): array
    {
        $errors = [];

        if ($d['title'] === '' || mb_strlen($d['title']) > 100) {
            $errors[] = 'Nosaukums ir obligāts (līdz 100 simboliem).';
        }
        if (mb_strlen($d['description']) > 2000) {
            $errors[] = 'Apraksts nedrīkst pārsniegt 2000 simbolus.';
        }
        if ($d['due_date'] !== '' && !self::validDate($d['due_date'])) {
            $errors[] = 'Nederīgs datums.';
        }
        if (!in_array($d['status'], STATUSES, true)) {
            $errors[] = 'Nederīgs statuss.';
        }
        if (!in_array($d['priority'], PRIORITIES, true)) {
            $errors[] = 'Nederīga prioritāte.';
        }
        if ($d['repeat_rule'] !== '' && !isset(REPEATS[$d['repeat_rule']])) {
            $errors[] = 'Nederīga atkārtošanās.';
        }
        // Kategorijai jābūt tukšai vai jāpieder šim lietotājam
        if ($d['category_id'] !== '' && !(ctype_digit($d['category_id'])
                && (new Category())->belongsTo((int)$d['category_id'], $userId))) {
            $errors[] = 'Nederīga kategorija.';
        }

        $d['description'] = $d['description'] !== '' ? $d['description'] : null;
        $d['due_date']    = $d['due_date'] !== '' ? $d['due_date'] : null;
        $d['repeat_rule'] = $d['repeat_rule'] !== '' ? $d['repeat_rule'] : null;
        $d['category_id'] = $d['category_id'] !== '' ? (int)$d['category_id'] : null;

        return $errors;
    }
}
