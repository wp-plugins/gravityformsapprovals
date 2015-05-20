<?php



// Make sure Gravity Forms is active and already loaded.
if ( ! class_exists( 'GFForms' ) ) {
die();
}

// The Add-On Framework is not loaded by default.
// Use the following function to load the appropriate files.
GFForms::include_feed_addon_framework();

class GF_Approvals extends GFFeedAddOn {

	// The following class variables are used by the Framework.
	// They are defined in GFAddOn and should be overridden.

	// The version number is used for example during add-on upgrades.
	protected $_version = GF_APPROVALS_VERSION;

	// The Framework will display an appropriate message on the plugins page if necessary
	protected $_min_gravityforms_version = '1.9';

	// A short, lowercase, URL-safe unique identifier for the add-on.
	// This will be used for storing options, filters, actions, URLs and text-domain localization.
	protected $_slug = 'gravityformsapprovals';

	// Relative path to the plugin from the plugins folder.
	protected $_path = 'gravityformsapprovals/approvals.php';

	// Full path the the plugin.
	protected $_full_path = __FILE__;

	// Title of the plugin to be used on the settings page, form settings and plugins page.
	protected $_title = 'Gravity Forms Approvals';

	// Short version of the plugin title to be used on menus and other places where a less verbose string is useful.
	protected $_short_title = 'Approvals';

	private static $_instance = null;

	public static function get_instance() {

		if ( self::$_instance == null ) {
			self::$_instance = new GF_Approvals();
		}

		return self::$_instance;

	}

	public function init_admin() {
		parent::init_admin();
		add_action( 'gform_entry_detail_sidebar_before', array( $this, 'entry_detail_approval_box' ), 10, 2 );

		add_filter( 'gform_notification_events', array( $this, 'add_notification_event' ) );

		if ( GFAPI::current_user_can_any( 'gravityforms_edit_entries' ) ) {
			add_action( 'wp_dashboard_setup', array( $this, 'dashboard_setup' ) );
		}
	}

	public function init_frontend() {
		parent::init_frontend();
		add_filter( 'gform_disable_registration', array( $this, 'disable_registration' ), 10, 4 );
		add_action( 'gform_after_submission', array( $this, 'after_submission' ), 9, 2 );
	}

	//Registers the dashboard widget
	public function dashboard_setup() {
		wp_add_dashboard_widget( 'gf_approvals_dashboard', 'Forms Pending My Approval', array( $this, 'dashboard' ) );
	}

	/**
	 * Override the feed_settings_field() function and return the configuration for the Feed Settings.
	 * Updating is handled by the Framework.
	 *
	 * @return array
	 */
	function feed_settings_fields() {

		$accounts = get_users();

		$account_choices = array( array( 'label' => 'None', 'value' => '' ) );
		foreach ( $accounts as $account ) {
			$account_choices[] = array( 'label' => $account->display_name, 'value' => $account->ID );
		}

		return array(
			array(
				'title'  => 'Approver',
				'fields' => array(
					array(
						'name' => 'description',
						'label'       => 'Description',
						'type'        => 'text',
					),
					array(
						'name'     => 'approver',
						'label'    => 'Approver',
						'type'     => 'select',
						'choices'  => $account_choices,
					),
					array(
						'name'           => 'condition',
						'tooltip'        => "Build the conditional logic that should be applied to this feed before it's allowed to be processed.",
						'label'          => 'Condition',
						'type'           => 'feed_condition',
						'checkbox_label' => 'Enable Condition for this approver',
						'instructions'   => 'Require approval from this user if',
					),
				)
			),
		);
	}

	/**
	 * Adds columns to the list of feeds.
	 *
	 * setting name => label
	 *
	 * @return array
	 */
	function feed_list_columns() {
		return array(
			'description' => 'Description',
			'approver' => 'Approver',
		);
	}

	/**
	 * Fires after form submission only if conditions are met.
	 *
	 * @param $feed
	 * @param $entry
	 * @param $form
	 */
	function process_feed( $feed, $entry, $form ) {

		$approver = absint( $feed['meta']['approver'] );

		gform_update_meta( $entry['id'], 'approval_status_' . $approver, 'pending' );

	}

