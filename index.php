<?php
/*
============================================================
  Employee Management System - index.php
  ---------------------------------------------------------
  Main PHP file for the Employee Management System web app.
  Handles all backend logic, dashboard, and UI rendering.

  CLASS & SECTION DOCUMENTATION:
  -----------------------------
  - Database connection: Sets up MySQL connection.
  - Insert/Update/Delete Handlers: Process form submissions for CRUD.
  - Search & Filtering: Handles employee search/filter logic.
  - Dashboard: Renders dashboard cards with employee stats.
  - Modals: Bootstrap modals for Add, Update, Delete, Search.
  - Table: Displays employee records with color-coded duty types.
  - JavaScript: Handles dynamic modal field addition and UI logic.

  MAIN CLASSES (CSS):
  -------------------
  - dashboard-card: Card for dashboard stats (light/dark mode styled)
  - employee-table: Main employee data table, responsive
  - dutytype-onduty/late/overtime: Color-coded duty type badges
  - custom-modal: Bootstrap modal overrides for app modals
  - sticky-footer: Footer that sticks to bottom
  - search-query-card/chip: UI for active search filters
  - ...see style.css for more
============================================================
*/

// ---------------------------
// Database connection config
// ---------------------------
$servername = "localhost";
$username = "root";
$password = ""; // no password in XAMPP
$dbname = "act05";

session_start();

// ---------------------------
// Connect to MySQL database
// / ---------------------------
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ---------------------------
// Insert New Employee handler
// ---------------------------
if (isset($_POST["add"])) {
  // Normalize incoming POST arrays (avoid undefined variable / notices)
  $names = isset($_POST["name"]) ? (array)$_POST["name"] : [];
  $shiftDates = isset($_POST["shift_date"]) ? (array)$_POST["shift_date"] : [];
  $shiftNos = isset($_POST['shift_no']) ? (array)$_POST['shift_no'] : [];
  $hoursArr = isset($_POST['hours']) ? (array)$_POST['hours'] : [];
  $dutyTypes = isset($_POST['duty_type']) ? (array)$_POST['duty_type'] : [];

  $success = 0;
  $fail = 0;
  $errors = [];

  // iterate over submitted rows using the count of the name array
  $rows = count($names);
  for ($i = 0; $i < $rows; $i++) {
    $name = $conn->real_escape_string(trim($names[$i] ?? ''));
    $shiftDate = $conn->real_escape_string($shiftDates[$i] ?? '');
    $shiftNo = isset($shiftNos[$i]) ? (int)$shiftNos[$i] : 0;
    $hours = isset($hoursArr[$i]) ? (int)$hoursArr[$i] : 0;
    $rawDuty = trim($dutyTypes[$i] ?? '');
    // Normalize common 'on duty' variants to 'On Duty'
    $dutyType = preg_match('/^on\s*duty$/i', $rawDuty) ? 'On Duty' : $rawDuty;
    $dutyType = $conn->real_escape_string($dutyType);

    // Skip empty or invalid data
    if ($name === '' || $hours < 0 || $shiftNo < 0) {
      $fail++;
      $errors[] = "Row " . ($i + 1) . " skipped: invalid or missing data.";
      continue;
    }

    $businessUnit = $conn->real_escape_string(trim($_POST['business_unit'][$i] ?? ''));
    $role = $conn->real_escape_string(trim($_POST['role'][$i] ?? ''));
    
    $sql = "INSERT INTO timesheet (Name, ShiftDate, ShiftNo, Business_Unit, Hours, Role, DutyType)
        VALUES ('$name', '$shiftDate', '$shiftNo', '$businessUnit', '$hours', '$role', '$dutyType')";
    if ($conn->query($sql)) {
      $success++;
    } else {
      $fail++;
      $errors[] = "Row " . ($i + 1) . " failed: " . $conn->error;
    }
  }
  if ($success > 0) {
    $_SESSION['toast'] = "$success employee(s) added successfully!";
  }
  if ($fail > 0) {
    $_SESSION['toast'] = (isset($_SESSION['toast']) ? $_SESSION['toast'] . ' ' : '') . "❌ $fail failed to add!";
  }
  header("Location: " . $_SERVER['PHP_SELF']);
  exit;
}

