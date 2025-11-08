<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// JSON Response
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include PHPMailer
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

// DB Connection
$conn = new mysqli("localhost", "root", "", "act05");
if ($conn->connect_error) {
    echo json_encode(['status' => 'error', 'message' => 'Database failed: ' . $conn->connect_error]);
    exit;
}

// Hardcoded weekly date ranges
$weeks = [
    ['label' => 'Week 03-09', 'start' => '2025-10-03', 'end' => '2025-10-09'],
    ['label' => 'Week 10-16', 'start' => '2025-10-10', 'end' => '2025-10-16'],
    ['label' => 'Week 17-23', 'start' => '2025-10-17', 'end' => '2025-10-23'],
    ['label' => 'Week 24-30', 'start' => '2025-10-24', 'end' => '2025-10-30'],
];

// Fetch all employees
$empQuery = "SELECT ID, Name, Email FROM employees ORDER BY Name ASC";
$empResult = $conn->query($empQuery);

if (!$empResult || $empResult->num_rows === 0) {
    echo json_encode(['status' => 'error', 'message' => 'No employees found']);
    exit;
}

$summary = [
    'success' => [],
    'failed' => []
];

while ($emp = $empResult->fetch_assoc()) {
    $empID   = $emp['ID'];
    $empName = $emp['Name'];
    $email   = $emp['Email'];

    foreach ($weeks as $week) {
        $weekLabel = $week['label'];
        $weekStart = $week['start'];
        $weekEnd   = $week['end'];

        // Run the same script logic, but as a function
        $result = sendPayslip($conn, $empID, $empName, $email, $weekLabel, $weekStart, $weekEnd);

        if ($result['success']) {
            $summary['success'][] = "{$empName} ({$weekLabel})";
        } else {
            $summary['failed'][] = "{$empName} ({$weekLabel}): {$result['message']}";
        }
    }
}

// Close DB
$conn->close();

// Return summary
echo json_encode([
    'status' => 'completed',
    'message' => 'Payslip sending completed.',
    'results' => $summary
]);


// ---------------------------------------------------------
// FUNCTION: Send Payslip (uses your working code logic)
// ---------------------------------------------------------
function sendPayslip($conn, $empID, $empName, $email, $weekLabel, $weekStart, $weekEnd)
{
    ob_start();
    include 'compute_and_send_payslip.php';
    return json_decode(ob_get_clean(), true);
}
?>
