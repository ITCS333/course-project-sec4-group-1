let students = [];

const studentTableBody = document.querySelector("#student-table tbody");
const addStudentForm = document.querySelector("#add-student-form");
const changePasswordForm = document.querySelector("#password-form");
const searchInput = document.querySelector("#search-input");
const tableHeaders = document.querySelectorAll("#student-table thead th");

function createStudentRow(student) {
    const tr = document.createElement("tr");

    const nameTd = document.createElement("td");
    nameTd.textContent = student.name;
    tr.appendChild(nameTd);

    const idTd = document.createElement("td");
    idTd.textContent = student.id;
    tr.appendChild(idTd);

    const emailTd = document.createElement("td");
    emailTd.textContent = student.email;
    tr.appendChild(emailTd);

    const actionsTd = document.createElement("td");
    const editBtn = document.createElement("button");
    editBtn.textContent = "Edit";
    editBtn.classList.add("edit-btn");
    editBtn.dataset.id = student.id;

    const deleteBtn = document.createElement("button");
    deleteBtn.textContent = "Delete";
    deleteBtn.classList.add("delete-btn");
    deleteBtn.dataset.id = student.id;

    actionsTd.appendChild(editBtn);
    actionsTd.appendChild(deleteBtn);
    tr.appendChild(actionsTd);

    return tr;
}

function renderTable(studentArray) {
    studentTableBody.innerHTML = "";
    studentArray.forEach(student => {
        studentTableBody.appendChild(createStudentRow(student));
    });
}

function handleChangePassword(event) {
    event.preventDefault();
    const messageBox = document.querySelector("#message-container");
    const currentPassword = document.querySelector("#current-password").value;
    const newPassword = document.querySelector("#new-password").value;
    const confirmPassword = document.querySelector("#confirm-password").value;

    messageBox.textContent = "";
    messageBox.style.color = "white";
    messageBox.style.padding = "10px";

    if (newPassword !== confirmPassword) {
        messageBox.textContent = "Error: Passwords do not match.";
        messageBox.style.backgroundColor = "#ef4444"; 
        return;
    }

    if (newPassword.length < 8) {
        messageBox.textContent = "Error: Password must be at least 8 characters.";
        messageBox.style.backgroundColor = "#ef4444"; 
        return;
    }

    messageBox.textContent = "Success: Password updated successfully!";
    messageBox.style.backgroundColor = "#22c55e"; 

    document.querySelector("#current-password").value = "";
    document.querySelector("#new-password").value = "";
    document.querySelector("#confirm-password").value = "";
}

function handleAddStudent(event) {
    event.preventDefault();
    const name = document.querySelector("#student-name").value.trim();
    const id = document.querySelector("#student-id").value.trim();
    const email = document.querySelector("#student-email").value.trim();
    const defaultPassword = document.querySelector("#default-password").value.trim();

    if (!name || !id || !email) {
        alert("Please fill out all required fields.");
        return;
    }

    if (students.some(s => s.id === id)) {
        alert("A student with this ID already exists.");
        return;
    }

    students.push({ name, id, email });
    renderTable(students);

    document.querySelector("#student-name").value = "";
    document.querySelector("#student-id").value = "";
    document.querySelector("#student-email").value = "";
    document.querySelector("#default-password").value = "";
}

function handleTableClick(event) {
    if (event.target.classList.contains("delete-btn")) {
        const studentId = event.target.dataset.id;
        students = students.filter(s => s.id !== studentId);
        renderTable(students);
    }
}

function handleSearch(event) {
    const term = searchInput.value.toLowerCase();
    if (!term) {
        renderTable(students);
        return;
    }
    const filtered = students.filter(s => s.name.toLowerCase().includes(term));
    renderTable(filtered);
}

function handleSort(event) {
    const index = event.currentTarget.cellIndex;
    let prop;
    if (index === 0) prop = "name";
    else if (index === 1) prop = "id";
    else if (index === 2) prop = "email";
    else return;

    const dir = event.currentTarget.dataset.sortDir === "asc" ? "desc" : "asc";
    event.currentTarget.dataset.sortDir = dir;

    students.sort((a, b) => {
        if (prop === "id") return dir === "asc" ? a.id.localeCompare(b.id, undefined, { numeric: true }) : b.id.localeCompare(a.id, undefined, { numeric: true });
        return dir === "asc" ? a[prop].localeCompare(b[prop]) : b[prop].localeCompare(a[prop]);
    });

    renderTable(students);
}

async function loadStudentsAndInitialize() {
    try {
        const response = await fetch("students.json");
        if (!response.ok) throw new Error("Failed to fetch students.json");
        students = await response.json();
        renderTable(students);

        changePasswordForm.addEventListener("submit", handleChangePassword);
        addStudentForm.addEventListener("submit", handleAddStudent);
        studentTableBody.addEventListener("click", handleTableClick);
        searchInput.addEventListener("input", handleSearch);
        tableHeaders.forEach(th => th.addEventListener("click", handleSort));
    } catch (error) {
        console.error(error);
    }
}

loadStudentsAndInitialize();
