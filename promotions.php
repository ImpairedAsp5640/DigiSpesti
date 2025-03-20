<?php
$servername = "database-1.cluster-cgnbwogrvdhe.eu-central-1.rds.amazonaws.com";
$username = "impairedasp5640";
$password = ">)0qTEMVW(19#)ez|2WF8RhupP21";
$dbname = "lidl_promotions";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

$sql = "SELECT * FROM promotions ORDER BY valid_from DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lidl Promotions</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <h1>Lidl Product Promotions</h1>
    <div class="promotions">
        <?php if ($result->num_rows > 0) : ?>
            <?php while ($row = $result->fetch_assoc()) : ?>
                <div class="promotion">
                    <img src="<?php echo htmlspecialchars($row['image']); ?>" alt="<?php echo htmlspecialchars($row['title']); ?>">
                    <h2><?php echo htmlspecialchars($row['title']); ?></h2>
                    <p>Price: ˆ<?php echo htmlspecialchars($row['price']); ?></p>
                    <p>Valid From: <?php echo htmlspecialchars($row['valid_from']); ?> to <?php echo htmlspecialchars($row['valid_to']); ?></p>
                    <p>Stock: <?php echo htmlspecialchars($row['stock']); ?></p>
                </div>
            <?php endwhile; ?>
        <?php else : ?>
            <p>No promotions available at the moment.</p>
        <?php endif; ?>
    </div>
</body>
</html>

<?php $conn->close(); ?>
