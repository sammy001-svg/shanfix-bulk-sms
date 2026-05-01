<?php
$pdo = new PDO('mysql:host=localhost;dbname=bulk_sms_system', 'root', '');
$stmt = $pdo->query('DESCRIBE users');
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    print_r($row);
}
