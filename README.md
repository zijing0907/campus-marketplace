# Campus Marketplace

A web app where university students buy and sell used items (textbooks, electronics, hostel essentials) within their own campus.

**Status:** in development.

## Tech stack

PHP 8 (plain PHP, PDO) · MySQL · HTML, CSS, vanilla JavaScript

## Folder structure

| Folder | Contents |
|---|---|
| `config/` | Settings such as database credentials |
| `includes/` | Shared PHP: header, footer, helper functions |
| `api/` | PHP endpoints that return JSON |
| `pages/` | The site's pages |
| `assets/` | CSS, JavaScript, images |
| `uploads/` | Photos uploaded by users (not committed) |

## Run it locally

1. Copy `config/config.example.php` to `config/config.php` and fill in your database details.
2. Start PHP's built-in server from the project folder:

   ```bash
   php -S localhost:8000
   ```

3. Open http://localhost:8000 in your browser.
