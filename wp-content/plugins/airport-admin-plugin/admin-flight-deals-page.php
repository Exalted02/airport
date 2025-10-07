<?php
global $wpdb;
$table = $wpdb->prefix . 'flight_deals';

// Handle Add
if (isset($_POST['add_flight_deal'])) {
    $wpdb->insert($table, [
        'offer_type'   => intval($_POST['offer_type']),
        'price'        => floatval($_POST['price']),
        'purpose'      => sanitize_text_field($_POST['purpose']),
        'booking_link' => esc_url_raw($_POST['booking_link']),
        'description'  => sanitize_textarea_field($_POST['description']),
        'more_details' => sanitize_textarea_field($_POST['more_details']),
        'status'       => 1
    ]);
    echo '<div class="notice notice-success"><p>Flight deal added.</p></div>';
}

// Handle Update
if (isset($_POST['update_flight_deal'])) {
    $wpdb->update($table, [
        'offer_type'   => intval($_POST['offer_type']),
        'price'        => floatval($_POST['price']),
        'purpose'      => sanitize_text_field($_POST['purpose']),
        'booking_link' => esc_url_raw($_POST['booking_link']),
        'description'  => sanitize_textarea_field($_POST['description']),
        'more_details' => sanitize_textarea_field($_POST['more_details']),
    ], ['id' => intval($_POST['deal_id'])]);
    echo '<div class="notice notice-success"><p>Flight deal updated.</p></div>';
}

// Handle Delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $wpdb->delete($table, ['id' => intval($_GET['delete'])]);
    echo '<div class="notice notice-success"><p>Flight deal deleted.</p></div>';
}

// Handle Archive/Unarchive
if (isset($_GET['archive']) && is_numeric($_GET['archive'])) {
    $wpdb->update($table, ['status' => 0], ['id' => intval($_GET['archive'])]);
    echo '<div class="notice notice-success"><p>Flight deal archived.</p></div>';
}
if (isset($_GET['unarchive']) && is_numeric($_GET['unarchive'])) {
    $wpdb->update($table, ['status' => 1], ['id' => intval($_GET['unarchive'])]);
    echo '<div class="notice notice-success"><p>Flight deal restored.</p></div>';
}

// Edit Form
$edit = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $edit = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", intval($_GET['edit'])));
}

// Get All Deals
$deals = $wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC");
?>

<div class="wrap">
    <h1>Flight Deal Management</h1>

    <h2><?php echo $edit ? "Edit Flight Deal" : "Add New Flight Deal"; ?></h2>
    <form method="post" style="max-width:800px;">
		<?php if ($edit): ?>
			<input type="hidden" name="deal_id" value="<?= esc_attr($edit->id) ?>" />
		<?php endif; ?>

		<table class="form-table">
			<tr>
				<th scope="row"><label for="offer_type">Offer Type</label></th>
				<td>
					<select name="offer_type" id="offer_type" required class="regular-text">
						<option value="0" <?= isset($edit) && $edit->offer_type == 0 ? 'selected' : '' ?>>Non Premium</option>
						<option value="1" <?= isset($edit) && $edit->offer_type == 1 ? 'selected' : '' ?>>Premium</option>
					</select>
				</td>
			</tr>

			<tr>
				<th scope="row"><label for="price">Price</label></th>
				<td><input type="number" step="0.01" name="price" id="price" value="<?= esc_attr($edit->price ?? '') ?>" required class="regular-text" /></td>
			</tr>

			<tr>
				<th scope="row"><label for="purpose">Purpose</label></th>
				<td><input type="text" name="purpose" id="purpose" value="<?= esc_attr($edit->purpose ?? '') ?>" required class="regular-text" /></td>
			</tr>

			<tr>
				<th scope="row"><label for="booking_link">Booking Link</label></th>
				<td><input type="url" name="booking_link" id="booking_link" value="<?= esc_attr($edit->booking_link ?? '') ?>" required class="regular-text" /></td>
			</tr>

			<tr>
				<th scope="row"><label for="description">Description</label></th>
				<td><textarea name="description" id="description" rows="4" required class="regular-text"><?= esc_textarea($edit->description ?? '') ?></textarea></td>
			</tr>

			<tr>
				<th scope="row"><label for="more_details">More Details</label></th>
				<td><textarea name="more_details" id="more_details" rows="5" class="regular-text"><?= esc_textarea($edit->more_details ?? '') ?></textarea></td>
			</tr>
		</table>

		<p class="submit">
			<button type="submit" name="<?php echo $edit ? 'update_flight_deal' : 'add_flight_deal'; ?>" class="button button-primary">
				<?php echo $edit ? 'Update Flight Deal' : 'Add Flight Deal'; ?>
			</button>
		</p>
	</form>

    <hr>

    <h2>All Flight Deals</h2>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>ID</th>
                <th>Offer Type</th>
                <th>Price</th>
                <th>Purpose</th>
                <th>Status</th>
                <th>Created</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($deals as $deal): ?>
            <tr>
                <td><?= $deal->id ?></td>
                <td><?= $deal->offer_type ? 'Premium' : 'Non Premium' ?></td>
                <td>$<?= number_format($deal->price, 2) ?></td>
                <td><?= esc_html($deal->purpose) ?></td>
                <td><?= $deal->status ? 'Active' : 'Archived' ?></td>
                <td><?= esc_html($deal->created_at) ?></td>
                <td>
                    <a href="?page=flight-deals&edit=<?= $deal->id ?>">Edit</a> | 
                    <a href="?page=flight-deals&delete=<?= $deal->id ?>" onclick="return confirm('Delete this deal?')">Delete</a> | 
                    <?php if ($deal->status): ?>
                        <a href="?page=flight-deals&archive=<?= $deal->id ?>">Archive</a>
                    <?php else: ?>
                        <a href="?page=flight-deals&unarchive=<?= $deal->id ?>">Unarchive</a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
