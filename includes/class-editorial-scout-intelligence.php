<?php
/**
 * Bounded local editorial configuration and reviewable discovery assistance.
 *
 * @package EditorialScout
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
final class Editorial_Scout_Intelligence {
	const OPTION  = 'editorial_scout_profiles';
	const RESULTS = 'editorial_scout_assessments';
	const DRAFT   = 'editorial_scout_learning';

	public static function state() {
		$value = get_option( self::OPTION, array() );
		return is_array( $value ) ? $value : array();
	}
	public static function fields() {
		return array(
			'name'         => __( 'Profile name', 'editorial-scout' ),
			'audience'     => __( 'Audience', 'editorial-scout' ),
			'topics'       => __( 'Relevant topics (comma separated)', 'editorial-scout' ),
			'excluded'     => __( 'Excluded topics (comma separated)', 'editorial-scout' ),
			'geography'    => __( 'Geography', 'editorial-scout' ),
			'tone'         => __( 'Writing tone', 'editorial-scout' ),
			'instructions' => __( 'Editorial instructions', 'editorial-scout' ),
		);
	}
	public static function terms( $taxonomy = 'category' ) {
		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'number'     => 201,
				'orderby'    => 'term_id',
			)
		);
		return is_wp_error( $terms ) ? array() : $terms;
	}
	public static function category_ids( $ids ) {
		$valid = array();
		foreach ( array_slice( is_array( $ids ) ? $ids : array(), 0, 10 ) as $id ) {
			if ( is_scalar( $id ) && get_term( absint( $id ), 'category' ) instanceof WP_Term ) {
				$valid[] = absint( $id );
			}
		}
		return array_values( array_unique( $valid ) );
	}
	/** Caller holds the common lease. No activation occurs as part of learning. */
	public static function save( $id, $input, $make_default = false ) {
		$state = self::state();
		if ( (int) ( $state['version'] ?? 1 ) > 1 ) {
			return new WP_Error( 'upgrade_required' );
		}
		$profiles = $state['profiles'] ?? array();
		if ( isset( $input['categories'] ) && ( ! is_array( $input['categories'] ) || count( $input['categories'] ) > 10 ) ) {
			return new WP_Error( 'invalid_profile' );
		}
		if ( ( $id && ! isset( $profiles[ $id ] ) ) || ( ! $id && count( $profiles ) >= 10 ) ) {
			return new WP_Error( 'profile_limit' );
		}
		$data   = array();
		$limits = array(
			'name'         => 80,
			'audience'     => 500,
			'topics'       => 1000,
			'excluded'     => 1000,
			'geography'    => 200,
			'tone'         => 200,
			'instructions' => 2000,
		);
		foreach ( $limits as $key => $limit ) {
			$value = isset( $input[ $key ] ) && is_string( $input[ $key ] ) ? sanitize_textarea_field( $input[ $key ] ) : '';
			if ( mb_strlen( $value ) > $limit ) {
				return new WP_Error( 'invalid_profile' );
			}
			$data[ $key ] = $value;
		}
		if ( '' === trim( $data['name'] ) ) {
			return new WP_Error( 'invalid_profile' );
		}
		$data['categories']       = self::category_ids( $input['categories'] ?? array() );
		$data['default_category'] = self::category_ids( array( $input['default_category'] ?? 0 ) )[0] ?? 0;
		$id                       = $id ? $id : wp_generate_uuid4();
		$profiles[ $id ]          = $data;
		$state['version']         = 1;
		$state['profiles']        = $profiles;
		if ( $make_default || empty( $state['default'] ) ) {
			$state['default'] = $id;
		}
		$old_state = self::state();
		if ( $state !== $old_state && ! update_option( self::OPTION, $state, false ) ) {
			return new WP_Error( 'storage_failed' );
		}
		return $id;
	}
	public static function delete( $id ) {
		$state = self::state();
		if ( ! isset( $state['profiles'][ $id ] ) ) {
			return new WP_Error( 'not_found' );
		}
		unset( $state['profiles'][ $id ] );
		if ( ( $state['default'] ?? '' ) === $id ) {
			$state['default'] = array_key_first( $state['profiles'] ) ?? '';
		}
		return update_option( self::OPTION, $state, false ) ? true : new WP_Error( 'storage_failed' );
	}

	/** IDs only: eight date strata, category round-robin, then bounded gap filling. */
	public static function learn( $from, $to, $size, $excluded, &$lock = null ) {
		global $wpdb;
		if ( ! is_string( $from ) || ! is_string( $to ) || ! preg_match( '/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', $from ) || ! preg_match( '/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', $to ) ) {
			return new WP_Error( 'invalid_sample' );
		}
		$start = DateTimeImmutable::createFromFormat( '!Y-m-d', $from, new DateTimeZone( 'UTC' ) );
		$end   = DateTimeImmutable::createFromFormat( '!Y-m-d', $to, new DateTimeZone( 'UTC' ) );
		if ( ! $start || ! $end || $start->format( 'Y-m-d' ) !== $from || $end->format( 'Y-m-d' ) !== $to || $start > $end || $size < 1 || $size > 32 ) {
			return new WP_Error( 'invalid_sample' );
		}
		if ( ! is_array( $excluded ) || count( $excluded ) > 10 || count( array_filter( $excluded, 'is_scalar' ) ) !== count( $excluded ) || count( self::category_ids( $excluded ) ) !== count( array_unique( $excluded ) ) ) {
			return new WP_Error( 'invalid_sample' );
		}
		$excluded = self::category_ids( $excluded );
		$catalog  = self::terms();
		if ( $excluded && count( $catalog ) > 200 ) {
			return new WP_Error( 'category_conflict' );
		}
		for ( $pass = 0; $pass < 200; ++$pass ) {
			$previous = $excluded;
			foreach ( $catalog as $term ) {
				if ( in_array( (int) $term->parent, $excluded, true ) && ! in_array( (int) $term->term_id, $excluded, true ) ) {
					$excluded[] = (int) $term->term_id;
				}
			}
			if ( $previous === $excluded ) {
				break;
			}
		}
		$terms = array_values( array_diff( array_map( 'intval', wp_list_pluck( $catalog, 'term_id' ) ), $excluded ) );
		$terms = array_slice( $terms, 0, 4 );
		$ids   = array();
		$span  = $end->getTimestamp() + DAY_IN_SECONDS - $start->getTimestamp();
		for ( $step = 0; $step < 8; ++$step ) {
			$window = array( 0, 7, 6, 1, 5, 2, 4, 3 )[ $step ];
			if ( $lock && ! Editorial_Scout_Runner::renew( $lock ) ) {
				return new WP_Error( 'lease_lost' );
			}
			if ( count( $ids ) >= $size ) {
				break;
			}
			$left         = $start->getTimestamp() + (int) floor( $span * $window / 8 );
			$right        = $start->getTimestamp() + (int) floor( $span * ( $window + 1 ) / 8 ) - 1;
			$window_start = count( $ids );
			$quota        = (int) ceil( $size / 8 );
			$rotated      = array_merge( array_slice( $terms, $window % max( 1, count( $terms ) ) ), array_slice( $terms, 0, $window % max( 1, count( $terms ) ) ) );
			foreach ( array_merge( $rotated, array( 0 ) ) as $category ) {
				if ( count( $ids ) >= $size || count( $ids ) - $window_start >= $quota ) {
					break;
				}
				$query = new WP_Query(
					array(
						'post_type'              => 'post',
						'post_status'            => 'publish',
						'has_password'           => false,
						'fields'                 => 'ids',
						'posts_per_page'         => $category ? 1 : min( $quota - count( $ids ) + $window_start, $size - count( $ids ) ),
						'no_found_rows'          => true,
						'orderby'                => 'date ID',
						'order'                  => $window % 2 ? 'DESC' : 'ASC',
						'post__not_in'           => $ids,
						'category__not_in'       => $excluded,
						'category__in'           => $category ? array( $category ) : array(),
						'date_query'             => array(
							array(
								'column'    => 'post_date',
								'after'     => gmdate( 'Y-m-d H:i:s', $left - DAY_IN_SECONDS ),
								'before'    => gmdate( 'Y-m-d H:i:s', $right + DAY_IN_SECONDS ),
								'inclusive' => true,
							),
							array(
								'column'    => 'post_date_gmt',
								'after'     => gmdate( 'Y-m-d H:i:s', $left ),
								'before'    => gmdate( 'Y-m-d H:i:s', $right ),
								'inclusive' => true,
							),
						),
						'update_post_meta_cache' => false,
						'update_post_term_cache' => false,
					)
				);
				if ( $wpdb->last_error ) {
					return new WP_Error( 'storage_failed' );
				}
				$ids = array_values( array_unique( array_merge( $ids, $query->posts ) ) );
			}
		}
		// Fill sparse date windows from both ends of the complete approved range.
		// Retain the stratified selection; no more than two additional ID queries.
		for ( $fill = 0; $fill < 2; ++$fill ) {
			if ( count( $ids ) >= $size ) {
				break;
			}
			if ( $lock && ! Editorial_Scout_Runner::renew( $lock ) ) {
				return new WP_Error( 'lease_lost' );
			}
			$args                            = $query->query;
			$args['posts_per_page']          = 0 === $fill ? (int) ceil( ( $size - count( $ids ) ) / 2 ) : $size - count( $ids );
			$args['post__not_in']            = $ids;
			$args['category__in']            = array();
			$args['order']                   = 0 === $fill ? 'DESC' : 'ASC';
			$args['date_query'][0]['after']  = gmdate( 'Y-m-d H:i:s', $start->getTimestamp() - DAY_IN_SECONDS );
			$args['date_query'][0]['before'] = gmdate( 'Y-m-d H:i:s', $end->getTimestamp() + 2 * DAY_IN_SECONDS - 1 );
			$args['date_query'][1]['after']  = $from . ' 00:00:00';
			$args['date_query'][1]['before'] = $to . ' 23:59:59';
			$query                           = new WP_Query( $args );
			if ( $wpdb->last_error ) {
				return new WP_Error( 'storage_failed' );
			}
			$ids = array_values( array_unique( array_merge( $ids, $query->posts ) ) );
		}
		$evidence = array();
		$keywords = array();
		foreach ( $ids as $id ) {
			// Never load whole post bodies or apply content/shortcode rendering filters.
			$post = $wpdb->get_row( $wpdb->prepare( "SELECT ID, LEFT(post_title,400) AS post_title, post_date_gmt, post_modified_gmt, SUBSTRING(post_content,1,4000) AS snippet FROM $wpdb->posts WHERE ID = %d AND post_type = 'post' AND post_status = 'publish' AND post_password = ''", $id ) );
			if ( $wpdb->last_error ) {
				return new WP_Error( 'storage_failed' );
			}
			if ( ! $post ) {
				continue;
			}
			$excerpt = mb_substr( wp_strip_all_tags( strip_shortcodes( $post->snippet ) ), 0, 600 );
			$words   = preg_split( '/[^\p{L}\p{N}]+/u', mb_strtolower( $post->post_title . ' ' . $excerpt ) );
			foreach ( array_unique( $words ) as $word ) {
				if ( mb_strlen( $word ) >= 5 && mb_strlen( $word ) <= 40 && ! in_array( $word, array( 'their', 'there', 'these', 'those', 'which', 'about', 'would', 'could', 'should', 'other', 'after', 'before', 'while', 'where', 'being', 'through', 'https' ), true ) ) {
					$keywords[ $word ] = ( $keywords[ $word ] ?? 0 ) + 1;
				}
			}
			$evidence[] = array(
				'id'       => (int) $id,
				'modified' => $post->post_modified_gmt,
				'date'     => $post->post_date_gmt,
			);
		}
		if ( ! $evidence ) {
			return new WP_Error( 'empty_sample' );
		}
		arsort( $keywords );
		$draft = array(
			'profile'  => array(
				'name'         => __( 'Learned profile proposal', 'editorial-scout' ),
				'topics'       => implode( ', ', array_slice( array_keys( $keywords ), 0, 12 ) ),
				'audience'     => '',
				'excluded'     => '',
				'geography'    => '',
				'tone'         => '',
				'instructions' => '',
			),
			'evidence' => $evidence,
			'created'  => time(),
			'token'    => wp_generate_uuid4(),
		);
		if ( $lock && ! Editorial_Scout_Runner::renew( $lock ) ) {
			return new WP_Error( 'lease_lost' );
		}
		return update_user_meta( get_current_user_id(), self::DRAFT, $draft ) ? $draft : new WP_Error( 'storage_failed' );
	}
	public static function proposal() {
		global $wpdb;
		$draft = get_user_meta( get_current_user_id(), self::DRAFT, true );
		if ( ! is_array( $draft ) || ( $draft['created'] ?? 0 ) < time() - HOUR_IN_SECONDS ) {
			if ( $draft ) {
				delete_user_meta( get_current_user_id(), self::DRAFT );
			}
			return array();
		}
		foreach ( $draft['evidence'] as $index => $evidence ) {
			$valid = $wpdb->get_row( $wpdb->prepare( "SELECT ID, LEFT(post_title,400) AS title FROM $wpdb->posts WHERE ID = %d AND post_type = 'post' AND post_status = 'publish' AND post_password = '' AND post_modified_gmt = %s", $evidence['id'], $evidence['modified'] ) );
			if ( ! $valid ) {
				delete_user_meta( get_current_user_id(), self::DRAFT );
				return array();
			}
			// Title is added only to the returned view, without loading a full WP_Post.
			$draft['evidence'][ $index ]['title'] = sanitize_text_field( $valid->title );
		}
		return $draft;
	}
	public static function results() {
		$rows = get_option( self::RESULTS, array() );
		return is_array( $rows ) ? $rows : array();
	}
	/** At most five distinct stories; never writes human decision or post tables. */
	public static function assess( $hashes, $profile_id, &$lock ) {
		global $wpdb;
		$profile = self::state()['profiles'][ $profile_id ] ?? null;
		if ( ! $profile || ! is_array( $hashes ) || count( $hashes ) > 5 || ! $hashes ) {
			return new WP_Error( 'assessment_limit' );
		}
		foreach ( $hashes as $hash ) {
			if ( ! is_string( $hash ) || ! preg_match( '/^[a-f0-9]{64}$/D', $hash ) ) {
				return new WP_Error( 'invalid_assessment' );
			}
		}
		$provider = new Editorial_Scout_Local_Provider();
		$rows     = array_filter(
			self::results(),
			static function ( $row ) {
				return $row['created'] >= time() - 30 * DAY_IN_SECONDS;
			}
		);
		foreach ( array_unique( $hashes ) as $hash ) {
			if ( ! is_string( $hash ) || ! preg_match( '/^[a-f0-9]{64}$/D', $hash ) ) {
				return new WP_Error( 'invalid_assessment' );
			}
			$item = $wpdb->get_row( $wpdb->prepare( 'SELECT LEFT(title,400) AS title FROM %i WHERE story_hash = %s ORDER BY id LIMIT 1', Editorial_Scout_Store::table( 'items' ), $hash ) );
			if ( $wpdb->last_error ) {
				return new WP_Error( 'storage_failed' );
			}
			if ( ! $item ) {
				return new WP_Error( 'not_found' );
			}
			$taxonomy = array_map(
				static function ( $term ) {
					return array( $term->term_id, $term->name, $term->slug );
				},
				self::terms()
			);
			$key      = hash( 'sha256', wp_json_encode( array( $hash, $profile, $item->title, $taxonomy, $provider->id() ) ) );
			if ( isset( $rows[ $key ] ) ) {
				continue;
			}
			$result = Editorial_Scout_AI::assess(
				$provider,
				array(
					'title'   => mb_substr( sanitize_text_field( $item->title ), 0, 400 ),
					'profile' => $profile,
				)
			);
			if ( is_wp_error( $result ) || ! Editorial_Scout_Runner::renew( $lock ) ) {
				return is_wp_error( $result ) ? $result : new WP_Error( 'lease_lost' );
			}
			$categories = self::category_ids( $profile['categories'] );
			if ( ! $categories ) {
				$categories = self::category_ids( array( $profile['default_category'] ) );
			}
			$result['categories']    = 'relevant' === $result['recommendation'] ? array_slice( $categories, 0, 3 ) : array();
			$result['story']         = $hash;
			$result['title']         = mb_substr( sanitize_text_field( $item->title ), 0, 400 );
			$result['title_hash']    = hash( 'sha256', $item->title );
			$result['taxonomy_hash'] = hash( 'sha256', wp_json_encode( $taxonomy ) );
			$result['profile_id']    = $profile_id;
			$result['profile_hash']  = hash( 'sha256', wp_json_encode( $profile ) );
			$result['created']       = time();
			$result['provider']      = $provider->id();
			$rows[ $key ]            = $result;
		}
		$rows     = array_filter(
			$rows,
			static function ( $row ) {
				return $row['created'] >= time() - 30 * DAY_IN_SECONDS;
			}
		);
		$rows     = array_slice( $rows, -200, null, true );
		$old_rows = self::results();
		return $rows === $old_rows || update_option( self::RESULTS, $rows, false ) ? true : new WP_Error( 'storage_failed' );
	}
	public static function current_result( $row ) {
		global $wpdb;
		$profile = self::state()['profiles'][ $row['profile_id'] ] ?? null;
		if ( ! $profile || $row['created'] < time() - 30 * DAY_IN_SECONDS || hash( 'sha256', wp_json_encode( $profile ) ) !== $row['profile_hash'] ) {
			return false;
		}
		$title    = $wpdb->get_var( $wpdb->prepare( 'SELECT LEFT(title,400) AS title FROM %i WHERE story_hash = %s ORDER BY id LIMIT 1', Editorial_Scout_Store::table( 'items' ), $row['story'] ) );
		$taxonomy = array_map(
			static function ( $term ) {
				return array( $term->term_id, $term->name, $term->slug );
			},
			self::terms()
		);
		return is_string( $title ) && hash( 'sha256', $title ) === $row['title_hash'] && hash( 'sha256', wp_json_encode( $taxonomy ) ) === $row['taxonomy_hash'];
	}
	/** Do not propose terms already present or nearly identical in the native catalog. */
	public static function candidate( $row ) {
		$terms = self::terms();
		if ( 'relevant' !== $row['recommendation'] || count( $terms ) > 200 ) {
			return '';
		}
		foreach ( $row['evidence'] as $phrase ) {
			$normalized = self::normalized( $phrase );
			$duplicate  = ! $normalized || term_exists( sanitize_title( $phrase ), 'category' );
			foreach ( $terms as $term ) {
				$existing = self::normalized( $term->name );
				if ( $normalized === $existing || ( strlen( $normalized ) >= 4 && strlen( $existing ) >= 4 && levenshtein( $normalized, $existing ) <= 1 ) ) {
					$duplicate = true;
				}
			}
			if ( ! $duplicate ) {
				return $phrase;
			}
		}
		return '';
	}

	private static function normalized( $name ) {
		return str_replace( '-', '', sanitize_title( remove_accents( $name ) ) );
	}
	/** Native taxonomy creation requires separate explicit administrator approval. */
	public static function approve_category( $key, $name, $slug, $description, $parent_id ) {
		$row = self::results()[ $key ] ?? null;
		if ( ! $row || ! self::current_result( $row ) || 'relevant' !== $row['recommendation'] || ! $row['evidence'] || mb_strlen( $name ) > 80 || mb_strlen( $description ) > 200 || ! $name || ! $slug || ( $parent_id && ! ( get_term( $parent_id, 'category' ) instanceof WP_Term ) ) ) {
			return new WP_Error( 'invalid_assessment' );
		}
		$normalized = self::normalized( $name );
		$justified  = false;
		foreach ( $row['evidence'] as $phrase ) {
			if ( self::normalized( $phrase ) === $normalized ) {
				$justified = true;
			}
		}
		$terms = self::terms();
		if ( ! $justified || ! $normalized || count( $terms ) > 200 || term_exists( $name, 'category' ) || term_exists( sanitize_title( $slug ), 'category' ) ) {
			return new WP_Error( 'category_conflict' );
		}
		foreach ( $terms as $term ) {
			$existing = self::normalized( $term->name );
			if ( $normalized === $existing || ( strlen( $normalized ) >= 4 && strlen( $existing ) >= 4 && levenshtein( $normalized, $existing ) <= 1 ) ) {
				return new WP_Error( 'category_conflict' );
			}
		}
		$result = wp_insert_term(
			sanitize_text_field( $name ),
			'category',
			array(
				'slug'        => sanitize_title( $slug ),
				'description' => sanitize_text_field( $description ),
				'parent'      => $parent_id,
			)
		);
		return is_wp_error( $result ) ? new WP_Error( 'category_conflict' ) : true;
	}
}
