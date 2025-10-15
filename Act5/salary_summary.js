// ================================
// script.js - Toggle Details Logic
// ================================

function toggleDetails(id, btn) {
  const row = document.getElementById(id);
  if (row.style.display === 'none' || row.style.display === '') {
    row.style.display = 'table-row';
    btn.textContent = 'Hide Details';
  } else {
    row.style.display = 'none';
    btn.textContent = 'Show Details';
  }
}
