<?php
// Uzdevuma soļi: pievienot, atzīmēt, dzēst.
// Atļauts īpašniekam un lietotājiem, ar kuriem uzdevums kopīgots.
class SubtaskController extends Controller
{
    private Subtask $subtasks;
    private Activity $activity;

    public function __construct()
    {
        $this->subtasks = new Subtask();
        $this->activity = new Activity();
    }

    // POST index.php?r=subtasks/create
    public function store(): void
    {
        $userId = $this->requireLogin();
        check_csrf();

        $taskId = (int)$this->input('task_id');
        $title  = $this->input('title');
        $this->requireTaskAccess($taskId, $userId);

        if ($errors = $this->subtasks->validate($title)) {
            flash($errors[0], 'error');
        } else {
            $this->subtasks->create($taskId, $title);
            $this->activity->event($taskId, $userId, "pievienoja soli \"$title\"");
            write_log("Pievienots solis uzdevumam #$taskId: $title");
        }
        $this->redirect('tasks/edit&id=' . $taskId);
    }

    // POST index.php?r=subtasks/toggle
    public function toggle(): void
    {
        $userId = $this->requireLogin();
        check_csrf();

        $step = $this->findStep($userId);
        $this->subtasks->toggle((int)$step['id']);
        $this->activity->event((int)$step['task_id'], $userId,
            ($step['is_done'] ? 'atcēla soli' : 'izpildīja soli') . " \"{$step['title']}\"");
        write_log("Pārslēgts solis #{$step['id']} (uzdevums #{$step['task_id']})");
        $this->redirect('tasks/edit&id=' . $step['task_id']);
    }

    // POST index.php?r=subtasks/delete
    public function delete(): void
    {
        $userId = $this->requireLogin();
        check_csrf();

        $step = $this->findStep($userId);
        $this->subtasks->delete((int)$step['id']);
        $this->activity->event((int)$step['task_id'], $userId, "izdzēsa soli \"{$step['title']}\"");
        write_log("Dzēsts solis #{$step['id']} (uzdevums #{$step['task_id']})");
        $this->redirect('tasks/edit&id=' . $step['task_id']);
    }

    // Atrod soli un pārbauda, vai lietotājam ir piekļuve tā uzdevumam
    private function findStep(int $userId): array
    {
        $step = $this->subtasks->find((int)$this->input('id'));
        if (!$step) {
            $this->fail(404, 'Solis nav atrasts.');
        }
        $this->requireTaskAccess((int)$step['task_id'], $userId);
        return $step;
    }
}
