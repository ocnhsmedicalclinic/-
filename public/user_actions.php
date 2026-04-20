<?php
require_once '../config/db.php';
requireAdmin();

// Include PHPMailer
require '../lib/PHPMailer/PHPMailer.php';
require '../lib/PHPMailer/SMTP.php';
require '../lib/PHPMailer/Exception.php';
require_once '../config/mail.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function sendStatusEmail($toEmail, $username, $statusType)
{
    if (defined('SMTP_USERNAME') && SMTP_USERNAME === 'your_email@gmail.com')
        return false;

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;

        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($toEmail);
        $mail->isHTML(true);

        if ($statusType === 'activated') {
            $mail->Subject = 'Account Approved - OCNHS Medical Clinic';
            $mail->Body = "
                <div style='font-family: Arial, sans-serif; padding: 20px; background-color: #f4f6f8;'>
                    <div style='max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);'>
                        <h2 style='color: #00ACB1;'>Account Approved</h2>
                        <p>Hello <strong>$username</strong>,</p>
                        <p>Good news! Your account has been <strong>approved and activated</strong> by the Administrator.</p>
                        <p>You may now login to the system using your credentials.</p>
                        <br>
                        <a href='http://192.168.0.101/index.php' style='background: #00ACB1; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Login Now</a>
                        <br><br>
                        <p style='font-size: 12px; color: #666;'>OCNHS Medical Clinic System</p>
                    </div>
                </div>
            ";
        } elseif ($statusType === 'deactivated') {
            $mail->Subject = 'Account Deactivated - OCNHS Medical Clinic';
            $mail->Body = "
                <div style='font-family: Arial, sans-serif; padding: 20px; background-color: #f4f6f8;'>
                    <div style='max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);'>
                        <h2 style='color: #e74c3c;'>Account Deactivated</h2>
                        <p>Hello <strong>$username</strong>,</p>
                        <p>Your account has been <strong>deactivated</strong> by the Administrator.</p>
                        <p>If you believe this is an error, please contact the system administrator.</p>
                        <br>
                        <p style='font-size: 12px; color: #666;'>OCNHS Medical Clinic System</p>
                    </div>
                </div>
            ";
        }

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email Error: " . $mail->ErrorInfo);
        return false;
    }
}

if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $userId = intval($_GET['id']);

    // Prevent modifying self
    if ($userId == $_SESSION['user_id']) {
        header("Location: users.php?error=Cannot modify your own account");
        exit();
    }

    // Fetch user details first
    $stmt = $conn->prepare("SELECT username, email FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user) {
        if ($action == 'activate') {
            $stmt = $conn->prepare("UPDATE users SET is_active = 1 WHERE id = ?");
            $stmt->bind_param("i", $userId);
            if ($stmt->execute()) {
                sendStatusEmail($user['email'], $user['username'], 'activated');
            }
        } elseif ($action == 'deactivate') {
            $stmt = $conn->prepare("UPDATE users SET is_active = 0 WHERE id = ?");
            $stmt->bind_param("i", $userId);
            if ($stmt->execute()) {
                sendStatusEmail($user['email'], $user['username'], 'deactivated');
            }
        } elseif ($action == 'delete') {
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
        }
    }
}

header("Location: users.php");
exit();
?>