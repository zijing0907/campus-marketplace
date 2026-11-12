<?php
// Demo data: sample accounts, listings and messages, so a freshly installed site
// does not look empty (NFR-11 asks for a demo account).
//
// It can be loaded in two ways:
//   - from the command line:    php database/seed.php       (--remove to undo)
//   - from the admin dashboard: the "Load demo data" button
// The second way exists because many hosts give no command line.
//
// Everything created here belongs to accounts whose email starts with "demo.".
// That is how remove_demo_data() finds it all again.
//
// Loaded by the admin dashboard and by database/seed.php, not by every page.

// The password shared by all demo accounts. It is public on purpose: it belongs in
// the README, so visitors can try the site. Demo accounts are ordinary students;
// there is deliberately no demo admin, because a public admin login would let
// anyone ban users or hide listings on the live site.
const DEMO_PASSWORD = 'campus-demo-2026';

// key => account. The key becomes the email: "student" is demo.student@<domain>.
const DEMO_USERS = [
    'student' => ['name' => 'Demo Student', 'faculty' => 'Faculty of Science'],
    'aisyah'  => ['name' => 'Nur Aisyah',   'faculty' => 'Faculty of Science'],
    'daniel'  => ['name' => 'Daniel Lim',   'faculty' => 'Faculty of Information and Communication Technology'],
    'priya'   => ['name' => 'Priya Nair',   'faculty' => 'Faculty of Business and Finance'],
];

// One row per listing:
// seller key, category, title, description, price, condition, meetup location,
// status, how many hours ago it was posted, photo files in database/demo/photos.
const DEMO_LISTINGS = [
    ['daniel', 'Electronics', 'Casio fx-570EX scientific calculator', "Used for two semesters and works perfectly.\nComes with the hard cover.", '35.00', 'like_new', 'Block A lobby', 'available', 3, ['calculator.jpg']],
    ['priya', 'Furniture', 'Study desk with drawer', "Solid wood desk, 120 cm wide.\nOne small scratch on the left side. You will need a car to collect it.", '85.00', 'used', 'Hostel Block D', 'available', 8, ['study-desk-1.jpg', 'study-desk-2.jpg']],
    ['aisyah', 'Textbooks', 'Engineering Mathematics textbook, 8th edition', 'Some pencil notes in the first three chapters, otherwise clean.', '45.00', 'used', 'Library entrance', 'available', 20, ['maths-textbook.jpg']],
    ['priya', 'Electronics', 'Wireless mouse and keyboard set', 'Unopened box. I received two as gifts.', '40.00', 'new', 'Block A lobby', 'available', 27, ['mouse-keyboard.jpg']],
    ['aisyah', 'Electronics', 'LED desk lamp, adjustable', 'Three brightness levels. Charges by USB.', '20.00', 'like_new', 'Cafeteria', 'available', 44, ['desk-lamp.jpg']],
    ['daniel', 'Textbooks', 'Introduction to Programming with Python', 'First-year textbook. No highlighting.', '30.00', 'used', 'Library entrance', 'available', 52, ['python-textbook.jpg']],
    ['priya', 'Electronics', 'Mini fridge, 50 litres', "Quiet and cold. Selling because I am moving out of the hostel.\nCollect from the ground floor.", '180.00', 'used', 'Hostel Block B', 'available', 70, ['mini-fridge.jpg']],
    ['aisyah', 'Clothing', 'University hoodie, size M', 'Worn twice. Too small for me.', '25.00', 'like_new', 'Student centre', 'available', 76, ['hoodie.jpg']],
    ['daniel', 'Furniture', 'Office chair with armrests', 'Adjustable height. The fabric is a little faded.', '60.00', 'used', 'Hostel Block D', 'available', 98, ['office-chair.jpg']],
    ['aisyah', 'Clothing', 'Lab coat, size L', 'Washed and ironed. Used for one semester of chemistry labs.', '15.00', 'used', 'Faculty of Science foyer', 'available', 120, ['lab-coat.jpg']],
    ['priya', 'Furniture', 'Bookshelf, 3 tiers', 'Light and easy to carry. Holds about 40 books.', '35.00', 'used', 'Hostel Block D', 'available', 146, ['bookshelf-1.jpg', 'bookshelf-2.jpg']],
    ['daniel', 'Others', 'Badminton racket with cover', 'Restrung last month.', '22.00', 'used', 'Sports complex', 'available', 170, ['badminton-racket.jpg']],
    ['daniel', 'Others', 'Rice cooker, 1 litre', 'Enough for two people.', '28.00', 'used', 'Hostel Block B', 'sold', 200, ['rice-cooker.jpg']],
    ['priya', 'Textbooks', 'Accounting Principles textbook', 'Like new. I changed course after two weeks.', '38.00', 'like_new', 'Library entrance', 'sold', 240, ['accounting-textbook.jpg']],
];

