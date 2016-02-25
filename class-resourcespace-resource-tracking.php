<?php

defined( 'ABSPATH' ) or die();

define( 'RRT_CONNECTION_FILE_PATH', dirname( __FILE__ ) . '/resourceconnections/' );

class Resourcespace_Resource_Tracking {

	private static $resource_file_ending = '.json';


	function __construct() {
		add_action( 'save_post', array( $this, 'rrt_resource_published' ), 10, 3 );
	}


	/**
	 * Return resource data for a specific post
	 */
	public function rrt_get_resource_data_by_id( $post_id ) {
		// Get the actual connections from post
		return $this->get_post_resource_connection( $post_id );
	}


	/**
	 * Post resource connection to a url
	 */
	public function rrt_send_resource_data( $url, $post_id ) {
		// Get connections for the post
		$fields = $this->get_post_resource_connection( $post_id );

		// Post the connection to a url
		return $this->send_connection_post_request( $url, $fields );
	}


	/**
	 *  Save metadata for each time a post uses a resource
	 */
	public function rrt_resource_published( $post_id, $post, $update ) {
		// If this is just a revision, don't save
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		// Get the actual connections from post
		$this_post_connections = $this->get_post_resource_connection( $post_id );

		// Only save if connections exist
		if ( count( $this_post_connections ) > 0 ) {

			// Diff the current connections with earlier connections and remove if needed.
			$new_connections = $this->update_connections( $this_post_connections );

			foreach ( $new_connections as $key => $connection ) {
				// Save the new connections
				$this->rrt_save_resource_to_file( $connection );
			}
		}
	}


	/**
	 * Save resource connections to file
	 */
	public function rrt_save_resource_to_file( $connections ) {

		if ( isset( $connections ) && isset( $connections[0]['resource_id'] ) ) {
			// Extract resource id
			$resource_id = $connections[0]['resource_id'];
			// Write to file
			$file = fopen( RRT_CONNECTION_FILE_PATH . $resource_id . self::$resource_file_ending, 'w' );
			fwrite( $file, json_encode( $connections ) );
			fclose( $file );
		}
	}


	private function get_post_resource_connection( $post_id ) {
		$resource_connections = array();

		// Find resources and links in this post
		$post = get_post( $post_id );

		$post_url = get_permalink( $post_id );

		// We need to parse the content to get our links
		if ( $post ) {
			$raw_urls = wp_extract_urls( $post->post_content );

			foreach ( $raw_urls as $key => $value ) {
				$attachment_id = $this->fjarrett_get_attachment_id_by_url( $value );

				$resource_id = get_post_meta( $attachment_id, 'resource_external_id', true );

				// Dont return if not from Resourcespace
				if ( empty( $resource_id ) ) {
					continue;
				}

				$connection_details = array(
					'post_id' => $post_id,
					'url' => $post_url,
					'attachment_id' => $attachment_id,
					'resource_id' => $resource_id
				);

				$resource_connections[] = $connection_details;
			}
		}

		return $resource_connections;
	}


	private function get_post_resource_connection_by_resource_id( $resource_id ) {

		$attachment_id = $this->get_post_ids_from_metadata( 'resource_external_id', $resource_id );

		$connections = array();

		if ( $attachment_id ) {
			$metadata = wp_get_attachment_metadata( ( $attachment_id[0] ) );

			$post_ids = $this->get_post_ids_from_resource_url( $metadata['file'] );

			foreach ( $post_ids as $post_id ) {
				$connections[] = $this->get_post_resource_connection( intval( $post_id ) );
			}
		}

		return $connections;
	}


	private function send_connection_post_request( $url, $fields ) {
		//url-ify the data for the POST
		foreach ( $fields as $key => $value ) {
			$value = urlencode( $value );
			$fields_string .= $key . '=' . $value . '&';
		}
		rtrim( $fields_string, '&' );

		//open connection
		$ch = curl_init();

		//set the url, number of POST vars, POST data
		curl_setopt( $ch, CURLOPT_URL, $url );
		curl_setopt( $ch, CURLOPT_POST, count( $fields ) );
		curl_setopt( $ch, CURLOPT_POSTFIELDS, $fields_string );

		//execute post
		$result = curl_exec( $ch );

		//close connection
		curl_close( $ch );

		return $result;
	}


