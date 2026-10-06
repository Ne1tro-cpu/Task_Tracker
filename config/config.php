<?php
// Lietotnes iestatījumi

// Datubāze (XAMPP noklusējumā: root bez paroles)
const DB_DSN  = 'mysql:host=localhost;dbname=uzdevumi_db;charset=utf8mb4';
const DB_USER = 'root';
const DB_PASS = '';

// Kļūdu detaļas lapā (ērti izstrādes laikā). Publiskā serverī noteikti false!
const APP_DEBUG = false;

// Mape ar SQL failiem (schema.sql un atjauninājumi) - izmanto sagatavošanas lapa
const DATABASE_DIR = __DIR__ . '/../database';

// Aizsardzība pret paroļu minēšanu
const MAX_LOGIN_ATTEMPTS = 5;   // neveiksmīgi mēģinājumi...
const LOCK_MINUTES       = 15;  // ...šajā laikā -> bloķēšana

// Žurnālfails
const LOG_FILE = __DIR__ . '/../logs/actions.log';

// Uzdevuma statusa atļautās vērtības
const STATUSES = ['jauns', 'procesā', 'pabeigts'];

// Uzdevuma prioritātes atļautās vērtības
const PRIORITIES = ['zema', 'vidēja', 'augsta'];

// Atkārtošanās: vērtība datubāzē => teksts lietotājam
const REPEATS = [
    'daily'   => 'katru dienu',
    'weekly'  => 'katru nedēļu',
    'monthly' => 'katru mēnesi',
];

// Pielikumi
const UPLOAD_DIR      = __DIR__ . '/../uploads';
const UPLOAD_MAX_SIZE = 5 * 1024 * 1024;   // 5 MB
// Atļautie paplašinājumi => atļautie MIME tipi (pārbauda faila saturu, ne tikai nosaukumu)
const UPLOAD_TYPES = [
    'pdf'  => ['application/pdf'],
    'png'  => ['image/png'],
    'jpg'  => ['image/jpeg'],
    'jpeg' => ['image/jpeg'],
    'gif'  => ['image/gif'],
    'webp' => ['image/webp'],
    'txt'  => ['text/plain'],
    'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
    'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
    'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip'],
    'zip'  => ['application/zip', 'application/x-zip-compressed'],
];

// Laika josla (datumiem, termiņiem, žurnālam)
date_default_timezone_set('Europe/Riga');
