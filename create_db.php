<?php

try {
    // Create (or open) the database file
    $pdo = new PDO('sqlite:cars.sqlite');


    // Set attributes for error handling
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // SQL to create the table
    $sql = "CREATE TABLE IF NOT EXISTS cars (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        sale_price INTEGER,
        status TEXT, -- To store 'SOLD', 'NOT FOR SALE', etc.
        make TEXT NOT NULL,
        model TEXT NOT NULL,
        year INTEGER NOT NULL,
        transmission TEXT,
        fuel TEXT,
        kilometers INTEGER,
        history_written_off INTEGER, -- 0 for false, 1 for true
        is_as_is INTEGER,            -- 0 for false, 1 for true
        has_rego INTEGER,            -- 0 for false, 1 for true
        submission_date DATETIME DEFAULT CURRENT_TIMESTAMP
    )";

    // Execute the query
    $pdo->exec($sql);

    echo "Database and 'cars' table created successfully.";

} catch (PDOException $e) {
    // Handle any errors
    die("DB ERROR: ". $e->getMessage());
}
?>
