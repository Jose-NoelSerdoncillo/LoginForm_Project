<?php
session_start();
require 'dashboard.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: loginform.php");
    exit();
}

$users = getAllUsers($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link rel="stylesheet" href="style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        /* Force full dark mode style rules over external sheets */
        html, body {
            background: #0a0a0a !important;
            color: #ffffff !important;
            font-family: 'DM Sans', sans-serif;
            padding: 2.5rem;
            margin: 0;
            min-height: 100vh;
        }
        
        /* Dashboard Header Layout Wrapper */
        .dashboard-header {
            display: flex !important;
            justify-content: space-between !important;
            align-items: center !important;
            margin-bottom: 2rem !important;
            padding-bottom: 1rem !important;
            border-bottom: 1px solid #222222 !important;
        }

        .dashboard-header h2 {
            font-family: 'Rajdhani', sans-serif !important;
            font-size: 2rem !important;
            font-weight: 700 !important;
            text-transform: uppercase !important;
            letter-spacing: 0.04em !important;
            color: #ffffff !important;
            margin: 0 !important;
            display: block !important;
        }

        /* Highly Visible Custom Accent Crimson Red Logout Button */
        .btn-logout {
            padding: 10px 20px !important;
            background: #d4382a !important; 
            color: #ffffff !important;
            text-decoration: none !important;
            font-family: 'Rajdhani', sans-serif !important;
            font-size: 0.9rem !important;
            font-weight: 700 !important;
            letter-spacing: 0.08em !important;
            text-transform: uppercase !important;
            border-radius: 3px !important;
            transition: background 0.15s ease !important;
            display: inline-flex !important;
            align-items: center !important;
            z-index: 9999 !important;
        }
        
        .btn-logout:hover {
            background: #b22d21 !important;
        }

        /* Seamless Dark Table Interface Injection */
        table {
            border-collapse: collapse !important;
            width: 100% !important;
            background: #0d0d0d !important;
            box-shadow: none !important;
            border: 1px solid #1a1917 !important;
        }
        
        th, td {
            border: 1px solid #1a1917 !important;
            padding: 14px 16px !important;
            text-align: left !important;
            font-size: 0.85rem !important;
            color: #ffffff !important;
        }
        
        th {
            background: #151515 !important;
            color: #9e9c98 !important;
            font-family: 'Rajdhani', sans-serif !important;
            font-weight: 600 !important;
            text-transform: uppercase !important;
            letter-spacing: 0.06em !important;
            border-bottom: 2px solid #222222 !important;
        }

        tr:nth-child(even) {
            background: #111111 !important;
        }

        tr:hover td {
            background: #161616 !important;
        }
    </style>
</head>
<body>

<div class="dashboard-header">
    <h2>Users List</h2>
    <a href="logout.php" class="btn-logout">Logout →</a>
</div>

<table>
    <thead>
        <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Email</th>
            <th>Verified</th>
            <th>Status</th>
            <th>Created</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($users as $user): ?>
        <tr>
            <td><?= htmlspecialchars($user['reg_id']) ?></td>
            <td><?= htmlspecialchars($user['name']) ?></td>
            
            <td><?= htmlspecialchars(decryptData($user['email'])) ?></td>
            
            <td><?= htmlspecialchars($user['email_verified_at'] ?? 'Not Verified') ?></td>
            <td><?= htmlspecialchars($user['status']) ?></td>
            <td><?= htmlspecialchars($user['created_at']) ?></td>
        </tr>
    <?php endforeach; ?>
</tbody>
</table>

</body>
</html>