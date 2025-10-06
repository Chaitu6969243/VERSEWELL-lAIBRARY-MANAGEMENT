class AdminPanel {
  constructor() {
    this.user = null;
    this.currentTab = 'dashboard';
    this.users = [];
    this.books = [];
    this.borrowedBooks = [];
    this.init();
  }
  async checkAuth() {
    try {
      console.log('Performing auth check...');
      
      // Check server-side session first (server truth wins)
      const response = await fetch('./admin_backend.php?action=whoami', {
        credentials: 'include'
      });
      
      console.log('Whoami response status:', response.status);
      
      const sessionData = await response.json();
      console.log('Whoami response data:', sessionData);
      
      if (sessionData.admin && sessionData.admin.is_active) {
        // Valid admin session exists
        this.user = {
          name: sessionData.admin.name,
          email: sessionData.admin.email,
          role: sessionData.admin.role
        };
        
        // Update localStorage for UI consistency
        localStorage.setItem('vw_signed_in', '1');
        localStorage.setItem('vw_is_admin', '1');
        localStorage.setItem('vw_user_name', sessionData.admin.name);
        localStorage.setItem('vw_user_email', sessionData.admin.email);
        
        console.log('✅ Admin auth successful:', this.user);
        
        // Mark that we have a valid server session
        this.user.hasValidSession = true;
        
        const adminNameEl = document.getElementById('adminName');
        if (adminNameEl) {
          adminNameEl.textContent = this.user.name;
        }
        
        return true;
      } else if (sessionData.user) {
        console.log('❌ Regular user session found, need admin session');
        alert('Your current session is a regular user. Please login as an admin to access this page.');
        window.location.href = './admin-login.html';
        return false;
      } else {
        // No valid session - redirect to login
        console.log('❌ No valid server session found:', sessionData);
        console.log('Redirecting to login page');
        alert('Admin access required. Please login as admin.');
        window.location.href = './admin-login.html';
        return false;
      }
    } catch (e) {
      console.error('Auth check error:', e);
      console.log('Server connection failed, redirecting to login');
      window.location.href = './admin-login.html';
      return false;
    }
  }

  async init() {
    try {
      console.log('Starting admin panel initialization...');
      
      // Check authentication first
      console.log('Checking authentication...');
      const authResult = await this.checkAuth();
      console.log('Auth result:', authResult);
      
      if (!authResult) {
        console.log('Auth failed, will redirect to login');
        return; // Will redirect to login if auth fails
      }

      // Bind event listeners
      console.log('Binding events...');
      this.bindEvents();
      
      // Auth was successful, continue with initialization
      
      // Load initial data
      console.log('Loading initial data...');
      await this.loadData();
      
      // Show initial tab (dashboard)
      console.log('Showing dashboard tab...');
      this.showTab('dashboard');
      
      console.log('Admin panel initialized successfully');
    } catch (error) {
      console.error('Error initializing admin panel:', error);
      console.error('Error stack:', error.stack);
      alert('Failed to initialize admin panel. Please refresh the page.');
    }
  }

  async loadData() {
    try {
      // Load users from database
      const usersResponse = await fetch('./admin_backend.php?action=users', {
        credentials: 'include'
      });
      this.users = await usersResponse.json();
      
      // Load borrowings from database
      const borrowingsResponse = await fetch('./admin_backend.php?action=borrowings', {
        credentials: 'include'
      });
      this.borrowedBooks = await borrowingsResponse.json();
      
      // Load books from database
      const booksResponse = await fetch('./admin_backend.php?action=books', {
        credentials: 'include'
      });
      this.books = await booksResponse.json();
      
      // Calculate stats from loaded data
      this.stats = {
        total_users: this.users.length,
        total_books: this.books.length,
        total_borrowings: this.borrowedBooks.length,
        active_borrowings: this.borrowedBooks.filter(b => b.status === 'borrowed').length,
        overdue_books: this.borrowedBooks.filter(b => b.display_status === 'overdue').length
      };
      
      console.log('Loaded admin data:', {
        users: this.users.length,
        borrowings: this.borrowedBooks.length,
        books: this.books.length,
        stats: this.stats
      });


      if (this.borrowedBooks.length > 0) {
        console.log('Sample borrowing data:', this.borrowedBooks[0]);
      }
      
    } catch (error) {
      console.error('Error loading admin data:', error);
      // Fallback to empty arrays
      this.users = [];
      this.borrowedBooks = [];
      this.books = [];
      this.stats = {};
    }
  }



  bindEvents() {
    // Logout button
    const logoutBtn = document.getElementById('logoutBtn');
    const logoutBtnMobile = document.getElementById('logoutBtnMobile');
    
    if (logoutBtn) {
      logoutBtn.addEventListener('click', (e) => {
        e.preventDefault();
        this.logout();
      });
    }
    
    if (logoutBtnMobile) {
      logoutBtnMobile.addEventListener('click', (e) => {
        e.preventDefault();
        this.logout();
      });
    }

    // Navigation tabs
    document.querySelectorAll('.nav-btn').forEach(item => {
      item.addEventListener('click', (e) => {
        e.preventDefault();
        const tab = e.currentTarget.dataset.tab;
        this.showTab(tab);
      });
    });

    // Search functionality
    const userSearch = document.getElementById('userSearch');
    if (userSearch) {
      userSearch.addEventListener('input', (e) => {
        this.filterUsers(e.target.value);
      });
    }

    const bookSearch = document.getElementById('bookSearch');
    if (bookSearch) {
      bookSearch.addEventListener('input', (e) => {
        this.filterBooks(e.target.value);
      });
    }

    const transactionSearch = document.getElementById('transactionSearch');
    if (transactionSearch) {
      transactionSearch.addEventListener('input', (e) => {
        this.filterTransactions(e.target.value);
      });
    }

    // Filter dropdowns
    const transactionFilter = document.getElementById('transactionFilter');
    if (transactionFilter) {
      transactionFilter.addEventListener('change', (e) => {
        this.filterTransactionsByStatus(e.target.value);
      });
    }

    // Add buttons
    const addBookBtn = document.getElementById('addBookBtn');
    if (addBookBtn) {
      addBookBtn.addEventListener('click', () => {
        this.showAddBookModal();
      });
    }

    const addUserBtn = document.getElementById('addUserBtn');
    if (addUserBtn) {
      addUserBtn.addEventListener('click', () => {
        this.showAddUserModal();
      });
    }

    // Send reminder button
    const sendReminderBtn = document.getElementById('sendReminderBtn');
    if (sendReminderBtn) {
      sendReminderBtn.addEventListener('click', () => {
        this.sendReminder();
      });
    }

    // Form submissions
    const editUserForm = document.getElementById('editUserForm');
    if (editUserForm) {
      editUserForm.addEventListener('submit', (e) => {
        e.preventDefault();
        this.saveUserChanges();
      });
    }

    const addBookForm = document.getElementById('addBookForm');
    if (addBookForm) {
      addBookForm.addEventListener('submit', (e) => {
        e.preventDefault();
        this.addNewBook();
      });
    }

    const addUserForm = document.getElementById('addUserForm');
    if (addUserForm) {
      addUserForm.addEventListener('submit', (e) => {
        e.preventDefault();
        this.addNewUser();
      });
    }

    this.bindModalEvents();
  }

  bindModalEvents() {
    document.querySelectorAll('.modal-close').forEach(btn => {
      btn.addEventListener('click', (e) => {
        this.closeModal(e.target.closest('.modal'));
      });
    });

    document.getElementById('cancelEditUser')?.addEventListener('click', () => {
      this.closeModal(document.getElementById('editUserModal'));
    });

    document.getElementById('saveEditUser')?.addEventListener('click', () => {
      this.saveUserChanges();
    });

    document.getElementById('cancelAddBook')?.addEventListener('click', () => {
      this.closeModal(document.getElementById('addBookModal'));
    });

    document.getElementById('saveAddBook')?.addEventListener('click', () => {
      this.addNewBook();
    });

    document.getElementById('cancelAddUser')?.addEventListener('click', () => {
      this.closeModal(document.getElementById('addUserModal'));
    });

    document.getElementById('saveAddUser')?.addEventListener('click', () => {
      this.addNewUser();
    });

    document.getElementById('cancelEditBook')?.addEventListener('click', () => {
      this.closeModal(document.getElementById('editBookModal'));
    });

    document.getElementById('saveEditBook')?.addEventListener('click', () => {
      this.saveBookChanges();
    });

    document.getElementById('cancelEditBorrowing')?.addEventListener('click', () => {
      this.closeModal(document.getElementById('editBorrowingModal'));
    });

    document.getElementById('saveEditBorrowing')?.addEventListener('click', () => {
      this.saveBorrowingChanges();
    });
  }

  logout() {
    try { 
      localStorage.removeItem('vw_signed_in');
      localStorage.removeItem('vw_is_admin');
      localStorage.removeItem('vw_user_id');
      localStorage.removeItem('vw_user_email');
      localStorage.removeItem('vw_user_name');
    } catch {}
    window.location.href = './auth.html';
  }

  showTab(tabName) {
    this.currentTab = tabName;
    
    // Updated selectors for new HTML structure
    document.querySelectorAll('.nav-btn').forEach(item => {
      item.classList.remove('active');
    });
    
    document.querySelectorAll('.admin-tab').forEach(content => {
      content.classList.remove('active');
    });

    document.querySelector(`[data-tab="${tabName}"]`)?.classList.add('active');
    document.getElementById(`${tabName}-tab`)?.classList.add('active');

    switch(tabName) {
      case 'dashboard':
        // Refresh data before loading dashboard to ensure accurate counts
        this.loadData().then(() => {
          this.loadDashboard();
        }).catch(error => {
          console.error('Error loading data:', error);
          this.loadDashboard(); // Load dashboard anyway
        });
        break;
      case 'users':
        this.loadUsers();
        break;
      case 'books':
        this.loadBooks();
        break;
      case 'transactions':
        this.loadTransactions();
        break;
    }
  }

  loadDashboard() {
    // Use stats from API if available, otherwise fallback to calculations
    const stats = this.stats || {};
    const totalUsers = stats.total_users || this.users.length;
    const totalBooks = stats.total_books || this.books.length;
    
    // Calculate real-time borrowed count (only active borrowings)
    const activeBorrowings = this.borrowedBooks.filter(b => {
      // A borrowing is active if status is 'borrowed' AND no return date
      const hasReturnDate = b.returned_at && b.returned_at !== null && b.returned_at !== '';
      const isActive = b.status === 'borrowed' && !hasReturnDate;
      
      console.log('Checking borrowing:', {
        id: b.id,
        status: b.status,
        returned_at: b.returned_at,
        hasReturnDate: hasReturnDate,
        isActive: isActive
      });
      return isActive;
    });
    
    // Always use real-time calculation instead of possibly stale stats
    const borrowedCount = activeBorrowings.length;
    
    console.log('Active borrowings calculation:', {
      totalBorrowings: this.borrowedBooks.length,
      activeBorrowingsCount: activeBorrowings.length,
      finalBorrowedCount: borrowedCount,
      sampleBorrowing: this.borrowedBooks[0] || 'No borrowings'
    });
    
    // Calculate overdue books based on current date
    const now = new Date();
    const overdueBooks = this.borrowedBooks.filter(b => {
      if (b.status !== 'borrowed' || b.returned_at) return false;
      const dueDate = new Date(b.due_date);
      return now > dueDate;
    });
    const overdue = stats.overdue_books || overdueBooks.length;
    
    console.log('Dashboard stats:', {
      totalUsers, totalBooks, borrowedCount, overdue,
      activeBorrowings: activeBorrowings.length,
      totalBorrowings: this.borrowedBooks.length
    });

    const totalUsersEl = document.getElementById('totalUsers');
    const totalBooksEl = document.getElementById('totalBooks');
    const borrowedBooksEl = document.getElementById('borrowedBooks');
    const overdueBooksEl = document.getElementById('overdueBooks');

    if (totalUsersEl) totalUsersEl.textContent = totalUsers;
    if (totalBooksEl) totalBooksEl.textContent = totalBooks;
    if (borrowedBooksEl) borrowedBooksEl.textContent = borrowedCount;
    if (overdueBooksEl) overdueBooksEl.textContent = overdue;

    // Load recent activity
    this.loadRecentActivity();
    
    // Load overdue alerts
    this.loadOverdueAlerts();

    // Add animation effect to stats
    [totalUsersEl, totalBooksEl, borrowedBooksEl, overdueBooksEl].forEach(el => {
      if (el) {
        el.style.transform = 'scale(1.1)';
        setTimeout(() => el.style.transform = 'scale(1)', 200);
      }
    });
  }



  loadRecentActivity() {
    const recentActivityEl = document.getElementById('recentActivity');
    if (!recentActivityEl) return;

    const activities = this.stats.recent_activity || [];
    
    if (activities.length === 0) {
      recentActivityEl.innerHTML = '<p class="no-data">No recent activity</p>';
      return;
    }

    recentActivityEl.innerHTML = activities.map(activity => {
      const date = new Date(activity.created_at).toLocaleDateString();
      const action = activity.status === 'returned' ? 'returned' : 'borrowed';
      return `
        <div class="activity-item">
          <div class="activity-info">
            <strong>${activity.user_name}</strong> ${action}
            <span class="book-title">"${activity.book_title}"</span>
          </div>
          <div class="activity-date">${date}</div>
        </div>
      `;
    }).join('');
  }

  loadOverdueAlerts() {
    const overdueAlertsEl = document.getElementById('overdueAlerts');
    if (!overdueAlertsEl) return;

    const overdueBooks = this.stats.overdue_details || [];
    
    if (overdueBooks.length === 0) {
      overdueAlertsEl.innerHTML = '<p class="no-data">No overdue books</p>';
      return;
    }

    overdueAlertsEl.innerHTML = overdueBooks.map(book => `
      <div class="alert-item overdue">
        <div class="alert-info">
          <strong>${book.user_name}</strong> - "${book.book_title}"
          <br><small>${book.user_email}</small>
        </div>
        <div class="alert-meta">
          <span class="days-overdue">${book.days_overdue} days overdue</span>
          <button class="btn btn-sm btn-accent" onclick="adminPanel.returnBookFromAlert(${book.id})">
            Mark Returned
          </button>
        </div>
      </div>
    `).join('');
  }

  async returnBookFromAlert(borrowingId) {
    try {
      const response = await fetch('./admin_backend.php?action=borrowings', {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({
          borrowing_id: borrowingId
        })
      });
      
      const data = await response.json();
      
      if (data.message) {
        alert('Book marked as returned successfully!');
        await this.loadData(); // Refresh data
        this.loadDashboard(); // Refresh dashboard
      } else {
        throw new Error(data.error || 'Failed to return book');
      }
    } catch (error) {
      console.error('Error returning book:', error);
      alert('Failed to return book: ' + error.message);
    }
  }

  loadUsers() {
    this.renderUsers(this.users);
  }

  renderUsers(users) {
    const usersList = document.getElementById('usersList');
    if (!usersList) return;
    
    if (users.length === 0) {
      usersList.innerHTML = '<p class="muted text-center">No users found</p>';
      return;
    }

    usersList.innerHTML = users.map(user => {
      const borrowedCount = this.borrowedBooks.filter(b => {
        return (b.user_id == user.id || b.userId == user.id) && 
               (b.status === 'borrowed' || (!b.returned && !b.return_date));
      }).length;
      return `
        <div class="user-item" data-user-id="${user.id}" onclick="adminPanel.selectUser('${user.id}')">
          <div class="user-info">
            <div class="user-name">${user.name}</div>
            <div class="user-email">${user.email}</div>
            <div class="user-meta">
              <span class="status ${user.is_active ? 'active' : 'suspended'}">${user.is_active ? 'active' : 'suspended'}</span>
              <span class="borrowed-count">${borrowedCount} borrowed</span>
            </div>
          </div>
        </div>
      `;
    }).join('');
  }

  selectUser(userId) {
    const user = this.users.find(u => u.id == userId);
    if (!user) {
      console.log('User not found:', userId);
      return;
    }

    console.log('Selecting user:', user);

    // Update selected state
    document.querySelectorAll('.user-item').forEach(item => {
      item.classList.remove('selected');
    });
    const userElement = document.querySelector(`[data-user-id="${userId}"]`);
    if (userElement) {
      userElement.classList.add('selected');
    }

    // Show user details
    const userInfo = document.getElementById('userInfo');
    const noUserSelected = document.getElementById('noUserSelected');
    
    if (userInfo) userInfo.style.display = 'block';
    if (noUserSelected) noUserSelected.style.display = 'none';

    // Update user info if elements exist
    const nameEl = document.getElementById('selectedUserName');
    const emailEl = document.getElementById('selectedUserEmail');
    const joinedEl = document.getElementById('selectedUserJoined');
    
    if (nameEl) nameEl.textContent = user.name;
    if (emailEl) emailEl.textContent = user.email;
    if (joinedEl) {
      const joinDate = user.created_at || user.joinDate || new Date().toISOString();
      joinedEl.textContent = `Joined: ${new Date(joinDate).toLocaleDateString()}`;
    }

    // Load user's borrowed books
    this.loadUserBorrowedBooks(userId);

    // Set up action buttons
    const editBtn = document.getElementById('editUserBtn');
    const suspendBtn = document.getElementById('suspendUserBtn');
    
    if (editBtn) {
      editBtn.onclick = () => this.editUser(userId);
    }
    if (suspendBtn) {
      suspendBtn.onclick = () => this.toggleUserStatus(userId);
      const currentStatus = user.is_active ? 'active' : 'suspended';
      suspendBtn.textContent = currentStatus === 'suspended' ? 'Activate User' : 'Suspend User';
    }
  }

  loadUserBorrowedBooks(userId) {
    const container = document.getElementById('userBorrowedBooks');
    const userBooks = this.borrowedBooks.filter(b => b.user_id == userId && b.status === 'borrowed');

    if (userBooks.length === 0) {
      container.innerHTML = '<p class="muted">No currently borrowed books</p>';
      return;
    }

    container.innerHTML = userBooks.map(book => {
      const isOverdue = book.display_status === 'overdue';
      const dueDate = new Date(book.due_date);
      let authors = [];
      try {
        authors = JSON.parse(book.authors || '[]');
      } catch {
        authors = book.authors ? [book.authors] : ['Unknown Author'];
      }
      
      return `
        <div class="borrowed-book-item ${isOverdue ? 'overdue' : ''}">
          <div class="book-cover-small">
            ${book.cover_url 
              ? `<img src="${book.cover_url}" alt="Cover" loading="lazy" />`
              : `<div class="book-placeholder-small">📖</div>`
            }
          </div>
          <div class="book-details">
            <div class="book-title">${book.book_title}</div>
            <div class="book-authors">By ${authors.join(', ')}</div>
            <div class="book-meta">
              <span>Due: ${dueDate.toLocaleDateString()}</span>
              <span class="status ${isOverdue ? 'overdue' : 'borrowed'}">
                ${isOverdue ? `Overdue (${book.days_overdue} days)` : 'Borrowed'}
              </span>
            </div>
          </div>
          <div class="book-actions">
            <button class="btn btn-small btn-accent" onclick="adminPanel.returnBookFromUser(${book.id})">
              Mark Returned
            </button>
          </div>
        </div>
      `;
    }).join('');
  }

  async returnBookFromUser(borrowingId) {
    if (!confirm('Mark this book as returned?')) return;
    
    try {
      const response = await fetch('./admin_backend.php?action=borrowings', {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({
          borrowing_id: borrowingId
        })
      });
      
      const data = await response.json();
      
      if (data.message) {
        alert('Book returned successfully!');
        
        // Refresh data and update display
        await this.loadData();
        const selectedUser = document.querySelector('.user-item.selected');
        if (selectedUser) {
          const userId = selectedUser.dataset.userId;
          this.loadUserBorrowedBooks(userId);
        }
        this.loadDashboard(); // Update dashboard stats
      } else {
        throw new Error(data.error || 'Failed to return book');
      }
      
    } catch (error) {
      console.error('Error returning book:', error);
      alert('Failed to return book: ' + error.message);
    }
  }

  async toggleUserStatus(userId) {
    console.log('=== TOGGLE USER STATUS START ===');
    console.log('toggleUserStatus called with userId:', userId);
    console.log('Current users array:', this.users);
    
    const user = this.users.find(u => u.id == userId); // Use == for type coercion
    console.log('Found user:', user);
    
    if (!user) {
      console.error('User not found with ID:', userId);
      console.error('Available user IDs:', this.users.map(u => ({id: u.id, type: typeof u.id})));
      alert('User not found. Please refresh the page and try again.');
      return;
    }

    // Convert is_active to status for logic
    const currentStatus = user.is_active ? 'active' : 'suspended';
    const newStatus = currentStatus === 'suspended' ? 'active' : 'suspended';
    
    console.log('User status toggle:', {
      userId,
      userIdType: typeof userId,
      currentIsActive: user.is_active,
      currentStatus,
      newStatus
    });
    
    try {
      const formData = new FormData();
      formData.append('action', 'update_user_status');
      formData.append('user_id', userId);
      formData.append('status', newStatus);
      
      console.log('Sending API request to update user status...');
      console.log('FormData contents:');
      for (let [key, value] of formData.entries()) {
        console.log(`  ${key}: ${value}`);
      }
      
      const response = await fetch('api.php', {
        method: 'POST',
        body: formData
      });
      
      console.log('API response status:', response.status);
      console.log('API response headers:', Object.fromEntries(response.headers.entries()));
      
      const responseText = await response.text();
      console.log('Raw API response:', responseText);
      
      let result;
      try {
        result = JSON.parse(responseText);
        console.log('Parsed API response data:', result);
      } catch (parseError) {
        console.error('Failed to parse JSON response:', parseError);
        console.error('Response was:', responseText);
        throw new Error('Invalid JSON response from server');
      }
      
      if (result.success) {
        alert(`User ${newStatus === 'suspended' ? 'suspended' : 'activated'} successfully!`);
        
        // Reload the page to reflect changes
        console.log('Reloading page to reflect status changes...');
        location.reload();
      } else {
        console.error('API call failed:', result);
        alert('Failed to update user status: ' + (result.message || result.error || 'Unknown error'));
      }
    } catch (error) {
      console.error('=== ERROR IN TOGGLE USER STATUS ===');
      console.error('Error updating user status:', error);
      console.error('Error stack:', error.stack);
      alert('Failed to update user status. Please try again. Error: ' + error.message);
    } finally {
      console.log('=== TOGGLE USER STATUS END ===');
    }
  }

  filterUsers(query) {
    const filtered = this.users.filter(user => 
      user.name.toLowerCase().includes(query.toLowerCase()) ||
      user.email.toLowerCase().includes(query.toLowerCase())
    );
    this.renderUsers(filtered);
  }

  loadBooks() {
    this.renderBooks(this.books);
  }

  renderBooks(books) {
    const tbody = document.querySelector('#booksTableBody');
    if (!tbody) return;
    
    if (books.length === 0) {
      tbody.innerHTML = '<tr><td colspan="5" class="text-center">No books found</td></tr>';
      return;
    }

    tbody.innerHTML = books.map(book => {
      const title = book.title || book.book_title || 'Untitled';
      const authors = (() => {
        if (book.authors && typeof book.authors === 'string') {
          try { return JSON.parse(book.authors); } catch { return [book.authors]; }
        }
        if (book.authors && Array.isArray(book.authors)) return book.authors;
        if (book.author) return Array.isArray(book.author) ? book.author : [book.author];
        return ['Unknown Author'];
      })();
  const available = (book.available_copies ?? book.availableCopies ?? book.available) || 0;
  const total = (book.total_copies ?? book.totalCopies ?? book.total) || 0;
      return `
      <tr>
        <td>${title}</td>
        <td>${authors.join(', ')}</td>
        <td>${available}</td>
        <td>${total}</td>
        <td>
          <button class="btn btn-small btn-ghost" onclick="adminPanel.editBook('${book.id}')">Edit</button>
          <button class="btn btn-small btn-danger" onclick="adminPanel.deleteBook('${book.id}')">Delete</button>
        </td>
      </tr>
    `; }).join('');
  }

  filterBooks(query) {
    if (!query.trim()) {
      this.renderBooks(this.books);
      return;
    }
    
    const filtered = this.books.filter(book => {
      const title = (book.title || '').toLowerCase();
      const searchQuery = query.toLowerCase();
      
      // Handle both single author string and author array
      let authorMatch = false;
      if (book.author) {
        if (Array.isArray(book.author)) {
          authorMatch = book.author.some(author => 
            (author || '').toLowerCase().includes(searchQuery)
          );
        } else {
          authorMatch = (book.author || '').toLowerCase().includes(searchQuery);
        }
      }
      
      const isbnMatch = book.isbn && book.isbn.includes(query);
      
      return title.includes(searchQuery) || authorMatch || isbnMatch;
    });
    this.renderBooks(filtered);
  }

  filterUsers(query) {
    if (!query.trim()) {
      this.renderUsers(this.users);
      return;
    }

    const filtered = this.users.filter(user => 
      user.name.toLowerCase().includes(query.toLowerCase()) ||
      user.email.toLowerCase().includes(query.toLowerCase()) ||
      (user.phone && user.phone.includes(query))
    );
    this.renderUsers(filtered);
  }

  loadTransactions() {
    this.renderTransactions(this.borrowedBooks);
  }

  renderTransactions(transactions) {
    const tbody = document.querySelector('#transactionsTableBody');
    if (!tbody) return;
    
    if (transactions.length === 0) {
      tbody.innerHTML = '<tr><td colspan="6" class="text-center">No transactions found</td></tr>';
      return;
    }

    tbody.innerHTML = transactions.map(transaction => {
      // Use the correct field names from the API response
      const userName = transaction.user_name || 'Unknown User';
      const bookTitle = transaction.book_title || 'Unknown Book';
      const borrowedDate = transaction.borrowed_at || transaction.borrow_date;
      const dueDate = transaction.due_date;
      const returnedDate = transaction.returned_at || transaction.return_date;
      const status = transaction.display_status || transaction.status || 'borrowed';
      
      // Format dates properly
      const borrowedDateStr = borrowedDate ? new Date(borrowedDate).toLocaleDateString() : 'N/A';
      const dueDateStr = dueDate ? new Date(dueDate).toLocaleDateString() : 'N/A';
      const returnedDateStr = returnedDate ? new Date(returnedDate).toLocaleDateString() : '';
      
      return `
        <tr>
          <td>${userName}</td>
          <td>${bookTitle}</td>
          <td>${borrowedDateStr}</td>
          <td>${dueDateStr}</td>
          <td>
            <span class="status ${status}">${status.charAt(0).toUpperCase() + status.slice(1)}</span>
          </td>
          <td>
            <div class="book-actions">
              <button class="btn btn-small" onclick="adminPanel.editBorrowing('${transaction.id}')" title="Edit Borrowing Details">
                ✏️ Edit
              </button>
              ${status === 'borrowed' || status === 'overdue' ? 
                `<button class="btn btn-small btn-accent" onclick="adminPanel.returnBook('${transaction.id}')" title="Mark as Returned">Return</button>` :
                ''
              }
            </div>
          </td>
        </tr>
      `;
    }).join('');
  }

  filterTransactions(query) {
    if (!query.trim()) {
      this.renderTransactions(this.borrowedBooks);
      return;
    }

    const filtered = this.borrowedBooks.filter(transaction => {
      const userName = (transaction.user_name || '').toLowerCase();
      const bookTitle = (transaction.book_title || '').toLowerCase();
      const userEmail = (transaction.user_email || transaction.email || '').toLowerCase();
      const searchQuery = query.toLowerCase();
      
      return userName.includes(searchQuery) || 
             bookTitle.includes(searchQuery) || 
             userEmail.includes(searchQuery) ||
             (transaction.id && transaction.id.toString().includes(query));
    });
    this.renderTransactions(filtered);
  }

  filterTransactionsByStatus(status) {
    let filtered = this.borrowedBooks;
    
    if (status === 'borrowed') {
      filtered = this.borrowedBooks.filter(t => t.status === 'borrowed' && t.display_status !== 'overdue');
    } else if (status === 'overdue') {
      filtered = this.borrowedBooks.filter(t => t.display_status === 'overdue');
    } else if (status === 'returned') {
      filtered = this.borrowedBooks.filter(t => t.status === 'returned');
    }
    
    this.renderTransactions(filtered);
  }

  showAddUserModal() {
    const form = document.getElementById('addUserForm');
    if (form) form.reset();
    
    const modal = document.getElementById('addUserModal');
    if (modal) modal.hidden = false;
  }

  async addNewUser() {
    const name = document.getElementById('addUserName')?.value;
    const email = document.getElementById('addUserEmail')?.value;
    const password = document.getElementById('addUserPassword')?.value;
    const status = document.getElementById('addUserStatus')?.value;

    if (!name || !email || !password) {
      alert('Please fill in all required fields');
      return;
    }

    // Email validation
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
      alert('Please enter a valid email address');
      return;
    }

    try {
      const response = await fetch('./admin_backend.php?action=users', {
        method: 'POST',
        credentials: 'include',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({
          name: name,
          email: email,
          password: password,
          phone: '',
          is_active: status === 'active' ? 1 : 0
        })
      });

      const data = await response.json();

      if (data.message) {
        alert('User added successfully!');
        const addUserModal = document.getElementById('addUserModal');
        const addUserForm = document.getElementById('addUserForm');
        
        if (addUserModal) addUserModal.setAttribute('hidden', '');
        if (addUserForm) addUserForm.reset();
        
        // Reload data using class methods
        await this.loadData();
        this.loadUsers();
        this.loadDashboard();
      } else {
        alert(data.error || 'Failed to add user');
      }
    } catch (error) {
      console.error('Error adding user:', error);
      alert('An error occurred while adding the user: ' + error.message);
    }
  }

  editUser(userId) {
    const user = this.users.find(u => u.id == userId);
    if (!user) return;

    document.getElementById('editUserName').value = user.name;
    document.getElementById('editUserEmail').value = user.email;
    document.getElementById('editUserStatus').value = user.is_active ? 'active' : 'suspended';
    
    const modal = document.getElementById('editUserModal');
    modal.dataset.userId = userId;
    modal.hidden = false;
  }

  async saveUserChanges() {
    console.log('=== SAVE USER CHANGES START ===');
    const modal = document.getElementById('editUserModal');
    const userId = modal.dataset.userId;
    const name = document.getElementById('editUserName').value;
    const email = document.getElementById('editUserEmail').value;
    const status = document.getElementById('editUserStatus').value;
    
    console.log('Edit user data:', {userId, name, email, status});

    if (!name || !email) {
      alert('Please provide name and email');
      return;
    }

    try {
      const response = await fetch('./admin_backend.php?action=users', {
        method: 'PUT',
        credentials: 'include',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({
          id: userId,
          name: name,
          email: email,
          is_active: status === 'active' ? 1 : 0
        })
      });

      const result = await response.json();
      
      if (result.message) {
        this.closeModal(modal);
        await this.loadData();
        this.loadUsers();
        alert('User updated successfully!');
      } else {
        console.error('Edit user API failed:', result);
        throw new Error(result.error || 'Failed to update user');
      }
    } catch (error) {
      console.error('=== ERROR IN SAVE USER CHANGES ===');
      console.error('Error updating user:', error);
      console.error('Error stack:', error.stack);
      alert('Failed to update user: ' + error.message);
    } finally {
      console.log('=== SAVE USER CHANGES END ===');
    }
  }

  deleteUser(userId) {
    if (confirm('Are you sure you want to delete this user?')) {
      this.users = this.users.filter(u => u.id != userId);
      localStorage.setItem('users', JSON.stringify(this.users));
      this.loadUsers();
      alert('User deleted successfully!');
    }
  }

  showAddBookModal() {
    const form = document.getElementById('addBookForm');
    if (form) form.reset();
    
    const modal = document.getElementById('addBookModal');
    if (modal) modal.hidden = false;
  }

  async addNewBook() {
    const title = document.getElementById('addBookTitle')?.value;
    const author = document.getElementById('addBookAuthor')?.value;
    const isbn = document.getElementById('addBookISBN')?.value;
    const copies = parseInt(document.getElementById('addBookCopies')?.value || '1');
    const description = document.getElementById('addBookDescription')?.value;

    if (!title || !author) {
      alert('Please fill in title and author fields');
      return;
    }

    try {
      const response = await fetch('./admin_backend.php?action=books', {
        method: 'POST',
        credentials: 'include',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({
          title: title,
          authors: [author],
          isbn: isbn || null,
          total_copies: copies,
          available_copies: copies,
          description: description || null
        })
      });
      
      const data = await response.json();
      
      if (data.message) {
        this.closeModal(document.getElementById('addBookModal'));
        await this.loadData(); // Reload data from database
        this.loadBooks();
        alert('Book added successfully!');
      } else {
        throw new Error(data.error || 'Failed to add book');
      }
    } catch (error) {
      console.error('Error adding book:', error);
      alert('Failed to add book: ' + error.message);
    }
  }

  editBook(bookId) {
    const book = this.books.find(b => b.id == bookId);
    if (!book) {
      alert('Book not found');
      return;
    }

    // Fill the edit form with book data
    document.getElementById('editBookTitle').value = book.title || book.book_title || '';
    document.getElementById('editBookAuthor').value = book.author || book.book_author || '';
    document.getElementById('editBookISBN').value = book.isbn || '';
    document.getElementById('editBookCopies').value = book.total_copies || book.totalCopies || 1;
    document.getElementById('editBookDescription').value = book.description || '';
    
    const modal = document.getElementById('editBookModal');
    modal.dataset.bookId = bookId;
    modal.hidden = false;
  }

  async saveBookChanges() {
    const modal = document.getElementById('editBookModal');
    const bookId = modal.dataset.bookId;
    const title = document.getElementById('editBookTitle').value;
    const author = document.getElementById('editBookAuthor').value;
    const isbn = document.getElementById('editBookISBN').value;
    const copies = parseInt(document.getElementById('editBookCopies').value || '1');
    const description = document.getElementById('editBookDescription').value;

    if (!title || !author) {
      alert('Please fill in title and author fields');
      return;
    }

    try {
      const response = await fetch('./admin_backend.php?action=books', {
        method: 'PUT',
        credentials: 'include',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({
          id: bookId,
          title: title,
          isbn: isbn || null,
          total_copies: copies,
          description: description || null
        })
      });
      
      const data = await response.json();
      
      if (data.message) {
        this.closeModal(modal);
        await this.loadData();
        this.loadBooks();
        alert('Book updated successfully!');
      } else {
        throw new Error(data.error || 'Failed to update book');
      }
    } catch (error) {
      console.error('Error updating book:', error);
      alert('Failed to update book: ' + error.message);
    }
  }

  async updateBookInDatabase(bookId, bookData) {
    try {
      const response = await updateBookAdmin(bookId, bookData);
      if (response.success) {
        await this.loadData(); // Reload data from database
        this.loadBooks();
        alert('Book updated successfully!');
      } else {
        throw new Error(response.message || 'Failed to update book');
      }
    } catch (error) {
      console.error('Error updating book:', error);
      alert('Failed to update book: ' + error.message);
    }
  }

  async deleteBook(bookId) {
    if (confirm('Are you sure you want to delete this book?')) {
      try {
        const response = await fetch('./admin_backend.php?action=books', {
          method: 'DELETE',
          credentials: 'include',
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify({
            id: bookId
          })
        });
        
        const data = await response.json();
        
        if (data.message) {
          await this.loadData();
          this.loadBooks();
          alert('Book deleted successfully!');
        } else {
          throw new Error(data.error || 'Failed to delete book');
        }
      } catch (error) {
        console.error('Error deleting book:', error);
        alert('Failed to delete book: ' + error.message);
      }
    }
  }

  async sendReminder() {
    const selectedUser = document.querySelector('.user-item.selected');
    if (!selectedUser) {
      alert('Please select a user first to send a reminder.');
      return;
    }

    const userId = selectedUser.dataset.userId;
    const user = this.users.find(u => u.id == userId);
    
    if (!user) {
      alert('User not found.');
      return;
    }

    // Get overdue books for this user
    const overdueBooks = this.borrowedBooks.filter(book => {
      if (book.returned || book.return_date) return false;
      if ((book.user_id != userId && book.userId != userId)) return false;
      
      const dueDate = new Date(book.due_date);
      return dueDate < new Date();
    });

    if (overdueBooks.length === 0) {
      alert(`${user.name} has no overdue books.`);
      return;
    }

    if (!confirm(`Send reminder to ${user.name} for ${overdueBooks.length} overdue book(s)?`)) {
      return;
    }

    try {
      const response = await fetch('api.php/send-reminder', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          user_id: userId,
          user_email: user.email,
          user_name: user.name,
          overdue_books: overdueBooks.map(book => ({
            id: book.id,
            title: book.book_title || book.title,
            due_date: book.due_date
          }))
        })
      });

      const result = await response.json();
      
      if (result.success) {
        alert(`Reminder sent successfully to ${user.name}!`);
        
        // Disable button temporarily
        const sendBtn = document.getElementById('sendReminderBtn');
        if (sendBtn) {
          sendBtn.disabled = true;
          sendBtn.textContent = 'Sent ✓';
          setTimeout(() => {
            sendBtn.disabled = false;
            sendBtn.textContent = 'Send Reminder';
          }, 3000);
        }
      } else {
        throw new Error(result.message || 'Failed to send reminder');
      }
    } catch (error) {
      console.error('Error sending reminder:', error);
      alert('Failed to send reminder: ' + error.message);
    }
  }

  async returnBook(transactionId) {
    if (!confirm('Mark this book as returned?')) return;
    
    try {
      const response = await fetch('./admin_backend.php?action=borrowings', {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({
          borrowing_id: transactionId
        })
      });
      
      const data = await response.json();
      
      if (data.message) {
        // Refresh all data
        await this.loadData();
        this.loadTransactions();
        this.loadDashboard();
        alert('Book returned successfully!');
      } else if (response.error) {
        throw new Error(response.error);
      } else {
        throw new Error('Failed to return book');
      }
    } catch (error) {
      console.error('Error returning book:', error);
      alert('Failed to return book: ' + error.message);
    }
  }

  editBorrowing(transactionId) {
    const transaction = this.borrowedBooks.find(t => t.id == transactionId);
    if (!transaction) {
      alert('Transaction not found');
      return;
    }

    // Fill the edit form with transaction data
    document.getElementById('editBorrowingUser').value = transaction.user_name || 'Unknown User';
    document.getElementById('editBorrowingBook').value = transaction.book_title || 'Unknown Book';
    
    // Format dates for date inputs
    const borrowedDate = transaction.borrowed_at || transaction.borrow_date;
    if (borrowedDate) {
      document.getElementById('editBorrowingDate').value = new Date(borrowedDate).toISOString().split('T')[0];
    }
    
    if (transaction.due_date) {
      document.getElementById('editBorrowingDueDate').value = new Date(transaction.due_date).toISOString().split('T')[0];
    }
    
    document.getElementById('editBorrowingStatus').value = transaction.status || 'borrowed';
    
    // Set return date if returned
    const returnDate = transaction.returned_at || transaction.return_date;
    if (returnDate) {
      document.getElementById('editBorrowingReturnDate').value = new Date(returnDate).toISOString().split('T')[0];
    }
    
    document.getElementById('editBorrowingNotes').value = transaction.notes || '';
    
    // Store transaction ID for saving
    const modal = document.getElementById('editBorrowingModal');
    modal.dataset.transactionId = transactionId;
    modal.hidden = false;
  }

  async saveBorrowingChanges() {
    const modal = document.getElementById('editBorrowingModal');
    const transactionId = modal.dataset.transactionId;
    
    const borrowedDate = document.getElementById('editBorrowingDate').value;
    const dueDate = document.getElementById('editBorrowingDueDate').value;
    const status = document.getElementById('editBorrowingStatus').value;
    const returnDate = document.getElementById('editBorrowingReturnDate').value;
    const notes = document.getElementById('editBorrowingNotes').value;

    if (!borrowedDate || !dueDate) {
      alert('Please fill in both borrowed date and due date');
      return;
    }

    // Validate dates
    const borrowedDateObj = new Date(borrowedDate);
    const dueDateObj = new Date(dueDate);
    
    if (dueDateObj <= borrowedDateObj) {
      alert('Due date must be after borrowed date');
      return;
    }

    if (status === 'returned' && !returnDate) {
      alert('Please specify return date for returned books');
      return;
    }

    try {
      const updateData = {
        id: transactionId,
        borrowed_date: borrowedDate,
        due_date: dueDate,
        status: status,
        notes: notes
      };

      // Add return date if status is returned
      if (status === 'returned' && returnDate) {
        updateData.return_date = returnDate;
      }

      const response = await fetch('api.php/edit-borrowing', {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(updateData)
      });

      const result = await response.json();
      
      if (result.success) {
        this.closeModal(modal);
        await this.loadData();
        this.loadTransactions();
        this.loadDashboard();
        alert('Borrowing details updated successfully!');
      } else {
        throw new Error(result.message || 'Failed to update borrowing');
      }
    } catch (error) {
      console.error('Error updating borrowing:', error);
      alert('Failed to update borrowing: ' + error.message);
    }
  }

  isOverdue(dueDate) {
    return new Date(dueDate) < new Date();
  }

  closeModal(modal) {
    if (modal) {
      modal.hidden = true;
      // Clear form data
      const forms = modal.querySelectorAll('form');
      forms.forEach(form => form.reset());
    }
  }

  saveData() {
    localStorage.setItem('users', JSON.stringify(this.users));
    localStorage.setItem('books', JSON.stringify(this.books));
  }

  showTab(tabName) {
    console.log('Switching to tab:', tabName);
    
    // Hide all tabs
    document.querySelectorAll('.admin-tab').forEach(tab => {
      tab.style.display = 'none';
    });
    
    // Remove active class from all nav buttons
    document.querySelectorAll('.nav-btn').forEach(btn => {
      btn.classList.remove('active');
    });
    
    // Show selected tab
    const targetTab = document.getElementById(`${tabName}-tab`);
    if (targetTab) {
      targetTab.style.display = 'block';
    }
    
    // Add active class to selected nav button
    const activeBtn = document.querySelector(`[data-tab="${tabName}"]`);
    if (activeBtn) {
      activeBtn.classList.add('active');
    }
    
    this.currentTab = tabName;
    
    // Load tab-specific data
    switch (tabName) {
      case 'dashboard':
        this.loadDashboard();
        break;
      case 'users':
        this.loadUsers();
        break;
      case 'books':
        this.loadBooks();
        break;
      case 'transactions':
        this.loadTransactions();
        break;
    }
  }

  logout() {
    if (confirm('Are you sure you want to logout?')) {
      // Clear all stored data
      localStorage.removeItem('vw_signed_in');
      localStorage.removeItem('vw_is_admin');
      localStorage.removeItem('vw_user_id');
      localStorage.removeItem('vw_user_email');
      localStorage.removeItem('vw_user_name');
      localStorage.removeItem('vw_admin_role');
      
      // Redirect to login page
      window.location.href = './admin-login.html';
    }
  }

  showAddBookModal() {
    const modal = document.getElementById('addBookModal');
    if (modal) {
      const form = document.getElementById('addBookForm');
      if (form) form.reset();
      modal.hidden = false;
    }
  }

  showAddUserModal() {
    const form = document.getElementById('addUserForm');
    if (form) form.reset();
    
    const modal = document.getElementById('addUserModal');
    if (modal) modal.hidden = false;
  }
}

// Initialize admin panel when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
  console.log('DOM loaded, initializing admin panel...');
  window.adminPanel = new AdminPanel();
});

const YEAR_EL = document.getElementById('year');
if (YEAR_EL) YEAR_EL.textContent = new Date().getFullYear();