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

        // Fetch user data from the database
        $sql = "SELECT * FROM users WHERE id = $id";
        $result = mysqli_query($conn, $sql);

        // Display user data in the form for editing
        if (mysqli_num_rows($result) == 1) {
            $row = mysqli_fetch_assoc($result);
            $name = $row['name'];
            $email = $row['email'];
        } else {
            echo "User not found.";
            exit();
        }

        // Close the database connection
        mysqli_close($conn);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Database connection
        $conn = mysqli_connect('localhost', 'root', 'root', 'CRUDdb');

        // Check connection
        if (!$conn) {
            die("Connection failed: " . mysqli_connect_error());
        }

        // Retrieve user ID and updated data from the form
        $id = $_POST['id'];
        $name = $_POST['name'];
        $email = $_POST['email'];

        // Update user data in the database
        $sql = "UPDATE users SET name='$name', email='$email' WHERE id=$id";
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
<!DOCTYPE html>
<html>
<head>
    <title>Edit User</title>
</head>
<body>
    <h2>Edit User</h2>
    <form method="post" action="edit.php">
        <input type="hidden" name="id" value="<?php echo $id; ?>">
        <label for="name">Name:</label>
        <input type="text" name="name" value="<?php echo $name; ?>" required>
        <label for="email">Email:</label>
        <input type="email" name="email" value="<?php echo $email; ?>" required>
        <button type="submit">Update User</button>
    </form>
</body>
</html>