// ---------------------------
// Update Employee handler
// ---------------------------
if (isset($_POST["update"])) {
  $ids = (array)$_POST["id"];
  $names = (array)$_POST["name"];
  $shiftDates = (array)$_POST["shift_date"];
  $shiftNos = (array)$_POST['shift_no'];
  $hoursArr = (array)$_POST['hours'];
  $dutyTypes = (array)$_POST['duty_type'];
  $success = 0;
  $fail = 0;
  for ($i = 0; $i < count($ids); $i++) {
    $id = (int)$ids[$i];
    $name = trim($conn->real_escape_string($names[$i]));
    $shiftDate = $conn->real_escape_string($shiftDates[$i]);
    $shiftNo = (int)$shiftNos[$i];
    $hours = (int)$hoursArr[$i];
    // Always use 'On Duty' (with space, title case)
    $dutyType = $conn->real_escape_string(
      preg_match('/^on\s*duty$/i', trim($dutyTypes[$i])) ? 'On Duty' : $dutyTypes[$i]
    );
    if ($hours < 0 || $shiftNo < 0) {
      $fail++;
      continue;
    }
    $businessUnit = $conn->real_escape_string(trim($_POST['business_unit'][$i] ?? ''));
    $role = $conn->real_escape_string(trim($_POST['role'][$i] ?? ''));
    
    $sql = "UPDATE timesheet
        SET Name='$name', ShiftDate='$shiftDate',
            ShiftNo='$shiftNo', Business_Unit='$businessUnit',
            Hours='$hours', Role='$role', DutyType='$dutyType'
        WHERE DataEntryID='$id'";
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
  header("Location: " . $_SERVER['PHP_SELF']);
  exit;
}


// ---------------------------
// Delete Employee handler (single and multi)
// ---------------------------
if (isset($_POST["delete"])) {
  $ids = (array)$_POST["id"];
  $success = 0;
  $fail = 0;
  foreach ($ids as $id) {
    $id = (int)$id;
    $sql = "DELETE FROM timesheet WHERE DataEntryID='$id'";
    if ($conn->query($sql)) {
      $success++;
    } else {
      $fail++;
    }
  }
  // Check if table is now empty, then reset AUTO_INCREMENT
  $check = $conn->query("SELECT COUNT(*) as cnt FROM timesheet");
  $row = $check ? $check->fetch_assoc() : null;
  if ($row && $row['cnt'] == 0) {
    $conn->query("ALTER TABLE timesheet AUTO_INCREMENT = 1");
  }
  if ($success > 0) {
    $_SESSION['toast'] = "$success employee(s) deleted successfully!";
  }
  if ($fail > 0) {
    $_SESSION['toast'] = (isset($_SESSION['toast']) ? $_SESSION['toast'] . ' ' : '') . "❌ $fail failed to delete!";
  }
  header("Location: " . $_SERVER['PHP_SELF']);
  exit;
}

// Multi-delete handler
if (isset($_POST['delete_selected']) && !empty($_POST['selected_ids'])) {
  $ids = array_map('intval', $_POST['selected_ids']);
  $idList = implode(',', $ids);
  $sql = "DELETE FROM timesheet WHERE DataEntryID IN ($idList)";
  if ($conn->query($sql)) {
    // Check if table is now empty, then reset AUTO_INCREMENT
    $check = $conn->query("SELECT COUNT(*) as cnt FROM timesheet");
    $row = $check ? $check->fetch_assoc() : null;
    if ($row && $row['cnt'] == 0) {
      $conn->query("ALTER TABLE timesheet AUTO_INCREMENT = 1");
    }
    $_SESSION['toast'] = "Selected employees deleted successfully!";
  } else {
    $_SESSION['toast'] = "❌ Error deleting selected employees!";
  }
  header("Location: " . $_SERVER['PHP_SELF']);
  exit;
}



// Search Query
$where = [];

if (!empty($_POST["name"])) {
  $search_name = $conn->real_escape_string($_POST["name"]);
  $where[] = "(TRIM(REPLACE(Name, '\r', '')) LIKE '%" . trim($search_name) . "%')";
}

if (!empty($_POST["shift_date"])) {
    $search_date = $conn->real_escape_string($_POST["shift_date"]);
    $where[] = "ShiftDate = '$search_date'";
}

if (!empty($_POST["shift_no"])) {
    $search_no = (int) $_POST["shift_no"];
    $where[] = "ShiftNo = $search_no";
}

// Base queries
$sql_all = "SELECT * FROM timesheet 
            WHERE TRIM(Name) <> '' 
              AND ShiftDate IS NOT NULL 
              AND ShiftNo IS NOT NULL 
              AND Hours IS NOT NULL 
              AND TRIM(DutyType) <> ''";
$sql_filtered = $sql_all;

if (!empty($where)) {
    $sql_filtered .= " AND " . implode(" AND ", $where);
}

$result_all = null; // default hidden
if (isset($_POST["view_all"])) {
    $result_all = $conn->query($sql_all);
}

$result_filtered = $conn->query($sql_filtered);

// when show all button is clicked

// Default: show all employees unless a filter is applied
$show_all = true;


// If any filter is applied, show filtered employees
if (!empty($_POST["name"]) || !empty($_POST["shift_date"]) || !empty($_POST["shift_no"])) {
  $show_all = false;
}
if (isset($_POST["view_all"])) {
  $show_all = true;
}

// Run queries conditionally
if ($show_all) {
    $result_all = $conn->query($sql_all);
    $result_filtered = null; // disable filtered
} else {
    $result_filtered = $conn->query($sql_filtered);
    $result_all = null; // disable all
}

// Export CSV (Filtered or All)
if (isset($_POST["export_all"]) || isset($_POST["export_filtered"])) {
    $export_sql = isset($_POST["export_filtered"]) ? $sql_filtered : $sql_all;
    $exportResult = $conn->query($export_sql);

    if ($exportResult->num_rows > 0) {
        $filename = isset($_POST["export_filtered"]) ? "employees_filtered.csv" : "employees_all.csv";
        header('Content-Type: text/csv');
        header("Content-Disposition: attachment;filename=$filename");

        $output = fopen("php://output", "w");
        fputcsv($output, ["DataEntryID", "Name", "ShiftDate", "ShiftNo", "Hours", "DutyType"]);

        while ($row = $exportResult->fetch_assoc()) {
            fputcsv($output, $row);
        }
        fclose($output);
        exit();
    } else {
        echo "<script>alert('No data to export');</script>";
    }
}

if (isset($_POST['clear_filter'])) {
    unset($_POST['name'], $_POST['shift_date'], $_POST['shift_no']);
    $show_all = true;
    // Force reload with no filters
  header("Location: " . $_SERVER['PHP_SELF']);
  exit;
}

// [ UPLOAD CSV FEATURE ]
if (isset($_POST['upload'])) {
    if (!empty($_FILES['file']['tmp_name'])) {
        $file = $_FILES['file']['tmp_name'];
        $handle = fopen($file, "r");
        fgetcsv($handle); // skip header row

        $success = 0;
        $fail = 0;
        $insertedIDs = []; // store generated DataEntryIDs for confirmation

        while (($row = fgetcsv($handle, 1000, ",")) !== FALSE) {

            // Match the columns in your CSV
            $shiftDates    = isset($row[0]) ? $conn->real_escape_string($row[0]) : '';
            $shiftNos      = isset($row[1]) ? $conn->real_escape_string($row[1]) : '';
            $businessUnits = isset($row[2]) ? $conn->real_escape_string($row[2]) : '';
            $names         = isset($row[3]) ? $conn->real_escape_string($row[3]) : '';
            $timeIns       = isset($row[4]) ? $conn->real_escape_string($row[4]) : '';
            $timeOuts      = isset($row[5]) ? $conn->real_escape_string($row[5]) : '';
            $hoursArr      = isset($row[6]) ? $conn->real_escape_string($row[6]) : '';
            $roles         = isset($row[7]) ? $conn->real_escape_string($row[7]) : '';
            $dutyTypes     = isset($row[8]) ? $conn->real_escape_string($row[8]) : '';
            $deductions    = isset($row[9]) && $row[9] !== '' ? $conn->real_escape_string($row[9]) : '0';
            $bonuses       = isset($row[10]) && $row[10] !== '' ? $conn->real_escape_string($row[10]) : '0';

            // Skip invalid rows
            if ($shiftDates === '' || $names === '') {
                continue;
            }

            // Convert date to Y-m-d if in m/d/Y format
            if (preg_match('/\d{1,2}\/\d{1,2}\/\d{4}/', $shiftDates)) {
                $shiftDates = date('Y-m-d', strtotime($shiftDates));
            }

            // ✅ Do NOT include DataEntryID — MySQL will auto-increment it
            $sql = "INSERT INTO timesheet 
                    (`ShiftDate`, `ShiftNo`, `Business_Unit`, `Name`, `TimeIN`, `TimeOUT`, `Hours`, `Role`, `DutyType`, `Deductions`, `Notes`)
                    VALUES 
                    ('$shiftDates','$shiftNos','$businessUnits','$names','$timeIns','$timeOuts','$hoursArr','$roles','$dutyTypes','$deductions','$bonuses')";

            if ($conn->query($sql)) {
                $success++;
                $insertedIDs[] = $conn->insert_id; // retrieve the new DataEntryID
            } else {
                $fail++;
            }
        }

        fclose($handle);

        // ✅ Display success message with generated IDs
        $idList = implode(", ", $insertedIDs);
        $_SESSION['toast'] = "✅ $success row(s) imported. ❌ $fail failed.<br>🆔 Inserted IDs: $idList";

        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
}


?>

<!DOCTYPE html>
<html>
<head>
  <link href="https://fonts.googleapis.com/css2?family=Google+Sans:wght@400;500;700&display=swap" rel="stylesheet">
    <title>Employee Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="style.css?v=<?php echo time(); ?>" rel="stylesheet">
    <style>
    /* ✅ FIXED Loading Overlay Styles */
    #loadingOverlay {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.7);
      z-index: 9999;
      justify-content: center;
      align-items: center;
      flex-direction: column;
    }
    
    #loadingOverlay.show {
      display: flex !important;
    }
    
    .spinner {
      border: 5px solid #f3f3f3;
      border-top: 5px solid #198754;
      border-radius: 50%;
      width: 60px;
      height: 60px;
      animation: spin 1s linear infinite;
    }
    
    @keyframes spin {
      0% { transform: rotate(0deg); }
      100% { transform: rotate(360deg); }
    }
    
    #loadingOverlay p {
      margin-top: 20px;
      color: #fff;
      font-size: 18px;
      font-weight: 500;
    }
    </style>
