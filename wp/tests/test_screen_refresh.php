<?php
/** Exercise production screen resolution, saves, REST and output URLs with WP stubs. */
define( 'ABSPATH', __DIR__ );
define( 'DS_VERSION', '4.8.3' );
$posts = array(
	1 => (object) array( 'ID' => 1, 'post_type' => 'ds_screen', 'post_title' => 'HDMI 1' ),
	2 => (object) array( 'ID' => 2, 'post_type' => 'ds_screen', 'post_title' => 'HDMI 2' ),
	101 => (object) array( 'ID' => 101, 'post_type' => 'ds_channel', 'post_title' => 'One' ),
	202 => (object) array( 'ID' => 202, 'post_type' => 'ds_channel', 'post_title' => 'Two' ),
);
$meta = array(
	1 => array( 'ds_channel_id' => 101, 'ds_pairing_token' => 'one' ),
	2 => array( 'ds_channel_id' => 202, 'ds_pairing_token' => 'two' ),
	202 => array( 'ds_is_priority' => 1 ),
);
$counter = 0;
$checks = 0;
function absint( $value ) { return abs( (int) $value ); }
function sanitize_text_field( $value ) { return trim( (string) $value ); }
function sanitize_key( $value ) { return strtolower( (string) $value ); }
function get_post_type( $id ) { global $posts; return $posts[$id]->post_type ?? ''; }
function get_post_status( $id ) { return get_post_type( $id ) ? 'publish' : ''; }
function get_post_meta( $id, $key, $single = true ) { global $meta; return $meta[$id][$key] ?? ''; }
function update_post_meta( $id, $key, $value ) { global $meta; $meta[$id][$key] = $value; }
function delete_post_meta( $id, $key ) { global $meta; unset( $meta[$id][$key] ); }
function get_post_field( $field, $id ) { return '2026-11-07'; }
function get_the_title( $id ) { global $posts; return $posts[$id]->post_title ?? ''; }
function wp_update_post( $post ) { return $post['ID']; }
function wp_set_post_terms( $id, $terms, $taxonomy ) {}
function wp_generate_uuid4() { global $counter; return 'revision-' . ++$counter; }
function wp_generate_password( $length, $special = false, $extra = false ) { global $counter; return str_pad( (string) ++$counter, $length, 'x' ); }
function current_time( $format, $gmt = false ) { return time(); }
function add_action( ...$args ) {}
function home_url( $path ) { return 'https://example.test' . $path; }
function add_query_arg( $args, $url ) { return $url . '?' . http_build_query( $args ); }
function get_posts( $args ) {
	global $posts;
	$result = array();
	foreach ( $posts as $id => $post ) {
		if ( $post->post_type !== $args['post_type'] || in_array( $id, $args['post__not_in'] ?? array(), true ) ) { continue; }
		if ( isset( $args['meta_key'] ) && get_post_meta( $id, $args['meta_key'] ) !== $args['meta_value'] ) { continue; }
		$result[] = 'ids' === ( $args['fields'] ?? '' ) ? $id : $post;
	}
	return ( $args['posts_per_page'] ?? -1 ) > 0 ? array_slice( $result, 0, $args['posts_per_page'] ) : $result;
}
class WP_REST_Request extends ArrayObject {}
class Test_Response {
	public $data;
	public $headers = array();
	public function __construct( $data ) { $this->data = $data; }
	public function header( $key, $value ) { $this->headers[$key] = $value; }
}
function rest_ensure_response( $data ) { return new Test_Response( $data ); }
class DS_Music {
	public static function revision( $id ) { return 'none'; }
	public static function player_config( $id ) { return null; }
}
class DS_Spotify {
	public static function controller_output_revision( $id ) { return 'off'; }
	public static function controller_player_config( $id ) { return null; }
}
class DS_Groups {
	public static function settings_for_post( $id ) { return array( 'poll_interval' => 60 ); }
}
class DS_Player {
	public static function get_player_url( $id ) { return home_url( '/play/' . get_post_meta( $id, 'ds_pairing_token' ) . '/' ); }
}
class Test_Database {
	public $prefix = 'wp_';
	public function prepare( $query, ...$args ) { return $query; }
	public function get_row( $query ) { return (object) array( 'public_id' => 'controller' ); }
	public function get_results( $query ) {
		return array(
			(object) array( 'screen_id' => 1, 'output_key' => 'hdmi-1', 'connector' => 'HDMI-1' ),
			(object) array( 'screen_id' => 2, 'output_key' => 'hdmi-2', 'connector' => 'HDMI-2' ),
		);
	}
}
$wpdb = new Test_Database();
require __DIR__ . '/../includes/class-ds-crud.php';
require __DIR__ . '/../includes/class-ds-schedule-resolver.php';
require __DIR__ . '/../includes/class-ds-rest.php';
require __DIR__ . '/../includes/class-ds-controllers.php';
function check( $condition, $message ) { global $checks; if ( ! $condition ) { throw new RuntimeException( $message ); } ++$checks; }
check( 101 === DS_Schedule_Resolver::resolve_channel_id_for_screen( 1 ), 'Priority on another screen must not replace HDMI 1 channel' );
check( 202 === DS_Schedule_Resolver::resolve_channel_id_for_screen( 2 ), 'HDMI 2 must keep its priority channel' );
DS_CRUD::save_screen( 1, array( 'title' => 'HDMI 1', 'channel_id' => 101 ) );
$first_revision = get_post_meta( 1, 'ds_screen_revision' );
check( '' !== $first_revision, 'Saving creates a screen revision' );
$rest = DS_REST::instance();
$request = new WP_REST_Request( array( 'token' => 'one' ) );
$before = $rest->get_changes( $request )->data;
$urls_before = DS_Controllers::get_assignments( 9 );
DS_CRUD::save_screen( 1, array( 'title' => 'HDMI 1', 'channel_id' => 101 ) );
$after = $rest->get_changes( $request )->data;
check( $first_revision !== get_post_meta( 1, 'ds_screen_revision' ), 'Even unchanged saves must refresh' );
check( $before['revision'] !== $after['revision'], 'Legacy players must detect screen saves' );
$playlist = $rest->get_playlist( $request );
check( 101 === $playlist->data['channel_id'], 'REST must return this screens channel' );
check( $after['revision'] === $playlist->data['revision'], 'Probe and playlist revisions must match' );
check( false !== strpos( $playlist->headers['Cache-Control'], 'no-store' ), 'Playlist must bypass HTTP caches' );
$urls_after = DS_Controllers::get_assignments( 9 );
check( $urls_before[0]['player_url'] !== $urls_after[0]['player_url'], 'Screen save must force controller navigation' );
check( $urls_before[1]['player_url'] === $urls_after[1]['player_url'], 'Other screens must not restart' );
check( $urls_after[0]['player_url'] !== $urls_after[1]['player_url'], 'HDMI outputs must have different URLs' );
update_post_meta( 2, 'ds_pairing_token', 'one' );
DS_CRUD::ensure_screen_token( 2 );
check( get_post_meta( 1, 'ds_pairing_token' ) !== get_post_meta( 2, 'ds_pairing_token' ), 'Copied tokens must be repaired' );
$stable = get_post_meta( 2, 'ds_pairing_token' );
DS_CRUD::ensure_screen_token( 2 );
check( $stable === get_post_meta( 2, 'ds_pairing_token' ), 'Unique tokens must remain stable' );
echo $checks . " screen refresh checks passed\n";
