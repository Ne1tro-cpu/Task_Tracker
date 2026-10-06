<?php
// Uzdevumu CRUD, meklēšana, statusa un termiņa maiņa (dēlim un kalendāram)
class TaskController extends Controller
{
    // Skatā "Visi" sen pabeigtos (vecākus par šo) paslēpjam, lai saraksts neaug bezgalīgi
    private const HIDE_DONE_AFTER = '-14 days';

    private Task $tasks;
    private Activity $activity;

    public function __construct()
    {
        $this->tasks = new Task();
        $this->activity = new Activity();
    }

    // READ: GET index.php?r=tasks[&status=...][&q=...]
    public function index(): void
    {
        $userId = $this->requireLogin();

        $status = $_GET['status'] ?? '';
        if (!in_array($status, STATUSES, true)) {
            $status = '';
        }
        $search = is_string($_GET['q'] ?? null) ? mb_substr(trim($_GET['q']), 0, 100) : '';

        // Bez filtra un meklēšanas sen pabeigtos nerādām (tos var atrast ar filtru "pabeigts")
        $hideBefore = $status === '' && $search === '' ? date('Y-m-d H:i:s', strtotime(self::HIDE_DONE_AFTER)) : null;
        $tasks = $this->tasks->all($userId, $status, $search, $hideBefore);

        $this->view('tasks/index', [
            'title'      => 'Mani uzdevumi',
            'tasks'      => $tasks,
            'summary'    => Task::summary($tasks),
            'status'     => $status,
            'search'     => $search,
            'hiddenDone' => $hideBefore ? $this->tasks->countDoneBefore($userId, $hideBefore) : 0,
            'heatmap'    => $this->tasks->heatmap($userId),
        ]);
    }

    // GET index.php?r=tasks/create - tukša forma (var padot ?due=YYYY-MM-DD no kalendāra)
    public function create(): void
    {
        $userId = $this->requireLogin();
        $due = is_string($_GET['due'] ?? null) && Task::validDate($_GET['due']) ? $_GET['due'] : '';
        $task = [
            'title' => '', 'description' => '', 'due_date' => $due, 'status' => 'jauns',
            'priority' => 'vidēja', 'repeat_rule' => '', 'category_id' => '',
        ];
        $this->showForm($userId, $task, null, []);
    }

    // CREATE: POST index.php?r=tasks/create
    public function store(): void
    {
        $userId = $this->requireLogin();
        check_csrf();

        $task = $this->formData();
        $errors = $this->tasks->validate($task, $userId);
        if ($errors) {
            $this->showForm($userId, $task, null, $errors);
            return;
        }

        $id = $this->tasks->create($userId, $task);
        $this->activity->event($id, $userId, 'izveidoja uzdevumu');
        write_log("Izveidots uzdevums #$id: {$task['title']}");
        flash('Uzdevums izveidots.');
        $this->redirect('tasks/edit&id=' . $id);
    }

    // GET index.php?r=tasks/edit&id=5
    // Īpašnieks redz rediģēšanas formu, lietotājs ar kopīgotu piekļuvi - tikai skatu.
    public function edit(): void
    {
        $userId = $this->requireLogin();
        $id = (int)($_GET['id'] ?? 0);

        $task = $this->tasks->findAccessible($id, $userId);
        if (!$task) {
            $this->fail(404, 'Uzdevums nav atrasts.');
        }

        if ((int)$task['user_id'] === $userId) {
            $this->showForm($userId, $task, $id, []);
            return;
        }
        $this->view('tasks/form', [
            'title'      => $task['title'],
            'task'       => $task,
            'id'         => $id,
            'readonly'   => true,
            'categories' => [],
        ] + $this->related($id));
    }

    // UPDATE: POST index.php?r=tasks/edit&id=5 (tikai īpašnieks)
    public function update(): void
    {
        $userId = $this->requireLogin();
        check_csrf();

        $id = (int)($_GET['id'] ?? 0);
        $before = $this->tasks->find($id, $userId);
        if (!$before) {
            $this->fail(404, 'Uzdevums nav atrasts.');
        }

        $task = $this->formData();
        $errors = $this->tasks->validate($task, $userId);
        if ($errors) {
            $this->showForm($userId, $task, $id, $errors);
            return;
        }

        // Saglabāšana un (ja vajag) nākamā atkārtojuma izveide notiek kopā - vai nu abi, vai neviens
        $db = Database::get();
        $db->beginTransaction();
        $this->tasks->update($id, $userId, $task);
        if ($before['status'] !== $task['status']) {
            $this->afterStatusChange($id, $before['status'], $task['status'], $userId);
        } else {
            $this->activity->event($id, $userId, 'rediģēja uzdevumu');
        }
        $db->commit();

        write_log("Rediģēts uzdevums #$id: {$task['title']}");
        flash('Uzdevums saglabāts.');

        // No saraksta (ātrā atzīmēšana) atgriežamies sarakstā, no formas - formā
        $this->redirect($this->input('back') === 'list' ? 'tasks' : 'tasks/edit&id=' . $id);
    }