</head>

<body>
 <!-- ✅ FIXED Upload Loading Overlay -->
<div id="loadingOverlay">
  <div class="spinner"></div>
  <p>Uploading CSV... Please wait.</p>
</div>

  <div class="d-flex flex-column min-vh-100">
    <!-- Splash Screen -->
    <div id="splash-screen" class="d-flex justify-content-center align-items-center flex-column">
        <div class="spinner-border text-light mb-3" role="status" style="width: 3rem; height: 3rem;"></div>
        <img src="office-building.png" alt="Company Logo" style="height:56px;width:auto;margin-bottom:18px;filter: brightness(0) invert(1);">
        <h2 class="text-light fw-bold">Employee Management System</h2>
    </div>

      
<!-- Modern Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark modern-navbar">
    <div class="container-fluid">
        <!-- Brand -->
        <a class="navbar-brand navbar-brand-modern" href="index.php">
            <img src="office-building.png" alt="Logo">
            <span>EMS</span>
        </a>

        <!-- Mobile Toggle -->
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">
            <!-- Left Navigation -->
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link nav-link-modern active" href="index.php">
                        <span class="material-icons" style="font-size:20px;">dashboard</span>
                        Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link nav-link-modern" href="employees.php">
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

            <!-- Right Actions -->
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <!-- Actions Dropdown -->
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
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item dropdown-item-modern" href="#" data-bs-toggle="modal" data-bs-target="#searchModal">
                                <span class="material-icons">search</span>
                                Search Records
                            </a>
                        </li>
                    </ul>
                </div>

                <!-- Divider -->
                <div class="navbar-divider d-none d-lg-block"></div>

                <!-- Dark Mode Toggle -->
                <button id="toggleDarkMode" class="dark-mode-toggle" title="Toggle dark/light mode">
                    <span id="darkModeIconSwitch" class="material-icons">dark_mode</span>
                </button>
            </div>
        </div>
    </div>
