<?php
global $wpdb;
$table = $wpdb->prefix . 'airport_list';

// Handle Add
if (isset($_POST['add_airport'])) {
    $wpdb->insert($table, [
        'code' => sanitize_text_field($_POST['code']),
        'name' => sanitize_text_field($_POST['name'])
    ]);
    echo '<div class="notice notice-success"><p>Airport added.</p></div>';
}

// Handle Update
if (isset($_POST['update_airport'])) {
    $wpdb->update($table, [
        'code' => sanitize_text_field($_POST['code']),
        'name' => sanitize_text_field($_POST['name']),
    ], ['id' => intval($_POST['airport_id'])]);
    echo '<div class="notice notice-success"><p>Airport updated.</p></div>';
}

// Handle Single Delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $wpdb->delete($table, ['id' => intval($_GET['delete'])]);
    echo '<div class="notice notice-success"><p>Airport deleted.</p></div>';
}

// Handle Bulk Actions
if (isset($_POST['do_bulk_action']) && !empty($_POST['airport_ids'])) {
    $action = $_POST['bulk_action'];
    $ids = array_map('intval', $_POST['airport_ids']);

    if ($action === 'delete') {
        foreach ($ids as $id) {
            $wpdb->delete($table, ['id' => $id]);
        }
        echo '<div class="notice notice-success"><p>Selected airports deleted.</p></div>';
    }
}

// Edit Form
$edit = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $edit = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", intval($_GET['edit'])));
}

// All airports
$airports = $wpdb->get_results("SELECT * FROM $table ORDER BY name ASC");
?>

<div class="wrap">
    <h1>Airport Management</h1>
	<div class="tablenav top">
		<div class="alignleft actions">
			<form method="post">
				<?php if ($edit): ?>
					<input type="hidden" name="airport_id" value="<?= esc_attr($edit->id) ?>" />
				<?php endif; ?>
				<input type="text" name="code" value="<?= esc_attr($edit->code ?? '') ?>" placeholder="Airport Code" required />
				<input type="text" name="name" value="<?= esc_attr($edit->name ?? '') ?>" placeholder="Airport Name" required />
				<button type="submit" name="<?php echo $edit ? 'update_airport' : 'add_airport'; ?>" class="button button-<?php echo $edit ? 'primary' : 'secondary'; ?>"><?php echo $edit ? 'Update Airport' : 'Add Airport'; ?></button>
			</form>
		</div>
	</div>
	
	<hr>

    <h2>All Airports</h2>
    <form method="post">
        <div class="tablenav top">
            <div class="alignleft actions">
                <select name="bulk_action">
                    <option value="">Bulk actions</option>
                    <option value="delete">Delete</option>
                </select>
                <?php submit_button('Apply', 'action', 'do_bulk_action', false); ?>
            </div>
            <br class="clear" />
        </div>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><input type="checkbox" id="check_all_airports" /></th>
                    <th>ID</th><th>Code</th><th>Name</th><th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($airports as $airport): ?>
                <tr>
                    <td><input type="checkbox" name="airport_ids[]" value="<?= $airport->id ?>" /></td>
                    <td><?= $airport->id ?></td>
                    <td><?= esc_html($airport->code) ?></td>
                    <td><?= esc_html($airport->name) ?></td>
                    <td>
                        <a href="?page=airports&edit=<?= $airport->id ?>">Edit</a> |
                        <a href="?page=airports&delete=<?= $airport->id ?>" onclick="return confirm('Delete airport?')">Delete</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </form>
</div>

<script>
document.getElementById("check_all_airports").onclick = function() {
    document.querySelectorAll('input[name="airport_ids[]"]').forEach(cb => cb.checked = this.checked);
};
</script>
