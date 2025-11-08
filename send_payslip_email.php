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
    e.Email, h.Type AS HolidayType, h.Rate AS HolidayRate
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

// ✅ Initialize tracking arrays to prevent duplicates
$processedDates = [];
$overtimeDates = [];
$nightDates = [];
$holidayDates = [];
$lateDates = [];
$shortageDates = [];
$caUniformDates = [];
$silDates = [];

// ✅ Initialize counters
$overtimeHrs = 0;
$nightShifts = 0;
$holidayPay = 0;
$silBonus = 0;
$lateDeduction = 0;
$shortage = 0;
$caUniform = 0;

$rate = $sss = $phic = $hdmf = $govt = 0;
$email = "";
$role = "";

while ($row = $result->fetch_assoc()) {
    $rate = (float)$row['Rate'];
    $sss = (float)$row['SSS'];
    $phic = (float)$row['PHIC'];
    $hdmf = (float)$row['HDMF'];
    $govt = (float)$row['GOVT'];
    $email = $row['Email'] ?? '';
    $role = strtolower(trim($row['Role']));

    $shiftDate = $row['ShiftDate'];
    $note = strtolower(trim($row['Notes']));
    $dutyType = trim($row['DutyType']);

    // ✅ Count valid workday (only once per date)
    $validTime = '/^(?:[01]\d|2[0-3]):[0-5]\d:[0-5]\d$/';
    if (!in_array($shiftDate, $processedDates) && 
        preg_match($validTime, trim($row['TimeIN'])) && 
        preg_match($validTime, trim($row['TimeOUT'])) &&
        $row['TimeIN'] != '00:00:00' && 
        $row['TimeOUT'] != '00:00:00') {
        $processedDates[] = $shiftDate;
    }

    // ✅ Overtime (one count per date)
    if (!in_array($shiftDate, $overtimeDates) &&
        strcasecmp($dutyType, 'Overtime') === 0 && 
        is_numeric($row['Hours'])) {
        $overtimeHrs += (float)$row['Hours'];
        $overtimeDates[] = $shiftDate;
    }

    // ✅ Night Differential (one count per date)
    $timeIn = strtotime($row['TimeIN']);
    $timeOut = strtotime($row['TimeOUT']);
    $isNightShift = (!empty($row['TimeIN']) && 
                     !empty($row['TimeOUT']) && 
                     $timeIn > $timeOut);
    $isSIL = ($note === 'sil');

    if (!in_array($shiftDate, $nightDates) && 
        ($isNightShift || $isSIL)) {
        $nightShifts++;
        $nightDates[] = $shiftDate;
    }

    // ✅ Holiday Pay (one count per date)
    if (!in_array($shiftDate, $holidayDates) && 
        !empty($row['HolidayType'])) {
        $holidayType = strtolower(trim($row['HolidayType']));
        
        if ($holidayType === 'regular holiday') {
            $holidayPay += $rate * 1;
        } elseif ($holidayType === 'special holiday') {
            $holidayPay += $rate * 0.3;
        }
        
        $holidayDates[] = $shiftDate;
    }

    // ✅ Late Deductions (one count per date)
    if (!in_array($shiftDate, $lateDates) && 
        strtolower($dutyType) === 'late') {
        $lateDeduction += 150;
        $lateDates[] = $shiftDate;
    }

    // ✅ Shortage (one count per date)
    if (!in_array($shiftDate, $shortageDates) && 
        $note === 'short') {
        $deductVal = isset($row['Deductions']) 
            ? abs((float)str_replace('-', '', $row['Deductions'])) 
            : 0;
        $shortage += $deductVal;
        $shortageDates[] = $shiftDate;
    }

    // ✅ CA / Uniform (one count per date)
    if (!in_array($shiftDate, $caUniformDates) && 
        ($note === 'ca' || $note === 'uniform')) {
        $caUniform += 106;
        $caUniformDates[] = $shiftDate;
    }

    // ✅ SIL Bonus (one count per date)
    if (!in_array($shiftDate, $silDates) && 
        $note === 'sil') {
        $silBonus += $rate;
        $silDates[] = $shiftDate;
    }
}

$daysWorked = count($processedDates);

$stmt->close();

// ✅ Compute allowance (matching main page logic)
$nameEsc = $conn->real_escape_string($empName);
$allowance = 0;

