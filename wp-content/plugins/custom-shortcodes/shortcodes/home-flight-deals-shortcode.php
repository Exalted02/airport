<?php
function homepage_flight_deals_shortcode() {
    global $wpdb;

    $table = $wpdb->prefix . 'flight_deals';
    $airport_table = $wpdb->prefix . 'airport_list';

    // Fetch only 3 deals for home page
    $results = $wpdb->get_results("
        SELECT f.*, a.code AS airport_code
        FROM $table f
        LEFT JOIN $airport_table a ON f.airport_id = a.id
        WHERE f.status = 1
        AND f.showing_home_page = 1
        ORDER BY f.created_at DESC
        LIMIT 3
    ");

    if (!$results) {
        return '<p>Brak dostępnych ofert lotów.</p>';
    }

    // Check login status
    $user_id = get_current_user_id();
    $button_url = $user_id ? home_url('/flight-deals') : home_url('/register');
    $button_text = $user_id ? 'Zobacz wszystkie oferty' : 'Zarejestruj się, aby zobaczyć więcej';

    ob_start();
    ?>

<style>
/* GRID */
.home-flight-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 25px;
    padding: 20px 0;
}

/* BOX */
.home-flight-box {
    background: #fff;
    border-radius: 14px;
    overflow: hidden;
    box-shadow: 0 4px 15px rgba(0, 53, 74, 0.22) !important;
    transition: 0.3s ease;
    cursor: pointer;
    display: flex;
    flex-direction: column;
    height: 100%;
}

.home-flight-box:hover {
    transform: translateY(-4px);
    box-shadow: 0 6px 22px rgba(0, 53, 74, 0.3);
}

/* FIXED IMAGE HEIGHT */
.home-flight-image {
    width: 100%;
    height: 200px !important; /* fixed same height */
    object-fit: cover;
    display: block;
}

/* BODY */
.home-flight-body {
    padding: 15px;
    display: flex;
    flex-direction: column;
    height: 100%;
}
.route-badge {
	border: 1px solid #0073aa;
	border-radius: 20px;
	padding: 4px 10px;
	font-size: 13px;
	font-weight: 500;
}
.home-flight-title {
    font-size: 1.1rem;
    font-weight: 700;
    margin-bottom: 6px;
    color: #00354a;
}

.home-flight-airport {
    font-size: 0.85rem;
    font-weight: 600;
    color: #0073aa;
    margin-bottom: 5px;
}

.home-flight-desc {
    font-size: 0.9rem;
    color: #555;
    margin-bottom: 10px;
}

/* PRICE STICK BOTTOM */
.home-flight-price {
    font-size: 1.1rem;
    font-weight: 700;
    color: #0073aa;
    margin-top: auto;
}
.home-flight-deal-info {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-bottom: 10px;
	font-size: 0.9rem;
	color: #444;
}
.home-flight-deal-info span i:before {
	color: #0073aa;
}
.home-flight-deal-price {
	font-size: 1.2rem;
	font-weight: 700;
	color: #0073aa;
}

/* BUTTON */
.home-view-more-btn {
    display: inline-block;
    margin: 25px auto 0;
    text-align: center;
    padding: 12px 25px;
    background: #0073aa;
    color: #fff;
    border-radius: 50px;
    font-weight: 600;
    text-decoration: none;
    transition: 0.3s;
}

.home-view-more-btn:hover {
    background-color: rgba(0, 53, 74, 0.1);
    color: rgb(0, 53, 74);
    border-color: rgb(0, 53, 74);
}

/* RESPONSIVE */
@media (max-width: 900px) {
    .home-flight-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}
@media (max-width: 600px) {
    .home-flight-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="home-flight-grid">
<?php foreach ($results as $deal): 
    $image = !empty($deal->image) ? esc_url($deal->image) : esc_url(content_url('uploads/2025/10/noimage.png'));
    $title = esc_html($deal->purpose);
    $desc = esc_html(wp_trim_words($deal->description, 15));
    $price = esc_html(number_format($deal->price, 0));
    $airport_code = esc_html($deal->airport_code);
?>
    <a class="home-flight-box" href="<?php echo esc_url(home_url('/deal-details/' . $deal->id)); ?>">
        <img src="<?php echo $image; ?>" class="home-flight-image" alt="Deal Image">

        <div class="home-flight-body">
            <div class="home-flight-airport"><span class="route-badge"><?php echo $airport_code; ?></span></div>
            <div class="home-flight-title"><?php echo $title; ?></div>
            <div class="home-flight-desc"><?php echo $desc; ?></div>
			<div class="home-flight-deal-info">
				<span><i class="dashicons dashicons-calendar"></i> 
					<?php $fmt = new IntlDateFormatter('pl_PL', IntlDateFormatter::LONG, IntlDateFormatter::NONE, null, null, 'd MMM');

							$start = $fmt->format(new DateTime($deal->start_date));
							$end   = $fmt->format(new DateTime($deal->end_date));
							echo $start. ' - '.$end;
					?>
				</span>
				<span class="home-flight-deal-price"><?php echo $price; ?> zł</span>
			</div>
        </div>
    </a>
<?php endforeach; ?>
</div>

<!-- VIEW ALL BUTTON -->
<div style="text-align:center;">
    <a href="<?php echo $button_url; ?>" class="home-view-more-btn">
        <?php echo $button_text; ?>
    </a>
</div>

<?php
    return ob_get_clean();
}
add_shortcode('home_flight_deals', 'homepage_flight_deals_shortcode');
