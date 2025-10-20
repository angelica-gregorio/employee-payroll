<?php
// ✅ Debug mode: enable during testing
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ✅ Clear any previous output buffer to prevent PDF corruption
if (ob_get_level()) { ob_end_clean(); }

// ✅ Include FPDF library
require_once(__DIR__ . '/fpdf/fpdf.php');

// ✅ Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "act05";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    http_response_code(500);
    exit('Database connection failed: ' . $conn->connect_error);
}

// ✅ Retrieve POST data
$empName   = trim($_POST['empName'] ?? '');
$empID     = trim($_POST['empID'] ?? '');
$weekLabel = trim($_POST['weekLabel'] ?? '');
$weekStart = trim($_POST['weekStart'] ?? '');
$weekEnd   = trim($_POST['weekEnd'] ?? '');

// ✅ Validate input
if (!$empName || !$weekStart || !$weekEnd) {
    http_response_code(400);
    exit('❌ Missing required data (empName, weekStart, weekEnd)');
}

// ✅ Prepare SQL Query
$sql = "SELECT 
    t.ShiftDate,
    t.ShiftNo,
    t.DutyType,
    t.Hours,
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
    h.Type AS HolidayType
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
    http_response_code(404);
    exit("⚠️ No records found for employee: $empName ($weekStart to $weekEnd)");
}

// ✅ Initialize totals
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
$processedDates = [];

while ($row = $result->fetch_assoc()) {
    $rate = (float)$row['Rate'];
    $sss = (float)$row['SSS'];
    $phic = (float)$row['PHIC'];
    $hdmf = (float)$row['HDMF'];
    $govt = (float)$row['GOVT'];

    $shiftDate = $row['ShiftDate'];
    $validTime = '/^(?:[01]\d|2[0-3]):[0-5]\d:[0-5]\d$/';

    // ✅ Count days worked once per date
    if (preg_match($validTime, $row['TimeIN']) && preg_match($validTime, $row['TimeOUT'])) {
        if (!in_array($shiftDate, $processedDates)) {
            $daysWorked++;
            $processedDates[] = $shiftDate;
        }
    }

    // ✅ Night shift detection
    if ((int)$row['ShiftNo'] >= 3) {
        $nightShifts++;
    }

    // ✅ Holiday pay computation
    if (!empty($row['HolidayType'])) {
        $holidayType = strtolower(trim($row['HolidayType']));
        if ($holidayType === 'regular holiday') {
            $holidayPay += $rate;
        } elseif ($holidayType === 'special holiday') {
            $holidayPay += $rate * 0.3;
        }
    }

    // ✅ Overtime
    if (strcasecmp($row['DutyType'], 'Overtime') === 0 && is_numeric($row['Hours'])) {
        $overtimeHrs += (float)$row['Hours'];
    }

    // ✅ Late deduction
    if (strtolower($row['DutyType']) === 'late') {
        $lateDeduction += 150;
    }

    // ✅ Cashier bonus
    if (strtolower($row['Role']) === 'cashier' && (float)$row['Hours'] >= 8) {
        $cashierBonus += 40;
    }

    // ✅ SIL
    if (strtolower($row['Notes']) === 'sil') {
        $silBonus += $rate;
    }

    // ✅ Shortage
    if (strtolower($row['Notes']) === 'short') {
        $shortage += abs((float)str_replace('-', '', $row['Deductions']));
    }

    // ✅ CA / Uniform
    if (in_array(strtolower($row['Notes']), ['ca', 'uniform'])) {
        $caUniform += 106;
    }
}

$stmt->close();
$conn->close();

// ✅ Computations
$basePay = $rate * $daysWorked;
$ratePerHour = $rate / 8;
$overtimePay = $overtimeHrs * $ratePerHour;
$allowance = ($rate > 520) ? ($daysWorked * 20) : 0;
$nightDiff = $nightShifts * 52;

$gross = $basePay + $overtimePay + $allowance + $nightDiff + $holidayPay + $cashierBonus + $silBonus;
$totalDeductions = $sss + $phic + $hdmf + $govt + $lateDeduction + $shortage + $caUniform;
$net = $gross - $totalDeductions;

// ✅ Create PDF
$pdf = new FPDF();
$pdf->AddPage();

// Header
$pdf->SetFont('Arial', 'B', 16);
$pdf->Cell(0, 12, 'PAYSLIP', 0, 1, 'C');
$pdf->SetFont('Arial', '', 9);
$pdf->Cell(0, 5, 'Employee Management System', 0, 1, 'C');
$pdf->Ln(8);

// Employee details
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

// Earnings section
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

// Deductions section
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 7, 'DEDUCTIONS', 0, 1);
$pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());
$pdf->SetFont('Arial', '', 9);

$deductions = [
    'SSS' => $sss,
    'PHIC' => $phic,
    'HDMF' => $hdmf,
    'GOVT' => $govt,
    'Late Deduction' => $lateDeduction,
    'Shortage' => $shortage,
    'CA/Uniform' => $caUniform
];

foreach ($deductions as $label => $value) {
    $pdf->Cell(100, 5, $label, 0, 0);
    $pdf->Cell(0, 5, 'P ' . number_format($value, 2), 0, 1, 'R');
}

$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(100, 6, 'TOTAL DEDUCTIONS', 0, 0);
$pdf->Cell(0, 6, 'P ' . number_format($totalDeductions, 2), 0, 1, 'R');
$pdf->Ln(8);

// Net income section
$pdf->SetFont('Arial', 'B', 12);
$pdf->SetFillColor(76, 175, 80);
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell(100, 8, 'NET INCOME', 0, 0, 'L', true);
$pdf->Cell(0, 8, 'P ' . number_format($net, 2), 0, 1, 'R', true);

$fileName = 'Payslip_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $empName) . '_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $weekLabel);

// ✅ Output PDF properly
$pdf->Output('D', $fileName . '.pdf');
exit;
?>
