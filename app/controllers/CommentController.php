<?php
// Komentāri pie uzdevuma. Komentēt var īpašnieks un lietotāji, ar kuriem uzdevums kopīgots.
class CommentController extends Controller
{
    private Activity $activity;

    public function __construct()
    {
        $this->activity = new Activity();
    }

    // POST index.php?r=comments/create
    public function store(): void
    {
        $userId = $this->requireLogin();
        check_csrf();

        $taskId = (int)$this->input('task_id');
        $text   = $this->input('body', true, true);
        $this->requireTaskAccess($taskId, $userId);

        if ($errors = $this->activity->validateComment($text)) {
            flash($errors[0], 'error');
        } else {
            $this->activity->comment($taskId, $userId, $text);
            write_log("Komentārs uzdevumam #$taskId");
        }
        $this->redirect('tasks/edit&id=' . $taskId . '#aktivitate');
    }

    // POST index.php?r=comments/delete - tikai savu komentāru
    public function delete(): void
    {
        $userId = $this->requireLogin();
        check_csrf();

        $row = $this->activity->find((int)$this->input('id'));
        if ($row && $this->activity->deleteComment((int)$row['id'], $userId)) {
            write_log("Dzēsts komentārs #{$row['id']} (uzdevums #{$row['task_id']})");
            flash('Komentārs izdzēsts.');
            $this->redirect('tasks/edit&id=' . $row['task_id'] . '#aktivitate');
        }
        $this->fail(404, 'Komentārs nav atrasts.');
    }
}
