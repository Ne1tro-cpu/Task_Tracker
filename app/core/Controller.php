<?php
// Bāzes kontrolieris - kopīgās metodes visiem kontrolieriem
abstract class Controller
{
    // Vai pieteikušā lietotāja konts šajā pieprasījumā jau pārbaudīts
    private static bool $userChecked = false;

    // Parāda skatu (view) ar galveni un kājeni.
    // $data masīva atslēgas kļūst par mainīgajiem skatā (extract).
    protected function view(string $name, array $data = []): void
    {
        extract($data);
        require __DIR__ . '/../views/layout/header.php';
        require __DIR__ . "/../views/$name.php";
        require __DIR__ . '/../views/layout/footer.php';
    }

    // Post/Redirect/Get - pēc POST pāradresējam, lai pārlādējot forma netiktu iesniegta atkārtoti.
    // Ja formu nosūtīja JavaScript (galvene X-Partial), atbildam ar JSON adresi, nevis pāradresāciju -
    // tad lapa tiek atjaunota bez pārlādes, bet flash ziņojums paliek sesijā, līdz to nolasa.
    protected function redirect(string $route): never
    {
        if (self::isPartial()) {
            send_json(['ok' => true, 'redirect' => "index.php?r=$route"]);
        }
        header("Location: index.php?r=$route");
        exit;
    }

    public static function isPartial(): bool
    {
        return ($_SERVER['HTTP_X_PARTIAL'] ?? '') === '1';
    }

    // Vai pieprasījumu sūtīja dēlis vai kalendārs (fetch)? Tad atbildam ar JSON datiem.
    protected function isAjax(): bool
    {
        return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';
    }

    protected function json(array $data, int $code = 200): never
    {
        send_json($data, $code);
    }

    // Kļūda ar pareizu atbildes veidu: JSON JavaScript pieprasījumiem, kļūdas lapa pārējiem
    protected function fail(int $code, string $message): never
    {
        $heading = [400 => 'Nederīgs pieprasījums', 403 => 'Nav atļauts', 404 => 'Nav atrasts'][$code] ?? 'Kļūda';
        render_error($code, $heading, $message);
    }

    // Nolasa POST lauku kā tekstu (ja atsūtīts masīvs vai nekas - tukšs teksts).
    // Izmet vadības simbolus un nederīgu UTF-8; vienrindas laukos rindu pārnesumus aizstāj ar atstarpi.
    protected function input(string $key, bool $trim = true, bool $multiline = false): string
    {
        $value = $_POST[$key] ?? '';
        if (!is_string($value)) {
            return '';
        }
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';
        if (!$multiline) {
            $value = str_replace(["\r\n", "\r", "\n"], ' ', $value);
        }
        return $trim ? trim($value) : $value;
    }

    // Piekļuve tikai pieteikušamies lietotājiem.
    // Pārbaudām arī, vai konts vēl eksistē (to varēja izdzēst citā pārlūkā).
    protected function requireLogin(): int
    {
        if (empty($_SESSION['user_id'])) {
            $this->redirect('login');
        }
        $userId = (int)$_SESSION['user_id'];

        if (!self::$userChecked) {
            if (!(new User())->find($userId)) {
                $this->endSession();
                flash('Konts vairs nav pieejams. Piesakies vēlreiz.', 'error');
                $this->redirect('login');
            }
            self::$userChecked = true;
        }
        return $userId;
    }

    protected function requireGuest(): void
    {
        if (!empty($_SESSION['user_id'])) {
            $this->redirect('tasks');
        }
    }

    // Pieteikšanās un reģistrācijas lapas datubāzi neizmanto, tāpēc pārbaudām to atsevišķi:
    // ja tā vēl nav izveidota vai jāatjaunina - uzreiz uz sagatavošanas lapu
    protected function requireDatabase(): void
    {
        if ((new Setup())->status() !== 'ok') {
            $this->redirect('setup');
        }
    }

    // Uzdevumam drīkst piekļūt īpašnieks un lietotāji, ar kuriem tas kopīgots
    protected function requireTaskAccess(int $taskId, int $userId): void
    {
        if (!(new Task())->canAccess($taskId, $userId)) {
            $this->fail(404, 'Uzdevums nav atrasts.');
        }
    }

    // Uzdevumu mainīt, dzēst un kopīgot drīkst tikai īpašnieks
    protected function requireTaskOwner(int $taskId, int $userId): void
    {
        if (!(new Task())->isOwner($taskId, $userId)) {
            $this->fail(404, 'Uzdevums nav atrasts.');
        }
    }

    // Iznīcina sesiju un tās sīkdatni (atteikšanās, konta dzēšana) un sāk jaunu, tukšu sesiju,
    // lai tajā varētu ielikt paziņojumu nākamajai lapai
    protected function endSession(): void
    {
        $_SESSION = [];
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        session_destroy();
        session_start();
        session_regenerate_id(true);
    }
}
