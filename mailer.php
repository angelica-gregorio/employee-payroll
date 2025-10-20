<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// PHPMailer library files
require 'phpmailer/src/PHPMailer.php';
require 'phpmailer/src/SMTP.php';
require 'phpmailer/src/Exception.php';

function sendPayslip($to, $subject, $body, $attachmentPath) {
    $mail = new PHPMailer(true);

    // 🧩 Enable debugging (shows errors in the browser)
    $mail->SMTPDebug = 2; // 0 = off, 1 = commands, 2 = full debug output
    $mail->Debugoutput = 'html'; // nicely formatted

    try {
        // 📨 Server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;

        // 🔐 Gmail credentials
        $mail->Username = 'angelica_g_gregorio@dlsu.edu.ph';  // your Gmail address
        $mail->Password = 'pbep trym mvri mzdz';  // Gmail App Password (NOT your Gmail login)
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;   // TLS encryption
        $mail->Port = 587;                                    // Port for TLS (use 465 for SSL)

        // 📧 Sender and recipient
        $mail->setFrom('angelica_g_gregorio@dlsu.edu.ph', 'Payroll System');
        $mail->addAddress($to); // recipient email

        // 📎 Optional attachment
        if (!empty($attachmentPath)) {
            $mail->addAttachment($attachmentPath);
        }

        // 📝 Email content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;

        // 🚀 Send email
        if ($mail->send()) {
            echo "✅ Email sent successfully!";
            return true;
        } else {
            echo "❌ Email failed to send. Error: {$mail->ErrorInfo}";
            return false;
        }

    } catch (Exception $e) {
        echo "❌ Mailer Error: {$mail->ErrorInfo}";
        return false;
    }
}
?>
