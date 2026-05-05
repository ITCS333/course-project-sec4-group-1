const listSection = document.getElementById("assignment-list-section");

function createAssignmentArticle(assignment) {
  const article = document.createElement("article");

  article.innerHTML = `
      <h2>${assignment.title}</h2>
      <p>Due: ${assignment.due_date}</p>
      <p>${assignment.description}</p>
      <a href="details.html?id=${assignment.id}">
        View Details & Discussion
      </a>
  `;

  return article;
}

async function loadAssignments() {
  try {
    const response = await fetch("./api/index.php?resource=assignments");
    const result = await response.json();

    listSection.innerHTML = "";
    const assignments = result.data || [];

    assignments.forEach(assignment => {
      const article = createAssignmentArticle(assignment);
      listSection.appendChild(article);
    });
  } catch (err) {
    console.error(err);
    listSection.textContent = "Failed to load assignments.";
  }
}

loadAssignments();
