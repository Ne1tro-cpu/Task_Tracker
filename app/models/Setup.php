<?php
// Datubāzes sagatavošana: izveide no schema.sql, atjaunināšana ar migration_v*.sql un demo dati.
// Neizmanto Model klasi, jo datubāzes vēl var nebūt.
class Setup
{
    // Atjauninājumi pēc kārtas: fails => tabulas un kolonnas, kuras tas pievieno
    private const MIGRATIONS = [
        'migration_v2.sql' => ['tasks.priority', 'subtasks', 'task_links', 'task_shares'],
        'migration_v3.sql' => ['tasks.repeat_rule', 'tasks.completed_at', 'task_files', 'task_activity'],
    ];

    // MySQL kļūdu kodi, ko atjauninot drīkst izlaist: tabula / kolonna / indekss jau ir
    private const ALREADY_EXISTS = [1050, 1060, 1061];

    // Demo konti (tikai lokālai demonstrācijai!)
    public const DEMO_ACCOUNTS = ['demo' => 'demo1234', 'anna' => 'anna1234'];

    /* ---------------------------------------------------------------
       Stāvoklis
       --------------------------------------------------------------- */

    // 'ok' | 'database' (nav datubāzes) | 'tables' (nav tabulu) | 'upgrade' (jāatjaunina)
    public function status(): string
    {
        $s = $this->existing();
        if (!$s['database']) {
            return 'database';
        }
        if (!in_array('users', $s['tables'], true) || !in_array('tasks', $s['tables'], true)) {
            return 'tables';
        }
        return $this->pendingMigrations($s) ? 'upgrade' : 'ok';
    }

    // Atjauninājumi, kuru izmaiņu datubāzē vēl nav
    public function pendingMigrations(?array $s = null): array
    {
        $s ??= $this->existing();
        $pending = [];
        foreach (self::MIGRATIONS as $file => $needs) {
            foreach ($needs as $item) {
                $found = str_contains($item, '.') ? in_array($item, $s['columns'], true) : in_array($item, $s['tables'], true);
                if (!$found) {
                    $pending[] = $file;
                    break;
                }
            }
        }
        return $pending;
    }

    /* ---------------------------------------------------------------
       Izveide un atjaunināšana
       --------------------------------------------------------------- */

    public function install(): void
    {
        $pdo = $this->connect(create: true);
        $this->runFile($pdo, 'schema.sql');

        // Ja datubāzē jau bija vecākas versijas tabulas - pievienojam trūkstošo
        foreach ($this->pendingMigrations() as $file) {
            $this->runFile($pdo, $file);
        }
    }

    // Atgriež izpildīto atjauninājumu sarakstu
    public function upgrade(): array
    {
        $pending = $this->pendingMigrations();
        $pdo = $this->connect();
        foreach ($pending as $file) {
            $this->runFile($pdo, $file);
        }
        return $pending;
    }

    /* ---------------------------------------------------------------
       Demo dati: lietotāji demo un anna ar uzdevumiem, kategorijām,
       soļiem, saitēm, kopīgošanu, komentāriem un 5 mēnešu vēsturi
       --------------------------------------------------------------- */

