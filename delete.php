<?php
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
        // Database connection
        $conn = mysqli_connect('localhost', 'root', 'root', 'CRUDdb');

        // Check connection
        if (!$conn) {
            die("Connection failed: " . mysqli_connect_error());
        }

        // Retrieve user ID from the URL parameter
        $id = $_GET['id'];

        // Delete user from the database
        $sql = "DELETE FROM users WHERE id = $id";
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
