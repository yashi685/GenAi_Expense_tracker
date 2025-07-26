<?php
session_start();
include 'db.php'; // Make sure db.php correctly establishes $conn

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'User';

// Handle adding expense
if (isset($_POST['add'])) {
    $category = $_POST['category'];
    $amount = $_POST['amount'];
    $date = $_POST['expense_date'];
    $desc = $_POST['description'];

    $stmt = $conn->prepare("INSERT INTO expenses (category, amount, expense_date, description, user_id)
                             VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sdssi", $category, $amount, $date, $desc, $user_id);
    if ($stmt->execute()) {
        echo "<p style='color:green;'>Expense added successfully!</p>";
    } else {
        echo "<p style='color:red;'>Error adding expense: " . $stmt->error . "</p>";
    }
    $stmt->close();
}

// Fetch data for charts based on selected month or all time
$chartData = []; // This will be used by the JS for AI prompt and chart
$chartTitle = "All Time Expenses";
$selectedMonth = $_GET['chart_month'] ?? ''; // Get chart_month from GET request

if (!empty($selectedMonth)) {
    // Corrected SQL: Include 'month' in SELECT and GROUP BY
    $sql = "SELECT DATE_FORMAT(expense_date, '%Y-%m') AS month, category, SUM(amount) as total FROM expenses WHERE user_id = ? AND DATE_FORMAT(expense_date, '%Y-%m') = ? GROUP BY month, category";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $user_id, $selectedMonth);
    $chartTitle = "Expenses for " . date("F Y", strtotime($selectedMonth));
} else {
    // Corrected SQL: Include 'month' in SELECT and GROUP BY
    $sql = "SELECT DATE_FORMAT(expense_date, '%Y-%m') AS month, category, SUM(amount) as total FROM expenses WHERE user_id = ? GROUP BY month, category";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
}
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $chartData[] = $row;
}
$stmt->close();


// Fetch expenses for the filter table (using GET parameters)
$filter_query_parts = ["user_id = ?"];
$filter_params = [$user_id];
$filter_types = "i";

$month_filter = $_GET['month'] ?? '';
$category_filter = $_GET['category'] ?? '';

if (!empty($month_filter)) {
    $filter_query_parts[] = "DATE_FORMAT(expense_date, '%Y-%m') = ?";
    $filter_params[] = $month_filter;
    $filter_types .= "s";
}
if (!empty($category_filter)) {
    $filter_query_parts[] = "category = ?";
    $filter_params[] = $category_filter;
    $filter_types .= "s";
}

$filter_query_string = implode(" AND ", $filter_query_parts);
$stmt_expenses = $conn->prepare("SELECT expense_date, category, amount, description FROM expenses WHERE $filter_query_string ORDER BY expense_date DESC");
$stmt_expenses->bind_param($filter_types, ...$filter_params);
$stmt_expenses->execute();
$expenses_result = $stmt_expenses->get_result();
$expenses_for_table = $expenses_result->fetch_all(MYSQLI_ASSOC);
$stmt_expenses->close();

$total_filtered_expenses = array_sum(array_column($expenses_for_table, 'amount'));

// PHP-based initial suggestions (simple logic, before AI is explicitly requested)
$initial_suggestions = [];
$total_expenses_for_initial_suggestions = array_sum(array_column($chartData, 'total'));

if ($total_expenses_for_initial_suggestions > 0) {
    foreach ($chartData as $row) {
        $percent = ($row['total'] / $total_expenses_for_initial_suggestions) * 100;
        if ($percent > 40) {
            $initial_suggestions[] = "You're spending <b>" . number_format($percent, 2) . "%</b> on <b>{$row['category']}</b>. Consider looking for ways to reduce this expense.";
        } elseif ($percent < 5 && $row['total'] > 0) {
            $initial_suggestions[] = "Your spending on <b>{$row['category']}</b> is very low (" . number_format($percent, 2) . "%). Ensure all expenses in this category are being tracked.";
        }
    }
}

if (empty($initial_suggestions) && $total_expenses_for_initial_suggestions > 0) {
    $initial_suggestions[] = "Your expenses appear well-balanced across categories! Keep up the great tracking! 🎯";
} elseif (empty($initial_suggestions) && $total_expenses_for_initial_suggestions == 0) {
    $initial_suggestions[] = "No expenses recorded yet for this period. Start adding expenses to see insights!";
}

