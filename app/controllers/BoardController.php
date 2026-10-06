<?php
// Kanban dēlis: uzdevumi trīs kolonnās pēc statusa.
// Pārvilkšana sūta POST uz tasks/status (TaskController::changeStatus).
class BoardController extends Controller
{
    // Kolonnā "Pabeigts" rādām tikai nesen pabeigtos, lai dēlis nepārplūst
    private const DONE_DAYS = 14;
    private const DONE_MAX  = 20;

    // GET index.php?r=board
    public function index(): void
    {
        $userId = $this->requireLogin();
        $tasks = new Task();
        $since = date('Y-m-d H:i:s', strtotime('-' . self::DONE_DAYS . ' days'));

        // Uz dēļa tikai paša uzdevumi - statusu mainīt drīkst tikai īpašnieks
        $columns = array_fill_keys(STATUSES, []);
        foreach ($tasks->all($userId, '', '', $since) as $t) {
            if (empty($t['owner'])) {
                $columns[$t['status']][] = $t;
            }
        }

        // Pabeigtie: jaunākie augšā, ne vairāk kā DONE_MAX
        usort($columns['pabeigts'], fn($a, $b) => strcmp($b['completed_at'] ?? '', $a['completed_at'] ?? ''));
        $hiddenDone = $tasks->countDoneBefore($userId, $since, true) + max(0, count($columns['pabeigts']) - self::DONE_MAX);
        $columns['pabeigts'] = array_slice($columns['pabeigts'], 0, self::DONE_MAX);

        $this->view('board/index', [
            'title'      => 'Dēlis',
            'columns'    => $columns,
            'hiddenDone' => $hiddenDone,
        ]);
    }
}
