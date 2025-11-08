<?php
// send_bulk_payslips.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once(__DIR__ . '/fpdf/fpdf.php');
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';
// Database connection
$conn = new mysqli("localhost", "root", "", "act05");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get POST data
$employees = isset($_POST['employees']) ? $_POST['employees'] : [];
$weekLabel = $_POST['weekLabel'] ?? '';
$weekStart = $_POST['weekStart'] ?? '';
$weekEnd = $_POST['weekEnd'] ?? '';
$selectedWeek = isset($_POST['selectedWeek']) ? (int)$_POST['selectedWeek'] : 1;

if (empty($employees)) {
    die("No employees selected.");
}

$successCount = 0;
$failedEmails = [];
$results = [];

foreach ($employees as $empData) {
    $empInfo = json_decode($empData, true);
    if (!$empInfo) continue;
    
    $empName = $empInfo['name'];
    $empID = $empInfo['empID'];
    $empEmail = $empInfo['email'];
    
    if (empty($empEmail)) {
        $failedEmails[] = "$empName (No email address)";
        continue;
    }
    
    // Generate PDF for this employee
    try {
        $pdfContent = generatePayslipPDF($empName, $empID, $weekLabel, $weekStart, $weekEnd, $selectedWeek, $conn);
        
        // Send email with PDF attachment
        $emailSent = sendPayslipEmail($empName, $empEmail, $weekLabel, $pdfContent);
        
        if ($emailSent) {
            $successCount++;
            $results[] = "✓ $empName - Sent to $empEmail";
        } else {
            $failedEmails[] = "$empName ($empEmail)";
            $results[] = "✗ $empName - Failed to send";
        }
    } catch (Exception $e) {
        $failedEmails[] = "$empName - " . $e->getMessage();
        $results[] = "✗ $empName - Error: " . $e->getMessage();
    }
}


