<?php
/*
* Plugin Name: Tag Based User Role for WC
* Plugin URI:        https://sarathlal.com
* Description: Assigns roles based on product tags.
* Version:           1.0.0
* Author:            Sarathlal N
* Author URI:        https://sarathlal.com
* 
*/

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class WC_Tag_Based_Role_Assignment_TinyLab {

    private $option_name = 'tl_wc_tag_role_assignments';

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'create_settings_page' ) );
        add_action( 'admin_footer', array( $this, 'enqueue_inline_script' ) );
        add_action( 'admin_head', array( $this, 'page_style') );
        add_action( 'admin_post_save_tag_role_assignments', array( $this, 'save_tag_role_assignments' ) );
        add_action( 'woocommerce_order_status_completed', array( $this, 'assign_role_based_on_tags' ) );
    }

	public function create_settings_page() {
		add_submenu_page(
			'woocommerce',                      // Parent slug (WooCommerce menu)
			'Tag Based User Role',         // Page title
			'Tag Based User Role',               // Menu title
			'manage_options',                    // Capability
			'wc_tag_role_assignment',            // Menu slug
			array( $this, 'settings_page_content' ), // Callback function
			999,                                  // Position/order
		);
	}


public function page_style() {
    $screen = get_current_screen();
    if ( $screen->id !== 'woocommerce_page_wc_tag_role_assignment' ) {
        return;
    }
	?>
        <style>
			.tag-role-container-wrapper {margin-bottom:16px}
            #tag-role-container {
                display: flex;
                flex-direction: column;
                gap: 10px;
                margin-top: 15px;
                max-width: 600px;
            }
            .tag-role-row {
                display: flex;
                gap: 10px;
                align-items: center;
                padding: 10px;
                background-color: #f9f9f9;
                border: 1px solid #ddd;
                border-radius: 4px;
            }
            .tag-role-field {
                flex: 1;
            }
            .tag-role-field label {
                font-weight: bold;
                display: block;
                margin-bottom: 4px;
            }
            .remove-row {
                background-color: #d9534f;
                color: #fff;
                border: none;
                padding: 5px 10px;
                cursor: pointer;
                font-size: 14px;
                border-radius: 4px;
            }
            .remove-row:hover {
                background-color: #c9302c;
            }
            #add-tag-role-row {
                margin-top: 15px;
            }
        </style>
	<?php
}