	/**
	 * Entry meta data is custom data that's stored and retrieved along with the entry object.
	 * For example, entry meta data may contain the results of a calculation made at the time of the entry submission.
	 *
	 * To add entry meta override the get_entry_meta() function and return an associative array with the following keys:
	 *
	 * label
	 * - (string) The label for the entry meta
	 * is_numeric
	 * - (boolean) Used for sorting
	 * is_default_column
	 * - (boolean) Default columns appear in the entry list by default. Otherwise the user has to edit the columns and select the entry meta from the list.
	 * update_entry_meta_callback
	 * - (string | array) The function that should be called when updating this entry meta value
	 * filter
	 * - (array) An array containing the configuration for the filter used on the results pages, the entry list search and export entries page.
	 *           The array should contain one element: operators. e.g. 'operators' => array('is', 'isnot', '>', '<')
	 *
	 *
	 * @param array $entry_meta An array of entry meta already registered with the gform_entry_meta filter.
	 * @param int   $form_id    The Form ID
	 *
	 * @return array The filtered entry meta array.
	 */
	function get_entry_meta( $entry_meta, $form_id ) {
		$feeds         = $this->get_feeds( $form_id );
		$has_approver = false;
		foreach ( $feeds as $feed ) {
			if ( ! $feed['is_active'] ) {
				continue;
			}
			$approver = absint( $feed['meta']['approver'] );

			$user_info = get_user_by( 'id', $approver );

			$display_name = $user_info ? $user_info->display_name : $approver;

			$entry_meta[ 'approval_status_' . $approver ] = array(
				'label'                      => 'Approval Status: ' . $display_name,
				'is_numeric'                 => false,
				'is_default_column'          => false, // this column will not be displayed by default on the entry list
				'filter'                     => array(
					'operators' => array( 'is', 'isnot' ),
					'choices'   => array(
						array( 'value' => 'pending', 'text' => 'Pending' ),
						array( 'value' => 'approved', 'text' => 'Approved' ),
						array( 'value' => 'rejected', 'text' => 'Rejected' ),
					)
				)
			);

			$has_approver = true;

		}
		if ( $has_approver ) {
			$entry_meta['approval_status'] = array(
				'label'                      => 'Approval Status',
				'is_numeric'                 => false,
				'update_entry_meta_callback' => array( $this, 'update_approval_status' ),
				'is_default_column'          => true, // this column will be displayed by default on the entry list
				'filter'                     => array(
					'operators' => array( 'is', 'isnot' ),
					'choices'   => array(
						array( 'value' => 'pending', 'text' => 'Pending' ),
						array( 'value' => 'approved', 'text' => 'Approved' ),
						array( 'value' => 'rejected', 'text' => 'Rejected' ),
					)
				)
			);
		}

		return $entry_meta;
	}

	/**
	 * The target of update_entry_meta_callback.
	 *
	 * @param string $key   The entry meta key
	 * @param array  $entry The Entry Object
	 * @param array  $form  The Form Object
	 *
	 * @return string|void
	 */
	function update_approval_status( $key, $entry, $form ) {
		return 'pending';
	}

