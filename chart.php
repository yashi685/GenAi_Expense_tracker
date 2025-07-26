<?php
// Start session and connect DB
session_start();
include 'db.php';

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get total expenses grouped by month and category
$query = "SELECT 
            DATE_FORMAT(expense_date, '%Y-%m') as month,
            category,
            SUM(amount) as total 
          FROM expenses 
          WHERE user_id = $user_id 
          GROUP BY month, category 
          ORDER BY month";

$result = $conn->query($query);

// Prepare data
$data = [];
$all_months = [];

while ($row = $result->fetch_assoc()) {
    $month = $row['month'];
    $category = $row['category'];
    $amount = $row['total'];

    $data[$category][$month] = $amount;
    $all_months[$month] = true;
}

$all_months = array_keys($all_months);
sort($all_months); // e.g., ['2025-06', '2025-07']
?>

<!DOCTYPE html>
<html>
<head>
    <title>Charts</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <h2>📊 Expense Comparison Chart</h2>
    <canvas id="expenseChart" width="800" height="400"></canvas>

    <script>
        const ctx = document.getElementById('expenseChart').getContext('2d');

        const chartData = {
            labels: <?php echo json_encode($all_months); ?>,
            datasets: [
                <?php foreach ($data as $category => $months): ?>
                {
                    label: "<?php echo $category; ?>",
                    data: [
                        <?php
                        foreach ($all_months as $month) {
                            echo isset($months[$month]) ? $months[$month] . ',' : '0,';
                        }
                        ?>