public function settings_page_content() {
    // Retrieve saved tag-role assignments
    $tag_role_assignments = get_option( $this->option_name, array() );

    // Retrieve product tags and roles
    $product_tags = get_terms( array( 'taxonomy' => 'product_tag', 'hide_empty' => false ) );
    $roles = wp_roles()->roles;

    ?>
    <div class="wrap">
        <h1>Tag Based User Role Assignment</h1>
        <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>">
            <input type="hidden" name="action" value="save_tag_role_assignments">
            <?php wp_nonce_field( 'save_tag_role_assignments_action', 'tag_role_nonce' ); ?>
			<div class="tag-role-container-wrapper">
            <div id="tag-role-container">
                <?php foreach ( $tag_role_assignments as $index => $assignment ): ?>
                    <div class="tag-role-row">
                        <div class="tag-role-field">
                            <label>Tag</label>
                            <select name="wc_tag_role_assignments[<?php echo $index; ?>][tag]">
								<option value="" disabled selected>Select a tag</option>
                                <?php foreach ( $product_tags as $tag ): ?>
                                    <option value="<?php echo esc_attr( $tag->slug ); ?>" <?php selected( $assignment['tag'], $tag->slug ); ?>><?php echo esc_html( $tag->name ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="tag-role-field">
                            <label>Role</label>
                            <select name="wc_tag_role_assignments[<?php echo $index; ?>][role]">
								<option value="" disabled selected >Select a role</option>
                                <?php foreach ( $roles as $role_key => $role ): ?>
                                    <option value="<?php echo esc_attr( $role_key ); ?>" <?php selected( $assignment['role'], $role_key ); ?>><?php echo esc_html( $role['name'] ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="button" class="remove-row">Remove</button>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" id="add-tag-role-row" class="button">Assign Role</button>
            </div>
            <input type="submit" value="Save Settings" class="button button-primary">
        </form>

        <!-- Hidden div template -->
        <div id="hidden-row-template" style="display: none;">
            <div class="tag-role-row">
                <div class="tag-role-field">
                    <label>Tag</label>
                    <select name="wc_tag_role_assignments[INDEX][tag]">
						<option value="" disabled selected>Select a tag</option>
                        <?php foreach ( $product_tags as $tag ): ?>
                            <option value="<?php echo esc_attr( $tag->slug ); ?>"><?php echo esc_html( $tag->name ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="tag-role-field">
                    <label>Role</label>
                    <select name="wc_tag_role_assignments[INDEX][role]">
						<option value="" disabled selected>Select a role</option>
                        <?php foreach ( $roles as $role_key => $role ): ?>
                            <option value="<?php echo esc_attr( $role_key ); ?>"><?php echo esc_html( $role['name'] ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="button" class="remove-row">Remove</button>
            </div>
        </div>
    </div>
    <?php
}

public function enqueue_inline_script() {
    $screen = get_current_screen();
    if ( $screen->id !== 'woocommerce_page_wc_tag_role_assignment' ) {
        return;
    }	
    ?>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        let rowIndex = <?php echo count( get_option( $this->option_name, array() ) ); ?>;

        $('#add-tag-role-row').click(function() {
            // Clone the hidden template div
            let newRow = $('#hidden-row-template').children().clone().removeAttr('id').css('display', 'flex');

            // Update the index in name attributes
            newRow.find('select[name^="wc_tag_role_assignments"]').each(function() {
                let name = $(this).attr('name').replace('INDEX', rowIndex);
                $(this).attr('name', name);
            });

            // Append the new row to the container
            $('#tag-role-container').append(newRow);
            rowIndex++;
        });

        // Remove row functionality
        $(document).on('click', '.remove-row', function() {
            $(this).closest('.tag-role-row').remove();
        });
    });
    </script>
    <?php
}




    public function save_tag_role_assignments() {
        // Check nonce and user capabilities
        if ( ! isset( $_POST['tag_role_nonce'] ) || ! wp_verify_nonce( $_POST['tag_role_nonce'], 'save_tag_role_assignments_action' ) ) {
            wp_die( 'Security check failed' );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Permission denied' );
        }

        // Prepare data to save in structured format
        $assignments = array();

        if ( isset( $_POST['wc_tag_role_assignments'] ) && is_array( $_POST['wc_tag_role_assignments'] ) ) {
            foreach ( $_POST['wc_tag_role_assignments'] as $assignment ) {
                if ( isset( $assignment['tag'], $assignment['role'] ) ) {
                    $assignments[] = array(
                        'tag'  => sanitize_text_field( $assignment['tag'] ),
                        'role' => sanitize_text_field( $assignment['role'] ),
                    );
                }
            }
            update_option( $this->option_name, $assignments, 0 );
        } else {
            delete_option( $this->option_name );
        }

        // Redirect back to settings page with success message
        wp_redirect( add_query_arg( 'settings-updated', 'true', wp_get_referer() ) );
        exit;
    }
    
    
// Method to assign role based on product tags when order is completed
public function assign_role_based_on_tags( $order_id ) {
    // Retrieve tag-role assignments
    $tag_role_assignments = get_option( $this->option_name, array() );

    if ( empty( $tag_role_assignments ) ) {
        return; // Exit if no tag-role assignments
    }

    // Get the order and the user who placed it
    $order = wc_get_order( $order_id );
    $user_id = $order->get_user_id();
    
    if ( ! $user_id ) {
        return; // Exit if order is not associated with a user
    }

    $user = new WP_User( $user_id );

    // Iterate over order items
    foreach ( $order->get_items() as $item ) {
        $product_id = $item->get_product_id();
        
        // Check each tag-role assignment
        foreach ( $tag_role_assignments as $assignment ) {
            $tag_slug = $assignment['tag'];
            $role = $assignment['role'];

            // Check if product has the assigned tag
            if ( has_term( $tag_slug, 'product_tag', $product_id ) ) {
                // Assign the role to the user if they don't already have it
                if ( ! in_array( $role, $user->roles ) ) {
                    $user->add_role( $role );
                }
            }
        }
    }
}
    
    
    
}

new WC_Tag_Based_Role_Assignment_TinyLab();


if (!function_exists('write_log')) {
	function write_log ( $log )  {
		if ( true === WP_DEBUG ) {
			if ( is_array( $log ) || is_object( $log ) ) {
				error_log( print_r( $log, true ) );
			} else {
				error_log( $log );
			}
		}
	}
}