// Process chart data for JavaScript (for the Chart.js chart)
$labels_for_js = [];
$grouped_for_js = [];
foreach ($chartData as $cd) { // Use $chartData which is fetched for the chart
    $labels_for_js[$cd['month']] = true; // Collect unique months for labels
    $grouped_for_js[$cd['category']][$cd['month']] = $cd['total']; // Group by category and month
}
$chart_labels_json = json_encode(array_keys($labels_for_js));

$chart_datasets_json = [];
$chart_colors = [
    'Food'=>'rgba(255, 99, 132, 0.6)',
    'Travel'=>'rgba(54, 162, 235, 0.6)',
    'Shopping'=>'rgba(255, 206, 86, 0.6)',
    'Health'=>'rgba(75, 192, 192, 0.6)',
    'Other'=>'rgba(153, 102, 255, 0.6)'
];

foreach ($grouped_for_js as $cat => $months) {
    $data_points = [];
    foreach (array_keys($labels_for_js) as $m) {
        $data_points[] = (float)($months[$m] ?? 0); // Ensure float and handle missing months
    }
    $chart_datasets_json[] = [
        "label" => $cat,
        "data" => $data_points,
        "backgroundColor" => $chart_colors[$cat] ?? 'rgba(128, 128, 128, 0.6)', // Default gray
        "borderColor" => str_replace('0.6', '1', $chart_colors[$cat] ?? 'rgba(128, 128, 128, 1)'),
        "borderWidth" => 1
    ];
}
$chart_datasets_json_encoded = json_encode($chart_datasets_json);

