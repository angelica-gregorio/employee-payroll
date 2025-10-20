<?php
// ✅ Enable error reporting during development
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ✅ Clear output buffer (prevents PDF corruption)
if (ob_get_level()) ob_end_clean();

// ✅ Include FPDF
require_once(__DIR__ . '/fpdf/fpdf.php');

// ✅ Connect to DB
$conn = new mysqli("localhost", "root", "", "act05");
if ($conn->connect_error) {
    http_response_code(500);
    exit("Database connection failed: " . $conn->connect_error);
}

// ✅ Get POST data
$empName   = trim($_POST['empName'] ?? '');
$empID     = trim($_POST['empID'] ?? '');
$weekLabel = trim($_POST['weekLabel'] ?? '');
$weekStart = trim($_POST['weekStart'] ?? '');
$weekEnd   = trim($_POST['weekEnd'] ?? '');

if (!$empName || !$weekStart || !$weekEnd) {
    http_response_code(400);
    exit("❌ Missing required data (empName, weekStart, weekEnd)");
}

// ✅ Fetch time & employee data
$sql = "
SELECT 
    t.ShiftDate, t.ShiftNo, t.DutyType, t.Hours, t.Role, t.TimeIN, t.TimeOUT,
    t.Notes, t.Deductions,
    e.Rate, e.SSS, e.PHIC, e.HDMF, e.GOVT,
    h.Type AS HolidayType
FROM timesheet t
JOIN employees e ON t.Name = e.Name
LEFT JOIN holidays h ON t.ShiftDate = h.Date
WHERE t.Name = ? AND t.ShiftDate BETWEEN ? AND ?
ORDER BY t.ShiftDate;
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("sss", $empName, $weekStart, $weekEnd);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(404);
    exit("⚠️ No records found for employee: $empName ($weekStart to $weekEnd)");
}

// ✅ Initialize counters
$daysWorked = 0;
$overtimeHrs = 0;
$nightShifts = 0;
$holidayPay = 0;
$cashierBonus = 0;
$silBonus = 0;
$lateDeduction = 0;
$shortage = 0;
$caUniform = 0;

$rate = $sss = $phic = $hdmf = $govt = 0;
$role = '';

$processedDates = [];

while ($row = $result->fetch_assoc()) {
    $rate = (float)$row['Rate'];
    $sss = (float)$row['SSS'];
    $phic = (float)$row['PHIC'];
    $hdmf = (float)$row['HDMF'];
    $govt = (float)$row['GOVT'];
    $role = strtolower(trim($row['Role']));

    $shiftDate = $row['ShiftDate'];
    $note = strtolower(trim($row['Notes']));

    // ✅ Count valid workday
    if (!in_array($shiftDate, $processedDates) && $row['TimeIN'] != '00:00:00' && $row['TimeOUT'] != '00:00:00') {
        $daysWorked++;
        $processedDates[] = $shiftDate;
    }

    // ✅ Overtime
    if (strcasecmp(trim($row['DutyType']), 'Overtime') === 0 && is_numeric($row['Hours'])) {
        $overtimeHrs += (float)$row['Hours'];
    }

    // ✅ Night Differential or SIL
    if ($note === 'sil' || (strtotime($row['TimeIN']) > strtotime($row['TimeOUT']))) {
        $nightShifts++;
    }

    // ✅ Holiday Pay
    if (!empty($row['HolidayType'])) {
        $holidayType = strtolower($row['HolidayType']);
        $holidayPay += ($holidayType === 'regular holiday') ? $rate * 1 : ($holidayType === 'special holiday' ? $rate * 0.3 : 0);
    }

    // ✅ Late Deductions
    if ($row['DutyType'] === 'Late') {
        $lateDeduction += 150;
    }

    // ✅ Shortage
    if ($note === 'short') {
        $shortage += abs((float)str_replace('-', '', $row['Deductions']));
    }

    // ✅ CA / Uniform
    if ($note === 'ca' || $note === 'uniform') {
        $caUniform += 106;
    }

    // ✅ Cashier Bonus
    if ($role === 'cashier' && (float)$row['Hours'] >= 8) {
        $cashierBonus += 40;
    }

    // ✅ SIL Bonus
    if ($note === 'sil') {
        $silBonus += $rate;
    }
}

$stmt->close();

// ✅ Compute allowance
$nameEsc = $conn->real_escape_string($empName);
$allowance = 0;

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
$workDays = $daysRes ? $daysRes->num_rows : 0;

if ($rate > 520) {
    $allowance = $workDays * 20;
}