</nav>
   <!-- DASHBOARD DATE FILTER & CARDS -->
<div class="dashboard-outer my-4">
  <form method="get" class="mb-3 d-flex flex-wrap align-items-center justify-content-center gap-2 dashboard-filter-form">
    <label for="dashboard_date" class="fw-bold me-2">Dashboard Date:</label>
    <input type="date" id="dashboard_date" name="dashboard_date" class="form-control" style="max-width:180px;" value="<?= isset($_GET['dashboard_date']) ? htmlspecialchars($_GET['dashboard_date']) : '' ?>">
    <button type="submit" class="btn btn-outline-primary">Apply</button>
    <?php if (isset($_GET['dashboard_date']) && $_GET['dashboard_date']): ?>
      <a href="index.php" class="btn btn-link">Clear</a>
    <?php endif; ?>
  </form>
  <div class="row g-3 justify-content-center dashboard-row">
    <?php
      // Build date filter
      $dateFilter = "";
      if (isset($_GET['dashboard_date']) && $_GET['dashboard_date']) {
        $dateFilter = " WHERE ShiftDate='" . $conn->real_escape_string($_GET['dashboard_date']) . "'";
      }
      
      // Count total entries (all records in timesheet)
      $totalEmployees = $conn->query("SELECT COUNT(*) AS cnt FROM timesheet" . $dateFilter)->fetch_assoc()['cnt'];
      
      // Count Late entries
      $lateDateFilter = $dateFilter ? $dateFilter . " AND DutyType='Late'" : " WHERE DutyType='Late'";
      $totalLate = $conn->query("SELECT COUNT(*) AS cnt FROM timesheet" . $lateDateFilter)->fetch_assoc()['cnt'];
      
      // Count Overtime entries
      $overtimeDateFilter = $dateFilter ? $dateFilter . " AND DutyType='Overtime'" : " WHERE DutyType='Overtime'";
      $totalOvertime = $conn->query("SELECT COUNT(*) AS cnt FROM timesheet" . $overtimeDateFilter)->fetch_assoc()['cnt'];
      
      // Count On Duty entries (use 'OnDuty' to match your database)
      $onDutyDateFilter = $dateFilter ? $dateFilter . " AND DutyType='OnDuty'" : " WHERE DutyType='OnDuty'";
      $totalOnDuty = $conn->query("SELECT COUNT(*) AS cnt FROM timesheet" . $onDutyDateFilter)->fetch_assoc()['cnt'];
    ?>
    <div class="col-6 col-md-3">
      <div class="card shadow-sm text-center py-3 dashboard-card">
        <span class="material-icons mb-1" style="font-size:2.2em;color:#018256;">group</span>
        <div class="fw-bold" style="font-size:1.3em;">Total Entries</div>
        <div class="fs-4"><?= $totalEmployees ?></div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card shadow-sm text-center py-3 dashboard-card">
        <span class="material-icons mb-1" style="font-size:2.2em;color:#d32f2f;">schedule</span>
        <div class="fw-bold" style="font-size:1.3em;">Total Late</div>
        <div class="fs-4"><?= $totalLate ?></div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card shadow-sm text-center py-3 dashboard-card">
        <span class="material-icons mb-1" style="font-size:2.2em;color:#1565c0;">alarm</span>
        <div class="fw-bold" style="font-size:1.3em;">Total Overtime</div>
        <div class="fs-4"><?= $totalOvertime ?></div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card shadow-sm text-center py-3 dashboard-card">
        <span class="material-icons mb-1" style="font-size:2.2em;color:#018256;">verified_user</span>
        <div class="fw-bold" style="font-size:1.3em;">On Duty</div>
        <div class="fs-4"><?= $totalOnDuty ?></div>
      </div>
    </div>
  </div>
