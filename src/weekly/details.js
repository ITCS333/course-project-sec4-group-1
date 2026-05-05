let currentWeekId = null;
let currentComments = [];

const weekTitle = document.querySelector('#week-title');
const weekStartDate = document.querySelector('#week-start-date');
const weekDescription = document.querySelector('#week-description');
const weekLinksList = document.querySelector('#week-links-list');
const commentList = document.querySelector('#comment-list');
const commentForm = document.querySelector('#comment-form');
const newCommentText = document.querySelector('#new-comment');

function getWeekIdFromURL() {
  const params = new URLSearchParams(window.location.search);
  return params.get('id');
}

function renderWeekDetails(week) {
  weekTitle.textContent = week.title;
  weekStartDate.textContent = "Starts on: " + week.start_date;
  weekDescription.textContent = week.description;

  weekLinksList.innerHTML = "";
  if (week.links) {
    week.links.forEach(link => {
      const li = document.createElement('li');
      const a = document.createElement('a');
      a.href = link;
      a.textContent = link;
      li.appendChild(a);
      weekLinksList.appendChild(li);
    });
  }
}

function createCommentArticle(comment) {
  const article = document.createElement('article');
  const p = document.createElement('p');
  p.textContent = comment.text;

  const footer = document.createElement('footer');
  footer.textContent = "Posted by: " + comment.author;

  article.appendChild(p);
  article.appendChild(footer);
  return article;
}

function renderComments() {
  commentList.innerHTML = "";
  currentComments.forEach(comment => {
    const article = createCommentArticle(comment);
    commentList.appendChild(article);
  });
}

async function handleAddComment(event) {
  event.preventDefault();

  const text = newCommentText.value.trim();
  if (!text) return;

  try {
    const res = await fetch(`./api/index.php?action=comment`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ 
        week_id: currentWeekId, 
        text: text,
        author: "Student" 
      })
    });
    const result = await res.json();
    if (result.success) {
      currentComments.push(result.data);
      renderComments();
      newCommentText.value = "";
    }
  } catch (err) {
    console.error("Error posting comment:", err);
  }
}

async function initializePage() {
  currentWeekId = getWeekIdFromURL();
  if (!currentWeekId) return;

  try {
    const [weekRes, commentsRes] = await Promise.all([
      fetch(`./api/index.php?id=${currentWeekId}`),
      fetch(`./api/index.php?action=comments&week_id=${currentWeekId}`)
    ]);

    const weekData = await weekRes.json();
    const commentsData = await commentsRes.json();

    if (weekData.success) {
      renderWeekDetails(weekData.data);
      currentComments = commentsData.data || [];
      renderComments();
      commentForm.addEventListener('submit', handleAddComment);
    }
  } catch (err) {
    console.error("Error loading data:", err);
  }
}

initializePage();
