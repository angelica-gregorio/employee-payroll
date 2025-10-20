<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Always return JSON
header('Content-Type: application/json');

// Debug mode (optional)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include PHPMailer (GitHub version)
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "act05";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed: ' . $conn->connect_error]);
    exit;
}

// Retrieve POST data
$empName   = trim($_POST['empName'] ?? '');
$empID     = trim($_POST['empID'] ?? '');
$weekLabel = trim($_POST['weekLabel'] ?? '');
$weekStart = trim($_POST['weekStart'] ?? '');
$weekEnd   = trim($_POST['weekEnd'] ?? '');

if (!$empName || !$weekStart || !$weekEnd) {
    echo json_encode(['status' => 'error', 'message' => 'Missing required data (empName, weekStart, weekEnd)']);
    exit;
}

// Fetch timesheet & employee data
$sql = "SELECT 
    t.ShiftDate, t.ShiftNo, t.DutyType, t.Hours, t.Role, t.TimeIN, t.TimeOUT, 
    t.Notes, t.Deductions, e.Rate, e.SSS, e.PHIC, e.HDMF, e.GOVT, 
    e.Email, h.Type AS HolidayType
FROM timesheet t
JOIN employees e ON t.Name = e.Name
LEFT JOIN holidays h ON t.ShiftDate = h.Date
WHERE t.Name = ? AND t.ShiftDate BETWEEN ? AND ?
ORDER BY t.ShiftDate";

$stmt = $conn->prepare($sql);
$stmt->bind_param("sss", $empName, $weekStart, $weekEnd);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['status' => 'error', 'message' => "No records found for $empName ($weekStart → $weekEnd)"]);
    exit;
}

// Initialize totals
$daysWorked = $overtimeHrs = $nightShifts = $holidayPay = $cashierBonus = $silBonus = 0;
$lateDeduction = $shortage = $caUniform = 0;
$rate = $sss = $phic = $hdmf = $govt = 0;
$email = "";
$processedDates = [];

while ($row = $result->fetch_assoc()) {
    $rate = (float)$row['Rate'];
    $sss = (float)$row['SSS'];
    $phic = (float)$row['PHIC'];
    $hdmf = (float)$row['HDMF'];
    $govt = (float)$row['GOVT'];
    $email = $row['Email'] ?? '';
    $shiftDate = $row['ShiftDate'];

    // Count days worked
    if (!in_array($shiftDate, $processedDates) && $row['TimeIN'] != '00:00:00' && $row['TimeOUT'] != '00:00:00') {
        $daysWorked++;
        $processedDates[] = $shiftDate;
    }

    // Holiday pay
    if (!empty($row['HolidayType'])) {
        if (strtolower($row['HolidayType']) == 'regular holiday') $holidayPay += $rate * 1;
        if (strtolower($row['HolidayType']) == 'special holiday') $holidayPay += $rate * 0.3;
    }

    // Overtime
    if (strcasecmp($row['DutyType'], 'Overtime') === 0) {
        $overtimeHrs += (float)$row['Hours'];
    }

    // Late deduction
    if (strtolower(trim($row['DutyType'])) === 'late') {
        $lateDeduction += 150;
    }

    // Cashier bonus
    if (strtolower($row['Role']) === 'cashier' && (float)$row['Hours'] >= 8) {
        $cashierBonus += 40;
    }

    // SIL bonus
    if (strtolower(trim($row['Notes'])) === 'sil') {
        $silBonus += $rate;
    }

    // Shortage or CA/uniform
    if (strtolower(trim($row['Notes'])) === 'short') {
        $shortage += abs((float)$row['Deductions']);
    }
    if (in_array(strtolower(trim($row['Notes'])), ['ca', 'uniform'])) {
        $caUniform += 106;
    }
}

// Compute earnings
$basePay = $rate * $daysWorked;
$ratePerHour = $rate / 8;
$overtimePay = $overtimeHrs * $ratePerHour;
$nightDiff = $nightShifts * 52;

// ✅ Include gov deductions only if cutoff is 24–30
$cutoffStartDay = (int)date('d', strtotime($weekStart));
$cutoffEndDay   = (int)date('d', strtotime($weekEnd));

$includeGovtDeductions = false;
for ($day = $cutoffStartDay; $day <= $cutoffEndDay; $day++) {
    if ($day >= 24 && $day <= 30) {
        $includeGovtDeductions = true;
        break;
    }
}