    // POST index.php?r=tasks/status - tikai statusa maiņa (Kanban dēlis). Atbild ar JSON, ja sūta JS.
    public function changeStatus(): void
    {
        $userId = $this->requireLogin();
        check_csrf();

        $id = (int)$this->input('id');
        $status = $this->input('status');
        $before = $this->tasks->find($id, $userId);

        if (!$before || !in_array($status, STATUSES, true)) {
            $this->fail(400, 'Nederīgs pieprasījums.');
        }

        $spawned = null;
        if ($before['status'] !== $status) {
            $db = Database::get();
            $db->beginTransaction();
            $this->tasks->setStatus($id, $userId, $status);
            $spawned = $this->afterStatusChange($id, $before['status'], $status, $userId);
            $db->commit();
            write_log("Uzdevumam #$id mainīts statuss: {$before['status']} -> $status");
        }

        if ($this->isAjax()) {
            unset($_SESSION['celebrate']); // JS svinēs uzreiz
            $this->json(['ok' => true, 'status' => $status, 'spawned' => $spawned]);
        }
        $this->redirect('board');
    }

    // POST index.php?r=tasks/due - tikai termiņa maiņa (kalendārs). Atbild ar JSON, ja sūta JS.
    public function changeDue(): void
    {
        $userId = $this->requireLogin();
        check_csrf();

        $id  = (int)$this->input('id');
        $due = $this->input('due_date');
        $before = $this->tasks->find($id, $userId);

        if (!$before || ($due !== '' && !Task::validDate($due))) {
            $this->fail(400, 'Nederīgs pieprasījums.');
        }

        if (($before['due_date'] ?? '') !== $due) {
            $this->tasks->setDue($id, $userId, $due !== '' ? $due : null);
            $this->activity->event($id, $userId, 'pārcēla termiņu uz ' . ($due !== '' ? lv_date($due) : '"bez termiņa"'));
            write_log("Uzdevumam #$id mainīts termiņš uz " . ($due ?: 'nav'));
        }

        if ($this->isAjax()) {
            $this->json(['ok' => true, 'due_date' => $due]);
        }
        $this->redirect('calendar&month=' . substr($due ?: date('Y-m-d'), 0, 7));
    }

    // DELETE: POST index.php?r=tasks/delete (tikai īpašnieks)
    public function delete(): void
    {
        $userId = $this->requireLogin();
        check_csrf();

        $id = (int)$this->input('id');
        if ($this->tasks->isOwner($id, $userId)) {
            (new TaskFile())->deleteFilesOfTask($id);
            $this->tasks->delete($id, $userId);
            write_log("Dzēsts uzdevums #$id");
            flash('Uzdevums izdzēsts.');
        } else {
            flash('Uzdevums nav atrasts.', 'error');
        }
        $this->redirect('tasks');
    }

    // --- Palīgmetodes ---

    // Pēc statusa maiņas: aktivitāte, atkārtošanās, svinības.
    // Nākamo atkārtojumu veidojam no jau saglabātajiem datiem (ja vienlaikus mainīts arī nosaukums u.c.)
    private function afterStatusChange(int $id, string $from, string $to, int $userId): ?int
    {
        $this->activity->event($id, $userId, "mainīja statusu: $from → $to");
        if ($to !== 'pabeigts') {
            return null;
        }
        $_SESSION['celebrate'] = true;

        $nextId = $this->tasks->spawnNext($this->tasks->find($id, $userId));
        if ($nextId) {
            $this->activity->event($id, $userId, 'izveidoja nākamo atkārtojumu');
            $this->activity->event($nextId, $userId, 'izveidots automātiski (atkārtots uzdevums)');
            write_log("Atkārtotam uzdevumam #$id izveidots nākamais #$nextId");
        }
        return $nextId;
    }

    private function formData(): array
    {
        return [
            'title'       => $this->input('title'),
            'description' => $this->input('description', true, true),
            'due_date'    => $this->input('due_date'),
            'status'      => $this->input('status'),
            'priority'    => $this->input('priority'),
            'repeat_rule' => $this->input('repeat_rule'),
            'category_id' => $this->input('category_id'),
        ];
    }

    // Soļi, saites, faili un aktivitāte uzdevuma lapai
    private function related(int $id): array
    {
        return [
            'subtasks' => (new Subtask())->forTask($id),
            'links'    => (new TaskLink())->forTask($id),
            'files'    => (new TaskFile())->forTask($id),
            'activity' => $this->activity->forTask($id),
        ];
    }

    private function showForm(int $userId, array $task, ?int $id, array $errors): void
    {
        $data = [
            'title'      => $id ? 'Rediģēt uzdevumu' : 'Jauns uzdevums',
            'task'       => $task,
            'id'         => $id,
            'errors'     => $errors,
            'categories' => (new Category())->all($userId),
        ];
        // Soļi, saites, faili, aktivitāte un kopīgošana ir pieejami tikai jau saglabātam uzdevumam
        if ($id) {
            $data += $this->related($id);
            $data['shares'] = (new TaskShare())->forTask($id);
        }
        $this->view('tasks/form', $data);
    }
}
