// Lightweight local stub of API functions to remove network dependencies.
// These functions preserve the original names and return Promises with
// shapes the UI expects. They use localStorage for persistence where sensible.

// Simple in-memory cache (persisted to localStorage)
const _LS_KEY = 'vw_local_db_v1';
function _loadDB() {
  try {
    const raw = localStorage.getItem(_LS_KEY);
    if (!raw) return { users: [], books: [], borrowings: [] };
    return JSON.parse(raw);
  } catch (e) {
    return { users: [], books: [], borrowings: [] };
  }
}
function _saveDB(db) { localStorage.setItem(_LS_KEY, JSON.stringify(db)); }
function _ensureSampleData() {
  const db = _loadDB();
  if (db.users.length === 0) {
    db.users.push({ id: 1, name: 'Demo User', email: 'demo.user@example.com', phone: '', created_at: new Date().toISOString(), is_active: 1 });
  }
  if (db.books.length === 0) {
    db.books.push({ id: 1, title: 'Demo Book for Admin Panel', authors: JSON.stringify(['Demo Author']), isbn: '0000-0', cover_url: null, total_copies: 1, available_copies: 1, created_at: new Date().toISOString() });
  }
  if (db.borrowings.length === 0) {
    db.borrowings.push({ id: 1, user_id: 1, book_id: 1, borrowed_at: new Date().toISOString(), due_date: new Date(Date.now()+14*24*3600*1000).toISOString().split('T')[0], status: 'borrowed' });
  }
  _saveDB(db);
}
_ensureSampleData();

// Search books (stub -> searches local books)
async function searchBooks(query = '', offset = 0, limit = 12) {
  const db = _loadDB();
  const q = (query || '').toLowerCase();
  const items = db.books.filter(b => (b.title || '').toLowerCase().includes(q) || (b.isbn||'').includes(q));
  return { books: items.slice(offset, offset+limit).map(b => ({ id: b.id, title: b.title, authors: JSON.parse(b.authors||'[]'), cover: b.cover_url })), totalItems: items.length, hasMore: offset+limit < items.length };
}

async function getPopularBooks(offset = 0, limit = 12) { return await searchBooks('', offset, limit); }

// addBookToLibrary -> add to local DB
async function addBookToLibrary(bookData) {
  const db = _loadDB();
  const id = db.books.length ? Math.max(...db.books.map(b => b.id)) + 1 : 1;
  db.books.push({ id, title: bookData.title||'Untitled', authors: JSON.stringify(bookData.authors||[]), isbn: bookData.isbn||null, cover_url: bookData.cover||null, total_copies: bookData.total_copies||1, available_copies: bookData.available_copies||1, created_at: new Date().toISOString() });
  _saveDB(db);
  return { message: 'Book added to local library', id };
}

// User auth -> localStorage-stored "session"
function _getSession() { try { return JSON.parse(localStorage.getItem('vw_session')||'null'); } catch { return null; } }
function _setSession(obj) { localStorage.setItem('vw_session', JSON.stringify(obj)); }
function _clearSession() { localStorage.removeItem('vw_session'); }

async function loginUser(email, password) {
  // Accept any email/password in stub mode; create user if missing
  const db = _loadDB();
  let user = db.users.find(u => u.email === email);
  if (!user) {
    const id = db.users.length ? Math.max(...db.users.map(u=>u.id)) + 1 : 1;
    user = { id, name: email.split('@')[0], email, phone: '', created_at: new Date().toISOString(), is_active: 1 };
    db.users.push(user); _saveDB(db);
  }
  _setSession({ user_id: user.id, is_admin: 0 });
  return { message: 'Login successful (stub)', user };
}

async function registerUser(userData) {
  const db = _loadDB();
  if (db.users.find(u => u.email === userData.email)) return { error: 'Email exists' };
  const id = db.users.length ? Math.max(...db.users.map(u=>u.id)) + 1 : 1;
  const user = { id, name: userData.name, email: userData.email, phone: userData.phone||'', created_at: new Date().toISOString(), is_active: 1 };
  db.users.push(user); _saveDB(db); return { message: 'User created (stub)', id };
}

