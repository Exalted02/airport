<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

function custom_flight_deals_shortcode() {
    global $wpdb;

    $table = $wpdb->prefix . 'flight_deals';
	$airport_table = $wpdb->prefix . 'airport_list';
    $per_page = 8;

	// Get logged-in user
    $user_id = get_current_user_id();
    if (!$user_id) {
        return '<p>Zaloguj się, aby zobaczyć dostępne oferty lotów.</p>';
    }
	
	// Get user's saved airports (comma-separated)
    $user_airports = get_user_meta($user_id, 'airport', true);

    if (empty($user_airports)) {
        return '<p>W Twoim profilu nie wybrano żadnego lotniska. Wybierz lotnisko, aby zobaczyć pasujące oferty lotów.</p>';
    }

    // Convert comma-separated to array of integers
    $airport_ids = array_filter(array_map('intval', explode(',', $user_airports)));

    if (empty($airport_ids)) {
        return '<p>Nie znaleziono prawidłowych danych dotyczących lotniska.</p>';
    }
	
    $paged = max( 1, get_query_var('paged') ? get_query_var('paged') : ( get_query_var('page') ? get_query_var('page') : 1 ) );
    $offset = ($paged - 1) * $per_page;

    // Count total deals for pagination
    $placeholders = implode(',', array_fill(0, count($airport_ids), '%d'));
    $query_total = $wpdb->prepare(
        "SELECT COUNT(*) FROM $table WHERE status = 1 AND airport_id IN ($placeholders)",
        $airport_ids
    );
    $total = $wpdb->get_var($query_total);

    // Fetch deals matching user's airports
    $query = $wpdb->prepare("
        SELECT f.*, a.code AS airport_code
        FROM $table f
        LEFT JOIN $airport_table a ON f.airport_id = a.id
        WHERE f.status = 1
        AND f.airport_id IN ($placeholders)
        ORDER BY f.created_at DESC
        LIMIT %d OFFSET %d
    ", array_merge($airport_ids, [$per_page, $offset]));
	$results = $wpdb->get_results($query);

    if (!$results) return '<p>W tej chwili nie ma żadnych ofert lotów.</p>';

    ob_start();
    ?>
    <style>
    /* ===== CARD STYLES ===== */
    .flight-deals-container {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(480px, 1fr));
        gap: 20px;
        margin: 40px auto;
    }
    .flight-deal-card {
        display: flex;
        background: #fff;
        border-radius: 16px;
        box-shadow: 0 4px 12px rgba(0, 53, 74, 0.5);
        overflow: hidden;
        transition: all 0.3s ease;
        position: relative;
        padding: 10px;
    }
    .flight-deal-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 6px 20px rgba(0, 53, 74, 0.5);
    }
    .flight-deal-image {
        width: 180px;
        height: 160px;
        object-fit: cover;
        flex-shrink: 0;
        border-radius: 10px !important;
    }
    .flight-deal-content {
        padding: 10px 20px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        flex-grow: 1;
    }
    .deal-badge {
        position: absolute;
        top: 10px;
        left: 10px;
        background: #eef5ff;
        color: #0073aa;
        font-size: 0.75rem;
        padding: 4px 8px;
        border-radius: 6px;
        font-weight: 600;
    }
    .flight-top {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 8px;
    }
    .route-badge {
        border: 1px solid #0073aa;
        border-radius: 20px;
        padding: 4px 10px;
        font-size: 13px;
        font-weight: 500;
    }
    .unlock-badge {
        background: #f2f5f7;
        border-radius: 20px;
        padding: 4px 10px;
        font-size: 13px;
        display: flex;
        align-items: center;
        gap: 4px;
    }
    .unlock-badge svg {
        width: 13px;
        height: 13px;
        fill: #0073aa;
    }
    .flight-deal-title {
        font-size: 1.1rem;
        font-weight: 700;
        color: #222;
        margin-bottom: 6px;
    }
    .flight-deal-desc {
        font-size: 0.9rem;
        color: #666;
        line-height: 1.4;
        margin-bottom: 8px;
    }
    .flight-deal-info {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 10px;
        font-size: 0.9rem;
        color: #444;
    }
    .flight-deal-info span i:before {
        color: #0073aa;
    }
    .flight-deal-price {
        font-size: 1.2rem;
        font-weight: 700;
        color: #0073aa;
    }
    .flight-deal-button, .flight-deal-button:hover {
        display: inline-block;
        width: 100%;
        text-align: center;
        padding: 10px 0;
        border-radius: 500px;
        border: 1px solid #0073aa;
        color: #000000;
        font-weight: 600;
        text-decoration: none;
        transition: background 0.3s, color 0.3s;
        cursor: pointer;
    }
    @media (max-width: 600px) {
        .flight-deal-card { flex-direction: column; }
        .flight-deal-image { width: 100%; height: 200px; }
    }

    /* ===== PAGINATION ===== */
    .custom-pagination {
        text-align: center;
        margin: 40px 0 0;
    }
    .custom-pagination a,
    .custom-pagination span {
        display: inline-block;
        padding: 8px 14px;
        margin: 0 4px;
        border-radius: 6px;
        border: 1px solid #ddd;
        text-decoration: none;
        color: #0073aa;
        font-weight: 500;
    }
    .custom-pagination a:hover { background: #0073aa; color: #fff; }
    .custom-pagination .current {
        background: #0073aa;
        color: #fff;
        border-color: #0073aa;
    }

    /* ===== PREMIUM POPUP ===== */
    #premium-popup {
        position: fixed;
        top:0; left:0; width:100%; height:100%;
        display:none;
        z-index:9999;
        opacity: 0;
        transition: opacity 0.3s ease;
        backdrop-filter: blur(4px);
    }
    #premium-popup.active { display: block; opacity: 1; }
    .premium-popup-overlay {
        position:absolute; top:0; left:0; width:100%; height:100%;
        background: rgba(0,0,0,0.6);
    }
    .premium-popup-content {
        position:absolute;
        top:50%; left:50%;
        transform: translate(-50%, -50%) scale(0.8);
        background:#fff;
        padding:30px;
        border-radius:10px;
        max-width:400px;
        text-align:center;
        transition: transform 0.3s ease;
    }
    .premium-popup-content h2 {
        font-size: 24px;
		font-weight: 700;
    }
    .premium-popup-content svg {
		width: 50px;
		height: 50px;
		fill: #0073aa;
    }
    #premium-popup.active .premium-popup-content { transform: translate(-50%, -50%) scale(1); }
    #premium-close {
        margin-top:10px; padding:10px 20px; border:none;
        background:#0073aa; color:#fff; cursor:pointer; border-radius:500px;
    }
    </style>

    <div class="flight-deals-container">
        <?php foreach ($results as $deal):
            $image = !empty($deal->image) ? esc_url($deal->image) : esc_url(content_url('uploads/2025/10/noimage.png'));
            $title = esc_html($deal->purpose);
            $desc = esc_html(wp_trim_words($deal->description, 15));
            $price = esc_html(number_format($deal->price, 0));
            $airport_code = esc_html($deal->airport_code);
        ?>
        <div class="flight-deal-card">
            <img src="<?php echo $image; ?>" alt="Deal Image" class="flight-deal-image" />
            <div class="flight-deal-content">
                <div class="flight-top">
                    <span class="route-badge"><?php echo $airport_code; ?></span>
                    <?php if ($deal->offer_type == 1): ?>
                        <span class="unlock-badge">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                <path d="M12 17a2 2 0 0 0 2-2v-1H10v1a2 2 0 0 0 2 2zm6-6V9a6 6 0 0 0-12 0h2a4 4 0 0 1 8 0v2H6a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-9a2 2 0 0 0-2-2z"/>
                            </svg> PREMIA
                        </span>
                    <?php endif; ?>
                </div>
                <div>
                    <div class="flight-deal-title"><?php echo $title; ?></div>
                    <div class="flight-deal-desc"><?php echo $desc; ?></div>
                </div>
                <div>
                    <div class="flight-deal-info">
                        <span><i class="dashicons dashicons-calendar"></i> 
                            <?php $fmt = new IntlDateFormatter('pl_PL', IntlDateFormatter::LONG, IntlDateFormatter::NONE, null, null, 'd MMM');

									$start = $fmt->format(new DateTime($deal->start_date));
									$end   = $fmt->format(new DateTime($deal->end_date));
									echo $start. ' - '.$end;
							?>
                        </span>
                        <span class="flight-deal-price"><?php echo $price; ?> zł</span>
                    </div>
                    <?php if ($deal->offer_type == 1): ?>
                        <a href="#" class="flight-deal-button premium-deal" data-title="<?php echo esc_attr($title); ?>">Zobacz szczegóły</a>
                    <?php else: ?>
                        <a href="<?php echo esc_url(home_url('/deal-details/' . $deal->id)); ?>" class="flight-deal-button">Zobacz szczegóły</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- PREMIUM POPUP -->
    <div id="premium-popup">
        <div class="premium-popup-overlay"></div>
        <div class="premium-popup-content">
			<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="14" height="14" fill="#0073aa" style="vertical-align: middle; margin-right: 4px;">
				<path d="M12 17a2 2 0 0 0 2-2v-2a2 2 0 0 0-4 0v2a2 2 0 0 0 2 2zm6-7h-1V9a5 5 0 0 0-10 0v1H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8a2 2 0 0 0-2-2zm-6-4a3 3 0 0 1 3 3v1H9V9a3 3 0 0 1 3-3z"/>
			</svg>
            <h2>Pełny dostęp do oferty tylko dla użytkowników Premium</h2>
            <p>Dostęp do pełnego opisu ofert lotów jest możliwy tylko z pakietem Premium – odkryj wszystkie szczegóły i korzyści, które dla Ciebie przygotowaliśmy</p>
			<a href="<?php echo esc_url( home_url( '/pricing' ) ); ?>" id="premium-close">Uaktualnij swój plan</a>
        </div>
    </div>

    <?php
    // Pagination
    $total_pages = ceil($total / $per_page);
    if ($total_pages > 1): ?>
        <div class="custom-pagination">
            <?php
            echo paginate_links(array(
                'base' => trailingslashit(get_permalink()) . '%_%',
                'format' => 'page/%#%/',
                'current' => $paged,
                'total' => $total_pages,
                'prev_text' => '« Prev',
                'next_text' => 'Next »',
            ));
            ?>
        </div>
    <?php endif; ?>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const premiumButtons = document.querySelectorAll('.premium-deal');
        const popup = document.getElementById('premium-popup');
        const closeBtn = document.getElementById('premium-close');

        function openPopup(title){
            popup.querySelector('h2').innerText = title + ' (Oferta Premium)';
            popup.classList.add('active');
        }
        function closePopup(){
            popup.classList.remove('active');
        }

        premiumButtons.forEach(btn => {
            btn.addEventListener('click', function(e){
                e.preventDefault();
                openPopup(this.dataset.title);
            });
        });
        closeBtn.addEventListener('click', closePopup);
        popup.querySelector('.premium-popup-overlay').addEventListener('click', closePopup);
    });
    </script>

    <?php
    return ob_get_clean();
}
add_shortcode('flight_deals','custom_flight_deals_shortcode');
