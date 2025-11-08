<?php
// view_payslip.php
// Displays the computed payslip directly in the browser (no email, no PDF)

// --- Database connection ---
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "act05";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("<h3>Database connection failed: " . $conn->connect_error . "</h3>");
}

// --- Retrieve GET parameters from JS redirect ---
$empName   = trim($_GET['empName'] ?? '');
$empID     = trim($_GET['empID'] ?? '');
$weekLabel = trim($_GET['weekLabel'] ?? '');
$weekStart = trim($_GET['weekStart'] ?? '');
$weekEnd   = trim($_GET['weekEnd'] ?? '');

if (!$empName || !$weekStart || !$weekEnd) {
    die("<h3>Missing required data (empName, weekStart, weekEnd)</h3>");
}

// --- Fetch timesheet + employee data ---
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
    die("<h3>No records found for $empName ($weekStart → $weekEnd)</h3>");
}

// --- Initialize variables ---
$processedDates = $overtimeDates = $nightDates = $holidayDates = $lateDates = $shortageDates = $caUniformDates = $silDates = [];
$overtimeHrs = $nightShifts = $holidayPay = $silBonus = $lateDeduction = $shortage = $caUniform = 0;
$rate = $sss = $phic = $hdmf = $govt = 0;
$email = $role = "";

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

    $validTime = '/^(?:[01]\d|2[0-3]):[0-5]\d:[0-5]\d$/';
    if (!in_array($shiftDate, $processedDates) && 
        preg_match($validTime, trim($row['TimeIN'])) && 
        preg_match($validTime, trim($row['TimeOUT'])) &&
        $row['TimeIN'] != '00:00:00' && 
        $row['TimeOUT'] != '00:00:00') {
        $processedDates[] = $shiftDate;
    }

    if (!in_array($shiftDate, $overtimeDates) &&
        strcasecmp($dutyType, 'Overtime') === 0 && 
        is_numeric($row['Hours'])) {
        $overtimeHrs += (float)$row['Hours'];
        $overtimeDates[] = $shiftDate;
    }

    $timeIn = strtotime($row['TimeIN']);
    $timeOut = strtotime($row['TimeOUT']);
    $isNightShift = (!empty($row['TimeIN']) && 
                     !empty($row['TimeOUT']) && 
                     $timeIn > $timeOut);
    $isSIL = ($note === 'sil');
    if (!in_array($shiftDate, $nightDates) && ($isNightShift || $isSIL)) {
        $nightShifts++;
        $nightDates[] = $shiftDate;
    }

    if (!in_array($shiftDate, $holidayDates) && !empty($row['HolidayType'])) {
        $holidayType = strtolower(trim($row['HolidayType']));
        if ($holidayType === 'regular holiday') $holidayPay += $rate * 1;
        elseif ($holidayType === 'special holiday') $holidayPay += $rate * 0.3;
        $holidayDates[] = $shiftDate;
    }

    if (!in_array($shiftDate, $lateDates) && strtolower($dutyType) === 'late') {
        $lateDeduction += 150;
        $lateDates[] = $shiftDate;
    }

    if (!in_array($shiftDate, $shortageDates) && $note === 'short') {
        $deductVal = isset($row['Deductions']) 
            ? abs((float)str_replace('-', '', $row['Deductions'])) 
            : 0;
        $shortage += $deductVal;
        $shortageDates[] = $shiftDate;
    }

    if (!in_array($shiftDate, $caUniformDates) && ($note === 'ca' || $note === 'uniform')) {
        $caUniform += 106;
        $caUniformDates[] = $shiftDate;
    }

    if (!in_array($shiftDate, $silDates) && $note === 'sil') {
        $silBonus += $rate;
        $silDates[] = $shiftDate;
    }
}

$daysWorked = count($processedDates);
$stmt->close();

// --- Compute Allowance ---
$nameEsc = $conn->real_escape_string($empName);
$allowance = 0;

if ($role === 'cashier') {
    $uniqueShiftsQuery = "
        SELECT DISTINCT ShiftDate, Hours
        FROM timesheet
        WHERE Name = '$nameEsc'
          AND Role = 'Cashier'
          AND ShiftDate BETWEEN '$weekStart' AND '$weekEnd'
          AND TimeIN != '00:00:00' AND TimeOUT != '00:00:00'";
    $resShifts = $conn->query($uniqueShiftsQuery);
    $totalHours = 0;
    if ($resShifts && $resShifts->num_rows > 0) {
        while ($tsRow = $resShifts->fetch_assoc()) {
            $totalHours += (float)$tsRow['Hours'];
        }
    }
    $cashierBonus = round($totalHours * 5, 2);
    $dailyAllowance = ($rate > 520) ? ($daysWorked * 20) : 0;
    $allowance = $cashierBonus + $dailyAllowance;
} else {
    $allowance = ($rate > 520) ? ($daysWorked * 20) : 0;
}

