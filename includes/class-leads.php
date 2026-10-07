<?php
/**
 * Lead storage and notifications.
 *
 * @package SoloEstate
 */

namespace SoloEstate;

defined( 'ABSPATH' ) || exit;

class Leads {

	const STATUSES = array( 'new', 'contacted', 'closed' );

	/**
	 * Stores a lead and sends notifications. Values must be sanitized.
	 *
	 * @param array $data name, phone, email, message, node_id, lang, page_url, ip.
	 * @return int Lead id.
	 */
	public static function create( array $data ) {
		global $wpdb;

		$node = Nodes::get( isset( $data['node_id'] ) ? $data['node_id'] : 0 );
		$row  = array(
			'created_at' => current_time( 'mysql' ),
			'name'       => isset( $data['name'] ) ? $data['name'] : '',
			'phone'      => isset( $data['phone'] ) ? $data['phone'] : '',
			'email'      => isset( $data['email'] ) ? $data['email'] : '',
			'message'    => isset( $data['message'] ) ? $data['message'] : '',
			'node_id'    => $node ? $node->id : 0,
			'project_id' => $node ? ( 'project' === $node->level ? $node->id : $node->project_id ) : 0,
			'lang'       => isset( $data['lang'] ) ? $data['lang'] : '',
			'page_url'   => isset( $data['page_url'] ) ? $data['page_url'] : '',
			'ip'         => isset( $data['ip'] ) ? $data['ip'] : '',
			'status'     => 'new',
		);
		$wpdb->insert( Install::table( 'leads' ), $row ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$id = (int) $wpdb->insert_id;
		if ( ! $id ) {
			return 0;
		}

		$row['id'] = $id;
		$context   = self::context( $node );

		self::send_email( $row, $context );
		self::send_webhook( $row, $context );

		/**
		 * Fires after a lead has been stored and notifications were sent.
		 *
		 * @param array $row     Lead row.
		 * @param array $context project/building/floor/flat titles.
		 */
		do_action( 'solo_estate_lead_created', $row, $context );

		return $id;
	}

	/**
	 * Titles of the node chain in the default language.
	 *
	 * @param object|null $node Node.
	 * @return array{project:string,building:string,floor:string,flat:string}
	 */
	public static function context( $node ) {
		$out = array_fill_keys( Nodes::LEVELS, '' );
		if ( $node ) {
			$lang = I18n::default_code();
			foreach ( Nodes::ancestors( $node ) as $item ) {
				$out[ $item->level ] = Nodes::display_title( $item, $lang );
			}
			// {flat} in the e-mail subject names any unit: apartment, commercial space, villa.
			foreach ( array( 'commercial', 'villa', 'spot' ) as $level ) {
				if ( '' === $out['flat'] && '' !== $out[ $level ] ) {
					$out['flat'] = $out[ $level ];
				}
			}
		}
		return $out;
	}

	private static function send_email( array $row, array $context ) {
		$to = array_filter( array_map( 'trim', explode( ',', (string) Settings::get( 'lead_recipients' ) ) ), 'is_email' );
		if ( ! $to ) {
			return;
		}

		$subject = strtr(
			(string) Settings::get( 'lead_subject' ),
			array(
				'{site}'     => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
				'{project}'  => $context['project'],
				'{building}' => $context['building'],
				'{floor}'    => $context['floor'],
				'{flat}'     => $context['flat'],
				'{name}'     => $row['name'],
				'{phone}'    => $row['phone'],
			)
		);
		$subject = trim( preg_replace( '/\s+(—|-)\s*$/u', '', $subject ) );

		$lines = array(
			__( 'Name', 'solo-estate' ) . ': ' . $row['name'],
			__( 'Phone', 'solo-estate' ) . ': ' . $row['phone'],
		);
		if ( '' !== $row['email'] ) {
			$lines[] = __( 'Email', 'solo-estate' ) . ': ' . $row['email'];
		}
		if ( '' !== $row['message'] ) {
			$lines[] = __( 'Message', 'solo-estate' ) . ': ' . $row['message'];
		}
		$lines[] = '';
		foreach ( $context as $level => $title ) {
			if ( '' !== $title ) {
				$lines[] = Nodes::level_label( $level ) . ': ' . $title;
			}
		}
		$lines[] = '';
		$lines[] = __( 'Page', 'solo-estate' ) . ': ' . $row['page_url'];
		$lines[] = __( 'All leads', 'solo-estate' ) . ': ' . admin_url( 'admin.php?page=solo-estate-leads' );

		wp_mail( $to, $subject, implode( "\n", $lines ) );
	}

	private static function send_webhook( array $row, array $context ) {
		$url = (string) Settings::get( 'lead_webhook' );
		if ( '' === $url ) {
			return;
		}
		unset( $row['ip'] ); // Not needed by a CRM.
		$body    = (string) wp_json_encode(
			array(
				'lead'    => $row,
				'context' => $context,
				'site'    => home_url( '/' ),
			),
			JSON_UNESCAPED_UNICODE
		);
		$headers = array( 'Content-Type' => 'application/json; charset=utf-8' );
		$secret  = (string) Settings::get( 'lead_webhook_secret' );
		if ( '' !== $secret ) {
			$headers['X-Solo-Estate-Signature'] = 'sha256=' . hash_hmac( 'sha256', $body, $secret );
		}
		// wp_safe_remote_post() refuses localhost/private network targets.
		wp_safe_remote_post(
			$url,
			array(
				'timeout'  => 5,
				'blocking' => false,
				'headers'  => $headers,
				'body'     => $body,
			)
		);
	}

	/**
	 * Daily clean-up: removes IPs older than 30 days and leads older than the retention setting.
	 */
	public static function cleanup() {
		global $wpdb;

		$table = Install::table( 'leads' );
		$ip_cutoff = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - 30 * DAY_IN_SECONDS ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- compared with local created_at.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( $wpdb->prepare( "UPDATE $table SET ip = '' WHERE ip <> '' AND created_at < %s", $ip_cutoff ) );

		$days = (int) Settings::get( 'lead_retention' );
		if ( $days > 0 ) {
			$cutoff = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - $days * DAY_IN_SECONDS ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( $wpdb->prepare( "DELETE FROM $table WHERE created_at < %s", $cutoff ) );
		}
	}

	/**
	 * Paged list.
	 *
	 * @param int    $per_page Rows per page.
	 * @param int    $page     1-based page.
	 * @param string $search   Search term.
	 * @return array{rows:object[],total:int}
	 */
	public static function query( $per_page = 20, $page = 1, $search = '' ) {
		global $wpdb;

		$table  = Install::table( 'leads' );
		$where  = '1=1';
		$params = array();
		if ( '' !== $search ) {
			$like     = '%' . $wpdb->esc_like( $search ) . '%';
			$where   .= ' AND (name LIKE %s OR phone LIKE %s OR email LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}
		$count_sql = "SELECT COUNT(*) FROM $table WHERE $where";
		$rows_sql  = "SELECT * FROM $table WHERE $where ORDER BY id DESC LIMIT %d OFFSET %d";

		// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
		$total = (int) ( $params ? $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ) : $wpdb->get_var( $count_sql ) );
		$rows  = (array) $wpdb->get_results( $wpdb->prepare( $rows_sql, array_merge( $params, array( (int) $per_page, max( 0, ( (int) $page - 1 ) * (int) $per_page ) ) ) ) );
		// phpcs:enable

		return array(
			'rows'  => $rows,
			'total' => $total,
		);
	}

	/**
	 * Every lead, newest first (CSV export).
	 *
	 * @return object[]
	 */
	public static function all() {
		global $wpdb;
		$table = Install::table( 'leads' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (array) $wpdb->get_results( "SELECT * FROM $table ORDER BY id DESC" );
	}

	/**
	 * Deletes leads.
	 *
	 * @param int[] $ids Ids.
	 */
	public static function delete( array $ids ) {
		global $wpdb;
		$ids = array_filter( array_map( 'intval', $ids ) );
		if ( ! $ids ) {
			return;
		}
		$table = Install::table( 'leads' );
		$in    = implode( ',', $ids );
		$wpdb->query( "DELETE FROM $table WHERE id IN ($in)" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Changes the processing status of leads.
	 *
	 * @param int[]  $ids    Ids.
	 * @param string $status new|contacted|closed.
	 */
	public static function set_status( array $ids, $status ) {
		global $wpdb;
		if ( ! in_array( $status, self::STATUSES, true ) ) {
			return;
		}
		foreach ( array_filter( array_map( 'intval', $ids ) ) as $id ) {
			$wpdb->update( Install::table( 'leads' ), array( 'status' => $status ), array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
	}

	/**
	 * Localised label of a lead status.
	 *
	 * @param string $status Status.
	 * @return string
	 */
	public static function status_label( $status ) {
		$labels = array(
			'new'       => __( 'New', 'solo-estate' ),
			'contacted' => __( 'Contacted', 'solo-estate' ),
			'closed'    => __( 'Closed', 'solo-estate' ),
		);
		return isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
	}
}
