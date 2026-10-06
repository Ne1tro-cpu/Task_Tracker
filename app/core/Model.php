<?php
// Bāzes modelis - visi modeļi manto datubāzes savienojumu
abstract class Model
{
    protected PDO $db;

    public function __construct()
    {
        $this->db = Database::get();
    }
}
