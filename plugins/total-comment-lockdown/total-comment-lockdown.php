<?php
/**
 * Plugin Name: Total Comment Lockdown
 * Plugin URI:  https://example.com/
 * Description: Повністю вимикає коментарі, pingback і trackback для записів, сторінок, медіафайлів, PDF та інших типів контенту.
 * Version:     1.0.0
 * Author:      Site Administrator
 * License:     GPL-2.0-or-later
 * Text Domain: total-comment-lockdown
 */

defined( 'ABSPATH' ) || exit;

/**
 * Під час активації:
 * - закриває коментарі й pingback для всього наявного контенту;
 * - вимикає їх за замовчуванням для майбутнього контенту.
 */
function tcl_activate_plugin() {
	global $wpdb;

	update_option( 'default_comment_status', 'closed' );
	update_option( 'default_ping_status', 'closed' );
	update_option( 'default_pingback_flag', 0 );
	update_option( 'close_comments_for_old_posts', 1 );

	$wpdb->query(
		"UPDATE {$wpdb->posts}
		 SET comment_status = 'closed',
		     ping_status = 'closed'
		 WHERE comment_status <> 'closed'
		    OR ping_status <> 'closed'"
	);

	clean_post_cache( 0 );
}
register_activation_hook( __FILE__, 'tcl_activate_plugin' );

/**
 * Примусово закриває коментарі та pingback для будь-якого контенту.
 */
add_filter( 'comments_open', '__return_false', PHP_INT_MAX );
add_filter( 'pings_open', '__return_false', PHP_INT_MAX );

/**
 * Не показує вже наявні коментарі на фронтенді.
 * Самі коментарі з бази не видаляються.
 */
add_filter( 'comments_array', '__return_empty_array', PHP_INT_MAX );

/**
 * Блокує пряме надсилання коментарів через wp-comments-post.php.
 */
function tcl_block_comment_submission() {
	wp_die(
		esc_html__( 'Comments are disabled on this website.', 'total-comment-lockdown' ),
		esc_html__( 'Comments disabled', 'total-comment-lockdown' ),
		array( 'response' => 403 )
	);
}
add_action( 'pre_comment_on_post', 'tcl_block_comment_submission', 0 );

/**
 * Прибирає підтримку коментарів і trackback у всіх зареєстрованих типів записів.
 */
function tcl_remove_comment_support() {
	$post_types = get_post_types( array(), 'names' );

	foreach ( $post_types as $post_type ) {
		remove_post_type_support( $post_type, 'comments' );
		remove_post_type_support( $post_type, 'trackbacks' );
	}
}
add_action( 'init', 'tcl_remove_comment_support', PHP_INT_MAX );

/**
 * Не дозволяє WordPress або іншим плагінам знову відкрити коментарі
 * під час створення чи оновлення контенту.
 */
function tcl_force_closed_status( $data ) {
	$data['comment_status'] = 'closed';
	$data['ping_status']    = 'closed';

	return $data;
}
add_filter( 'wp_insert_post_data', 'tcl_force_closed_status', PHP_INT_MAX );

/**
 * Вимикає REST API маршрути коментарів.
 */
function tcl_disable_comment_rest_routes( $endpoints ) {
	foreach ( array_keys( $endpoints ) as $route ) {
		if ( 0 === strpos( $route, '/wp/v2/comments' ) ) {
			unset( $endpoints[ $route ] );
		}
	}

	return $endpoints;
}
add_filter( 'rest_endpoints', 'tcl_disable_comment_rest_routes', PHP_INT_MAX );

/**
 * Вимикає XML-RPC pingback.
 */
function tcl_disable_xmlrpc_pingbacks( $methods ) {
	unset( $methods['pingback.ping'] );
	unset( $methods['pingback.extensions.getPingbacks'] );

	return $methods;
}
add_filter( 'xmlrpc_methods', 'tcl_disable_xmlrpc_pingbacks', PHP_INT_MAX );
add_filter( 'wp_headers', function ( $headers ) {
	unset( $headers['X-Pingback'] );
	return $headers;
}, PHP_INT_MAX );

/**
 * Прибирає посилання на RSS-стрічки коментарів.
 */
remove_action( 'wp_head', 'feed_links_extra', 3 );
add_filter( 'post_comments_feed_link', '__return_empty_string', PHP_INT_MAX );

/**
 * Повертає 404 для стрічок коментарів.
 */
function tcl_disable_comment_feeds() {
	if ( is_comment_feed() ) {
		global $wp_query;

		if ( isset( $wp_query ) ) {
			$wp_query->set_404();
		}

		status_header( 404 );
		nocache_headers();
		exit;
	}
}
add_action( 'template_redirect', 'tcl_disable_comment_feeds', 0 );

/**
 * Прибирає розділ коментарів з адміністративної панелі.
 */
function tcl_remove_admin_comment_ui() {
	remove_menu_page( 'edit-comments.php' );
	remove_meta_box( 'dashboard_recent_comments', 'dashboard', 'normal' );
}
add_action( 'admin_menu', 'tcl_remove_admin_comment_ui', PHP_INT_MAX );
add_action( 'wp_dashboard_setup', 'tcl_remove_admin_comment_ui', PHP_INT_MAX );

/**
 * Прибирає пункт «Коментарі» з верхньої адмін-панелі.
 */
function tcl_remove_admin_bar_comments( $wp_admin_bar ) {
	$wp_admin_bar->remove_node( 'comments' );
}
add_action( 'admin_bar_menu', 'tcl_remove_admin_bar_comments', PHP_INT_MAX );

/**
 * Прибирає метабокси коментарів і обговорення з редактора.
 */
function tcl_remove_comment_meta_boxes() {
	$post_types = get_post_types( array(), 'names' );

	foreach ( $post_types as $post_type ) {
		remove_meta_box( 'commentstatusdiv', $post_type, 'normal' );
		remove_meta_box( 'commentsdiv', $post_type, 'normal' );
	}
}
add_action( 'add_meta_boxes', 'tcl_remove_comment_meta_boxes', PHP_INT_MAX );

/**
 * Захищає налаштування від повторного ввімкнення через адмінку.
 */
function tcl_enforce_comment_options() {
	if ( 'closed' !== get_option( 'default_comment_status' ) ) {
		update_option( 'default_comment_status', 'closed' );
	}

	if ( 'closed' !== get_option( 'default_ping_status' ) ) {
		update_option( 'default_ping_status', 'closed' );
	}

	if ( (int) get_option( 'default_pingback_flag' ) !== 0 ) {
		update_option( 'default_pingback_flag', 0 );
	}
}
add_action( 'admin_init', 'tcl_enforce_comment_options', PHP_INT_MAX );
