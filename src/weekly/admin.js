let weeks = [];
let editingId = null;

const weekForm = document.querySelector('#week-form');
const weeksTableBody = document.querySelector('#weeks-tbody');

function createWeekRow(week) {
  const tr = document.createElement('tr');

  const tdTitle = document.createElement('td');
  tdTitle.textContent = week.title;

  const tdDate = document.createElement('td');
  tdDate.textContent = week.start_date;

  const tdDesc = document.createElement('td');
  tdDesc.textContent = week.description;

  const tdActions = document.createElement('td');
  
  const editBtn = document.createElement('button');
  editBtn.textContent = "Edit";
  editBtn.className = "edit-btn";
  editBtn.dataset.id = week.id;

  const deleteBtn = document.createElement('button');
  deleteBtn.textContent = "Delete";
  deleteBtn.className = "delete-btn";
  deleteBtn.dataset.id = week.id;

  tdActions.appendChild(editBtn);
  tdActions.appendChild(deleteBtn);

  tr.appendChild(tdTitle);
  tr.appendChild(tdDate);
  tr.appendChild(tdDesc);
  tr.appendChild(tdActions);

  return tr;
}

function renderTable() {
  weeksTableBody.innerHTML = "";
  weeks.forEach(week => {
    weeksTableBody.appendChild(createWeekRow(week));
  });
}

async function handleAddWeek(event) {
  event.preventDefault();

  const title = document.querySelector('#week-title').value.trim();
  const start_date = document.querySelector('#week-start-date').value;
  const description = document.querySelector('#week-description').value.trim();
  const linksText = document.querySelector('#week-links').value.trim();

  const links = linksText ? linksText.split("\n").map(l => l.trim()) : [];

  const weekData = { title, start_date, description, links };

  try {
    const res = await fetch('./api/index.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(weekData)
    });
    const result = await res.json();
    if (result.success) {
      await loadAndInitialize();
      weekForm.reset();
    }
  } catch (err) {
    console.error("Error saving week:", err);
  }
}

async function handleTableClick(event) {
  const id = event.target.dataset.id;
  if (!id) return;

  if (event.target.classList.contains("delete-btn")) {
    if (confirm("Delete this week?")) {
      await fetch(`./api/index.php?id=${id}`, { method: 'DELETE' });
      await loadAndInitialize();
    }
  } else if (event.target.classList.contains("edit-btn")) {
    const week = weeks.find(w => w.id == id);
    if (week) {
      document.getElementById("week-title").value = week.title;
      document.getElementById("week-start-date").value = week.start_date;
      document.getElementById("week-description").value = week.description;
      document.getElementById("week-links").value = Array.isArray(week.links) ? week.links.join("\n") : "";
      editingId = id;
    }
  }
}

async function loadAndInitialize() {
  try {
    const res = await fetch('./api/index.php');
    const result = await res.json();
    if (result.success) {
      weeks = result.data;
      renderTable();
    }
  } catch (err) {
    console.error("Load error:", err);
  }
}

weekForm.addEventListener('submit', handleAddWeek);
weeksTableBody.addEventListener('click', handleTableClick);
loadAndInitialize();
