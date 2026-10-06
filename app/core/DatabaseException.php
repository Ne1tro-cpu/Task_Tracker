<?php
// Datubāzes problēma ar lietotājam saprotamu skaidrojumu.
// $kind pasaka, kas noticis:
//   server   - MySQL serveris nav palaists
//   access   - nepareizs MySQL lietotājvārds vai parole
//   database - datubāze vēl nav izveidota
//   tables   - datubāzē nav tabulu
//   upgrade  - datubāze ir vecākā versijā (trūkst kolonnu)
//   other    - cita kļūda
class DatabaseException extends RuntimeException
{
    public function __construct(public readonly string $kind, string $message, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    // PDO kļūdu pēc MySQL kļūdas koda pārvēršam saprotamā skaidrojumā
    public static function fromPdo(PDOException $e): self
    {
        $code = (int)($e->errorInfo[1] ?? 0) ?: (int)$e->getCode();

        return match (true) {
            in_array($code, [2002, 2003, 2006], true) =>
                new self('server', 'Neizdevās pieslēgties MySQL serverim. Pārbaudi, vai XAMPP Control Panel ir palaists MySQL.', $e),
            in_array($code, [1044, 1045, 1698], true) =>
                new self('access', 'MySQL nepieņēma lietotājvārdu vai paroli. Pārbaudi DB_USER un DB_PASS failā config/config.php.', $e),
            $code === 1049 => new self('database', 'Datubāze vēl nav izveidota.', $e),
            $code === 1146 => new self('tables', 'Datubāzē nav vajadzīgo tabulu.', $e),
            $code === 1054 => new self('upgrade', 'Datubāze ir vecākā versijā - tā jāatjaunina.', $e),
            default        => new self('other', 'Datubāzes kļūda. Mēģini vēlreiz.', $e),
        };
    }

    // Vai problēmu var atrisināt sagatavošanas lapā (izveidot vai atjaunināt datubāzi)?
    public function fixableBySetup(): bool
    {
        return in_array($this->kind, ['database', 'tables', 'upgrade'], true);
    }
}
