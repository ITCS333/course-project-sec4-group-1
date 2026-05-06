let currentTopicId = null;
let currentReplies = [];

const topicSubjectElement = document.querySelector('#topic-subject');
const opMessage = document.querySelector('#op-message');
const opFooter = document.querySelector('#op-footer');
const replyListContainer = document.querySelector('#reply-list-container');
const replyForm = document.querySelector('#reply-form');
const newReplyText = document.querySelector('#new-reply');

function getTopicIdFromURL() {
    const params = new URLSearchParams(window.location.search);
    return params.get('id');
}

function renderOriginalPost(topic) {
    if (!topicSubjectElement || !opMessage || !opFooter) return;
    topicSubjectElement.textContent = topic.subject;
    opMessage.textContent = topic.message;
    opFooter.textContent = `Posted by: ${topic.author} on ${topic.created_at}`;
}

function createReplyArticle(reply) {
    const { id, text, author, created_at } = reply;
    const article = document.createElement('article');
    article.innerHTML = `
        <p>${text}</p>
        <footer>Posted by: ${author} on ${created_at}</footer>
        <div class="actions">
            <button type="button" class="delete-reply-btn" data-id="${id}">Delete</button>
        </div>
    `;
    return article;
}

function renderReplies() {
    if (!replyListContainer) return;
    replyListContainer.innerHTML = '';
    currentReplies.forEach(reply => {
        replyListContainer.appendChild(createReplyArticle(reply));
    });
}

async function handleAddReply(event) {
    event.preventDefault();
    const text = newReplyText.value.trim();
    if (!text) return;

    try {
        const response = await fetch('./api/index.php?action=reply', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ 
                topic_id: currentTopicId, 
                text: text, 
                author: 'Student' 
            })
        });
        const result = await response.json();
        if (result.success) {
            newReplyText.value = '';
            await initializePage();
        }
    } catch (error) {
        console.error('Failed to post reply:', error);
    }
}

async function handleReplyListClick(event) {
    const target = event.target;
    if (target.classList.contains('delete-reply-btn')) {
        const id = target.dataset.id;
        try {
            const response = await fetch(`./api/index.php?action=delete_reply&id=${id}`, { 
                method: 'DELETE' 
            });
            const result = await response.json();
            if (result.success) await initializePage();
        } catch (error) {
            console.error('Failed to delete reply:', error);
        }
    }
}

async function initializePage() {
    currentTopicId = getTopicIdFromURL();
    if (!currentTopicId) return;

    try {
        const [tRes, rRes] = await Promise.all([
            fetch(`./api/index.php?id=${currentTopicId}`),
            fetch(`./api/index.php?action=replies&topic_id=${currentTopicId}`)
        ]);

        const tData = await tRes.json();
        const rData = await rRes.json();

        if (tData.success) renderOriginalPost(tData.data);
        if (rData.success) {
            currentReplies = rData.data;
            renderReplies();
        }
    } catch (error) {
        console.error('Failed to initialize page:', error);
    }
}

if (replyForm) replyForm.addEventListener('submit', handleAddReply);
if (replyListContainer) replyListContainer.addEventListener('click', handleReplyListClick);

initializePage();
