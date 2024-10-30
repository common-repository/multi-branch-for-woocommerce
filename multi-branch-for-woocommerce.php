<?php

/**
 * Plugin Name: Multi Branch for WooCommerce
 * Plugin URI: https://en.condless.com/multi-branch-for-woocommerce/
 * Description: WooCommerce plugin for configuring store with multiple branches.
 * Version: 1.0.6
 * Author: Condless
 * Author URI: https://en.condless.com/
 * Developer: Condless
 * Developer URI: https://en.condless.com/
 * Contributors: condless
 * Text Domain: multi-branch-for-woocommerce
 * Domain Path: /i18n/languages
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 5.2
 * Tested up to: 6.5
 * Requires PHP: 7.0
 * WC requires at least: 3.4
 * WC tested up to: 8.9
 */

/**
 * Exit if accessed directly
 */
defined( 'ABSPATH' ) || exit;

/**
 * Check if WooCommerce is active
 */
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) || get_site_option( 'active_sitewide_plugins') && array_key_exists( 'woocommerce/woocommerce.php', get_site_option( 'active_sitewide_plugins' ) ) ) {

	/**
	 * Multi Branch for WooCommerce class.
	 */
	class WC_MBW {

		/**
		 * Construct class
		 */
		public function __construct() {
			add_action( 'before_woocommerce_init', function() {
				if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
					\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
				}
			} );
			add_action( 'plugins_loaded', [ $this, 'init' ] );
			$this->branches = $this->wc_get_branches();
			$this->shipping_methods_users = $this->wc_get_shipping_methods_users();
		}

		/**
		 * WC init
		 */
		public function init() {
			$this->init_textdomain();
			$this->init_settings();
			$this->init_functions();
		}

		/**
		 * Loads text domain for internationalization
		 */
		public function init_textdomain() {
			load_plugin_textdomain( 'multi-branch-for-woocommerce', false, dirname( plugin_basename( __FILE__ ) ) . '/i18n/languages' );
		}

		/**
		 * WC settings init
		 */
		public function init_settings() {
			add_action( 'admin_init', [ $this, 'wc_orders_manager_user_role' ] );
			add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), [ $this, 'wc_update_settings_link' ] );
			add_filter( 'plugin_row_meta', [ $this, 'wc_add_plugin_links' ], 10, 4 );
			add_filter( 'woocommerce_settings_tabs_array', [ $this, 'wc_add_settings_tab' ], 50 );
			add_action( 'woocommerce_settings_tabs_mbw', [ $this, 'wc_settings_tab' ] );
			add_action( 'woocommerce_update_options_mbw', [ $this, 'wc_update_settings' ] );
			add_filter( 'woocommerce_product_data_tabs', [ $this, 'wc_product_multi_branch_tab' ] );
			add_action( 'woocommerce_product_data_panels', [ $this, 'wc_product_multi_branch_panel' ] );
			add_action( 'woocommerce_admin_process_product_object', [ $this, 'wc_save_product_multi_branch_options' ] );
			add_filter( 'manage_edit-product_columns', [ $this, 'wc_shipping_methods_column' ] );
			add_action( 'manage_posts_custom_column', [ $this, 'wc_populate_shipping_methods_column' ] );
			add_shortcode( 'mbw_branches', [ $this, 'wc_branches_shortcode' ] );
			add_filter( 'wp_footer', [ $this, 'wc_allocated_branch_scripts' ] );
			add_action( 'wp_ajax_wc_save_allocated_branch', [ $this, 'wc_save_allocated_branch' ] );
			add_action( 'wp_ajax_nopriv_wc_save_allocated_branch', [ $this, 'wc_save_allocated_branch' ] );
		}

		/**
		 * WC functions init
		 */
		public function init_functions() {
			add_filter( 'woocommerce_post_class', [ $this, 'wc_set_product_visibility' ], 10, 2 );
			add_filter( 'woocommerce_add_to_cart_validation', [ $this, 'wc_shipping_methods_products_validation' ], 10, 2 );
			add_filter( 'woocommerce_shipping_chosen_method', [ $this, 'wc_set_default_checkout_shipping_method' ], 5, 2 );
			add_action( 'woocommerce_after_checkout_validation', [ $this, 'wc_shipping_methods_items_validation' ], 10, 2 );
			add_action( 'woocommerce_checkout_create_order', [ $this, 'wc_order_add_shipping_method_meta' ], 10, 2 );
			add_filter( 'woocommerce_email_recipient_new_order', [ $this, 'wc_shipping_methods_recipients' ], 10, 2 );
			add_filter( 'woocommerce_email_recipient_failed_order', [ $this, 'wc_shipping_methods_recipients' ], 10, 2 );
			add_filter( 'woocommerce_email_recipient_cancelled_order', [ $this, 'wc_shipping_methods_recipients' ], 10, 2 );
			add_filter( 'woocommerce_include_processing_order_count_in_menu', [ $this, 'wc_remove_menu_order_count' ] );
			add_action( 'pre_get_posts', [ $this, 'wc_shop_order_shipping_methods_filter' ] );
			add_filter( 'get_post_metadata', [ $this, 'wc_edit_orders_permission' ], 10, 5 );
			add_action( 'woocommerce_admin_order_data_after_order_details', [ $this, 'wc_restrict_edit_order_direct_access' ] );
			add_filter( 'acf/settings/remove_wp_meta_box', '__return_false' );
		}

		/**
		 * Add the Orders manager user role
		 */
		public function wc_orders_manager_user_role() {
			add_role( 'orders_manager', __( 'Orders manager', 'multi-branch-for-woocommerce' ), [
				'read'		=> true,
				'view_admin_dashboard'		=> true,
				'assign_shop_order_terms'		=> true,
				'delete_others_shop_orders'		=> true,
				'delete_private_shop_orders'	=> true,
				'delete_published_shop_orders'	=> true,
				'delete_shop_order'		=> true,
				'delete_shop_order_terms'		=> true,
				'delete_shop_orders'		=> true,
				'edit_others_shop_orders'		=> true,
				'edit_private_shop_orders'		=> true,
				'edit_published_shop_orders'	=> true,
				'edit_shop_order'		=> true,
				'edit_shop_order_terms'		=> true,
				'edit_shop_orders'		=> true,
				'manage_shop_order_terms'		=> true,
				'publish_shop_orders'		=> true,
				'read_private_shop_orders'		=> true,
				'read_shop_order'		=> true,
			] );
		}

		/**
		 * Add plugin links to the plugin menu
		 * @param mixed $links
		 * @return mixed
		 */
		public function wc_update_settings_link( $links ) {
			array_unshift( $links, '<a href=' . esc_url( add_query_arg( 'page', 'wc-settings&tab=mbw', get_admin_url() . 'admin.php' ) ) . '>' . __( 'Settings' ) . '</a>' );
			return $links;
		}

		/**
		 * Add plugin meta links to the plugin menu
		 * @param mixed $links_array
		 * @param mixed $plugin_file_name
		 * @param mixed $plugin_data
		 * @param mixed $status
		 * @return mixed
		 */
		public function wc_add_plugin_links( $links_array, $plugin_file_name, $plugin_data, $status ) {
			if ( strpos( $plugin_file_name, basename( __FILE__ ) ) ) {
				$sub_domain = 'he_IL' === get_locale() ? 'www' : 'en';
				$links_array[] = "<a href=https://$sub_domain.condless.com/multi-branch-for-woocommerce/>" . __( 'Docs', 'woocommerce' ) . '</a>';
				$links_array[] = "<a href=https://$sub_domain.condless.com/contact/>" . _x( 'Contact', 'Theme starter content' ) . '</a>';
			}
			return $links_array;
		}

		/**
		 * Add a new settings tab to the WooCommerce settings tabs array
		 * @param array $settings_tabs
		 * @return array
		 */
		public function wc_add_settings_tab( $settings_tabs ) {
			$settings_tabs['mbw'] = __( 'Multi Branch', 'multi-branch-for-woocommerce' );
			return $settings_tabs;
		}

		/**
		 * Use the WooCommerce admin fields API to output settings via the @see woocommerce_admin_fields() function
		 * @uses woocommerce_admin_fields()
		 * @uses self::wc_get_settings()
		 */
		public function wc_settings_tab() {
			woocommerce_admin_fields( self::wc_get_settings() );
		}

		/**
		 * Use the WooCommerce options API to save settings via the @see woocommerce_update_options() function
		 * @uses woocommerce_update_options()
		 * @uses self::wc_get_settings()
		 */
		public function wc_update_settings() {
			woocommerce_update_options( self::wc_get_settings() );
		}

		/**
		 * Get all the settings for this plugin for @see woocommerce_admin_fields() function
		 * @return array Array of settings for @see woocommerce_admin_fields() function
		 */
		public function wc_get_settings() {
			$settings = [
				'users_section'	=> [
					'name'	=> __( 'Shipping methods', 'woocommerce' ) . ' ' . __( 'Users' ),
					'type'	=> 'title',
					'id'	=> 'wc_mbw_users_section',
					'desc'	=> __( 'Config the Shipping Methods Users (branch managers)', 'multi-branch-for-woocommerce' ) . '. <a href=https://' . ( 'he_IL' === get_locale() ? 'www' : 'en' ) . '.condless.com/contact/>' . __( 'Support' ) . '</a>',
				],
				'users_count'	=> [
					'name'		=> __( 'Users' ),
					'type'		=> 'number',
					'id'		=> 'wc_mbw_users_count',
					'default'	=> '2',
					'desc_tip'	=> __( 'The number of shipping methods users to configured, orders which not assigned to any user will be allocated to the first user.', 'multi-branch-for-woocommerce' ),
					'custom_attributes'	=> [
						'min'	=> 0,
						'step'	=> 1,
					],
				],
			];
			for ( $i = 1; $i <= get_option( 'wc_mbw_users_count', 2 ); $i++ ) {
				$settings += [
					"shipping_methods_user_id_$i"	=> [
						'name'		=> "#{$i} " . __( 'User ID' ),
						'type'		=> 'number',
						'desc_tip'	=> __( 'The id of the user.', 'multi-branch-for-woocommerce' ),
						'id'		=> "wc_mbw_shipping_methods_user_id_$i",
					],
					"shipping_methods_user_shipping_methods_$i"	=> [
						'name'		=> "#{$i} " . __( 'Shipping method instance ID.', 'woocommerce' ),
						'type'		=> 'text',
						'desc_tip'	=> __( 'The instance id of the shipping methods of the shipping methods user seperated by comma (,).', 'multi-branch-for-woocommerce' ),
						'id'		=> "wc_mbw_shipping_methods_user_shipping_methods_$i",
					],
				];
			}
			$settings += [
				'users_section_end'	=> [
					'type'	=> 'sectionend',
					'id'	=> 'wc_mbw_users_section_end'
				],
				'branches_section'	=> [
					'name'	=> __( 'Branches', 'multi-branch-for-woocommerce' ) . ' ' . __( 'Shipping methods', 'woocommerce' ),
					'type'	=> 'title',
					'id'	=> 'wc_mbw_branches_section',
					'desc'	=> __( 'Config the Branches Shipping Methods (branch areas), shipping method should not be assigned to more than 1 branch', 'multi-branch-for-woocommerce' ),
				],
				'branches_count'	=> [
					'name'		=> __( 'Branches', 'multi-branch-for-woocommerce' ),
					'type'		=> 'number',
					'default'	=> '2',
					'id'		=> 'wc_mbw_branches_count',
					'desc_tip'	=> __( 'The number of branches to configured, by default the users will be allocated to the first branch.', 'multi-branch-for-woocommerce' ),
					'custom_attributes'	=> [
						'min'	=> 0,
						'step'	=> 1,
					],
				],
			];
			for ( $i = 1; $i <= get_option( 'wc_mbw_branches_count', 2 ); $i++ ) {
				$settings += [
					"branch_id_$i"	=> [
						'name'		=> "#{$i} " . __( 'Branch', 'multi-branch-for-woocommerce' ) . ' ' . __( 'ID', 'woocommerce' ),
						'type'		=> 'text',
						'desc_tip'	=> __( 'Unique ID of the branch.', 'multi-branch-for-woocommerce' ),
						'id'		=> "wc_mbw_branch_id_$i",
					],
					"branch_name_$i"	=> [
						'name'		=> "#{$i} " . __( 'Branch', 'multi-branch-for-woocommerce' ) . ' ' . __( 'Name', 'woocommerce' ),
						'type'		=> 'text',
						'desc_tip'	=> __( 'The name of the branch.', 'multi-branch-for-woocommerce' ),
						'id'		=> "wc_mbw_branch_name_$i",
					],
					"branch_shipping_methods_$i"	=> [
						'name'		=> "#{$i} " . __( 'Branch', 'multi-branch-for-woocommerce' ) . ' ' . __( 'Shipping methods', 'woocommerce' ),
						'type'		=> 'text',
						'desc_tip'	=> __( 'The instance id of the shipping methods of the branch seperated by comma (,).', 'multi-branch-for-woocommerce' ),
						'id'		=> "wc_mbw_branch_shipping_methods_$i",
					],
				];
			}
			$settings += [
				'branches_section_end'	=> [
					'type'	=> 'sectionend',
					'id'	=> 'wc_mbw_branches_section_end'
				],
			];
			return apply_filters( 'wc_mbw_settings', $settings );
		}
		
		/**
		 * Add custom Multi Branch tab to the products
		 * @param mixed $tabs
		 * @return mixed
		 */
		public function wc_product_multi_branch_tab( $tabs ) {
			echo '<style>#woocommerce-product-data ul.wc-tabs li.multi_branch_options a:before { font-family: WooCommerce; content: "\e900"; }</style>';
			$tabs['multi_branch'] = [
				'label'		=> __( 'Multi Branch', 'multi-branch-for-woocommerce' ),
				'class'		=> [ 'hide_if_virtual' ],
				'target'	=> 'multi_branch_product_data',
			];
			return $tabs;
		}

		/**
		 * Add the Multi Branch settings to products
		 */
		public function wc_product_multi_branch_panel() {
			echo '<div id="multi_branch_product_data" class="panel woocommerce_options_panel hidden">';
			woocommerce_wp_text_input( [
				'id'		=> '_mbw_shipping_methods',
				'label'		=> __( 'Shipping method instance ID.', 'woocommerce' ),
				'description'	=> __( 'The instance id of the shipping methods which the product can be provided by seperated by comma (,), leave empty for all..', 'multi-branch-for-woocommerce' ),
				'desc_tip'		=> true,
			] );
			do_action( 'woocommerce_product_options_multi_branch_product_data' );
			echo '</div>';
		}

		/**
		 * Save the products settings
		 * @param mixed $product
		 */
		public function wc_save_product_multi_branch_options( $product ) {
			$product->update_meta_data( '_mbw_shipping_methods', isset( $_POST['_mbw_shipping_methods'] ) ? wc_clean( wp_unslash( $_POST['_mbw_shipping_methods'] ) ) : null );
		}

		/**
		 * Add product shipping methods admin column title
		 * @param mixed $columns_array
		 * @return mixed
		 */
		public function wc_shipping_methods_column( $columns_array ) {
			$columns_array['mbw_shipping_methods'] = __( 'Shipping methods', 'woocommerce' );
			return $columns_array;
		}

		/**
		 * Populate product shipping methods admin column
		 * @param mixed $column_name
		 * @return mixed
		 */
		public function wc_populate_shipping_methods_column( $column_name ) {
			if ( 'mbw_shipping_methods' === $column_name ) {
				global $product;
				if ( $product ) {
					echo $product->get_meta( '_mbw_shipping_methods' );
				}
			}
		}

		/**
		 * Add branches shortcode
		 * @param mixed $atts
		 * @return mixed
		 */
		public function wc_branches_shortcode( $atts ) {
			if ( apply_filters( 'mbw_branches_shortcode_enabled', ! ( is_cart() || is_checkout() ) ) ) {
				$atts = shortcode_atts( [
					'field_id'	=> 'mbw_branch',
					'label'		=> __( 'Branch', 'multi-branch-for-woocommerce' ),
					'class'		=> [ 'form-row', 'form-row-first' ],
				], $atts, 'mbw_branches' );
				if ( function_exists( 'is_plugin_active' ) && is_plugin_active( 'woocommerce-checkout-manager/woocommerce-checkout-manager.php' ) ) {
					remove_all_filters( 'woocommerce_form_field_select' );
				}
				foreach ( $this->branches as $branch_id => $branch ) {
					$branches[ $branch_id ] = $branch['name'];
				}
				ob_start();
				woocommerce_form_field( $atts['field_id'], [
					'type'		=> 'select',
					'required'	=> true,
					'label'		=> $atts['label'],
					'class'		=> $atts['class'],
					'options'	=> $branches ?? [],
				] );
				return ob_get_clean();
			}
		}

		/**
		 * Activate allocated branch ajax
		 */
		public function wc_allocated_branch_scripts() {
			?>
			<style>
			#mbw_branch {
				visibility: hidden;
			}
			</style>
			<script type="text/javascript">
			jQuery( function( $ ) {
				$( document ).ready( function() {
					var data = {
						'action'	: 'wc_save_allocated_branch',
					};
					$.post( '<?php echo admin_url( 'admin-ajax.php' ); ?>', data, function( response ) {
						wc_apply_allocated_branch( response );
						$( '#mbw_branch' ).css( 'visibility', 'visible' );
					} );
				} );
				$( '#mbw_branch' ).on( 'change', function() {
					var data = {
						'action'		: 'wc_save_allocated_branch',
						'mbw_branch'	: $( this ).val(),
					};
					$.post( '<?php echo admin_url( 'admin-ajax.php' ); ?>', data, function( response ) {
						wc_apply_allocated_branch( response );
					} );
				} );
				function wc_apply_allocated_branch( response ) {
					$( "[class*='mbw-restrict']" ).show();
					if ( response.hidden_classes ) {
						$( response.hidden_classes ).hide();
					}
					if ( response.allocated_branch ) {
						$( '#mbw_branch' ).each( function() {
							if ( $( this ).val() !== response.allocated_branch ) {
								$( this ).val( response.allocated_branch );
							}
						} );
					}
				}
			} );
			</script>
			<?php
		}

		/**
		 * Save the allocated branch in customer session
		 */
		public function wc_save_allocated_branch() {
			if ( isset( WC()->session ) ) {
				if ( isset( $_POST['mbw_branch'] ) ) {
					if ( ! WC()->session->has_session() ) {
						WC()->session->set_customer_session_cookie( true );
					}
					WC()->session->set( 'mbw_branch', wc_clean( $_POST['mbw_branch'] ) );
					$mbw_data['allocated_branch'] = wc_clean( $_POST['mbw_branch'] );
				} else {
					$mbw_data['allocated_branch'] = $this->wc_get_customer_branch();
				}
				if ( $mbw_data['allocated_branch'] ) {
					$classes[] = 'mbw-restrict-branch-' . $mbw_data['allocated_branch'];
				}
				foreach ( $this->branches as $branch_id => $branch ) {
					if ( $branch_id !== $mbw_data['allocated_branch'] ) {
						$classes[] = 'mbw-restrict-all-branches-except-' . $branch_id;
					}
				}
				$mbw_data['hidden_classes'] = ! empty( $classes ) ? '.' . implode( ', .', $classes ) : '';
				wp_send_json( $mbw_data );
			}
			wp_send_json( false );
		}

		/**
		 * Add the relevant restricted branch classes to the product
		 * @param mixed $classes
		 * @param mixed $product
		 * @return mixed
		 */
		public function wc_set_product_visibility( $classes, $product ) {
			if ( apply_filters( 'mbw_products_add_branch_restrict_classes_enabled', ! $product->is_virtual(), $product ) ) {
				$branches = $this->branches;
				foreach ( $branches as $branch_id => $branch ) {
					foreach ( $branch['shipping_methods'] as $shipping_method ) {
						if ( $this->wc_product_is_provided( $product, $shipping_method ) ) {
							continue 2;
						}
					}
					$classes[] = 'mbw-restrict-branch-' . $branch_id;
				}
			}
			return $classes;
		}

		/**
		 * Validate the add to cart by the customer's allocated branch
		 * @param mixed $passed
		 * @param mixed $product_id
		 * @return mixed
		 */
		public function wc_shipping_methods_products_validation( $passed, $product_id ) {
			$product = wc_get_product( $product_id );
			if ( apply_filters( 'mbw_add_to_cart_branch_validation_enabled', ! $product->is_virtual(), $product ) ) {
				$allocated_branch = $this->wc_get_customer_branch();
				if ( $allocated_branch ) {
					$branches = $this->branches;
					if ( isset( $branches[ $allocated_branch ] ) ) {
						foreach ( $branches[ $allocated_branch ]['shipping_methods'] as $shipping_method ) {
							if ( $this->wc_product_is_provided( $product, $shipping_method ) ) {
								return $passed;
							}
						}
						wc_add_notice( apply_filters( 'mbw_add_to_cart_validation_notice', $product->get_title() . ' ' . __( 'is not provided by branch', 'multi-branch-for-woocommerce' ) . ': ' . $branches[ $allocated_branch ]['name'], $product, $branches[ $allocated_branch ] ), 'notice' );
						if ( apply_filters( 'mbw_debug_mode_enabled', true ) ) {
							wc_get_logger()->debug( 'MBW: ' . $allocated_branch . ' ' . $product_id );
						}
					}
				}
			}
			return $passed;
		}

		/**
		 * Set default shipping method by customer's allocated branch
		 * @param mixed $default
		 * @param mixed $rates
		 * @return mixed
		 */
		public function wc_set_default_checkout_shipping_method( $default, $rates ) {
			$allocated_branch = $this->wc_get_customer_branch();
			if ( $allocated_branch ) {
				$branches = $this->branches;
				if ( ! in_array( $default, $branches[ $allocated_branch ]['shipping_methods'] ) ) {
					foreach ( $rates as $rate_id => $rate ) {
						if ( in_array( explode( ':', $rate_id )[1], $branches[ $allocated_branch ]['shipping_methods'] ) ) {
							return $rate_id;
						}
					}
				}
			}
			return $default;
		}

		/**
		 * Validate the cart items by the selected shipping method on checkout
		 * @param mixed $fields
		 * @param mixed $erorrs
		 */
		public function wc_shipping_methods_items_validation( $fields, $errors ) {
			$chosen_shipping_methods = WC()->session->get( 'chosen_shipping_methods' );
			foreach ( WC()->shipping()->get_packages() as $i => $package ) {
				$shipping_method = explode( ':', $chosen_shipping_methods[ $i ] )[1];
				foreach ( $package['contents'] as $cart_item ) {
					$parent_id = $cart_item['data']->get_parent_id();
					$product = ! empty( $parent_id ) ? wc_get_product( $parent_id ) : $cart_item['data'];
					if ( ! $this->wc_product_is_provided( $product, $shipping_method ) ) {
						$forbidden_items[] = $product->get_title();
					}	
				}
				if ( ! empty( $forbidden_items ) ) {
					$shipping_method_title = get_option( str_replace( ':', '_', "woocommerce_{$chosen_shipping_methods[ $i ]}_settings" ) )['title'];
					$errors->add( 'shipping', apply_filters( 'mbw_checkout_validation_notice', __( 'The following products can not be provided to your zone via', 'multi-branch-for-woocommerce' ) . ' ' . $shipping_method_title . ': ' . implode( ', ', $forbidden_items ), $forbidden_items, $shipping_method_title ) );
					if ( apply_filters( 'mbw_debug_mode_enabled', true ) ) {
						wc_get_logger()->debug( 'MBW: ' . $shipping_method . ' ' . wc_print_r( $forbidden_items, true ) );
					}
				}
			}
		}

		/**
		 * Check if product is provided in the specific shipping method
		 * @param mixed $product
		 * @param mixed $shipping_method
		 * @return mixed
		 */		
		public function wc_product_is_provided( $product, $shipping_method ) {
			$product_shipping_methods = preg_split( '/\s*,\s*/', $product->get_meta( '_mbw_shipping_methods' ), -1, PREG_SPLIT_NO_EMPTY );
			return apply_filters( 'mbw_product_is_provided', empty( $product_shipping_methods ) || in_array( $shipping_method, $product_shipping_methods ), $product, $shipping_method, $product_shipping_methods );
		}

		/**
		 * Add the order shipping method id in the order meta
		 * @param mixed $order
		 * @param mixed $data
		 */
		public function wc_order_add_shipping_method_meta( $order, $data ) {
			$shipping_methods = $order->get_shipping_methods();
			if ( $shipping_methods ) {
				$shipping_method_id = reset( $shipping_methods )->get_instance_id(); // Only the first order's shipping method is taken only account, so doesn't support multi package configuration.
				$order->update_meta_data( 'mbw_shipping_method', $shipping_method_id );
				$branches = $this->branches;
				$allocated_branch = $this->wc_get_customer_branch();
				if ( ! $allocated_branch || ! isset( $branches[ $allocated_branch ] ) || ! in_array( $shipping_method_id, $branches[ $allocated_branch ]['shipping_methods'] ) ) {
					foreach ( $branches as $branch_id => $branch ) {
						if ( in_array( $shipping_method_id, $branch['shipping_methods'] ) ) {
							WC()->session->set( 'mbw_branch', $branch_id );
							break;
						}
					}
				}
			}
		}

		/**
		 * Set order recipients by its shipping method
		 * @param mixed $recipient
		 * @param mixed $order
		 * @return mixed
		 */
		public function wc_shipping_methods_recipients( $recipient, $order ) {
			$shipping_methods_users = $this->shipping_methods_users;
			if ( is_a( $order, 'WC_Order' ) ) {
				$shipping_method = $order->get_meta( 'mbw_shipping_method' );
				if ( $shipping_method ) {
					foreach ( $shipping_methods_users as $shipping_methods_user_id => $shipping_methods_user ) {
						if ( in_array( $shipping_method, $shipping_methods_user['shipping_methods'] ) ) {
							$user = get_userdata( $shipping_methods_user_id );
							if ( $user ) {
								$additional_recipients[] = $user->user_email;
							}
						}
					}
				}
			}
			if ( empty( $additional_recipients ) ) {
				foreach ( $shipping_methods_users as $shipping_methods_user_id => $shipping_methods_user ) {
					$user = get_userdata( $shipping_methods_user_id );
					if ( $user ) {
						$additional_recipients[] = $user->user_email;
						break;
					}
				}
			}
			return $recipient . ( ! empty( $additional_recipients ) ? ',' . implode( ', ', $additional_recipients ) : '' );
		}

		/**
		 * Remove order menu count for shipping methods users
		 * @param mixed $enabled
		 * @return mixed
		 */
		public function wc_remove_menu_order_count( $enabled ) {
			return ! isset ( $this->shipping_methods_users[ get_current_user_id() ] );
		}

		/**
		 * Display only relevant orders in the orders screen
		 * @param mixed $query
		 */
		public function wc_shop_order_shipping_methods_filter( $query ) {
			if ( function_exists( 'get_current_screen' ) ) {
				$screen = get_current_screen();
				if ( isset( $screen, $screen->id ) && 'edit-shop_order' === $screen->id ) {
					$shipping_methods_users = $this->shipping_methods_users;
					$current_user = get_current_user_id();
					if ( isset( $shipping_methods_users[ $current_user ] ) ) {
						add_filter( 'views_edit-shop_order', '__return_empty_string' );
						add_action( 'admin_notices', [ $this, 'wc_add_restricted_user_notice' ] );
						$allowed_orders_status = apply_filters( 'mbw_allowed_order_status', [], $current_user );
						if ( ! empty( $allowed_orders_status ) ) {
							$valid_order_statuses = wc_get_order_statuses();
							foreach ( $allowed_orders_status as $order_status ) {
								if ( isset( $valid_order_statuses[ "wc-$order_status" ] ) ) {
									$raw_orders_status[] = "wc-$order_status";
								}
							}
							if ( ! empty( $raw_orders_status ) ) {
								$query->set('post_status', $raw_orders_status );
							} else {
								wp_die( __( 'There are no orders with the order status you are allowed to view', 'multi-branch-for-woocommerce' ) );
							}
						}
						$excluded_shipping_methods = $this->wc_get_user_excluded_shipping_methods( $current_user );
						if ( $excluded_shipping_methods ) {
							$added_meta_query = [
								'relation' => 'OR',
								[
									'key'		=> 'mbw_shipping_method',
									'value'		=> $excluded_shipping_methods,
									'compare'	=> 'NOT IN',
								],
								[
									'key'		=> 'mbw_shipping_method',
									'compare'	=> 'NOT EXISTS',
								],
							];
						} else {
							$added_meta_query[] = [
								'key'		=> 'mbw_shipping_method',
								'value'		=> ! empty( $shipping_methods_users[ $current_user ]['shipping_methods'] ) ? $shipping_methods_users[ $current_user ]['shipping_methods'] : [ '0' ],
								'compare'	=> 'IN',
							];
						}
						$meta_query = $query->get( 'meta_query' );
						$query->set( 'meta_query', ( ! empty( $meta_query ) ? $meta_query : [] ) + $added_meta_query );
					}
				}
			}
		}

		/**
		 * Echo message for orders manager on orders screen
		 */
		public function wc_add_restricted_user_notice() {
			echo '<div class="notice"><p>' . __( 'You are restricted to see specific orders', 'multi-branch-for-woocommerce' ) . '</p></div>';
		}

		/**
		 * Restrict order edit for shipping methods users
		 * @param mixed $value
		 * @param mixed $object_id
		 * @param mixed $meta_key
		 * @param mixed $single
		 * @param mixed $meta_type
		 * @return mixed
		 */
		public function wc_edit_orders_permission( $value, $object_id, $meta_key, $single, $meta_type ) {
			if ( '_edit_lock' === $meta_key ) {
				$order = wc_get_order( $object_id );
				if ( $order ) {
					$order_shipping_method = $order->get_meta( 'mbw_shipping_method' );
					$shipping_methods_users = $this->shipping_methods_users;
					$current_user = get_current_user_id();
					$excluded_shipping_methods = $this->wc_get_user_excluded_shipping_methods( $current_user );
					$allowed_orders_status = apply_filters( 'mbw_allowed_order_status', [], $current_user );
					if ( isset( $shipping_methods_users[ $current_user ] ) && ( 'edit' !== apply_filters( 'mbw_shipping_methods_user_order_permission', 'edit', $current_user ) || ! empty( $allowed_orders_status ) && ! in_array( $order->get_status(), $allowed_orders_status ) || ! in_array( $order_shipping_method, $shipping_methods_users[ $current_user ]['shipping_methods'] ) && ( ! $excluded_shipping_methods || in_array( $order_shipping_method, $excluded_shipping_methods ) ) ) ) {
						return time() . ':' . apply_filters( 'mbw_edit_lock_admin_user_id', 1 );
					}
				}
			}
			return $value;
		}

		/**
		 * Restrict direct access to order for shipping methods users
		 * @param mixed $order
		 */
		public function wc_restrict_edit_order_direct_access( $order ) {
			$order_shipping_method = $order->get_meta( 'mbw_shipping_method' );
			$shipping_methods_users = $this->shipping_methods_users;
			$current_user = get_current_user_id();
			$excluded_shipping_methods = $this->wc_get_user_excluded_shipping_methods( $current_user );
			$allowed_orders_status = apply_filters( 'mbw_allowed_order_status', [], $current_user );
			if ( isset( $shipping_methods_users[ $current_user ] ) && ( 'view' === apply_filters( 'mbw_shipping_methods_user_order_permission', 'edit', $current_user ) || ! empty( $allowed_orders_status ) && ! in_array( $order->get_status(), $allowed_orders_status ) || ! in_array( $order_shipping_method, $shipping_methods_users[ $current_user ]['shipping_methods'] ) && ( ! $excluded_shipping_methods || in_array( $order_shipping_method, $excluded_shipping_methods ) ) ) ) {
				if ( ! wp_safe_redirect( admin_url() ) ) {
					wp_die( esc_html__( 'You do not have permission to edit this order', 'woocommerce' ) );
				}
			}
		}

		/**
		 * Get the excluded shipping methods (used for the default shipping methods user)
		 * @param mixed $user_id
		 * @return mixed
		 */
		public function wc_get_user_excluded_shipping_methods( $user_id ) {
			if ( apply_filters( 'mbw_default_shipping_methods_user_enabled', true ) ) {
				$shipping_methods_users = $this->shipping_methods_users;
				if ( key( $shipping_methods_users ) === $user_id ) {
					$excluded_shipping_methods = [];
					foreach ( $shipping_methods_users as $shipping_method_user_id => $shipping_methods_user ) {
						if ( $shipping_method_user_id != $user_id ) {
							$excluded_shipping_methods = array_merge( $excluded_shipping_methods, $shipping_methods_user['shipping_methods'] );
						}
					}
					$calculated_excluded_shipping_methods = array_diff( $excluded_shipping_methods, $shipping_methods_users[ $user_id ]['shipping_methods'] );
					return $calculated_excluded_shipping_methods ? $calculated_excluded_shipping_methods : [ '0' ];
				}
			}
			return false;
		}

		/**
		 * Get the customer allocated branch
		 * @return mixed
		 */
		function wc_get_customer_branch() {
			$branches= $this->branches;
			if ( ! empty( $branches ) ) {
				if ( isset( WC()->session ) ) {
					$session_branch = WC()->session->get( 'mbw_branch' );
					if ( isset( $branches[ $session_branch ] ) ) {
						return $session_branch;
					}
				}
				return apply_filters( 'mbw_default_customer_allocated_branch', key( $branches ), $branches );
			}
			return false;
		}

		/**
		 * Get the shipping methods users
		 * @return mixed
		 */
		public function wc_get_shipping_methods_users() {
			for ( $i = 1; $i <= get_option( 'wc_mbw_users_count' ); $i++ ) {
				$user_id = get_option( "wc_mbw_shipping_methods_user_id_$i" );
				if ( $user_id ) {
					$shipping_methods_users[ $user_id ]['shipping_methods'] = preg_split( '/\s*,\s*/', get_option( "wc_mbw_shipping_methods_user_shipping_methods_$i" ), -1, PREG_SPLIT_NO_EMPTY );
				}
			}
			return apply_filters( 'mbw_get_shipping_methods_users', $shipping_methods_users ?? [] );
		}

		/**
		 * Get the shipping methods users
		 * @return mixed
		 */
		public function wc_get_branches() {
			for ( $i = 1; $i <= get_option( 'wc_mbw_branches_count' ); $i++ ) {
				$branch_id = get_option( "wc_mbw_branch_id_$i" );
				if ( $branch_id && ! isset( $branches[ $branch_id ] ) ) {
					$branches[ $branch_id ]['name'] = ! empty( get_option( "wc_mbw_branch_name_$i" ) ) ? get_option( "wc_mbw_branch_name_$i" ) : $branch_id;
					$branches[ $branch_id ]['shipping_methods'] = preg_split( '/\s*,\s*/', get_option( "wc_mbw_branch_shipping_methods_$i" ), -1, PREG_SPLIT_NO_EMPTY );
				}
			}
			return apply_filters( 'mbw_get_branches_shipping_methods', $branches ?? [] );
		}
	}

	/**
	 * Instantiate class
	 */
	$multi_branch_for_woocommerce = new WC_MBW();
};