$conn->close();

// --- Compute Totals ---
$basePay = $rate * $daysWorked;
$ratePerHour = $rate / 8;
$overtimePay = $overtimeHrs * $ratePerHour;
$nightDiff = $nightShifts * 52;

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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payslip - <?= htmlspecialchars($empName) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background:#f5f7fa; padding:30px; font-family: Arial, sans-serif; }
        .card { border-radius:12px; box-shadow:0 2px 10px rgba(0,0,0,0.1); }
        .card h5 { color:#2d6cdf; }
        table td { padding:6px 0; }
    </style>
</head>
<body>

<div class="container">
    <div class="card p-4 mb-4">
        <h3 class="text-center mb-2">Employee Payslip</h3>
        <p class="text-center text-muted">
            Pay Period: <strong><?= htmlspecialchars($weekLabel) ?></strong><br>
            (<?= htmlspecialchars($weekStart) ?> → <?= htmlspecialchars($weekEnd) ?>)
        </p>
    </div>

    <div class="card p-4 mb-4">
        <h5>Employee Information</h5>
        <table class="table table-borderless">
            <tr><td><strong>Name:</strong></td><td><?= htmlspecialchars($empName) ?></td></tr>
            <tr><td><strong>Employee ID:</strong></td><td><?= htmlspecialchars($empID) ?></td></tr>
            <tr><td><strong>Days Worked:</strong></td><td><?= $daysWorked ?></td></tr>
            <tr><td><strong>Daily Rate:</strong></td><td>₱<?= number_format($rate, 2) ?></td></tr>
        </table>
    </div>

    <div class="card p-4 mb-4">
        <h5>Earnings</h5>
        <table class="table table-borderless">
            <tr><td>Base Pay (<?= $daysWorked ?> days)</td><td class="text-end">₱<?= number_format($basePay, 2) ?></td></tr>
            <?php if ($overtimePay > 0): ?><tr><td>Overtime</td><td class="text-end">₱<?= number_format($overtimePay, 2) ?></td></tr><?php endif; ?>
            <?php if ($allowance > 0): ?><tr><td>Allowance</td><td class="text-end">₱<?= number_format($allowance, 2) ?></td></tr><?php endif; ?>
            <?php if ($nightDiff > 0): ?><tr><td>Night Differential</td><td class="text-end">₱<?= number_format($nightDiff, 2) ?></td></tr><?php endif; ?>
            <?php if ($holidayPay > 0): ?><tr><td>Holiday Pay</td><td class="text-end">₱<?= number_format($holidayPay, 2) ?></td></tr><?php endif; ?>
            <?php if ($silBonus > 0): ?><tr><td>SIL Bonus</td><td class="text-end">₱<?= number_format($silBonus, 2) ?></td></tr><?php endif; ?>
            <tr class="border-top"><td><strong>GROSS INCOME</strong></td><td class="text-end text-success"><strong>₱<?= number_format($gross, 2) ?></strong></td></tr>
        </table>
    </div>

    <div class="card p-4 mb-4">
        <h5>Deductions</h5>
        <table class="table table-borderless">
            <?php if ($includeGovtDeductions): ?>
                <tr><td>SSS</td><td class="text-end">₱<?= number_format($sss, 2) ?></td></tr>
                <tr><td>PhilHealth</td><td class="text-end">₱<?= number_format($phic, 2) ?></td></tr>
                <tr><td>Pag-IBIG</td><td class="text-end">₱<?= number_format($hdmf, 2) ?></td></tr>
                <tr><td>Gov’t Loan</td><td class="text-end">₱<?= number_format($govt, 2) ?></td></tr>
            <?php endif; ?>
            <?php if ($lateDeduction > 0): ?><tr><td>Late Deduction</td><td class="text-end">₱<?= number_format($lateDeduction, 2) ?></td></tr><?php endif; ?>
            <?php if ($shortage > 0): ?><tr><td>Shortage</td><td class="text-end">₱<?= number_format($shortage, 2) ?></td></tr><?php endif; ?>
            <?php if ($caUniform > 0): ?><tr><td>CA / Uniform</td><td class="text-end">₱<?= number_format($caUniform, 2) ?></td></tr><?php endif; ?>
            <tr class="border-top"><td><strong>TOTAL DEDUCTIONS</strong></td><td class="text-end text-danger"><strong>₱<?= number_format($totalDeductions, 2) ?></strong></td></tr>
        </table>
    </div>

    <div class="card p-4 text-center" style="background:linear-gradient(135deg, #2d6cdf 0%, #1e4db7 100%);color:white;">
        <h5>NET INCOME</h5>
        <h2><strong>₱<?= number_format($net, 2) ?></strong></h2>
        <p class="mb-0 mt-2" style="font-size:13px;">This is a computer-generated payslip. No signature required.</p>
    </div>

</div>
</body>
</html>
