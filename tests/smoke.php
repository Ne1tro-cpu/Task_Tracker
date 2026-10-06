<?php
/**
 * Dūmu tests (smoke test): pārbauda lietotnes galvenās funkcijas caur HTTP, kā īsts lietotājs.
 *
 * Lietotnei jābūt palaistai (piem., XAMPP). Palaišana no termināļa:
 *   C:\xampp\php\php.exe tests\smoke.php http://localhost/uzdevumi
 *   C:\xampp\php\php.exe tests\smoke.php http://localhost/uzdevumi --full
 *
 * --full  papildus pārbauda aizsardzību pret paroļu minēšanu. Uzmanību: pēc tam no šī datora
 *         15 minūtes nevarēs pieteikties neviens lietotājs (tā darbojas aizsardzība).
 *
 * Tests izveido divus pagaidu lietotājus (smoke_a_..., smoke_b_...) un beigās tos izdzēš.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
if (function_exists('sapi_windows_cp_set')) {
    sapi_windows_cp_set(65001);   // UTF-8 burti Windows terminālī
}
if (!extension_loaded('curl')) {
    fwrite(STDERR, "Nepieciešams PHP curl paplašinājums (php.ini: extension=curl).\n");
    exit(2);
}

$args = array_slice($argv, 1);
$full = in_array('--full', $args, true);
$args = array_values(array_filter($args, fn($a) => !str_starts_with($a, '--')));
$base = rtrim($args[0] ?? 'http://localhost/uzdevumi', '/');

/* ---------------------------------------------------------------
   HTTP klients ar sīkdatnēm (katram lietotājam savs)
   --------------------------------------------------------------- */
final class Client
{
    public int $status = 0;
    public array $headers = [];
    public string $body = '';
    public static array $warnings = [];
    private string $jar;
    private ?string $csrf = null;

    public function __construct(private string $base)
    {
        $this->jar = tempnam(sys_get_temp_dir(), 'smoke');
    }

    public function __destruct()
    {
        if (is_file($this->jar)) {
            unlink($this->jar);
        }
    }

    public function get(string $route, array $query = [], array $headers = []): self
    {
        return $this->send('GET', $this->url($route, $query), null, $headers);
    }

    public function post(string $route, array $data = [], array $headers = [], array $query = [], bool $csrf = true): self
    {
        if ($csrf && !array_key_exists('csrf_token', $data)) {
            $data['csrf_token'] = $this->token();
        }
        return $this->send('POST', $this->url($route, $query), $data, $headers);
    }

    // Pieprasījums uz failu, nevis maršrutu (piem., .htaccess pārbaudei)
    public function raw(string $path): self
    {
        return $this->send('GET', $this->base . '/' . ltrim($path, '/'), null, []);
    }

    // CSRF kods no lapas (pēc pieteikšanās/atteikšanās jāielādē no jauna)
    public function token(): string
    {
        if ($this->csrf === null) {
            $this->get('login');
            if ($this->status === 302) {
                $this->get('tasks');
            }
            preg_match('/name="csrf_token" value="([a-f0-9]{64})"/', $this->body, $m)
                || preg_match('/name="csrf-token" content="([a-f0-9]{64})"/', $this->body, $m);
            $this->csrf = $m[1] ?? '';
        }
        return $this->csrf;
    }

    public function forgetToken(): void
    {
        $this->csrf = null;
    }

    public function location(): string
    {
        return $this->headers['location'] ?? '';
    }

    public function json(): ?array
    {
        $data = json_decode($this->body, true);
        return is_array($data) ? $data : null;
    }

    // Seko pāradresācijai un atgriež nākamās lapas saturu (lai nolasītu paziņojumus)
    public function follow(): string
    {
        $loc = $this->location();
        if ($loc === '') {
            return $this->body;
        }
        $url = str_starts_with($loc, 'http') ? $loc : $this->base . '/' . ltrim($loc, '/');
        $this->send('GET', $url, null, []);
        return $this->body;
    }

