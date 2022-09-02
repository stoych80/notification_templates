<?php
if (!class_exists( 'WP_List_Table' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}
class dd_notification_templates_list extends WP_List_Table {

	private static $filters_where=array();
	/** Class constructor */
	public function __construct() {
		parent::__construct( array(
			'singular' => __('Notification Template', 'sp'), //singular name of the listed records
			'plural'   => __('Notification Templates', 'sp'), //plural name of the listed records
			'ajax'     => false,
			'screen' => 'dd_notification_templates_list'
		));
		if (isset($_REQUEST['ddnt_filter']) && is_array($_REQUEST['ddnt_filter'])) {
			foreach ($_REQUEST['ddnt_filter'] as $f=>$val) {
				if (is_string($val)) {
					$val=trim($val);
				} else if (is_array($val)) {
					$val=  array_map('trim', $val);
				}
				if (empty($val)) continue;
				if ($f=='dd_notification_template_ref' || $f=='title' || $f=='subject' || $f=='body' || $f=='description') {
					self::$filters_where[]="nt.$f LIKE '%".esc_sql($val)."%'";
				} else if ($f=='status') {
					self::$filters_where[]="nt.$f = '".esc_sql($val)."'";
				} else if (($f=='dd_notification_templates_html_wrapper_id' || $f=='dd_notification_templates_group_id') && is_array($val)) {
					$val_sanitised=array();
					foreach ($val as $val2) {
						if (is_numeric($val2)) $val_sanitised[]=$val2;
					}
					if (empty($val_sanitised)) continue;
					self::$filters_where[]="nt.$f IN (".implode(',',$val_sanitised).")";
				}
			}
		}
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
		$sql = "SELECT nt.dd_notification_template_ref,nt.title,nt.subject,nt.body,nt.description,nt.substitution,nt.record_modified,nt.status,nt.is_html,wr.title as wrapper_title,gr.title as group_title FROM dd_notification_templates nt
				LEFT JOIN dd_notification_templates_html_wrappers wr USING(dd_notification_templates_html_wrapper_id)
				LEFT JOIN dd_notification_templates_groups gr USING(dd_notification_templates_group_id)";
		if (self::$filters_where) {
			$sql .= ' WHERE '.  implode(' AND ', self::$filters_where);
		}
		if (!empty($_REQUEST['orderby']) && ($_REQUEST['orderby'] == 'title' || $_REQUEST['orderby'] == 'subject' || $_REQUEST['orderby'] == 'record_modified' || $_REQUEST['orderby'] == 'status' || $_REQUEST['orderby'] == 'is_html' || $_REQUEST['orderby'] == 'wrapper_title' || $_REQUEST['orderby'] == 'group_title')) {
			$sql .= ' ORDER BY ' . esc_sql($_REQUEST['orderby']);
			$sql .= !empty($_REQUEST['order']) ? ' ' . esc_sql($_REQUEST['order']) : ' ASC';
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
		_e( 'No Notification Templates.', 'sp');
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
		return sprintf('<input type="checkbox" name="bulk-ids[]" value="%s" />', $item['dd_notification_template_ref']);
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
			'edit' => sprintf('<a href="?page=%s&action=%s&id=%s">Edit</a>', esc_attr($_REQUEST['page']), 'edit', $item['dd_notification_template_ref']),
			'delete' => sprintf('<a href="?page=%s&action=%s&id=%s&_wpnonce=%s" class="delete_notif_template">Delete</a>', esc_attr($_REQUEST['page']), 'delete', $item['dd_notification_template_ref'], $delete_nonce)
		);
		return $item['title'] . $this->row_actions( $actions ) .'<br><u>System Ref</u>: '. $item['dd_notification_template_ref'];
	}
	function column_subject( $item ) {
		return $item['subject'];
	}
	function column_body( $item ) {
		return $item['body'];
	}
	function column_description($item) {
		return $item['description'];
	}
	function column_substitution($item) {
		$subs = dd_notification_templates::getSubstitutionFields($item['substitution'], $item['dd_notification_template_ref']);
		$subs_html = '';
		foreach ($subs as $name => $details) :
			$subs_html .= '<tr><td style="text-align:left;vertical-align:top;border-bottom:solid 1px #DDD !important;">'.$details['title'].($details['required'] ? ' <span style="color:red;">*</span>' : '').'<br>{'.$name.'}<br><i>'.$details['example'].'</i></td></tr>';
		endforeach;
		return $subs_html ? '<table cellpadding="6" cellspacing="0" style="">'.$subs_html.'</table>' : '';
	}
	function column_record_modified($item) {
		return !empty($item['record_modified']) ? date('d/m/Y H:i',strtotime($item['record_modified'])) : '';
	}
	function column_status($item) {
		return array_key_exists($item['status'], dd_notification_templates::$statuses) ? dd_notification_templates::$statuses[$item['status']] : 'Unknown';
	}
	function column_is_html($item) {
		return !empty($item['is_html']) ? '<img src="/wp-content/plugins/dd_notification_templates/images/check.gif" />' : '<img src="/wp-content/plugins/dd_notification_templates/images/cross.png" />';
	}
	function column_wrapper_title($item) {
		return $item['wrapper_title'];
	}
	function column_group_title($item) {
		return $item['group_title'];
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
			'subject'    => 'Subject',
			'body'    => 'Body',
			'description'=> 'Description',
			'substitution'=> 'Macros',
			'record_modified'=> 'Record Modified',
			'status'=> 'Status',
			'is_html'=> 'Is html',
			'wrapper_title'=> 'HTML Wrapper',
			'group_title'=> 'Group'
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
			'title' => array('title', true),
			'subject' => array('subject', true),
			'record_modified' => array('record_modified', true),
			'status' => array('status', true),
			'is_html' => array('is_html', true),
			'wrapper_title' => array('wrapper_title', true),
			'group_title' => array('group_title', true)
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
		$sql = "SELECT COUNT(nt.dd_notification_template_ref) FROM dd_notification_templates nt LEFT JOIN dd_notification_templates_html_wrappers wr USING(dd_notification_templates_html_wrapper_id)";
		if (self::$filters_where) {
			$sql .= ' WHERE '.  implode(' AND ', self::$filters_where);
		}
		return $wpdb->get_var($sql);
	}
	public function process_bulk_action() {
		if ('delete' === $this->current_action() || ((isset($_POST['action']) && $_POST['action'] == 'bulk-delete'))) {
			$url_toredirect = '/wp-admin/admin.php?page=dd_notification_templates';
			global $wpdb;
			if ('delete' === $this->current_action()) {
				// In our file that handles the request, verify the nonce.
				$nonce = esc_attr( $_REQUEST['_wpnonce'] );
				if (!wp_verify_nonce( $nonce, '***')) {
					die('invalid token');
				} else {
					$wpdb->query($wpdb->prepare('DELETE FROM dd_notification_templates WHERE dd_notification_template_ref=%s',$_GET['id']));
					wp_safe_redirect($url_toredirect.'&ddnt_msg='.urlencode('The Notification Template has been deleted.'));
					exit;
				}
			}
			if (isset($_POST['action']) && $_POST['action'] == 'bulk-delete') {
				$delete_ids = $_POST['bulk-ids'];
				if (!empty($delete_ids)) {
					foreach ($delete_ids as $id) {
						$wpdb->query($wpdb->prepare('DELETE FROM dd_notification_templates WHERE dd_notification_template_ref=%s',$id));
					}
					wp_safe_redirect($url_toredirect.'&ddnt_msg='.urlencode('The Notification Templates have been deleted.'));
					exit;
				}
			}
		}
	}
}