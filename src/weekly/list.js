const listSection = document.querySelector('#week-list-section');

function createWeekArticle(week) {
  const article = document.createElement('article');

  const h2 = document.createElement('h2');
  h2.textContent = week.title;

  const pDate = document.createElement('p');
  pDate.textContent = "Starts on: " + week.start_date;

  const pDesc = document.createElement('p');
  pDesc.textContent = week.description;

  const link = document.createElement('a');
  link.href = `details.html?id=${week.id}`;
  link.textContent = "View Details & Discussion";

  article.appendChild(h2);
  article.appendChild(pDate);
  article.appendChild(pDesc);
  article.appendChild(link);

  return article;
}

async function loadWeeks() {
  try {
    const res = await fetch('./api/index.php');
    const result = await res.json();

    listSection.innerHTML = "";

    if (result.success && Array.isArray(result.data)) {
      result.data.forEach(week => {
        const article = createWeekArticle(week);
        listSection.appendChild(article);
      });
    }
  } catch (err) {
    console.error("Error loading weeks:", err);
    listSection.textContent = "Failed to load weeks.";
  }
}

loadWeeks();
