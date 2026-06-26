<?php
// ======================================================================
// SCRIPT: save_car.php
// ======================================================================
// PURPOSE: Saves car sale display data to a separate SQLite database.
//          Target DB: carsales.sqlite
//          Features: Auto DB Migration, Proxy-aware IP capture
// ======================================================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); 
header('Access-Control-Allow-Headers: Content-Type');

// Helper to get real IP Address (handling proxies/Cloudflare)
function get_client_ip() {
    $ip_keys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
    foreach ($ip_keys as $key) {
        if (array_key_exists($key, $_SERVER) === true) {
            foreach (explode(',', $_SERVER[$key]) as $ip) {
                $ip = trim($ip);
                // Validate IP (prevent spoofing with private/reserved IPs if needed, but for logs usually fine)
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                    return $ip;
                }
            }
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'POST method required.']);
    exit;
}

$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);

if (empty($data['make']) || empty($data['model']) || empty($data['year'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Make, Model, and Year are required.']);
    exit;
}

// --- Configuration ---
$db_path = 'carsales.sqlite';

try {
    $db_dir = dirname($db_path);
    if (!is_dir($db_dir)) {
        mkdir($db_dir, 0755, true);
    }

    $pdo = new PDO('sqlite:' . $db_path);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // --- Ensure Table Exists (Matching your exact schema) ---
    // Added 'ip_address' to the creation script
    $pdo->exec("CREATE TABLE IF NOT EXISTS cars (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        sale_price INTEGER,
        status TEXT,
        make TEXT NOT NULL,
        model TEXT NOT NULL,
        year INTEGER NOT NULL,
        transmission TEXT,
        fuel TEXT,
        kilometers INTEGER,
        history_written_off INTEGER,
        is_as_is INTEGER,
        has_rego INTEGER,
        ip_address TEXT,
        submission_date DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // --- Automatic Migration: Add ip_address column if it doesn't exist ---
    // This prevents errors if you are running this on an existing database
    $columns_stmt = $pdo->query("PRAGMA table_info(cars)");
    $columns = $columns_stmt->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('ip_address', $columns)) {
        $pdo->exec("ALTER TABLE cars ADD COLUMN ip_address TEXT");
    }

    // --- Prepare Data ---
    $price_option = $data['price_option'] ?? 'show_price';
    $price = null;
    $status = 'AVAILABLE';

    if ($price_option === 'show_price') {
        $price = filter_var($data['price'], FILTER_VALIDATE_INT);
        $status = 'AVAILABLE';
    } elseif ($price_option === 'sold') {
        $status = 'SOLD';
        $price = 0; 
    } elseif ($price_option === 'not_for_sale') {
        $status = 'NOT FOR SALE';
        $price = 0;
    }

    // --- Insert Data ---
    // Added 'ip_address' to insert logic
    $stmt = $pdo->prepare(
        "INSERT INTO cars (
            make, model, year, sale_price, status, 
            transmission, fuel, kilometers, 
            history_written_off, is_as_is, has_rego, 
            ip_address, submission_date
        ) VALUES (
            :make, :model, :year, :sale_price, :status, 
            :transmission, :fuel, :kilometers, 
            :history, :as_is, :rego, 
            :ip_address, :submission_date
        )"
    );

    $stmt->bindValue(':make', $data['make']);
    $stmt->bindValue(':model', $data['model']);
    $stmt->bindValue(':year', filter_var($data['year'], FILTER_VALIDATE_INT));
    $stmt->bindValue(':sale_price', $price, PDO::PARAM_INT);
    $stmt->bindValue(':status', $status);
    $stmt->bindValue(':transmission', $data['transmission']);
    $stmt->bindValue(':fuel', $data['fuel']);
    $stmt->bindValue(':kilometers', filter_var($data['kms'], FILTER_VALIDATE_INT));
    
    // Checkboxes
    $stmt->bindValue(':history', isset($data['history']) ? 1 : 0, PDO::PARAM_INT);
    $stmt->bindValue(':as_is', isset($data['as_is']) ? 1 : 0, PDO::PARAM_INT);
    $stmt->bindValue(':rego', isset($data['rego']) ? 1 : 0, PDO::PARAM_INT);
    
    // ✅ Capture IP Address using logic to detect proxies
    $stmt->bindValue(':ip_address', get_client_ip());
    
    $stmt->bindValue(':submission_date', date('Y-m-d H:i:s'));

    $stmt->execute();
    $new_id = $pdo->lastInsertId();

    echo json_encode([
        'status' => 'success', 
        'message' => 'Car saved to carsales.sqlite successfully (ID: ' . $new_id . ').'
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'System error: ' . $e->getMessage()]);
}
?>
