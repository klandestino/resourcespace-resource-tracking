<?php
/*
Plugin Name: Resourcespace Resource Tracking
Plugin URI: https://github.com/klandestino/resourcespace-resource-tracking
Description: Keep track of which resource in Resourcespace is used in which post(s)
Version: 0.1
Author: Tom Bergman
License: GPL2
Text Domain: resourcespacetracking
Domain Path: /languages
*/

require_once dirname( __FILE__ ) . '/class-resourcespace-resource-tracking.php';
require_once dirname( __FILE__ ) . '/class-resourcespace-resource-tracking-admin.php';

// Initalize for WP save_post action
$rrt = new Resourcespace_Resource_Tracking();

// WP admin setup
Resourcespace_Resource_Tracking_Admin::get_instance();

// WP-rest init
add_action( 'rest_api_init', 'rrt_api_tracking_setup' );


/**
 * Setup routes for Resourcespace tracking API. This depends on the plugin WP REST API (http://v2.wp-api.org/).
 * Currently on version 2.0 Beta 12 for Wp 4.4 or later
 */
function rrt_api_tracking_setup() {
	// Get posts resource data
	register_rest_route( 'resourcespace-tracking/v1', '/postid/(?P<id>\d+)', array(
			'methods' => 'GET',
			'callback' => 'rrt_resource_tracking_get_data',
			'args' => array(
				'id' => array(
					'validate_callback' => function( $param, $request, $key ) {
						return is_numeric( $param );
					}
				),
			),
		) );


	// Send all connections to specified url
	register_rest_route( 'resourcespace-tracking/v1', '/send', array(
			'methods' => 'POST',
			'callback' => 'rrt_resource_tracking_send_data',
			'args' => array(
				'id' => array(
					'validate_callback' => function( $param, $request, $key ) {
						return is_numeric( $param );
					}
				),
				'url' => array(
					'sanitize_callback' => function( $param, $request, $key ) {
						// TODO: Maybe we dont want to send this to wherever...!!
						return esc_url_raw( $param );
					}
				),
			),
		) );
}

/**
 * Returns resource connections for a post
 *
 * @param WP_REST_Request $request Full details about the request.
 * @return WP_Error|WP_REST_Response
 */
function rrt_resource_tracking_get_data( WP_REST_Request $request ) {
	$rrt = new Resourcespace_Resource_Tracking();

	$connections = $rrt->rrt_get_resource_data_by_id( $request->get_param( 'id' ) );

	if ( empty( $connections ) ) {
		return new WP_Error( 'rrt_no_connections_found', 'No connections found', array( 'status' => 404 ) );
	} else {
		return $connections;
	}
}


/**
 * Sends resource connections for a post
 *
 * @param WP_REST_Request $request Full details about the request.
 * @return WP_Error|boolean
 */
function rrt_resource_tracking_send_data( WP_REST_Request $request ) {
	$rrt = new Resourcespace_Resource_Tracking();

	return $rrt->rrt_send_resource_data( $request->get_param( 'id' ), $request->get_param( 'url' ) );
}
