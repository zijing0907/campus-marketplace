<?php
// The text fields of the listing form, shared by "Sell an item" and "Edit listing"
// so the two forms can never drift apart (NFR-10).
//
// Include it inside a <form>. It expects these variables from the page:
//   $data        the field values, as returned by listing_form_input()
//   $errors      [field => message], as returned by validate_listing()
//   $categories  the list from all_categories()
?>
<label for="title">Title</label>
<input type="text" id="title" name="title" value="<?= e($data['title']) ?>"
       required minlength="3" maxlength="120" placeholder="Casio fx-570 scientific calculator">
<?= field_error($errors, 'title') ?>

<div class="form-row">
    <div>
        <label for="price">Price (RM)</label>
        <input type="number" id="price" name="price" value="<?= e($data['price']) ?>"
               required min="0" max="999999.99" step="0.01" inputmode="decimal" placeholder="35.00">
        <?= field_error($errors, 'price') ?>
    </div>

    <div>
        <label for="category_id">Category</label>
        <select id="category_id" name="category_id" required>
            <option value="">Choose a category</option>
            <?php foreach ($categories as $category): ?>
                <option value="<?= e((string) $category['id']) ?>" <?= (string) $category['id'] === $data['category_id'] ? 'selected' : '' ?>>
                    <?= e($category['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?= field_error($errors, 'category_id') ?>
    </div>
</div>

<label for="condition">Condition</label>
<select id="condition" name="condition" required>
    <option value="">Choose a condition</option>
    <?php foreach (LISTING_CONDITIONS as $value => $label): ?>
        <option value="<?= e($value) ?>" <?= $value === $data['condition'] ? 'selected' : '' ?>><?= e($label) ?></option>
    <?php endforeach; ?>
</select>
<?= field_error($errors, 'condition') ?>

<label for="description">Description</label>
<textarea id="description" name="description" rows="5" required maxlength="2000"
          placeholder="What is it, how old is it, and is anything wrong with it?"><?= e($data['description']) ?></textarea>
<?= field_error($errors, 'description') ?>

<label for="meetup_location">Meetup location</label>
<input type="text" id="meetup_location" name="meetup_location" value="<?= e($data['meetup_location']) ?>"
       required minlength="2" maxlength="120" placeholder="Library entrance">
<?= field_error($errors, 'meetup_location') ?>
