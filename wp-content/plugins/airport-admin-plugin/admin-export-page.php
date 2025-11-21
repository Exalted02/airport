<?php if (!defined('ABSPATH')) exit; ?>

<div class="wrap">
    <h1>Export Data (CSV)</h1>

    <p>Download CSV exports for Users, PMS Subscriptions, and Flight Deals.</p>

    <h2>Users Export</h2>
    <a href="<?php echo admin_url('admin-post.php?action=export_users_csv'); ?>" class="button button-primary">
        Export Users CSV
    </a>

    <h2 style="margin-top: 25px;">PMS Subscriptions Export</h2>
    <a href="<?php echo admin_url('admin.php?page=pms-export-page'); ?>" class="button button-primary">
        Export PMS Subscriptions CSV
    </a>

    <h2 style="margin-top: 25px;">Flight Deals Export</h2>
    <a href="<?php echo admin_url('admin-post.php?action=export_flight_deals_csv'); ?>" class="button button-primary">
        Export Flight Deals CSV
    </a>

    <hr>

    <p>
        <strong>Note:</strong> All files download instantly and include the latest database data.
    </p>
</div>
