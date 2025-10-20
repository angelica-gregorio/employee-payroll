<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "act05";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle update form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update'])) {
    $empID = $_POST['EmpID'];
    $name = $_POST['Name'];
    $department = $_POST['Department'];
    $email = $_POST['Email'];
    $rate = $_POST['Rate'];

    $stmt = $conn->prepare("UPDATE employees SET Name=?, Department=?, Email=?, Rate=? WHERE EmpID=?");
    $stmt->bind_param("sssdi", $name, $department, $email, $rate, $empID);

    if ($stmt->execute()) {
        echo "<script>alert('Employee record updated successfully!'); window.location.href='employees.php';</script>";
    } else {
        echo "<script>alert('Error updating record.');</script>";
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Employee List</title>
    <link href="https://fonts.googleapis.com/css2?family=Google+Sans:wght@400;500;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
    <style>
        body { font-family: 'Google Sans', sans-serif; }
        .modal-header { background-color: var(--primary); color: white; }
        .table th { background-color: var(--primary); color: white; }
        .btn-edit { background-color: var(--primary); color: white; }
        .btn-edit:hover { background-color: var(--primary-dark); color: white; }
    </style>
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
            <a href="salary_summary.php" class="btn btn-outline-secondary">Salary Summary</a>
            <a href="employees.php" class="btn btn-outline-primary">Employees</a>
        </div>
    </div>
</nav>

<!-- MAIN CONTENT -->
<div class="container my-4">
    <h2 class="text-center mb-4 fw-bold">EMPLOYEE LIST</h2>
    <div class="table-responsive">
        <table class="table table-bordered text-center align-middle shadow-sm">
            <thead class="table-dark">
                <tr>
                    <th>EmpID</th>
                    <th>Name</th>
                    <th>Department</th>
                    <th>Email</th>
                    <th>Rate</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $result = $conn->query("SELECT EmpID, Name, Department, Email, Rate FROM employees ORDER BY Name");
                if ($result && $result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        echo "<tr>
                                <td>{$row['EmpID']}</td>
                                <td>{$row['Name']}</td>
                                <td>{$row['Department']}</td>
                                <td>{$row['Email']}</td>
                                <td>₱" . number_format($row['Rate'], 2) . "</td>
                                <td>
                                    <button class='btn btn-sm btn-edit' 
                                        data-bs-toggle='modal' 
                                        data-bs-target='#editModal'
                                        data-id='{$row['EmpID']}'
                                        data-name='{$row['Name']}'
                                        data-dept='{$row['Department']}'
                                        data-email='{$row['Email']}'
                                        data-rate='{$row['Rate']}'>
                                        <i class='bi bi-pencil-square'></i> Edit
                                    </button>
                                </td>
                              </tr>";
                    }
                } else {
                    echo "<tr><td colspan='6' class='text-center text-muted'>No employees found</td></tr>";
                }
                ?>
            </tbody>
        </table>
    </div>
</div>

<!-- EDIT MODAL -->
<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" action="employees.php">
        <div class="modal-header">
          <h5 class="modal-title" id="editModalLabel">Edit Employee</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="EmpID" id="editEmpID">
          <div class="mb-3">
            <label class="form-label">Name</label>
            <input type="text" class="form-control" name="Name" id="editName" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Department</label>
            <select class="form-select" name="Department" id="editDept" required>
                <option value="Main Office">Main Office</option>
              <option value="Service Crew">Service Crew</option>
              <option value="Canteen">Canteen</option>
              <option value="Satellite Office">Satellite Office</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Email</label>
            <input type="email" class="form-control" name="Email" id="editEmail" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Rate (₱)</label>
            <input type="number" class="form-control" name="Rate" id="editRate" step="0.01" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" name="update" class="btn btn-primary">Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- FOOTER -->
<footer class="text-center py-3 mt-auto sticky-footer"
        style="background: var(--surface); color: var(--primary-dark);
               font-size: 1.05rem; border-top: 1px solid var(--primary-light);">
    Powered by <strong>Angelica Gregorio</strong> and <strong>Ysabella Santos</strong>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Populate modal with existing data
const editModal = document.getElementById('editModal');
editModal.addEventListener('show.bs.modal', event => {
    const button = event.relatedTarget;
    document.getElementById('editEmpID').value = button.getAttribute('data-id');
    document.getElementById('editName').value = button.getAttribute('data-name');
    document.getElementById('editDept').value = button.getAttribute('data-dept');
    document.getElementById('editEmail').value = button.getAttribute('data-email');
    document.getElementById('editRate').value = button.getAttribute('data-rate');
});
</script>
</body>
</html>
<?php $conn->close(); ?>
