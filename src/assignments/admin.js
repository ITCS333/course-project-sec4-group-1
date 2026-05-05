let assignments = [];

const assignmentForm = document.querySelector('#assignment-form');
const assignmentsTableBody = document.querySelector('#assignments-tbody');

function createAssignmentRow(assignment) {
  const tr = document.createElement('tr');
  tr.innerHTML = `
    <td>${assignment.title}</td>
    <td>${assignment.due_date}</td>
    <td>${assignment.description}</td>
    <td>
      <button class="edit-btn" data-id="${assignment.id}">Edit</button>
      <button class="delete-btn" data-id="${assignment.id}">Delete</button>
    </td>
  `;
  return tr;
}

function renderTable() {
  assignmentsTableBody.innerHTML = '';
  assignments.forEach(asg => {
    assignmentsTableBody.appendChild(createAssignmentRow(asg));
  });
}

async function handleAddAssignment(event) {
  event.preventDefault();

  const title = document.querySelector('#assignment-title').value;
  const description = document.querySelector('#assignment-description').value;
  const dueDate = document.querySelector('#assignment-due-date').value;
  const filesRaw = document.querySelector('#assignment-files').value;

  const filesArray = filesRaw.split('\n').filter(link => link.trim() !== "");

  const payload = {
    title,
    description,
    due_date: dueDate,
    files: filesArray
  };

  try {
    const response = await fetch('./api/index.php?resource=assignments', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });

    const result = await response.json();
    if (result.success) {
      await loadAndInitialize();
      assignmentForm.reset();
    }
  } catch (err) {
    console.error(err);
  }
}

async function handleTableClick(event) {
  const target = event.target;
  const id = target.dataset.id;

  if (target.classList.contains('delete-btn')) {
    if (!confirm("Are you sure?")) return;
    await fetch(`./api/index.php?resource=assignments&id=${id}`, { method: 'DELETE' });
    await loadAndInitialize();
  }

  if (target.classList.contains('edit-btn')) {
    const asg = assignments.find(a => a.id == id);
    if (asg) {
      document.getElementById("assignment-title").value = asg.title;
      document.getElementById("assignment-due-date").value = asg.due_date;
      document.getElementById("assignment-description").value = asg.description;
      document.getElementById("assignment-files").value = Array.isArray(asg.files) ? asg.files.join('\n') : "";
    }
  }
}

async function loadAndInitialize() {
  try {
    const res = await fetch('./api/index.php?resource=assignments');
    const result = await res.json();
    assignments = result.data || [];
    renderTable();

    assignmentForm.addEventListener('submit', handleAddAssignment);
    assignmentsTableBody.addEventListener('click', handleTableClick);
  } catch (err) {
    console.error(err);
  }
}

loadAndInitialize();
