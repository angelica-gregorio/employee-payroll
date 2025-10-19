<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "act05";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

/* ✅ Helper Function */
function showValue($val) {
    return ($val > 0) ? '₱' . number_format($val, 2) : '-';
}

/* ✅ Month Range Preset */
$dateRanges = [
    'January' => ['01-01 to 01-31'],
    'February' => ['02-01 to 02-28'],
    'March' => ['03-01 to 03-31'],
    'April' => ['04-01 to 04-30'],
    'May' => ['05-01 to 05-31'],
    'June' => ['06-01 to 06-30'],
    'July' => ['07-01 to 07-31'],
    'August' => ['08-01 to 08-31'],
    'September' => ['09-01 to 09-30'],
    'October' => ['10-01 to 10-31'],
    'November' => ['11-01 to 11-30'],
    'December' => ['12-01 to 12-31']
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Salary Summary</title>
    <link href="https://fonts.googleapis.com/css2?family=Google+Sans:wght@400;500;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
</head>
<body class="d-flex flex-column min-vh-100">

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark" style="background-color: var(--primary); padding: 15px 30px;">
    <div class="container-fluid d-flex justify-content-between align-items-center">
        <span class="navbar-brand mb-0 h1 fw-bold" style="color: var(--text-light);">
            <img src="office-building.png" alt="Company Logo" style="height:32px;vertical-align:middle;margin-right:10px;filter: brightness(0) invert(1);">
            EMPLOYEE MANAGEMENT SYSTEM
        </span>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <a href="index.php" class="btn btn-outline-secondary">Dashboard</a>
            <a href="salary_summary.php" class="btn btn-outline-primary">Salary Summary</a>
        </div>
    </div>
</nav>

<div class="container my-4">
    <h2 class="text-center mb-4 fw-bold">SALARY SUMMARY</h2>

    <div class="container my-4">
        <h4 class="text-center">Select a Month</h4>
        <form method="GET" class="d-flex justify-content-center align-items-center gap-2 mb-4">
            <select name="month" class="form-select" style="max-width:200px;">
                <?php foreach ($dateRanges as $month => $ranges): ?>
                    <option value="<?= $month ?>"><?= $month ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary">Generate</button>
        </form>
    </div>

<?php
if (isset($_GET['month'])) {
    $selectedMonth = $_GET['month'];
    $start = date('Y') . "-01-03";
    $end = date('Y') . "-01-30";

    echo "<h5 class='text-center text-success mb-3'>
            Showing salaries for <b>$selectedMonth</b> (January 03–30)
          </h5>";

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
    WHERE t.ShiftDate BETWEEN '$start' AND '$end'
    ORDER BY t.Name, t.ShiftDate
    ";

    $result = $conn->query($sql);

    if ($result && $result->num_rows > 0) {
        $data = [];

        while ($row = $result->fetch_assoc()) {
            $empID = $row['EmpID'];
            $name = $row['Name'];
            $rate = (float)$row['Rate'];
            $role = strtolower(trim($row['Role']));
            $shift = (int)$row['ShiftNo'];
            $hours = (float)$row['Hours'];
            $note = strtolower(trim($row['Notes']));
            $holidayRate = (float)$row['HolidayRate'];
            $deductVal = isset($row['Deductions']) ? abs((float)str_replace('-', '', $row['Deductions'])) : 0;

            if (!isset($data[$name])) {
                $data[$name] = [
                    'EmpID' => $empID,
                    'Email' => $row['Email'],
                    'Rate' => $rate,
                    'DaysWorked' => [],
                    'SILDays' => 0,
                    'SILDates' => [],
                    'NightShifts' => 0,
                    'HolidayPay' => 0,
                    'OvertimeHrs' => 0,
                    'CashierBonus' => 0,
                    'SILBonus' => 0,
                    'Shortage' => 0,
                    'CAUniform' => 0,
                    'SSS' => (float)$row['SSS'],
                    'PHIC' => (float)$row['PHIC'],
                    'HDMF' => (float)$row['HDMF'],
                    'GOVT' => (float)$row['GOVT'],
                    'LateDeduction' => 0
                ];
            }

            $validTime = '/^(?:[01]\d|2[0-3]):[0-5]\d:[0-5]\d$/';
            if (!empty($row['ShiftDate']) && preg_match($validTime, trim($row['TimeIN'])) && preg_match($validTime, trim($row['TimeOUT']))) {
                $data[$name]['DaysWorked'][$row['ShiftDate']] = true;
            }

            if ($shift >= 3) $data[$name]['NightShifts']++;

            // ✅ Holiday Pay Computation (Duplicate-Safe + Bruce Wayne Debug)
            if (!empty($row['HolidayType']) && isset($data[$name]['DaysWorked'][$row['ShiftDate']])) {

                $shiftDate = $row['ShiftDate'];
                $holidayType = strtolower(trim($row['HolidayType']));

                // ✅ Initialize holiday tracker if not yet existing
                if (!isset($data[$name]['HolidayDates'])) {
                    $data[$name]['HolidayDates'] = [];
                }

                // ✅ Skip if this date was already counted for holiday pay
                if (!in_array($shiftDate, $data[$name]['HolidayDates'])) {

                    $data[$name]['HolidayDates'][] = $shiftDate; // mark this date as processed
                    $holidayPay = 0;

                    // ✅ Compute only the EXTRA pay
                    if ($holidayType === 'regular holiday') {
                        $holidayPay = $rate * 1.0; // +100% of daily rate
                    } elseif ($holidayType === 'special holiday') {
                        $holidayPay = $rate * 0.3; // +30% of daily rate
                    }

                    // ✅ Add to total holiday pay
                    $data[$name]['HolidayPay'] += $holidayPay;

                    // 🟦 Debug info (for Bruce Wayne only)
                    if (strtolower(trim($name)) === 'bruce, wayne') {
                        echo "
                        <div class='p-1 border-bottom small text-info'>
                            <b>DEBUG (Holiday Pay)</b> →
                            {$name} | Date: {$shiftDate} | 
                            Type: {$row['HolidayType']} | 
                            Daily Rate: ₱" . number_format($rate, 2) . " | 
                            Extra Pay Added: ₱" . number_format($holidayPay, 2) . " | 
                            Running Total: ₱" . number_format($data[$name]['HolidayPay'], 2) . "
                        </div>";
                    }
                }
            }

            // ✅ Overtime
            if (!isset($data[$name]['OvertimeDates'])) $data[$name]['OvertimeDates'] = [];
            if (strcasecmp(trim($row['DutyType']), 'Overtime') === 0 && is_numeric($row['Hours'])) {
                $shiftDate = $row['ShiftDate'];
                if (!in_array($shiftDate, $data[$name]['OvertimeDates'])) {
                    $data[$name]['OvertimeHrs'] += (float)$row['Hours'];
                    $data[$name]['OvertimeDates'][] = $shiftDate;
                }
            }

            // ✅ Late Deduction
            if (!isset($data[$name]['LateDates'])) $data[$name]['LateDates'] = [];
            if (strtolower(trim($row['DutyType'])) === 'late') {
                $shiftDate = $row['ShiftDate'];
                if (!in_array($shiftDate, $data[$name]['LateDates'])) {
                    $data[$name]['LateDeduction'] += 150;
                    $data[$name]['LateDates'][] = $shiftDate;
                }
            }

            // ✅ Cashier Bonus
            if (!isset($data[$name]['CashierBonusDates'])) $data[$name]['CashierBonusDates'] = [];
            if ($role === 'cashier' && $hours >= 8) {
                $shiftDate = $row['ShiftDate'];
                if (!in_array($shiftDate, $data[$name]['CashierBonusDates'])) {
                    $data[$name]['CashierBonus'] += 40;
                    $data[$name]['CashierBonusDates'][] = $shiftDate;
                }
            }

            // ✅ SIL / Shortage / CA-Uniform (duplicate-safe)
            if ($note === 'sil') {
                $shiftDate = $row['ShiftDate'];
                if (!in_array($shiftDate, $data[$name]['SILDates'])) {
                    $data[$name]['SILBonus'] += $rate;
                    $data[$name]['SILDays']++;
                    $data[$name]['SILDates'][] = $shiftDate;
                }
            }

            if (!isset($data[$name]['ShortageDates'])) $data[$name]['ShortageDates'] = [];
            if (!isset($data[$name]['CAUniformDates'])) $data[$name]['CAUniformDates'] = [];

            if ($note === 'short') {
                $shiftDate = $row['ShiftDate'];
                if (!in_array($shiftDate, $data[$name]['ShortageDates'])) {
                    $data[$name]['Shortage'] += $deductVal;
                    $data[$name]['ShortageDates'][] = $shiftDate;
                }
            }

            if ($note === 'ca' || $note === 'uniform') {
                $shiftDate = $row['ShiftDate'];
                if (!in_array($shiftDate, $data[$name]['CAUniformDates'])) {
                    $data[$name]['CAUniform'] += 106;
                    $data[$name]['CAUniformDates'][] = $shiftDate;
                }
            }
        }

        echo "<div class='table-responsive'>
              <table class='table table-bordered text-center align-middle'>
                <thead class='table-dark'>
                  <tr>
                    <th>EmpID</th>
                    <th>Name</th>
                    <th>Days of Work</th>
                    <th>Gross Income</th>
                    <th>Total Deductions</th>
                    <th>Net Income</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody>";

        foreach ($data as $name => $emp) {
            $numDayQuery = "
              SELECT COUNT(DISTINCT ShiftDate) AS daysWorked
              FROM timesheet
              WHERE TimeIN != '00:00:00'
              AND TimeOUT != '00:00:00'
              AND Name = '$name'
              AND (DutyType != 'Overtime' OR DutyType IS NULL)
              AND ShiftDate BETWEEN '$start' AND '$end'
            ";
            $out = $conn->query($numDayQuery);
            $row = $out ? $out->fetch_assoc() : ['daysWorked' => 0];
            $days = (int)$row['daysWorked'];

            $rate = (float)$emp['Rate'];
            $rate2 = $rate / 8;
            $overtimePay = $emp['OvertimeHrs'] * $rate2;
            $allowance = ($rate > 520) ? ($days * 20) : 0;
            $nightDiff = $emp['NightShifts'] * 52;
            $holiday = $emp['HolidayPay'];
            $cashierBonus = $emp['CashierBonus'];
            $silBonus = $emp['SILBonus'];
            $shortage = $emp['Shortage'];
            $caUniform = $emp['CAUniform'];
            $lateDeduction = $emp['LateDeduction'];

            $basePay = $rate * $days;
            $gross = $basePay + $overtimePay + $allowance + $nightDiff + $holiday + $cashierBonus + $silBonus;
            $sss = $emp['SSS'];
            $phic = $emp['PHIC'];
            $hdmf = $emp['HDMF'];
            $govt = $emp['GOVT'];
            $totalDeductions = $sss + $phic + $hdmf + $govt + $lateDeduction + $shortage + $caUniform;
            $net = $gross - $totalDeductions;

            echo "<tr>
                <td>{$emp['EmpID']}</td>
                <td>$name</td>
                <td>$days</td>
                <td>" . showValue($gross) . "</td>
                <td>" . showValue($totalDeductions) . "</td>
                <td><b class='text-success'>" . showValue($net) . "</b></td>
                <td>
                    <button class='btn btn-info btn-sm' type='button' data-bs-toggle='collapse' data-bs-target='#details_$emp[EmpID]'>Show Details</button>
                    <a href='generate_payslip.php?name=" . urlencode($name) . "&email=" . urlencode($emp['Email']) . "' class='btn btn-primary btn-sm'>Payslip</a>
                </td>
              </tr>
              <tr class='collapse' id='details_$emp[EmpID]'>
                <td colspan='7'>
                  <div class='text-start p-3 bg-light border rounded'>
                    <b>Computation Details for $name</b><br>
                    <ul class='mb-0'>
                      <li><b>Daily Rate:</b> ₱" . number_format($rate, 2) . "</li>
                      <li><b>Hourly Rate:</b> ₱" . number_format($rate2, 2) . "</li>
                      <li><b>Days Worked:</b> $days</li>
                      <li><b>Base Pay:</b> ₱" . number_format($basePay, 2) . "</li>
                      <li><b>Overtime Hours:</b> {$emp['OvertimeHrs']} hrs → ₱" . number_format($overtimePay, 2) . "</li>
                      <li><b>Allowance:</b> ₱" . number_format($allowance, 2) . "</li>
                      <li><b>Night Differential:</b> ₱" . number_format($nightDiff, 2) . "</li>
                      <li><b>Holiday Pay:</b> ₱" . number_format($holiday, 2) . "</li>
                      <li><b>Cashier Bonus:</b> ₱" . number_format($cashierBonus, 2) . "</li>
                      <li><b>SIL (Paid Leave):</b> ₱" . number_format($silBonus, 2) . "</li>
                      <li><b>SSS:</b> ₱" . number_format($sss, 2) . "</li>
                      <li><b>PHIC:</b> ₱" . number_format($phic, 2) . "</li>
                      <li><b>HDMF:</b> ₱" . number_format($hdmf, 2) . "</li>
                      <li><b>GOVT Loan:</b> ₱" . number_format($govt, 2) . "</li>
                      <li><b>Late Deduction:</b> ₱" . number_format($lateDeduction, 2) . "</li>
                      <li><b>Shortage:</b> ₱" . number_format($shortage, 2) . "</li>
                      <li><b>CA/Uniform:</b> ₱" . number_format($caUniform, 2) . "</li>
                      <li><b>Total Deductions:</b> ₱" . number_format($totalDeductions, 2) . "</li>
                      <li><b>Gross Income:</b> ₱" . number_format($gross, 2) . "</li>
                      <li><b>Net Income:</b> <b class='text-success'>₱" . number_format($net, 2) . "</b></li>
                    </ul>
                  </div>
                </td>
              </tr>";
        }

        echo "</tbody></table></div>";
    } else {
        echo "<div class='alert alert-warning text-center'>No records found for this date range.</div>";
    }
}
?>

</div>

<footer class="text-center py-3 mt-auto sticky-footer" style="background: var(--surface); color: var(--primary-dark); font-size: 1.05rem; border-top: 1px solid var(--primary-light);">
    Powered by <strong>Angelica Gregorio</strong> and <strong>Ysabella Santos</strong>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="script.js"></script>
</body>
</html>
