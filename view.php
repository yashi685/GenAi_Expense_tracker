<?php
include 'db.php';

// Dummy salary
$salary = 50000;

// Fetch all expenses grouped by month & category
$sql = "SELECT category, MONTH(date) AS month, SUM(amount) AS total FROM expenses GROUP BY category, month";
$result = $conn->query($sql);

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[$row['month']][$row['category']] = $row['total'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Expense Tracker</title>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    * {
      box-sizing: border-box;
    }
    body {
      margin: 0;
      font-family: 'Segoe UI', sans-serif;
      background: #f0f2f5;
      display: flex;
    }

    .sidebar {
      width: 240px;
      background: #2c3e50;
      color: white;
      height: 100vh;
      position: fixed;
      padding: 20px;
    }

    .sidebar h2 {
      color: #fff;
    }

    .sidebar a {
      color: #ecf0f1;
      display: block;
      padding: 10px;
      margin: 10px 0;
      border-radius: 5px;
      text-decoration: none;
    }

    .sidebar a:hover {
      background-color: #34495e;
    }

    .main {
      margin-left: 260px;
      padding: 30px;
      width: 100%;
    }

    .card {
      background: white;
      padding: 20px;
      margin-bottom: 25px;
      border-radius: 8px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }

    form {
      margin-top: 15px;
    }

    label, select {
      margin-right: 10px;
    }

    canvas {
      width: 100% !important;
      max-width: 800px;
    }
  </style>
</head>
<body>

  <!-- Sidebar -->
  <div class="sidebar">
    <h2>📊 Menu</h2>
    <a href="#dashboard">1. Dashboard</a>
    <a href="#filters">2. Filter Records</a>
    <a href="#charts">3. Charts</a>
  </div>

  <!-- Main Content -->
  <div class="main">

    <!-- Section 1: Dashboard -->
    <div id="dashboard" class="card">
      <h2>💰 Dashboard</h2>
      <p><strong>Total Salary:</strong> ₹<?php echo $salary; ?></p>

      <h3>Category-wise Expense</h3>
      <ul>
        <?php
        $query = "SELECT category, SUM(amount) AS total FROM expenses GROUP BY category";
        $res = $conn->query($query);
        while ($row = $res->fetch_assoc()) {
            echo "<li>{$row['category']}: ₹{$row['total']}</li>";
        }
        ?>
      </ul>
    </div>

    <!-- Section 2: Filters -->
    <div id="filters" class="card">
      <h2>📅 Filter Expenses</h2>
      <form method="GET">
        <label>Month:</label>
        <select name="month">
          <option value="">All</option>
          <?php for ($m = 1; $m <= 12; $m++) echo "<option value='$m'>$m</option>"; ?>
        </select>

        <label>Category:</label>
        <select name="category">
          <option value="">All</option>
          <?php
          $cat = $conn->query("SELECT DISTINCT category FROM expenses");
          while ($row = $cat->fetch_assoc()) {
              echo "<option value='{$row['category']}'>{$row['category']}</option>";
          }
          ?>
        </select>

        <button type="submit">Apply</button>
      </form>

      <?php
      if (!empty($_GET['month']) || !empty($_GET['category'])) {
          $month = $_GET['month'];
          $category = $_GET['category'];
          $condition = "1=1";
          if ($month) $condition .= " AND MONTH(date) = $month";
          if ($category) $condition .= " AND category = '$category'";

          $sql = "SELECT * FROM expenses WHERE $condition ORDER BY date DESC";
          $res = $conn->query($sql);
          echo "<h3>Filtered Results:</h3><ul>";
          while ($r = $res->fetch_assoc()) {
              echo "<li>{$r['date']} | ₹{$r['amount']} | {$r['category']}</li>";
          }
          echo "</ul>";
      }
      ?>
    </div>

    <!-- Section 3: Charts -->
    <div id="charts" class="card">
      <h2>📈 Monthly Expense Chart</h2>
      <canvas id="expenseChart"></canvas>
    </div>

  </div>

  <!-- Chart Script -->
  <script>
    const ctx = document.getElementById('expenseChart').getContext('2d');

    const chartData = {
      labels: [<?php echo implode(',', array_map(fn($m) => "'Month $m'", range(1, 12))); ?>],
      datasets: [
        <?php
        $allCats = [];
        foreach ($data as $month => $cats) {
            foreach ($cats as $cat => $amt) {
                $allCats[$cat][$month] = $amt;
            }
        }

        $colors = ['#e74c3c','#3498db','#2ecc71','#f39c12','#9b59b6','#1abc9c'];
        $i = 0;
        foreach ($allCats as $cat => $months) {
            echo "{ label: '$cat', data: [";
            for ($m = 1; $m <= 12; $m++) {
                echo $months[$m] ?? 0;
                echo ($m != 12) ? "," : "";
            }
            echo "], backgroundColor: '{$colors[$i % count($colors)]}' },";
            $i++;
        }
        ?>
      ]
    };

    new Chart(ctx, {
      type: 'bar',
      data: chartData,
      options: {
        responsive: true,
        plugins: {
          legend: { position: 'top' },
          title: { display: true, text: 'Monthly Expenses by Category' }
        }
      }
    });
  </script>
</body>
</html>
