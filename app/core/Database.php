<?php
// Viens kopīgs PDO savienojums visiem modeļiem (Singleton)
class Database
{
    private static ?PDO $pdo = null;

    private const OPTIONS = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // kļūdas kā izņēmumi
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,                  // īsti parametrizēti vaicājumi
    ];

    public static function get(): PDO
    {
        if (self::$pdo === null) {
            try {
                self::$pdo = new PDO(DB_DSN, DB_USER, DB_PASS, self::OPTIONS);
            } catch (PDOException $e) {
                throw DatabaseException::fromPdo($e);   // saprotams skaidrojums, ko parāda kļūdu lapa
            }
        }
        return self::$pdo;
    }

    // Savienojums ar MySQL serveri bez konkrētas datubāzes (lai to varētu izveidot sagatavošanas lapā)
    public static function server(): PDO
    {
        try {
            return new PDO(preg_replace('/dbname=[^;]*;?/', '', DB_DSN), DB_USER, DB_PASS, self::OPTIONS);
        } catch (PDOException $e) {
            throw DatabaseException::fromPdo($e);
        }
    }

    // Datubāzes nosaukums no DB_DSN (piem., uzdevumi_db)
    public static function name(): string
    {
        if (!preg_match('/dbname=([A-Za-z0-9_]+)/', DB_DSN, $m)) {
            throw new RuntimeException('config.php: DB_DSN jānorāda dbname (tikai burti, cipari, _).');
        }
        return $m[1];
    }

    // Ja kļūdas brīdī bija atvērta transakcija - atceļam to
    public static function rollBack(): void
    {
        if (self::$pdo?->inTransaction()) {
            self::$pdo->rollBack();
        }
    }
}