// Display results
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payslip Email Results</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .result-container {
            background: white;
            border-radius: 15px;
            padding: 40px;
            max-width: 700px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .success-icon {
            color: #28a745;
            font-size: 64px;
        }
        .warning-icon {
            color: #ffc107;
            font-size: 64px;
        }
        .result-item {
            padding: 10px;
            margin: 5px 0;
            border-radius: 6px;
            font-family: monospace;
        }
        .result-success {
            background: #d4edda;
            color: #155724;
        }
        .result-failed {
            background: #f8d7da;
            color: #721c24;
        }
    </style>
</head>
<body>
    <div class="result-container">
        <div class="text-center mb-4">
            <?php if (empty($failedEmails)): ?>
                <i class="bi bi-check-circle-fill success-icon"></i>
                <h2 class="mt-3">All Payslips Sent Successfully!</h2>
                <p class="text-muted">Sent <?= $successCount ?> payslip(s)</p>
            <?php else: ?>
                <i class="bi bi-exclamation-triangle-fill warning-icon"></i>
                <h2 class="mt-3">Payslips Sent with Some Errors</h2>
                <p class="text-muted">Successfully sent: <?= $successCount ?> | Failed: <?= count($failedEmails) ?></p>
            <?php endif; ?>
        </div>
        
        <div class="mb-4">
            <h5>Details:</h5>
            <?php foreach ($results as $result): ?>
                <?php 
                $isSuccess = strpos($result, '✓') !== false;
                $class = $isSuccess ? 'result-success' : 'result-failed';
                ?>
                <div class="result-item <?= $class ?>"><?= htmlspecialchars($result) ?></div>
            <?php endforeach; ?>
        </div>
        
        <?php if (!empty($failedEmails)): ?>
            <div class="alert alert-danger">
                <strong>Failed to send to:</strong>
                <ul class="mb-0 mt-2">
                    <?php foreach ($failedEmails as $failed): ?>
                        <li><?= htmlspecialchars($failed) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <div class="text-center mt-4">
            <a href="salary_summary.php?week=<?= $selectedWeek ?>" class="btn btn-primary">
                <i class="bi bi-arrow-left"></i> Back to Salary Summary
            </a>
        </div>
    </div>
</body>
</html>

<?php

// ============================================
// FUNCTION: Generate Payslip PDF
// ============================================
function generatePayslipPDF($empName, $empID, $weekLabel, $weekStart, $weekEnd, $selectedWeek, $conn) {
    $nameEsc = $conn->real_escape_string($empName);
    
 // ✅ Fetch employee and timesheet data (EXACT SAME QUERY as salary_summary.php)
$sql = "
SELECT 
    e.EmpID,
    t.Name,
    t.ShiftDate,
    t.ShiftNo,
    t.DutyType,
    t.Hours,
    t.Business_Unit,
    t.Role,
    t.TimeIN,
    t.TimeOUT,
    t.Notes,
    t.Deductions,
    e.Rate,
    e.SSS,
    e.PHIC,
    e.HDMF,
    e.GOVT,
    h.Type AS HolidayType,
    h.Rate AS HolidayRate,
    e.Email
FROM timesheet t
JOIN employees e ON t.Name = e.Name
LEFT JOIN holidays h ON t.ShiftDate = h.Date
WHERE t.Name = '$nameEsc' AND t.ShiftDate BETWEEN '$weekStart' AND '$weekEnd'
ORDER BY t.ShiftDate
";

$result = $conn->query($sql);

if (!$result || $result->num_rows === 0) {
    http_response_code(404);
    exit("⚠️ No records found for employee: $empName ($weekStart to $weekEnd)");
}

// ✅ Initialize employee data array (MATCHING salary_summary.php structure)
$emp = [
    'EmpID' => '',
    'Email' => '',
    'Rate' => 0,
    'DaysWorked' => [],
    'SILDays' => 0,
    'SILDates' => [],
    'NightShifts' => 0,
    'NightDates' => [],
    'HolidayPay' => 0,
    'HolidayDates' => [],
    'AutoHolidayGiven' => [],
    'OvertimeHrs' => 0,
    'OvertimeDates' => [],
    'SILBonus' => 0,
    'Shortage' => 0,
    'ShortageDates' => [],
    'CAUniform' => 0,
    'CAUniformDates' => [],
    'SSS' => 0,
    'PHIC' => 0,
    'HDMF' => 0,
    'GOVT' => 0,
    'LateDeduction' => 0,
    'LateDates' => []
];

$role = '';

// ✅ EXACT SAME PROCESSING LOGIC as salary_summary.php
while ($row = $result->fetch_assoc()) {
    $emp['EmpID'] = $row['EmpID'];
    $emp['Email'] = $row['Email'];
    $emp['Rate'] = (float)$row['Rate'];
    $emp['SSS'] = (float)$row['SSS'];
    $emp['PHIC'] = (float)$row['PHIC'];
    $emp['HDMF'] = (float)$row['HDMF'];
    $emp['GOVT'] = (float)$row['GOVT'];
    $role = strtolower(trim($row['Role']));
    
    $rate = (float)$row['Rate'];
    $shiftDate = $row['ShiftDate'];
    $note = strtolower(trim($row['Notes']));
    $deductVal = isset($row['Deductions']) ? abs((float)str_replace('-', '', $row['Deductions'])) : 0;
    
    // ✅ Valid workday counting
    $validTime = '/^(?:[01]\d|2[0-3]):[0-5]\d:[0-5]\d$/';
    if (!empty($row['ShiftDate']) &&
        preg_match($validTime, trim($row['TimeIN'])) &&
        preg_match($validTime, trim($row['TimeOUT']))) {
        $emp['DaysWorked'][$row['ShiftDate']] = true;
    }
    
    // ✅ NIGHT DIFFERENTIAL
    $timeIn = strtotime($row['TimeIN']);
    $timeOut = strtotime($row['TimeOUT']);
    $isNightShift = (!empty($row['TimeIN']) && !empty($row['TimeOUT']) && $timeIn > $timeOut);
    $isSIL = ($note === 'sil');
    
    if (($isNightShift || $isSIL) && !in_array($shiftDate, $emp['NightDates'])) {
        $emp['NightShifts']++;
        $emp['NightDates'][] = $shiftDate;
    }
    
    // ✅ HOLIDAY PAY (matching salary_summary.php logic)
    $worked = !empty($row['TimeIN']) && $row['TimeIN'] != '00:00:00';
    
    // Get regular holidays
    $regularHolidays = [];
    $holidayQuery = $conn->query("
        SELECT Date 
        FROM holidays 
        WHERE Type = 'Regular Holiday'
        AND Date BETWEEN '$weekStart' AND '$weekEnd'
    ");
    if ($holidayQuery && $holidayQuery->num_rows > 0) {
        while ($h = $holidayQuery->fetch_assoc()) {
            $regularHolidays[] = $h['Date'];
        }
    }
    
    if (!empty($row['HolidayType'])) {
        $holidayType = strtolower(trim($row['HolidayType']));
        $holidayPay = 0;
        
        if ($holidayType === 'special holiday' || $holidayType === 'special non-working holiday') {
            if ($worked) {
                $holidayPay = $rate * 0.3;
            } else {
                $holidayPay = 0;
            }
        }
        
        if (!in_array($shiftDate, $emp['HolidayDates'])) {
            $emp['HolidayPay'] += $holidayPay;
            $emp['HolidayDates'][] = $shiftDate;
        }
    } else {
        // Auto-grant for regular holidays
        foreach ($regularHolidays as $rDate) {
            if (!in_array($rDate, $emp['AutoHolidayGiven']) && 
                !in_array($rDate, $emp['HolidayDates'])) {
                $emp['HolidayPay'] += $rate * 1.0;
                $emp['AutoHolidayGiven'][] = $rDate;
            }
        }
    }
    
    // ✅ OVERTIME
    if (strcasecmp(trim($row['DutyType']), 'Overtime') === 0 &&
        is_numeric($row['Hours']) &&
        !in_array($shiftDate, $emp['OvertimeDates'])) {
        $emp['OvertimeHrs'] += (float)$row['Hours'];
        $emp['OvertimeDates'][] = $shiftDate;
    }
    
    // ✅ LATE DEDUCTION
    if (strtolower(trim($row['DutyType'])) === 'late' &&
        !in_array($shiftDate, $emp['LateDates'])) {
        $emp['LateDeduction'] += 150;
        $emp['LateDates'][] = $shiftDate;
    }
    
    // ✅ SIL BONUS
    if ($note === 'sil' && !in_array($shiftDate, $emp['SILDates'])) {
        $emp['SILBonus'] += $rate;
        $emp['SILDays']++;
        $emp['SILDates'][] = $shiftDate;
    }
    
    // ✅ SHORTAGE
    if ($note === 'short' && !in_array($shiftDate, $emp['ShortageDates'])) {
        $emp['Shortage'] += $deductVal;
        $emp['ShortageDates'][] = $shiftDate;
    }
    
    // ✅ CA/UNIFORM
    if (($note === 'uniform' || $note === 'ca') && 
        !in_array($shiftDate, $emp['CAUniformDates'])) {
        if ($note === 'uniform') {
            $emp['CAUniform'] += 106;
        } elseif ($note === 'ca') {
            $emp['CAUniform'] += 500;
        }
        $emp['CAUniformDates'][] = $shiftDate;
    }
}

// ✅ COUNT DAYS WORKED (EXACT SAME QUERY as salary_summary.php)
$numDayQuery = "
    SELECT COUNT(DISTINCT ShiftDate) AS daysWorked
    FROM timesheet
    WHERE TimeIN != '00:00:00'
      AND TimeOUT != '00:00:00'
      AND Name = '$nameEsc'
      AND (DutyType != 'Overtime' OR DutyType IS NULL)
      AND ShiftDate BETWEEN '$weekStart' AND '$weekEnd'
";
$out = $conn->query($numDayQuery);
$row = $out ? $out->fetch_assoc() : ['daysWorked' => 0];
$days = (int)$row['daysWorked'];

$rate = (float)$emp['Rate'];
$rate2 = $rate / 8;
$overtimePay = $emp['OvertimeHrs'] * $rate2;

// ✅ ALLOWANCE COMPUTATION (EXACT SAME as salary_summary.php)
$roleLower = strtolower($role);
$allowance = 0;

if ($roleLower === 'cashier') {
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

$conn->close();

// ✅ COMPUTATION SUMMARY (EXACT SAME as salary_summary.php)
$nightDiff = $emp['NightShifts'] * 52;
$holiday = $emp['HolidayPay'];
$silBonus = $emp['SILBonus'];
$shortage = $emp['Shortage'];
$caUniform = $emp['CAUniform'];
$lateDeduction = $emp['LateDeduction'];

$basePay = $rate * $days;
$gross = $basePay + $overtimePay + $allowance + $nightDiff + $holiday + $silBonus;
$sss = $emp['SSS'];
$phic = $emp['PHIC'];
$hdmf = $emp['HDMF'];
$govt = $emp['GOVT'];

// ✅ CONDITIONAL DEDUCTIONS BASED ON WEEK (EXACT SAME as salary_summary.php)
$sss_deduction = 0;
$phic_deduction = 0;
$hdmf_deduction = 0;
$govt_deduction = 0;

if ($selectedWeek == 2) {
    $sss_deduction = $sss;
} elseif ($selectedWeek == 3) {
    $phic_deduction = $phic;
    $hdmf_deduction = $hdmf;
} elseif ($selectedWeek == 4) {
    $govt_deduction = $govt;
} elseif ($selectedWeek == 5) {
    $sss_deduction = $sss;
    $phic_deduction = $phic;
    $hdmf_deduction = $hdmf;
    $govt_deduction = $govt;
}

$totalDeductions = $sss_deduction + $phic_deduction + $hdmf_deduction + $govt_deduction
    + $lateDeduction + $shortage + $caUniform;
$net = $gross - $totalDeductions;

 // ✅ Generate PDF
$pdf = new FPDF();
$pdf->AddPage();

// Header
$pdf->SetFont('Arial', 'B', 16);
$pdf->Cell(0, 12, 'PAYSLIP', 0, 1, 'C');
$pdf->SetFont('Arial', '', 9);
$pdf->Cell(0, 5, 'Employee Management System', 0, 1, 'C');
$pdf->Ln(8);

// Employee Info
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(40, 6, 'Name:', 0, 0);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(0, 6, $empName, 0, 1);

$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(40, 6, 'Employee ID:', 0, 0);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(0, 6, $empID, 0, 1);

$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(40, 6, 'Pay Period:', 0, 0);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(0, 6, $weekLabel, 0, 1);

$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(40, 6, 'Days Worked:', 0, 0);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(0, 6, $days, 0, 1);

$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(40, 6, 'Daily Rate:', 0, 0);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(0, 6, 'P ' . number_format($rate, 2), 0, 1);

$pdf->Ln(8);

// ✅ Earnings section
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 7, 'EARNINGS', 0, 1);
$pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());
$pdf->Ln(2);
$pdf->SetFont('Arial', '', 9);

$earnings = [
    'Daily Rate' => $rate,
    'Hourly Rate' => $rate2,
    'Days Worked' => $days . ' days',
    'Base Pay' => $basePay,
    'Overtime (' . $emp['OvertimeHrs'] . ' hrs)' => $overtimePay,
    'Allowance' => $allowance,
    'Night Differential' => $nightDiff,
    'Holiday Pay' => $holiday,
    'SIL (Paid Leave)' => $silBonus
];

foreach ($earnings as $label => $value) {
    $pdf->Cell(130, 5, $label, 0, 0);
    if (is_numeric($value)) {
        $pdf->Cell(0, 5, 'P ' . number_format($value, 2), 0, 1, 'R');
    } else {
        $pdf->Cell(0, 5, $value, 0, 1, 'R');
    }
}

$pdf->Ln(3);
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetFillColor(230, 230, 230);
$pdf->Cell(130, 6, 'GROSS INCOME', 0, 0, 'L', true);
$pdf->Cell(0, 6, 'P ' . number_format($gross, 2), 0, 1, 'R', true);
$pdf->Ln(8);

// ✅ Deductions section
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 7, 'DEDUCTIONS', 0, 1);
$pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());
$pdf->Ln(2);
$pdf->SetFont('Arial', '', 9);