// A conversation about the first listing, between the demo login and its seller.
// Each row: who writes ("buyer" or "seller"), the text, minutes ago, already read?
const DEMO_MESSAGES = [
    ['buyer',  'Hi, is the calculator still available?', 95, true],
    ['seller', 'Yes, it is. I can meet at the Block A lobby on weekdays after 2pm.', 80, true],
    ['buyer',  'Great. Would you take RM30?', 70, true],
    ['seller', 'RM32 and it is yours. Does tomorrow at 3pm work?', 25, false],
];

// Sales with a rating, so the demo sellers have something on their profile.
// Each row: position of the sold listing in DEMO_LISTINGS (counting from 0),
// the buyer's key, the rating from 1 to 5, the comment, and how many hours ago.
const DEMO_REVIEWS = [
    [12, 'student', 5, 'Exactly as described, and Daniel was on time. Would buy from him again.', 150],
    [13, 'aisyah',  4, 'The book is in great condition. We met a little late, but all fine.', 190],
];

// The listings the demo login has saved, by their position in DEMO_LISTINGS
// (counting from 0): the study desk and the mini fridge.
const DEMO_WISHLIST = [1, 6];

/**
 * The email address of a demo account, for example demo.student@1utar.my.
 */
function demo_email(string $key): string
{
    return 'demo.' . $key . '@' . config()['university_email_domain'];
}

/**
 * The ids of all demo accounts that currently exist.
 */
