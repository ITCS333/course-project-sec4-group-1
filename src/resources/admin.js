const apiUrl = './api/index.php';
let resources = [];

function createResourceRow(resource) {
    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td>${resource.title}</td>
        <td>${resource.description}</td>
        <td>${resource.link}</td>
        <td>
            <button class="edit-btn" data-id="${resource.id}">Edit</button>
            <button class="delete-btn" data-id="${resource.id}">Delete</button>
        </td>
    `;
    return tr;
}

function renderTable(data) {
    const tbody = document.getElementById('resources-tbody');
    tbody.innerHTML = '';
    data.forEach(r => tbody.appendChild(createResourceRow(r)));
}

async function handleAddResource(event) {
    event.preventDefault();
    const title = document.getElementById('resource-title').value;
    const description = document.getElementById('resource-description').value;
    const link = document.getElementById('resource-link').value;

    const body = { title, description, link };

    try {
        const res = await fetch(apiUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body)
        });
        const data = await res.json();
        if (data.success) {
            document.getElementById('resource-form').reset();
            loadAndInitialize();
        }
    } catch (err) {
        console.error(err);
    }
}

function handleTableClick(event) {
    const target = event.target;
    const id = target.dataset.id;

    if (target.classList.contains('delete-btn')) {
        if (!confirm('Are you sure?')) return;
        fetch(apiUrl, {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        }).then(() => loadAndInitialize());
    } else if (target.classList.contains('edit-btn')) {
        const resource = resources.find(r => r.id == id);
        if (resource) {
            document.getElementById('resource-title').value = resource.title;
            document.getElementById('resource-description').value = resource.description;
            document.getElementById('resource-link').value = resource.link;
        }
    }
}

async function loadAndInitialize() {
    try {
        const res = await fetch(apiUrl);
        const data = await res.json();
        if (data.success) {
            resources = data.data;
            renderTable(resources);
        }
    } catch (err) {
        console.error(err);
    }

    if (!loadAndInitialize._listenersAttached) {
        document.getElementById('resource-form').addEventListener('submit', handleAddResource);
        document.getElementById('resources-tbody').addEventListener('click', handleTableClick);
        loadAndInitialize._listenersAttached = true;
    }
}

loadAndInitialize._listenersAttached = false;

window.createResourceRow = createResourceRow;
window.renderTable = renderTable;
window.handleAddResource = handleAddResource;
window.handleTableClick = handleTableClick;
window.loadAndInitialize = loadAndInitialize;

loadAndInitialize();
