# Campus Marketplace

A web app where university students buy and sell used items (textbooks, electronics, hostel essentials) within their own campus.

**Status:** in development.

## Features so far

- Register with a university email (only `@1utar.my` addresses are accepted), log in and log out
- Passwords stored as bcrypt hashes with `password_hash()`, never as plain text
- Sessions that use HttpOnly, SameSite cookies and get a new session ID at every login
- A CSRF token on every form, prepared statements for every query, and all output escaped
- Edit your profile: display name, faculty, phone and a profile photo
- Photo uploads are checked by their contents (JPG, PNG or WebP, up to 2 MB) and saved under random names
- Photos are shrunk in the browser before upload, so large phone photos upload quickly
- Sell an item: post a listing with a title, price, category, condition, meetup location and 1 to 5 photos
- Listing page with a photo gallery and the seller's details
- A seller's phone number and WhatsApp button are shown to logged-in students only, never to guests
- My listings: edit a listing and its photos, delete it, or mark it as sold
- Ownership is checked on the server for every edit, delete and status change, so a student can only change their own listings
- Marking a listing as sold uses a JSON API called with `fetch`, so the page updates without reloading
- Browse page with the newest listings, 12 per page
- Keyword search on title and description, filters for category, condition and price range, and sorting by newest or price
- Search and filters update the results without reloading the page, and still work with JavaScript turned off
- Messaging: a buyer messages the seller from a listing, with one conversation per buyer per listing
- Inbox with each conversation's latest message and unread count, and an unread badge in the header
- A conversation can be opened only by its buyer and its seller; anyone else gets "not found"
- Report a listing with a reason: scam, prohibited item, spam or something else
- Admin page for open reports: hide the listing or dismiss the report, and restore a listing hidden by mistake
- Admin pages check the user's role on the server; students get "access denied"
- Admin dashboard with totals: users, active listings, sold listings and open reports
- Admin users page: search, ban and unban. A banned user is logged out at once, cannot log in, and their listings disappear for other students
- Admin categories page: add, rename and remove. Removing a category moves its listings to "Others"

## Tech stack

PHP 8 (plain PHP, PDO) · MySQL · HTML, CSS, vanilla JavaScript

## Folder structure

| Folder | Contents |
|---|---|
| `config/` | Settings such as database credentials |
| `database/` | `schema.sql`, which creates all the tables |
| `includes/` | Shared PHP: header, footer, database connection, sessions and login, CSRF protection |
| `api/` | PHP endpoints that return JSON |
| `pages/` | The site's pages |
| `assets/` | CSS, JavaScript, images |
| `uploads/` | Photos uploaded by users (not committed) |

## Run it locally

1. Create the database and load the tables:

   ```bash
   mysql -u root -p -e "CREATE DATABASE campus_marketplace CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
   mysql -u root -p campus_marketplace < database/schema.sql
   ```

2. Copy `config/config.example.php` to `config/config.php` and fill in your database details.
3. Start PHP's built-in server from the project folder:

   ```bash
   php -S localhost:8000
   ```

4. Open http://localhost:8000 in your browser.

## Make an admin account

Every new account is a student. There is deliberately no page for becoming an admin: an admin is made directly in the database. Register normally, then run this with your own email (in phpMyAdmin's SQL tab, or the `mysql` command):

```sql
UPDATE users SET role = 'admin' WHERE email = 'you@1utar.my';
```

Log in again and an **Admin** link appears in the header.
