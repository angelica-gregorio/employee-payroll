<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "act05";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

session_start();

// Handle add form submission
if (isset($_POST["add"])) {
    $names = $_POST["name"];
    $departments = $_POST["department"];
    $rates = $_POST["rate"];
    $sssArr = $_POST["sss"];
    $phicArr = $_POST["phic"];
    $hdmfArr = $_POST["hdmf"];
    $govtArr = $_POST["govt"];
    $emails = $_POST["email"];

    $success = 0;
    $fail = 0;
    for ($i = 0; $i < count($names); $i++) {
        $name = $conn->real_escape_string($names[$i]);
        $department = $conn->real_escape_string($departments[$i]);
        $rate = (float)$rates[$i];
        $sss = (float)$sssArr[$i];
        $phic = (float)$phicArr[$i];
        $hdmf = (float)$hdmfArr[$i];
        $govt = (float)$govtArr[$i];
        $email = $conn->real_escape_string($emails[$i]);

        if ($name === '' || $rate < 0) {
            $fail++;
            continue;
        }

        $sql = "INSERT INTO employees (Name, Department, Rate, SSS, PHIC, HDMF, GOVT, Email)
                VALUES ('$name', '$department', '$rate', '$sss', '$phic', '$hdmf', '$govt', '$email')";
        if ($conn->query($sql)) {
            $success++;
        } else {
            $fail++;
        }
    }
    if ($success > 0) {
        $_SESSION['toast'] = "$success employee(s) added successfully!";
    }
    if ($fail > 0) {
        $_SESSION['toast'] = (isset($_SESSION['toast']) ? $_SESSION['toast'] . ' ' : '') . "❌ $fail failed to add!";
    }
    header("Location: employees.php");
    exit;
}

// Handle update form submission
if (isset($_POST["update"])) {
    $ids = (array)$_POST["id"];
    $names = (array)$_POST["name"];
    $departments = (array)$_POST["department"];
    $rates = (array)$_POST["rate"];
    $sssArr = (array)$_POST["sss"];
    $phicArr = (array)$_POST["phic"];
    $hdmfArr = (array)$_POST["hdmf"];
    $govtArr = (array)$_POST["govt"];
    $emails = (array)$_POST["email"];

    $success = 0;
    $fail = 0;
    for ($i = 0; $i < count($ids); $i++) {
        $id = (int)$ids[$i];
        $name = trim($conn->real_escape_string($names[$i]));
        $department = $conn->real_escape_string($departments[$i]);
        $rate = (float)$rates[$i];
        $sss = (float)$sssArr[$i];
        $phic = (float)$phicArr[$i];
        $hdmf = (float)$hdmfArr[$i];
        $govt = (float)$govtArr[$i];
        $email = $conn->real_escape_string($emails[$i]);

        if ($rate < 0) {
            $fail++;
            continue;
        }
        $sql = "UPDATE employees
                SET Name='$name', Department='$department', Rate='$rate',
                    SSS='$sss', PHIC='$phic', HDMF='$hdmf', GOVT='$govt', Email='$email'
                WHERE EmpID='$id'";
        if ($conn->query($sql)) {
            $success++;
        } else {
            $fail++;
        }
    }
    if ($success > 0) {
        $_SESSION['toast'] = "$success employee(s) updated successfully!";
    }
    if ($fail > 0) {
        $_SESSION['toast'] = (isset($_SESSION['toast']) ? $_SESSION['toast'] . ' ' : '') . "❌ $fail failed to update!";
    }
    header("Location: employees.php");
    exit;
}

// Handle delete form submission
if (isset($_POST["delete"])) {
    $ids = (array)$_POST["id"];
    $success = 0;
    $fail = 0;
    foreach ($ids as $id) {
        $id = (int)$id;
        $sql = "DELETE FROM employees WHERE EmpID='$id'";
        if ($conn->query($sql)) {
            $success++;
        } else {
            $fail++;
        }
    }
    if ($success > 0) {
        $_SESSION['toast'] = "$success employee(s) deleted successfully!";
    }
    if ($fail > 0) {
        $_SESSION['toast'] = (isset($_SESSION['toast']) ? $_SESSION['toast'] . ' ' : '') . "❌ $fail failed to delete!";
    }
    header("Location: employees.php");
    exit;
}

