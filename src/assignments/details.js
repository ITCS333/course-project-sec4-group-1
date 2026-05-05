let currentAssignmentId = null;
let currentComments = [];

const assignmentTitle = document.getElementById("assignment-title");
const assignmentDueDate = document.getElementById("assignment-due-date");
const assignmentDescription = document.getElementById("assignment-description");
const assignmentFilesList = document.getElementById("assignment-files-list");

const commentList = document.getElementById("comment-list");
const commentForm = document.getElementById("comment-form");
const newCommentText = document.getElementById("new-comment");

function getAssignmentIdFromURL() {
  const params = new URLSearchParams(window.location.search);
  return params.get("id");
}

function renderAssignmentDetails(assignment) {
  assignmentTitle.textContent = assignment.title;
  assignmentDueDate.textContent = "Due: " + assignment.due_date;
  assignmentDescription.textContent = assignment.description;

  assignmentFilesList.innerHTML = "";
  if (assignment.files) {
    assignment.files.forEach(fileUrl => {
      const li = document.createElement("li");
      const a = document.createElement("a");
      a.href = fileUrl;
      a.textContent = fileUrl.split('/').pop();
      li.appendChild(a);
      assignmentFilesList.appendChild(li);
    });
  }
}

function createCommentArticle(comment) {
  const article = document.createElement("article");
  article.innerHTML = `
    <p>${comment.text}</p>
    <footer>Posted by: ${comment.author}</footer>
  `;
  return article;
}

function renderComments() {
  commentList.innerHTML = "";
  currentComments.forEach(c => {
    commentList.appendChild(createCommentArticle(c));
  });
}

async function handleAddComment(event) {
  event.preventDefault();
  const text = newCommentText.value.trim();
  
  if (!text) return;

  try {
    const response = await fetch(`./api/index.php?action=comment`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        assignment_id: currentAssignmentId,
        text: text
      })
    });

    const result = await response.json();
    if (result.success) {
      currentComments.push(result.data);
      renderComments();
      newCommentText.value = "";
    }
  } catch (err) {
    console.error(err);
  }
}

async function initializePage() {
  currentAssignmentId = getAssignmentIdFromURL();
  if (!currentAssignmentId) return;

  try {
    const [asgRes, comRes] = await Promise.all([
      fetch(`./api/index.php?resource=assignments&id=${currentAssignmentId}`),
      fetch(`./api/index.php?action=comments&assignment_id=${currentAssignmentId}`)
    ]);

    const asgResult = await asgRes.json();
    const comResult = await comRes.json();

    if (asgResult.success) {
      renderAssignmentDetails(asgResult.data);
    }
    
    currentComments = comResult.data || [];
    renderComments();

    commentForm.addEventListener("submit", handleAddComment);
  } catch (error) {
    console.error(error);
  }
}

initializePage();
