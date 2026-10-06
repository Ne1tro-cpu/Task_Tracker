<?php
// Saites pie uzdevuma: pievienot, noņemt.
// Atļauts īpašniekam un lietotājiem, ar kuriem uzdevums kopīgots.
class LinkController extends Controller
{
    private TaskLink $links;
    private Activity $activity;

    public function __construct()
    {
        $this->links = new TaskLink();
        $this->activity = new Activity();
    }

    // POST index.php?r=links/create
    public function store(): void
    {
        $userId = $this->requireLogin();
        check_csrf();

        $taskId = (int)$this->input('task_id');
        $url    = $this->input('url');
        $title  = $this->input('title');
        $this->requireTaskAccess($taskId, $userId);

        if ($errors = $this->links->validate($url, $title)) {
            flash($errors[0], 'error');
        } else {
            $this->links->create($taskId, $url, $title !== '' ? $title : null);
            $this->activity->event($taskId, $userId, 'pievienoja saiti ' . ($title !== '' ? "\"$title\"" : parse_url($url, PHP_URL_HOST)));
            write_log("Pievienota saite uzdevumam #$taskId: $url");
            flash('Saite pievienota.');
        }
        $this->redirect('tasks/edit&id=' . $taskId);
    }

    // POST index.php?r=links/delete
    public function delete(): void
    {
        $userId = $this->requireLogin();
        check_csrf();

        $link = $this->links->find((int)$this->input('id'));
        if (!$link) {
            $this->fail(404, 'Saite nav atrasta.');
        }
        $this->requireTaskAccess((int)$link['task_id'], $userId);

        $this->links->delete((int)$link['id']);
        $this->activity->event((int)$link['task_id'], $userId, 'noņēma saiti ' . ($link['title'] ? "\"{$link['title']}\"" : parse_url($link['url'], PHP_URL_HOST)));
        write_log("Noņemta saite #{$link['id']} (uzdevums #{$link['task_id']})");
        flash('Saite noņemta.');
        $this->redirect('tasks/edit&id=' . $link['task_id']);
    }
}
