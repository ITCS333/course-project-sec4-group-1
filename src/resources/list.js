const apiUrl = './api/index.php';

function createResourceArticle(resource) {
    const article = document.createElement('article');
    article.innerHTML = `
        <a href="details.html?id=${resource.id}">${resource.title}</a>
        <p>${resource.description}</p>
    `;
    return article;
}

window.createResourceArticle = createResourceArticle;

async function loadResources() {
    const container = document.getElementById('resource-list-section');
    if (!container) return;
    
    container.innerHTML = '';

    try {
        const res = await fetch(apiUrl);
        const data = await res.json();

        if (data.success && Array.isArray(data.data)) {
            data.data.forEach(resource => {
                const article = createResourceArticle(resource);
                container.appendChild(article);
            });
        }
    } catch (err) {
        console.error('Error:', err);
    }
}

window.loadResources = loadResources;

loadResources();