$deductions = [];

if ($selectedWeek == 2 || $selectedWeek == 5) {
    $deductions['SSS'] = $sss_deduction;
}
if ($selectedWeek == 3 || $selectedWeek == 5) {
    $deductions['PhilHealth (PHIC)'] = $phic_deduction;
    $deductions['Pag-IBIG (HDMF)'] = $hdmf_deduction;
}
if ($selectedWeek == 4 || $selectedWeek == 5) {
    $deductions['Government Loan'] = $govt_deduction;
}

$deductions['Late Deduction'] = $lateDeduction;
$deductions['Shortage'] = $shortage;
$deductions['CA/Uniform'] = $caUniform;

$hasDeductions = false;
foreach ($deductions as $label => $value) {
    if ($value > 0) {
        $hasDeductions = true;
        $pdf->Cell(130, 5, $label, 0, 0);
        $pdf->Cell(0, 5, 'P ' . number_format($value, 2), 0, 1, 'R');
    }
}

if (!$hasDeductions) {
    $pdf->SetFont('Arial', 'I', 9);
    $pdf->Cell(0, 5, 'No deductions for this pay period', 0, 1, 'C');
}

$pdf->Ln(3);
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetFillColor(230, 230, 230);
$pdf->Cell(130, 6, 'TOTAL DEDUCTIONS', 0, 0, 'L', true);
$pdf->Cell(0, 6, 'P ' . number_format($totalDeductions, 2), 0, 1, 'R', true);
$pdf->Ln(8);