    private function url(string $route, array $query): string
    {
        return $this->base . '/index.php?' . http_build_query(['r' => $route] + $query);
    }

    private function send(string $method, string $url, ?array $data, array $headers): self
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_COOKIEJAR      => $this->jar,
            CURLOPT_COOKIEFILE     => $this->jar,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTPHEADER     => $headers,
        ]);
        if ($method === 'POST') {
            $hasFile = (bool)array_filter($data ?? [], fn($v) => $v instanceof CURLFile);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $hasFile ? $data : http_build_query($data ?? []));
        }
        $raw = curl_exec($ch);
        if ($raw === false) {
            throw new RuntimeException('Savienojums neizdevās: ' . curl_error($ch) . " ($url)");
        }
        $size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $this->status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $this->body = substr($raw, $size);
        $this->headers = [];
        foreach (explode("\r\n", substr($raw, 0, $size)) as $line) {
            if (str_contains($line, ':')) {
                [$k, $v] = explode(':', $line, 2);
                $k = strtolower(trim($k));
                $this->headers[$k] = isset($this->headers[$k]) && $k === 'set-cookie'
                    ? $this->headers[$k] . "\n" . trim($v) : trim($v);
            }
        }
        curl_close($ch);

        // PHP brīdinājumi lapā nozīmē kļūdu kodā
        if (preg_match('/<b>(Warning|Notice|Deprecated|Fatal error)<\/b>|PHP (Warning|Notice|Deprecated)/', $this->body, $m)) {
            self::$warnings[] = "$method $url: {$m[0]}";
        }
        return $this;
    }
}

/* ---------------------------------------------------------------
   Pārbaužu palīgfunkcijas
   --------------------------------------------------------------- */
$stats = ['ok' => 0, 'fail' => 0, 'skip' => 0];
$failures = [];

function section(string $title): void
{
    echo "\n\033[1m$title\033[0m\n";
}

function check(string $name, bool $condition, string $detail = ''): bool
{
    global $stats, $failures;
    if ($condition) {
        $stats['ok']++;
        echo "  \033[32mOK\033[0m    $name\n";
    } else {
        $stats['fail']++;
        $failures[] = $name . ($detail !== '' ? " — $detail" : '');
        echo "  \033[31mKĻŪDA\033[0m $name" . ($detail !== '' ? "\n        $detail" : '') . "\n";
    }
    return $condition;
}

function skip(string $name, string $why): void
{
    global $stats;
    $stats['skip']++;
    echo "  \033[33mIZLAISTS\033[0m $name ($why)\n";
}

function flash(Client $c): string
{
    $html = $c->follow();
    preg_match_all('/class="toast[^"]*"[^>]*>.*?<p>(.*?)<\/p>/s', $html, $m);
    return html_entity_decode(strip_tags(implode(' | ', $m[1])));
}

function extract_id(string $pattern, string $html): int
{
    return preg_match($pattern, $html, $m) ? (int)$m[1] : 0;
}

$suffix   = bin2hex(random_bytes(3));
$userA    = "smoke_a_$suffix";
$userB    = "smoke_b_$suffix";
$password = 'Testa-parole-123';
$tomorrow = date('Y-m-d', strtotime('+1 day'));

echo "Uzdevumi — dūmu tests\nAdrese: $base\n";

