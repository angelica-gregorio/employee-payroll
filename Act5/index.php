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
  $names = $_POST["name"];
  $shiftDates = $_POST["shift_date"];
  $shiftNos = $_POST['shift_no'];
  $hoursArr = $_POST['hours'];
  $dutyTypes = $_POST['duty_type'];

  $success = 0;
  $fail = 0;
  for ($i = 0; $i < count($firstNames); $i++) {
    $name = $conn->real_escape_string($names[$i]);
    $shiftDate = $conn->real_escape_string($shiftDates[$i]);
    $shiftNo = (int)$shiftNos[$i];
    $hours = (int)$hoursArr[$i];
    // Always use 'On Duty' (with space, title case)
    $dutyType = $conn->real_escape_string(
      preg_match('/^on\s*duty$/i', trim($dutyTypes[$i])) ? 'On Duty' : $dutyTypes[$i]
    );
    // Skip empty or invalid data
    if ($name === '' || $hours < 0 || $shiftNo < 0) {
      $fail++;
      continue;
    }

    $sql = "INSERT INTO timesheet (Name, ShiftDate, ShiftNo, Hours, DutyType)
        VALUES ('$name', '$shiftDate', '$shiftNo', '$hours', '$dutyType')";
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
    $sql = "UPDATE timesheet
        SET Name='$name', ShiftDate='$shiftDate',
          ShiftNo='$shiftNo', Hours='$hours', DutyType='$dutyType'
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
  $where[] = "(Name LIKE '%$search_name%')";
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
?>
<!DOCTYPE html>
<html>

<head>
  <!-- Google Fonts: Google Sans (Product Sans) -->
  <link href="https://fonts.googleapis.com/css2?family=Google+Sans:wght@400;500;700&display=swap" rel="stylesheet">
    <title>Employee Management</title>
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="style.css?v=<?php echo time(); ?>" rel="stylesheet">
  <!-- Custom styles moved to style.css -->
</head>

<body>
  <div class="d-flex flex-column min-vh-100">
  <!-- SPLASH SCREEN -->
    <!--
      Splash screen shown on page load. Includes:
      - Company logo (office-building.png) for branding
      - Bootstrap spinner for loading animation
      - System title for context
      The splash screen is hidden via JavaScript after the page loads.
    -->
    <div id="splash-screen" class="d-flex justify-content-center align-items-center flex-column">
          <!-- Bootstrap spinner for loading effect -->
        <div class="spinner-border text-light mb-3" role="status" style="width: 3rem; height: 3rem;"></div>
        <!-- Company logo for branding -->
        <img src="office-building.png" alt="Company Logo" style="height:56px;width:auto;margin-bottom:18px;filter: brightness(0) invert(1);">
        <!-- System title -->
        <h2 class="text-light fw-bold">Employee Management System</h2>
    </div>

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
                    <button type="button" class="btn btn-outline-success d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#addModal">
                        <span class="material-icons align-middle me-1">person_add</span> Add
                    </button>
                    <button type="button" class="btn btn-outline-warning d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#updateModal">
                        <span class="material-icons align-middle me-1">edit</span> Update
                    </button>
                    <button type="button" class="btn btn-outline-danger d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#deleteModal">
                        <span class="material-icons align-middle me-1">delete</span> Delete
                    </button>
                    <button type="button" class="btn btn-outline-primary d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#searchModal">
                        <span class="material-icons align-middle me-1">search</span> Search
                    </button>
                    <button id="toggleDarkMode" class="icon-darkmode-btn ms-2" title="Toggle dark/light mode" style="background:none;border:none;outline:none;padding:0;display:flex;align-items:center;">
                      <span id="darkModeIconSwitch" class="material-icons" aria-hidden="true">dark_mode</span>
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
          $dateFilter = isset($_GET['dashboard_date']) && $_GET['dashboard_date'] ?
            (" AND ShiftDate='" . $conn->real_escape_string($_GET['dashboard_date']) . "'") : '';
          $totalEmployees = $conn->query("SELECT COUNT(*) AS cnt FROM timesheet 
          WHERE TRIM(Name) <> '' 
          AND ShiftDate IS NOT NULL 
          AND ShiftNo IS NOT NULL 
          AND Hours IS NOT NULL 
          AND TRIM(DutyType) <> '' 
          $dateFilter")->fetch_assoc()['cnt'];
          $totalLate = $conn->query("SELECT COUNT(*) as cnt FROM timesheet WHERE DutyType='Late' $dateFilter")->fetch_assoc()['cnt'];
          $totalOvertime = $conn->query("SELECT COUNT(*) as cnt FROM timesheet WHERE DutyType='Overtime' $dateFilter")->fetch_assoc()['cnt'];
          $totalOnDuty = $conn->query("SELECT COUNT(*) as cnt FROM timesheet WHERE DutyType='OnDuty' $dateFilter")->fetch_assoc()['cnt'];
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
        <!-- Google Material Icons CDN -->
        <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">

        <!-- Main Content: Only Employees Table -->
        <div id="content" class="flex-grow-1 p-4">
            <div id="all">
                <?php if (!empty($_POST['last_name']) || !empty($_POST['shift_date']) || !empty($_POST['shift_no'])): ?>
                <div class="search-query-card mb-2" id="search-query-card" style="max-width:1100px;margin-left:auto;margin-right:auto;min-width:400px;width:90%;">
                    <?php if (!empty($_POST['last_name'])): ?>
                        <span class="search-query-chip">Name: <?= htmlspecialchars($_POST['name']) ?></span>
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
                <?php endif; ?>
                <div class="row mb-3 align-items-center" style="max-width:1100px;margin-left:auto;margin-right:auto;">
          <div class="col-md-6 d-flex flex-wrap gap-2 align-items-center">
            <h4 class="fw-bold mb-0" style="display:inline;font-family:'Segoe UI', 'Liberation Sans', 'DejaVu Sans', 'Arial', 'sans-serif';"> 
              <?= $show_all ? 'All Employees' : 'Filtered Employees' ?>
            </h4>
          </div>
          <div class="col-md-6 d-flex justify-content-md-end justify-content-start mt-2 mt-md-0 align-items-center gap-2">
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
                        <th><input type="checkbox" id="selectAll"></th>
                        <th>ID</th>
                        <th>Date</th>
                        <th>Shift No</th>
                        <th>Business Unit</th>
                        <th>Name</th>
                        <th>Time IN</th>
                        <th>Time OUT</th>
                        <th>Hours</th>
                        <th>Role</th>
                        <th>Duty Type</th>
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
                        echo "<tr><td colspan='8' class='text-center text-muted'>No records found</td></tr>";
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

    <!-- Add Modal -->
    <div class="modal fade custom-modal" id="addModal" tabindex="-1" aria-labelledby="addModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered" style="max-width: 420px;">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="addModalLabel">Add Employee</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <form method="post" id="addForm">
              <div id="addFields">
                <div class="add-row mb-3 border-bottom pb-2">
                  <input type="text" name="name[]" class="form-control mb-2" placeholder="Full Name" required>
                  <input type="date" name="shift_date[]" class="form-control mb-2" required>
                  <input type="number" name="shift_no[]" class="form-control mb-2" placeholder="Shift No" required>
                  <input type="number" name="hours[]" class="form-control mb-2" placeholder="Hours" required>
                  <select name="duty_type[]" class="form-select mb-2" required>
                    <option value="On Duty">On Duty</option>
                    <option value="Late">Late</option>
                    <option value="Overtime">Overtime</option>
                  </select>
                </div>
              </div>
              <button type="button" class="btn btn-outline-success w-100 mb-2" id="addMoreBtn">Add More</button>
              <div class="d-grid gap-2 mt-2">
                <button type="submit" name="add" class="btn btn-primary">Add</button>
              </div>
            </form>
            <div class="text-center mt-3">
              <a href="#" id="updateInsteadLink" class="forgot-link">Update instead?</a>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Update Modal -->
    <div class="modal fade custom-modal" id="updateModal" tabindex="-1" aria-labelledby="updateModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered" style="max-width: 420px;">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="updateModalLabel">Update Employee</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <form method="post" id="updateForm">
              <div id="updateFields">
                <div class="update-row mb-3 border-bottom pb-2">
                  <input type="number" name="id[]" class="form-control mb-2" placeholder="Data Entry ID" required>
                  <input type="text" name="name[]" class="form-control mb-2" placeholder="Full Name" required>
                  <input type="date" name="shift_date[]" class="form-control mb-2" required>
                  <input type="number" name="shift_no[]" class="form-control mb-2" placeholder="Shift No" required>
                  <input type="number" name="hours[]" class="form-control mb-2" placeholder="Hours" required>
                  <select name="duty_type[]" class="form-select mb-2" required>
                    <option value="OnDuty">On Duty</option>
                    <option value="Late">Late</option>
                    <option value="Overtime">Overtime</option>
                  </select>
                </div>
              </div>
              <button type="button" class="btn btn-outline-warning w-100 mb-2" id="updateMoreBtn">Update More</button>
              <div class="d-grid gap-2 mt-2">
                <button type="submit" name="update" class="btn btn-warning">Update</button>
              </div>
            </form>
            <div class="text-center mt-3">
              <a href="#" id="addInsteadLink" class="forgot-link">Add instead?</a>
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
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <form method="post" id="deleteForm">
              <div id="deleteFields">
                <div class="delete-row mb-3 border-bottom pb-2">
                  <input type="number" name="id[]" class="form-control mb-2" placeholder="Data Entry ID" required>
                </div>
              </div>
              <button type="button" class="btn btn-outline-danger w-100 mb-2" id="deleteMoreBtn">Delete More</button>
              <button type="submit" name="delete" class="btn btn-danger w-100">Delete</button>
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


    <footer class="text-center py-3 mt-auto sticky-footer" style="background: var(--surface); color: var(--primary-dark); font-size: 1.05rem; border-top: 1px solid var(--primary-light);">
      Powered by <strong>Angelica Gregorio</strong> and <strong>Ysabella Santos</strong>
    </footer>
    </div>
</body>

</html>
