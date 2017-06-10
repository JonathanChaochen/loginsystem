<?php
/* Reset your password form, sends reset.php password link */
require("../vendor/autoload.php");
require 'db.php';
session_start();

// Check if form submitted with method="post"
if ( $_SERVER['REQUEST_METHOD'] == 'POST' )
{
    $email = $mysqli->real_escape_string($_POST['email']);
    $result = $mysqli->query("SELECT * FROM users WHERE email='$email'");

    if ( $result->num_rows == 0 ) // User doesn't exist
    {
        $_SESSION['message'] = "User with that email doesn't exist!";
        header("location: error.php");
    }
    else { // User exists (num_rows != 0)

        $user = $result->fetch_assoc(); // $user becomes array with user data

        $email = $user['email'];

        $first_name = $user['first_name'];

        // Session message to display on success.php
        $_SESSION['message'] = "<p>Please check your email <span>$email</span>"
        . " for a confirmation link to complete your password reset!</p>";

        // Send registration confirmation link (reset.php)
        // $to      = $email;
        // $subject = 'Password Reset Link ( webdeveloperchao.com )';
        // $message_body = '
        // Hello '.$first_name.',

        // You have requested password reset!

        // Please click this link to reset your password:

        // http://localhost/~chenchao/new/reset.php?email='.$email;

        // mail($to, $subject, $message_body);



        $mail = new PHPMailer;

        $mail->isSMTP();                            // Set mailer to use SMTP
        $mail->Host = 'smtp.gmail.com';             // Specify main and backup SMTP servers
        $mail->SMTPAuth = true;                     // Enable SMTP authentication
        $mail->Username = 'chaochen42@gmail.com';          // SMTP username
        $mail->Password = 'Chenchao3304'; // SMTP password
        $mail->SMTPSecure = 'tls';                  // Enable TLS encryption, `ssl` also accepted
        $mail->Port = 587;                          // TCP port to connect to

        $mail->setFrom('chaochen42@gmail.com', 'chao chen');
        // $mail->addReplyTo('cczhang3304@163.com', 'Chao');
        $mail->addAddress($email);   // Add a recipient
        // $mail->addCC('cc@example.com');
        // $mail->addBCC('bcc@example.com');

        $mail->isHTML(true);  // Set email format to HTML

        $bodyContent = '<h1>Hello , $first_name.

                  You have requested password reset!</h1>';

        $bodyContent .= '<a href="http://localhost/~chenchao/loginsystem/src/reset.php?email=$email"> Please click this link to activate your account. </a>';

        $bodyContent = str_replace('$first_name', $first_name, $bodyContent);
        $bodyContent = str_replace('$email', $email, $bodyContent);

        $mail->Subject = 'Password Reset Link ( webdeveloperchao.com )';

        $mail->Body    = $bodyContent;

        if($mail->send()) {

        header("location: success.php");
        }
  }
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>Reset Your Password</title>
  <?php include 'css/css.html'; ?>
</head>
<body>

  <div class="form">

    <h1>Reset Your Password</h1>

    <form action="forgot.php" method="post">
     <div class="field-wrap">
      <label>
        Email Address<span class="req">*</span>
      </label>
      <input type="email"required autocomplete="off" name="email"/>
    </div>
    <button class="button button-block"/>Reset</button>
    </form>
  </div>

<script src='http://cdnjs.cloudflare.com/ajax/libs/jquery/2.1.3/jquery.min.js'></script>
<script src="js/index.js"></script>
</body>

</html>
