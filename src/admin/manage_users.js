let users = [];
const userTableBody = document.querySelector('#user-table-body');
const addUserForm = document.querySelector('#add-user-form');
const passwordForm = document.querySelector('#password-form');
const searchInput = document.querySelector('#search-input');
function createUserRow(user) {
  const tr = document.createElement('tr');
  const name = document.createElement('td'); name.textContent = user.name; tr.appendChild(name);
  const email = document.createElement('td'); email.textContent = user.email; tr.appendChild(email);
  const admin = document.createElement('td'); admin.textContent = Number(user.is_admin) === 1 ? 'Yes' : 'No'; tr.appendChild(admin);
  const actions = document.createElement('td');
  const edit = document.createElement('button'); edit.textContent = 'Edit'; edit.className = 'edit-btn'; edit.dataset.id = user.id;
  const del = document.createElement('button'); del.textContent = 'Delete'; del.className = 'delete-btn'; del.dataset.id = user.id;
  actions.appendChild(edit); actions.appendChild(del); tr.appendChild(actions);
  return tr;
}
function renderTable(data = users) { userTableBody.innerHTML = ''; data.forEach(user => userTableBody.appendChild(createUserRow(user))); }
async function handleChangePassword(event) {
  event.preventDefault();
  const current = document.getElementById('current-password').value;
  const next = document.getElementById('new-password').value;
  const confirm = document.getElementById('confirm-password').value;
  if (next !== confirm) { alert('Passwords do not match'); return; }
  if (next.length < 8) { alert('Password must be at least 8 characters'); return; }
  await fetch('./api/index.php?action=change_password', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({id:1,current_password:current,new_password:next})});
  document.getElementById('current-password').value = '';
  document.getElementById('new-password').value = '';
  document.getElementById('confirm-password').value = '';
}
async function handleAddUser(event) {
  event.preventDefault();
  const name = document.getElementById('user-name').value.trim();
  const email = document.getElementById('user-email').value.trim();
  const password = document.getElementById('default-password').value;
  const is_admin = Number(document.getElementById('is-admin') ? document.getElementById('is-admin').value : 0);
  if (!name || !email || !password) { alert('All fields are required'); return; }
  const res = await fetch('./api/index.php', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({name,email,password,is_admin})});
  const result = await res.json();
  if (result.success) { users.push({id: result.data.id, name, email, is_admin}); renderTable(); addUserForm.reset(); }
}
async function handleTableClick(event) {
  const btn = event.target;
  const id = btn.dataset.id;
  if (btn.classList.contains('delete-btn')) { await fetch('./api/index.php?id=' + id, {method:'DELETE'}); users = users.filter(u => String(u.id) !== String(id)); renderTable(); }
  if (btn.classList.contains('edit-btn')) { const user = users.find(u => String(u.id) === String(id)); if (user) { document.getElementById('user-name').value = user.name; document.getElementById('user-email').value = user.email; } }
}
function handleSearch(event) {
  const term = event.target.value.toLowerCase();
  renderTable(users.filter(u => u.name.toLowerCase().includes(term) || u.email.toLowerCase().includes(term)));
}
function handleSort(event) {
  const header = event.currentTarget;
  const index = Array.from(header.parentElement.children).indexOf(header);
  const key = index === 1 ? 'email' : 'name';
  const dir = header.dataset.sortDir === 'asc' ? 'desc' : 'asc';
  header.dataset.sortDir = dir;
  users.sort((a,b) => dir === 'asc' ? String(a[key]).localeCompare(String(b[key])) : String(b[key]).localeCompare(String(a[key])));
  renderTable();
}
async function loadUsersAndInitialize() {
  const res = await fetch('./api/index.php');
  const result = await res.json();
  if (result.success) { users = result.data; renderTable(); }
  if (!loadUsersAndInitialize._listenersAttached) {
    if (passwordForm) passwordForm.addEventListener('submit', handleChangePassword);
    if (addUserForm) addUserForm.addEventListener('submit', handleAddUser);
    if (userTableBody) userTableBody.addEventListener('click', handleTableClick);
    if (searchInput) searchInput.addEventListener('input', handleSearch);
    document.querySelectorAll('#user-table thead th').forEach(th => th.addEventListener('click', handleSort));
    loadUsersAndInitialize._listenersAttached = true;
  }
}
if (typeof window !== 'undefined') loadUsersAndInitialize();
