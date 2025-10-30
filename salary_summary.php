<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "act05";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

/* HELPER FUNCTION */
function showValue($val)
{
    return ($val > 0) ? '₱' . number_format($val, 2) : '-';
}

/* MONTH RANGE */
$dateRanges = [
    'January' => ['start' => date('Y') . '-01-01', 'end' => date('Y') . '-01-31'],
    'February' => ['start' => date('Y') . '-02-01', 'end' => date('Y') . '-02-28'],
    'March' => ['start' => date('Y') . '-03-01', 'end' => date('Y') . '-03-31'],
    'April' => ['start' => date('Y') . '-04-01', 'end' => date('Y') . '-04-30'],
    'May' => ['start' => date('Y') . '-05-01', 'end' => date('Y') . '-05-31'],
    'June' => ['start' => date('Y') . '-06-01', 'end' => date('Y') . '-06-30'],
    'July' => ['start' => date('Y') . '-07-01', 'end' => date('Y') . '-07-31'],
    'August' => ['start' => date('Y') . '-08-01', 'end' => date('Y') . '-08-31'],
    'September' => ['start' => date('Y') . '-09-01', 'end' => date('Y') . '-09-30'],
    'October' => ['start' => date('Y') . '-10-01', 'end' => date('Y') . '-10-31'],
    'November' => ['start' => date('Y') . '-11-01', 'end' => date('Y') . '-11-30'],
    'December' => ['start' => date('Y') . '-12-01', 'end' => date('Y') . '-12-31']
];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Salary Summary</title>
    <link href="https://fonts.googleapis.com/css2?family=Google+Sans:wght@400;500;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
    <link href="salary_summary.css" rel="stylesheet">

</head>

