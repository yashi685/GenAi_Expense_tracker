<?php
session_start();
include 'db.php'; // Ensure db.php is correctly configured

$message = ''; // Renamed $error to $message for consistency with my previous example

if (isset($_POST['login'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Prepare SQL statement to prevent SQL injection
    $stmt = $conn->prepare("SELECT id, username, password FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        if (password_verify($password, $row['password'])) {
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['username'] = $row['username']; // Store username in session
            header("Location: index.php"); // Redirect to the main expense tracker page
            exit();
        } else {
            $message = "Invalid password.";
        }
    } else {
        $message = "User not found.";
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login - Budget Tracker</title>
    <style>
        /* General body styling for background */
        body {
            font-family: Arial, sans-serif; /* Consistent with index.php */
            margin: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            /* Darker, more muted gradient relatable to sidebar background */
            background: linear-gradient(to right, #2c5364, #203a43, #0f2027);
            color: #333; /* Default text color */
        }

        /* Container for the login form */
        .login-container {
            background-color: #ffffff; /* White background for the card */
            padding: 40px; /* Increased padding */
            border-radius: 8px; /* Slightly less aggressive border-radius */
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2); /* Softer shadow */
            width: 100%;
            max-width: 400px; /* Max width for the form */
            text-align: center;
            box-sizing: border-box; /* Ensures padding doesn't increase total width */
        }

        h2 {
            color: #1a1a2e; /* Dark blue from your sidebar */
            margin-bottom: 10px;
            font-size: 24px; /* Slightly larger heading */
        }

        .subtitle {
            text-align: center;
            font-size: 15px; /* Slightly larger subtitle */
            color: #555; /* Softer grey */
            margin-bottom: 25px; /* More space below subtitle */
        }

        label {
            display: block;
            text-align: left;
            margin-bottom: 8px; /* More space above input */
            margin-top: 15px; /* Space between fields */
            font-weight: bold;
            color: #333;
        }

        input[type="text"], input[type="password"] {
            width: calc(100% - 24px); /* Account for padding and border */
            padding: 12px; /* Increased padding for input fields */
            margin-top: 0px; /* Remove default margin-top from label */
            margin-bottom: 20px; /* More space below input fields */
            border-radius: 5px;
            border: 1px solid #ccc;
            font-size: 16px;
            box-sizing: border-box; /* Include padding and border in the element's total width */
        }

        button {
            margin-top: 20px;
            width: 100%;
            background-color: #1a1a2e; /* Dark blue from your sidebar */
            color: white;
            padding: 12px;
            font-size: 18px; /* Slightly larger button text */
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s ease; /* Smooth hover effect */
        }

        button:hover {
            background-color: #16213e; /* Slightly darker on hover, consistent with index.php */
        }

        .message { /* Renamed from .error for general messages */
            margin-top: 15px;
            color: red;
            text-align: center;
            font-size: 14px;
        }

        .register-link {
            text-align: center;
            margin-top: 25px; /* More space above link */
            font-size: 14px;
            color: #555; /* Softer grey */
        }

        .register-link a {
            color: #007bff; /* A standard blue for links, more visible than the dark theme color for links */
            text-decoration: none;
            font-weight: bold;
            transition: color 0.3s ease;
        }

        .register-link a:hover {
            text-decoration: underline; /* Added underline on hover for clarity */
            color: #0056b3; /* Darker blue on hover */
        }
    </style>
</head>
<body>
<div class="login-container">
    <h2>Welcome to Budget Tracker</h2>
    <div class="subtitle">Login to manage your expenses</div>
    <form method="POST">
        <label for="username">Username</label>
        <input type="text" id="username" name="username" required>

        <label for="password">Password</label>
        <input type="password" id="password" name="password" required>

        <button type="submit" name="login">Login</button>

        <?php if (!empty($message)) echo "<div class='message'>$message</div>"; ?>
    </form>
    <div class="register-link">
        Don't have an account? <a href="register.php">Register here</a>
    </div>
</div>
</body>
</html>