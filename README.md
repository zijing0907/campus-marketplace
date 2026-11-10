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
