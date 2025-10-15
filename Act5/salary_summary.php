<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "act05";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

/* ✅ Helper Function - showValue() */
function showValue($val) {
    return ($val > 0) ? '₱' . number_format($val, 2) : '-';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Salary Summary</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="salary_summary.css" rel="stylesheet">
</head>
<body class="p-4 bg-light">

<h2 class="text-center mb-4 fw-bold">SALARY SUMMARY</h2>

<form method="GET" class="d-flex justify-content-center align-items-center gap-2 mb-4">
  <label>From:</label>
  <input type="date" name="start_date" class="form-control" style="max-width:200px;" required>
  <label>To:</label>
  <input type="date" name="end_date" class="form-control" style="max-width:200px;" required>
  <button type="submit" class="btn btn-primary">Generate</button>
</form>

<?php
if (isset($_GET['start_date']) && isset($_GET['end_date'])) {
  $start = $_GET['start_date'];
  $end   = $_GET['end_date'];

  echo "<h5 class='text-center text-success mb-3'>
          Showing salaries from <b>$start</b> to <b>$end</b>
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
    e.Department,
    e.Rate,
    e.SSS,
    e.PHIC,
    e.HDMF,
    e.GOVT,
    h.Type AS HolidayType,
    h.Rate AS HolidayRate
  FROM timesheet t
  JOIN employees e ON t.Name = e.Name
  LEFT JOIN holidays h ON t.ShiftDate = h.Date
  WHERE t.ShiftDate BETWEEN '$start' AND '$end'
  ORDER BY t.Name, t.ShiftDate
";

  $result = $conn->query($sql);

  if ($result && $result->num_rows > 0) {

    $data = [];

    // ---------- BUILD AGGREGATE ----------
    while ($row = $result->fetch_assoc()) {
      $empID = $row['EmpID'] ?? '';
      $name  = $row['Name'];
      $rate  = (float)$row['Rate'];
      $role  = strtolower(trim($row['Role']));
      $shift = (int)$row['ShiftNo'];
      $hours = (float)$row['Hours'];
      $note  = strtolower(trim($row['Notes']));
      $holidayRate = (float)$row['HolidayRate'];
      $deductVal   = isset($row['Deductions']) ? abs((float)str_replace('-', '', $row['Deductions'])) : 0;

      if (!isset($data[$name])) {
        $data[$name] = [
          'EmpID' => $empID,
          'Rate' => $rate,
          'DaysWorked' => [],
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

      $validTimePattern = '/^(?:[01]\d|2[0-3]):[0-5]\d:[0-5]\d$/';
      if (!empty($row['ShiftDate']) && preg_match($validTimePattern, trim($row['TimeIN'])) && preg_match($validTimePattern, trim($row['TimeOUT']))) {
        $data[$name]['DaysWorked'][$row['ShiftDate']] = true;
      }

      if ($shift >= 3) $data[$name]['NightShifts']++;

      if (!empty($row['HolidayType']) && isset($data[$name]['DaysWorked'][$row['ShiftDate']])) {
        $holidayExtra = $rate * $holidayRate;
        $data[$name]['HolidayPay'] += $holidayExtra;
      }

      if (strtolower(trim($row['DutyType'])) === 'overtime' && is_numeric($hours)) {
        $data[$name]['OvertimeHrs'] += $hours;
      }

      if (strtolower(trim($row['DutyType'])) === 'late') {
        $data[$name]['LateDeduction'] += 150;
      }

      if ($role === 'cashier' && $hours >= 8) {
        $data[$name]['CashierBonus'] += 40;
      }

      if ($note === 'sil') {
        $data[$name]['SILBonus'] += 1560;
      } elseif ($note === 'short') {
        $data[$name]['Shortage'] += $deductVal;
      } elseif ($note === 'ca' || $note === 'uniform') {
        $data[$name]['CAUniform'] += 106;
      }
    }

    echo "<div class='table-responsive'>
          <table class='table table-bordered text-center align-middle'>
            <thead class='table-dark'>
              <tr>
                <th>EmpID</th>
                <th>Name</th>
                <th>Gross Income</th>
                <th>Total Deductions</th>
                <th>Net Income</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>";

    $grandTotal = 0;
    $rowIndex = 0;

    foreach ($data as $name => $emp) {
      $rowIndex++;
      $days = count($emp['DaysWorked']);
      $rate = floatval($emp['Rate']);
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
      $sss = $emp['SSS']; $phic = $emp['PHIC']; $hdmf = $emp['HDMF']; $govt = $emp['GOVT'];
      $totalDeductions = $sss + $phic + $hdmf + $govt + $lateDeduction + $shortage + $caUniform;
      $net = $gross - $totalDeductions;
      $grandTotal += $net;

      echo "
      <tr>
        <td>{$emp['EmpID']}</td>
        <td>$name</td>
        <td><b>" . showValue($gross) . "</b></td>
        <td>" . showValue($totalDeductions) . "</td>
        <td class='fw-bold text-success'>" . showValue($net) . "</td>
        <td><button class='btn btn-sm btn-info' onclick=\"toggleDetails('details$rowIndex', this)\">Show Details</button></td>
      </tr>

      <tr id='details$rowIndex' class='details-row'>
        <td colspan='6'>
          <table class='payslip-table table-sm'>
            <tr><td>Rate</td><td>" . showValue($rate) . "</td></tr>
            <tr><td>Work Days</td><td>" . ($days > 0 ? $days : '-') . "</td></tr>
            <tr><td>Overtime Hours</td><td>" . ($emp['OvertimeHrs'] > 0 ? $emp['OvertimeHrs'] : '-') . "</td></tr>
            <tr><td>Allowance</td><td>" . showValue($allowance) . "</td></tr>
            <tr><td>Night Differential</td><td>" . showValue($nightDiff) . "</td></tr>
            <tr><td>Holiday Pay</td><td>" . showValue($holiday) . "</td></tr>
            <tr><td>Cashier Bonus</td><td>" . showValue($cashierBonus) . "</td></tr>
            <tr><td>SIL Bonus</td><td>" . showValue($silBonus) . "</td></tr>
            <tr><td>Shortage Deduction</td><td>" . showValue($shortage) . "</td></tr>
            <tr><td>CA / Uniform Deduction</td><td>" . showValue($caUniform) . "</td></tr>
            <tr><td>Late Deduction</td><td>" . showValue($lateDeduction) . "</td></tr>
            <tr><td>SSS</td><td>" . showValue($sss) . "</td></tr>
            <tr><td>PHIC</td><td>" . showValue($phic) . "</td></tr>
            <tr><td>HDMF</td><td>" . showValue($hdmf) . "</td></tr>
            <tr><td>GOVT</td><td>" . showValue($govt) . "</td></tr>
            <tr><td><b>Total Deductions</b></td><td><b>" . showValue($totalDeductions) . "</b></td></tr>
            <tr><td><b>Net Income</b></td><td><b class='text-success'>" . showValue($net) . "</b></td></tr>
          </table>
        </td>
      </tr>";
    }

    echo "</tbody>
          <tfoot class='table-secondary fw-bold'>
            <tr>
              <td colspan='4' class='text-end'>GRAND TOTAL NET INCOME:</td>
              <td colspan='2'>" . showValue($grandTotal) . "</td>
            </tr>
          </tfoot>
          </table></div>";
  } else {
    echo "<div class='alert alert-warning text-center'>No records found for this date range.</div>";
  }
}
?>

<div class='text-center mt-4'>
  <a href='index.php' class='btn btn-secondary'>⬅ Back to Dashboard</a>
</div>

<script src="salary_summary.js"></script>
</body>
</html>
