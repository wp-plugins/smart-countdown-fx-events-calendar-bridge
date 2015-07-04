<?php
/*
Version: 1.1
Author: Alex Polonski
Author URI: http://smartcalc.es/wp
License: GPL2
*/

defined( 'ABSPATH' ) or die();

// we relax max_results limit for upcoming events - this is a workaround:
// events calendar tribe_get_events() returns today finished events as
// upcoming, so that if there is a lot of finished events for today, setting
// a lower limit we may lose real future events. We may consider adding another
// query for "today" (with no limit) and change upcoming events query to
// a custom one with start_date = tomorrow, then union 3 arrays... 
define( 'SCD_TRIBE_EVENTS_MAX_RESULTS', 32 );

class SmartCountdownTEBRidge_Helper {
public static function selectInput( $id, $name, $selected = '', $config = array() ) {
		$config = array_merge( array(
				'type' => 'integer',
				'start' => 1,
				'end' => 10,
				'step' => 1,
				'default' => 0,
				'padding' => 2,
				'class' => '' 
		), $config );
		
		if( !empty( $config['class'] ) ) {
			$config['class'] = ' class="' . $config['class'] . '"';
		}
		$html = array();
		
		if( $config['type'] == 'integer' ) {
			$html[] = '<select id="' . $id . '" name="' . $name . '"' . $config['class'] . '>';
			
			for( $v = $config['start']; $v <= $config['end']; $v += $config['step'] ) {
				$html[] = '<option value="' . $v . '"' . ( $selected == $v ? ' selected' : '' ) . '>' . str_pad( $v, $config['padding'], '0', STR_PAD_LEFT ) . '</option>';
			}
		} elseif( $config['type'] == 'optgroups' ) {
			// plain lists and option groups supported
			$html[] = '<select id="' . $id . '" name="' . $name . '"' . $config['class'] . '>';
			
			foreach( $config['options'] as $value => $option ) {
				if( is_array( $option ) ) {
					// this is an option group
					$html[] = '<optgroup label="' . esc_html( $value ) . '">';
					foreach( $option as $v => $text ) {
						$html[] = '<option value="' . $v . '"' . ( $v == $selected ? ' selected' : '' ) . '>';
						$html[] = esc_html( $text );
						$html[] = '</option>';
					}
					$html[] = '</optgroup>';
				} else {
					// this is a plain select option
					$html[] = '<option value="' . $value . '"' . ( $value == $selected ? ' selected' : '' ) . '>';
					$html[] = esc_html( $option );
					$html[] = '</option>';
				}
			}
		}
		
		$html[] = '</select>';
		
		return implode( "\n", $html );
	}
	public static function checkboxesInput( $id, $name, $values, $config = array() ) {
		$html = array ();
		if ( !empty( $config ['legend'] ) ) {
			$html [] = '<fieldset><legend>' . $config ['legend'] . '</legend>';
		}
		foreach ( $config['options'] as $value => $text ) {
			$field_id = $id . $value;
			$field_name = $name . '[' . $value . ']';
			$html [] = '<input type="checkbox" class="checkbox" id="' . $field_id . '" name="' . $field_name . '"' . ( !empty( $values[$value] ) && $values[$value] == 'on'  ? ' checked' : '' ) . ' />';
			$html [] = '<label for="' . $field_id . '">' . esc_attr($text);
			$html [] = '</label>&nbsp;';
		}
		if ( !empty( $config ['legend'] ) ) {
			$html [] = '</fieldset>';
		}
		return implode( "\n", $html );
	}
	
