<?php
use PHPMailer\PHPMailer\PHPMailer;
Use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;


require __DIR__ . '/vendor/autoload.php';

if (isset($_POST["register"]))
{
    $name = $_POST["name"];
    $email = $_POST["email"];
    $password = $_POST["password"];

    $mail = new PHPMailer(true);
    try {
        $mail->SMTPDebug = 0;
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'alexabais275@gmail.com';
        $mail->Password   = 'ikbn dqct orvj ydiv'; // App Password from Google
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->setFrom('alexabais275@gmail.com', 'Register System');
        $mail->addAddress($email, $name);
        $mail->isHTML(true);
        $verification_code = substr(number_format(time() * rand(), 0, '', ''), 0, 6);
        $mail->Subject = 'Email Verification';
        $mail->Body    = "Dear <b>$name</b>,<br><br>Thank you for registering! Your verification code is: <b>$verification_code</b><br><br>Best regards,<br>Register System";
        $mail->send();
        echo "Registration email sent!";
        $encrypted_password = password_hash($password, PASSWORD_DEFAULT);
        $conn = mysqli_connect("localhost", "root", "", "register");
        
        $stmt = $conn->prepare("
            INSERT INTO users (name, email, password, verification_code)
            VALUES (?, ?, ?, ?)
        ");

        $stmt->bind_param(
            "ssss",
            $name,
            $email,
            $encrypted_password,
            $verification_code
        );

        $stmt->execute();
        $stmt->close();
        

        header("Location: email_verification.php?email=" . $email);
        exit();
 
    } catch (Exception $e) {
        echo "Error sending email: {$mail->ErrorInfo}";
}
}
?>
