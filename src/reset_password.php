<?php
/* Password reset process, updates database with new user password */
require 'db.php';
session_start();

// Make sure the form is being submitted with method="post"
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // Make sure the two passwords match
    if ( $_POST['newpassword'] == $_POST['confirmpassword'] ) {

        $new_password = password_hash($_POST['newpassword'], PASSWORD_DEFAULT);

        // We get $_POST['email'] and from the hidden input field of reset.php form
        $email = $mysqli->real_escape_string($_POST['email']);


        $sql = "UPDATE users SET password='$new_password'  WHERE email='$email'";


        if ( $mysqli->query($sql) ) {

        $_SESSION['message'] = "Your password has been reset successfully!";
        header("location: success.php");

        }

    }
    else {
        $_SESSION['message'] = "Two passwords you entered don't match, try again!";
        header("location: error.php");
    }

}
?>