if ($role === 'cashier') {
    // Cashier gets hourly bonus
    $uniqueShiftsQuery = "
        SELECT DISTINCT ShiftDate, ShiftNo, DutyType, Hours, Business_Unit, Notes
        FROM timesheet
        WHERE Name = '$nameEsc'
          AND Role = 'Cashier'
          AND ShiftDate BETWEEN '$weekStart' AND '$weekEnd'
          AND TimeIN != '00:00:00'
          AND TimeOUT != '00:00:00'
          AND Hours IS NOT NULL
          AND ShiftNo IS NOT NULL
          AND DutyType IS NOT NULL
        ORDER BY ShiftDate ASC, ShiftNo ASC
    ";
    $resShifts = $conn->query($uniqueShiftsQuery);
    
    $totalHours = 0;
    if ($resShifts && $resShifts->num_rows > 0) {
        while ($tsRow = $resShifts->fetch_assoc()) {
            $totalHours += (float)$tsRow['Hours'];
        }
    }
    
    $cashierBonus = round($totalHours * 5, 2);
    $dailyAllowance = 0;
    
    if ($rate > 520) {
        $uniqueDaysQuery = "
            SELECT DISTINCT ShiftDate
            FROM timesheet
            WHERE Name = '$nameEsc'
              AND ShiftDate BETWEEN '$weekStart' AND '$weekEnd'
              AND DutyType = 'OnDuty'
              AND TimeIN != '00:00:00'
              AND TimeOUT != '00:00:00'
        ";
        $daysRes = $conn->query($uniqueDaysQuery);
        
        if ($daysRes && $daysRes->num_rows > 0) {
            $workDays = $daysRes->num_rows;
            $dailyAllowance = $workDays * 20;
        }
    }
    
    $allowance = $cashierBonus + $dailyAllowance;
} else {
    // Non-cashier only gets daily allowance
    $dailyAllowance = 0;
    
    if ($rate > 520) {
        $uniqueDaysQuery = "
            SELECT DISTINCT ShiftDate
            FROM timesheet
            WHERE Name = '$nameEsc'
              AND ShiftDate BETWEEN '$weekStart' AND '$weekEnd'
              AND DutyType = 'OnDuty'
              AND TimeIN != '00:00:00'
              AND TimeOUT != '00:00:00'
        ";
        $daysRes = $conn->query($uniqueDaysQuery);
        
        if ($daysRes && $daysRes->num_rows > 0) {
            $workDays = $daysRes->num_rows;
            $dailyAllowance = $workDays * 20;
        }
    }
    
    $allowance = $dailyAllowance;
}

// Close DB
$conn->close();

// ✅ Compute earnings
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

$gross = $basePay + $overtimePay + $allowance + $nightDiff + $holidayPay + $silBonus;
$net = $gross - $totalDeductions;

// Format email HTML
$html = '
<div style="font-family:Arial,sans-serif;max-width:650px;margin:auto;padding:25px;background:#f7f9fc;border-radius:12px;box-shadow:0 2px 10px rgba(0,0,0,0.1);">
    <div style="text-align:center;margin-bottom:25px;">
        <h2 style="color:#2d6cdf;margin:0;font-size:28px;">Employee Payslip</h2>
        <p style="color:#666;margin:8px 0 0 0;font-size:14px;">
            Pay Period: <strong>' . htmlspecialchars($weekLabel) . '</strong><br>
            <span style="font-size:12px;">(' . htmlspecialchars($weekStart) . ' to ' . htmlspecialchars($weekEnd) . ')</span>
        </p>
    </div>
    
    <div style="background:#fff;padding:20px;border-radius:8px;margin-bottom:15px;">
        <h3 style="color:#333;margin-top:0;border-bottom:2px solid #2d6cdf;padding-bottom:8px;">Employee Information</h3>
        <table style="width:100%;font-size:14px;">
            <tr>
                <td style="padding:6px 0;"><strong>Name:</strong></td>
                <td style="padding:6px 0;">' . htmlspecialchars($empName) . '</td>
            </tr>
            <tr>
                <td style="padding:6px 0;"><strong>Employee ID:</strong></td>
                <td style="padding:6px 0;">' . htmlspecialchars($empID) . '</td>
            </tr>
            <tr>
                <td style="padding:6px 0;"><strong>Days Worked:</strong></td>
                <td style="padding:6px 0;">' . $daysWorked . '</td>
            </tr>
            <tr>
                <td style="padding:6px 0;"><strong>Daily Rate:</strong></td>
                <td style="padding:6px 0;">₱' . number_format($rate, 2) . '</td>
            </tr>
        </table>
    </div>

    <div style="background:#fff;padding:20px;border-radius:8px;margin-bottom:15px;">
        <h3 style="color:#333;margin-top:0;border-bottom:2px solid #4caf50;padding-bottom:8px;">Earnings</h3>
        <table style="width:100%;font-size:14px;">
            <tr>
                <td style="padding:6px 0;">Base Pay (' . $daysWorked . ' days)</td>
                <td style="padding:6px 0;text-align:right;"><strong>₱' . number_format($basePay, 2) . '</strong></td>
            </tr>';

if ($overtimePay > 0) {
    $html .= '<tr>
                <td style="padding:6px 0;">Overtime (' . $overtimeHrs . ' hours @ ₱' . number_format($ratePerHour, 2) . '/hr)</td>
                <td style="padding:6px 0;text-align:right;"><strong>₱' . number_format($overtimePay, 2) . '</strong></td>
            </tr>';
}

if ($allowance > 0) {
    $html .= '<tr>
                <td style="padding:6px 0;">Allowance</td>
                <td style="padding:6px 0;text-align:right;"><strong>₱' . number_format($allowance, 2) . '</strong></td>
            </tr>';
}