    // Atgriež izveidotos kontus ['demo' => 'demo1234', ...] vai [], ja tie jau ir
    public function seedDemo(): array
    {
        $users = new User();
        foreach (array_keys(self::DEMO_ACCOUNTS) as $name) {
            if ($users->findByUsername($name)) {
                return [];
            }
        }

        $db = Database::get();
        $db->beginTransaction();

        $demo = $users->create('demo', 'demo@example.com', self::DEMO_ACCOUNTS['demo']);
        $anna = $users->create('anna', 'anna@example.com', self::DEMO_ACCOUNTS['anna']);

        $categories = new Category();
        $cat = [];
        foreach (['Skola', 'Mājas', 'Sports', 'Hobiji'] as $name) {
            $cat[$name] = $categories->create($demo, $name);
        }

        $tasks    = new Task();
        $subtasks = new Subtask();
        $links    = new TaskLink();
        $activity = new Activity();
        $day = fn(int $offset) => date('Y-m-d', strtotime("$offset days"));

        // [nosaukums, apraksts, termiņš, statuss, prioritāte, atkārtošana, kategorija, soļi [nosaukums => izpildīts]]
        $demoTasks = [
            ['Nodot PHP praktisko darbu', 'Augšupielādēt Moodle un sagatavot prezentāciju skolotājam.', $day(0), 'procesā', 'augsta', null, 'Skola',
                ['MVC struktūra' => 1, 'Datubāze un modeļi' => 1, 'Dizains' => 1, 'README fails' => 0, 'Prezentācija' => 0]],
            ['Matemātikas kontroldarbs', 'Logaritmi un eksponentfunkcijas.', $day(1), 'jauns', 'augsta', null, 'Skola',
                ['Atkārtot formulas' => 0, 'Izpildīt 20 uzdevumus' => 0, 'Pārbaudīt atbildes' => 0]],
            ['Iztīrīt istabu', null, $day(-2), 'jauns', 'zema', 'weekly', 'Mājas', []],
            ['Rīta skrējiens 5 km', 'Mierīgā tempā, pēc tam izstaipīties.', $day(1), 'jauns', 'vidēja', 'daily', 'Sports', []],
            ['Izlasīt «Mērnieku laiki»', 'Literatūrai, 1.–5. nodaļa.', $day(5), 'procesā', 'zema', null, 'Skola',
                ['1. nodaļa' => 1, '2. nodaļa' => 1, '3. nodaļa' => 0, '4. nodaļa' => 0, '5. nodaļa' => 0]],
            ['Nopirkt dāvanu mammai', 'Dzimšanas diena nākamnedēļ.', $day(9), 'jauns', 'vidēja', null, 'Mājas', []],
            ['Iemācīties 3 akordus ģitārai', 'Am, C un G.', null, 'jauns', 'zema', null, 'Hobiji', []],
            ['Atjaunināt CV', null, null, 'pabeigts', 'vidēja', null, null, []],
        ];

        $ids = [];
        foreach ($demoTasks as [$title, $desc, $due, $status, $prio, $repeat, $category, $steps]) {
            $id = $tasks->create($demo, [
                'title' => $title, 'description' => $desc, 'due_date' => $due, 'status' => $status,
                'priority' => $prio, 'repeat_rule' => $repeat, 'category_id' => $category ? $cat[$category] : null,
            ]);
            $ids[$title] = $id;
            $activity->event($id, $demo, 'izveidoja uzdevumu');
            foreach ($steps as $stepTitle => $done) {
                $stepId = $subtasks->create($id, $stepTitle);
                if ($done) {
                    $subtasks->toggle($stepId);
                }
            }
        }
        $php = $ids['Nodot PHP praktisko darbu'];
        $links->create($php, 'https://www.w3schools.com/php/php_forms.asp', 'PHP formas (W3Schools)');
        $links->create($php, 'https://www.php.net/manual/en/book.pdo.php', 'PDO dokumentācija');

        // Annas uzdevums, kas kopīgots ar demo lietotāju
        $group = $tasks->create($anna, [
            'title' => 'Grupas projekts: prezentācija par datubāzēm', 'description' => 'Kopīgs darbs ar demo. Termiņš - piektdiena.',
            'due_date' => $day(3), 'status' => 'procesā', 'priority' => 'augsta', 'repeat_rule' => null, 'category_id' => null,
        ]);
        $subtasks->toggle($subtasks->create($group, 'Atrast piemērus'));
        $subtasks->create($group, 'Sagatavot slaidus par SQL injekcijām');
        (new TaskShare())->create($group, $demo);
        $activity->event($group, $anna, 'izveidoja uzdevumu');
        $activity->event($group, $anna, 'kopīgoja ar demo');
        $activity->comment($group, $anna, "Sveiks! Vai vari uztaisīt slaidus par SQL injekcijām?\nEs paņemšu ievadu un secinājumus.");

        // 5 mēnešu vēsture aktivitātes kartei (pēdējās 7 dienas - sērija)
        $titles = ['Atkārtot vārdus angļu valodā', 'Rīta skrējiens', 'Nopirkt maizi', 'Izmazgāt traukus', 'Fizikas mājasdarbs',
                   'Iznest atkritumus', 'Piezvanīt vecmāmiņai', 'Ģitāras treniņš', 'Ķīmijas laboratorijas darbs', 'Sakārtot rakstāmgaldu'];
        $insert = $db->prepare("INSERT INTO tasks (user_id, category_id, title, status, priority, due_date, completed_at, created_at)
                                VALUES (?, ?, ?, 'pabeigts', ?, ?, ?, ?)");
        $catIds = array_values($cat);
        mt_srand(2026);   // vienmēr vienāda "nejaušā" vēsture
        for ($i = 150; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $weekend = (int)date('N', strtotime($date)) >= 6;
            $count = $i <= 7 ? mt_rand(1, 4) : (mt_rand(0, 9) < ($weekend ? 3 : 6) ? mt_rand(1, $weekend ? 2 : 5) : 0);
            for ($k = 0; $k < $count; $k++) {
                $time = sprintf('%s %02d:%02d:00', $date, mt_rand(8, 21), mt_rand(0, 59));
                $insert->execute([$demo, $catIds[mt_rand(0, count($catIds) - 1)], $titles[mt_rand(0, count($titles) - 1)],
                                  PRIORITIES[mt_rand(0, 2)], $date, $time, $time]);
            }
        }

        $db->commit();
        return self::DEMO_ACCOUNTS;
    }

    /* ---------------------------------------------------------------
       Palīgmetodes
       --------------------------------------------------------------- */

    // Kas datubāzē jau ir (no MySQL information_schema)
    private function existing(): array
    {
        $pdo = Database::server();
        $name = Database::name();

        $st = $pdo->prepare('SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?');
        $st->execute([$name]);
        $exists = (bool)$st->fetchColumn();

        $st = $pdo->prepare('SELECT LOWER(TABLE_NAME) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ?');
        $st->execute([$name]);
        $tables = $st->fetchAll(PDO::FETCH_COLUMN);

        $st = $pdo->prepare("SELECT LOWER(CONCAT(TABLE_NAME, '.', COLUMN_NAME)) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ?");
        $st->execute([$name]);
        $columns = $st->fetchAll(PDO::FETCH_COLUMN);

        return ['database' => $exists, 'tables' => $tables, 'columns' => $columns];
    }

    private function connect(bool $create = false): PDO
    {
        $pdo = Database::server();
        $name = Database::name();   // pārbaudīts: tikai burti, cipari un _
        if ($create) {
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        }
        $pdo->exec("USE `$name`");
        return $pdo;
    }

    // Izpilda SQL failu pa vienam vaicājumam. CREATE DATABASE un USE izlaiž -
    // datubāzes nosaukums tiek ņemts no config.php.
    private function runFile(PDO $pdo, string $file): void
    {
        $sql = file_get_contents(DATABASE_DIR . '/' . $file);
        $sql = preg_replace('/--[^\r\n]*/', '', $sql);   // komentāri

        foreach (preg_split('/;\s*(?:\r?\n|$)/', $sql) as $statement) {
            $statement = trim($statement);
            if ($statement === '' || preg_match('/^(CREATE\s+DATABASE|USE)\b/i', $statement)) {
                continue;
            }
            try {
                $pdo->exec($statement);
            } catch (PDOException $e) {
                if (!in_array((int)($e->errorInfo[1] ?? 0), self::ALREADY_EXISTS, true)) {
                    throw $e;
                }
            }
        }
    }
}
