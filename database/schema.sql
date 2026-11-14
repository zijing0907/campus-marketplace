-- Campus Marketplace database schema (MySQL 8+).
--
-- Load it into an existing database:
--   mysql -u root -p campus_marketplace < database/schema.sql
--
-- Tables are created parents first, because a foreign key can only point
-- at a table that already exists.

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- People
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS users (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(100) NOT NULL,
    email         VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,          -- output of password_hash(), never the password
    faculty       VARCHAR(100) NULL,
    phone         VARCHAR(20)  NULL,
    avatar_path   VARCHAR(255) NULL,
    role          ENUM('student', 'admin') NOT NULL DEFAULT 'student',
    is_verified   TINYINT(1) NOT NULL DEFAULT 0,  -- set to 1 after the email link is clicked
    is_banned     TINYINT(1) NOT NULL DEFAULT 0,
    deleted_at    DATETIME NULL,                  -- set when the student deletes their account
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Things for sale
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS categories (
    id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,

    UNIQUE KEY uq_categories_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS listings (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    seller_id       INT UNSIGNED NOT NULL,
    buyer_id        INT UNSIGNED NULL,            -- filled in when the seller marks it sold;
                                                  -- only this user may leave a review
    category_id     INT UNSIGNED NOT NULL,
    title           VARCHAR(120) NOT NULL,
    description     TEXT NOT NULL,
    price           DECIMAL(8,2) NOT NULL,        -- DECIMAL, not FLOAT: money must be exact
    item_condition  ENUM('new', 'like_new', 'used') NOT NULL,
    meetup_location VARCHAR(120) NOT NULL,
    status          ENUM('available', 'sold', 'hidden') NOT NULL DEFAULT 'available',
    views           INT UNSIGNED NOT NULL DEFAULT 0,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_listings_seller   FOREIGN KEY (seller_id)   REFERENCES users (id),
    CONSTRAINT fk_listings_buyer    FOREIGN KEY (buyer_id)    REFERENCES users (id),
    CONSTRAINT fk_listings_category FOREIGN KEY (category_id) REFERENCES categories (id),
    CONSTRAINT chk_listings_price   CHECK (price >= 0),

    -- Indexes for the home page and filters (NFR-07).
    KEY idx_listings_status_created (status, created_at),  -- "newest available listings"
    KEY idx_listings_price (price),                        -- price range filter and sort
    FULLTEXT KEY ft_listings_search (title, description)   -- keyword search (FR-14)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS listing_images (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    listing_id INT UNSIGNED NOT NULL,
    file_path  VARCHAR(255) NOT NULL,
    sort_order TINYINT UNSIGNED NOT NULL DEFAULT 0,   -- 0 is the cover photo

    -- ON DELETE CASCADE: deleting a listing deletes its image rows too.
    CONSTRAINT fk_images_listing FOREIGN KEY (listing_id) REFERENCES listings (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Interaction between people
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS conversations (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    listing_id INT UNSIGNED NOT NULL,
    buyer_id   INT UNSIGNED NOT NULL,
    seller_id  INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_conversations_listing FOREIGN KEY (listing_id) REFERENCES listings (id) ON DELETE CASCADE,
    CONSTRAINT fk_conversations_buyer   FOREIGN KEY (buyer_id)   REFERENCES users (id),
    CONSTRAINT fk_conversations_seller  FOREIGN KEY (seller_id)  REFERENCES users (id),

    -- One conversation per buyer per listing (FR-17).
    UNIQUE KEY uq_conversations_listing_buyer (listing_id, buyer_id),
    KEY idx_conversations_seller (seller_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS messages (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT UNSIGNED NOT NULL,
    sender_id       INT UNSIGNED NOT NULL,
    body            TEXT NOT NULL,
    is_read         TINYINT(1) NOT NULL DEFAULT 0,
    sent_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_messages_conversation FOREIGN KEY (conversation_id) REFERENCES conversations (id) ON DELETE CASCADE,
    CONSTRAINT fk_messages_sender       FOREIGN KEY (sender_id)       REFERENCES users (id),

    -- Serves "messages in this conversation newer than id X" (polling, FR-21).
    KEY idx_messages_conversation (conversation_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS wishlists (
    user_id    INT UNSIGNED NOT NULL,
    listing_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    -- Composite primary key: a user can save a listing only once.
    PRIMARY KEY (user_id, listing_id),
    CONSTRAINT fk_wishlists_user    FOREIGN KEY (user_id)    REFERENCES users (id)    ON DELETE CASCADE,
    CONSTRAINT fk_wishlists_listing FOREIGN KEY (listing_id) REFERENCES listings (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reviews (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    listing_id  INT UNSIGNED NOT NULL,
    reviewer_id INT UNSIGNED NOT NULL,
    seller_id   INT UNSIGNED NOT NULL,
    rating      TINYINT UNSIGNED NOT NULL,
    comment     TEXT NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_reviews_listing  FOREIGN KEY (listing_id)  REFERENCES listings (id) ON DELETE CASCADE,
    CONSTRAINT fk_reviews_reviewer FOREIGN KEY (reviewer_id) REFERENCES users (id),
    CONSTRAINT fk_reviews_seller   FOREIGN KEY (seller_id)   REFERENCES users (id),
    CONSTRAINT chk_reviews_rating  CHECK (rating BETWEEN 1 AND 5),

    -- One review per buyer per listing (FR-20).
    UNIQUE KEY uq_reviews_listing_reviewer (listing_id, reviewer_id),
    KEY idx_reviews_seller (seller_id)                -- average rating for a seller
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Keeping it clean
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS reports (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    listing_id  INT UNSIGNED NOT NULL,
    reporter_id INT UNSIGNED NOT NULL,
    reason      ENUM('scam', 'prohibited', 'spam', 'other') NOT NULL,
    details     TEXT NULL,
    status      ENUM('open', 'resolved', 'dismissed') NOT NULL DEFAULT 'open',
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_reports_listing  FOREIGN KEY (listing_id)  REFERENCES listings (id) ON DELETE CASCADE,
    CONSTRAINT fk_reports_reporter FOREIGN KEY (reporter_id) REFERENCES users (id),

    KEY idx_reports_status (status)                   -- admin works from status = 'open'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Email
-- ---------------------------------------------------------------------

-- One row for every link emailed to a user, such as "reset your password" (FR-06).
-- The link contains a long random secret. Only a hash of that secret is stored
-- here, exactly as only a hash of a password is stored. Someone who could read
-- this table would still not be able to build a working link.
CREATE TABLE IF NOT EXISTS user_tokens (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    purpose    VARCHAR(30) NOT NULL,              -- what the link is for, e.g. 'password_reset'
    token_hash CHAR(64) NOT NULL,                 -- SHA-256 of the secret in the link
    expires_at DATETIME NOT NULL,                 -- the link stops working after this time
    used_at    DATETIME NULL,                     -- set when the link is used; it works only once
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_user_tokens_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,

    UNIQUE KEY uq_user_tokens_hash (token_hash),      -- a link is looked up by this
    KEY idx_user_tokens_user (user_id, purpose)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Emails written while the site runs with 'mail_driver' => 'log' (see config.php).
-- Nothing is really sent then: the email is saved here so a developer can read it
-- at /pages/dev-mailbox.php. A live site that sends real email leaves this empty.
CREATE TABLE IF NOT EXISTS outbox (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    to_email   VARCHAR(255) NOT NULL,
    subject    VARCHAR(255) NOT NULL,
    body       TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Security
-- ---------------------------------------------------------------------

-- One row per wrong password, kept for a day. Used to pause logins after too many
-- failures in a row, which stops someone from guessing a password by trying
-- thousands of them (see includes/login-limit.php).
CREATE TABLE IF NOT EXISTS login_attempts (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email        VARCHAR(255) NOT NULL,           -- the address typed, whether or not it has an account
    ip           VARCHAR(45) NOT NULL,            -- the visitor's IP address (45 characters fits IPv6)
    attempted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    KEY idx_login_attempts_email (email, attempted_at),  -- "recent failures for this address"
    KEY idx_login_attempts_ip (ip, attempted_at),        -- "recent failures from this computer"
    KEY idx_login_attempts_time (attempted_at)           -- clearing out old rows
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Starting data
-- ---------------------------------------------------------------------

-- INSERT IGNORE skips rows that already exist, so this file is safe to run twice.
INSERT IGNORE INTO categories (name) VALUES
    ('Textbooks'),
    ('Electronics'),
    ('Furniture'),
    ('Clothing'),
    ('Others');
