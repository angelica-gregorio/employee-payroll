<?php
require_once 'mailer.php';

$to = 'gregorioangelica653@gmail.com';  // test email (not same as sender)
$subject = 'Test Payslip Email';
$body = 'Hello! This is a test email from your Payroll System.';
$attachmentPath = ''; // no attachment yet

sendPayslip($to, $subject, $body, $attachmentPath);
?>