// ✅ Net income
$pdf->SetFont('Arial', 'B', 12);
$pdf->SetFillColor(76, 175, 80);
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell(130, 8, 'NET INCOME', 0, 0, 'L', true);
$pdf->Cell(0, 8, 'P ' . number_format($net, 2), 0, 1, 'R', true);

$pdf->Ln(10);
$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('Arial', 'I', 8);
$pdf->Cell(0, 5, 'This is a computer-generated document. No signature required.', 0, 1, 'C');
$pdf->Cell(0, 5, 'Generated on: ' . date('F d, Y h:i A'), 0, 1, 'C');

    return $pdf->Output('S'); // Return PDF as string
}

// ============================================
// FUNCTION: Calculate Allowance
// ============================================
function calculateAllowance($nameEsc, $role, $rate, $weekStart, $weekEnd, $conn) {
    $allowance = 0;
    $roleLower = strtolower($role);
    
    if ($roleLower === 'cashier') {
        $query = "SELECT SUM(Hours) as totalHours FROM timesheet
                  WHERE Name = '$nameEsc' AND Role = 'Cashier'
                  AND ShiftDate BETWEEN '$weekStart' AND '$weekEnd'
                  AND TimeIN != '00:00:00' AND TimeOUT != '00:00:00'";
        $res = $conn->query($query);
        $totalHours = $res ? (float)$res->fetch_assoc()['totalHours'] : 0;
        $allowance = round($totalHours * 5, 2);
    }
    
    if ($rate > 520) {
        $query = "SELECT COUNT(DISTINCT ShiftDate) as workDays FROM timesheet
                  WHERE Name = '$nameEsc' AND DutyType = 'OnDuty'
                  AND ShiftDate BETWEEN '$weekStart' AND '$weekEnd'
                  AND TimeIN != '00:00:00' AND TimeOUT != '00:00:00'";
        $res = $conn->query($query);
        $workDays = $res ? (int)$res->fetch_assoc()['workDays'] : 0;
        $allowance += $workDays * 20;
    }
    
    return $allowance;
}

// ============================================
// FUNCTION: Send Email with PDF
// ============================================
function sendPayslipEmail($empName, $email, $weekLabel, $pdfContent) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'angelica_g_gregorio@dlsu.edu.ph';
        $mail->Password = 'cgfi anha jgka oiqp'; // ❗ ideally load from config
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->setFrom('hr@company.com', 'HR Department');
        $mail->addAddress($email, $empName);
        $mail->Subject = "Your Payslip for $weekLabel";
        $mail->Body = "Dear $empName,\n\nPlease find attached your payslip.\n\nBest,\nHR Department";
        $mail->addStringAttachment($pdfContent, "payslip_" . str_replace(' ', '_', $empName) . ".pdf");

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email to $email failed: " . $mail->ErrorInfo);
        return false;
    }
}
?>