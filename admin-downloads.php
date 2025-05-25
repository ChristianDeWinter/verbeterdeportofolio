<?php
session_start();
require('../fpdf/fpdf.php'); // Adjust the path if needed
include "../db/conn.php"; // Database Connection

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../inloggen.php");
    exit();
}

// session data
$user_id = intval($_SESSION['user_id']);
$user_name = htmlspecialchars($_SESSION['user'], ENT_QUOTES, 'UTF-8');

// Function to generate PDF
function generatePDF($pdo, $user_id) {
    $sql = "SELECT date, hours FROM hours WHERE user_id = :user_id AND MONTH(date) = MONTH(CURDATE()) AND YEAR(date) = YEAR(CURDATE()) ORDER BY date ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':user_id' => $user_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $pdf = new FPDF();
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->Cell(0, 10, 'Maandelijkse Urenoverzicht', 0, 1, 'C');
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(0, 10, 'Gebruiker: ' . htmlspecialchars($_SESSION['user']), 0, 1);

    $pdf->Cell(40, 10, 'Datum', 1);
    $pdf->Cell(40, 10, 'Uren', 1);
    $pdf->Ln();

    $total_hours = 0;
    foreach ($rows as $row) {
        $pdf->Cell(40, 10, $row['date'], 1);
        $pdf->Cell(40, 10, $row['hours'], 1);
        $pdf->Ln();
        $total_hours += $row['hours'];
    }

    $pdf->Ln();
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(40, 10, 'Totaal Uren:', 1);
    $pdf->Cell(40, 10, $total_hours, 1);

    $pdf->Output('D', 'Maandelijkse_Urenoverzicht.pdf');
}

// Handle PDF download request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['download_pdf'])) {
    generatePDF($pdo, $user_id);
    exit();
}

// Fetch users with the role 'user', ensuring no duplicates by combining username and user_id
$sql = "SELECT DISTINCT users.user_id, users.name, users.role FROM users 
        LEFT JOIN hours ON users.user_id = hours.user_id 
        WHERE users.role = 'user'";  // Filter only users with role 'user'
$stmt = $pdo->prepare($sql);
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maandelijkse Uren Download</title>
    <link rel="stylesheet" href="admin-index.css">
</head>
<body>
<div class="container">
    <div class="sidebar">
        <div class="menu-item">Dashboard</div>
        <div class="menu-item active">Download</div>
        <div class="menu-item">Gebruikers</div>
        <div class="menu-item">Uitloggen</div>
    </div>
    <div class="content">
        <h1>Download Maandelijkse Uren (PDF)</h1>

        <table>
            <tr>
                <th>Naam</th>
                <th>Download</th>
            </tr>
            <?php foreach ($users as $user): ?>
                <?php
                // Check if the user has hours for the current month
                $hour_check_sql = "SELECT COUNT(*) FROM hours WHERE user_id = :user_id AND MONTH(date) = MONTH(CURDATE()) AND YEAR(date) = YEAR(CURDATE())";
                $hour_check_stmt = $pdo->prepare($hour_check_sql);
                $hour_check_stmt->execute([':user_id' => $user['user_id']]);
                $hour_check = $hour_check_stmt->fetchColumn();
                ?>
                <tr>
                    <td><?= htmlspecialchars($user['name']) ?></td>
                    <td>
                        <?php if ($hour_check > 0): ?>
                            <form method="POST">
                                <input type="hidden" name="download_pdf" value="1">
                                <button type="submit">✔️ Download PDF</button>
                            </form>
                        <?php else: ?>
                            <span>No hours made</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
</div>
</body>
</html>