try {
    /* ============================================================= */
    section('Pieejamība un drošība');
    $guest = new Client($base);

    $guest->get('tasks');
    if ($guest->status === 0 || ($guest->status >= 500 && !str_contains($guest->body, '<'))) {
        throw new RuntimeException("Lietotne neatbild ($base). Vai Apache un MySQL ir palaisti?");
    }
    check('Bez pieteikšanās saraksts pāradresē uz pieteikšanos', $guest->status === 302 && str_contains($guest->location(), 'r=login'),
        "statuss {$guest->status}, location: {$guest->location()}");
    $guest->get('nav-tada-lapa');
    check('Nezināms maršruts atbild ar 404 un kļūdas lapu', $guest->status === 404 && str_contains($guest->body, 'class="error-page"'),
        "statuss {$guest->status}");
    $guest->get('logout');
    check('GET pieprasījums uz atteikšanos atbild ar 405', $guest->status === 405, "statuss {$guest->status}");

    $fresh = new Client($base);   // jauns klients - sīkdatne tiek iestatīta pirmajā atbildē
    $fresh->get('login');
    check('Pieteikšanās lapa atveras', $fresh->status === 200 && str_contains($fresh->body, 'name="password"'), "statuss {$fresh->status}");
    check('Drošības galvenes (X-Frame-Options, nosniff)',
        ($fresh->headers['x-frame-options'] ?? '') === 'DENY' && ($fresh->headers['x-content-type-options'] ?? '') === 'nosniff');
    $cookie = $fresh->headers['set-cookie'] ?? '';
    check('Sesijas sīkdatne ar HttpOnly un SameSite', stripos($cookie, 'httponly') !== false && stripos($cookie, 'samesite=lax') !== false, $cookie);

    $guest->post('login', ['username' => 'x', 'password' => 'y'], [], [], false);
    check('POST bez CSRF koda tiek noraidīts (403)', $guest->status === 403, "statuss {$guest->status}");

    $guest->raw('index.php?r=login');
    $isApache = stripos($guest->headers['server'] ?? '', 'apache') !== false;
    foreach (['config/config.php', 'database/schema.sql', 'app/models/User.php', 'logs/', 'uploads/', 'tests/smoke.php'] as $path) {
        if (!$isApache) {
            skip("Mape aizsargāta: $path", 'nav Apache - .htaccess netiek lietots');
            continue;
        }
        $guest->raw($path);
        check("Mape aizsargāta ar .htaccess: $path", $guest->status === 403, "statuss {$guest->status}");
    }

    /* ============================================================= */
    section('Reģistrācija un pieteikšanās');
    $a = new Client($base);

    $a->post('register', ['username' => 'a', 'email' => 'nav-epasts', 'password' => '1', 'password2' => '2']);
    check('Nederīgi reģistrācijas dati parāda kļūdas', $a->status === 200 && substr_count($a->body, 'class="alert"') >= 3,
        'kļūdu skaits: ' . substr_count($a->body, 'class="alert"'));

    $a->post('register', ['username' => $userA, 'email' => "$userA@example.com", 'password' => $password, 'password2' => $password]);
    check('Reģistrācija izdodas', $a->status === 302 && str_contains($a->location(), 'r=login'), "statuss {$a->status}");

    $a->post('register', ['username' => $userA, 'email' => "$userA@example.com", 'password' => $password, 'password2' => $password]);
    check('Aizņemts lietotājvārds tiek noraidīts', str_contains($a->body, 'aizņemts'));

    $a->post('login', ['username' => $userA, 'password' => 'nepareiza-parole']);
    check('Nepareiza parole tiek noraidīta', $a->status === 200 && str_contains($a->body, 'Nepareizs lietotājvārds vai parole'));

    $a->post('login', ['username' => $userA, 'password' => $password]);
    check('Pieteikšanās izdodas', $a->status === 302 && str_contains($a->location(), 'r=tasks'), "statuss {$a->status}");
    $a->forgetToken();

    $a->get('tasks');
    check('Sākumlapa atveras pēc pieteikšanās', $a->status === 200 && str_contains($a->body, $userA));

    /* ============================================================= */
    section('Kategorijas');
    $a->post('categories/create', ['name' => 'Skola']);
    check('Kategorijas izveide', $a->status === 302);
    $a->get('categories');
    $catId = extract_id('/name="id" value="(\d+)">\s*<input type="text" name="name" value="Skola"/', $a->body);
    check('Kategorija redzama sarakstā', $catId > 0);
    $a->post('categories/edit', ['id' => $catId, 'name' => 'Skola un mājasdarbi']);
    $a->get('categories');
    check('Kategorijas pārdēvēšana', str_contains($a->body, 'value="Skola un mājasdarbi"'));
    $a->post('categories/create', ['name' => '   ']);
    check('Tukšs kategorijas nosaukums tiek noraidīts', str_contains($a->body, 'class="alert"'));
    $a->post('categories/create', ['name' => 'Skola un mājasdarbi']);
    check('Kategorija ar jau esošu nosaukumu tiek noraidīta', str_contains($a->body, 'jau ir'));

    /* ============================================================= */
    section('Uzdevumi');
    $a->post('tasks/create', [
        'title' => '', 'description' => '', 'due_date' => '2026-02-30', 'status' => 'xxx',
        'priority' => 'mega', 'repeat_rule' => 'katru-gadu', 'category_id' => '999999',
    ]);
    check('Nederīgi uzdevuma dati parāda visas kļūdas', substr_count($a->body, 'class="alert"') >= 6,
        'kļūdu skaits: ' . substr_count($a->body, 'class="alert"'));

    $title = "Dūmu tests $suffix";
    $a->post('tasks/create', [
        'title' => $title, 'description' => "Pirmā rinda\nOtrā rinda", 'due_date' => $tomorrow, 'status' => 'jauns',
        'priority' => 'augsta', 'repeat_rule' => 'weekly', 'category_id' => (string)$catId,
    ]);
    $taskId = extract_id('/id=(\d+)/', $a->location());
    check('Uzdevuma izveide pāradresē uz tā lapu', $a->status === 302 && $taskId > 0, "location: {$a->location()}");

    $a->get('tasks');
    check('Uzdevums redzams sarakstā ar prioritāti un atkārtošanos',
        str_contains($a->body, htmlspecialchars($title)) && str_contains($a->body, 'prio-high') && str_contains($a->body, 'katru nedēļu'));
    $a->get('tasks', ['q' => "tests $suffix"]);
    check('Meklēšana atrod uzdevumu', str_contains($a->body, htmlspecialchars($title)));
    $a->get('tasks', ['q' => 'šāda-uzdevuma-nav-' . $suffix]);
    check('Meklēšana bez rezultātiem', str_contains($a->body, 'Nekas netika atrasts'));
    $a->get('tasks', ['q' => "%' OR 1=1 --"]);
    check('Meklēšana ar SQL injekcijas mēģinājumu ir droša', $a->status === 200 && !str_contains($a->body, htmlspecialchars($title)));

    $a->get('tasks/edit', ['id' => $taskId]);
    check('Uzdevuma lapa ar visām sadaļām',
        $a->status === 200 && str_contains($a->body, 'data-region="steps"') && str_contains($a->body, 'data-region="files"')
        && str_contains($a->body, 'data-region="activity"') && str_contains($a->body, 'Pirmā rinda'));

    $edit = ['title' => $title, 'description' => 'Labots apraksts', 'due_date' => $tomorrow, 'status' => 'procesā',
             'priority' => 'augsta', 'repeat_rule' => 'weekly', 'category_id' => (string)$catId];
    $a->post('tasks/edit', $edit, ['X-Partial: 1'], ['id' => $taskId]);
    $json = $a->json();
    check('Saglabāšana bez pārlādes atbild ar JSON', ($json['ok'] ?? false) === true && str_contains($json['redirect'] ?? '', 'tasks/edit'),
        substr($a->body, 0, 200));
    $a->post('tasks/edit', ['title' => ''] + $edit, ['X-Partial: 1'], ['id' => $taskId]);
    check('Kļūda saglabājot bez pārlādes atgriež lapu ar kļūdu', $a->status === 200 && str_contains($a->body, 'class="alert"'));
    $a->post('tasks/edit', $edit, ['X-Partial: 1'], ['id' => 99999999]);
    check('Neesošs uzdevums atbild ar JSON 404', $a->status === 404 && ($a->json()['ok'] ?? null) === false, "statuss {$a->status}");

    /* ============================================================= */
    section('Soļi, saites un komentāri');
    $a->post('subtasks/create', ['task_id' => $taskId, 'title' => "Solis ar\nrindu"], ['X-Partial: 1']);
    check('Soļa pievienošana', ($a->json()['ok'] ?? false) === true);
    $a->get('tasks/edit', ['id' => $taskId]);
    check('Vienrindas laukā rindu pārnesums tiek aizstāts', str_contains($a->body, '>Solis ar rindu<'));
    $stepId = extract_id('/subtasks\/toggle">\s*<input type="hidden" name="csrf_token"[^>]*>\s*<input type="hidden" name="id" value="(\d+)"/', $a->body);
    $a->post('subtasks/toggle', ['id' => $stepId]);
    $a->get('tasks/edit', ['id' => $taskId]);
    check('Soļa atzīmēšana', str_contains($a->body, 'class="steps-count">1/1<'));

    $a->post('links/create', ['task_id' => $taskId, 'url' => 'https://example.com/attels.png', 'title' => '']);
    check('Saites pievienošana', str_contains(flash($a), 'Saite pievienota'));
    $a->post('links/create', ['task_id' => $taskId, 'url' => 'javascript:alert(1)', 'title' => '']);
    check('Bīstama saite (javascript:) tiek noraidīta', str_contains(flash($a), 'Nederīga saite'));

    $a->post('comments/create', ['task_id' => $taskId, 'body' => "Komentārs <b>ar HTML</b>\notrā rindā"]);
    $a->get('tasks/edit', ['id' => $taskId]);
    check('Komentārs pievienots un HTML tiek attēlots droši', str_contains($a->body, 'Komentārs &lt;b&gt;ar HTML&lt;/b&gt;<br'));
    $commentId = extract_id('/comments\/delete">\s*<input type="hidden" name="csrf_token"[^>]*>\s*<input type="hidden" name="id" value="(\d+)"/', $a->body);
    check('Aktivitātē ierakstītas darbības', str_contains($a->body, 'pievienoja soli') && str_contains($a->body, 'pievienoja saiti'));

    /* ============================================================= */
    section('Kopīgošana');
    $b = new Client($base);
    $b->post('register', ['username' => $userB, 'email' => "$userB@example.com", 'password' => $password, 'password2' => $password]);
    $b->post('login', ['username' => $userB, 'password' => $password]);
    $b->forgetToken();
    check('Otrais lietotājs piesakās', $b->status === 302);

    $a->post('shares/create', ['task_id' => $taskId, 'username' => $userB]);
    check('Kopīgošana ar otru lietotāju', str_contains(flash($a), 'Kopīgots ar'));
    $a->post('shares/create', ['task_id' => $taskId, 'username' => $userA]);
    check('Kopīgot ar sevi nevar', str_contains(flash($a), 'jau ir tavs'));
    $a->post('shares/create', ['task_id' => $taskId, 'username' => 'nav_tada_' . $suffix]);
    check('Neesošs lietotājs tiek noraidīts', str_contains(flash($a), 'nav atrasts'));

    $b->get('tasks');
    check('Kopīgotais uzdevums redzams otram lietotājam', str_contains($b->body, htmlspecialchars($title)) && str_contains($b->body, $userA));
    $b->get('tasks/edit', ['id' => $taskId]);
    check('Otrais lietotājs redz tikai skatu (nevar rediģēt)', str_contains($b->body, 'kopīgoja ar tevi') && !str_contains($b->body, 'class="title-input"'));

    $b->post('tasks/edit', $edit, [], ['id' => $taskId]);
    check('Kopīgots lietotājs nevar rediģēt (404)', $b->status === 404, "statuss {$b->status}");
    $b->post('tasks/status', ['id' => $taskId, 'status' => 'pabeigts'], ['X-Requested-With: fetch']);
    check('Kopīgots lietotājs nevar mainīt statusu dēlī', $b->status === 400, "statuss {$b->status}");
    $b->post('shares/create', ['task_id' => $taskId, 'username' => $userB]);
    check('Kopīgots lietotājs nevar kopīgot tālāk', $b->status === 404, "statuss {$b->status}");
    $b->post('subtasks/create', ['task_id' => $taskId, 'title' => 'Otrā lietotāja solis'], ['X-Partial: 1']);
    check('Kopīgots lietotājs var pievienot soli', ($b->json()['ok'] ?? false) === true);
    $b->post('comments/delete', ['id' => $commentId]);
    check('Svešu komentāru dzēst nevar', $b->status === 404, "statuss {$b->status}");

    $a->post('tasks/create', ['title' => "Privāts $suffix", 'description' => '', 'due_date' => '', 'status' => 'jauns',
        'priority' => 'zema', 'repeat_rule' => '', 'category_id' => '']);
    $privateId = extract_id('/id=(\d+)/', $a->location());
    $b->get('tasks/edit', ['id' => $privateId]);
    check('Nekopīgotu uzdevumu otrs lietotājs neredz (404)', $b->status === 404, "statuss {$b->status}");

    /* ============================================================= */
    section('Pielikumi');
    $pdf = tempnam(sys_get_temp_dir(), 'pdf');
    file_put_contents($pdf, "%PDF-1.4\n%test\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n");
    $evil = tempnam(sys_get_temp_dir(), 'png');
    file_put_contents($evil, '<?php echo "uzlauzts"; ?>');

    $a->post('files/upload', ['task_id' => $taskId, 'file' => new CURLFile($pdf, 'application/pdf', 'konspekts.pdf')], [], ['task' => $taskId]);
    check('PDF faila augšupielāde', str_contains(flash($a), 'Fails pievienots'));
    $fileId = extract_id('/files\/download&amp;id=(\d+)/', $a->body);
    $a->post('files/upload', ['task_id' => $taskId, 'file' => new CURLFile($evil, 'image/png', 'attels.png')], [], ['task' => $taskId]);
    check('PHP kods ar .png nosaukumu tiek noraidīts', str_contains(flash($a), 'Šāda veida failu nevar pievienot'));

    $a->get('files/download', ['id' => $fileId]);
    check('Lejupielāde ar pareizām galvenēm', $a->status === 200 && str_starts_with($a->headers['content-type'] ?? '', 'application/pdf')
        && str_contains($a->headers['content-disposition'] ?? '', 'attachment') && str_starts_with($a->body, '%PDF'),
        "statuss {$a->status}, tips: " . ($a->headers['content-type'] ?? ''));
    $b->get('files/download', ['id' => $fileId]);
    check('Kopīgots lietotājs var lejupielādēt', $b->status === 200);
    unlink($pdf);
    unlink($evil);

    /* ============================================================= */
    section('Dēlis, atkārtošana un kalendārs');
    $a->get('board');
    check('Dēlis atveras', $a->status === 200 && substr_count($a->body, 'class="kb-col') === 3);

    $a->post('tasks/status', ['id' => $taskId, 'status' => 'pabeigts'], ['X-Requested-With: fetch']);
    $json = $a->json();
    $nextId = (int)($json['spawned'] ?? 0);
    check('Statusa maiņa dēlī (JSON)', ($json['ok'] ?? false) === true && ($json['status'] ?? '') === 'pabeigts', substr($a->body, 0, 200));
    check('Atkārtotam uzdevumam izveidots nākamais', $nextId > 0);
    $a->get('tasks/edit', ['id' => $nextId]);
    $nextDue = date('Y-m-d', strtotime($tomorrow . ' +1 week'));
    check('Nākamajam atkārtojumam termiņš +1 nedēļa un soļi nokopēti',
        str_contains($a->body, 'name="due_date" value="' . $nextDue . '"') && str_contains($a->body, 'Solis ar rindu'), "gaidīts $nextDue");

    $a->post('tasks/status', ['id' => $taskId, 'status' => 'nav-statuss'], ['X-Requested-With: fetch']);
    check('Nederīgs statuss tiek noraidīts (400)', $a->status === 400, "statuss {$a->status}");

    // Ikdienas uzdevums, kas nokavēts 5 dienas: nākamais atkārtojums ir rīt, nevis pagātnē
    $a->post('tasks/create', ['title' => "Ikdienas $suffix", 'description' => '', 'due_date' => date('Y-m-d', strtotime('-5 days')),
        'status' => 'jauns', 'priority' => 'zema', 'repeat_rule' => 'daily', 'category_id' => '']);
    $dailyId = extract_id('/id=(\d+)/', $a->location());
    $a->post('tasks/status', ['id' => $dailyId, 'status' => 'pabeigts'], ['X-Requested-With: fetch']);
    $a->get('tasks/edit', ['id' => (int)($a->json()['spawned'] ?? 0)]);
    check('Nokavētam atkārtotam uzdevumam nākamais termiņš ir nākotnē (rīt)',
        str_contains($a->body, 'name="due_date" value="' . $tomorrow . '"'), "gaidīts $tomorrow");

    $a->get('tasks');
    check('Sērija rāda pabeigto uzdevumu', (bool)preg_match('/data-count="[1-9]\d*">[1-9]\d*<\/b>\s*<span>dien/', $a->body));

    $a->get('calendar', ['month' => date('Y-m', strtotime($nextDue))]);
    check('Kalendārs atveras', $a->status === 200 && str_contains($a->body, 'data-date="' . $nextDue . '"'));
    $a->get('calendar', ['month' => 'nederigs']);
    check('Kalendārs ar nederīgu mēnesi atver šo mēnesi', $a->status === 200 && str_contains($a->body, 'data-date="' . date('Y-m-d') . '"'));
    $a->post('tasks/due', ['id' => $nextId, 'due_date' => $tomorrow], ['X-Requested-With: fetch']);
    check('Termiņa maiņa kalendārā (JSON)', ($a->json()['ok'] ?? false) === true, substr($a->body, 0, 200));
    $a->post('tasks/due', ['id' => $nextId, 'due_date' => '2026-13-45'], ['X-Requested-With: fetch']);
    check('Nederīgs datums tiek noraidīts (400)', $a->status === 400, "statuss {$a->status}");

    /* ============================================================= */
    section('Dzēšana');
    $a->post('comments/delete', ['id' => $commentId]);
    check('Sava komentāra dzēšana', str_contains(flash($a), 'Komentārs izdzēsts'));
    $a->post('categories/delete', ['id' => $catId]);
    check('Kategorijas dzēšana', str_contains(flash($a), 'Kategorija izdzēsta'));
    $a->get('tasks/edit', ['id' => $nextId]);
    preg_match('/<select name="category_id">(.*?)<\/select>/s', $a->body, $m);
    check('Uzdevums paliek bez kategorijas', $a->status === 200 && isset($m[1]) && !str_contains($m[1], 'selected'));

    $b->post('tasks/delete', ['id' => $taskId]);
    check('Kopīgots lietotājs nevar izdzēst uzdevumu', str_contains(flash($b), 'nav atrasts'));
    $a->post('tasks/delete', ['id' => $taskId]);
    check('Uzdevuma dzēšana', str_contains(flash($a), 'Uzdevums izdzēsts'));
    $a->get('files/download', ['id' => $fileId]);
    check('Dzēsta uzdevuma faili vairs nav pieejami', $a->status === 404, "statuss {$a->status}");

    /* ============================================================= */
    section('Profils');
    $passwords = [$userA => $password, $userB => $password];
    $a->get('profile');
    check('Profila lapa ar statistiku', $a->status === 200 && str_contains($a->body, 'class="profile-stats"') && str_contains($a->body, $userA));
    $a->post('profile/password', ['current_password' => 'nepareiza', 'password' => 'Jauna-parole-456', 'password2' => 'Jauna-parole-456']);
    check('Paroles maiņa ar nepareizu pašreizējo paroli tiek noraidīta', str_contains($a->body, 'Pašreizējā parole nav pareiza'));
    $a->post('profile/password', ['current_password' => $password, 'password' => 'Jauna-parole-456', 'password2' => 'Cita-parole-789']);
    check('Paroles maiņa ar nesakrītošām parolēm tiek noraidīta', str_contains($a->body, 'Paroles nesakrīt'));
    $a->post('profile/password', ['current_password' => $password, 'password' => 'Jauna-parole-456', 'password2' => 'Jauna-parole-456']);
    check('Paroles maiņa izdodas', str_contains(flash($a), 'Parole nomainīta'));
    $passwords[$userA] = 'Jauna-parole-456';

    /* ============================================================= */
    section('Atteikšanās');
    $a->post('logout');
    $a->forgetToken();
    $a->get('tasks');
    check('Pēc atteikšanās saraksts nav pieejams', $a->status === 302 && str_contains($a->location(), 'r=login'));
    $a->post('login', ['username' => $userA, 'password' => $password]);
    check('Vecā parole pēc maiņas vairs nederīga', $a->status === 200 && str_contains($a->body, 'Nepareizs'));
    $a->post('login', ['username' => $userA, 'password' => $passwords[$userA]]);
    check('Jaunā parole der', $a->status === 302 && str_contains($a->location(), 'r=tasks'));
    $a->forgetToken();

    /* ============================================================= */
    if ($full) {
        section('Aizsardzība pret paroļu minēšanu');
        $c = new Client($base);
        for ($i = 0; $i < 5; $i++) {
            $c->post('login', ['username' => $userB, 'password' => "nepareiza-$i"]);
        }
        $c->post('login', ['username' => $userB, 'password' => $password]);
        check('Pēc 5 neveiksmīgiem mēģinājumiem pieteikšanās ir bloķēta', str_contains($c->body, 'Pārāk daudz neveiksmīgu mēģinājumu'));
    }

    /* ============================================================= */
    section('Konta dzēšana (tīrīšana)');
    $b->post('profile/delete', ['password' => 'nepareiza']);
    check('Kontu ar nepareizu paroli nevar izdzēst', str_contains($b->body, 'parole nav pareiza'));
    foreach ([[$a, $userA], [$b, $userB]] as [$client, $name]) {
        $client->post('profile/delete', ['password' => $passwords[$name]]);
        check("Pagaidu lietotājs $name izdzēsts", $client->status === 302 && str_contains($client->location(), 'r=login'), "statuss {$client->status}");
    }
    $c = new Client($base);
    $c->post('login', ['username' => $userA, 'password' => $passwords[$userA]]);
    check('Izdzēstais konts vairs nevar pieteikties', str_contains($c->body, 'Nepareizs'));
    $b->get('tasks');
    check('Izdzēstā konta sesija ir beigusies', $b->status === 302 && str_contains($b->location(), 'r=login'));
} catch (Throwable $e) {
    $stats['fail']++;
    $failures[] = 'Tests apstājās: ' . $e->getMessage();
    echo "\n\033[31mTests apstājās:\033[0m {$e->getMessage()}\n";
}

/* ---------------------------------------------------------------
   Kopsavilkums
   --------------------------------------------------------------- */
section('PHP brīdinājumi lapās');
check('Nevienā atbildē nav PHP brīdinājumu', !Client::$warnings, implode("\n        ", array_slice(Client::$warnings, 0, 5)));

echo "\n" . str_repeat('─', 50) . "\n";
printf("Izdevās: %d   Kļūdas: %d   Izlaisti: %d\n", $stats['ok'], $stats['fail'], $stats['skip']);
if ($failures) {
    echo "\nNeizdevās:\n - " . implode("\n - ", $failures) . "\n";
}
exit($stats['fail'] ? 1 : 0);
