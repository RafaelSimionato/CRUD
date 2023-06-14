<?php
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Database connection
        $conn = mysqli_connect('localhost', 'root', 'root', 'CRUDdb');

        // Check connection
        if (!$conn) {
            die("Connection failed: " . mysqli_connect_error());
        }

        // Retrieve user data from the form
        $name = $_POST['name'];
        $email = $_POST['email'];

        // Insert user into the database
        $sql = "INSERT INTO users (name, email) VALUES ('$name', '$email')";
        if (mysqli_query($conn, $sql)) {
            header('Location: index.php');
            exit();
        } else {
            echo "Error: " . $sql . "<br>" . mysqli_error($conn);
        }

        // Close the database connection
        mysqli_close($conn);
    }
?>