	public static function getEvents( $instance, $configs ) {
		if( empty( $configs ) ) {
			return $instance;
		}
		
		// plugin not installed
		if( !function_exists( 'tribe_get_events' ) ) {
			return $instance;
		}
		
		$imported = array();
		
		foreach( $configs as $config ) {
			if( $config['filter_cat_id'] == -1 ) {
				// configuration disabled
				continue;
			}
			
			// if this plugin is used with old version of Smart Countdown FX we presume that
			// countdown_to_end mode is always OFF
			$countdown_to_end = !empty( $instance['countdown_to_end'] ) ? true : false;
			
			// get current UTC time for finished events final filter
			$now_ts = current_time( 'timestamp', true );
			
			// get local yestarday date
			$today = new DateTime( current_time( 'Y-m-d 00:00:00' ) );
			$yesterday = $today->modify( '-1 day' )->format( 'Y-m-d' );
			
			// get upcoming events
			$params = array(
					'eventDisplay'		=> 'list', // upcoming
					'posts_per_page'	=> SCD_TRIBE_EVENTS_MAX_RESULTS
			);
			// add category filter (if any)
			if( !empty( $config['filter_cat_id'] ) ) {
				$params['tax_query'] = array(
						array(
							'taxonomy'	=> 'tribe_events_cat',
							'field'		=> 'term_id',
							'terms'		=> $config['filter_cat_id']
						)
				);
 			}
			$posts = tribe_get_events( $params );
			
			// get yesterday events - this is a workaround for correct time zone
			// management - adding yesterday's event will guarantee that events with
			// negative tz offsets which are not finished are included in timeline.
			$params['eventDate'] = $yesterday;
			$params['eventDisplay'] = 'day';
			$yesterday_posts = tribe_get_events( $params );
			
			// union yesterday and upcoming
			$posts = array_merge( $posts, $yesterday_posts );
			
			// If no posts let's bail
			if ( empty( $posts ) ) {
				continue;
			}
			
			// index posts array by ID, this will also remove duplicates
			$indexed_posts = array();
			foreach( $posts as $post ) {
				$indexed_posts[$post->ID] = $post;
			}
			// get all posts ids to optimize meta query
			$posts_ids = array_keys( $indexed_posts );
			$ids_imploded = implode( ',', $posts_ids );
			global $wpdb;
			// get timezones for all posts
			$time_zones = $wpdb->get_results( $wpdb->prepare(
					"
						SELECT post_id, meta_value AS time_zone
						FROM $wpdb->postmeta
						WHERE meta_key = %s AND post_id IN($ids_imploded)
					",
					'scd_time_zone'
			), OBJECT_K );
			
			foreach( $posts as $post ) {
				$is_all_day = tribe_event_is_all_day( $post->ID );
				if( $is_all_day && empty( $config['all_day_event_start'] ) ) {
					// discard all-day events if set so in options
					continue;
				}
				
				// if custom time_zone is not set or = -1 (default), we pass null time_zone to dateToUTC() - 
				// WordPress default time_zone will be used for UTC conversion
				$time_zone = isset( $time_zones[$post->ID] ) && $time_zones[$post->ID]->time_zone != -1 ? $time_zones[$post->ID]->time_zone : null;
				
				$start = self::dateToUTC( $is_all_day ? substr( $post->EventStartDate, 0, -8 ) . $config['all_day_event_start'] : $post->EventStartDate, $time_zone );
				$end = $is_all_day ? $start : self::dateToUTC( $post->EventEndDate, $time_zone );
				$duration = $end->getTimestamp() - $start->getTimestamp();
				
				if( $end->getTimestamp() < $now_ts ) {
					// discard finished events (after time zone adjustment)
					continue;
				}
				// this is a valid event for import
				
				// get event properties
				$title = esc_html( $post->post_title );
				
				// apply styles to title
				$style = !empty( $config['title_css'] ) ? ' style="' . $config['title_css'] . '"' : '';
				$title = '<span' . $style . '>' . $title . '</span>';
				
				// append date if set so in configuration
				if( $config['show_date'] > 0 ) {
					$date = new DateTime( $post->EventStartDate );
					if( $is_all_day ) {
						$date = $date->format( tribe_get_date_format( $config['show_date'] == 2 /* show with year */ ) );
					} else {
						$date = $date->format( tribe_get_datetime_format( $config['show_date'] == 2 /* show with year */ ) );
					}
					
					// ensure that the date goes on the same line
					$date = str_replace( ' ', '&nbsp;', $date );
					
					// append time zone (if custom time zone set)
					if( !is_null( $time_zone ) ) {
						// strip underscores, do not allow line breaks in time zone string
						$date .= ( ' (' . str_replace( '_', '&nbsp;', $time_zone ) . ') ');
					}
					$style = !empty( $config['date_css'] ) ? ' style="' . $config['date_css'] . '"' : '';
					$date = ' <span' . $style . '>' . esc_html( $date ) . '</span>';
					
					// append date
					$title .= ' - ' . $date;
				}
				
				// append venue if set so in configuration
				if( tribe_has_venue( $post->ID ) && $config['show_location'] > 0 ) {
					if( $config['show_location'] == 1 ) {
						$location = esc_html( tribe_get_venue( $post->ID ) );
					} elseif( $config['show_location'] == 2 ) {
						$location = esc_html( strip_tags( tribe_get_full_address( $post->ID, false ) ) );
					}
					if( !empty( $location ) ) {
						$style = !empty( $config['location_css'] ) ? ' style="' . $config['location_css'] . '"' : '';
						$location = ' <span' . $style . '>' . $location . '</span>';
						
						// append location, place it on new line - avoid messing up things
						$title .= '<br />' . $location;
					}
				}
				
				// Link constructed title to event view if set so in configuration
				if( $config['link_title'] == 1 ) {
					$title = '<a href="' . tribe_get_event_link( $post->ID ) . '">' . $title . '</a>';
				}
				
				if( !$countdown_to_end || $duration == 0) {
					$imported[] = array(
							'deadline' => $start->format( 'Y-m-d H:i:s' ),
							'title' => $config['show_title'] == 1 ? $title : '',
							'duration' => $duration
					);
				} else {
					// "countdown to end" mode - add 2 events to timeline:
					// event start time - normal
					$imported[] = array(
							'deadline' => $start->format( 'Y-m-d H:i:s' ),
							'title' => $config['show_title'] == 1 ? $title : '',
							'duration' => 0
					);
					// event end time - with countdown_to_end flag
					$imported[] = array(
							'deadline' => $end->format( 'Y-m-d H:i:s' ),
							'title' => $config['show_title'] == 1 ? $title : '',
							'duration' => 0,
							'is_countdown_to_end' => 1
					);
				}
			}
		}
		
		if( !isset( $instance['imported'] ) ) {
			$instance['imported'] = array();
		}
	
		$instance['imported'][SmartCountdownTEBridge_Plugin::$provider_alias] = $imported;
	
		return $instance;
	}
	
