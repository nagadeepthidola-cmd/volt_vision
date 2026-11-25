<?php
require_once "config/db.php";

echo "Connected Database: ";

$result = $conn->query("SELECT DATABASE()");

$row = $result->fetch_row();

echo $row[0];

echo "<br><br>";

$result = $conn->query("SHOW TABLES LIKE 'predictions'");

if ($result && $result->num_rows > 0) {
    echo "Predictions table EXISTS";
} else {
    echo "Predictions table NOT FOUND";
}
?>