<body class="d-flex flex-column min-vh-100">

    <!-- NAVBAR -->
    <nav class="navbar navbar-expand-lg navbar-dark" style="background-color: var(--primary); padding: 15px 30px;">
        <div class="container-fluid d-flex justify-content-between align-items-center">
            <span class="navbar-brand mb-0 h1 fw-bold" style="color: var(--text-light);">
                <img src="office-building.png" alt="Company Logo"
                    style="height:32px;vertical-align:middle;margin-right:10px;filter: brightness(0) invert(1);">
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

        <h4 class="text-center">Select a Month</h4>
        <form method="GET" class="d-flex justify-content-center align-items-center gap-2 mb-4">
            <?php $selectedMonth = isset($_GET['month']) ? $_GET['month'] : 'January'; ?>
            <select name="month" class="form-select" style="max-width:280px;">
                <?php foreach (array_keys($dateRanges) as $month): ?>
                    <option value="<?= $month ?>" <?= ($selectedMonth === $month) ? 'selected' : '' ?>><?= $month ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary">Generate</button>
        </form>

        <?php
        if (isset($_GET['month'])) {
            $selectedMonth = $_GET['month'];
            
            if (!isset($dateRanges[$selectedMonth])) {
                echo "<div class='alert alert-danger text-center'>Invalid month selected.</div>";
                exit;
            }

            $monthStart = $dateRanges[$selectedMonth]['start'];
            $monthEnd = $dateRanges[$selectedMonth]['end'];

            // Define weeks for January (adjust for other months as needed)
            $weeks = [
                1 => ['label' => 'Week 1: January 3 to January 9', 'start' => date('Y') . '-01-03', 'end' => date('Y') . '-01-09'],
                2 => ['label' => 'Week 2: January 10 to January 16', 'start' => date('Y') . '-01-10', 'end' => date('Y') . '-01-16'],
                3 => ['label' => 'Week 3: January 17 to January 23', 'start' => date('Y') . '-01-17', 'end' => date('Y') . '-01-23'],
                4 => ['label' => 'Week 4: January 24 to January 30', 'start' => date('Y') . '-01-24', 'end' => date('Y') . '-01-30'],
                5 => ['label' => 'Whole Month: January 3 to January 30', 'start' => date('Y') . '-01-03', 'end' => date('Y') . '-01-30']
            ];

            echo "<h5 class='text-center text-success mb-3'>Showing salaries for <b>$selectedMonth</b></h5>";

            // Get unique employees for the month
            $employeeQuery = "
                SELECT DISTINCT e.EmpID, e.Name, e.Email
                FROM timesheet t
                JOIN employees e ON t.Name = e.Name
                WHERE t.ShiftDate BETWEEN '$monthStart' AND '$monthEnd'
                ORDER BY e.Name
            ";
            
            $employeeResult = $conn->query($employeeQuery);

            if ($employeeResult && $employeeResult->num_rows > 0) {
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

                while ($empRow = $employeeResult->fetch_assoc()) {
                    $empName = $empRow['Name'];
                    $empID = $empRow['EmpID'];
                    $empEmail = $empRow['Email'];

                    // Calculate for whole month (week 5)
                    $selectedWeek = 5;
                    $start = $weeks[5]['start'];
                    $end = $weeks[5]['end'];

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
                        WHERE t.Name = '$empName' AND t.ShiftDate BETWEEN '$start' AND '$end'
                        ORDER BY t.ShiftDate
                    ";

                    $result = $conn->query($sql);

                    if ($result && $result->num_rows > 0) {
                        $data = [];

                        while ($row = $result->fetch_assoc()) {
                            $name = $row['Name'];
                            $rate = (float) $row['Rate'];
                            $role = strtolower(trim($row['Role']));
                            $note = strtolower(trim($row['Notes']));
                            $deductVal = isset($row['Deductions']) ? abs((float) str_replace('-', '', $row['Deductions'])) : 0;

                            if (!isset($data[$name])) {
                                $data[$name] = [
                                    'EmpID' => $empID,
                                    'Email' => $row['Email'],
                                    'Rate' => $rate,
                                    'Role' => $row['Role'],
                                    'DaysWorked' => [],
                                    'SILDays' => 0,
                                    'SILDates' => [],
                                    'NightShifts' => 0,
                                    'HolidayPay' => 0,
                                    'OvertimeHrs' => 0,
                                    'SILBonus' => 0,
                                    'Shortage' => 0,
                                    'CAUniform' => 0,
                                    'SSS' => (float) $row['SSS'],
                                    'PHIC' => (float) $row['PHIC'],
                                    'HDMF' => (float) $row['HDMF'],
                                    'GOVT' => (float) $row['GOVT'],
                                    'LateDeduction' => 0
                                ];
                            }

                            $validTime = '/^(?:[01]\d|2[0-3]):[0-5]\d:[0-5]\d$/';
                            if (
                                !empty($row['ShiftDate']) &&
                                preg_match($validTime, trim($row['TimeIN'])) &&
                                preg_match($validTime, trim($row['TimeOUT']))
                            ) {
                                $data[$name]['DaysWorked'][$row['ShiftDate']] = true;
                            }

                            // NIGHT DIFFERENTIAL
                            $timeIn = strtotime($row['TimeIN']);
                            $timeOut = strtotime($row['TimeOUT']);
                            $shiftDate = $row['ShiftDate'];

                            if (!isset($data[$name]['NightDates'])) {
                                $data[$name]['NightDates'] = [];
                            }

                            $isNightShift = (!empty($row['TimeIN']) &&
                                !empty($row['TimeOUT']) &&
                                $timeIn > $timeOut);
                            $isSIL = ($note === 'sil');

                            if (
                                ($isNightShift || $isSIL) &&
                                !in_array($shiftDate, $data[$name]['NightDates'])
                            ) {
                                $data[$name]['NightShifts']++;
                                $data[$name]['NightDates'][] = $shiftDate;
                            }

                            // HOLIDAY PAY
                            $worked = !empty($row['TimeIN']) && $row['TimeIN'] != '00:00:00';
                            $holidayPay = 0;

                            $regularHolidays = [];
                            $holidayQuery = $conn->query("
                                SELECT Date 
                                FROM holidays 
                                WHERE Type = 'Regular Holiday'
                                AND Date BETWEEN '$start' AND '$end'
                            ");
                            if ($holidayQuery && $holidayQuery->num_rows > 0) {
                                while ($h = $holidayQuery->fetch_assoc()) {
                                    $regularHolidays[] = $h['Date'];
                                }
                            }

                            if (!empty($row['HolidayType'])) {
                                $holidayType = strtolower(trim($row['HolidayType']));

                                if ($holidayType === 'special holiday' || $holidayType === 'special non-working holiday') {
                                    if ($worked) {
                                        $holidayPay = $rate * 0.3;
                                    } else {
                                        $holidayPay = 0;
                                    }
                                }

                                if (!isset($data[$name]['HolidayDates']))
                                    $data[$name]['HolidayDates'] = [];
                                if (!in_array($shiftDate, $data[$name]['HolidayDates'])) {
                                    $data[$name]['HolidayPay'] += $holidayPay;
                                    $data[$name]['HolidayDates'][] = $shiftDate;
                                }

                            } else {
                                if (!isset($data[$name]['AutoHolidayGiven']))
                                    $data[$name]['AutoHolidayGiven'] = [];
                                if (!isset($data[$name]['HolidayDates']))
                                    $data[$name]['HolidayDates'] = [];

                                foreach ($regularHolidays as $rDate) {
                                    if (
                                        !in_array($rDate, $data[$name]['AutoHolidayGiven']) &&
                                        !in_array($rDate, $data[$name]['HolidayDates'])
                                    ) {
                                        $data[$name]['HolidayPay'] += $rate * 1.0;
                                        $data[$name]['AutoHolidayGiven'][] = $rDate;
                                    }
                                }
                            }

                            // OVERTIME
                            if (!isset($data[$name]['OvertimeDates']))
                                $data[$name]['OvertimeDates'] = [];
                            if (
                                strcasecmp(trim($row['DutyType']), 'Overtime') === 0 &&
                                is_numeric($row['Hours'])
                            ) {
                                if (!in_array($shiftDate, $data[$name]['OvertimeDates'])) {
                                    $data[$name]['OvertimeHrs'] += (float) $row['Hours'];
                                    $data[$name]['OvertimeDates'][] = $shiftDate;
                                }
                            }

                            // LATE DEDUCTION
                            if (!isset($data[$name]['LateDates']))
                                $data[$name]['LateDates'] = [];
                            if (strtolower(trim($row['DutyType'])) === 'late') {
                                if (!in_array($shiftDate, $data[$name]['LateDates'])) {
                                    $data[$name]['LateDeduction'] += 150;
                                    $data[$name]['LateDates'][] = $shiftDate;
                                }
                            }

                            // SIL
                            if ($note === 'sil') {
                                if (!in_array($shiftDate, $data[$name]['SILDates'])) {
                                    $data[$name]['SILBonus'] += $rate;
                                    $data[$name]['SILDays']++;
                                    $data[$name]['SILDates'][] = $shiftDate;
                                }
                            }

                            // SHORTAGE
                            if (!isset($data[$name]['ShortageDates']))
                                $data[$name]['ShortageDates'] = [];
                            if (!isset($data[$name]['CAUniformDates']))
                                $data[$name]['CAUniformDates'] = [];

                            if ($note === 'short') {
                                if (!in_array($shiftDate, $data[$name]['ShortageDates'])) {
                                    $data[$name]['Shortage'] += $deductVal;
                                    $data[$name]['ShortageDates'][] = $shiftDate;
                                }
                            }

                            // CA/UNIFORM
                            if ($note === 'uniform' || $note === 'ca') {
                                if (!in_array($shiftDate, $data[$name]['CAUniformDates'])) {
                                    if ($note === 'uniform') {
                                        $data[$name]['CAUniform'] += 106;
                                    } elseif ($note === 'ca') {
                                        $data[$name]['CAUniform'] += 500;
                                    }
                                    $data[$name]['CAUniformDates'][] = $shiftDate;
                                }
                            }
                        }

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
                            $days = (int) $row['daysWorked'];

                            $rate = (float) $emp['Rate'];
                            $rate2 = $rate / 8;
                            $overtimePay = $emp['OvertimeHrs'] * $rate2;

                            // ALLOWANCE COMPUTATION
                            $nameEsc = $conn->real_escape_string($name);
                            $roleLower = strtolower($emp['Role']);

                            if ($roleLower === 'cashier') {
                                $uniqueShiftsQuery = "
                                    SELECT DISTINCT ShiftDate, ShiftNo, DutyType, Hours, Business_Unit, Notes
                                    FROM timesheet
                                    WHERE Name = '$nameEsc'
                                      AND Role = 'Cashier'
                                      AND ShiftDate BETWEEN '$start' AND '$end'
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
                                        $totalHours += (float) $tsRow['Hours'];
                                    }
                                }

                                $cashierBonus = round($totalHours * 5, 2);
                                $dailyAllowance = 0;

                                if ($rate > 520) {
                                    $uniqueDaysQuery = "
                                        SELECT DISTINCT ShiftDate
                                        FROM timesheet
                                        WHERE Name = '$nameEsc'
                                          AND ShiftDate BETWEEN '$start' AND '$end'
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
                                          AND ShiftDate BETWEEN '$start' AND '$end'
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

                            // COMPUTATION SUMMARY 
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

                            // CONDITIONAL DEDUCTIONS - WHOLE MONTH
                            $sss_deduction = $sss;
                            $phic_deduction = $phic;
                            $hdmf_deduction = $hdmf;
                            $govt_deduction = $govt;

                            $totalDeductions = $sss_deduction + $phic_deduction + $hdmf_deduction + $govt_deduction
                                + $lateDeduction + $shortage + $caUniform;

                            $net = $gross - $totalDeductions;

                            echo "<tr>
                                    <td>$empID</td>
                                    <td>$name</td>
                                    <td>$days</td>
                                    <td>" . showValue($gross) . "</td>
                                    <td>" . showValue($totalDeductions) . "</td>
                                    <td><b class='text-success'>" . showValue($net) . "</b></td>
                                    <td>
                                        <button class='btn-showdetails' type='button' data-bs-toggle='collapse' data-bs-target='#details_$empID' title='Show Weekly Breakdown'>
                                        <i class='bi bi-chevron-down'></i>
                                        </button>

                                        <a href='generate_payslip.php?name=" . urlencode($name) .
                                "&email=" . urlencode($empEmail) . "' class='btn btn-primary btn-sm'>
                                            Payslip
                                        </a>
                                    </td>
                                </tr>";

                            // WEEKLY BREAKDOWN
                            echo "<tr class='collapse' id='details_$empID'>
                                    <td colspan='7'>
                                        <div class='payroll-details'>
                                            <h5>Weekly Breakdown — $name</h5>
                                            <div class='table-responsive'>
                                                <table class='table table-sm table-bordered text-center'>
                                                    <thead class='table-secondary'>
                                                        <tr>
                                                            <th>Week</th>
                                                            <th>Period</th>
                                                            <th>Days</th>
                                                            <th>Gross</th>
                                                            <th>Deductions</th>
                                                            <th>Net</th>
                                                            <th>Details</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>";

                            // Loop through each week
                            foreach ($weeks as $weekNum => $weekInfo) {
                                $weekStart = $weekInfo['start'];
                                $weekEnd = $weekInfo['end'];

                                // Run same computation for each week
                                $weekSql = "
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
                                    WHERE t.Name = '$name' AND t.ShiftDate BETWEEN '$weekStart' AND '$weekEnd'
                                    ORDER BY t.ShiftDate
                                ";

                                $weekResult = $conn->query($weekSql);
                                $weekData = [];

                                if ($weekResult && $weekResult->num_rows > 0) {
                                    while ($wRow = $weekResult->fetch_assoc()) {
                                        $wName = $wRow['Name'];
                                        $wRate = (float) $wRow['Rate'];
                                        $wNote = strtolower(trim($wRow['Notes']));
                                        $wDeductVal = isset($wRow['Deductions']) ? abs((float) str_replace('-', '', $wRow['Deductions'])) : 0;

                                        if (!isset($weekData[$wName])) {
                                            $weekData[$wName] = [
                                                'Rate' => $wRate,
                                                'Role' => $wRow['Role'],
                                                'NightShifts' => 0,
                                                'NightDates' => [],
                                                'HolidayPay' => 0,
                                                'HolidayDates' => [],
                                                'AutoHolidayGiven' => [],
                                                'OvertimeHrs' => 0,
                                                'OvertimeDates' => [],
                                                'SILBonus' => 0,
                                                'SILDates' => [],
                                                'Shortage' => 0,
                                                'ShortageDates' => [],
                                                'CAUniform' => 0,
                                                'CAUniformDates' => [],
                                                'SSS' => (float) $wRow['SSS'],
                                                'PHIC' => (float) $wRow['PHIC'],
                                                'HDMF' => (float) $wRow['HDMF'],
                                                'GOVT' => (float) $wRow['GOVT'],
                                                'LateDeduction' => 0,
                                                'LateDates' => []
                                            ];
                                        }

                                        $wShiftDate = $wRow['ShiftDate'];
                                        $wTimeIn = strtotime($wRow['TimeIN']);
                                        $wTimeOut = strtotime($wRow['TimeOUT']);

                                        // Night differential
                                        $wIsNightShift = (!empty($wRow['TimeIN']) && !empty($wRow['TimeOUT']) && $wTimeIn > $wTimeOut);
                                        $wIsSIL = ($wNote === 'sil');

                                        if (($wIsNightShift || $wIsSIL) && !in_array($wShiftDate, $weekData[$wName]['NightDates'])) {
                                            $weekData[$wName]['NightShifts']++;
                                            $weekData[$wName]['NightDates'][] = $wShiftDate;
                                        }

                                        // Holiday pay
                                        $wWorked = !empty($wRow['TimeIN']) && $wRow['TimeIN'] != '00:00:00';
                                        $wHolidayPay = 0;

                                        $wRegularHolidays = [];
                                        $wHolidayQuery = $conn->query("
                                            SELECT Date 
                                            FROM holidays 
                                            WHERE Type = 'Regular Holiday'
                                            AND Date BETWEEN '$weekStart' AND '$weekEnd'
                                        ");
                                        if ($wHolidayQuery && $wHolidayQuery->num_rows > 0) {
                                            while ($wh = $wHolidayQuery->fetch_assoc()) {
                                                $wRegularHolidays[] = $wh['Date'];
                                            }
                                        }

                                        if (!empty($wRow['HolidayType'])) {
                                            $wHolidayType = strtolower(trim($wRow['HolidayType']));

                                            if ($wHolidayType === 'special holiday' || $wHolidayType === 'special non-working holiday') {
                                                if ($wWorked) {
                                                    $wHolidayPay = $wRate * 0.3;
                                                }
                                            }

                                            if (!in_array($wShiftDate, $weekData[$wName]['HolidayDates'])) {
                                                $weekData[$wName]['HolidayPay'] += $wHolidayPay;
                                                $weekData[$wName]['HolidayDates'][] = $wShiftDate;
                                            }
                                        } else {
                                            foreach ($wRegularHolidays as $wrDate) {
                                                if (!in_array($wrDate, $weekData[$wName]['AutoHolidayGiven']) && !in_array($wrDate, $weekData[$wName]['HolidayDates'])) {
                                                    $weekData[$wName]['HolidayPay'] += $wRate * 1.0;
                                                    $weekData[$wName]['AutoHolidayGiven'][] = $wrDate;
                                                }
                                            }
                                        }

                                        // Overtime
                                        if (strcasecmp(trim($wRow['DutyType']), 'Overtime') === 0 && is_numeric($wRow['Hours'])) {
                                            if (!in_array($wShiftDate, $weekData[$wName]['OvertimeDates'])) {
                                                $weekData[$wName]['OvertimeHrs'] += (float) $wRow['Hours'];
                                                $weekData[$wName]['OvertimeDates'][] = $wShiftDate;
                                            }
                                        }

                                        // Late deduction
                                        if (strtolower(trim($wRow['DutyType'])) === 'late') {
                                            if (!in_array($wShiftDate, $weekData[$wName]['LateDates'])) {
                                                $weekData[$wName]['LateDeduction'] += 150;
                                                $weekData[$wName]['LateDates'][] = $wShiftDate;
                                            }
                                        }

                                        // SIL
                                        if ($wNote === 'sil') {
                                            if (!in_array($wShiftDate, $weekData[$wName]['SILDates'])) {
                                                $weekData[$wName]['SILBonus'] += $wRate;
                                                $weekData[$wName]['SILDates'][] = $wShiftDate;
                                            }
                                        }

                                        // Shortage
                                        if ($wNote === 'short') {
                                            if (!in_array($wShiftDate, $weekData[$wName]['ShortageDates'])) {
                                                $weekData[$wName]['Shortage'] += $wDeductVal;
                                                $weekData[$wName]['ShortageDates'][] = $wShiftDate;
                                            }
                                        }

                                        // CA/Uniform
                                        if ($wNote === 'uniform' || $wNote === 'ca') {
                                            if (!in_array($wShiftDate, $weekData[$wName]['CAUniformDates'])) {
                                                if ($wNote === 'uniform') {
                                                    $weekData[$wName]['CAUniform'] += 106;
                                                } elseif ($wNote === 'ca') {
                                                    $weekData[$wName]['CAUniform'] += 500;
                                                }
                                                $weekData[$wName]['CAUniformDates'][] = $wShiftDate;
                                            }
                                        }
                                    }
                                }

                                // Calculate week totals
                                if (isset($weekData[$name])) {
                                    $wEmp = $weekData[$name];

                                    $wNumDayQuery = "
                                        SELECT COUNT(DISTINCT ShiftDate) AS daysWorked
                                        FROM timesheet
                                        WHERE TimeIN != '00:00:00'
                                          AND TimeOUT != '00:00:00'
                                          AND Name = '$name'
                                          AND (DutyType != 'Overtime' OR DutyType IS NULL)
                                          AND ShiftDate BETWEEN '$weekStart' AND '$weekEnd'
                                    ";
                                    $wOut = $conn->query($wNumDayQuery);
                                    $wRow = $wOut ? $wOut->fetch_assoc() : ['daysWorked' => 0];
                                    $wDays = (int) $wRow['daysWorked'];

                                    $wRate = (float) $wEmp['Rate'];
                                    $wRate2 = $wRate / 8;
                                    $wOvertimePay = $wEmp['OvertimeHrs'] * $wRate2;

                                    // Allowance
                                    $wNameEsc = $conn->real_escape_string($name);
                                    $wRoleLower = strtolower($wEmp['Role']);
                                    $wAllowance = 0;

                                    if ($wRoleLower === 'cashier') {
                                        $wUniqueShiftsQuery = "
                                            SELECT DISTINCT ShiftDate, ShiftNo, DutyType, Hours, Business_Unit, Notes
                                            FROM timesheet
                                            WHERE Name = '$wNameEsc'
                                              AND Role = 'Cashier'
                                              AND ShiftDate BETWEEN '$weekStart' AND '$weekEnd'
                                              AND TimeIN != '00:00:00'
                                              AND TimeOUT != '00:00:00'
                                              AND Hours IS NOT NULL
                                              AND ShiftNo IS NOT NULL
                                              AND DutyType IS NOT NULL
                                            ORDER BY ShiftDate ASC, ShiftNo ASC
                                        ";
                                        $wResShifts = $conn->query($wUniqueShiftsQuery);

                                        $wTotalHours = 0;
                                        if ($wResShifts && $wResShifts->num_rows > 0) {
                                            while ($wTsRow = $wResShifts->fetch_assoc()) {
                                                $wTotalHours += (float) $wTsRow['Hours'];
                                            }
                                        }

                                        $wCashierBonus = round($wTotalHours * 5, 2);
                                        $wDailyAllowance = 0;

                                        if ($wRate > 520) {
                                            $wUniqueDaysQuery = "
                                                SELECT DISTINCT ShiftDate
                                                FROM timesheet
                                                WHERE Name = '$wNameEsc'
                                                  AND ShiftDate BETWEEN '$weekStart' AND '$weekEnd'
                                                  AND DutyType = 'OnDuty'
                                                  AND TimeIN != '00:00:00'
                                                  AND TimeOUT != '00:00:00'
                                            ";
                                            $wDaysRes = $conn->query($wUniqueDaysQuery);

                                            if ($wDaysRes && $wDaysRes->num_rows > 0) {
                                                $wWorkDays = $wDaysRes->num_rows;
                                                $wDailyAllowance = $wWorkDays * 20;
                                            }
                                        }

                                        $wAllowance = $wCashierBonus + $wDailyAllowance;
                                    } else {
                                        if ($wRate > 520) {
                                            $wUniqueDaysQuery = "
                                                SELECT DISTINCT ShiftDate
                                                FROM timesheet
                                                WHERE Name = '$wNameEsc'
                                                  AND ShiftDate BETWEEN '$weekStart' AND '$weekEnd'
                                                  AND DutyType = 'OnDuty'
                                                  AND TimeIN != '00:00:00'
                                                  AND TimeOUT != '00:00:00'
                                            ";
                                            $wDaysRes = $conn->query($wUniqueDaysQuery);

                                            if ($wDaysRes && $wDaysRes->num_rows > 0) {
                                                $wWorkDays = $wDaysRes->num_rows;
                                                $wAllowance = $wWorkDays * 20;
                                            }
                                        }
                                    }

                                    $wNightDiff = $wEmp['NightShifts'] * 52;
                                    $wHoliday = $wEmp['HolidayPay'];
                                    $wSilBonus = $wEmp['SILBonus'];
                                    $wShortage = $wEmp['Shortage'];
                                    $wCaUniform = $wEmp['CAUniform'];
                                    $wLateDeduction = $wEmp['LateDeduction'];

                                    $wBasePay = $wRate * $wDays;
                                    $wGross = $wBasePay + $wOvertimePay + $wAllowance + $wNightDiff + $wHoliday + $wSilBonus;

                                    // CONDITIONAL DEDUCTIONS BASED ON WEEK
                                    $wSss_deduction = 0;
                                    $wPhic_deduction = 0;
                                    $wHdmf_deduction = 0;
                                    $wGovt_deduction = 0;

                                    if ($weekNum == 2) {
                                        $wSss_deduction = $wEmp['SSS'];
                                    } elseif ($weekNum == 3) {
                                        $wPhic_deduction = $wEmp['PHIC'];
                                        $wHdmf_deduction = $wEmp['HDMF'];
                                    } elseif ($weekNum == 4) {
                                        $wGovt_deduction = $wEmp['GOVT'];
                                    } elseif ($weekNum == 5) {
                                        $wSss_deduction = $wEmp['SSS'];
                                        $wPhic_deduction = $wEmp['PHIC'];
                                        $wHdmf_deduction = $wEmp['HDMF'];
                                        $wGovt_deduction = $wEmp['GOVT'];
                                    }

                                    $wTotalDeductions = $wSss_deduction + $wPhic_deduction + $wHdmf_deduction + $wGovt_deduction
                                        + $wLateDeduction + $wShortage + $wCaUniform;

                                    $wNet = $wGross - $wTotalDeductions;

                                    $weekLabel = ($weekNum == 5) ? "Whole Month" : "Week $weekNum";

                                    echo "<tr>
                                            <td>$weekLabel</td>
                                            <td>{$weekInfo['label']}</td>
                                            <td>$wDays</td>
                                            <td>" . showValue($wGross) . "</td>
                                            <td>" . showValue($wTotalDeductions) . "</td>
                                            <td><b>" . showValue($wNet) . "</b></td>
                                            <td>
                                                <button class='btn btn-sm btn-info' type='button' data-bs-toggle='collapse' data-bs-target='#week_{$empID}_{$weekNum}'>
                                                    <i class='bi bi-eye'></i>
                                                </button>
                                            </td>
                                        </tr>";

                                    // Week details
                                    echo "<tr class='collapse' id='week_{$empID}_{$weekNum}'>
                                            <td colspan='7' class='bg-light'>
                                                <div class='payroll-grid'>
                                                    <div class='payroll-section'>
                                                        <h6>Earnings</h6>
                                                        <ul>
                                                            <li><span>Daily Rate:</span> ₱" . number_format($wRate, 2) . "</li>
                                                            <li><span>Hourly Rate:</span> ₱" . number_format($wRate2, 2) . "</li>
                                                            <li><span>Days Worked:</span> $wDays</li>
                                                            <li><span>Base Pay:</span> ₱" . number_format($wBasePay, 2) . "</li>
                                                            <li><span>Overtime Hours:</span> {$wEmp['OvertimeHrs']} hrs</li>
                                                            <li><span>Overtime Pay:</span> ₱" . number_format($wOvertimePay, 2) . "</li>
                                                            <li><span>Allowance:</span> ₱" . number_format($wAllowance, 2) . "</li>
                                                            <li><span>Night Differential:</span> ₱" . number_format($wNightDiff, 2) . "</li>
                                                            <li><span>Holiday Pay:</span> ₱" . number_format($wHoliday, 2) . "</li>
                                                            <li><span>SIL (Paid Leave):</span> ₱" . number_format($wSilBonus, 2) . "</li>
                                                        </ul>
                                                    </div>

                                                    <div class='payroll-section'>
                                                        <h6>Deductions</h6>
                                                        <ul>
                                                            " . (($weekNum == 2 || $weekNum == 5) ? "<li><span>SSS:</span> ₱" . number_format($wSss_deduction, 2) . "</li>" : "") . "
                                                            " . (($weekNum == 3 || $weekNum == 5) ? "<li><span>PHIC:</span> ₱" . number_format($wPhic_deduction, 2) . "</li>" : "") . "
                                                            " . (($weekNum == 3 || $weekNum == 5) ? "<li><span>HDMF:</span> ₱" . number_format($wHdmf_deduction, 2) . "</li>" : "") . "
                                                            " . (($weekNum == 4 || $weekNum == 5) ? "<li><span>GOVT Loan:</span> ₱" . number_format($wGovt_deduction, 2) . "</li>" : "") . "
                                                            <li><span>Late Deduction:</span> ₱" . number_format($wLateDeduction, 2) . "</li>
                                                            <li><span>Shortage:</span> ₱" . number_format($wShortage, 2) . "</li>
                                                            <li><span>CA/Uniform:</span> ₱" . number_format($wCaUniform, 2) . "</li>
                                                        </ul>
                                                    </div>
                                                </div>

                                                <div class='payroll-summary'>
                                                    <div><b>Gross Income:</b> ₱" . number_format($wGross, 2) . "</div>
                                                    <div><b>Total Deductions:</b> ₱" . number_format($wTotalDeductions, 2) . "</div>
                                                    <div><b>Net Income:</b> <span class='net'>₱" . number_format($wNet, 2) . "</span></div>
                                                </div>
                                            </td>
                                        </tr>";
                                } else {
                                    // No data for this week
                                    $weekLabel = ($weekNum == 5) ? "Whole Month" : "Week $weekNum";
                                    echo "<tr>
                                            <td>$weekLabel</td>
                                            <td>{$weekInfo['label']}</td>
                                            <td colspan='5' class='text-muted'>No data for this period</td>
                                        </tr>";
                                }
                            }

                            echo "</tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </td>
                                </tr>";
                        }
                    }
                }

                echo "</tbody></table></div>";
            } else {
                echo "<div class='alert alert-warning text-center'>No records found for this month.</div>";
            }
        }
        ?>
    </div>

    <footer class="text-center py-3 mt-auto sticky-footer" style="background: var(--surface); color: var(--primary-dark);
                   font-size: 1.05rem; border-top: 1px solid var(--primary-light);">
        Powered by <strong>Angelica Gregorio</strong> and <strong>Ysabella Santos</strong>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="script.js"></script>
</body>

</html>
