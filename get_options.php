<?php
// ======================================================================
// SCRIPT: get_options.php
// ======================================================================
// PURPOSE: Fetches distinct Makes and Models from the MAIN CarStrike DB
//          to populate autocomplete fields in the generator.
// ======================================================================

header('Content-Type: application/json');

// Connect to the MAIN CarStrike database (Source of truth for Makes/Models)
$db_file = 'carstrike.sqlite';

if (!file_exists($db_file)) {
    echo json_encode([]);
    exit;
}

try {
    $pdo = new PDO('sqlite:' . $db_file);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $type = $_GET['type'] ?? '';
    $make = $_GET['make'] ?? '';

    if ($type === 'makes') {
        // Fetch all distinct makes
        $stmt = $pdo->query("SELECT DISTINCT make FROM cars ORDER BY make ASC");
        $makes = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo json_encode($makes);

    } elseif ($type === 'models' && !empty($make)) {
        // Fetch distinct models for a specific make
        $stmt = $pdo->prepare("SELECT DISTINCT model FROM cars WHERE make LIKE ? ORDER BY model ASC");
        $stmt->execute([$make]);
        $models = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo json_encode($models);
        
    } else {
        echo json_encode([]);
    }

} catch (Exception $e) {
    echo json_encode([]);
}
?>
