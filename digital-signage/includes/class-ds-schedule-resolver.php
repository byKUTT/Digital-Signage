<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves, for a given screen and point in time, which channel should be playing
 * and which of that channel's slides are currently eligible per-zone — taking into
 * account recurring day/time schedules, one-off date overrides, and priority /
 * emergency interrupts.
 */
class DS_Schedule_Resolver {

	/**
	 * Returns the channel ID that should be active on a screen right now.
	 * Priority order: emergency/priority channel override (site or screen-wide) >
	 * active one-off schedule > active recurring schedule > screen's assigned default channel.
	 */
	public static function resolve_channel_id_for_screen( $screen_id ) {
		$now = current_time( 'timestamp' );

		// 1. Global/site-wide emergency channel takes over everything.
		$emergency = self::find_emergency_channel();
		if ( $emergency ) {
			return $emergency;
		}

		// 2. Explicit remote "override" pushed from admin (immediate content override).
		$override = get_post_meta( $screen_id, 'ds_manual_override_channel', true );
		$override_expires = (int) get_post_meta( $screen_id, 'ds_manual_override_expires', true );
		if ( $override && ( ! $override_expires || $override_expires > time() ) ) {
			return absint( $override );
		}

		// 3. Schedules targeting this screen, one-offs first (higher specificity), then recurring, ordered by priority desc.
		$schedules = self::get_schedules_for_screen( $screen_id );

		usort(
			$schedules,
			function ( $a, $b ) {
				$a_oneoff = get_post_meta( $a->ID, 'ds_is_one_off', true ) ? 1 : 0;
				$b_oneoff = get_post_meta( $b->ID, 'ds_is_one_off', true ) ? 1 : 0;
				if ( $a_oneoff !== $b_oneoff ) {
					return $b_oneoff - $a_oneoff;
				}
				$a_pri = (int) get_post_meta( $a->ID, 'ds_priority', true );
				$b_pri = (int) get_post_meta( $b->ID, 'ds_priority', true );
				return $b_pri - $a_pri;
			}
		);

		foreach ( $schedules as $schedule ) {
			if ( self::schedule_is_active( $schedule->ID, $now ) ) {
				return absint( get_post_meta( $schedule->ID, 'ds_channel_id', true ) );
			}
		}

		// 4. Fall back to the screen's statically assigned channel.
		return absint( get_post_meta( $screen_id, 'ds_channel_id', true ) );
	}

	public static function find_emergency_channel() {
		$channels = get_posts(
			array(
				'post_type'      => 'ds_channel',
				'posts_per_page' => 1,
				'meta_key'       => 'ds_is_priority',
				'meta_value'     => 1,
				'post_status'    => 'publish',
			)
		);
		return $channels ? $channels[0]->ID : 0;
	}

	private static function get_schedules_for_screen( $screen_id ) {
		$all = get_posts(
			array(
				'post_type'      => 'ds_schedule',
				'posts_per_page' => -1,
				'post_status'    => 'publish',
			)
		);

		return array_values(
			array_filter(
				$all,
				function ( $schedule ) use ( $screen_id ) {
					$targets = array_map( 'absint', (array) get_post_meta( $schedule->ID, 'ds_screen_ids', true ) );
					return in_array( absint( $screen_id ), $targets, true );
				}
			)
		);
	}

	public static function schedule_is_active( $schedule_id, $now = null ) {
		if ( null === $now ) {
			$now = current_time( 'timestamp' );
		}

		$is_one_off = get_post_meta( $schedule_id, 'ds_is_one_off', true );
		$start_date = get_post_meta( $schedule_id, 'ds_start_date', true );
		$end_date   = get_post_meta( $schedule_id, 'ds_end_date', true );
		$start_time = get_post_meta( $schedule_id, 'ds_start_time', true );
		$end_time   = get_post_meta( $schedule_id, 'ds_end_time', true );
		$days       = (array) get_post_meta( $schedule_id, 'ds_days', true );

		$today = date( 'Y-m-d', $now );

		if ( $is_one_off ) {
			if ( $start_date && $today < $start_date ) {
				return false;
			}
			if ( $end_date && $today > $end_date ) {
				return false;
			}
			return self::time_in_range( $now, $start_time, $end_time );
		}

		// Recurring: optional active date window, plus day-of-week + time-of-day match.
		if ( $start_date && $today < $start_date ) {
			return false;
		}
		if ( $end_date && $today > $end_date ) {
			return false;
		}

		if ( ! empty( $days ) ) {
			$dow = strtolower( date( 'D', $now ) ); // mon, tue, ...
			$map = array( 'mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun' );
			$dow_full = strtolower( date( 'l', $now ) );
			if ( ! in_array( $dow, $days, true ) && ! in_array( $dow_full, $days, true ) ) {
				return false;
			}
		}

		return self::time_in_range( $now, $start_time, $end_time );
	}

	private static function time_in_range( $now, $start_time, $end_time ) {
		if ( ! $start_time && ! $end_time ) {
			return true;
		}
		$current = date( 'H:i', $now );
		if ( $start_time && $end_time ) {
			if ( $start_time <= $end_time ) {
				return $current >= $start_time && $current <= $end_time;
			}
			// Overnight range, e.g. 22:00-06:00.
			return $current >= $start_time || $current <= $end_time;
		}
		if ( $start_time ) {
			return $current >= $start_time;
		}
		return $current <= $end_time;
	}

	/**
	 * Returns the ordered, currently-eligible slides for a channel, grouped by zone.
	 */
	public static function get_active_playlist( $channel_id ) {
		$now = current_time( 'timestamp' );

		$slides = get_posts(
			array(
				'post_type'      => 'ds_slide',
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'meta_key'       => 'ds_channel_id',
				'meta_value'     => $channel_id,
				'orderby'        => 'meta_value_num',
				'meta_key2'      => 'ds_order',
				'order'          => 'ASC',
			)
		);

		$zones = array();

		foreach ( $slides as $slide ) {
			if ( ! self::slide_is_active( $slide->ID, $now ) ) {
				continue;
			}
			$zone = get_post_meta( $slide->ID, 'ds_zone', true ) ?: 'main';
			if ( ! isset( $zones[ $zone ] ) ) {
				$zones[ $zone ] = array();
			}
			$zones[ $zone ][] = DS_REST::slide_to_array( $slide );
		}

		return $zones;
	}

	public static function slide_is_active( $slide_id, $now = null ) {
		if ( null === $now ) {
			$now = current_time( 'timestamp' );
		}

		$start_date = get_post_meta( $slide_id, 'ds_sched_start_date', true );
		$end_date   = get_post_meta( $slide_id, 'ds_sched_end_date', true );
		$start_time = get_post_meta( $slide_id, 'ds_sched_start_time', true );
		$end_time   = get_post_meta( $slide_id, 'ds_sched_end_time', true );
		$days       = (array) get_post_meta( $slide_id, 'ds_sched_days', true );

		$today = date( 'Y-m-d', $now );

		if ( $start_date && $today < $start_date ) {
			return false;
		}
		if ( $end_date && $today > $end_date ) {
			return false;
		}

		if ( ! empty( array_filter( $days ) ) ) {
			$dow_full = strtolower( date( 'l', $now ) );
			$dow      = strtolower( date( 'D', $now ) );
			if ( ! in_array( $dow, $days, true ) && ! in_array( $dow_full, $days, true ) ) {
				return false;
			}
		}

		return self::time_in_range( $now, $start_time, $end_time );
	}
}
