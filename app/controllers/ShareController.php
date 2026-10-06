<?php
// Uzdevuma kopīgošana ar citiem lietotājiem. Atļauts tikai īpašniekam.
class ShareController extends Controller
{
    private TaskShare $shares;
    private Activity $activity;

    public function __construct()
    {
        $this->shares = new TaskShare();
        $this->activity = new Activity();
    }

    // POST index.php?r=shares/create
    public function store(): void
    {
        $userId = $this->requireLogin();
        check_csrf();

        $taskId   = (int)$this->input('task_id');
        $username = $this->input('username');
        $this->requireTaskOwner($taskId, $userId);

        $other = (new User())->findByUsername($username);

        if (!$other) {
            flash('Lietotājs ar šādu vārdu nav atrasts.', 'error');
        } elseif ((int)$other['id'] === $userId) {
            flash('Uzdevums jau ir tavs.', 'error');
        } elseif ($this->shares->exists($taskId, (int)$other['id'])) {
            flash("Uzdevums jau ir kopīgots ar {$other['username']}.", 'error');
        } else {
            $this->shares->create($taskId, (int)$other['id']);
            $this->activity->event($taskId, $userId, "kopīgoja ar {$other['username']}");
            write_log("Uzdevums #$taskId kopīgots ar {$other['username']}");
            flash("Kopīgots ar {$other['username']}.");
        }
        $this->redirect('tasks/edit&id=' . $taskId);
    }

    // POST index.php?r=shares/delete
    public function delete(): void
    {
        $userId = $this->requireLogin();
        check_csrf();

        $share = $this->shares->find((int)$this->input('id'));
        if (!$share) {
            $this->fail(404, 'Kopīgošana nav atrasta.');
        }
        $this->requireTaskOwner((int)$share['task_id'], $userId);

        $this->shares->delete((int)$share['id']);
        $this->activity->event((int)$share['task_id'], $userId, "pārtrauca kopīgošanu ar {$share['username']}");
        write_log("Pārtraukta kopīgošana #{$share['id']} (uzdevums #{$share['task_id']})");
        flash('Kopīgošana pārtraukta.');
        $this->redirect('tasks/edit&id=' . $share['task_id']);
    }
}
