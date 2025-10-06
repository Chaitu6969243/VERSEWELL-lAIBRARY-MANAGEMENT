document.addEventListener("DOMContentLoaded", function() {
    if (!localStorage.getItem("vw_signed_in")) {
        window.location.href = "./auth.html";
        return;
    }
    
    loadTrendingBooks();
    setupModalHandlers();
    setupSearchHandler();
    setupSignInOut();
});

function setupModalHandlers() {
    const modalCloseBtn = document.querySelector(".modal-close");
    if (modalCloseBtn) {
        modalCloseBtn.addEventListener("click", closeModal);
    }
    
    const cancelBtn = document.getElementById("cancelBorrow");
    if (cancelBtn) {
        cancelBtn.addEventListener("click", closeModal);
    }
}

function loadTrendingBooks() {
    fetch("./book_api.php?action=trending&limit=20")
        .then(response => response.json())
        .then(data => {
            console.log("Books data:", data);
            if (data.success && data.books) {
                displayBooks(data.books);
            } else {
                document.getElementById("status").textContent = "No books available";
            }
        })
        .catch(error => {
            console.error("Error loading books:", error);
            document.getElementById("status").textContent = "Error loading books";
        });
}

function displayBooks(books) {
    const container = document.getElementById("booksGrid");
    if (!container) return;
    
    container.innerHTML = "";
    
    books.forEach(book => {
        const div = document.createElement("div");
        div.className = "book-card";
        
        let description = book.description || "";
        if (description.length > 200) {
            description = description.substring(0, 200) + "...";
        }
        
        div.innerHTML = `
            <div class="book-cover">
                <img src="${book.cover_url || ""}" alt="${book.title || "Book cover"}" loading="lazy">
            </div>
            <div class="book-info">
                <div class="book-content">
                    <h3 class="book-title">${book.title || "Unknown Title"}</h3>
                    <p class="book-authors">${book.authors || "Unknown Author"}</p>
                    ${description ? `<p class="book-description">${description}</p>` : ""}
                </div>
                <div class="book-actions">
                    <button class="btn btn-accent" onclick="openBorrowModal('${book.google_book_id}', '${(book.title || "").replace(/'/g, "\\'")}', '${(book.authors || "").replace(/'/g, "\\'")}', '${book.cover_url || ""}')">
                        <i class="fas fa-book"></i> Borrow
                    </button>
                </div>
            </div>
        `;
        container.appendChild(div);
    });
}

function openBorrowModal(bookId, title, authors, coverUrl) {
    const modal = document.getElementById("borrowModal");
    modal.style.display = "flex";
    modal.removeAttribute("hidden");
    document.getElementById("modalBookTitle").textContent = title;
    document.getElementById("modalBookAuthor").textContent = authors;
    if (coverUrl) document.getElementById("modalBookCover").src = coverUrl;
    
    const durationSelect = document.getElementById("borrowDuration");
    updateDueDate();
    durationSelect.onchange = updateDueDate;
    
    document.getElementById("confirmBorrow").onclick = function() { borrowBook(bookId, title, authors); };
}

function updateDueDate() {
    const duration = parseInt(document.getElementById("borrowDuration").value);
    const dueDate = new Date();
    dueDate.setDate(dueDate.getDate() + duration);
    document.getElementById("dueDate").textContent = dueDate.toLocaleDateString("en-US", {
        year: "numeric",
        month: "long",
        day: "numeric"
    });
}

function borrowBook(bookId, title, authors) {
    const confirmBtn = document.getElementById("confirmBorrow");
    const cancelBtn = document.getElementById("cancelBorrow");
    
    if (confirmBtn) {
        confirmBtn.disabled = true;
        confirmBtn.innerHTML = `<i class="fas fa-spinner fa-spin"></i> Borrowing...`;
    }
    if (cancelBtn) cancelBtn.disabled = true;

    const duration = parseInt(document.getElementById("borrowDuration").value);
    const formData = new FormData();
    formData.append("action", "borrow");
    formData.append("google_book_id", bookId);
    formData.append("title", title);
    formData.append("authors", authors);
    formData.append("duration", duration);

    fetch("./book_api.php", {
        method: "POST",
        body: formData,
        credentials: "include"
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showSuccessMessage("Book borrowed successfully!");
            closeModal();
        } else {
            alert(data.message || "Failed to borrow book");
        }
    })
    .catch(error => {
        console.error("Error:", error);
        alert("An error occurred while borrowing the book");
    })
    .finally(() => {
        if (confirmBtn) {
            confirmBtn.disabled = false;
            confirmBtn.innerHTML = "Confirm Borrow";
        }
        if (cancelBtn) cancelBtn.disabled = false;
    });
}