$conn->close();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Expense Tracker</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
        }
        .sidebar {
            height: 100vh;
            width: 220px;
            position: fixed;
            left: 0;
            top: 0;
            background: #1a1a2e;
            color: #fff;
            padding-top: 60px;
        }
        .sidebar h3 {
            text-align: center;
            margin-bottom: 30px;
        }
        .sidebar a {
            display: block;
            padding: 12px 20px;
            color: #fff;
            text-decoration: none;
            border-bottom: 1px solid #333;
        }
        .sidebar a:hover {
            background-color: #16213e;
        }
        .header-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 30px;
            background-color: #f0f0f0;
            margin-left: 220px;
        }
        .profile-pic img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
        }
        .welcome-text h2 {
            font-size: 1.2em;
            font-weight: bold;
            color: #333;
            margin: 0;
        }
        .logout-btn a {
            color: #333;
            text-decoration: none;
            font-weight: bold;
        }
        .container {
            margin-left: 220px;
            padding: 20px;
        }
        .section {
            display: none;
        }
        .section.active {
            display: block;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid #ccc;
            padding: 8px;
            text-align: left;
        }
        form label {
            display: block;
            margin-top: 10px;
            margin-bottom: 5px;
        }
        form input[type="number"],
        form input[type="date"],
        form select,
        form textarea,
        form input[type="month"] {
            width: calc(100% - 22px);
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
        }
        form button {
            background-color: #1a1a2e;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        form button:hover {
            background-color: #16213e;
        }
        canvas {
            max-width: 100%;
            height: auto;
        }

        /* Styles for the charts section layout */
        .charts-content-wrapper {
            display: flex;
            flex-wrap: wrap;
            gap: 30px;
            margin-top: 20px;
            align-items: flex-start;
        }

        .chart-column {
            flex: 2;
            min-width: 300px;
        }

        .suggestion-column {
            flex: 1;
            padding: 20px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            background-color: #fefefe;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            min-width: 250px;
        }

        .suggestion-column h4 {
            color: #1a1a2e;
            margin-top: 0;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .suggestion-column ul {
            list-style: disc;
            padding-left: 20px;
            margin: 0;
        }

        .suggestion-column li {
            margin-bottom: 10px;
            line-height: 1.4;
            color: #444;
        }

        .suggestion-column li:last-child {
            margin-bottom: 0;
        }

        .suggestion-column p {
            color: #444;
            line-height: 1.4;
        }

        /* Styles for AI button and loading */
        .ai-button-container {
            margin-top: 20px;
            text-align: center;
        }
        .ai-button {
            background-color: #28a745;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.3s ease;
        }
        .ai-button:hover {
            background-color: #218838;
        }
        .loading-indicator {
            display: none;
            margin-top: 10px;
            font-style: italic;
            color: #666;
        }
    </style>
</head>
<body>

<div class="sidebar">
    <h3>Expense Menu</h3>
    <a href="#" onclick="showSection('overview')">1. Overview</a>
    <a href="#" onclick="showSection('filter')">2. Filter Records</a>
    <a href="#" onclick="showSection('charts')">3. Charts</a>
</div>

<div class="header-bar">
    <div class="profile-pic">
        <img src="user.png" alt="Profile Picture">
    </div>
    <div class="welcome-text">
        <h2>Welcome <?php echo $username; ?></h2>
    </div>
    <div class="logout-btn">
        <a href="logout.php">Logout</a>
    </div>
</div>

<div class="container">
    <div id="overview" class="section active">
        <h3>Add Expense</h3>
        <form method="POST">
            <label for="category">Category</label>
            <select name="category" id="category" required>
                <option value="Food">Food</option>
                <option value="Travel">Travel</option>
                <option value="Shopping">Shopping</option>
                <option value="Health">Health</option>
                <option value="Other">Other</option>
            </select>

            <label for="amount">Amount (₹)</label>
            <input type="number" name="amount" id="amount" step="0.01" required>

            <label for="expense_date">Date</label>
            <input type="date" name="expense_date" id="expense_date" required>

            <label for="description">Description</label>
            <textarea name="description" id="description"></textarea>

            <button type="submit" name="add">Add Expense</button>
        </form>
    </div>

    <div id="filter" class="section">
        <h3>Filter Expenses</h3>
        <form method="GET" id="filterForm">
            <label for="filter_category">Category</label>
            <select name="category" id="filter_category">
                <option value="">All</option>
                <option value="Food" <?php echo ($category_filter == 'Food') ? 'selected' : ''; ?>>Food</option>
                <option value="Travel" <?php echo ($category_filter == 'Travel') ? 'selected' : ''; ?>>Travel</option>
                <option value="Shopping" <?php echo ($category_filter == 'Shopping') ? 'selected' : ''; ?>>Shopping</option>
                <option value="Health" <?php echo ($category_filter == 'Health') ? 'selected' : ''; ?>>Health</option>
                <option value="Other" <?php echo ($category_filter == 'Other') ? 'selected' : ''; ?>>Other</option>
            </select>

            <label for="filter_month">Month</label>
            <input type="month" name="month" id="filter_month" value="<?php echo $month_filter; ?>">

            <button type="submit">Filter</button>
        </form>

        <table>
            <tr>
                <th>Date</th>
                <th>Category</th>
                <th>Amount (₹)</th>
                <th>Description</th>
            </tr>
            <?php
            // Display filtered expenses
            if (!empty($expenses_for_table)) {
                foreach ($expenses_for_table as $exp) {
                    echo "<tr>
                                <td>{$exp['expense_date']}</td>
                                <td>{$exp['category']}</td>
                                <td>₹" . number_format($exp['amount'], 2) . "</td>
                                <td>{$exp['description']}</td>
                              </tr>";
                }
            } else {
                echo "<tr><td colspan='4'>No expenses found for the selected criteria.</td></tr>";
            }
            ?>
            <tr>
                <th colspan="2">Total</th>
                <th colspan="2">₹<?php echo number_format($total_filtered_expenses, 2); ?></th>
            </tr>
        </table>
    </div>

    <div id="charts" class="section">
        <h3>Expense Summary by Category</h3>

        <form method="GET" id="chartFilterForm" onsubmit="showSection('charts');">
            <label for="chart_month">Select Month</label>
            <input type="month" name="chart_month" id="chart_month" value="<?php echo $selectedMonth; ?>">
            <button type="submit">Show Chart</button>
        </form>

        <div class="charts-content-wrapper">
            <div class="chart-column">
                <canvas id="expenseChart" width="600" height="400"></canvas>
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        const ctx = document.getElementById('expenseChart').getContext('2d');
                        const chartData = {
                            labels: <?php echo $chart_labels_json; ?>,
                            datasets: <?php echo $chart_datasets_json_encoded; ?>
                        };

                        console.log("Chart Data for Chart.js:", chartData);

                        // Check if there's actual data to render the chart
                        const hasData = chartData.datasets.some(dataset => dataset.data.some(value => value > 0));

                        if (chartData.labels.length === 0 || !hasData) {
                            // Clear canvas and display message if no data
                            ctx.clearRect(0, 0, ctx.canvas.width, ctx.canvas.height);
                            ctx.font = "18px Arial";
                            ctx.fillStyle = "#666";
                            ctx.textAlign = "center";
                            ctx.fillText("No chart data available for this period.", ctx.canvas.width / 2, ctx.canvas.height / 2);
                        } else {
                            new Chart(ctx, {
                                type: 'bar',
                                data: chartData,
                                options: {
                                    responsive: true,
                                    plugins: {
                                        title: {
                                            display: true,
                                            text: <?php echo json_encode($chartTitle); ?>
                                        }
                                    },
                                    scales: {
                                        y: {
                                            beginAtZero: true
                                        }
                                    }
                                }
                            });
                        }
                    });
                </script>
            </div>

            <div class="suggestion-column">
                <h4>💡 Budget Suggestions</h4>
                <div id="aiSuggestionsContent">
                    <ul>
                        <?php foreach ($initial_suggestions as $s): ?>
                            <li><?php echo $s; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="ai-button-container">
                    <button class="ai-button" onclick="getAiBudgetAdvice()">Get AI Budget Advice</button>
                    <div class="loading-indicator" id="aiLoading">Generating advice...</div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function showSection(id) {
        const sections = document.querySelectorAll('.section');
        sections.forEach(sec => sec.classList.remove('active'));
        document.getElementById(id).classList.add('active');

        // Sync month selection between filter and charts when switching sections
        const chartForm = document.getElementById('chartFilterForm');
        const filterForm = document.getElementById('filterForm');

        if (id === 'filter') {
            const chartMonth = chartForm.elements['chart_month'].value;
            if (chartMonth) {
                filterForm.elements['month'].value = chartMonth;
            }
        } else if (id === 'charts') {
            const filterMonth = filterForm.elements['month'].value;
            if (filterMonth) {
                chartForm.elements['chart_month'].value = filterMonth;
            }
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('category') || urlParams.has('month')) {
            showSection('filter');
        } else if (urlParams.has('chart_month')) {
            showSection('charts');
        } else {
            showSection('overview');
        }

        // Hide loading indicator on initial page load
        const aiLoading = document.getElementById('aiLoading');
        if (aiLoading) {
            aiLoading.style.display = 'none';
        }
    });

    // This function now calls your server-side get_ai_advice.php
    async function getAiBudgetAdvice() {
        const aiLoading = document.getElementById('aiLoading');
        const aiSuggestionsContent = document.getElementById('aiSuggestionsContent');
        aiSuggestionsContent.innerHTML = ''; // Clear previous suggestions
        aiLoading.style.display = 'block';

        // Prepare the data to send to the server-side AI endpoint
        // These variables are populated by PHP on page load
        const chartData = <?php echo json_encode($chartData); ?>;
        const username = <?php echo json_encode($username); ?>;
        const selectedMonth = <?php echo json_encode($selectedMonth); ?>;

        const requestData = {
            chartData: chartData,
            username: username,
            selectedMonth: selectedMonth
        };

        try {
            const response = await fetch('get_ai_advice.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(requestData)
            });

            const result = await response.json();

            if (response.ok && result.success) { // Check both HTTP status and custom success flag
                const text = result.advice;
                // Basic formatting for bullet points.
                // The AI model's output might require more sophisticated parsing.
                // Replace potential newlines with <br> for better display in HTML list items.
                const formattedText = text.replace(/^- (.*)/gm, '<li>$1</li>').replace(/\n/g, '<br>');
                aiSuggestionsContent.innerHTML = `<ul>${formattedText}</ul>`;
            } else {
                // Display error message from the PHP script
                aiSuggestionsContent.innerHTML = `<p style="color:red;">${result.message || 'An unknown error occurred.'}</p>`;
                console.error('AI response error:', result.message, result.details);
            }
        } catch (error) {
            aiSuggestionsContent.innerHTML = '<p style="color:red;">Error connecting to AI. Please check your network or try again later.</p>';
            console.error('Error fetching AI advice (client-side):', error);
        } finally {
            aiLoading.style.display = 'none';
        }
    }
</script>

</body>
</html>