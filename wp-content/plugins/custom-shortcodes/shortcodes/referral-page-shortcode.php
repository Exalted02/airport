<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

// =======================
// Shortcode for Referral Link + Copy Button (Input Group Style)
// =======================
function get_referral_link_with_button() {
    if (is_user_logged_in()) {
        $user_id = get_current_user_id();
        $referral_code = get_user_meta($user_id, 'wrc_ref_code', true);

        if (!empty($referral_code)) {
            $register_page_url = home_url('/register');
            $referral_link = add_query_arg('ref', $referral_code, $register_page_url);

            ob_start(); ?>
            
            <div class="referral-input-group" style="display:flex;align-items:center;">
                <input type="text" id="referral_link_input"
                       value="<?php echo esc_url($referral_link); ?>"
                       readonly
                       style="flex:1;padding:5px 15px;border:1px solid #ccc;border-right:0;
                              border-radius:25px 0 0 25px;outline:none;">
                <button type="button" id="copy_referral_btn"
                        style="padding:5px 20px;border:1px solid #ccc;border-left:0;
                               background:#429FE1;color:#fff;cursor:pointer;
                               border-radius:0 25px 25px 0;">
                    Kopiuj
                </button>
            </div>

            <script>
                document.addEventListener("DOMContentLoaded", function(){
                    const copyBtn = document.getElementById("copy_referral_btn");
                    const inputField = document.getElementById("referral_link_input");

                    if(copyBtn){
                        copyBtn.addEventListener("click", function(){
                            inputField.select();
                            inputField.setSelectionRange(0, 99999); // For mobile
                            navigator.clipboard.writeText(inputField.value);
                            copyBtn.innerText = "Skopiowano";
                            setTimeout(() => copyBtn.innerText = "Kopiuj", 2000);
                        });
                    }
                });
            </script>

            <?php
            return ob_get_clean();
        }
    }
    return '';
}
add_shortcode('referral_link', 'get_referral_link_with_button');
