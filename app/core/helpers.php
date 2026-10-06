<?php
// Palīgfunkcijas: XSS aizsardzība, CSRF, žurnāls, paziņojumi, atbildes, datumi

// HTML izvades drošība (pret XSS) - izmanto skatos
function e($text): string
{
    return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
}

/* ---------- Pieprasījuma veids ---------- */

// Vai pieprasījumu sūtīja JavaScript? Tad atbildam ar JSON, nevis HTML lapu.
//   X-Partial: 1              - forma nosūtīta fonā (lapa tiek atjaunota bez pārlādes)
//   X-Requested-With: fetch   - dēlis un kalendārs
function is_partial_request(): bool
{
    return ($_SERVER['HTTP_X_PARTIAL'] ?? '') === '1'
        || ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';
}

function send_json(array $data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Kļūdas lapa (404, 403, 500 ...) ar parasto izkārtojumu; JavaScript pieprasījumiem - JSON
function render_error(int $code, string $heading, string $message = ''): never
{
    if (is_partial_request()) {
        send_json(['ok' => false, 'error' => $message !== '' ? $message : $heading], $code);
    }
    http_response_code($code);
    $title = $heading;
    require __DIR__ . '/../views/layout/header.php';
    require __DIR__ . '/../views/error.php';
    require __DIR__ . '/../views/layout/footer.php';
    exit;
}

/* ---------- CSRF aizsardzība ---------- */
// Katrai sesijai nejaušs kods, kas ielikts katrā formā.
// POST pieprasījumā pārbaudām, vai atsūtītais kods sakrīt ar sesijā saglabāto.
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

function check_csrf(): void
{
    $sent = $_POST['csrf_token'] ?? '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        write_log('CSRF pārbaude neizdevās');
        render_error(403, 'Nederīgs pieprasījums', 'Sesija beigusies vai forma ir novecojusi. Pārlādē lapu un mēģini vēlreiz.');
    }
}

/* ---------- Privātas mapes (žurnāls, pielikumi) ---------- */
// Izveido mapi, ja tās nav, un ieliek tajā .htaccess, kas aizliedz to atvērt pārlūkā.
// Tā mape paliek aizsargāta arī tad, ja tā bija izdzēsta un lietotne to izveido no jauna.
function ensure_private_dir(string $dir): void
{
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    if (!is_file("$dir/.htaccess")) {
        @file_put_contents("$dir/.htaccess", "# Block browser access to this folder\r\nRequire all denied\r\n");
    }
}

/* ---------- Žurnālfails: laiks | IP | lietotājs | darbība ---------- */
function write_log(string $message): void
{
    ensure_private_dir(dirname(LOG_FILE));
    $user = $_SESSION['username'] ?? 'viesis';
    $ip   = $_SERVER['REMOTE_ADDR'] ?? '-';
    // Rindu pārnesumus un atdalītāju "|" aizstājam, lai lietotāja ievade nevarētu "viltot" žurnāla ierakstus
    $message = str_replace(["\r", "\n", '|'], [' ', ' ', '/'], $message);
    $line = date('Y-m-d H:i:s') . " | $ip | $user | $message" . PHP_EOL;
    // @ - ja žurnālu nevar ierakstīt, lietotne tik un tā strādā tālāk
    @file_put_contents(LOG_FILE, $line, FILE_APPEND | LOCK_EX);
}

/* ---------- Vienreizēji paziņojumi pēc pāradresācijas ---------- */
// $type: 'ok' (izdevās), 'error' (kļūda) vai 'info' (svarīga ziņa, kas pati nepazūd)
function flash(string $msg, string $type = 'ok'): void
{
    $_SESSION['flash'] = ['msg' => $msg, 'type' => $type];
}

function get_flash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return is_array($flash) ? $flash : null;
}

/* ---------- Datumi latviski ---------- */
const LV_MONTHS   = ['janvāris', 'februāris', 'marts', 'aprīlis', 'maijs', 'jūnijs', 'jūlijs',
                     'augusts', 'septembris', 'oktobris', 'novembris', 'decembris'];
const LV_WEEKDAYS = ['svētdiena', 'pirmdiena', 'otrdiena', 'trešdiena', 'ceturtdiena', 'piektdiena', 'sestdiena'];

// lv_month(10) -> "oktobris", lv_month(10, true) -> "okt"
function lv_month(int $month, bool $short = false): string
{
    $name = LV_MONTHS[$month - 1] ?? '';
    return $short ? mb_substr($name, 0, 3) : $name;
}

// lv_weekday(1) -> "pirmdiena" (0 = svētdiena, kā date('w'))
function lv_weekday(int $weekday): string
{
    return LV_WEEKDAYS[$weekday] ?? '';
}

// "5. oktobris"
function lv_date(string $date): string
{
    $d = new DateTimeImmutable($date);
    return $d->format('j') . '. ' . lv_month((int)$d->format('n'));
}

// Cik dienas līdz termiņam (negatīvs = nokavēts, null = termiņa nav)
function days_left(?string $due): ?int
{
    if (!$due) {
        return null;
    }
    return (int)(new DateTimeImmutable('today'))->diff(new DateTimeImmutable($due))->format('%r%a');
}

// Termiņš vārdos: "šodien", "rīt", "pēc 3 d.", "nokavēts 2 d."
function due_label(?int $days): string
{
    return match (true) {
        $days === null => '',
        $days < -1     => 'nokavēts ' . -$days . ' d.',
        $days === -1   => 'vakar',
        $days === 0    => 'šodien',
        $days === 1    => 'rīt',
        default        => 'pēc ' . $days . ' d.',
    };
}

/* ---------- CSS klases statusiem un prioritātēm ---------- */
function status_class(string $status): string
{
    return ['jauns' => 'new', 'procesā' => 'doing', 'pabeigts' => 'done'][$status] ?? 'new';
}

function prio_class(?string $priority): string
{
    return ['zema' => 'low', 'vidēja' => 'mid', 'augsta' => 'high'][$priority ?? ''] ?? 'mid';
}

// Pirmais burts avatāram
function initial(string $name): string
{
    return mb_strtoupper(mb_substr($name, 0, 1));
}
