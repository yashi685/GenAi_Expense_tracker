<?php
session_start();
include 'db.php'; // Ensure db.php is correctly configured

$message = ''; // Using $message for consistency with login.php

if (isset($_POST['register'])) {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);

    // Check if username or email already exists
    $check = $conn->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
    $check->bind_param("ss", $username, $email);
    $check->execute();
    $result = $check->get_result();

    if ($result->num_rows > 0) {
        $message = "Username or Email already taken!";
    } else {
        $stmt = $conn->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $username, $email, $password);
        if ($stmt->execute()) {
            $_SESSION['user_id'] = $stmt->insert_id;
            $_SESSION['username'] = $username;
            header("Location: index.php");
            exit();
        } else {
            $message = "Error registering user: " . $stmt->error;
        }
    }
    $check->close();
    if (isset($stmt)) { // Close $stmt only if it was prepared
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Register - Budget Tracker</title>
    <style>
        /* General body styling for background */
        body {
            font-family: Arial, sans-serif; /* Consistent with index.php and login.php */
            margin: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            /* Darker, more muted gradient relatable to sidebar background */
            background: linear-gradient(to right, #2c5364, #203a43, #0f2027);
            color: #333; /* Default text color */
            box-sizing: border-box; /* Ensure padding doesn't affect total width/height */
        }

        /* Container for the registration form */
        .container { /* Renamed from .login-container for clarity, but same styles */
            background-color: #ffffff; /* White background for the card */
            padding: 40px; /* Increased padding */
            border-radius: 8px; /* Consistent border-radius */
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2); /* Softer shadow */
            width: 100%;
            max-width: 400px; /* Max width for the form */
            text-align: center;
            box-sizing: border-box; /* Ensures padding doesn't increase total width */
        }

        h2 {
            color: #1a1a2e; /* Dark blue from your sidebar */
            margin-bottom: 25px; /* More space below heading */
            font-size: 24px;
        }

        input[type="text"],
        input[type="password"],
        input[type="email"] {
            width: calc(100% - 24px); /* Account for padding and border */
            padding: 12px; /* Increased padding for input fields */
            margin-bottom: 20px; /* More space below input fields */
            border: 1px solid #ccc;
            border-radius: 5px; /* Consistent border-radius */
            font-size: 16px;
            box-sizing: border-box; /* Include padding and border in the element's total width */
        }

        button {
            width: 100%;
            background-color: #1a1a2e; /* Dark blue from your sidebar */
            color: white;
            border: none;
            padding: 12px;
            border-radius: 5px; /* Consistent border-radius */
            font-weight: bold;
            cursor: pointer;
            font-size: 18px; /* Consistent button text size */
            transition: background-color 0.3s ease; /* Smooth hover effect */
        }

        button:hover {
            background-color: #16213e; /* Slightly darker on hover, consistent with index.php */
        }

        .message { /* Renamed from .error for general messages */
            color: red;
            text-align: center;
            margin-bottom: 15px; /* Space above form or link */
            font-size: 14px;
        }

        .login-link {
            text-align: center;
            margin-top: 25px; /* More space above link */
            font-size: 14px;
            color: #555; /* Softer grey */
        }

        .login-link a {
            color: #007bff; /* A standard blue for links, consistent with login.php */
            text-decoration: none;
            font-weight: bold;
            transition: color 0.3s ease;
        }

        .login-link a:hover {
            text-decoration: underline; /* Added underline on hover for clarity */
            color: #0056b3; /* Darker blue on hover */
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Welcome to Budget Tracker</h2>
        <?php if (!empty($message)) echo "<div class='message'>$message</div>"; ?>
        <form method="POST">
            <input type="text" name="username" placeholder="Create Username" required>
            <input type="email" name="email" placeholder="Enter Email" required>
            <input type="password" name="password" placeholder="Create Password" required>
            <button type="submit" name="register">Register</button>
        </form>
        <div class="login-link">
            Already have an account? <a href="login.php">Login here</a>
        </div>
    </div>
</body>
</html>