function closeModal() {
    const modal = document.getElementById("borrowModal");
    modal.style.display = "none";
    modal.setAttribute("hidden", "");
}

function showSuccessMessage(message) {
    const toast = createToast(message, "success");
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.remove();
    }, 3000);
}

function createToast(message, type = "info") {
    const toast = document.createElement("div");
    toast.className = `toast toast-${type}`;
    toast.innerHTML = `
        <div class="toast-content">
            <i class="fas ${type === "success" ? "fa-check-circle" : "fa-info-circle"}"></i>
            <span>${message}</span>
        </div>
    `;
    
    toast.style.position = "fixed";
    toast.style.top = "20px";
    toast.style.right = "20px";
    toast.style.zIndex = "1001";
    
    return toast;
}

function setupSearchHandler() {
    const searchForm = document.getElementById('searchForm');
    const titleQuery = document.getElementById('titleQuery');
    const booksGrid = document.getElementById('booksGrid');
    const status = document.getElementById('status');

    // Function to fetch books (replace with your actual API endpoint)
    async function fetchBooks(query) {
        try {
            const response = await fetch(`./book_api.php?action=search&query=${encodeURIComponent(query)}`);
            if (!response.ok) {
                console.error('Failed to fetch books. HTTP status:', response.status);
                throw new Error('Failed to fetch books');
            }
            const data = await response.json();
            if (!data.success) {
                console.error('API error:', data.error || 'Unknown error');
                throw new Error(data.error || 'Unknown error');
            }
            return data.books || [];
        } catch (error) {
            console.error('Error fetching books:', error);
            return [];
        }
    }

    // Function to render books in the grid
    function renderBooks(books) {
        const booksGrid = document.getElementById('booksGrid');
        const status = document.getElementById('status');

        booksGrid.innerHTML = ''; // Clear previous results

        if (books.length === 0) {
            status.textContent = 'No books found.';
            return;
        }

        status.textContent = '';

        books.forEach((book) => {
            const bookEl = document.createElement('div');
            bookEl.className = 'book-card';
            bookEl.innerHTML = `
                <div class="book-cover">
                    <img src="${book.cover_url || ''}" alt="${book.title || 'Book cover'}" loading="lazy">
                </div>
                <div class="book-info">
                    <h3 class="book-title">${book.title || 'Unknown Title'}</h3>
                    <p class="book-authors">${book.authors || 'Unknown Author'}</p>
                    <p class="book-description">${book.description || 'No description available.'}</p>
                </div>
            `;
            booksGrid.appendChild(bookEl);
        });
    }

    // Handle search form submission
    searchForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const query = titleQuery.value.trim();
        if (!query) {
            status.textContent = 'Please enter a search term.';
            return;
        }
        status.textContent = 'Searching...';
        const books = await fetchBooks(query);
        renderBooks(books);
    });
}

function setupSignInOut() {
    const signedIn = localStorage.getItem('vw_signed_in') === '1';
    const signInButton = document.querySelector('.signin-btn');
    const signOutButton = document.querySelector('.signout-btn');

    if (signedIn) {
        if (signInButton) signInButton.style.display = 'none';
        if (signOutButton) signOutButton.style.display = 'block';
    } else {
        if (signInButton) signInButton.style.display = 'block';
        if (signOutButton) signOutButton.style.display = 'none';
    }

    if (signOutButton) {
        signOutButton.addEventListener('click', () => {
            localStorage.removeItem('vw_signed_in');
            localStorage.removeItem('vw_user_id');
            localStorage.removeItem('vw_user_email');
            localStorage.removeItem('vw_user_name');
            localStorage.removeItem('token');
            window.location.href = 'index.html';
        });
    }
}
