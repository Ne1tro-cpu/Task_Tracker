<?php
// FRONT CONTROLLER - vienīgais ieejas punkts visai lietotnei.
// Visi pieprasījumi nāk šeit: index.php?r=tasks, index.php?r=tasks/edit&id=5 u.t.t.
// Maršrutētājs (router) pēc HTTP metodes + maršruta izvēlas kontrolieri un tā metodi.

require __DIR__ . '/config/config.php';

// Klašu automātiskā ielāde no app/core, app/models, app/controllers
spl_autoload_register(function ($class) {
    foreach (['core', 'models', 'controllers'] as $folder) {
        $file = __DIR__ . "/app/$folder/$class.php";
        if (is_file($file)) {
            require $file;
            return;
        }
    }
});

require __DIR__ . '/app/core/helpers.php';

// Kļūdas: ierakstām žurnālā un parādām saprotamu lapu (sk. app/core/ErrorHandler.php)
ErrorHandler::register();

// Žurnāla un pielikumu mapes vienmēr aizsargātas (ja to nav - izveido kopā ar .htaccess)
ensure_private_dir(dirname(LOG_FILE));
ensure_private_dir(UPLOAD_DIR);
ob_start();   // lapa vispirms tiek veidota buferī - kļūdas gadījumā to var aizstāt ar kļūdas lapu

// Drošības galvenes: lapu nevar ielikt svešā <iframe> (clickjacking), pārlūks neuzmin failu tipus
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

// Sesija:
//   use_strict_mode - nepieņem svešus, serverī neizveidotus sesijas ID (pret sesijas fiksāciju)
//   HttpOnly        - JavaScript nevar nolasīt sīkdatni
//   SameSite=Lax    - citas lapas nevar sūtīt POST pieprasījumus ar šo sīkdatni
ini_set('session.use_strict_mode', '1');
session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
    'secure'   => !empty($_SERVER['HTTPS']),
]);
session_start();

// Maršrutu tabula: 'METODE maršruts' => [Kontrolieris, metode]
// GET  - tikai datu lasīšana / formu parādīšana
// POST - visas darbības, kas maina datus
$routes = [
    'GET register'         => ['AuthController', 'showRegister'],
    'POST register'        => ['AuthController', 'register'],
    'GET login'            => ['AuthController', 'showLogin'],
    'POST login'           => ['AuthController', 'login'],
    'POST logout'          => ['AuthController', 'logout'],

    'GET tasks'            => ['TaskController', 'index'],      // Read
    'GET tasks/create'     => ['TaskController', 'create'],     // forma
    'POST tasks/create'    => ['TaskController', 'store'],      // Create
    'GET tasks/edit'       => ['TaskController', 'edit'],       // forma
    'POST tasks/edit'      => ['TaskController', 'update'],     // Update
    'POST tasks/delete'    => ['TaskController', 'delete'],     // Delete
    'POST tasks/status'    => ['TaskController', 'changeStatus'], // Kanban dēlis
    'POST tasks/due'       => ['TaskController', 'changeDue'],    // kalendārs

    'GET board'            => ['BoardController', 'index'],
    'GET calendar'         => ['CalendarController', 'index'],

    'GET categories'         => ['CategoryController', 'index'],
    'POST categories/create' => ['CategoryController', 'store'],
    'POST categories/edit'   => ['CategoryController', 'update'],
    'POST categories/delete' => ['CategoryController', 'delete'],

    'POST subtasks/create'   => ['SubtaskController', 'store'],
    'POST subtasks/toggle'   => ['SubtaskController', 'toggle'],
    'POST subtasks/delete'   => ['SubtaskController', 'delete'],

    'POST links/create'      => ['LinkController', 'store'],
    'POST links/delete'      => ['LinkController', 'delete'],

    'POST shares/create'     => ['ShareController', 'store'],
    'POST shares/delete'     => ['ShareController', 'delete'],

    'POST files/upload'      => ['FileController', 'upload'],
    'GET files/download'     => ['FileController', 'download'],
    'POST files/delete'      => ['FileController', 'delete'],

    'POST comments/create'   => ['CommentController', 'store'],
    'POST comments/delete'   => ['CommentController', 'delete'],

    'GET profile'            => ['ProfileController', 'index'],
    'POST profile/password'  => ['ProfileController', 'changePassword'],
    'POST profile/delete'    => ['ProfileController', 'delete'],

    // Datubāzes izveide/atjaunināšana (pieejama tikai tad, kad datubāze nav gatava)
    'GET setup'              => ['SetupController', 'index'],
    'POST setup/install'     => ['SetupController', 'install'],
    'POST setup/upgrade'     => ['SetupController', 'upgrade'],
];

$method = $_SERVER['REQUEST_METHOD'];
$route  = is_string($_GET['r'] ?? null) ? $_GET['r'] : 'tasks';
$key    = "$method $route";

if (!isset($routes[$key])) {
    // Maršruts eksistē, bet ar citu metodi -> 405, citādi 404
    $otherMethod = ($method === 'GET' ? 'POST' : 'GET') . " $route";
    if (isset($routes[$otherMethod])) {
        render_error(405, 'Metode nav atļauta', 'Šī darbība jāveic ar formas palīdzību.');
    }
    render_error(404, 'Lapa nav atrasta', 'Šāda lapa neeksistē. Varbūt saite ir novecojusi?');
}

[$controllerName, $action] = $routes[$key];
$controller = new $controllerName();
$controller->$action();
