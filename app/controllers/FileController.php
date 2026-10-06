<?php
// Pielikumi: augšupielāde, lejupielāde, dzēšana.
// Atļauts īpašniekam un lietotājiem, ar kuriem uzdevums kopīgots.
class FileController extends Controller
{
    private TaskFile $files;

    public function __construct()
    {
        $this->files = new TaskFile();
    }

    // POST index.php?r=files/upload (forma ar enctype="multipart/form-data")
    public function upload(): void
    {
        $userId = $this->requireLogin();

        // Ja fails pārsniedz post_max_size, PHP izmet visus POST datus (arī CSRF kodu un task_id).
        // Tāpēc uzdevuma nr. ir arī adresē (&task=N), lai varētu atgriezties pie tā.
        if (empty($_POST) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
            flash('Fails ir par lielu (maksimums ' . (UPLOAD_MAX_SIZE / 1024 / 1024) . ' MB).', 'error');
            $this->redirect('tasks/edit&id=' . (int)($_GET['task'] ?? 0));
        }
        check_csrf();

        $taskId = (int)$this->input('task_id');
        $this->requireTaskAccess($taskId, $userId);

        $result = $this->files->store($taskId, $userId, $_FILES['file'] ?? null);
        if ($result['errors']) {
            flash($result['errors'][0], 'error');
        } else {
            (new Activity())->event($taskId, $userId, "pievienoja failu \"{$result['name']}\"");
            write_log("Pievienots fails uzdevumam #$taskId: {$result['name']}");
            flash('Fails pievienots.');
        }
        $this->redirect('tasks/edit&id=' . $taskId);
    }

    // GET index.php?r=files/download&id=N - atdod failu tikai tiem, kam ir piekļuve uzdevumam
    public function download(): void
    {
        $userId = $this->requireLogin();
        $file = $this->findFile((int)($_GET['id'] ?? 0), $userId);
        $path = $this->files->path($file);
        if (!is_file($path)) {
            $this->fail(404, 'Fails nav atrasts.');
        }

        while (ob_get_level() > 0) {
            ob_end_clean();   // failu sūtām tieši, bez bufera
        }

        // Attēlus rāda pārlūkā, pārējos lejupielādē
        $inline = str_starts_with($file['mime'], 'image/') && !isset($_GET['download']);
        header('Content-Type: ' . $file['mime']);
        header('Content-Length: ' . filesize($path));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, max-age=0');
        header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment')
            . '; filename="' . str_replace(['"', '\\'], '', $file['original_name']) . '"'
            . "; filename*=UTF-8''" . rawurlencode($file['original_name']));
        readfile($path);
        exit;
    }

    // POST index.php?r=files/delete
    public function delete(): void
    {
        $userId = $this->requireLogin();
        check_csrf();

        $file = $this->findFile((int)$this->input('id'), $userId);
        $this->files->delete($file);
        (new Activity())->event((int)$file['task_id'], $userId, "izdzēsa failu \"{$file['original_name']}\"");
        write_log("Dzēsts fails #{$file['id']} (uzdevums #{$file['task_id']})");
        flash('Fails izdzēsts.');
        $this->redirect('tasks/edit&id=' . $file['task_id']);
    }

    private function findFile(int $id, int $userId): array
    {
        $file = $this->files->find($id);
        if (!$file) {
            $this->fail(404, 'Fails nav atrasts.');
        }
        $this->requireTaskAccess((int)$file['task_id'], $userId);
        return $file;
    }
}
