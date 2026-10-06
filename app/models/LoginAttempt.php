<?php
// Modelis tabulai `login_attempts` - aizsardzība pret paroļu minēšanu
class LoginAttempt extends Model
{
    // Vai no šīs IP pēdējās LOCK_MINUTES minūtēs ir par daudz neveiksmīgu mēģinājumu?
    public function isBlocked(string $ip): bool
    {
        $since = date('Y-m-d H:i:s', time() - LOCK_MINUTES * 60);

        $stmt = $this->db->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip_address = ? AND attempted_at > ?');
        $stmt->execute([$ip, $since]);
        return (int)$stmt->fetchColumn() >= MAX_LOGIN_ATTEMPTS;
    }

    public function add(string $ip, string $username): void
    {
        $stmt = $this->db->prepare('INSERT INTO login_attempts (ip_address, username, attempted_at) VALUES (?, ?, ?)');
        $stmt->execute([$ip, mb_substr($username, 0, 30), date('Y-m-d H:i:s')]);

        // Vecie ieraksti vairs nav vajadzīgi - lai tabula neaug bezgalīgi
        $old = date('Y-m-d H:i:s', time() - LOCK_MINUTES * 60);
        $this->db->prepare('DELETE FROM login_attempts WHERE attempted_at < ?')->execute([$old]);
    }

    // Pēc veiksmīgas pieteikšanās mēģinājumus notīram
    public function clear(string $ip): void
    {
        $stmt = $this->db->prepare('DELETE FROM login_attempts WHERE ip_address = ?');
        $stmt->execute([$ip]);
    }
}
