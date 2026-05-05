<?php
/**
 * Plugin Name: Jify Taxes
 * Description: Custom tax manager. Overrides default WC tax, calculates based on original price with optional shipping/discount adjustments.
 * Version: 2.4.0 (Per-item tax after discount)
 * Author: jify cloud
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
    return;
}

class Jify_Taxes_Standalone {

    public static function init() {
        load_plugin_textdomain( 'jify-taxes', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

        // UI
        add_filter( 'woocommerce_product_data_tabs', array( __CLASS__, 'add_product_tab' ) );
        add_action( 'woocommerce_product_data_panels', array( __CLASS__, 'add_product_data_panel' ) );
        add_action( 'woocommerce_process_product_meta', array( __CLASS__, 'save_tab_data' ) );
        
        // Product Status
        add_filter( 'woocommerce_product_get_tax_status', array( __CLASS__, 'disable_standard_tax_status' ), 10, 2 );
        add_filter( 'woocommerce_product_variation_get_tax_status', array( __CLASS__, 'disable_standard_tax_status' ), 10, 2 );

        // Backend Logic (Suppression)
        add_action( 'woocommerce_before_calculate_totals', array( __CLASS__, 'set_customer_vat_exempt' ) );

        // Frontend Hunter-Killer (JS + CSS)
        add_action( 'wp_footer', array( __CLASS__, 'output_frontend_scripts' ) );

        // Custom Jify Tax Calculation
        // Priority 50: This runs AFTER Jify Discount (which is at 20)
        // Expected Result: Discount is added first, Tax is added second (after discount)
        add_action( 'woocommerce_cart_calculate_fees', array( __CLASS__, 'calculate_custom_tax' ), 50 ); 

        // Disable native WC tax rendering when Jify Taxes is active.
        add_filter( 'wc_tax_enabled', array( __CLASS__, 'maybe_disable_wc_tax' ), 9999 );

        // Hide native WC tax rows when Jify Taxes is active.
        add_filter( 'woocommerce_cart_tax_totals', array( __CLASS__, 'filter_cart_tax_totals' ), 9999, 2 );
    }

    public static function is_jify_active_in_cart() {
        // Safety check to ensure we don't break admin
        if ( is_admin() && ! defined( 'DOING_AJAX' ) ) return false;
        if ( ! function_exists('WC') || ! WC()->cart ) return false;

        foreach( WC()->cart->get_cart() as $item ) {
            $target_id = $item['variation_id'] ? $item['variation_id'] : $item['product_id'];
            if ( get_post_meta( $target_id, '_jify_tax_enabled', true ) === 'yes' ) {
                return true;
            }
        }
        return false;
    }

    public static function set_customer_vat_exempt( $cart ) {
        if ( self::is_jify_active_in_cart() ) {
            if ( WC()->customer ) {
                WC()->customer->set_is_vat_exempt( true );
            }
        }
    }

    public static function output_frontend_scripts() {
        if ( ! self::is_jify_active_in_cart() ) return;
        ?>
        <style type="text/css">
            tr[class*="tax-rate"], 
            .cart-subtotal + .tax-rate,
            .tax-rate { 
                display: none !important; 
                visibility: hidden !important;
                opacity: 0 !important;
                height: 0 !important;
                margin: 0 !important;
                padding: 0 !important;
                overflow: hidden !important;
            }
        </style>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            function jifyKillTaxRow() {
                $('.tax-rate').remove();
                $('tr[class*="tax-rate"]').remove();
                $('tr[class*="tax-rate-tw"]').remove();
            }
            jifyKillTaxRow();
            $(document.body).on('updated_cart_totals updated_checkout', function(){
                jifyKillTaxRow();
                setTimeout(jifyKillTaxRow, 100);
            });
        });
        </script>
        <?php
    }

    public static function add_product_tab( $tabs ) {
        $tabs['jify_taxes'] = array(
            'label'    => __( 'Jify 稅金', 'jify-taxes' ),
            'target'   => 'jify_taxes_product_data',
            'class'    => array( 'show_if_simple', 'show_if_variable' ),
            'priority' => 62,
        );
        return $tabs;
    }

    public static function add_product_data_panel() {
        global $post;
        $product = wc_get_product( $post->ID );
        if ( ! $product ) return;

        $products_to_configure = array();
        $products_to_configure[] = array('id' => $post->ID, 'name' => __('Main Product / Default', 'jify-taxes'));

        if ( $product->is_type( 'variable' ) ) {
            $variations = $product->get_available_variations();
            foreach ( $variations as $variation ) {
                $var_obj = wc_get_product($variation['variation_id']);
                $products_to_configure[] = array(
                    'id' => $variation['variation_id'],
                    'name' => strip_tags( $var_obj->get_formatted_name() )
                );
            }
        }

        echo '<div id="jify_taxes_product_data" class="panel woocommerce_options_panel hidden">';
        echo '<div id="jify-taxes-app">';

        foreach ( $products_to_configure as $item ) {
            $id = $item['id'];
            $name = $item['name'];
            
            $is_enabled = get_post_meta( $id, '_jify_tax_enabled', true );
            $rate = get_post_meta( $id, '_jify_tax_rate', true );
            if ( $rate === '' ) $rate = '5';

            $inc_ship = get_post_meta( $id, '_jify_tax_inc_shipping', true );
            $deduct_disc = get_post_meta( $id, '_jify_tax_deduct_discount', true );
            
            ?>
            <div class="jify-product-row" style="border: 1px solid #ccd0d4; padding: 15px; margin: 10px 10px 20px 10px; background: #fff;">
                <h3><?php echo esc_html( $name ); ?> <span style="font-size: 11px; color: #a7aaad;">(ID: <?php echo $id; ?>)</span></h3>
                <p class="form-field">
                    <label>
                        <input type="checkbox" class="jify-enable-check" name="jify_tax_enabled[<?php echo $id; ?>]" value="yes" <?php checked( $is_enabled, 'yes' ); ?>>
                        <?php esc_html_e( 'Enable Jify 稅金 (Overrides standard WC 稅金)', 'jify-taxes' ); ?>
                    </label>
                </p>
                <div class="jify-tax-container" style="<?php echo $is_enabled === 'yes' ? '' : 'display:none;'; ?>">
                    <p class="form-field">
                        <label><?php esc_html_e( '稅金稅率 (%)', 'jify-taxes' ); ?></label>
                        <input type="number" step="0.01" style="width:80px;" name="jify_tax_rate[<?php echo $id; ?>]" value="<?php echo esc_attr($rate); ?>"> %
                    </p>
                    <div style="background:#f9f9f9; padding:10px;">
                        <strong>稅金基底調整:</strong>
                        <p class="form-field" style="margin:5px 0 0 0;">
                            <label>
                                <input type="checkbox" name="jify_tax_inc_ship[<?php echo $id; ?>]" value="yes" <?php checked( $inc_ship, 'yes' ); ?>>
                                <?php esc_html_e( 'Add Shipping to 稅金基底', 'jify-taxes' ); ?>
                            </label>
                        </p>
                        <p class="form-field" style="margin:5px 0 0 0;">
                            <label>
                                <input type="checkbox" name="jify_tax_deduct_disc[<?php echo $id; ?>]" value="yes" <?php checked( $deduct_disc, 'yes' ); ?>>
                                <?php esc_html_e( 'Deduct Discount from 稅金基底', 'jify-taxes' ); ?>
                            </label>
                        </p>
                    </div>
                </div>
            </div>
            <?php
        }
        echo '</div>'; // app
        
        ?>
        <script>
        jQuery(document).ready(function($) {
            $('#jify-taxes-app').on('change', '.jify-enable-check', function() {
                $(this).closest('.jify-product-row').find('.jify-tax-container').toggle($(this).is(':checked'));
            });
        });
        </script>
        <?php
        echo '</div>';
    }

    public static function save_tab_data( $post_id ) {
        $all_ids = array();
        if ( isset( $_POST['jify_tax_enabled'] ) ) $all_ids = array_keys( $_POST['jify_tax_enabled'] );
        if ( isset( $_POST['jify_tax_rate'] ) ) $all_ids = array_merge( $all_ids, array_keys( $_POST['jify_tax_rate'] ) );
        $all_ids = array_unique( $all_ids );

        foreach ( $all_ids as $id ) {
            $enabled = isset( $_POST['jify_tax_enabled'][$id] ) ? 'yes' : 'no';
            update_post_meta( $id, '_jify_tax_enabled', $enabled );

            if ( isset( $_POST['jify_tax_rate'][$id] ) ) update_post_meta( $id, '_jify_tax_rate', sanitize_text_field( $_POST['jify_tax_rate'][$id] ) );
            
            $inc_ship = isset( $_POST['jify_tax_inc_ship'][$id] ) ? 'yes' : 'no';
            update_post_meta( $id, '_jify_tax_inc_shipping', $inc_ship );

            $deduct_disc = isset( $_POST['jify_tax_deduct_disc'][$id] ) ? 'yes' : 'no';
            update_post_meta( $id, '_jify_tax_deduct_discount', $deduct_disc );
        }
    }

    public static function disable_standard_tax_status( $status, $product ) {
        $id = $product->get_id();
        if ( get_post_meta( $id, '_jify_tax_enabled', true ) === 'yes' ) {
            return 'none';
        }
        return $status;
    }

    public static function calculate_custom_tax( $cart ) {
        if ( is_admin() && ! defined( 'DOING_AJAX' ) ) return;

        $discount_items = array();
        if ( function_exists( 'WC' ) && WC()->session ) {
            $discount_items = WC()->session->get( 'jify_discount_item_amounts', array() );
        }
        if ( ! is_array( $discount_items ) ) {
            $discount_items = array();
        }

        $shipping_total = floatval( $cart->shipping_total );
        $shipping_basis_total = 0;
        $tax_items = array();

        foreach ( $cart->get_cart() as $item_key => $item ) {
            $vid = $item['variation_id'];
            $pid = $item['product_id'];
            $target_id = 0;

            if ( $vid && get_post_meta( $vid, '_jify_tax_enabled', true ) === 'yes' ) {
                $target_id = $vid;
            } elseif ( get_post_meta( $pid, '_jify_tax_enabled', true ) === 'yes' ) {
                $target_id = $pid;
            }

            if ( ! $target_id ) {
                continue;
            }

            $rate_percent = floatval( get_post_meta( $target_id, '_jify_tax_rate', true ) );
            if ( $rate_percent <= 0 ) {
                continue;
            }

            $line_total = isset( $item['line_total'] ) ? floatval( $item['line_total'] ) : 0;
            if ( $line_total <= 0 ) {
                continue;
            }

            $tax_items[] = array(
                'key' => $item_key,
                'line_total' => $line_total,
                'rate' => $rate_percent,
                'inc_ship' => get_post_meta( $target_id, '_jify_tax_inc_shipping', true ) === 'yes',
                'deduct_disc' => get_post_meta( $target_id, '_jify_tax_deduct_discount', true ) === 'yes',
            );
        }

        if ( empty( $tax_items ) ) {
            return;
        }

        foreach ( $tax_items as $tax_item ) {
            if ( $tax_item['inc_ship'] ) {
                $shipping_basis_total += $tax_item['line_total'];
            }
        }

        $tax_amount = 0;
        foreach ( $tax_items as $tax_item ) {
            $basis = $tax_item['line_total'];
            if ( $tax_item['deduct_disc'] && isset( $discount_items[ $tax_item['key'] ] ) ) {
                $basis -= floatval( $discount_items[ $tax_item['key'] ] );
            }
            if ( $basis < 0 ) {
                $basis = 0;
            }
            if ( $tax_item['inc_ship'] && $shipping_total > 0 && $shipping_basis_total > 0 ) {
                $basis += $shipping_total * ( $tax_item['line_total'] / $shipping_basis_total );
            }
            $tax_amount += $basis * ( $tax_item['rate'] / 100 );
        }

        $tax_amount = round( $tax_amount );

        if ( $tax_amount > 0 ) {
            $cart->add_fee( __( '稅金', 'jify-taxes' ), $tax_amount, false, '', 'jify_tax_fee' );
        }
    }

    public static function filter_cart_tax_totals( $tax_totals, $cart ) {
        if ( is_admin() && ! defined( 'DOING_AJAX' ) ) return $tax_totals;
        if ( ! self::is_jify_active_in_cart() ) return $tax_totals;
        return array();
    }

    public static function maybe_disable_wc_tax( $enabled ) {
        if ( is_admin() && ! defined( 'DOING_AJAX' ) ) return $enabled;
        if ( ! self::is_jify_active_in_cart() ) return $enabled;
        return false;
    }
}

Jify_Taxes_Standalone::init();
