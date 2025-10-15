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

// Update predefined date ranges for monthly payslips
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
<html>
<head>
    <title>Salary Summary</title>
    <!-- Google Fonts: Google Sans (Product Sans) -->
    <link href="https://fonts.googleapis.com/css2?family=Google+Sans:wght@400;500;700&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 -->
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
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <a href="index.php" class="btn btn-outline-secondary">Dashboard</a>
                <a href="salary_summary.php" class="btn btn-outline-primary">Salary Summary</a>
            </div>
        </div>
    </div>
</nav>

<!-- Main Content -->
<div class="container my-4">
    <h2 class="text-center mb-4 fw-bold">SALARY SUMMARY</h2>

    <!-- Date Range Selection -->
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
    // Summarized Salary Logic
    // Adjust SQL query to calculate and display important parameters in the summary
    if (isset($_GET['month'])) {
        $selectedMonth = $_GET['month'];
        $start = date('Y') . "-01-03"; // Fixed start date
        $end = date('Y') . "-01-30";   // Fixed end date

        echo "<h5 class='text-center text-success mb-3'>
                Showing salaries for <b>$selectedMonth</b> (January 03-30)
              </h5>";

        $sql = "
        SELECT 
            t.Name,
            COUNT(DISTINCT t.ShiftDate) AS DaysOfWork,
            t.ShiftDate,
            t.ShiftNo,
            t.DutyType,
            t.Hours,
            t.Business_Unit,
            t.Role,
            e.Rate,
            e.Email,
            e.SSS,
            e.PHIC,
            e.HDMF,
            e.GOVT,
            CASE WHEN h.Date IS NOT NULL THEN 1 ELSE 0 END AS IsHoliday
        FROM timesheet t
        JOIN employees e ON t.Name = e.Name
        LEFT JOIN holidays h ON t.ShiftDate = h.Date
        WHERE t.ShiftDate BETWEEN '$start' AND '$end'
          AND t.DutyType NOT IN ('training') -- Exclude training shifts
        GROUP BY t.Name, t.ShiftDate
        ORDER BY t.Name, t.ShiftDate
        ";

        $result = $conn->query($sql);

        if ($result && $result->num_rows > 0) {
            $data = [];

            while ($row = $result->fetch_assoc()) {
                $name = $row['Name'];
                $email = $row['Email'];
                $daysOfWork = (int)$row['DaysOfWork'];
                $rate = (float)$row['Rate'];
                $hours = (float)$row['Hours'];
                $role = strtolower(trim($row['Role']));
                $businessUnit = strtolower(trim($row['Business_Unit']));
                $dutyType = strtolower(trim($row['DutyType']));
                $isHoliday = (int)$row['IsHoliday'];
                $sss = (float)$row['SSS'];
                $phic = (float)$row['PHIC'];
                $hdmf = (float)$row['HDMF'];
                $govt = (float)$row['GOVT'];

                if (!isset($data[$name])) {
                    $data[$name] = [
                        'Email' => $email,
                        'DaysOfWork' => 0,
                        'Rate' => $rate,
                        'GrossIncome' => 0,
                        'TotalDeductions' => 0,
                        'NetIncome' => 0
                    ];
                }

                // Increment Days of Work
                $data[$name]['DaysOfWork']++;

                // Regular Duty Pay
                $regularHours = ($businessUnit === 'canteen') ? 10 : 8;
                $data[$name]['GrossIncome'] += min($hours, $regularHours) * $rate;

                // Government Deductions
                $data[$name]['TotalDeductions'] += $sss + $phic + $hdmf + $govt;

                // Net Income
                $data[$name]['NetIncome'] = $data[$name]['GrossIncome'] - $data[$name]['TotalDeductions'];
            }

            echo "<div class='table-responsive'>
                  <table class='table table-bordered text-center align-middle'>
                    <thead class='table-dark'>
                      <tr>
                        <th>Name</th>
                        <th>Days of Work</th>
                        <th>Rate</th>
                        <th>Gross Income</th>
                        <th>Total Deductions</th>
                        <th>Net Income</th>
                        <th>Action</th>
                      </tr>
                    </thead>
                    <tbody>";

            foreach ($data as $name => $emp) {
                echo "<tr>
                        <td>$name</td>
                        <td>" . number_format($emp['DaysOfWork'], 0) . "</td>
                        <td>₱" . number_format($emp['Rate'], 2) . "</td>
                        <td>₱" . number_format($emp['GrossIncome'], 2) . "</td>
                        <td>₱" . number_format($emp['TotalDeductions'], 2) . "</td>
                        <td><b>₱" . number_format($emp['NetIncome'], 2) . "</b></td>
                        <td><a href='generate_payslip.php?name=$name&email=" . urlencode($emp['Email']) . "' class='btn btn-primary btn-sm'>Generate Payslip</a></td>
                      </tr>";
            }

            echo "</tbody>
                  </table>
                  </div>";
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
