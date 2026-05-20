<?php
require 'conn.php';

function getAllUsers($conn) {

    $result = $conn->query("
        SELECT 
            reg_id,
            name,
            email,
            email_verified_at,
            status,
            created_at
        FROM users
    ");

    $users = [];

    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }

    return $users;
}
?>