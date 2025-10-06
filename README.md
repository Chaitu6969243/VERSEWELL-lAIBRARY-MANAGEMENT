# Versewell Digital Library

A fully functional digital library website with user authentication, book browsing, borrowing system, and admin panel.

## Features

### 🔐 Authentication System
- User registration and login
- Admin login with special credentials
- Session management with localStorage
- Protected routes for admin and user areas

### 📚 Book Management
- Search and browse books using Google Books API
- Book details with covers, descriptions, and metadata
- Borrow functionality with due dates
- Return books system
- Reading list management

### 👤 User Features
- Personal profile management
- View borrowed books and due dates
- Track reading history
- Overdue notifications

### 🛠️ Admin Panel
- User management dashboard
- View all borrowed books
- Send reminders to users
- Mark books as returned
- System statistics

## Quick Setup

### 1. Database Setup
1. Open MySQL Workbench
2. Run the `database.sql` file to create the database and tables
3. The script will create:
   - `versewell_library` database
   - All necessary tables (users, books, borrowed_books, etc.)
   - Sample data and admin accounts

### 2. File Structure
```
versewell-library/
├── index.html          # Homepage
├── auth.html           # Login/Register page  
├── book.html           # Browse books page
├── profile.html        # User profile page
├── admin.html          # Admin dashboard
├── styles.css          # Main styles
├── auth.css           # Authentication styles
├── book.css           # Book browsing styles
├── profile.css        # Profile page styles
├── admin.css          # Admin panel styles
├── script.js          # Global JavaScript
├── auth.js            # Authentication logic
├── api.js             # Book API integration
├── book.js            # Book browsing functionality
├── profile.js         # Profile management
├── admin.js           # Admin panel logic
└── database.sql       # MySQL database schema
```

### 3. Running the Application
1. Open `index.html` in a web browser
2. Navigate between pages using the navigation menu
3. All functionality works client-side with localStorage

## User Accounts

### Regular Users
- Register new accounts through the signup form
- Login with email and password
- Access profile and book browsing features

### Admin Accounts
Use these pre-configured admin credentials:

**Admin 1:**
- Email: `bot@gmail.com`
- Password: `bot123`

**Admin 2:**  
- Email: `master@lib.com`
- Password: `master123`

## How to Use

### For Users:
1. **Register/Login**: Create account or login on auth.html
2. **Browse Books**: Visit book.html to search and browse books
3. **Borrow Books**: Click "Borrow Book" and select duration
4. **Manage Profile**: View borrowed books and due dates in profile.html
5. **Return Books**: Click "Return Book" in your profile

### For Admins:
1. **Admin Login**: Use admin credentials on auth.html
2. **Dashboard**: View system statistics and user management
3. **User Management**: Select users to view their borrowing history
4. **Send Reminders**: Notify users about due/overdue books
5. **Book Returns**: Mark books as returned in the system

## Database Schema

### Main Tables:
- **users**: User accounts and profiles
- **admins**: Administrator accounts  
- **books**: Book catalog with metadata
- **borrowed_books**: Borrowing transactions and history
- **user_sessions**: Login session management
- **book_reviews**: User ratings and reviews

### Key Features:
- Foreign key constraints maintain data integrity
- Triggers automatically update book availability
- Indexes optimize search performance
- Views provide simplified data access

## API Integration

### Google Books API
- Searches books by title, author, or keywords
- Fetches book metadata, covers, and descriptions
- Provides preview links and reading options
- Handles pagination for large result sets

### Local Storage
- User authentication state
- Borrowed books tracking
- User preferences and session data
- Offline functionality support

## Security Features

- Input validation and sanitization
- Protected admin routes
- Session timeout handling
- SQL injection prevention (prepared statements)
- XSS protection through proper escaping

## Browser Compatibility

- Modern browsers (Chrome, Firefox, Safari, Edge)
- Mobile responsive design
- Progressive enhancement
- Accessibility features (ARIA labels, keyboard navigation)

## Development Notes

### Code Structure:
- Modular JavaScript with clear separation of concerns
- CSS custom properties for consistent theming
- Semantic HTML with accessibility in mind
- No external frameworks - vanilla JavaScript

### Performance:
- Lazy loading for book images
- Infinite scroll for large book lists
- Debounced search to reduce API calls
- Optimized CSS with minimal reflows

## Troubleshooting

### Common Issues:

1. **Books not loading**: Check internet connection for Google Books API
2. **Login not working**: Clear browser localStorage and try again
3. **Admin access denied**: Ensure using correct admin credentials
4. **Database errors**: Verify MySQL connection and run database.sql

### Browser Console:
Enable developer tools to see detailed error messages and API responses.

## Future Enhancements

- Real backend integration with Node.js/PHP
- Email notifications for due dates
- Advanced search filters
- Book recommendations
- Reading progress tracking
- Multi-language support

---

**Created with ❤️ for book lovers everywhere**