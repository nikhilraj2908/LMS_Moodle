const searchInput = document.getElementById('course-search-input');
const searchResultsContainer = document.getElementById('search-results-container');

if (searchInput && searchResultsContainer) {
    searchInput.addEventListener('input', debounce(function(e) {
        const query = e.target.value.trim();
        if (query.length >= 2) {
            fetchSearchResults(query);
        } else {
            clearSearchResults();
        }
    }, 300));

    document.addEventListener('click', function(e) {
        if (!searchResultsContainer.contains(e.target) && e.target !== searchInput) {
            clearSearchResults();
        }
    });
}

function debounce(func, wait) {
    let timeout;
    return function () {
        const context = this, args = arguments;
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(context, args), wait);
    };
}

function fetchSearchResults(query) {
    searchResultsContainer.innerHTML = '<div class="text-center p-2">Searching...</div>';
    searchResultsContainer.style.display = 'block';

    fetch(`${M.cfg.wwwroot}/my/index.php`, { // important: direct all requests to /my/index.php
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: 'search=' + encodeURIComponent(query)
    })
    .then(response => response.json())
    .then(data => {
        if (data && data.success) {
            displaySearchResults(data.searchResults);
        } else {
            displayError(data?.error || 'No results found');
        }
    })
    .catch(error => {
        console.error('Search error:', error);
        displayError('Failed to complete search.');
    });
}

function displaySearchResults(results) {
    const container = document.getElementById('search-results-container');
    container.innerHTML = '';

    if (!results || results.length === 0) {
        container.innerHTML = '<div class="search-result-item">No courses found</div>';
        container.style.display = 'block';
        return;
    }

    results.forEach(course => {
        const item = document.createElement('div');
        item.className = 'search-result-item';
        item.textContent = course.coursename;
        item.onclick = () => window.location.href = course.courseurl;
        container.appendChild(item);
    });

    container.style.display = 'block';
}

function displayError(message) {
    if (!searchResultsContainer) return;
    searchResultsContainer.innerHTML = `<div class="alert alert-danger p-2">${escapeHtml(message)}</div>`;
    searchResultsContainer.style.display = 'block';
}

function clearSearchResults() {
    if (searchResultsContainer) {
        searchResultsContainer.innerHTML = '';
        searchResultsContainer.style.display = 'none';
    }
}

function escapeHtml(unsafe) {
    if (!unsafe) return '';
    return unsafe
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}
