<?php

class Resourcespace_Resource_Tracking_Admin {

	protected static $instance;

	/**
	 * Create singleton instance.
	 * @return Resourcespace_Resource_Tracking_Admin
	 */
	public static function get_instance() {

		if ( ! self::$instance ) {
			self::$instance = new self();
			self::$instance->setup_actions();
		}

		return self::$instance;
	}

	public function setup_actions() {
		// Add to admin menu settings
		add_action( 'admin_menu', array( $this, 'rrt_add_pages' ) );
	}

	/**
	 * Add pages to admin menu
	 */
	function rrt_add_pages() {
		// Add a new submenu under Settings:
		add_options_page( __( 'Resourcespace Tracking', 'resourcespacetracking' ), __( 'Resourcespace Tracking', 'resourcespacetracking' ), 'manage_options', 'resourcespacetracking', array( $this, 'rrt_settings_page' ) );
	}

	/**
	 * The admin settings page template
	 */
	function rrt_settings_page() {

		//must check that the user has the required capability
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
		}

		// variables for the field and option names
		$opt_name = 'rrt_connections_file_path';
		$hidden_field_name = 'rrt_file_path_submit_hidden';
		$data_field_name = 'rrt_file_path';

		// Read in existing option value from database
		$opt_val = get_option( $opt_name );

		// See if the user has posted us some information
		// If they did, this hidden field will be set to 'Y'
		if ( isset( $_POST[ $hidden_field_name ] ) && $_POST[ $hidden_field_name ] == 'Y' ) {
			// Read their posted value
			$opt_val = sanitize_text_field( $_POST[ $data_field_name ] );

			// Save the posted value in the database
			update_option( $opt_name, $opt_val );

			// Put a "settings saved" message on the screen

	?>
	<div class="updated"><p><strong><?php _e( 'settings saved.', 'resourcespacetracking' ); ?></strong></p></div>
	<?php

		}

		// Now display the settings editing screen

		echo '<div class="wrap">';
		echo "<h2>" . __( 'Resourcespace Resource Tracking Settings', 'resourcespacetracking' ) . "</h2>";

		// settings form
	?>
		<form name="form1" method="post" action="">
			<input type="hidden" name="<?php echo $hidden_field_name; ?>" value="Y">

			<p><strong><label for="<?php echo $data_field_name; ?>"><?php _e( "Resource connection file path:", 'resourcespacetracking' ); ?></label></strong>
				<input type="text" name="<?php echo $data_field_name; ?>" id="<?php echo $data_field_name; ?>" value="<?php echo $opt_val; ?>" size="50">
			</p>
			<p><?php _e( "Make sure folder exists or no connection data will be saved.", 'resourcespacetracking' ); ?></p>
			<hr />

			<p class="submit">
				<input type="submit" name="Submit" class="button-primary" value="<?php esc_attr_e( 'Save Changes' ) ?>" />
			</p>

		</form>
		</div>

	<?php

	}


}