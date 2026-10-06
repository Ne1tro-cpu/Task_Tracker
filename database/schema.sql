-- Datubāzes izveide (phpMyAdmin -> SQL cilne, vai automātiski sagatavošanas lapā index.php?r=setup)
CREATE DATABASE IF NOT EXISTS Task_Tracker_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE Task_Tracker_db_db;

-- Lietotāji
CREATE TABLE IF NOT EXISTS users (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(30)  NOT NULL UNIQUE,
    email         VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,          -- password_hash() rezultāts, nevis parole
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Kategorijas (katram lietotājam savas)
CREATE TABLE IF NOT EXISTS categories (
    id      INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT         NOT NULL,
    name    VARCHAR(50) NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Uzdevumi (saistīti ar lietotāju UN kategoriju) - pilns CRUD
CREATE TABLE IF NOT EXISTS tasks (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT          NOT NULL,
    category_id INT          NULL,
    title       VARCHAR(100) NOT NULL,
    description TEXT         NULL,
    due_date    DATE         NULL,
    status      ENUM('jauns','procesā','pabeigts') NOT NULL DEFAULT 'jauns',
    priority    ENUM('zema','vidēja','augsta')     NOT NULL DEFAULT 'vidēja',
    repeat_rule VARCHAR(10)  NULL,                -- daily / weekly / monthly / NULL
    completed_at DATETIME    NULL,                -- kad atzīmēts kā pabeigts (aktivitātes kartei)
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)     REFERENCES users(id)      ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
);

-- Uzdevuma soļi (apakšuzdevumi)
CREATE TABLE IF NOT EXISTS subtasks (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    task_id    INT          NOT NULL,
    title      VARCHAR(100) NOT NULL,
    is_done    TINYINT(1)   NOT NULL DEFAULT 0,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE
);

-- Saites pie uzdevuma (lapas, attēli, video)
CREATE TABLE IF NOT EXISTS task_links (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    task_id    INT          NOT NULL,
    url        VARCHAR(500) NOT NULL,
    title      VARCHAR(100) NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE
);

-- Kopīgošana: kuram lietotājam ir piekļuve citu uzdevumam
CREATE TABLE IF NOT EXISTS task_shares (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    task_id    INT      NOT NULL,
    user_id    INT      NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (task_id, user_id),
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Pielikumi (faili glabājas mapē uploads/ ar nejaušu nosaukumu)
CREATE TABLE IF NOT EXISTS task_files (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    task_id       INT          NOT NULL,
    user_id       INT          NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    stored_name   VARCHAR(64)  NOT NULL UNIQUE,
    mime          VARCHAR(100) NOT NULL,
    size          INT          NOT NULL,
    created_at    DATETIME     NOT NULL,
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Aktivitāte un komentāri pie uzdevuma
CREATE TABLE IF NOT EXISTS task_activity (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    task_id    INT      NOT NULL,
    user_id    INT      NOT NULL,
    type       ENUM('event','comment') NOT NULL,
    body       TEXT     NOT NULL,
    created_at DATETIME NOT NULL,
    INDEX (task_id, created_at),
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Pieteikšanās mēģinājumi (aizsardzība pret paroļu minēšanu)
CREATE TABLE IF NOT EXISTS login_attempts (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    ip_address   VARCHAR(45) NOT NULL,
    username     VARCHAR(30) NOT NULL,
    attempted_at DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX (ip_address, attempted_at)
);
