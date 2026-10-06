<?php
// Datubāzes sagatavošana pārlūkā: izveide (ar demo datiem pēc izvēles) vai atjaunināšana.
// Pieejama tikai tad, kad datubāze nav gatava - pēc tam šī lapa pāradresē uz sākumu.
class SetupController extends Controller
{
    private Setup $setup;

    public function __construct()
    {
        $this->setup = new Setup();
    }

    // GET index.php?r=setup
    public function index(): void
    {
        $status = $this->setup->status();   // ja MySQL nav palaists - kļūdas lapa ar skaidrojumu
        if ($status === 'ok') {
            $this->redirect(empty($_SESSION['user_id']) ? 'login' : 'tasks');
        }
        $this->view('setup/index', [
            'title'   => 'Datubāzes sagatavošana',
            'status'  => $status,
            'pending' => $this->setup->pendingMigrations(),
            'dbName'  => Database::name(),
        ]);
    }

    // POST index.php?r=setup/install
    public function install(): void
    {
        check_csrf();
        if (!in_array($this->setup->status(), ['database', 'tables'], true)) {
            $this->redirect('setup');
        }

        $this->setup->install();
        $accounts = $this->input('demo') === '1' ? $this->setup->seedDemo() : [];
        write_log('Datubāze izveidota' . ($accounts ? ' (ar demo datiem)' : ''));

        if ($accounts) {
            $list = implode(', ', array_map(fn($u, $p) => "$u / $p", array_keys($accounts), $accounts));
            flash("Datubāze izveidota ar demo datiem. Piesakies: $list", 'info');
            $this->redirect('login');
        }
        flash('Datubāze izveidota! Tagad izveido savu kontu.');
        $this->redirect('register');
    }

    // POST index.php?r=setup/upgrade
    public function upgrade(): void
    {
        check_csrf();
        if ($this->setup->status() !== 'upgrade') {
            $this->redirect('setup');
        }

        $applied = $this->setup->upgrade();
        write_log('Datubāze atjaunināta: ' . implode(', ', $applied));
        flash('Datubāze atjaunināta (' . implode(', ', $applied) . ').');
        $this->redirect(empty($_SESSION['user_id']) ? 'login' : 'tasks');
    }
}