if ($includeGovtDeductions) {
    $totalDeductions = $sss + $phic + $hdmf + $govt + $lateDeduction + $shortage + $caUniform;
} else {
    $totalDeductions = $lateDeduction + $shortage + $caUniform;
}

$gross = $basePay + $overtimePay + $nightDiff + $holidayPay + $cashierBonus + $silBonus;
$net = $gross - $totalDeductions;

// Close DB
$stmt->close();
$conn->close();

// Format email HTML
$html = '
<div style="font-family:Arial,sans-serif;max-width:600px;margin:auto;padding:20px;background:#f7f9fc;border-radius:10px;">
    <h2 style="color:#2d6cdf;text-align:center;">Employee Payslip</h2>
    <p style="text-align:center;color:#555;">For the period <b>' . htmlspecialchars($weekLabel) . '</b> (' . htmlspecialchars($weekStart) . ' → ' . htmlspecialchars($weekEnd) . ')</p>
    
    <hr style="border:1px solid #ddd;">
    <h3 style="color:#444;">Employee Details</h3>
    <p><strong>Name:</strong> ' . htmlspecialchars($empName) . '</p>
    <p><strong>ID:</strong> ' . htmlspecialchars($empID) . '</p>
    <p><strong>Days Worked:</strong> ' . $daysWorked . '</p>

    <hr style="border:1px solid #ddd;">
    <h3 style="color:#444;">Earnings</h3>
    <ul style="list-style:none;padding:0;">
        <li>Base Pay: <b>P ' . number_format($basePay, 2) . '</b></li>
        <li>Overtime (' . $overtimeHrs . ' hrs): <b>P ' . number_format($overtimePay, 2) . '</b></li>
        <li>Night Differential: <b>P ' . number_format($nightDiff, 2) . '</b></li>
        <li>Holiday Pay: <b>P ' . number_format($holidayPay, 2) . '</b></li>
        <li>Cashier Bonus: <b>P ' . number_format($cashierBonus, 2) . '</b></li>
        <li>SIL Bonus: <b>P ' . number_format($silBonus, 2) . '</b></li>
    </ul>
    <p><strong>Gross Income:</strong> P ' . number_format($gross, 2) . '</p>

    <hr style="border:1px solid #ddd;">
    <h3 style="color:#444;">Deductions</h3>
    <ul style="list-style:none;padding:0;">';

if ($includeGovtDeductions) {
    $html .= '
        <li>SSS: <b>P ' . number_format($sss, 2) . '</b></li>
        <li>PHIC: <b>P ' . number_format($phic, 2) . '</b></li>
        <li>HDMF: <b>P ' . number_format($hdmf, 2) . '</b></li>
        <li>GOVT: <b>P ' . number_format($govt, 2) . '</b></li>';
}

$html .= '
        <li>Late Deduction: <b>P ' . number_format($lateDeduction, 2) . '</b></li>
        <li>Shortage: <b>P ' . number_format($shortage, 2) . '</b></li>
        <li>CA/Uniform: <b>P ' . number_format($caUniform, 2) . '</b></li>
    </ul>
    <p><strong>Total Deductions:</strong> P ' . number_format($totalDeductions, 2) . '</p>

    <div style="margin-top:20px;padding:15px;background:#2d6cdf;color:#fff;border-radius:8px;text-align:center;">
        <h3 style="margin:0;">Net Income: P ' . number_format($net, 2) . '</h3>
    </div>
    <p style="color:#777;text-align:center;margin-top:10px;">Generated automatically by the Employee Management System</p>
</div>
';

// Send email via PHPMailer
if (!empty($email)) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'angelica_g_gregorio@dlsu.edu.ph'; // change this
        $mail->Password = 'cgfi anha jgka oiqp'; // your app password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->setFrom('angelica_g_gregorio@dlsu.edu.ph', 'HR Department');
        $mail->addAddress($email, $empName);
        $mail->isHTML(true);
        $mail->Subject = 'Payslip for ' . $weekLabel;
        $mail->Body = $html;
        $mail->AltBody = strip_tags($html);

        $mail->send();
        echo json_encode(['status' => 'success', 'message' => "Payslip successfully emailed to $empName at $email."]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Failed to send email: ' . $mail->ErrorInfo]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => "No email address found for $empName."]);
}
?>
