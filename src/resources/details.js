const apiUrl = './api/index.php';
let currentComments = [];

function getResourceIdFromURL() {
    const urlParams = new URLSearchParams(window.location.search);
    return urlParams.get('id');
}

function renderResourceDetails(resource) {
    document.getElementById('resource-title').textContent = resource.title;
    document.getElementById('resource-description').textContent = resource.description;
    document.getElementById('resource-link').setAttribute('href', resource.link);
}

function createCommentArticle(comment) {
    const article = document.createElement('article');
    article.innerHTML = `
        <p>${comment.text}</p>
        <footer>Authored by: ${comment.author}</footer>
    `;
    return article;
}

function renderComments() {
    const list = document.getElementById('comment-list');
    list.innerHTML = '';
    currentComments.forEach(c => {
        list.appendChild(createCommentArticle(c));
    });
}

async function handleAddComment(event) {
    event.preventDefault();
    const textarea = document.getElementById('new-comment');
    const text = textarea.value.trim();
    const id = getResourceIdFromURL();

    if (!text) return;

    try {
        const res = await fetch(`${apiUrl}?action=comment`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                resource_id: id,
                author: "Student",
                text: text
            })
        });
        const data = await res.json();
        if (data.success) {
            textarea.value = '';
            const newComment = data.data || { author: "Student", text: text };
            currentComments.push(newComment);
            renderComments();
        }
    } catch (err) {
        console.error(err);
    }
}

async function initializePage() {
    const id = getResourceIdFromURL();
    if (!id) return;

    try {
        const resRes = await fetch(`${apiUrl}?id=${id}`);
        const resData = await resRes.json();
        if (resData.success) renderResourceDetails(resData.data);

        const comRes = await fetch(`${apiUrl}?action=comments&resource_id=${id}`);
        const comData = await comRes.json();
        if (comData.success) {
            currentComments = comData.data;
            renderComments();
        }
    } catch (err) {
        console.error(err);
    }

    const form = document.getElementById('comment-form');
    if (form) form.addEventListener('submit', handleAddComment);
}

window.getResourceIdFromURL = getResourceIdFromURL;
window.renderResourceDetails = renderResourceDetails;
window.createCommentArticle = createCommentArticle;
window.renderComments = renderComments;
window.handleAddComment = handleAddComment;
window.initializePage = initializePage;

initializePage();
