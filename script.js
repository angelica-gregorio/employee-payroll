/**
 * Employee Management System - script.js
 * --------------------------------------
 * Handles all client-side interactivity:
 * - Splash screen logic
 * - Dark mode toggle
 * - Modal switching
 * - Toast auto-hide
 * - Multi-delete selection
 * - Dynamic modal field addition
 *
 * Author: Angelica Gregorio & Ysabella Santos
 * Last updated: 2025-11-06
 */

document.addEventListener("DOMContentLoaded", function () {
  // =============================
  // Multi-Delete Checkbox Logic
  // =============================
  // Handles 'Select All' and row checkbox sync for multi-delete
  const selectAll = document.getElementById('selectAll');
  if (selectAll) {
    selectAll.addEventListener('change', function () {
      document.querySelectorAll('.row-checkbox').forEach(cb => {
        cb.checked = selectAll.checked;
      });
    });
  }
  // Uncheck selectAll if any row is unchecked, check if all are checked
  document.addEventListener('change', function (e) {
    if (e.target.classList.contains('row-checkbox')) {
      if (!e.target.checked && selectAll) selectAll.checked = false;
      if (selectAll && document.querySelectorAll('.row-checkbox:checked').length === document.querySelectorAll('.row-checkbox').length) {
        selectAll.checked = true;
      }
    }
  });

  // =============================
  // Splash Screen Logic
  // =============================
  // Shows splash screen for 1s on first visit, always hides after 2s (failsafe)
  const splash = document.getElementById("splash-screen");
  if (splash) {
    if (!localStorage.getItem("splashShown")) {
      setTimeout(() => {
        splash.classList.add("hidden");
        localStorage.setItem("splashShown", "true");
      }, 1000); // Show splash for 1 second on first visit
    } else {
      splash.classList.add("hidden");
    }
    // Failsafe: always hide splash after 2 seconds
    setTimeout(() => {
      splash.classList.add("hidden");
    }, 2000);
  }

  // =============================
  // Dark Mode Toggle
  // =============================
  // Handles dark/light mode switching and icon animation
  const darkModeBtn = document.getElementById("toggleDarkMode");
  const darkModeIconSwitch = document.getElementById("darkModeIconSwitch");
  const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
  const savedMode = localStorage.getItem('darkMode');
  // Updates the icon and animation for dark mode toggle
  function setSwitchState(isDark, animate = false) {
    if (!darkModeIconSwitch) return;
    if (isDark) {
      darkModeIconSwitch.textContent = 'dark_mode';
      if (animate) {
        darkModeIconSwitch.classList.remove('sunrise', 'sundown');
        void darkModeIconSwitch.offsetWidth;
        darkModeIconSwitch.classList.add('sundown');
      } else {
        darkModeIconSwitch.classList.remove('sunrise', 'sundown');
      }
    } else {
      darkModeIconSwitch.textContent = 'light_mode';
      if (animate) {
        darkModeIconSwitch.classList.remove('sunrise', 'sundown');
        void darkModeIconSwitch.offsetWidth;
        darkModeIconSwitch.classList.add('sunrise');
      } else {
        darkModeIconSwitch.classList.remove('sunrise', 'sundown');
      }
    }
  }
  // Set initial mode based on saved preference or system
  if (savedMode === 'dark' || (!savedMode && prefersDark)) {
    document.body.classList.add('dark-mode');
    setSwitchState(true);
  } else {
    document.body.classList.remove('dark-mode');
    setSwitchState(false);
  }
  // Toggle dark mode on button click
  if (darkModeBtn) {
    darkModeBtn.addEventListener('click', function () {
      const goingDark = !document.body.classList.contains('dark-mode');
      document.body.classList.toggle('dark-mode');
      localStorage.setItem('darkMode', document.body.classList.contains('dark-mode') ? 'dark' : 'light');
      setSwitchState(document.body.classList.contains('dark-mode'), goingDark);
    });
  }

  // =============================
  // Modal Switchers
  // =============================
  // Allows switching between Add and Update modals via links
  const updateInsteadLink = document.getElementById('updateInsteadLink');
  if (updateInsteadLink) {
    updateInsteadLink.addEventListener('click', function (e) {
      e.preventDefault();
      const addModal = bootstrap.Modal.getInstance(document.getElementById('addModal'));
      if (addModal) addModal.hide();
      const updateModal = new bootstrap.Modal(document.getElementById('updateModal'));
      updateModal.show();
    });
  }
  const addInsteadLink = document.getElementById('addInsteadLink');
  if (addInsteadLink) {
    addInsteadLink.addEventListener('click', function (e) {
      e.preventDefault();
      const updateModal = bootstrap.Modal.getInstance(document.getElementById('updateModal'));
      if (updateModal) updateModal.hide();
      const addModal = new bootstrap.Modal(document.getElementById('addModal'));
      addModal.show();
    });
  }

  // =============================
  // Toast Auto-hide
  // =============================
  // Automatically hides toast notifications after 2.5 seconds
  const toastEl = document.querySelector('.toast');
  if (toastEl) {
    setTimeout(() => {
      const toast = bootstrap.Toast.getOrCreateInstance(toastEl);
      toast.hide();
    }, 2500);
  }

  // =============================
  // Dynamic Modal Field Addition
  // =============================
  // Handles 'Add More', 'Update More', and 'Delete More' buttons in modals

// Add More for Add Modal - Single row layout matching index.php
const addMoreBtn = document.getElementById('addMoreBtn');
if (addMoreBtn) {
  addMoreBtn.addEventListener('click', function() {
    const addFields = document.getElementById('addFields');
    const row = document.createElement('div');
    row.className = 'add-row mb-3 border-bottom pb-3';
    row.innerHTML = `
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
        <div class="col-md-2">
          <label class="form-label small fw-bold">Name</label>
          <input type="text" name="name[]" class="form-control form-control-sm" placeholder="Full Name" required>
        </div>
        <div class="col-md-2">
          <label class="form-label small fw-bold">Time IN</label>
          <input type="time" name="time_in[]" class="form-control form-control-sm">
        </div>
        <div class="col-md-2">
          <label class="form-label small fw-bold">Time OUT</label>
          <input type="time" name="time_out[]" class="form-control form-control-sm">
        </div>
        <div class="col-md-1">
          <label class="form-label small fw-bold">Hours</label>
          <input type="number" name="hours[]" class="form-control form-control-sm" placeholder="8" required>
        </div>
        <div class="col-md-2">
          <label class="form-label small fw-bold">Role</label>
          <input type="text" name="role[]" class="form-control form-control-sm" placeholder="Position" required>
        </div>
        <div class="col-md-2">
          <label class="form-label small fw-bold">Duty Type</label>
          <select name="duty_type[]" class="form-select form-select-sm" required>
            <option value="On Duty">On Duty</option>
            <option value="Late">Late</option>
            <option value="Overtime">Overtime</option>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label small fw-bold">Deductions</label>
          <input type="number" step="0.01" name="deductions[]" class="form-control form-control-sm" placeholder="0.00">
        </div>
        <div class="col-md-3">
          <label class="form-label small fw-bold">Notes</label>
          <input type="text" name="notes[]" class="form-control form-control-sm" placeholder="Optional notes">
        </div>
      </div>
    `;
    addFields.appendChild(row);
  });
}

// Update More for Update Modal - Single row layout matching index.php
const updateMoreBtn = document.getElementById('updateMoreBtn');
if (updateMoreBtn) {
  updateMoreBtn.addEventListener('click', function() {
    const updateFields = document.getElementById('updateFields');
    const row = document.createElement('div');
    row.className = 'update-row mb-3 border-bottom pb-3';
    row.innerHTML = `
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
        <div class="col-md-2">
          <label class="form-label small fw-bold">Time IN</label>
          <input type="time" name="time_in[]" class="form-control form-control-sm">
        </div>
        <div class="col-md-2">
          <label class="form-label small fw-bold">Time OUT</label>
          <input type="time" name="time_out[]" class="form-control form-control-sm">
        </div>
        <div class="col-md-1">
          <label class="form-label small fw-bold">Hours</label>
          <input type="number" name="hours[]" class="form-control form-control-sm" placeholder="8" required>
        </div>
        <div class="col-md-2">
          <label class="form-label small fw-bold">Role</label>
          <input type="text" name="role[]" class="form-control form-control-sm" placeholder="Position" required>
        </div>
        <div class="col-md-2">
          <label class="form-label small fw-bold">Duty Type</label>
          <select name="duty_type[]" class="form-select form-select-sm" required>
            <option value="On Duty">On Duty</option>
            <option value="Late">Late</option>
            <option value="Overtime">Overtime</option>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label small fw-bold">Deductions</label>
          <input type="number" step="0.01" name="deductions[]" class="form-control form-control-sm" placeholder="0.00">
        </div>
        <div class="col-md-3">
          <label class="form-label small fw-bold">Notes</label>
          <input type="text" name="notes[]" class="form-control form-control-sm" placeholder="Optional notes">
        </div>
      </div>
    `;
    updateFields.appendChild(row);
  });
}

  // Delete More for Delete Modal
  const deleteMoreBtn = document.getElementById('deleteMoreBtn');
  if (deleteMoreBtn) {
    deleteMoreBtn.addEventListener('click', function() {
      const deleteFields = document.getElementById('deleteFields');
      const row = document.createElement('div');
      row.className = 'delete-row mb-3 border-bottom pb-3';
      row.innerHTML = `
        <div class="row g-2">
          <div class="col-12">
            <label class="form-label fw-bold">Data Entry ID</label>
            <input type="number" name="id[]" class="form-control" placeholder="Enter Employee ID to delete" required>
            <small class="form-text text-muted">You can find the ID in the first column of the table</small>
          </div>
        </div>
      `;
      deleteFields.appendChild(row);
    });
  }
});