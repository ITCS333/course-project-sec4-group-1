let topics = [];

const topicListContainer = document.querySelector('#topic-list-container');
const newTopicForm = document.querySelector('#new-topic-form');
const topicSubject = document.querySelector('#topic-subject');
const topicMessage = document.querySelector('#topic-message');

function createTopicArticle(topic) {
    const { id, subject, message, author, created_at } = topic;
    const article = document.createElement('article');

    article.innerHTML = `
        <h3><a href="topic.html?id=${id}">${subject}</a></h3>
        <p>${message}</p>
        <footer>Posted by: ${author} on ${created_at}</footer>
        <div class="actions">
            <button type="button" class="delete-btn" data-id="${id}">Delete</button>
        </div>
    `;
    return article;
}

function renderTopics() {
    if (!topicListContainer) return;
    topicListContainer.innerHTML = '';
    topics.forEach(topic => {
        topicListContainer.appendChild(createTopicArticle(topic));
    });
}

async function handleCreateTopic(event) {
    event.preventDefault();
    const subject = topicSubject.value.trim();
    const message = topicMessage.value.trim();

    if (!subject || !message) return;

    try {
        const response = await fetch('./api/index.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ subject, message, author: 'Student' })
        });
        const result = await response.json();
        if (result.success) {
            topicSubject.value = '';
            topicMessage.value = '';
            await loadAndInitialize();
        }
    } catch (error) {
        console.error('Failed to create topic:', error);
    }
}

async function handleTopicListClick(event) {
    const target = event.target;
    if (target.classList.contains('delete-btn')) {
        const id = target.dataset.id;
        if (!confirm('Are you sure?')) return;

        try {
            const response = await fetch(`./api/index.php?id=${id}`, { method: 'DELETE' });
            const result = await response.json();
            if (result.success) await loadAndInitialize();
        } catch (error) {
            console.error('Failed to delete topic:', error);
        }
    }
}

async function loadAndInitialize() {
    try {
        const response = await fetch('./api/index.php');
        const result = await response.json();
        if (result.success) {
            topics = result.data;
            renderTopics();
        }
    } catch (error) {
        console.error('Failed to load topics:', error);
    }

    if (newTopicForm) {
        newTopicForm.removeEventListener('submit', handleCreateTopic);
        newTopicForm.addEventListener('submit', handleCreateTopic);
    }
    if (topicListContainer) {
        topicListContainer.removeEventListener('click', handleTopicListClick);
        topicListContainer.addEventListener('click', handleTopicListClick);
    }
}

loadAndInitialize();
