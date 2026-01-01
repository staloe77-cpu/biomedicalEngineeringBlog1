<?php

require_once 'config.php';

try {
    $db = getDB();

    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE NOT NULL,
        password CHAR(60) NOT NULL
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS posts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL,
        content TEXT NOT NULL,
        user_id INTEGER,
        status TEXT DEFAULT 'draft',
        image_path TEXT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )");

    $columns = [];
    $stmt = $db->query("PRAGMA table_info(posts)");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
        $columns[] = $col['name'];
    }

    if (!in_array('image_path', $columns)) {
        $db->exec("ALTER TABLE posts ADD COLUMN image_path TEXT NULL");
    }

    if (in_array('image', $columns)) {
        $db->exec("
            UPDATE posts
            SET image_path = image
            WHERE image IS NOT NULL
              AND (image_path IS NULL OR image_path = '')
        ");
    }

    $db->exec("CREATE TABLE IF NOT EXISTS comments (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        content TEXT NOT NULL,
        user_id INTEGER,
        post_id INTEGER,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id),
        FOREIGN KEY (post_id) REFERENCES posts(id)
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS contact_messages (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        email TEXT NOT NULL,
        message TEXT NOT NULL,
        status TEXT DEFAULT 'new',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    echo "Database initialized successfully!\n";
    echo "- users table OK\n";
    echo "- posts table OK (with image_path)\n";
    echo "- comments table OK\n";
    echo "- contact_messages table OK\n";

} catch (PDOException $e) {
    echo "Error initializing database: " . $e->getMessage() . "\n";
    exit(1);
}