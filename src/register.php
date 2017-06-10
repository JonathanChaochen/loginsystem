<?php
/* Registration process, inserts user info into the database
   and sends account confirmation email message
 */

   require("../vendor/autoload.php");
   include('settings.php');

   // Set session variables to be used on profile.php page
   $_SESSION['email'] = $_POST['email'];
   $_SESSION['first_name'] = $_POST['firstname'];
   $_SESSION['last_name'] = $_POST['lastname'];

   // Escape all $_POST variables to protect against SQL injections
   $first_name = $mysqli->real_escape_string($_POST['firstname']);
   $last_name = $mysqli->real_escape_string($_POST['lastname']);
   $email = $mysqli->real_escape_string($_POST['email']);
   $password = $mysqli->real_escape_string(password_hash($_POST['password'], PASSWORD_DEFAULT));


   // Check if user with that email already exists
   $result = $mysqli->query("SELECT * FROM users WHERE email='$email'") ;

   // We know user email exists if the rows returned are more than 0
   if ( $result->num_rows > 0 ) {

       $_SESSION['message'] = 'User with this email already exists!';
       header("location: error.php");

   }
   else { // Email doesn't already exist in a database, proceed...

       // active is 0 by DEFAULT (no need to include it here)
       $sql = "INSERT INTO users (first_name, last_name, email, password) "
               . "VALUES ('$first_name','$last_name','$email','$password')";

       // Add user to the database
       if ( $mysqli->query($sql) ){

           $_SESSION['active'] = 0; //0 until user activates their account with verify.php
           $_SESSION['logged_in'] = true; // So we know the user has logged in
           $_SESSION['message'] =

                    "Confirmation link has been sent to $email, please verify
                    your account by clicking on the link in the message!";

           // Send registration confirmation link (verify.php)
           // $to      = $email;
           // $subject = 'Account Verification ( webdeveloperchao.com )';
           // $message_body = '
           // Hello '.$first_name.',

           // Thank you for signing up!


           // Please click this link to activate your account:

           // http://localhost/~chenchao/loginsystem/src/verify.php?email='.$email;

           // mail( $to, $subject, $message_body );


           $mail = new PHPMailer;

           $mail->isSMTP();                            // Set mailer to use SMTP
           $mail->Host = 'smtp.gmail.com';             // Specify main and backup SMTP servers
           $mail->SMTPAuth = true;                     // Enable SMTP authentication


           $mail->Username = $settings['username'];          // SMTP username
           $mail->Password = $settings['password']; // SMTP password


           $mail->SMTPSecure = 'tls';                  // Enable TLS encryption, `ssl` also accepted
           $mail->Port = 587;                          // TCP port to connect to

           $mail->setFrom('chaochen42@gmail.com', 'chao chen');
           // $mail->addReplyTo('cczhang3304@163.com', 'Chao');
           $mail->addAddress($email);   // Add a recipient
           // $mail->addCC('cc@example.com');
           // $mail->addBCC('bcc@example.com');

           $mail->isHTML(true);  // Set email format to HTML

           $bodyContent = '<h1>Hello , $first_name.

                     Thank you for signing up!</h1>';

           $bodyContent .= '<a href="http://localhost/~chenchao/loginsystem/src/verify.php?email=$email"> Please click this link to activate your account. </a>';

           $bodyContent = str_replace('$first_name', $first_name, $bodyContent);
           $bodyContent = str_replace('$email', $email, $bodyContent);

           $mail->Subject = 'Account Verification ( webdeveloperchao.com )';

           $mail->Body = $bodyContent;

           // if(!$mail->send()) {
           //     echo 'Message could not be sent.';
           //     echo 'Mailer Error: ' . $mail->ErrorInfo;
           // } else {
           //     echo 'Message has been sent';
           // }

           if($mail->send()) {

           header("location: success.php");
           }

       }

       else {
           $_SESSION['message'] = 'Registration failed!';
           header("location: error.php");
       }

     }
