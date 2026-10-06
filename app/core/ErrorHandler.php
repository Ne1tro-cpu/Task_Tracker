<?php
// Kļūdu apstrāde visai lietotnei:
//   - PHP brīdinājumi (Warning, Notice) tiek pārvērsti izņēmumos, lai tie nepaliktu nepamanīti
//     un nesabojātu lapu vai JSON atbildi;
//   - katrs izņēmums tiek ierakstīts žurnālā, un lietotājs redz saprotamu kļūdas lapu;
//   - ja datubāze vēl nav izveidota vai ir veca, lietotājs tiek aizvests uz sagatavošanas lapu.
class ErrorHandler
{
    public static function register(): void
    {
        set_error_handler([self::class, 'onError']);
        set_exception_handler([self::class, 'onException']);
    }

    public static function onError(int $severity, string $message, string $file, int $line): bool
    {
        if (!(error_reporting() & $severity)) {
            return false;   // kļūda apslāpēta ar @
        }
        // Novecojušu funkciju brīdinājumi lietotni neaptur - tikai ierakstām žurnālā
        if ($severity === E_DEPRECATED || $severity === E_USER_DEPRECATED) {
            write_log("Brīdinājums (deprecated): $message (" . basename($file) . ":$line)");
            return true;
        }
        throw new ErrorException($message, 0, $severity, $file, $line);
    }

    public static function onException(Throwable $e): void
    {
        restore_error_handler();   // kļūdas lapas veidošanas laikā vairs nepārvēršam brīdinājumus
        Database::rollBack();
        while (ob_get_level() > 0) {
            ob_end_clean();        // izmetam pa pusei izveidoto lapu, lai kļūdas lapa būtu tīra
        }

        // Žurnālā - arī sākotnējā kļūda (piem., MySQL teksts), kas slēpjas aiz saprotamā skaidrojuma
        $cause = $e->getPrevious() ?? $e;
        write_log('KĻŪDA: ' . get_class($e) . ': ' . $e->getMessage()
            . ($cause !== $e ? ' [' . $cause->getMessage() . ']' : '')
            . ' (' . basename($cause->getFile()) . ':' . $cause->getLine() . ')');

        $db = match (true) {
            $e instanceof DatabaseException => $e,
            $e instanceof PDOException      => DatabaseException::fromPdo($e),
            default                         => null,
        };

        // Datubāzi var izveidot vai atjaunināt sagatavošanas lapā
        if ($db && $db->fixableBySetup() && ($_GET['r'] ?? '') !== 'setup' && self::setupNeeded()) {
            if (is_partial_request()) {
                send_json(['ok' => false, 'error' => 'Datubāze nav sagatavota. Pārlādē lapu.'], 503);
            }
            header('Location: index.php?r=setup');
            exit;
        }

        $message = $db ? $db->getMessage() : 'Notika negaidīta kļūda. Mēģini vēlreiz.';
        if (APP_DEBUG) {
            $message .= ' [' . get_class($e) . ': ' . $e->getMessage() . ' - ' . basename($e->getFile()) . ':' . $e->getLine() . ']';
        }
        render_error($db ? 503 : 500, $db ? 'Problēma ar datubāzi' : 'Servera kļūda', $message);
    }

    // Pārliecināmies, ka sagatavošanas lapā tiešām ir ko darīt (citādi būtu bezgalīga pāradresācija)
    private static function setupNeeded(): bool
    {
        try {
            return (new Setup())->status() !== 'ok';
        } catch (Throwable) {
            return false;
        }
    }
}