// Admin login (local mode) -> create admin session if email contains 'admin'
async function loginAdmin(email, password) {
  if (!email) throw new Error('Missing email');
  const db = _loadDB();
  let admin = db.users.find(u => u.email === email && (u.is_admin || email.includes('admin')));
  if (!admin) {
    // create an admin-like record in users for stub
    const id = db.users.length ? Math.max(...db.users.map(u=>u.id)) + 1 : 1;
    admin = { id, name: 'Admin', email, phone: '', created_at: new Date().toISOString(), is_active: 1, is_admin: 1 };
    db.users.push(admin); _saveDB(db);
  }
  _setSession({ admin_id: admin.id, is_admin: 1 });
  return { message: 'Admin login successful (stub)', admin };
}

async function getUsers() { const db=_loadDB(); return db.users; }

async function borrowBook(userId, bookId) {
  const db = _loadDB();
  const id = db.borrowings.length ? Math.max(...db.borrowings.map(b=>b.id)) + 1 : 1;
  db.borrowings.push({ id, user_id: userId, book_id: bookId, borrowed_at: new Date().toISOString(), due_date: new Date(Date.now()+14*24*3600*1000).toISOString().split('T')[0], status: 'borrowed' });
  _saveDB(db); return { message: 'Borrowed (stub)', id };
}

async function getBorrowings(userId=null) { const db=_loadDB(); return userId ? db.borrowings.filter(b=>b.user_id==userId) : db.borrowings; }
async function returnBorrowedBook(borrowingId) { const db=_loadDB(); const b=db.borrowings.find(x=>x.id==borrowingId); if (b) { b.status='returned'; b.returned_at=new Date().toISOString(); _saveDB(db); return { message:'Book returned (stub)' }; } throw new Error('Not found'); }

async function returnBookAdmin(borrowingId) { return await returnBorrowedBook(borrowingId); }

async function getStats() { const db=_loadDB(); return { total_users: db.users.length, total_books: db.books.length, total_borrowings: db.borrowings.length, active_borrowings: db.borrowings.filter(b=>b.status==='borrowed').length, overdue_books: db.borrowings.filter(b=>new Date(b.due_date) < new Date()).length } }

async function getAllUsersAdmin() { return await getUsers(); }
async function createUserAdmin(userData) { return await registerUser(userData); }
async function updateUserAdmin(userId, userData) { const db=_loadDB(); const u=db.users.find(x=>x.id==userId); if (!u) return { error:'not found' }; Object.assign(u,userData); _saveDB(db); return { message:'updated' }; }
async function deleteUserAdmin(userId) { const db=_loadDB(); const u=db.users.find(x=>x.id==userId); if (!u) return { error:'not found' }; u.is_active=0; _saveDB(db); return { message:'deactivated' }; }
async function getAllBorrowingsAdmin() { return await getBorrowings(); }
async function getAllBooksAdmin() { const db=_loadDB(); return db.books; }
async function addBookAdmin(bookData) { return await addBookToLibrary(bookData); }
async function updateBookAdmin(bookId, bookData) { const db=_loadDB(); const b=db.books.find(x=>x.id==bookId); if (!b) return { error:'not found' }; Object.assign(b,bookData); _saveDB(db); return { message:'updated' }; }
async function deleteBookAdmin(bookId) { const db=_loadDB(); const idx=db.books.findIndex(x=>x.id==bookId); if (idx===-1) return { error:'not found' }; db.books.splice(idx,1); _saveDB(db); return { message:'deleted' }; }

// Exports
window.searchBooks = searchBooks;
window.getPopularBooks = getPopularBooks;
window.addBookToLibrary = addBookToLibrary;
window.loginUser = loginUser;
window.registerUser = registerUser;
window.loginAdmin = loginAdmin;
window.getUsers = getUsers;
window.borrowBook = borrowBook;
window.getBorrowings = getBorrowings;
window.returnBorrowedBook = returnBorrowedBook;

// Admin functions
window.getStats = getStats;
window.getAllUsersAdmin = getAllUsersAdmin;
window.createUserAdmin = createUserAdmin;
window.updateUserAdmin = updateUserAdmin;
window.deleteUserAdmin = deleteUserAdmin;
window.getAllBorrowingsAdmin = getAllBorrowingsAdmin;
window.returnBookAdmin = returnBookAdmin;
window.getAllBooksAdmin = getAllBooksAdmin;
window.addBookAdmin = addBookAdmin;
window.updateBookAdmin = updateBookAdmin;
window.deleteBookAdmin = deleteBookAdmin;