	/**
	 * Convert a date to UTC using provided time zone or default one (from general settings)
	 * @param string or DateTime $date
	 * @param string $tz
	 * @return DateTime
	 */
	private static function dateToUTC( $date, $tz = null ) {
		if( $date instanceof DateTime ) {
			$result = $date;
		} else {
			$result = new DateTime( $date/*, new DateTimeZone('UTC')*/ );
		}
		
		// For now we use current WP system time (aware of time zone in settings)
		$tz_string = empty( $tz ) ? get_option( 'timezone_string', 'UTC' ) : $tz;
		
		if( strpos( $tz_string , 'UTC' ) === 0 ) {
			// special case - direct offset value in hours (e.g. UTC+2 or UTC-1.5)
			$sign = substr( $tz_string, 3, 1 );
			$offset = substr( $tz_string, 4 );
			
			$offset = $offset * 3600 * ( $sign == '-' ? -1 : 1 );
		} else {
			// normal flow
			if( empty( $tz_string ) ) {
				// direct offset if not a TZ
				$offset = get_option( 'gmt_offset' ) * 3600;
			} else {
				try {
					$tz = new DateTimeZone( $tz_string );
					$offset = $tz->getOffset( $result );
				} catch( Exception $e ) {
					$offset = 0; // invalid timezone string
				}
			}
		}
		$result->modify( ($offset < 0 ? '+' : '-') . abs( $offset ) . ' second' );
		
		return $result;
	}
}