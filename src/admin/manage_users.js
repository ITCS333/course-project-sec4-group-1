let users = [];

const userTableBody = document.querySelector("#user-table-body");
const addUserForm = document.querySelector("#add-user-form");
const passwordForm = document.querySelector("#password-form");
const searchInput = document.querySelector("#search-input");
const tableHeaders = document.querySelectorAll("#user-table thead th");

function createUserRow(user) {
    const tr = document.createElement("tr");

    const nameTd = document.createElement("td");
    nameTd.textContent = user.name;
    tr.appendChild(nameTd);

    const emailTd = document.createElement("td");
    emailTd.textContent = user.email;
    tr.appendChild(emailTd);

    const adminTd = document.createElement("td");
    adminTd.textContent = user.is_admin == 1 ? "Yes" : "No";
    tr.appendChild(adminTd);

    const actionsTd = document.createElement("td");
    
    const editBtn = document.createElement("button");
    editBtn.textContent = "Edit";
    editBtn.classList.add("edit-btn");
    editBtn.dataset.id = user.id;

    const deleteBtn = document.createElement("button");
    deleteBtn.textContent = "Delete";
    deleteBtn.classList.add("delete-btn");
    deleteBtn.dataset.id = user.id;

    actionsTd.appendChild(editBtn);
    actionsTd.appendChild(deleteBtn);
    tr.appendChild(actionsTd);

    return tr;
}

function renderTable(userArray) {
    userTableBody.innerHTML = "";
    userArray.forEach(user => {
        userTableBody.appendChild(createUserRow(user));
    });
}

function handleChangePassword(event) {
    event.preventDefault();

    const currentPw = document.getElementById("current-password");
    const newPw = document.getElementById("new-password");
    const confirmPw = document.getElementById("confirm-password");

    if (newPw.value !== confirmPw.value) {
        alert("Error: Passwords do not match.");
        return;
    }

    if (newPw.value.length < 8) {
        alert("Error: Password must be at least 8 characters.");
        return;
    }

    currentPw.value = "";
    newPw.value = "";
    confirmPw.value = "";
}

async function handleAddUser(event) {
    event.preventDefault();

    const name = document.getElementById("user-name").value.trim();
    const email = document.getElementById("user-email").value.trim();
    const password = document.getElementById("default-password").value.trim();
    const isAdmin = document.getElementById("is-admin").value;

    if (!name || !email || !password) {
        alert("Error: Please fill out all required fields.");
        return;
    }

    try {
        const response = await fetch("api/index.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ name, email, password, is_admin: isAdmin })
        });
        
        if (response.ok) {
            await loadUsersAndInitialize();
            document.getElementById("user-name").value = "";
            document.getElementById("user-email").value = "";
            document.getElementById("default-password").value = "";
        }
    } catch (err) {
        console.error(err);
    }
}

async function handleTableClick(event) {
    if (event.target.classList.contains("delete-btn")) {
        const userId = event.target.dataset.id;
        
        if (confirm("Are you sure?")) {
            const response = await fetch(`api/index.php?id=${userId}`, {
                method: "DELETE"
            });
            if (response.ok) {
                users = users.filter(u => u.id != userId);
                renderTable(users);
            }
        }
    }
}

function handleSearch() {
    const term = searchInput.value.toLowerCase();
    const filtered = users.filter(u => 
        u.name.toLowerCase().includes(term) || 
        u.email.toLowerCase().includes(term)
    );
    renderTable(filtered);
}

function handleSort(event) {
    const header = event.currentTarget;
    const index = header.cellIndex;
    let prop = index === 0 ? "name" : (index === 1 ? "email" : null);
    
    if (!prop) return;

    const currentDir = header.dataset.sortDir || "desc";
    const newDir = currentDir === "asc" ? "desc" : "asc";
    header.dataset.sortDir = newDir;

    users.sort((a, b) => {
        return newDir === "asc" 
            ? a[prop].localeCompare(b[prop]) 
            : b[prop].localeCompare(a[prop]);
    });

    renderTable(users);
}

async function loadUsersAndInitialize() {
    try {
        const response = await fetch("api/index.php");
        const result = await response.json();
        
        if (result.success) {
            users = result.data;
            renderTable(users);
        }

        if (!loadUsersAndInitialize._listenersAttached) {
            passwordForm.addEventListener("submit", handleChangePassword);
            addUserForm.addEventListener("submit", handleAddUser);
            userTableBody.addEventListener("click", handleTableClick);
            searchInput.addEventListener("input", handleSearch);
            tableHeaders.forEach(th => th.addEventListener("click", handleSort));
            
            loadUsersAndInitialize._listenersAttached = true;
        }
    } catch (error) {
        console.error(error);
    }
}

loadUsersAndInitialize._listenersAttached = false;
loadUsersAndInitialize();