	function entry_detail_approval_box( $form, $entry ) {
		global $current_user;

		if ( ! isset( $entry['approval_status'] ) ) {
			return;
		}

		if ( isset( $_POST['gf_approvals_status'] ) && check_admin_referer( 'gf_approvals' ) ) {

			$new_status = $_POST['gf_approvals_status'];
			gform_update_meta( $entry['id'], 'approval_status_' . $current_user->ID, $new_status );
			$entry[ 'approval_status_' . $current_user->ID ] = $new_status;
			$entry_approved = true;
			$entry_rejected = false;
			foreach ( $this->get_feeds( $form['id'] ) as $feed ) {
				if ( $feed['is_active'] ) {
					$approver = $feed['meta']['approver'];
					if ( ! empty( $entry[ 'approval_status_' . $approver ] ) ) {
						if ( $entry[ 'approval_status_' . $approver ] != 'approved' ) {
							$entry_approved = false;
						}
						if ( $new_status == 'rejected' ) {
							$entry_rejected = true;
						}
					}
				}
			}
			if ( $entry_rejected ) {
				gform_update_meta( $entry['id'], 'approval_status', 'rejected' );
				$entry['approval_status'] = 'rejected';
			} elseif ( $entry_approved ) {
				gform_update_meta( $entry['id'], 'approval_status', 'approved' );
				$entry['approval_status'] = 'approved';

				// Integration with the User Registration Add-On
				if ( class_exists( 'GFUser' ) ) {
					GFUser::gf_create_user( $entry, $form );
				}

				// Integration with the Zapier Add-On
				if ( class_exists( 'GFZapier' ) ) {
					GFZapier::send_form_data_to_zapier( $entry, $form );
				}
			}

			$notifications_to_send = GFCommon::get_notifications_to_send( 'form_approval', $form, $entry );
			foreach ( $notifications_to_send as $notification ) {
				GFCommon::send_notification( $notification, $form, $entry );
			}
		}
		$status = 'Pending Approval';
		$approve_icon = '<i class="fa fa-check" style="color:green"></i>';
		$reject_icon = '<i class="fa fa-times" style="color:red"></i>';
		if ( $entry['approval_status'] == 'approved' ) {
			$status = $approve_icon . ' Approved';
		} elseif ( $entry['approval_status'] == 'rejected' ) {
			$status = $reject_icon .' Rejected';
		}
		?>
		<div class="postbox">
			<h3><?php echo $status ?></h3>

			<div style="padding:10px;">
				<ul>
					<?php
					$has_been_approved = false;
					foreach ( $this->get_feeds( $form['id'] ) as $feed ) {
						if ( $feed['is_active'] ) {
							$approver = $feed['meta']['approver'];
							if ( ! empty( $entry[ 'approval_status_' . $approver ] ) ) {
								$user_info = get_user_by( 'id', $approver );
								$status    = $entry[ 'approval_status_' . $approver ];
								if ( $status != 'pending' ) {
									$has_been_approved = true;
								}
								echo '<li>' . $user_info->display_name . ': ' . $status . '</li>';
							}
						}
					}
					if ( $has_been_approved ) {
						add_action( 'gform_entrydetail_update_button', array( $this, 'remove_entrydetail_update_button' ), 10 );
					}
					?>
				</ul>
				<div>
					<?php
					if ( isset( $entry[ 'approval_status_' . $current_user->ID ] ) && $entry[ 'approval_status_' . $current_user->ID ] == 'pending' ) {
						?>
						<form method="post" id="sidebar_form" enctype='multipart/form-data'>
							<?php wp_nonce_field( 'gf_approvals' );	?>
							<button name="gf_approvals_status" value="approved" type="submit" class="button">
								<?php echo $approve_icon; ?> Approve
							</button>
							<button name="gf_approvals_status" value="rejected" type="submit" class="button">
								<?php echo $reject_icon; ?> Reject
							</button>
						</form>
					<?php
					}
					?>
				</div>
			</div>
		</div>
	<?php
	}

	/**
	 * Displays the Dashboard UI
	 */
	public static function dashboard() {
		global $current_user;

		$search_criteria['field_filters'][] = array( 'key' => 'approval_status_' . $current_user->ID, 'value' => 'pending' );
		$search_criteria['field_filters'][] = array( 'key' => 'approval_status', 'value' => 'pending' );

		$entries = GFAPI::get_entries( 0, $search_criteria );

		if ( sizeof( $entries ) > 0 ) {
			?>
			<table class="widefat" cellspacing="0" style="border:0px;">
				<thead>
				<tr>
					<td><i>Form</i></td>
					<td><i>User</i></td>
					<td><i>Submission Date</i></td>
				</tr>
				</thead>

				<tbody class="list:user user-list">
				<?php
				foreach ( $entries as $entry ) {
					$form = GFAPI::get_form( $entry['form_id'] );
					$user = get_user_by( 'id', (int) $entry['created_by'] );
					$url_entry = sprintf( 'admin.php?page=gf_entries&view=entry&id=%d&lid=%d', $entry['form_id'], $entry['id'] );
					$url_entry = esc_url( admin_url( $url_entry ) );
					?>
					<tr>
						<td>
							<?php
							echo "<a href='{$url_entry}'>{$form['title']}</a>";
							?>
						</td>
						<td>
							<?php
							echo "<a href='{$url_entry}'>{$user->display_name}</a>";
							?>
						</td>
						<td>
							<?php
							echo "<a href='{$url_entry}'>{$entry['date_created']}</a>";
							?>
						</td>
					</tr>
				<?php
				}
				?>
				</tbody>
			</table>

		<?php
		} else {
			?>
			<div>
				Hurray, inbox zero!.
			</div>
		<?php
		}
	}

	function remove_entrydetail_update_button( $button ) {
		return 'This entry has been approved and can no longer be edited';
	}

	function add_notification_event( $events ) {
		$events['form_approval'] = 'Form is approved or rejected';
		return $events;
	}

	function disable_registration( $is_disabled, $form, $entry, $fulfilled ) {

		//check status to decide if registration should be stopped
		if ( isset( $entry['approval_status'] ) && $entry['approval_status'] == 'approved' ) {
			//disable registration
			return false;
		} else {
			return true;
		}
	}

	function after_submission( $entry, $form ) {

		//check submitted values to decide if data should be should be stopped before sending to Zapier
		if ( isset( $entry['approval_status'] ) && $entry['approval_status'] != 'approved' ) {
			remove_action( 'gform_after_submission', array( 'GFZapier', 'send_form_data_to_zapier' ) );
		}
	}

}