</div>

    <div class="d-flex">
        <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">

        <!-- Main Content -->
        <div id="content" class="flex-grow-1 p-4">
            <div id="all">
                <div class="search-query-card mb-2" id="search-query-card" style="max-width:1100px;margin-left:auto;margin-right:auto;min-width:400px;width:90%;">
                    <?php if (!empty($_POST['last_name'])): ?>
                        <span class="search-query-chip">Name: <?= htmlspecialchars($_POST['last_name']) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($_POST['shift_date'])): ?>
                        <span class="search-query-chip">Date: <?= htmlspecialchars($_POST['shift_date']) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($_POST['shift_no'])): ?>
                        <span class="search-query-chip">Shift: <?= htmlspecialchars($_POST['shift_no']) ?></span>
                    <?php endif; ?>
                    <form method="post" class="d-inline">
                        <button type="submit" name="clear_filter" class="query-card-btn ms-2">Clear Filter</button>
                    </form>
                    <button type="button" class="query-card-btn ms-2" data-bs-toggle="modal" data-bs-target="#searchModal">+ Add Filter</button>
                    <form method="post" class="d-inline ms-2" id="exportFilteredForm">
                        <input type="hidden" name="last_name" value="<?= isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : '' ?>">
                        <input type="hidden" name="shift_date" value="<?= isset($_POST['shift_date']) ? htmlspecialchars($_POST['shift_date']) : '' ?>">
                        <input type="hidden" name="shift_no" value="<?= isset($_POST['shift_no']) ? htmlspecialchars($_POST['shift_no']) : '' ?>">
                        <button type="submit" name="export_filtered" class="query-card-btn">Export Filtered</button>
                    </form>
                </div>
                <div class="row mb-3 align-items-center" style="max-width:1100px;margin-left:auto;margin-right:auto;">
          <div class="col-md-6 d-flex flex-wrap gap-2 align-items-center">
            <h4 class="fw-bold mb-0" style="display:inline;font-family:'Segoe UI', 'Liberation Sans', 'DejaVu Sans', 'Arial', 'sans-serif';"> 
              <?= $show_all ? 'All Employees' : 'Filtered Employees' ?>
            </h4>
          </div>
          <div class="col-md-6 d-flex justify-content-md-end justify-content-start mt-2 mt-md-0 align-items-center gap-2">

          <!-- ✅ FIXED Upload CSV with proper form ID -->
          <?php if ($show_all) { ?>
              <form method="POST" enctype="multipart/form-data" class="d-inline ms-2" id="uploadForm">
                <input type="file" name="file" accept=".csv" required 
                  class="form-control form-control-sm d-inline-block" 
                  style="width: 200px; display: inline-block;">
                <button type="submit" name="upload" class="btn btn-success btn-sm">Upload CSV</button>
              </form>
            <?php } ?>

            <?php if ($show_all) { ?>
              <form method="post" class="d-inline ms-2" id="exportAllForm">
                <button type="submit" name="export_all" class="btn btn-dark">Export All</button>
              </form>
            <?php } ?>

            <?php if (!$show_all) { ?>
              <form method="post" class="d-inline">
                <button type="submit" name="view_all" class="btn btn-success">View All</button>
              </form>
            <?php } ?>
          </div>
                </div>
                <div class="table-responsive">
                <form id="multiDeleteForm" method="post" onsubmit="return confirm('Delete selected employees?');">
                  <table class="employee-table">
                    <thead>
                      <tr>
                        <th style="width: 40px;"><input type="checkbox" id="selectAll"></th>
                        <th style="width: 60px;">ID</th>
                        <th style="width: 120px;">Date</th>
                        <th style="width: 80px;">Shift No</th>
                        <th style="width: 150px;">Business Unit</th>
                        <th style="width: 200px;">Name</th>
                        <th style="width: 100px;">Time IN</th>
                        <th style="width: 100px;">Time OUT</th>
                        <th style="width: 80px;">Hours</th>
                        <th style="width: 150px;">Role</th>
                        <th style="width: 120px;">Duty Type</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php
                      $data = $show_all ? $result_all : $result_filtered;
                      if ($data && $data->num_rows > 0) {
                        while ($row = $data->fetch_assoc()) {
                          $dutyType = $row['DutyType'];
                          $dutyClass = '';
                          if (strcasecmp($dutyType, 'OnDuty') === 0) {
                            $dutyClass = 'dutytype-onduty';
                          } elseif (strcasecmp($dutyType, 'Late') === 0) {
                            $dutyClass = 'dutytype-late';
                          } elseif (strcasecmp($dutyType, 'Overtime') === 0) {
                            $dutyClass = 'dutytype-overtime';
                          }
                          echo "<tr>
                            <td><input type='checkbox' class='row-checkbox' name='selected_ids[]' value='{$row['DataEntryID']}'></td>
                            <td>{$row['DataEntryID']}</td>
                            <td>{$row['ShiftDate']}</td>
                            <td>{$row['ShiftNo']}</td>
                            <td>{$row['Business_Unit']}</td>
                            <td>{$row['Name']}</td>
                            <td>" . (!empty($row['TimeIN']) ? date('g:i A', strtotime($row['TimeIN'])) : '') . "</td>
                            <td>" . (!empty($row['TimeOUT']) ? date('g:i A', strtotime($row['TimeOUT'])) : '') . "</td>
                            <td>{$row['Hours']}</td>
                            <td>{$row['Role']}</td>
                            <td><span class='$dutyClass'>{$row['DutyType']}</span></td>
                          </tr>";
                        }
                      } else {
                        echo "<tr><td colspan='11' class='text-center text-muted'>No records found</td></tr>";
                      }
                      ?>
                    </tbody>
                  </table>
                  <div class="mt-2" style="max-width:1100px;margin-left:auto;margin-right:auto;">
                    <button type="submit" name="delete_selected" class="btn btn-danger">Delete Selected</button>
                  </div>
                </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modals (Add, Update, Delete, Search) remain the same -->
    <!-- ... (keeping all modal code unchanged for brevity) ... -->

    <!-- ✅ FIXED Add Modal - Aligned with Timesheet Columns -->
    <div class="modal fade custom-modal" id="addModal" tabindex="-1" aria-labelledby="addModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered modal-lg" style="max-width: 900px;">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="addModalLabel">Add Record</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <form method="post" id="addForm">
              <div id="addFields">
                <div class="add-row mb-3 border-bottom pb-3">
                  <div class="row g-2">
                    <div class="col-md-2">
                      <label class="form-label small fw-bold">Date</label>
                      <input type="date" name="shift_date[]" class="form-control form-control-sm" required>
                    </div>
                    <div class="col-md-1">
                      <label class="form-label small fw-bold">Shift No</label>
                      <input type="number" name="shift_no[]" class="form-control form-control-sm" placeholder="#" required>
                    </div>
                    <div class="col-md-2">
                      <label class="form-label small fw-bold">Business Unit</label>
                      <input type="text" name="business_unit[]" class="form-control form-control-sm" placeholder="Unit" required>
                    </div>
                    <div class="col-md-3">
                      <label class="form-label small fw-bold">Name</label>
                      <input type="text" name="name[]" class="form-control form-control-sm" placeholder="Full Name" required>
                    </div>
                    <div class="col-md-1">
                      <label class="form-label small fw-bold">Hours</label>
                      <input type="number" name="hours[]" class="form-control form-control-sm" placeholder="8" required>
                    </div>
                    <div class="col-md-2">
                      <label class="form-label small fw-bold">Role</label>
                      <input type="text" name="role[]" class="form-control form-control-sm" placeholder="Position" required>
                    </div>
                    <div class="col-md-1">
                      <label class="form-label small fw-bold">Duty Type</label>
                      <select name="duty_type[]" class="form-select form-select-sm" required>
                        <option value="On Duty">On Duty</option>
                        <option value="Late">Late</option>
                        <option value="Overtime">Overtime</option>
                      </select>
                    </div>
                  </div>
                </div>
              </div>
              <button type="button" class="btn btn-outline-success btn-sm mb-2" id="addMoreBtn">+ Add Another Record</button>
              <div class="d-grid gap-2 mt-2">
                <button type="submit" name="add" class="btn btn-primary">Add Record(s)</button>
              </div>
            </form>
            <div class="text-center mt-3">
              <a href="#" id="updateInsteadLink" class="forgot-link">Update instead?</a>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ✅ FIXED Update Modal - Aligned with Timesheet Columns -->
    <div class="modal fade custom-modal" id="updateModal" tabindex="-1" aria-labelledby="updateModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered modal-lg" style="max-width: 950px;">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="updateModalLabel">Update Record</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <form method="post" id="updateForm">
              <div id="updateFields">
                <div class="update-row mb-3 border-bottom pb-3">
                  <div class="row g-2">
                    <div class="col-md-1">
                      <label class="form-label small fw-bold">ID</label>
                      <input type="number" name="id[]" class="form-control form-control-sm" placeholder="ID" required>
                    </div>
                    <div class="col-md-2">
                      <label class="form-label small fw-bold">Date</label>
                      <input type="date" name="shift_date[]" class="form-control form-control-sm" required>
                    </div>
                    <div class="col-md-1">
                      <label class="form-label small fw-bold">Shift No</label>
                      <input type="number" name="shift_no[]" class="form-control form-control-sm" placeholder="#" required>
                    </div>
                    <div class="col-md-2">
                      <label class="form-label small fw-bold">Business Unit</label>
                      <input type="text" name="business_unit[]" class="form-control form-control-sm" placeholder="Unit" required>
                    </div>
                    <div class="col-md-2">
                      <label class="form-label small fw-bold">Name</label>
                      <input type="text" name="name[]" class="form-control form-control-sm" placeholder="Full Name" required>
                    </div>
                    <div class="col-md-1">
                      <label class="form-label small fw-bold">Hours</label>
                      <input type="number" name="hours[]" class="form-control form-control-sm" placeholder="8" required>
                    </div>
                    <div class="col-md-2">
                      <label class="form-label small fw-bold">Role</label>
                      <input type="text" name="role[]" class="form-control form-control-sm" placeholder="Position" required>
                    </div>
                    <div class="col-md-1">
                      <label class="form-label small fw-bold">Duty Type</label>
                      <select name="duty_type[]" class="form-select form-select-sm" required>
                        <option value="OnDuty">On Duty</option>
                        <option value="Late">Late</option>
                        <option value="Overtime">Overtime</option>
                      </select>
                    </div>
                  </div>
                </div>
              </div>
              <button type="button" class="btn btn-outline-warning btn-sm mb-2" id="updateMoreBtn">+ Update Another Record</button>
              <div class="d-grid gap-2 mt-2">
                <button type="submit" name="update" class="btn btn-warning">Update Record(s)</button>
              </div>
            </form>
            <div class="text-center mt-3">
              <a href="#" id="addInsteadLink" class="forgot-link">Add instead?</a>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ✅ FIXED Delete Modal - Aligned with Timesheet Columns -->
    <div class="modal fade custom-modal" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered" style="max-width: 500px;">
        <div class="modal-content">
          <div class="modal-header bg-danger text-white">
            <h5 class="modal-title" id="deleteModalLabel">Delete Record</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div class="alert alert-warning d-flex align-items-center" role="alert">
              <span class="material-icons me-2">warning</span>
              <div>Enter the Data Entry ID(s) you want to delete. This action cannot be undone!</div>
            </div>
            <form method="post" id="deleteForm">
              <div id="deleteFields">
                <div class="delete-row mb-3 border-bottom pb-3">
                  <div class="row g-2">
                    <div class="col-12">
                      <label class="form-label fw-bold">Data Entry ID</label>
                      <input type="number" name="id[]" class="form-control" placeholder="Enter Employee ID to delete" required>
                      <small class="form-text text-muted">You can find the ID in the first column of the table</small>
                    </div>
                  </div>
                </div>
              </div>
              <button type="button" class="btn btn-outline-danger btn-sm mb-3" id="deleteMoreBtn">+ Delete Another Record</button>
              <div class="d-grid gap-2">
                <button type="submit" name="delete" class="btn btn-danger">
                  <span class="material-icons align-middle" style="font-size: 18px;">delete_forever</span>
                  Delete Record(s)
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>

    <!-- Search Modal -->
    <div class="modal fade custom-modal" id="searchModal" tabindex="-1" aria-labelledby="searchModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered" style="max-width: 370px;">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="searchModalLabel">Search Employees</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <form method="post" class="row g-3" id="employee-search-form">
              <div class="col-md-12 mb-2">
                <input type="text" name="name" class="form-control" placeholder="Name" value="<?= isset($_POST['name']) ? htmlspecialchars($_POST['name']) : '' ?>">
              </div>
              <div class="col-md-12 mb-2">
                <input type="date" name="shift_date" class="form-control" value="<?= isset($_POST['shift_date']) ? htmlspecialchars($_POST['shift_date']) : '' ?>">
              </div>
              <div class="col-md-12 mb-2">
                <input type="number" name="shift_no" class="form-control" placeholder="Shift No" value="<?= isset($_POST['shift_no']) ? htmlspecialchars($_POST['shift_no']) : '' ?>">
              </div>
              <div class="d-flex justify-content-center gap-2 mt-3">
                <button type="submit" class="btn btn-primary" id="search-btn">Search</button>
                <button type="submit" name="export_filtered" class="btn btn-secondary">Export Filtered</button>
                <button type="submit" name="export_all" class="btn btn-dark">Export All</button>
                <button type="submit" name="view_all" class="btn btn-success">View All</button>
              </div>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="script.js"></script>
    
    <!-- ✅ FIXED: Upload CSV Loading Script -->
    <script>
    document.addEventListener("DOMContentLoaded", function() {
      const uploadForm = document.getElementById("uploadForm");
      const loadingOverlay = document.getElementById("loadingOverlay");
      
      if (uploadForm) {
        uploadForm.addEventListener("submit", function(e) {
          const fileInput = uploadForm.querySelector('input[type="file"]');
          
          // Check if a file is selected
          if (fileInput && fileInput.files.length > 0) {
            // Show loading overlay
            loadingOverlay.classList.add("show");
          }
        });
      }
      
      // Select All checkbox functionality
      const selectAll = document.getElementById("selectAll");
      const rowCheckboxes = document.querySelectorAll(".row-checkbox");
      
      if (selectAll) {
        selectAll.addEventListener("change", function() {
          rowCheckboxes.forEach(checkbox => {
            checkbox.checked = selectAll.checked;
          });
        });
      }
    });
    </script>
 
    <!-- ✅ FIXED: Upload CSV Loading Script -->
    <script>
    document.addEventListener("DOMContentLoaded", function() {
      const uploadForm = document.getElementById("uploadForm");
      const loadingOverlay = document.getElementById("loadingOverlay");
      
      if (uploadForm) {
        uploadForm.addEventListener("submit", function(e) {
          const fileInput = uploadForm.querySelector('input[type="file"]');
          
          // Check if a file is selected
          if (fileInput && fileInput.files.length > 0) {
            // Show loading overlay
            loadingOverlay.classList.add("show");
          }
        });
      }
      
      // Select All checkbox functionality
      const selectAll = document.getElementById("selectAll");
      const rowCheckboxes = document.querySelectorAll(".row-checkbox");
      
      if (selectAll) {
        selectAll.addEventListener("change", function() {
          rowCheckboxes.forEach(checkbox => {
            checkbox.checked = selectAll.checked;
          });
        });
      }
    });
    </script>
    <footer class="text-center py-3 mt-auto sticky-footer" style="background: var(--surface); color: var(--primary-dark); font-size: 1.05rem; border-top: 1px solid var(--primary-light);">
      Powered by <strong>Angelica Gregorio</strong> and <strong>Ysabella Santos</strong>
    </footer>
    </div>
<script src="script.js?v=<?php echo time(); ?>"></script>
</body>
</html>
