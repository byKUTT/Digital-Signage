<?php
/**
 * Group-scoped, self-hosted commercial background music.
 *
 * @package Digital_Signage
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class DS_Music {
	const PLAYLIST_TYPE = 'ds_music_playlist';

	public static function tracks( $group_id ) {
		return array_values( array_filter( get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'post_mime_type' => 'audio', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC' ) ), function ( $track ) use ( $group_id ) {
			return DS_Groups::can_access_post( $track ) && absint( $group_id ) === DS_Groups::post_group_id( $track );
		} ) );
	}

	public static function playlists( $group_id ) {
		return array_values( array_filter( get_posts( array( 'post_type' => self::PLAYLIST_TYPE, 'post_status' => 'any', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC' ) ), function ( $playlist ) use ( $group_id ) {
			return DS_Groups::can_access_post( $playlist ) && absint( $group_id ) === DS_Groups::post_group_id( $playlist );
		} ) );
	}

	public static function save_playlist( $id, $title, array $track_ids, $group_id ) {
		$post = array( 'post_type' => self::PLAYLIST_TYPE, 'post_status' => 'publish', 'post_title' => sanitize_text_field( $title ), 'post_author' => get_current_user_id() );
		if ( $id ) { $post['ID'] = absint( $id ); $id = wp_update_post( $post, true ); } else { $id = wp_insert_post( $post, true ); }
		if ( is_wp_error( $id ) ) { return $id; }
		$allowed = array_map( function ( $track ) { return absint( $track->ID ); }, self::tracks( $group_id ) );
		update_post_meta( $id, 'ds_music_track_ids', array_values( array_intersect( array_map( 'absint', $track_ids ), $allowed ) ) );
		DS_Groups::set_post_group( $id, $group_id );
		return absint( $id );
	}

	public static function controller_config( $controller_id ) {
		return wp_parse_args( (array) get_option( 'ds_music_controller_' . absint( $controller_id ), array() ), array( 'playlist_id' => 0, 'output_key' => '', 'volume' => 35, 'shuffle' => 1 ) );
	}

	public static function save_controller_config( $controller_id, $playlist_id, $output_key, $volume, $shuffle ) {
		$config = array( 'playlist_id' => absint( $playlist_id ), 'output_key' => sanitize_text_field( $output_key ), 'volume' => min( 100, max( 0, absint( $volume ) ) ), 'shuffle' => $shuffle ? 1 : 0 );
		return update_option( 'ds_music_controller_' . absint( $controller_id ), $config, false );
	}

	public static function player_config( $screen_id ) {
		$controller_id = absint( get_post_meta( $screen_id, 'ds_controller_id', true ) );
		if ( ! $controller_id ) { return null; }
		$config = self::controller_config( $controller_id );
		$output_key = sanitize_text_field( get_post_meta( $screen_id, 'ds_controller_output', true ) );
		if ( ! $config['playlist_id'] || ! $config['output_key'] || $config['output_key'] !== $output_key ) { return null; }
		$playlist = get_post( $config['playlist_id'] );
		if ( ! $playlist || self::PLAYLIST_TYPE !== $playlist->post_type || DS_Groups::controller_group_id( $controller_id ) !== DS_Groups::post_group_id( $playlist ) ) { return null; }
		$tracks = array();
		foreach ( array_map( 'absint', (array) get_post_meta( $playlist->ID, 'ds_music_track_ids', true ) ) as $track_id ) {
			$url = wp_get_attachment_url( $track_id );
			if ( $url && 0 === strpos( (string) get_post_mime_type( $track_id ), 'audio/' ) && DS_Groups::post_group_id( $track_id ) === DS_Groups::post_group_id( $playlist ) ) {
				$tracks[] = array( 'id' => $track_id, 'title' => get_the_title( $track_id ), 'src' => esc_url_raw( $url ) );
			}
		}
		return $tracks ? array( 'playlist_id' => absint( $playlist->ID ), 'tracks' => $tracks, 'volume' => absint( $config['volume'] ) / 100, 'shuffle' => ! empty( $config['shuffle'] ), 'duck_ms' => 1500 ) : null;
	}

	public static function revision( $screen_id ) {
		$config = self::player_config( $screen_id );
		return $config ? md5( wp_json_encode( $config ) ) : 'none';
	}

	public static function delete_playlist( $playlist_id ) {
		foreach ( DS_Controllers::get_all() as $controller ) {
			$config = self::controller_config( $controller->id );
			if ( absint( $config['playlist_id'] ) === absint( $playlist_id ) ) { self::save_controller_config( $controller->id, 0, '', $config['volume'], $config['shuffle'] ); }
		}
		return wp_delete_post( absint( $playlist_id ), true );
	}
}
