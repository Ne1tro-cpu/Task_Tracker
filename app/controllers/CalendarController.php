<?php
// Mēneša kalendārs ar uzdevumiem to termiņu dienās.
// Pārvilkšana uz citu dienu sūta POST uz tasks/due (TaskController::changeDue).
class CalendarController extends Controller
{
    // GET index.php?r=calendar[&month=YYYY-MM]
    public function index(): void
    {
        $userId = $this->requireLogin();

        $month = is_string($_GET['month'] ?? null) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $_GET['month'])
            ? $_GET['month'] : date('Y-m');
        $first = new DateTimeImmutable($month . '-01');

        // Režģis sākas ar pirmdienu pirms (vai vienādu ar) mēneša 1. datumu un beidzas ar svētdienu
        $gridStart = $first->modify('monday this week');
        $gridEnd   = $first->modify('last day of this month')->modify('sunday this week');

        $byDay = [];
        foreach ((new Task())->between($userId, $gridStart->format('Y-m-d'), $gridEnd->format('Y-m-d')) as $t) {
            $byDay[$t['due_date']][] = $t;
        }

        $this->view('calendar/index', [
            'title'     => 'Kalendārs',
            'first'     => $first,
            'gridStart' => $gridStart,
            'gridEnd'   => $gridEnd,
            'byDay'     => $byDay,
            'prev'      => $first->modify('-1 month')->format('Y-m'),
            'next'      => $first->modify('+1 month')->format('Y-m'),
        ]);
    }
}
