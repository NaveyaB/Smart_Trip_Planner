<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

include "db.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Get form data
    $username = trim($_POST["username"]);
    $email = trim($_POST["email"]);
    $password = $_POST["password"];
    $confirm_password = $_POST["confirm_password"];


    // Check password
    if ($password !== $confirm_password) {
        die("Passwords do not match!");
    }


    // Check email already exists
    $check = $conn->prepare(
        "SELECT id FROM users WHERE email = ?"
    );

    if (!$check) {
        die("Database Error: " . $conn->error);
    }

    $check->bind_param("s", $email);
    $check->execute();

    $result = $check->get_result();


    if ($result->num_rows > 0) {
        die("Email already registered!");
    }


    // Hash password
    $hashed_password = password_hash(
        $password,
        PASSWORD_DEFAULT
    );


    // Insert user
    $sql = "INSERT INTO users (username, email, password)
            VALUES (?, ?, ?)";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        die("Database Error: " . $conn->error);
    }

    $stmt->bind_param(
        "sss",
        $username,
        $email,
        $hashed_password
    );


    // Register user
    if ($stmt->execute()) {

        // Create session
        $_SESSION["user_id"] = $stmt->insert_id;
        $_SESSION["username"] = $username;
        $_SESSION["email"] = $email;


        // Go automatically to dashboard
        header("Location: dashboard.php");
        exit();

    } else {

        die("Registration Failed: " . $stmt->error);

    }


    $stmt->close();
    $check->close();
    $conn->close();

} else {

    die("Invalid Request!");

}

?>