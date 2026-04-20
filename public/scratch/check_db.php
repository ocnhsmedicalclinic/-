<?php
require_once "c:/xampp/htdocs/clinic-system/config/db.php";
$res = $conn->query("DESC inventory_transactions");
while($row = $res->fetch_assoc()) {
    echo $row['Field'] . "\n";
}
?>
