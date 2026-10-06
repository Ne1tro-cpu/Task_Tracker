<?php
// Kategoriju CRUD (otrā saistītā tabula)
class CategoryController extends Controller
{
    private Category $categories;

    public function __construct()
    {
        $this->categories = new Category();
    }

    // READ: GET index.php?r=categories
    public function index(array $errors = []): void
    {
        $userId = $this->requireLogin();
        $this->view('categories/index', [
            'title'      => 'Kategorijas',
            'categories' => $this->categories->allWithCounts($userId),
            'errors'     => $errors,
        ]);
    }

    // CREATE: POST index.php?r=categories/create
    public function store(): void
    {
        $userId = $this->requireLogin();
        check_csrf();

        $name = $this->input('name');
        $errors = $this->categories->validate($name, $userId);
        if ($errors) {
            $this->index($errors);
            return;
        }

        $this->categories->create($userId, $name);
        write_log("Izveidota kategorija: $name");
        flash('Kategorija pievienota.');
        $this->redirect('categories');
    }

    // UPDATE: POST index.php?r=categories/edit
    public function update(): void
    {
        $userId = $this->requireLogin();
        check_csrf();

        $id = (int)$this->input('id');
        $name = $this->input('name');
        $errors = $this->categories->validate($name, $userId, $id);
        if ($errors) {
            $this->index($errors);
            return;
        }

        if ($this->categories->update($id, $userId, $name)) {
            write_log("Pārdēvēta kategorija #$id uz: $name");
            flash('Kategorija pārdēvēta.');
        } elseif (!$this->categories->belongsTo($id, $userId)) {
            flash('Kategorija nav atrasta.', 'error');
        }
        $this->redirect('categories');
    }

    // DELETE: POST index.php?r=categories/delete
    public function delete(): void
    {
        $userId = $this->requireLogin();
        check_csrf();

        $id = (int)$this->input('id');
        if ($this->categories->delete($id, $userId)) {
            write_log("Dzēsta kategorija #$id");
            flash('Kategorija izdzēsta.');
        } else {
            flash('Kategorija nav atrasta.', 'error');
        }
        $this->redirect('categories');
    }
}
