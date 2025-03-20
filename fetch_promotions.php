<?php
$apiUrl = "https://lidl.p.rapidapi.com/searchByURL";
$apiKey = "3f83bd8354msh259cf3f64fbb110p1b64b5jsn628f6a461acc"; // Replace with your actual API key

// Initialize cURL request
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "x-rapidapi-host: lidl.p.rapidapi.com",
    "x-rapidapi-key: $apiKey",
    "Content-Type: application/json"
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Check if API request was successful
if ($httpCode !== 200) {
    die("API request failed. HTTP Code: $httpCode");
}

// Decode API response
$promotions = json_decode($response, true);
if (!$promotions) {
    die("Error fetching promotions. API returned invalid JSON.");
}

// Database connection
$servername = "database-1.cluster-cgnbwogrvdhe.eu-central-1.rds.amazonaws.com";
$username = "impairedasp5640";
$password = ">)0qTEMVW(19#)ez|2WF8RhupP21";
$dbname = "lidl_promotions";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// Ensure the table has the correct columns
//$alterTableQuery = "ALTER TABLE promotions 
   // ADD COLUMN IF NOT EXISTS stock VARCHAR(50)";
//$conn->query($alterTableQuery);

// Insert or update promotions
echo $promotions;
foreach ($promotions as $promo) {
    $title = $conn->real_escape_string($promo['title']);
    $price = $conn->real_escape_string($promo['price']);
    $image = $conn->real_escape_string($promo['image']);
    $valid_from = $conn->real_escape_string($promo['valid_from']);
    $valid_to = $conn->real_escape_string($promo['valid_to']);
    $stock = isset($promo['stock']) ? $conn->real_escape_string($promo['stock']) : 'Unknown';
    
    $sql = "INSERT INTO promotions (title, price, image, valid_from, valid_to, stock)
            VALUES ('$title', '$price', '$image', '$valid_from', '$valid_to', '$stock')
            ON DUPLICATE KEY UPDATE 
            price='$price', image='$image', valid_from='$valid_from', valid_to='$valid_to', stock='$stock'";

    if (!$conn->query($sql)) {
        die("Database error: " . $conn->error);
    }
}

$conn->close();

echo "Promotions updated successfully.";
?>
