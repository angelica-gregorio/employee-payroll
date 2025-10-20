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
    'January' => '01',
    'February' => '02',
    'March' => '03',
    'April' => '04',
    'May' => '05',
    'June' => '06',
    'July' => '07',
    'August' => '08',
    'September' => '09',
    'October' => '10',
    'November' => '11',
    'December' => '12'
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
    <style>
        .modal-header { background-color: var(--primary); color: white; }
        .modal-body { background-color: #f8f9fa; }
        .week-option { 
            padding: 15px; 
            border: 2px solid #ddd; 
            border-radius: 8px; 
            cursor: pointer; 
            transition: all 0.3s;
            text-align: center;
            font-weight: 500;
        }
        .week-option:hover { 
            background-color: #e9ecef; 
            border-color: #0d6efd;
        }
        .week-option.selected { 
            background-color: #0d6efd; 
            color: white; 
            border-color: #0d6efd; 
        }
    </style>
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
                <?php foreach ($dateRanges as $month => $num): ?>
                    <option value="<?= $num ?>"><?= $month ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary">Generate</button>
        </form>
    </div>

<?php
if (isset($_GET['month'])) {
    $selectedMonthNum = $_GET['month'];
    $selectedMonthName = array_search($selectedMonthNum, $dateRanges);
    $start = date('Y') . "-$selectedMonthNum-03";
    $end = date('Y') . "-$selectedMonthNum-30";

    echo "<h5 class='text-center text-success mb-3'>
            Showing salaries for <b>$selectedMonthName</b> (03–30)
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

            if (!empty($row['HolidayType']) && isset($data[$name]['DaysWorked'][$row['ShiftDate']])) {
                $shiftDate = $row['ShiftDate'];
                $holidayType = strtolower(trim($row['HolidayType']));

                if (!isset($data[$name]['HolidayDates'])) {
                    $data[$name]['HolidayDates'] = [];
                }

                if (!in_array($shiftDate, $data[$name]['HolidayDates'])) {
                    $data[$name]['HolidayDates'][] = $shiftDate;
                    $holidayPay = 0;

                    if ($holidayType === 'regular holiday') {
                        $holidayPay = $rate * 1.0;
                    } elseif ($holidayType === 'special holiday') {
                        $holidayPay = $rate * 0.3;
                    }

                    $data[$name]['HolidayPay'] += $holidayPay;
                }
            }

            if (!isset($data[$name]['OvertimeDates'])) $data[$name]['OvertimeDates'] = [];
            if (strcasecmp(trim($row['DutyType']), 'Overtime') === 0 && is_numeric($row['Hours'])) {
                $shiftDate = $row['ShiftDate'];
                if (!in_array($shiftDate, $data[$name]['OvertimeDates'])) {
                    $data[$name]['OvertimeHrs'] += (float)$row['Hours'];
                    $data[$name]['OvertimeDates'][] = $shiftDate;
                }
            }

            if (!isset($data[$name]['LateDates'])) $data[$name]['LateDates'] = [];
            if (strtolower(trim($row['DutyType'])) === 'late') {
                $shiftDate = $row['ShiftDate'];
                if (!in_array($shiftDate, $data[$name]['LateDates'])) {
                    $data[$name]['LateDeduction'] += 150;
                    $data[$name]['LateDates'][] = $shiftDate;
                }
            }

            if (!isset($data[$name]['CashierBonusDates'])) $data[$name]['CashierBonusDates'] = [];
            if ($role === 'cashier' && $hours >= 8) {
                $shiftDate = $row['ShiftDate'];
                if (!in_array($shiftDate, $data[$name]['CashierBonusDates'])) {
                    $data[$name]['CashierBonus'] += 40;
                    $data[$name]['CashierBonusDates'][] = $shiftDate;
                }
            }

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

            // Encode data for modal
            $empData = json_encode([
                'EmpID' => $emp['EmpID'],
                'Name' => $name,
                'Email' => $emp['Email'],
                'Rate' => $rate,
                'Days' => $days,
                'BasePay' => $basePay,
                'OvertimeHrs' => $emp['OvertimeHrs'],
                'OvertimePay' => $overtimePay,
                'Allowance' => $allowance,
                'NightDiff' => $nightDiff,
                'Holiday' => $holiday,
                'CashierBonus' => $cashierBonus,
                'SILBonus' => $silBonus,
                'SSS' => $sss,
                'PHIC' => $phic,
                'HDMF' => $hdmf,
                'GOVT' => $govt,
                'LateDeduction' => $lateDeduction,
                'Shortage' => $shortage,
                'CAUniform' => $caUniform,
                'TotalDeductions' => $totalDeductions,
                'Gross' => $gross,
                'Net' => $net
            ]);

            echo "<tr>
                <td>{$emp['EmpID']}</td>
                <td>$name</td>
                <td>$days</td>
                <td>" . showValue($gross) . "</td>
                <td>" . showValue($totalDeductions) . "</td>
                <td><b class='text-success'>" . showValue($net) . "</b></td>
                <td>
                    <button class='btn btn-info btn-sm' type='button' data-bs-toggle='collapse' data-bs-target='#details_$emp[EmpID]'>Show Details</button>
                    <button class='btn btn-primary btn-sm' type='button' data-bs-toggle='modal' data-bs-target='#payslipModal' onclick='openPayslipModal(" . htmlspecialchars($empData, ENT_QUOTES) . ")'>Payslip</button>
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

<!-- 🎯 Payslip Modal -->
<div class="modal fade" id="payslipModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Generate Payslip</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="empInfo" class="mb-4"></div>
                
                <h6 class="mb-3"><strong>📅 Select Date Range (Weekly):</strong></h6>
                <div id="weeklyOptions" class="row g-3 mb-4"></div>

                <div class="d-flex gap-2 justify-content-center flex-wrap">
                    <button class="btn btn-success" id="downloadBtn" onclick="downloadPayslip()">
                        📥 Download PDF
                    </button>
                    <button class="btn btn-info" id="emailBtn" onclick="sendEmail()">
                        📧 Send to Email
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<footer class="text-center py-3 mt-auto sticky-footer" style="background: var(--surface); color: var(--primary-dark); font-size: 1.05rem; border-top: 1px solid var(--primary-light);">
    Powered by <strong>Angelica Gregorio</strong> and <strong>Ysabella Santos</strong>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
let selectedEmp = null;
let selectedWeek = null;

function openPayslipModal(empData) {
    selectedEmp = empData;
    selectedWeek = null;

    // Display employee info
    const infoHtml = `
        <div class="alert alert-info">
            <strong>${empData.Name}</strong> (ID: ${empData.EmpID})<br>
            <small>Email: ${empData.Email}</small>
        </div>
    `;
    document.getElementById('empInfo').innerHTML = infoHtml;

    // Generate weekly options
    const monthNum = '<?php echo isset($_GET['month']) ? $_GET['month'] : '01'; ?>';
    const year = '<?php echo date('Y'); ?>';
    
    const weeklyOptions = [
        { label: 'Week 03-09', start: `${year}-${monthNum}-03`, end: `${year}-${monthNum}-09` },
        { label: 'Week 10-16', start: `${year}-${monthNum}-10`, end: `${year}-${monthNum}-16` },
        { label: 'Week 17-23', start: `${year}-${monthNum}-17`, end: `${year}-${monthNum}-23` },
        { label: 'Week 24-30', start: `${year}-${monthNum}-24`, end: `${year}-${monthNum}-30` }
    ];

    let htmlOptions = '';
    weeklyOptions.forEach((week, idx) => {
        htmlOptions += `
            <div class="col-md-6">
                <div class="week-option" onclick="selectWeek(${idx}, this, '${week.label}', '${week.start}', '${week.end}')">
                    <strong>${week.label}</strong>
                </div>
            </div>
        `;
    });
    document.getElementById('weeklyOptions').innerHTML = htmlOptions;
}

function selectWeek(idx, element, label, start, end) {
    document.querySelectorAll('.week-option').forEach(el => el.classList.remove('selected'));
    element.classList.add('selected');
    selectedWeek = { label: label, start: start, end: end };
}

function downloadPayslip() {
    if (!selectedWeek) {
        alert('Please select a week first!');
        return;
    }
    
    const formData = new FormData();
    formData.append('empName', selectedEmp.Name);
    formData.append('empID', selectedEmp.EmpID);
    formData.append('weekLabel', selectedWeek.label);
    formData.append('weekStart', selectedWeek.start);
    formData.append('weekEnd', selectedWeek.end);
    formData.append('empData', JSON.stringify(selectedEmp));

    fetch('generate_payslip_pdf.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.blob())
    .then(blob => {
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `Payslip_${selectedEmp.Name}_${selectedWeek.label}.pdf`;
        a.click();
        window.URL.revokeObjectURL(url);
        alert('Payslip downloaded successfully!');
    })
    .catch(err => alert('Download failed: ' + err.message));
}

function sendEmail() {
    if (!selectedWeek) {
        alert('Please select a week first!');
        return;
    }
    
    const formData = new FormData();
    formData.append('empName', selectedEmp.Name);
    formData.append('empEmail', selectedEmp.Email);
    formData.append('empID', selectedEmp.EmpID);
    formData.append('weekLabel', selectedWeek.label);
    formData.append('weekStart', selectedWeek.start);
    formData.append('weekEnd', selectedWeek.end);
    formData.append('empData', JSON.stringify(selectedEmp));

    fetch('send_payslip_email.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        alert(data.message);
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('payslipModal')).hide();
        }
    })
    .catch(err => alert('Error: ' + err.message));
}
</script>
<script src="script.js"></script>
</body>
</html>