	private function get_post_ids_from_metadata( $key, $value ) {

		global $wpdb;

		if ( empty( $key ) )
			return;

		$post_ids = $wpdb->get_col( $wpdb->prepare( "
			SELECT pm.post_id FROM {$wpdb->postmeta} pm
			WHERE pm.meta_key = '%s'
			AND pm.meta_value = '%s'
		", $key, $value ) );

		return $post_ids;
	}


	/**
	 * Return an ID of an attachment by searching the database with the file URL.
	 *
	 * First checks to see if the $url is pointing to a file that exists in
	 * the wp-content directory. If so, then we search the database for a
	 * partial match consisting of the remaining path AFTER the wp-content
	 * directory. Finally, if a match is found the attachment ID will be
	 * returned.
	 *
	 * @param string  $url The URL of the image (ex: http://mysite.com/wp-content/uploads/2013/05/test-image.jpg)
	 *
	 * @return int|null $attachment Returns an attachment ID, or null if no attachment is found
	 */
	private function fjarrett_get_attachment_id_by_url( $url ) {
		// Split the $url into two parts with the wp-content directory as the separator
		$parsed_url  = explode( parse_url( WP_CONTENT_URL, PHP_URL_PATH ), $url );
		// Get the host of the current site and the host of the $url, ignoring www
		$this_host = str_ireplace( 'www.', '', parse_url( home_url(), PHP_URL_HOST ) );
		$file_host = str_ireplace( 'www.', '', parse_url( $url, PHP_URL_HOST ) );

		// This to remove any rezing stuff
		$parsed_url[1] = preg_replace( '/-[0-9]{1,4}x[0-9]{1,4}.(jpg|jpeg|png|gif|bmp)$/i', '.$1', $parsed_url[1] );

		// Return nothing if there aren't any $url parts or if the current host and $url host do not match
		if ( ! isset( $parsed_url[1] ) || empty( $parsed_url[1] ) || ( $this_host != $file_host ) ) {
			return;
		}
		// Now we're going to quickly search the DB for any attachment GUID with a partial path match
		// Example: /uploads/2013/05/test-image.jpg

		global $wpdb;
		$attachment = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->prefix}posts WHERE guid RLIKE %s;", $parsed_url[1] ) );
		// Returns null if no attachment is found
		return $attachment[0];
	}


	private function get_all_resource_connections( $connections ) {
		$existing_connections = array();
		foreach ( $connections as $key => $connection ) {
			$existing_connections[] = $this->read_resource_connection( $connection['resource_id'] );
		}

		return $existing_connections;
	}


	private function read_resource_connection( $resource_id ) {
		$current_connection = array();

		$file_path = RRT_CONNECTION_FILE_PATH . $resource_id . self::$resource_file_ending;

		if ( file_exists( $file_path ) ) {
			$str = file_get_contents( $file_path );

			if ( $str ) {
				$connections = json_decode( $str );

				// Check if one or more connections exists
				// TODO: Feels like this could be solved in a better way...
				if ( is_object( $connections ) ) {
					$current_connection[] = $this->extract_single_connection( $connections );
				} else {
					foreach ( $connections as $connection ) {
						$current_connection[] = $this->extract_single_connection( $connection );
					}
				}
			}
		}

		return $current_connection;
	}


	private function extract_single_connection( $connection_obj ) {
		$tmp_arr = array();
		foreach ( $connection_obj as $key => $connection ) {
			$tmp_arr += array_combine( array( $key ), (array)$connection );
		}

		return $tmp_arr;
	}


	private function update_connections( $new_connections ) {
		$changed_connections = array();

		foreach ( $new_connections as $key => $connection ) {
			$changed_connections[] = $this->update_connection( $connection );
		}

		// Remove if connection been removed
		// TODO...

		return $changed_connections;
	}


	private function update_connection( $new_connection ) {
		$updated_connections = array();
		$old_connections = $this->read_resource_connection( $new_connection['resource_id'] );

		$updated = false;
		if ( $old_connections ) {
			// Update the old connections
			foreach ( $old_connections as $key => $old_connection ) {
				if ( $new_connection['post_id'] === $old_connection['post_id'] && $new_connection['resource_id'] === $old_connection['resource_id'] ) {
					$old_connection = $new_connection;
					$updated = true;
				}
			}
			if ( ! $updated ) {
				// Add new connection to the rest
				$old_connections[] = $new_connection;
			}

			$updated_connections = $old_connections;

		} else {
			// Add new connection
			$updated_connections[] = $new_connection;
		}

		return $updated_connections;
	}
}
