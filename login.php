<?php

session_start();

include "db.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $login = trim($_POST["email"]);
    $password = $_POST["password"];


    // Check empty fields
    if (empty($login) || empty($password)) {
        die("Please enter username/email and password!");
    }


    // Find user by username OR email
    $sql = "SELECT * FROM users WHERE username = ? OR email = ?";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        die("Database Error: " . $conn->error);
    }

    $stmt->bind_param("ss", $login, $login);

    $stmt->execute();

    $result = $stmt->get_result();


    // User found
    if ($result->num_rows == 1) {

        $user = $result->fetch_assoc();


        // Check password
        if (password_verify($password, $user["password"])) {

            // Create session
            $_SESSION["user_id"] = $user["id"];
            $_SESSION["username"] = $user["username"];
            $_SESSION["email"] = $user["email"];


            // Redirect to dashboard
            header("Location: dashboard.php");
            exit();

        } else {

            echo "Invalid Password!";

        }

    } else {

        echo "User Not Found!";

    }


    $stmt->close();
    $conn->close();

} else {

    echo "Invalid Request!";

}

?>