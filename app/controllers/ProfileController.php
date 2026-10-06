<?php
// Profils: statistika, paroles maiņa, konta dzēšana un izskata iestatījumi.
// Kopā ar reģistrāciju tas dod pilnu CRUD arī tabulai `users`.
class ProfileController extends Controller
{
    private User $users;

    public function __construct()
    {
        $this->users = new User();
    }

    // READ: GET index.php?r=profile
    public function index(array $errors = []): void
    {
        $userId = $this->requireLogin();
        $this->view('profile/index', [
            'title'   => 'Profils',
            'user'    => $this->users->find($userId),
            'stats'   => $this->users->stats($userId),
            'heatmap' => (new Task())->heatmap($userId),
            'errors'  => $errors,
        ]);
    }

    // UPDATE: POST index.php?r=profile/password
    public function changePassword(): void
    {
        $userId = $this->requireLogin();
        check_csrf();

        $current   = $this->input('current_password', false);
        $password  = $this->input('password', false);
        $password2 = $this->input('password2', false);

        $errors = $this->users->checkPassword($userId, $current)
            ? $this->users->validatePassword($password, $password2)
            : ['Pašreizējā parole nav pareiza.'];
        if (!$errors && $password === $current) {
            $errors[] = 'Jaunajai parolei jāatšķiras no pašreizējās.';
        }
        if ($errors) {
            $this->index($errors);
            return;
        }

        $this->users->updatePassword($userId, $password);
        session_regenerate_id(true);   // vecais sesijas ID vairs nav derīgs
        write_log('Nomainīja paroli');
        flash('Parole nomainīta.');
        $this->redirect('profile');
    }

    // DELETE: POST index.php?r=profile/delete - ar paroles apstiprinājumu
    public function delete(): void
    {
        $userId = $this->requireLogin();
        check_csrf();

        if (!$this->users->checkPassword($userId, $this->input('password', false))) {
            $this->index(['Konts netika dzēsts: parole nav pareiza.']);
            return;
        }

        write_log('Izdzēsa savu kontu');
        $this->users->delete($userId);
        $this->endSession();
        flash('Konts un visi tā dati ir izdzēsti.');
        $this->redirect('login');
    }
}