// Get all employees
$result = $conn->query("SELECT EmpID, Name, Department, Rate, SSS, PHIC, HDMF, GOVT, Email FROM employees ORDER BY Name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Employee List</title>
    <link href="https://fonts.googleapis.com/css2?family=Google+Sans:wght@400;500;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="style.css?v=<?php echo time(); ?>" rel="stylesheet">
    <style>
        .table th { background-color: var(--primary); color: white; }
        .btn-edit { background-color: var(--primary); color: white; }
        .btn-edit:hover { background-color: var(--primary-dark); color: white; }
        
        /* Modal Header Styling */
        .custom-modal .modal-header {
            background: linear-gradient(135deg, var(--primary) 0%, #0056b3 100%);
            color: white;
            border-bottom: none;
        }
        
        .custom-modal .modal-title {
            color: white;
            font-weight: 600;
        }
        
        .custom-modal .btn-close-white {
            filter: brightness(0) invert(1);
        }
        
        /* Modal Body */
        .custom-modal .modal-body {
            background: var(--surface);
            color: var(--text-dark);
        }
        
        body.dark-mode .custom-modal .modal-body {
            background: #1e1e1e;
            color: var(--text-light);
        }
        
        /* Form Row Styling */
        .add-row, .update-row, .delete-row {
            border-bottom: 1px solid #dee2e6;
            padding-bottom: 1rem;
            margin-bottom: 1rem;
        }
        
        body.dark-mode .add-row,
        body.dark-mode .update-row,
        body.dark-mode .delete-row {
            border-bottom-color: #33364a;
        }
        
        .add-row:last-child, .update-row:last-child, .delete-row:last-child {
            border-bottom: none;
        }
        
        /* Link Styling */
        .text-decoration-none {
            color: var(--primary);
        }
        
        .text-decoration-none:hover {
            color: var(--primary-dark);
            text-decoration: underline !important;
        }
        
        /* Force Navbar Modern Styles */
        .modern-navbar.navbar-dark .navbar-nav .nav-link-modern {
            color: rgba(255,255,255,0.9) !important;
        }
        
        .modern-navbar.navbar-dark .navbar-nav .nav-link-modern:hover {
            color: white !important;
        }
        
        .modern-navbar .action-btn-modern,
        .modern-navbar .dark-mode-toggle {
            color: white !important;
        }
    </style>
</head>
<body class="d-flex flex-column min-vh-100">

<!-- Modern Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark modern-navbar">
    <div class="container-fluid">
        <a class="navbar-brand navbar-brand-modern" href="index.php">
            <img src="office-building.png" alt="Logo">
            <span>EMS</span>
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link nav-link-modern" href="index.php">
                        <span class="material-icons" style="font-size:20px;">dashboard</span>
                        Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link nav-link-modern active" href="employees.php">
                        <span class="material-icons" style="font-size:20px;">group</span>
                        Employees
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link nav-link-modern" href="salary_summary.php">
                        <span class="material-icons" style="font-size:20px;">paid</span>
                        Salary
                    </a>
                </li>
            </ul>

            <div class="d-flex align-items-center gap-2 flex-wrap">
                <div class="dropdown">
                    <button class="action-btn-modern dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <span class="material-icons" style="font-size:18px;">settings</span>
                        Actions
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end dropdown-menu-modern">
                        <li>
                            <a class="dropdown-item dropdown-item-modern" href="#" data-bs-toggle="modal" data-bs-target="#addModal">
                                <span class="material-icons">person_add</span>
                                Add Employee
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item dropdown-item-modern" href="#" data-bs-toggle="modal" data-bs-target="#updateModal">
                                <span class="material-icons">edit</span>
                                Update Employee
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item dropdown-item-modern" href="#" data-bs-toggle="modal" data-bs-target="#deleteModal">
                                <span class="material-icons">delete</span>
                                Delete Employee
                            </a>
                        </li>
                    </ul>
                </div>

                <div class="navbar-divider d-none d-lg-block"></div>

                <button id="toggleDarkMode" class="dark-mode-toggle" title="Toggle dark/light mode">
                    <span id="darkModeIconSwitch" class="material-icons">dark_mode</span>
                </button>
            </div>
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
                    <th>Rate</th>
                    <th>SSS</th>
                    <th>PHIC</th>
                    <th>HDMF</th>
                    <th>GOVT</th>
                    <th>Email</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if ($result && $result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        echo "<tr>
                                <td>{$row['EmpID']}</td>
                                <td>{$row['Name']}</td>
                                <td>{$row['Department']}</td>
                                <td>₱" . number_format($row['Rate'], 2) . "</td>
                                <td>₱" . number_format($row['SSS'], 2) . "</td>
                                <td>₱" . number_format($row['PHIC'], 2) . "</td>
                                <td>₱" . number_format($row['HDMF'], 2) . "</td>
                                <td>₱" . number_format($row['GOVT'], 2) . "</td>
                                <td>{$row['Email']}</td>
                              </tr>";
                    }
                } else {
                    echo "<tr><td colspan='9' class='text-center text-muted'>No employees found</td></tr>";
                }
                ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Modal -->
<div class="modal fade custom-modal" id="addModal" tabindex="-1" aria-labelledby="addModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 500px;">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addModalLabel">Add Employee</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="post" id="addForm">
                    <div id="addFields">
                        <div class="add-row">
                            <input type="text" name="name[]" class="form-control mb-2" placeholder="Name" required>
                            <select name="department[]" class="form-select mb-2" required>
                                <option value="">Select Department</option>
                                <option value="Main Office">Main Office</option>
                                <option value="Service Crew">Service Crew</option>
                                <option value="Canteen">Canteen</option>
                                <option value="Satellite Office">Satellite Office</option>
                            </select>
                            <input type="number" name="rate[]" class="form-control mb-2" placeholder="Rate" step="0.01" required>
                            <input type="number" name="sss[]" class="form-control mb-2" placeholder="SSS" step="0.01" required>
                            <input type="number" name="phic[]" class="form-control mb-2" placeholder="PHIC" step="0.01" required>
                            <input type="number" name="hdmf[]" class="form-control mb-2" placeholder="HDMF" step="0.01" required>
                            <input type="number" name="govt[]" class="form-control mb-2" placeholder="GOVT" step="0.01" required>
                            <input type="email" name="email[]" class="form-control mb-2" placeholder="Email" required>
                        </div>
                    </div>
                    <button type="button" class="btn btn-outline-success w-100 mb-2" id="addMoreBtn">Add More</button>
                    <div class="d-grid gap-2 mt-2">
                        <button type="submit" name="add" class="btn btn-primary">Add</button>
                    </div>
                </form>
                <div class="text-center mt-3">
                    <a href="#" id="updateInsteadLink" class="text-decoration-none">Update instead?</a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Update Modal -->
<div class="modal fade custom-modal" id="updateModal" tabindex="-1" aria-labelledby="updateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 500px;">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="updateModalLabel">Update Employee</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="post" id="updateForm">
                    <div id="updateFields">
                        <div class="update-row">
                            <input type="number" name="id[]" class="form-control mb-2" placeholder="Employee ID" required>
                            <input type="text" name="name[]" class="form-control mb-2" placeholder="Name" required>
                            <select name="department[]" class="form-select mb-2" required>
                                <option value="">Select Department</option>
                                <option value="Main Office">Main Office</option>
                                <option value="Service Crew">Service Crew</option>
                                <option value="Canteen">Canteen</option>
                                <option value="Satellite Office">Satellite Office</option>
                            </select>
                            <input type="number" name="rate[]" class="form-control mb-2" placeholder="Rate" step="0.01" required>
                            <input type="number" name="sss[]" class="form-control mb-2" placeholder="SSS" step="0.01" required>
                            <input type="number" name="phic[]" class="form-control mb-2" placeholder="PHIC" step="0.01" required>
                            <input type="number" name="hdmf[]" class="form-control mb-2" placeholder="HDMF" step="0.01" required>
                            <input type="number" name="govt[]" class="form-control mb-2" placeholder="GOVT" step="0.01" required>
                            <input type="email" name="email[]" class="form-control mb-2" placeholder="Email" required>
                        </div>
                    </div>
                    <button type="button" class="btn btn-outline-warning w-100 mb-2" id="updateMoreBtn">Update More</button>
                    <div class="d-grid gap-2 mt-2">
                        <button type="submit" name="update" class="btn btn-warning">Update</button>
                    </div>
                </form>
                <div class="text-center mt-3">
                    <a href="#" id="addInsteadLink" class="text-decoration-none">Add instead?</a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Delete Modal -->
<div class="modal fade custom-modal" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 420px;">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteModalLabel">Delete Employee</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="post" id="deleteForm">
                    <div id="deleteFields">
                        <div class="delete-row">
                            <input type="number" name="id[]" class="form-control mb-2" placeholder="Employee ID" required>
                        </div>
                    </div>
                    <button type="button" class="btn btn-outline-danger w-100 mb-2" id="deleteMoreBtn">Delete More</button>
                    <button type="submit" name="delete" class="btn btn-danger w-100">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Toast -->
<div class="toast-container position-fixed bottom-0 end-0 p-3">
    <?php if (isset($_SESSION['toast'])): ?>
        <div class="toast align-items-center text-bg-success border-0 show">
            <div class="d-flex">
                <div class="toast-body"><?= $_SESSION['toast']; ?></div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
        <?php unset($_SESSION['toast']); ?>
    <?php endif; ?>
</div>

<!-- FOOTER -->
<footer class="text-center py-3 mt-auto sticky-footer"
        style="background: var(--surface); color: var(--primary-dark);
               font-size: 1.05rem; border-top: 1px solid var(--primary-light);">
    Powered by <strong>Angelica Gregorio</strong> and <strong>Ysabella Santos</strong>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Dark Mode Toggle
const toggleDarkMode = document.getElementById('toggleDarkMode');
const darkModeIcon = document.getElementById('darkModeIconSwitch');

if (localStorage.getItem('theme') === 'dark') {
    document.body.classList.add('dark-mode');
    darkModeIcon.textContent = 'light_mode';
}

toggleDarkMode.addEventListener('click', () => {
    document.body.classList.toggle('dark-mode');
    
    if (document.body.classList.contains('dark-mode')) {
        darkModeIcon.textContent = 'light_mode';
        darkModeIcon.classList.add('sunrise');
        localStorage.setItem('theme', 'dark');
    } else {
        darkModeIcon.textContent = 'dark_mode';
        darkModeIcon.classList.add('sundown');
        localStorage.setItem('theme', 'light');
    }
    
    setTimeout(() => {
        darkModeIcon.classList.remove('sunrise', 'sundown');
    }, 700);
});

// Add More Button
document.getElementById('addMoreBtn').addEventListener('click', function() {
    const addFields = document.getElementById('addFields');
    const newRow = document.createElement('div');
    newRow.className = 'add-row';
    newRow.innerHTML = `
        <input type="text" name="name[]" class="form-control mb-2" placeholder="Name" required>
        <select name="department[]" class="form-select mb-2" required>
            <option value="">Select Department</option>
            <option value="Main Office">Main Office</option>
            <option value="Service Crew">Service Crew</option>
            <option value="Canteen">Canteen</option>
            <option value="Satellite Office">Satellite Office</option>
        </select>
        <input type="number" name="rate[]" class="form-control mb-2" placeholder="Rate" step="0.01" required>
        <input type="number" name="sss[]" class="form-control mb-2" placeholder="SSS" step="0.01" required>
        <input type="number" name="phic[]" class="form-control mb-2" placeholder="PHIC" step="0.01" required>
        <input type="number" name="hdmf[]" class="form-control mb-2" placeholder="HDMF" step="0.01" required>
        <input type="number" name="govt[]" class="form-control mb-2" placeholder="GOVT" step="0.01" required>
        <input type="email" name="email[]" class="form-control mb-2" placeholder="Email" required>
    `;
    addFields.appendChild(newRow);
});

// Update More Button
document.getElementById('updateMoreBtn').addEventListener('click', function() {
    const updateFields = document.getElementById('updateFields');
    const newRow = document.createElement('div');
    newRow.className = 'update-row';
    newRow.innerHTML = `
        <input type="number" name="id[]" class="form-control mb-2" placeholder="Employee ID" required>
        <input type="text" name="name[]" class="form-control mb-2" placeholder="Name" required>
        <select name="department[]" class="form-select mb-2" required>
            <option value="">Select Department</option>
            <option value="Main Office">Main Office</option>
            <option value="Service Crew">Service Crew</option>
            <option value="Canteen">Canteen</option>
            <option value="Satellite Office">Satellite Office</option>
        </select>
        <input type="number" name="rate[]" class="form-control mb-2" placeholder="Rate" step="0.01" required>
        <input type="number" name="sss[]" class="form-control mb-2" placeholder="SSS" step="0.01" required>
        <input type="number" name="phic[]" class="form-control mb-2" placeholder="PHIC" step="0.01" required>
        <input type="number" name="hdmf[]" class="form-control mb-2" placeholder="HDMF" step="0.01" required>
        <input type="number" name="govt[]" class="form-control mb-2" placeholder="GOVT" step="0.01" required>
        <input type="email" name="email[]" class="form-control mb-2" placeholder="Email" required>
    `;
    updateFields.appendChild(newRow);
});

// Delete More Button
document.getElementById('deleteMoreBtn').addEventListener('click', function() {
    const deleteFields = document.getElementById('deleteFields');
    const newRow = document.createElement('div');
    newRow.className = 'delete-row';
    newRow.innerHTML = `
        <input type="number" name="id[]" class="form-control mb-2" placeholder="Employee ID" required>
    `;
    deleteFields.appendChild(newRow);
});

// Modal Switching
document.getElementById('updateInsteadLink').addEventListener('click', function(e) {
    e.preventDefault();
    bootstrap.Modal.getInstance(document.getElementById('addModal')).hide();
    new bootstrap.Modal(document.getElementById('updateModal')).show();
});

document.getElementById('addInsteadLink').addEventListener('click', function(e) {
    e.preventDefault();
    bootstrap.Modal.getInstance(document.getElementById('updateModal')).hide();
    new bootstrap.Modal(document.getElementById('addModal')).show();
});

// Auto-hide toast after 5 seconds
setTimeout(() => {
    const toastEl = document.querySelector('.toast');
    if (toastEl) {
        const toast = new bootstrap.Toast(toastEl);
        toast.hide();
    }
}, 5000);
</script>
</body>
</html>
<?php $conn->close(); ?>