if ($nightDiff > 0) {
    $html .= '<tr>
                <td style="padding:6px 0;">Night Differential (' . $nightShifts . ' shifts @ ₱52)</td>
                <td style="padding:6px 0;text-align:right;"><strong>₱' . number_format($nightDiff, 2) . '</strong></td>
            </tr>';
}

if ($holidayPay > 0) {
    $html .= '<tr>
                <td style="padding:6px 0;">Holiday Pay</td>
                <td style="padding:6px 0;text-align:right;"><strong>₱' . number_format($holidayPay, 2) . '</strong></td>
            </tr>';
}

if ($silBonus > 0) {
    $html .= '<tr>
                <td style="padding:6px 0;">SIL (Paid Leave)</td>
                <td style="padding:6px 0;text-align:right;"><strong>₱' . number_format($silBonus, 2) . '</strong></td>
            </tr>';
}

$html .= '
            <tr style="border-top:2px solid #e0e0e0;">
                <td style="padding:10px 0;font-size:15px;"><strong>GROSS INCOME</strong></td>
                <td style="padding:10px 0;text-align:right;font-size:15px;color:#4caf50;"><strong>₱' . number_format($gross, 2) . '</strong></td>
            </tr>
        </table>
    </div>

    <div style="background:#fff;padding:20px;border-radius:8px;margin-bottom:15px;">
        <h3 style="color:#333;margin-top:0;border-bottom:2px solid #f44336;padding-bottom:8px;">Deductions</h3>
        <table style="width:100%;font-size:14px;">';

if ($includeGovtDeductions) {
    $html .= '
            <tr>
                <td style="padding:6px 0;">SSS Contribution</td>
                <td style="padding:6px 0;text-align:right;"><strong>₱' . number_format($sss, 2) . '</strong></td>
            </tr>
            <tr>
                <td style="padding:6px 0;">PhilHealth (PHIC)</td>
                <td style="padding:6px 0;text-align:right;"><strong>₱' . number_format($phic, 2) . '</strong></td>
            </tr>
            <tr>
                <td style="padding:6px 0;">Pag-IBIG (HDMF)</td>
                <td style="padding:6px 0;text-align:right;"><strong>₱' . number_format($hdmf, 2) . '</strong></td>
            </tr>
            <tr>
                <td style="padding:6px 0;">Government Loan</td>
                <td style="padding:6px 0;text-align:right;"><strong>₱' . number_format($govt, 2) . '</strong></td>
            </tr>';
}

if ($lateDeduction > 0) {
    $html .= '<tr>
                <td style="padding:6px 0;">Late Deduction</td>
                <td style="padding:6px 0;text-align:right;"><strong>₱' . number_format($lateDeduction, 2) . '</strong></td>
            </tr>';
}

if ($shortage > 0) {
    $html .= '<tr>
                <td style="padding:6px 0;">Shortage</td>
                <td style="padding:6px 0;text-align:right;"><strong>₱' . number_format($shortage, 2) . '</strong></td>
            </tr>';
}

if ($caUniform > 0) {
    $html .= '<tr>
                <td style="padding:6px 0;">CA/Uniform</td>
                <td style="padding:6px 0;text-align:right;"><strong>₱' . number_format($caUniform, 2) . '</strong></td>
            </tr>';
}

$html .= '
            <tr style="border-top:2px solid #e0e0e0;">
                <td style="padding:10px 0;font-size:15px;"><strong>TOTAL DEDUCTIONS</strong></td>
                <td style="padding:10px 0;text-align:right;font-size:15px;color:#f44336;"><strong>₱' . number_format($totalDeductions, 2) . '</strong></td>
            </tr>
        </table>
    </div>

    <div style="background:linear-gradient(135deg, #2d6cdf 0%, #1e4db7 100%);padding:20px;border-radius:8px;text-align:center;margin-bottom:15px;">
        <p style="color:#fff;margin:0;font-size:14px;font-weight:500;">NET INCOME</p>
        <h2 style="color:#fff;margin:10px 0 0 0;font-size:32px;font-weight:bold;">₱' . number_format($net, 2) . '</h2>
    </div>

    <div style="text-align:center;padding-top:15px;border-top:1px solid #ddd;">
        <p style="color:#888;font-size:12px;margin:5px 0;">This is a computer-generated payslip. No signature required.</p>
        <p style="color:#999;font-size:11px;margin:5px 0;">Generated on ' . date('F d, Y \a\t h:i A') . '</p>
        <p style="color:#999;font-size:11px;margin:5px 0;">Employee Management System</p>
    </div>
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
        $mail->Subject = 'Payslip for ' . $weekLabel . ' - ' . $empName;
        $mail->Body = $html;
        $mail->AltBody = strip_tags($html);

        $mail->send();
        echo json_encode([
            'success' => true,
            'status' => 'success', 
            'message' => "✅ Payslip successfully emailed to $empName at $email."
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'status' => 'error', 
            'message' => '❌ Failed to send email: ' . $mail->ErrorInfo
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'status' => 'error', 
        'message' => "❌ No email address found for $empName."
    ]);
}
?>