<?php
require_once('fpdf/fpdf.php');
require_once('mailer.php'); // Assuming you have a mailer setup
require_once('db_connection.php'); // Include your database connection

if (isset($_GET['start_date']) && isset($_GET['end_date']) && isset($_GET['name'])) {
    $start_date = $_GET['start_date'];
    $end_date = $_GET['end_date'];
    $name = $_GET['name'];

    // Fetch employee data based on date range
    $query = "SELECT t.Name, COUNT(DISTINCT t.ShiftDate) AS DaysOfWork, e.Rate, SUM(t.Hours) AS TotalHours, e.SSS, e.PHIC, e.HDMF, e.GOVT
              FROM timesheet t
              JOIN employees e ON t.Name = e.Name
              WHERE t.Name = ? AND t.ShiftDate BETWEEN ? AND ?
              GROUP BY t.Name";

    $stmt = $conn->prepare($query);
    $stmt->bind_param('sss', $name, $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $employeeData = $result->fetch_assoc();

        // Generate PDF
        $pdf = new FPDF();
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 16);
        $pdf->Cell(0, 10, "Payslip for $name", 0, 1, 'C');
        $pdf->SetFont('Arial', '', 12);
        $pdf->Ln(10);

        $pdf->Cell(50, 10, "Days of Work:", 0, 0);
        $pdf->Cell(50, 10, $employeeData['DaysOfWork'], 0, 1);

        $pdf->Cell(50, 10, "Rate:", 0, 0);
        $pdf->Cell(50, 10, "₱" . number_format($employeeData['Rate'], 2), 0, 1);

        $pdf->Cell(50, 10, "Total Hours:", 0, 0);
        $pdf->Cell(50, 10, $employeeData['TotalHours'], 0, 1);

        $grossIncome = $employeeData['Rate'] * $employeeData['DaysOfWork'];
        $pdf->Cell(50, 10, "Gross Income:", 0, 0);
        $pdf->Cell(50, 10, "₱" . number_format($grossIncome, 2), 0, 1);

        $totalDeductions = $employeeData['SSS'] + $employeeData['PHIC'] + $employeeData['HDMF'] + $employeeData['GOVT'];
        $pdf->Cell(50, 10, "Total Deductions:", 0, 0);
        $pdf->Cell(50, 10, "₱" . number_format($totalDeductions, 2), 0, 1);

        $netIncome = $grossIncome - $totalDeductions;
        $pdf->Cell(50, 10, "Net Income:", 0, 0);
        $pdf->Cell(50, 10, "₱" . number_format($netIncome, 2), 0, 1);

        $filePath = "payslips/{$name}_payslip.pdf";
        $pdf->Output('F', $filePath);

        echo "Payslip generated successfully. <a href='$filePath' download>Download Payslip</a>";
    } else {
        echo "No records found for the specified date range.";
    }
} else {
    echo "Invalid request. Please provide a name and date range.";
}
?>