<?php
// Reģistrācija, pieteikšanās, atteikšanās
class AuthController extends Controller
{
    // GET index.php?r=register
    public function showRegister(): void
    {
        $this->requireGuest();
        $this->requireDatabase();
        $this->view('auth/register', ['title' => 'Reģistrācija', 'errors' => [], 'username' => '', 'email' => '']);
    }

    // POST index.php?r=register
    public function register(): void
    {
        $this->requireGuest();
        check_csrf();

        $username  = $this->input('username');
        $email     = $this->input('email');
        $password  = $this->input('password', false);
        $password2 = $this->input('password2', false);

        $user = new User();
        $errors = $user->validate($username, $email, $password, $password2);

        if ($errors) {
            $this->view('auth/register', compact('errors', 'username', 'email') + ['title' => 'Reģistrācija']);
            return;
        }

        $user->create($username, $email, $password);
        write_log("Reģistrēts jauns lietotājs: $username");
        flash('Reģistrācija veiksmīga! Tagad vari pieteikties.');
        $this->redirect('login');
    }

    // GET index.php?r=login
    public function showLogin(): void
    {
        $this->requireGuest();
        $this->requireDatabase();
        $this->view('auth/login', ['title' => 'Pieteikšanās', 'errors' => [], 'username' => '']);
    }

    // POST index.php?r=login
    public function login(): void
    {
        $this->requireGuest();
        check_csrf();

        $username = $this->input('username');
        $password = $this->input('password', false);
        $ip       = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $attempts = new LoginAttempt();
        $errors   = [];

        if ($attempts->isBlocked($ip)) {
            write_log("Pieteikšanās bloķēta (paroļu minēšana), lietotājvārds: $username");
            $errors[] = 'Pārāk daudz neveiksmīgu mēģinājumu. Mēģini vēlreiz pēc ' . LOCK_MINUTES . ' minūtēm.';
        } elseif ($username === '' || $password === '') {
            $errors[] = 'Aizpildi visus laukus.';
        } elseif ($user = (new User())->authenticate($username, $password)) {
            session_regenerate_id(true);       // jauns sesijas ID (pret sesijas fiksāciju)
            unset($_SESSION['csrf_token']);    // un jauns CSRF kods
            $_SESSION['user_id']  = (int)$user['id'];
            $_SESSION['username'] = $user['username'];

            $attempts->clear($ip);
            write_log('Pieteicās sistēmā');
            $this->redirect('tasks');
        } else {
            $attempts->add($ip, $username);
            write_log("Neveiksmīga pieteikšanās, lietotājvārds: $username");
            $errors[] = 'Nepareizs lietotājvārds vai parole.';
        }

        $this->view('auth/login', compact('errors', 'username') + ['title' => 'Pieteikšanās']);
    }

    // POST index.php?r=logout
    public function logout(): void
    {
        check_csrf();
        write_log('Atteicās no sistēmas');
        $this->endSession();
        flash('Tu esi izgājis no sistēmas.');
        $this->redirect('login');
    }
}
