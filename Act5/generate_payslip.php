<?php
require_once('fpdf/fpdf.php');
require_once('mailer.php'); // Assuming you have a mailer setup

if (isset($_GET['name']) && isset($_GET['email'])) {
    $name = $_GET['name'];
    $email = $_GET['email'];

    // Fetch employee data (replace with actual database query)
    // Example data for demonstration
    $employeeData = [
        'DaysOfWork' => 20,
        'Rate' => 600,
        'GrossIncome' => 12000,
        'TotalDeductions' => 2000,
        'NetIncome' => 10000
    ];

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

    $pdf->Cell(50, 10, "Gross Income:", 0, 0);
    $pdf->Cell(50, 10, "₱" . number_format($employeeData['GrossIncome'], 2), 0, 1);

    $pdf->Cell(50, 10, "Total Deductions:", 0, 0);
    $pdf->Cell(50, 10, "₱" . number_format($employeeData['TotalDeductions'], 2), 0, 1);

    $pdf->Cell(50, 10, "Net Income:", 0, 0);
    $pdf->Cell(50, 10, "₱" . number_format($employeeData['NetIncome'], 2), 0, 1);

    $filePath = "payslips/{$name}_payslip.pdf";
    $pdf->Output('F', $filePath);

    // Send email with payslip
    $subject = "Payslip for $name";
    $body = "Dear $name,\n\nPlease find attached your payslip.\n\nBest regards,\nPayroll Team";
    $attachments = [$filePath];

    if (sendEmail($email, $subject, $body, $attachments)) {
        echo "Payslip sent successfully to $email.";
    } else {
        echo "Failed to send payslip to $email.";
    }
} else {
    echo "Invalid request.";
}
?>