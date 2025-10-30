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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
    <link rel="stylesheet" href="salary_summary.css?v=<?php echo time(); ?>">


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

        <form id="filterForm" method="GET" class="row justify-content-center align-items-center g-3 mb-4">
            <?php
            $selectedMonth = isset($_GET['month']) ? $_GET['month'] : 'January';
            $selectedWeek = isset($_GET['week']) ? (int) $_GET['week'] : 1;
            ?>

            <div class="col-auto">
                <select name="month" id="month" class="form-select shadow-sm" style="min-width: 180px;">
                    <?php
                    $months = [
                        'January',
                        'February',
                        'March',
                        'April',
                        'May',
                        'June',
                        'July',
                        'August',
                        'September',
                        'October',
                        'November',
                        'December'
                    ];
                    $selectedMonth = isset($_GET['month']) ? $_GET['month'] : 'January';

                    // Get months that actually exist in database
                    $availableMonths = [];
                    $monthQuery = $conn->query("SELECT DISTINCT MONTHNAME(ShiftDate) AS Month FROM timesheet ORDER BY MONTH(ShiftDate)");
                    if ($monthQuery && $monthQuery->num_rows > 0) {
                        while ($row = $monthQuery->fetch_assoc()) {
                            $availableMonths[] = $row['Month'];
                        }
                    }

                    // Month dropdown 
                    foreach ($months as $m) {
                        $selected = ($selectedMonth === $m) ? 'selected' : '';
                        // If month has data → normal text; else → faded text
                        $disabled = in_array($m, $availableMonths) ? '' : 'style="color:#999;"';
                        echo "<option value='$m' $selected $disabled>$m</option>";
                    }
                    ?>

                </select>
            </div>


            <div class="col-auto text-center">
                <div class="d-flex align-items-center justify-content-center gap-2">
                    <button type="button" id="prevWeek" class="week-btn">
                        <i class="bi bi-chevron-left"></i>
                    </button>

                    <input type="hidden" name="week" id="week" value="<?= $selectedWeek ?>">

                    <span id="weekLabel" class="week-label">
                        <?= ($selectedWeek == 5) ? 'Whole Month' : 'Week ' . $selectedWeek ?>
                    </span>

                    <button type="button" id="nextWeek" class="week-btn">
                        <i class="bi bi-chevron-right"></i>
                    </button>
                </div>
            </div>



        </form>

        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const form = document.getElementById('filterForm');
                const monthSelect = document.getElementById('month');
                const weekInput = document.getElementById('week');
                const weekLabel = document.getElementById('weekLabel');
                const prevBtn = document.getElementById('prevWeek');
                const nextBtn = document.getElementById('nextWeek');

                let currentWeek = parseInt(weekInput.value);

                // Change week display text
                const updateLabel = () => {
                    weekLabel.textContent = (currentWeek === 5) ? 'Whole Month' : `Week ${currentWeek}`;
                };

                // Navigate weeks with arrows
                prevBtn.addEventListener('click', () => {
                    currentWeek = (currentWeek > 1) ? currentWeek - 1 : 5;
                    weekInput.value = currentWeek;
                    updateLabel();
                    form.submit();
                });

                nextBtn.addEventListener('click', () => {
                    currentWeek = (currentWeek < 5) ? currentWeek + 1 : 1;
                    weekInput.value = currentWeek;
                    updateLabel();
                    form.submit();
                });

                // Auto-submit when month changes
                monthSelect.addEventListener('change', () => form.submit());
            });
        </script>



        <?php
        if (isset($_GET['week']) && isset($_GET['month'])) {
            $weeks = [
                1 => ['label' => 'January 3 to January 9', 'start' => date('Y') . '-01-03', 'end' => date('Y') . '-01-09'],
                2 => ['label' => 'January 10 to January 16', 'start' => date('Y') . '-01-10', 'end' => date('Y') . '-01-16'],
                3 => ['label' => 'January 17 to January 23', 'start' => date('Y') . '-01-17', 'end' => date('Y') . '-01-23'],
                4 => ['label' => 'January 24 to January 30', 'start' => date('Y') . '-01-24', 'end' => date('Y') . '-01-30'],
                5 => ['label' => 'January 3 to January 30', 'start' => date('Y') . '-01-03', 'end' => date('Y') . '-01-30']
            ];

            $selectedWeek = (int) $_GET['week'];
            if (!isset($weeks[$selectedWeek])) {
                echo "<div class='alert alert-danger text-center'>Invalid week selected.</div>";
                exit;
            }

            $weekLabel = $weeks[$selectedWeek]['label'];
            $start = $weeks[$selectedWeek]['start'];
            $end = $weeks[$selectedWeek]['end'];

            echo "<h5 class='text-center text-success mb-3'>
            Showing salaries for <b>$weekLabel</b>
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
                    $rate = (float) $row['Rate'];
                    $role = strtolower(trim($row['Role']));
                    $shift = (int) $row['ShiftNo'];
                    $hours = (float) $row['Hours'];
                    $note = strtolower(trim($row['Notes']));
                    $holidayRate = (float) $row['HolidayRate'];
                    $deductVal = isset($row['Deductions']) ? abs((float) str_replace('-', '', $row['Deductions'])) : 0;

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

                    //  NIGHT DIFFERENTIAL
                    $timeIn = strtotime($row['TimeIN']);
                    $timeOut = strtotime($row['TimeOUT']);
                    $note = strtolower(trim($row['Notes']));
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

                    // ✅ REVISED HOLIDAY FORMULA (COMBINED BASE PAY + HOLIDAY PAY)
                    $shiftDate = $row['ShiftDate'];
                    $worked = !empty($row['TimeIN']) && $row['TimeIN'] != '00:00:00';
                    $holidayPay = 0;

                    // --- Check for regular holidays in the selected range (for auto-grant) ---
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

                        // Add once per actual holiday date
                        if (!isset($data[$name]['HolidayDates']))
                            $data[$name]['HolidayDates'] = [];
                        if (!in_array($shiftDate, $data[$name]['HolidayDates'])) {
                            $data[$name]['HolidayPay'] += $holidayPay;
                            $data[$name]['HolidayDates'][] = $shiftDate;
                        }

                    } else {
                        // --- Auto-grant for all Regular Holidays not yet given ---
                        if (!isset($data[$name]['AutoHolidayGiven']))
                            $data[$name]['AutoHolidayGiven'] = [];
                        if (!isset($data[$name]['HolidayDates']))
                            $data[$name]['HolidayDates'] = [];

                        foreach ($regularHolidays as $rDate) {
                            // Give only if not already worked or tagged as holiday
                            if (
                                !in_array($rDate, $data[$name]['AutoHolidayGiven']) &&
                                !in_array($rDate, $data[$name]['HolidayDates'])
                            ) {
                                $data[$name]['HolidayPay'] += $rate * 1.0; // +100% per regular holiday
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
                        $shiftDate = $row['ShiftDate'];
                        if (!in_array($shiftDate, $data[$name]['OvertimeDates'])) {
                            $data[$name]['OvertimeHrs'] += (float) $row['Hours'];
                            $data[$name]['OvertimeDates'][] = $shiftDate;
                        }
                    }

                    // LATE DEDUCTION
                    if (!isset($data[$name]['LateDates']))
                        $data[$name]['LateDates'] = [];
                    if (strtolower(trim($row['DutyType'])) === 'late') {
                        $shiftDate = $row['ShiftDate'];
                        if (!in_array($shiftDate, $data[$name]['LateDates'])) {
                            $data[$name]['LateDeduction'] += 150;
                            $data[$name]['LateDates'][] = $shiftDate;
                        }
                    }

                    // SIL / Shortage / CA-Uniform
                    if ($note === 'sil') {
                        $shiftDate = $row['ShiftDate'];
                        if (!in_array($shiftDate, $data[$name]['SILDates'])) {
                            $data[$name]['SILBonus'] += $rate;
                            $data[$name]['SILDays']++;
                            $data[$name]['SILDates'][] = $shiftDate;
                        }
                    }

                    if (!isset($data[$name]['ShortageDates']))
                        $data[$name]['ShortageDates'] = [];
                    if (!isset($data[$name]['CAUniformDates']))
                        $data[$name]['CAUniformDates'] = [];

                    if ($note === 'short') {
                        $shiftDate = $row['ShiftDate'];
                        if (!in_array($shiftDate, $data[$name]['ShortageDates'])) {
                            $data[$name]['Shortage'] += $deductVal;
                            $data[$name]['ShortageDates'][] = $shiftDate;
                        }
                    }

                    if ($note === 'uniform' || $note === 'ca') {
                        $shiftDate = $row['ShiftDate'];
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
                    $days = (int) $row['daysWorked'];

                    $rate = (float) $emp['Rate'];
                    $rate2 = $rate / 8;
                    $overtimePay = $emp['OvertimeHrs'] * $rate2;

                    // ALLOWANCE COMPUTATION
                    $nameEsc = $conn->real_escape_string($name);
                    $roleLower = strtolower($role);

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
                        $workDays = 0;

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
                        $workDays = 0;

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


                    // === CONDITIONAL DEDUCTIONS BASED ON WEEK === //
                    $sss_deduction = 0;
                    $phic_deduction = 0;
                    $hdmf_deduction = 0;
                    $govt_deduction = 0;

                    // Apply deductions only for specific weeks
                    if ($selectedWeek == 2) {
                        // Week 2 → SSS only
                        $sss_deduction = $sss;
                    } elseif ($selectedWeek == 3) {
                        // Week 3 → PHIC and HDMF only
                        $phic_deduction = $phic;
                        $hdmf_deduction = $hdmf;
                    } elseif ($selectedWeek == 4) {
                        // Week 4 → Govt Loan only
                        $govt_deduction = $govt;
                    } elseif ($selectedWeek == 5) {
                        // Whole month → All deductions
                        $sss_deduction = $sss;
                        $phic_deduction = $phic;
                        $hdmf_deduction = $hdmf;
                        $govt_deduction = $govt;
                    }
                    // Week 1 automatically has all set to 0 → no deductions
        
                    $totalDeductions = $sss_deduction + $phic_deduction + $hdmf_deduction + $govt_deduction
                        + $lateDeduction + $shortage + $caUniform;


                    $net = $gross - $totalDeductions;

                    echo "<tr>
                            <td>{$emp['EmpID']}</td>
                            <td>$name</td>
                            <td>$days</td>
                            <td>" . showValue($gross) . "</td>
                            <td>" . showValue($totalDeductions) . "</td>
                            <td><b class='text-success'>" . showValue($net) . "</b></td>
                            <td>
                                <button class='btn-showdetails' type='button' data-bs-toggle='collapse' data-bs-target='#details_$emp[EmpID]' title='Show Details'>
                                <i class='bi bi-chevron-down'></i>
                                </button>

                                <a href='generate_payslip.php?name=" . urlencode($name) .
                        "&email=" . urlencode($emp['Email']) . "' class='btn btn-primary btn-sm'>
                                    Payslip
                                </a>
                            </td>
                        </tr>
                        <tr class='collapse' id='details_$emp[EmpID]'>
    <td colspan='7'>
        <div class='payroll-details'>
            <h5>Payroll Breakdown — $name</h5>
            <div class='payroll-grid'>
                <div class='payroll-section'>
                    <h6>Earnings</h6>
                    <ul>
                        <li><span>Daily Rate:</span> ₱" . number_format($rate, 2) . "</li>
                        <li><span>Hourly Rate:</span> ₱" . number_format($rate2, 2) . "</li>
                        <li><span>Days Worked:</span> $days</li>
                        <li><span>Base Pay:</span> ₱" . number_format($basePay, 2) . "</li>
                        <li><span>Overtime Hours:</span> {$emp['OvertimeHrs']} hrs</li>
                        <li><span>Overtime Pay:</span> ₱" . number_format($overtimePay, 2) . "</li>
                        <li><span>Allowance:</span> ₱" . number_format($allowance, 2) . "</li>
                        <li><span>Night Differential:</span> ₱" . number_format($nightDiff, 2) . "</li>
                        <li><span>Holiday Pay:</span> ₱" . number_format($holiday, 2) . "</li>
                        <li><span>SIL (Paid Leave):</span> ₱" . number_format($silBonus, 2) . "</li>
                    </ul>
                </div>

                <div class='payroll-section'>
                    <h6>Deductions</h6>
                    <ul>
                        " . (($selectedWeek == 2 || $selectedWeek == 5) ? "<li><span>SSS:</span> ₱" . number_format($sss_deduction, 2) . "</li>" : "") . "
                        " . (($selectedWeek == 3 || $selectedWeek == 5) ? "<li><span>PHIC:</span> ₱" . number_format($phic_deduction, 2) . "</li>" : "") . "
                        " . (($selectedWeek == 3 || $selectedWeek == 5) ? "<li><span>HDMF:</span> ₱" . number_format($hdmf_deduction, 2) . "</li>" : "") . "
                        " . (($selectedWeek == 4 || $selectedWeek == 5) ? "<li><span>GOVT Loan:</span> ₱" . number_format($govt_deduction, 2) . "</li>" : "") . "
                        <li><span>Late Deduction:</span> ₱" . number_format($lateDeduction, 2) . "</li>
                        <li><span>Shortage:</span> ₱" . number_format($shortage, 2) . "</li>
                        <li><span>CA/Uniform:</span> ₱" . number_format($caUniform, 2) . "</li>
                    </ul>
                </div>
            </div>

            <div class='payroll-summary'>
                <div><b>Gross Income:</b> ₱" . number_format($gross, 2) . "</div>
                <div><b>Total Deductions:</b> ₱" . number_format($totalDeductions, 2) . "</div>
                <div><b>Net Income:</b> <span class='net'>₱" . number_format($net, 2) . "</span></div>
            </div>
        </div>
    </td>
</tr>";
                }

                echo "</tbody></table></div>";
            } else {
                echo "<div class='alert alert-info text-center p-3 rounded shadow-sm' style='background:#f8f9fa; border:1px solid #ccc;'> <i class='bi bi-info-circle me-2'></i> No data to be shown for <b>{$selectedMonth}</b>. </div>";
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