function demo_user_ids(): array
{
    $stmt = db()->prepare('SELECT id FROM users WHERE email LIKE ? ORDER BY id');
    $stmt->execute(['demo.%@' . addcslashes(config()['university_email_domain'], '%_\\')]);

    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function demo_data_loaded(): bool
{
    return count(demo_user_ids()) > 0;
}

/**
 * Create the demo accounts, listings, photos and messages.
 * Returns ['users' => 4, 'listings' => 14]. Throws if demo data is already there.
 */
function load_demo_data(): array
{
    if (demo_data_loaded()) {
        throw new RuntimeException('Demo data is already loaded.');
    }

    $photoSource = __DIR__ . '/../database/demo/photos';
    $photoTarget = __DIR__ . '/../uploads/listings';
    if (!is_dir($photoTarget) && !mkdir($photoTarget, 0755, true)) {
        throw new RuntimeException('Cannot create the folder uploads/listings.');
    }

    // Category name => id. A category an admin has removed falls back to the catch-all.
    $categoryIds = array_column(all_categories(), 'id', 'name');
    $fallbackId = $categoryIds[FALLBACK_CATEGORY] ?? reset($categoryIds);

    $pdo = db();
    $copiedPhotos = [];

    // One transaction: either all the demo data appears, or none of it.
    $pdo->beginTransaction();

    try {
        // Accounts. The same hashing as real registration, so demo logins work normally.
        $hash = password_hash(DEMO_PASSWORD, PASSWORD_DEFAULT);
        $insertUser = $pdo->prepare('INSERT INTO users (name, email, password_hash, faculty) VALUES (?, ?, ?, ?)');
        $userIds = [];
        foreach (DEMO_USERS as $key => $user) {
            $insertUser->execute([$user['name'], demo_email($key), $hash, $user['faculty']]);
            $userIds[$key] = (int) $pdo->lastInsertId();
        }

        // Listings, each with its photos copied into uploads/ under a random name,
        // exactly as if a student had uploaded them.
        $insertListing = $pdo->prepare(
            'INSERT INTO listings (seller_id, category_id, title, description, price, item_condition, meetup_location, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW() - INTERVAL ? HOUR)'
        );
        $insertImage = $pdo->prepare('INSERT INTO listing_images (listing_id, file_path, sort_order) VALUES (?, ?, ?)');
        $listingIds = [];

        foreach (DEMO_LISTINGS as [$seller, $category, $title, $description, $price, $condition, $location, $status, $hoursAgo, $photos]) {
            $insertListing->execute([
                $userIds[$seller], $categoryIds[$category] ?? $fallbackId, $title, $description,
                $price, $condition, $location, $status, $hoursAgo,
            ]);
            $listingId = (int) $pdo->lastInsertId();
            $listingIds[] = $listingId;

            foreach ($photos as $order => $photo) {
                $path = 'uploads/listings/' . bin2hex(random_bytes(16)) . '.jpg';
                if (!copy($photoSource . '/' . $photo, __DIR__ . '/../' . $path)) {
                    throw new RuntimeException('Cannot copy the demo photo ' . $photo);
                }
                $copiedPhotos[] = $path;
                $insertImage->execute([$listingId, $path, $order]);
            }
        }

        // The conversation: the demo login asks the first listing's seller about it.
        $firstSeller = $userIds[DEMO_LISTINGS[0][0]];
        $stmt = $pdo->prepare('INSERT INTO conversations (listing_id, buyer_id, seller_id) VALUES (?, ?, ?)');
        $stmt->execute([$listingIds[0], $userIds['student'], $firstSeller]);
        $conversationId = (int) $pdo->lastInsertId();

        $insertMessage = $pdo->prepare(
            'INSERT INTO messages (conversation_id, sender_id, body, is_read, sent_at)
             VALUES (?, ?, ?, ?, NOW() - INTERVAL ? MINUTE)'
        );
        foreach (DEMO_MESSAGES as [$from, $body, $minutesAgo, $isRead]) {
            $senderId = $from === 'buyer' ? $userIds['student'] : $firstSeller;
            $insertMessage->execute([$conversationId, $senderId, $body, $isRead ? 1 : 0, $minutesAgo]);
        }

        // Recorded buyers and their ratings (FR-20). The seller is taken from the
        // listing itself, as it is when a real buyer rates a sale.
        $setBuyer = $pdo->prepare('UPDATE listings SET buyer_id = ? WHERE id = ?');
        $insertReview = $pdo->prepare(
            'INSERT INTO reviews (listing_id, reviewer_id, seller_id, rating, comment, created_at)
             VALUES (?, ?, ?, ?, ?, NOW() - INTERVAL ? HOUR)'
        );
        foreach (DEMO_REVIEWS as [$position, $buyer, $stars, $comment, $hoursAgo]) {
            $sellerId = $userIds[DEMO_LISTINGS[$position][0]];
            $setBuyer->execute([$userIds[$buyer], $listingIds[$position]]);
            $insertReview->execute([$listingIds[$position], $userIds[$buyer], $sellerId, $stars, $comment, $hoursAgo]);
        }

        // The demo login's saved listings.
        $insertWish = $pdo->prepare('INSERT INTO wishlists (user_id, listing_id) VALUES (?, ?)');
        foreach (DEMO_WISHLIST as $position) {
            $insertWish->execute([$userIds['student'], $listingIds[$position]]);
        }

        $pdo->commit();
    } catch (Throwable $ex) {
        $pdo->rollBack();
        foreach ($copiedPhotos as $path) {
            delete_uploaded_file($path);
        }
        throw $ex;
    }

    return ['users' => count($userIds), 'listings' => count($listingIds)];
}

/**
 * Remove the demo accounts and everything that belongs to them.
 * Returns how much was removed: ['users' => 4, 'listings' => 14].
 */
function remove_demo_data(): array
{
    $ids = demo_user_ids();
    if (!$ids) {
        return ['users' => 0, 'listings' => 0];
    }

    // "IN (?, ?, ?, ?)" needs one placeholder per id, so the list of question marks
    // is built to match. The ids themselves still travel as parameters.
    $in = implode(', ', array_fill(0, count($ids), '?'));
    $pdo = db();

    // Note the files to delete before the rows that name them are gone.
    $stmt = $pdo->prepare(
        "SELECT i.file_path FROM listing_images i JOIN listings l ON l.id = i.listing_id WHERE l.seller_id IN ($in)"
    );
    $stmt->execute($ids);
    $files = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $stmt = $pdo->prepare("SELECT avatar_path FROM users WHERE id IN ($in) AND avatar_path IS NOT NULL");
    $stmt->execute($ids);
    $files = array_merge($files, $stmt->fetchAll(PDO::FETCH_COLUMN));

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM listings WHERE seller_id IN ($in)");
    $stmt->execute($ids);
    $listingCount = (int) $stmt->fetchColumn();

    $pdo->beginTransaction();

    try {
        // First the rows that point at a demo account from somewhere else: its
        // conversations and reports about other students' listings, and any sale
        // recorded with a demo account as the buyer. The database would refuse to
        // delete the accounts while these still referred to them.
        $both = array_merge($ids, $ids);
        $pdo->prepare("DELETE FROM conversations WHERE buyer_id IN ($in) OR seller_id IN ($in)")->execute($both);
        $pdo->prepare("DELETE FROM reviews WHERE reviewer_id IN ($in) OR seller_id IN ($in)")->execute($both);
        $pdo->prepare("DELETE FROM reports WHERE reporter_id IN ($in)")->execute($ids);
        $pdo->prepare("UPDATE listings SET buyer_id = NULL WHERE buyer_id IN ($in)")->execute($ids);

        // Deleting the listings also deletes their photo rows, reports and wishlist
        // entries, because those foreign keys say ON DELETE CASCADE.
        $pdo->prepare("DELETE FROM listings WHERE seller_id IN ($in)")->execute($ids);
        $pdo->prepare("DELETE FROM users WHERE id IN ($in)")->execute($ids);

        $pdo->commit();
    } catch (Throwable $ex) {
        $pdo->rollBack();
        throw $ex;
    }

    foreach ($files as $path) {
        delete_uploaded_file($path);
    }

    return ['users' => count($ids), 'listings' => $listingCount];
}