if ($role === 'cashier') {
    $hoursRes = $conn->query("
        SELECT SUM(Hours) AS totalHours
        FROM timesheet
        WHERE Name = '$nameEsc'
          AND Role = 'Cashier'
          AND ShiftDate BETWEEN '$weekStart' AND '$weekEnd'
    ");
    $hoursRow = $hoursRes->fetch_assoc();
    $totalHours = (float)($hoursRow['totalHours'] ?? 0);
    $allowance += round($totalHours * 5, 2);
}

$conn->close();

// ✅ Payroll computation
$basePay = $rate * $daysWorked;
$ratePerHour = $rate / 8;
$overtimePay = $overtimeHrs * $ratePerHour;
$nightDiff = $nightShifts * 52;

// ✅ Check if cutoff includes 24–30
$cutoffStart = (int)date('d', strtotime($weekStart));
$cutoffEnd   = (int)date('d', strtotime($weekEnd));
$includeGovt = false;
for ($d = $cutoffStart; $d <= $cutoffEnd; $d++) {
    if ($d >= 24 && $d <= 30) { $includeGovt = true; break; }
}

// ✅ Compute totals
$gross = $basePay + $overtimePay + $allowance + $nightDiff + $holidayPay + $cashierBonus + $silBonus;

if ($includeGovt) {
    $totalDeductions = $sss + $phic + $hdmf + $govt + $lateDeduction + $shortage + $caUniform;
} else {
    $totalDeductions = $lateDeduction + $shortage + $caUniform;
}

$net = $gross - $totalDeductions;

// ✅ Generate PDF
$pdf = new FPDF();
$pdf->AddPage();

$pdf->SetFont('Arial', 'B', 16);
$pdf->Cell(0, 12, 'PAYSLIP', 0, 1, 'C');
$pdf->SetFont('Arial', '', 9);
$pdf->Cell(0, 5, 'Employee Management System', 0, 1, 'C');
$pdf->Ln(8);

$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(40, 6, 'Name:', 0, 0);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(0, 6, $empName, 0, 1);

$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(40, 6, 'ID:', 0, 0);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(0, 6, $empID, 0, 1);

$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(40, 6, 'Period:', 0, 0);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(0, 6, $weekLabel, 0, 1);

$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(40, 6, 'Days Worked:', 0, 0);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(0, 6, $daysWorked, 0, 1);

$pdf->Ln(8);

// ✅ Earnings section
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 7, 'EARNINGS', 0, 1);
$pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());
$pdf->SetFont('Arial', '', 9);

$earnings = [
    'Base Pay' => $basePay,
    "Overtime ({$overtimeHrs} hrs)" => $overtimePay,
    'Allowance' => $allowance,
    'Night Diff' => $nightDiff,
    'Holiday Pay' => $holidayPay,
    'Cashier Bonus' => $cashierBonus,
    'SIL Bonus' => $silBonus
];
foreach ($earnings as $label => $value) {
    $pdf->Cell(100, 5, $label, 0, 0);
    $pdf->Cell(0, 5, 'P ' . number_format($value, 2), 0, 1, 'R');
}

$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(100, 6, 'GROSS INCOME', 0, 0);
$pdf->Cell(0, 6, 'P ' . number_format($gross, 2), 0, 1, 'R');
$pdf->Ln(8);

// ✅ Deductions section
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 7, 'DEDUCTIONS', 0, 1);
$pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());
$pdf->SetFont('Arial', '', 9);

$deductions = [
    'Late Deduction' => $lateDeduction,
    'Shortage' => $shortage,
    'CA/Uniform' => $caUniform
];

if ($includeGovt) {
    $deductions = array_merge([
        'SSS' => $sss,
        'PHIC' => $phic,
        'HDMF' => $hdmf,
        'GOVT Loan' => $govt
    ], $deductions);
}

foreach ($deductions as $label => $value) {
    $pdf->Cell(100, 5, $label, 0, 0);
    $pdf->Cell(0, 5, 'P ' . number_format($value, 2), 0, 1, 'R');
}

$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(100, 6, 'TOTAL DEDUCTIONS', 0, 0);
$pdf->Cell(0, 6, 'P ' . number_format($totalDeductions, 2), 0, 1, 'R');
$pdf->Ln(8);

// ✅ Net income
$pdf->SetFont('Arial', 'B', 12);
$pdf->SetFillColor(76, 175, 80);
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell(100, 8, 'NET INCOME', 0, 0, 'L', true);
$pdf->Cell(0, 8, 'P ' . number_format($net, 2), 0, 1, 'R', true);

$fileName = 'Payslip_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $empName) . '_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $weekLabel);
$pdf->Output('D', $fileName . '.pdf');
exit;
?>
