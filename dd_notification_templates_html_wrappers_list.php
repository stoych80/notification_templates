<?php
if (!class_exists( 'WP_List_Table' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}
class dd_notification_templates_html_wrappers_list extends WP_List_Table {

	/** Class constructor */
	public function __construct() {
		parent::__construct( array(
			'singular' => __( 'HTML Wrapper', 'sp' ), //singular name of the listed records
			'plural'   => __( 'HTML Wrappers', 'sp' ), //plural name of the listed records
			'ajax'     => false,
			'screen' => 'dd_notification_templates_html_wrappers_list'
		));
	}

	/**
	 * Retrieve users data from the database
	 *
	 * @param int $per_page
	 * @param int $page_number
	 *
	 * @return mixed
	 */
	public static function get_items( $per_page = 5, $page_number = 1 ) {
		global $wpdb;
		$sql = "SELECT dd_notification_templates_html_wrapper_id,title,header,footer FROM dd_notification_templates_html_wrappers";

		if (!empty($_REQUEST['orderby']) && $_REQUEST['orderby'] == 'title') {
			$sql .= ' ORDER BY ' . esc_sql( $_REQUEST['orderby'] );
			$sql .= ! empty( $_REQUEST['order'] ) ? ' ' . esc_sql( $_REQUEST['order'] ) : ' ASC';
		} else {
			$sql .= ' ORDER BY title';
		}

		$sql .= " LIMIT $per_page";
		$sql .= ' OFFSET ' . ( $page_number - 1 ) * $per_page;

		$result = $wpdb->get_results( $sql, 'ARRAY_A');
		return $result;
	}

	/** Text displayed when no user data is available */
	public function no_items() {
		_e( 'No HTML Wrappers.', 'sp');
	}

	/**
	 * Render a column when no column specific method exist.
	 *
	 * @param array $item
	 * @param string $column_name
	 *
	 * @return mixed
	 */
	public function column_default( $item, $column_name ) {
		/*switch ( $column_name ) {
			case 'address':
			case 'city':
				return $item[ $column_name ];
			default:
				return print_r( $item, true ); //Show the whole array for troubleshooting purposes
		}*/
	}

	/**
	 * Render the bulk edit checkbox
	 *
	 * @param array $item
	 *
	 * @return string
	 */
	function column_cb( $item ) {
		return sprintf('<input type="checkbox" name="bulk-ids[]" value="%d" />', $item['dd_notification_templates_html_wrapper_id']);
	}

	/**
	 * Method for name column
	 *
	 * @param array $item an array of DB data
	 *
	 * @return string
	 */
	function column_title( $item ) {
		$delete_nonce = wp_create_nonce('***');
		$actions = array(
			'edit' => sprintf('<a href="?page=%s&action=%s&id=%d">Edit</a>', esc_attr($_REQUEST['page']), 'edit', absint( $item['dd_notification_templates_html_wrapper_id'] ), $delete_nonce),
			'delete' => sprintf('<a href="?page=%s&action=%s&id=%d&_wpnonce=%s" class="delete_html_wrapper">Delete</a>', esc_attr($_REQUEST['page']), 'delete', absint( $item['dd_notification_templates_html_wrapper_id'] ), $delete_nonce)
		);
		return $item['title'] . $this->row_actions( $actions );
	}
	function column_header_footer( $item ) {
		return $item['header'].$item['footer'];
	}

	/**
	 *  Associative array of columns
	 *
	 * @return array
	 */
	function get_columns() {
		$columns = array(
			'cb'      => '<input type="checkbox" />',
			'title'    => 'Title',
			'header_footer'    => 'Header + Footer'
		);
		return $columns;
	}


	/**
	 * Columns to make sortable.
	 *
	 * @return array
	 */
	public function get_sortable_columns() {
		$sortable_columns = array(
			'title' => array('title', true)
		);
		return $sortable_columns;
	}

	/**
	 * Returns an associative array containing the bulk action
	 *
	 * @return array
	 */
	public function get_bulk_actions() {
		$actions = array(
			'bulk-delete' => 'Delete',
		);
		return $actions;
	}

	/**
	 * Handles data query and filter, sorting, and pagination.
	 */
	public function prepare_items() {

		$this->_column_headers = $this->get_column_info();

		/** Process bulk action */
		$this->process_bulk_action();

		$per_page     = $this->get_items_per_page( 'users_per_page', 20 );
		$current_page = $this->get_pagenum();
		$total_items  = self::record_count();

		$this->set_pagination_args(array(
			'total_items' => $total_items, //WE have to calculate the total number of items
			'per_page'    => $per_page //WE have to determine how many items to show on a page
		));

		$this->items = self::get_items( $per_page, $current_page );
	}
	public static function record_count() {
		global $wpdb;
		$sql = "SELECT COUNT(dd_notification_templates_html_wrapper_id) FROM dd_notification_templates_html_wrappers";
		return $wpdb->get_var( $sql );
	}
	public function process_bulk_action() {
		if ('delete' === $this->current_action() || ((isset($_POST['action']) && $_POST['action'] == 'bulk-delete'))) {
			$url_toredirect = '/wp-admin/admin.php?page=dd_notification_templates_html_wrappers';
			global $wpdb;
			if ('delete' === $this->current_action()) {
				// In our file that handles the request, verify the nonce.
				$nonce = esc_attr( $_REQUEST['_wpnonce'] );
				if (!wp_verify_nonce( $nonce, '***')) {
					die('invalid token');
				} else {
					$wpdb->query($wpdb->prepare('DELETE FROM dd_notification_templates_html_wrappers WHERE dd_notification_templates_html_wrapper_id=%d',$_GET['id']));
					wp_safe_redirect($url_toredirect.'&ddnt_msg='.urlencode('The HTML Wrapper has been deleted.'));
					exit;
				}
			}
			if (isset($_POST['action']) && $_POST['action'] == 'bulk-delete') {
				$delete_ids = $_POST['bulk-ids'];
				if (!empty($delete_ids)) {
					foreach ($delete_ids as $id) {
						$wpdb->query($wpdb->prepare('DELETE FROM dd_notification_templates_html_wrappers WHERE dd_notification_templates_html_wrapper_id=%d',$id));
					}
					wp_safe_redirect($url_toredirect.'&ddnt_msg='.urlencode('The HTML Wrappers have been deleted.'));
					exit;
				}
			}
		}
	}
}