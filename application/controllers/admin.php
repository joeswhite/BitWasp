<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Administration Panel Controller
 *
 * @package		BitWasp
 * @subpackage	Controllers
 * @category	Admin
 * @author		BitWasp
 */

class Admin extends CI_Controller {

	public $nav;

	/**
	 * Constructor
	 *
	 * @access	public
	 * @see		Models/Categories_model
	 */

	public function __construct() {
		parent::__construct();
		$this->load->model('categories_model');
		
		// Define information for the navigation panel.
		$this->nav = array(	'' => 			array(	'panel' => '',
													'title' => 'General',
													'heading' => 'Admin Panel'),
							'bitcoin' => 	array(  'panel' => '/bitcoin',
													'title' => 'Bitcoin',
													'heading' => 'Bitcoin Panel'),
							'items' =>		array(	'panel' => '/items',
													'title' => 'Items',
													'heading' => 'Items Panel'),
							'users' => 		array(	'panel' => '/users',
													'title' => 'Users',
													'heading' => 'User Panel'),
							'autorun' => 	array(	'panel' => '/autorun',
													'title' => 'Autorun',
													'heading' => 'Autorun Panel'),
							'logs' =>		array(	'panel' => '/logs',
													'title' => 'Logs',
													'heading' => 'Logs Panel')
						);
	}
	
	/**
	 * Load the General Information Panel.
	 * URI: /admin
	 * 
	 * Load general info about the site, like OpenSSL version, GPG version,
	 * and other general site settings from the database. Has Admin Nav Bar.
	 * 
	 * @see 	Libraries/GPG
	 * @see 	Libraries/Bw_Config
	 * @return	void
	 */
	public function index() {
		$this->load->library('gpg');
		if($this->gpg->have_GPG == TRUE) 
			$data['gpg'] = 'gnupg-'.$this->gpg->version;
		$data['openssl'] = OPENSSL_VERSION_TEXT;
		$data['config'] = $this->bw_config->load_admin('');
		
		$data['page'] = 'admin/index';
		$data['title'] = $this->nav['']['heading'];
		$data['nav'] = $this->generate_nav();
		$this->load->library('Layout', $data);
	}

	/**
	 * Edit General Settings.
	 * URI: /admin/edit
	 * 
	 * Compare the POSTed fields with those on record. If there's any
	 * difference, this will be submitted to the database. If it's the same
	 * the variable is set to NULL, and filtered using array_filter.
	 * Changes are commited and the user is redirected to the info panel.
	 * Has Admin Nav Bar.
	 * 
	 * @see 	Libraries/GPG
	 * @see		Libraries/Form_Validation
	 * @see 	Libraries/Bw_Config
	 * @return	void
	 */
	public function edit_general() {
		$this->load->library('form_validation');
		$data['config'] = $this->bw_config->load_admin('');
		
		if($this->form_validation->run('admin_edit_') == TRUE) {
			// Determine which settings have changed. Filter unchanged.
			$changes['site_description'] = ($this->input->post('site_descrpition') !== $data['config']['site_description']) ? $this->input->post('site_description') : NULL;
			$changes['site_title'] = ($this->input->post('site_title') !== $data['config']['site_title']) ? $this->input->post('site_title') : NULL;
			$changes['openssl_keysize'] = ($this->input->post('openssl_keysize') !== $data['config']['openssl_keysize']) ? $this->input->post('openssl_keysize') : NULL;
			$changes['allow_guests'] = ($this->input->post('allow_guests') !== $data['config']['allow_guests']) ? $this->input->post('allow_guests') : NULL;
			$changes = array_filter($changes, 'strlen');
	
			if($this->config_model->update($changes) == TRUE)
				redirect('admin');			
		}
		$data['page'] = 'admin/edit_';
		$data['title'] = $this->nav['']['heading'];
		$data['nav'] = $this->generate_nav();
		$this->load->library('Layout', $data);
	}
	
	/**
	 * Load the Logs Information Panel.
	 * URI: /admin/autorun
	 * 
	 * User is shown the amount of transcations, order's, and messages on
	 * record. 
	 * 
	 * @see 	Libraries/Bw_Config
	 * @see		Models/Autorun_Model
	 * @return	void
	 */
	public function autorun() {
		$this->load->model('autorun_model');
		$data['page'] = 'admin/autorun';
		$data['title'] = $this->nav['autorun']['heading'];

		$data['transaction_count'] = $this->general_model->count_transactions();
		$data['order_count'] = $this->general_model->count_orders();
		$data['messages_count'] = $this->general_model->count_entries('messages');

		$data['jobs'] = $this->autorun_model->load_all();
		$data['config'] = $this->bw_config->load_admin('autorun');
		$data['nav'] = $this->generate_nav();
		
		$this->load->library('Layout', $data);
	}
	
	/**
	 * Edit Autorun
	 * 
	 * Edit the settings regarding how long different information is kept.
	 * URI: /admin/edit/autorun
	 * 
	 * Need to add the form!
	 * 
	 * @see 	Libraries/Bw_Config
	 * @see		Models/Autorun_Model
	 * @return	void
	 */
	 public function edit_autorun() {
		$this->load->library('form_validation');
		$this->load->model('autorun_model');
		
		$data['page'] = 'admin/edit_autorun';
		$data['title'] = $this->nav['autorun']['heading'];
		$data['jobs'] = $this->autorun_model->load_all();
		
		
		if($this->form_validation->run('admin_edit_autorun') == TRUE){
			
			// Load the array of jobs, and the specified intervals.
			$jobs = $this->input->post('jobs');
			$update = FALSE;
			// Load the array of disabled jobs.
			$disabled_jobs = $this->input->post('disabled_jobs');
			foreach($jobs as $index => $interval){
				// Intervals should always be numeric. 
				if(!is_numeric($interval))
					redirect('admin/autorun');
					
				// Set the interval to zero if a job is disabled.
				if($data['jobs'][$index] !== '0' && $disabled_jobs[$index] == '1'){
					if($this->autorun_model->set_interval($index, '0') == TRUE)
						$update = TRUE;
				} else {
				
					// If the job exists, and the interval has changed..
					if(isset($data['jobs'][$index]) && $data['jobs'][$index]['interval'] !== $interval){
						// Update the interval.
						if($this->autorun_model->set_interval($index, $interval) == TRUE)
							$update = TRUE;

						// If the interval has changed, rerun the job??
						if($interval !== '0')
							$this->autorun->jobs[$index]->job();
					}
				}
			}
			// If the update happened successfully, redirect!
			if($update)
				redirect('admin/autorun');
		}

		$data['config'] = $this->bw_config->load_admin('autorun');
		$data['nav'] = $this->generate_nav();
		
		$this->load->library('Layout', $data);
	}

	/**
	 * Load the Bitcoin Information Panel.
	 * URI: /admin/bitcoin
	 * 
	 * This panel displays information about the accounts in the bitcoin
	 * wallet, the number of transactions processed to date, the source
	 * of the bitcoin exchange rates, and the latest block.
	 * Has Admin Nav Bar.
	 * 
	 * @see 	Libraries/Bw_Bitcoin
	 * @see		Models/Bitcoin_Model
	 * @return	void
	 */	
	public function bitcoin() {
		$this->load->library('bw_bitcoin');
		$this->load->model('bitcoin_model');
		$data['config'] = $this->bw_config->load_admin('bitcoin');
		$data['latest_block'] = $this->bitcoin_model->latest_block();
		$data['transaction_count'] = $this->general_model->count_transactions();
		$data['accounts'] = $this->bw_bitcoin->listaccounts(0);
		$data['bitcoin_index'] = $this->bw_config->price_index;
		$data['bitcoin_info'] = $this->bw_bitcoin->getinfo();
		
		// If there is any information about a recent transaction, display it.
		$info = (array)json_decode($this->session->flashdata('info'));
		if(count($info) !== 0){
			// If the information is to do with topping up a WIF key:
			if($info['action'] == 'topup'){
				$topup_amount = $data['accounts'][$info['account']]-$info['old_amount'];
				$data['returnMessage'] = "BTC {$topup_amount} was added to the '{$info['account']}' account.";
			}
		}
		
		$data['page'] = 'admin/bitcoin';
		$data['title'] = $this->nav['bitcoin']['heading'];
		$data['nav'] = $this->generate_nav();
		$this->load->library('Layout', $data);
	}
	
	/**
	 * Edit the Bitcoin Settings.
	 * URI: /admin/edit/bitcoin
	 * 
	 * If the user submitted the Price Index form, we check for updates.
	 * If the source specified exists, then update the config setting.
	 * + If the source was previously disabled, re-setup the periodic updates.
	 * + Trigger a new update from the new price index.
	 * If the source is set to disabled, then disable the periodic updates.
	 * by setting the interval to zero.
	 * 
	 * If the user submitted the form to transfer coins, check that the
	 * sending account has sufficient balance. If so, transfer coins, and
	 * redirect to Bitcoin Information Page. If not, display an error.
	 * Has Admin Nav Bar.
	 * 
	 * @see 	Libraries/Bw_Bitcoin
	 * @see		Libraries/Bw_Config
	 * @see		Libraries/Form_Validation
	 * @return	void
	 */
	public function edit_bitcoin() {
		$this->load->library('form_validation');
		$this->load->library('bw_bitcoin');
		$this->load->model('bitcoin_model');
		$this->load->model('autorun_model');
		
		$data['config'] = $this->bw_config->load_admin('bitcoin');
		// Load the current selection for the bitcoin price index.
		$data['price_index'] = $this->bw_config->price_index;
		// Load the list of accounts in the bitcoin daemon.
		$data['accounts'] = $this->bw_bitcoin->listaccounts(0);
		
		// If the WIF Import form was submitted:
		if($this->input->post('submit_wallet_topup') == 'Topup') {
			if($this->form_validation->run('admin_wallet_topup') == TRUE) {
				// Attempt to import the private key
				$import = $this->bw_bitcoin->importprivkey($this->input->post('wif'), $this->input->post('topup_account'));
				if(isset($import['code'])){
					// If there is an error, display it. 
					$data['import_wallet_error'] = $import['message'];
				} else if($import == NULL) {
					// Successful import, record the data to be displayed.
					$info = json_encode(array('action' => 'topup',
											  'old_amount' => $data['accounts'][$this->input->post('topup_account')],
											  'account' => $this->input->post('topup_account')));
					$this->session->set_flashdata("info",$info);
					redirect('admin/bitcoin');
				}
			}
		}
		
		// If the Settings form was submitted:
		if($this->input->post('submit_edit_bitcoin') == 'Update') {
			if($this->form_validation->run('admin_edit_bitcoin') == TRUE) {
			
				// Alter the transaction purging period.
				$changes['delete_transactions_after'] = ($this->input->post('delete_transactions_after') !== $data['config']['delete_transactions_after']) ? $this->input->post('delete_transactions_after') : NULL ;
				// If we're disabling auto-deleting transactions, set that.
				if($this->input->post('delete_transactions_after_disabled') == '1') $changes['delete_transactions_after'] = "0";

				// Alter the balance backup method
				$changes['balance_backup_method'] = ($this->input->post('balance_backup_method') !== $data['config']['balance_backup_method']) ? $this->input->post('balance_backup_method') : NULL;
				if($this->input->post('balance_backup_method_disabled') == '1') $changes['balance_backup_method'] = 'Disabled';

				// Disable wallet backups if the MPK isn't set up.
				if($changes['balance_backup_method'] == 'Electrum' && $this->bw_config->electrum_mpk == ''){
					$this->autorun_model->set_interval('backup_wallet', '0');
				}

				// Check if electrum_mpk is being updated with text.
				if($this->bw_config->balance_backup_method == 'Electrum') {
					$electrum_mpk = htmlentities($this->input->post('electrum_mpk'));
					if(strlen($electrum_mpk) > 0)
						$changes['electrum_mpk'] = ($electrum_mpk !== $data['config']['electrum_mpk']) ? $electrum_mpk : NULL ;
				}
				// See later for how we set electrum_mpk to ''.

				// Load the array of accounts, and the balance to back up.
				$backup_balances = $this->input->post('account');
				// Load the array of disabled accounts (value will = 1)
				$disabled_backups = $this->input->post('backup_disabled');
				if(is_array($backup_balances)) {
					foreach($backup_balances as $account => $balance) {
						// Disable access to '' and topup.
						if($this->general->matches_any($account, array('','topup')) == TRUE)
							continue;
						
						// If the account exists in bitcoind, but not
						// in the database, try create the entry. 
						// Skip update if unsuccessful.
						$var = "max_".$account."_balance";
						if(!isset($data['config'][$var]) && isset($data['accounts'][$account])) {
							if(!$this->config_model->create($var, '0.00000000'))
								continue;
						}
						
						$changes[$var] = ($balance !== $data['config'][$var]) ? $balance: NULL;						
						
						if($disabled_backups[$account] == '1')
							$changes[$var] = ($balance !== '0') ? 0.00000000 : $changes[$var];	
					}
				}
				
				// Check if the selection exists.
				if($data['config']['price_index'] !== $this->input->post('price_index')){
					if(is_array($data['config']['price_index_config'][$this->input->post('price_index')]) || $this->input->post('price_index') == 'Disabled'){
					
						$update = array('price_index' => $this->input->post('price_index'));
						$this->config_model->update($update);
						
						if($this->input->post('price_index') !== 'Disabled'){		
							// If the price index was previously disabled, set the auto-run script interval back up..
							if($data['price_index'] == 'Disabled') 
								$this->config_model->set_autorun_interval('price_index','15');
								
							// And request new exchange rates.
							$this->bw_bitcoin->ratenotify();
						} else {
							// When disabling BPI updates, set the interval to 0.
							$this->config_model->set_autorun_interval('price_index', '0');
						}
						// Redirect when complete.
						redirect('admin/bitcoin');
					}
				}
				
				$changes = array_filter($changes, 'strlen');
		
				// Check if electrum_mpk is being blanked.
				if($this->bw_config->balance_backup_method == 'Electrum') {
					$electrum_mpk = htmlentities($this->input->post('electrum_mpk'));
					if(strlen($electrum_mpk) == 0)
						$changes['electrum_mpk'] = '';
				}
		
				if(count($changes) > 0 && $this->config_model->update($changes) == TRUE)
					redirect('admin/bitcoin');	
			}
		}
		
		// If the bitcoin transfer form has been completed:
		if($this->input->post('admin_transfer_bitcoins') == 'Send') {
			
			if($this->form_validation->run('admin_transfer_bitcoins') == TRUE) {
				
				// Check that the account has the specified available balance.
				$amount = $this->input->post('amount');
				if($data['accounts'][$this->input->post('from')] >= (float)$amount) {
					
					if($this->bw_bitcoin->move($this->input->post('from'), $this->input->post('to'), (float)$amount) == TRUE)
						redirect('admin/bitcoin');
				} else {
					// Return an error if not redirected.
					$data['transfer_bitcoins_error'] = 'That account has insufficient funds.';
				}
			}
		}
		$data['page'] = 'admin/edit_bitcoin';
		$data['title'] = $this->nav['bitcoin']['heading'];
		$data['nav'] = $this->generate_nav();
		$data['use_electrum'] = ($this->bw_config->balance_backup_method == "Electrum") ? TRUE : FALSE;
		$this->load->library('Layout', $data);
	}
	
	/**
	 * Load the Users Information Panel.
	 * URI: /admin/users
	 * 
	 * Display user count, and global user configuration settings like
	 * session timeout, the captcha length, whether users can register on 
	 * the register form, whether vendors may register on the site, whether
	 * PM's are encrypted using RSA, whether vendors should be forced to 
	 * have a PGP associated with their account, or how long it takes before
	 * banning a user due to inactivity.
	 * 
	 * @see		Libraries/Bw_Config
	 * @see		Libraries/Form_Validation
	 * @return	void
	 */	
	public function users() {
		$data['nav'] = $this->generate_nav();
		$data['user_count'] = $this->general_model->count_entries('users');
		$data['config'] = $this->bw_config->load_admin('users');

		$data['page'] = 'admin/users';
		$data['title'] = $this->nav['users']['heading'];
		$this->load->library('Layout', $data);
	}

	/**
	 * Edit the User Settings.
	 * URI: /admin/edit/users
	 * 
	 * Alter the user settings. Work out which fields are different, and
	 * set the corresponding $changes[] entry to the POST fields. Unchanged
	 * entries are set to NULL and filtered. Changes are saved and the user
	 * redirected back to the User Info Page. Has Admin Nav Bar.
	 * 
	 * @see 	Libraries/Bw_Bitcoin
	 * @see		Libraries/Bw_Config
	 * @see		Libraries/Form_Validation
	 * @return	void
	 */	
	public function edit_users() {
		$this->load->library('form_validation');
		$data['nav'] = $this->generate_nav();
		$data['config'] = $this->bw_config->load_admin('users');
		
		if($this->form_validation->run('admin_edit_users') == TRUE) {
			// Determine what changes, if any, to make. 
			$changes['login_timeout'] = ((int)$this->input->post('login_timeout') !== $data['config']['login_timeout']) ? $this->input->post('login_timeout') : NULL;
			$changes['captcha_length'] = ((int)$this->input->post('captcha_length') !== $data['config']['captcha_length']) ? $this->input->post('captcha_length') : NULL;
			$changes['registration_allowed'] = ((int)$this->input->post('registration_allowed') !== $data['config']['registration_allowed']) ? $this->input->post('registration_allowed'): NULL;
			$changes['vendor_registration_allowed'] = ((int)$this->input->post('vendor_registration_allowed') !== $data['config']['vendor_registration_allowed']) ? $this->input->post('vendor_registration_allowed'): NULL;
			$changes['encrypt_private_messages'] = ((int)$this->input->post('encrypt_private_messages') !== $data['config']['encrypt_private_messages']) ? $this->input->post('encrypt_private_messages'): NULL;
			$changes['force_vendor_pgp'] = ((int)$this->input->post('force_vendor_pgp') !== $data['config']['force_vendor_pgp']) ? $this->input->post('force_vendor_pgp') : NULL;
			$changes['refund_after_inactivity'] = ($this->input->post('refund_after_inactivity') !== $data['config']['refund_after_inactivity']) ? $this->input->post('refund_after_inactivity') : NULL ;			
			$changes['delete_messages_after'] = ($this->input->post('delete_messages_after') !== $data['config']['delete_messages_after']) ? $this->input->post('delete_messages_after') : NULL ;			
			$changes['entry_payment_buyer'] = ($this->input->post('entry_payment_buyer') !== $data['config']['entry_payment_buyer']) ? $this->input->post('entry_payment_buyer') : NULL;
			$changes['entry_payment_vendor'] = ($this->input->post('entry_payment_vendor') !== $data['config']['entry_payment_vendor']) ? $this->input->post('entry_payment_vendor') : NULL;
			
			// If we're disabling auto-banning users after inactivity, set that.
			if($this->input->post('refund_after_inactivity_disabled') == '1') 		$changes['refund_after_inactivity'] = "0";

			// If we're disabling auto-clearing of user messages.
			if($this->input->post('delete_messages_after_disabled') == '1')		$changes['delete_messages_after'] = "0";				
			
			// Set registration payments for buyer/vendor to zero if disabled.
			if($this->input->post('entry_payment_buyer_disabled') == '1')		$changes['entry_payment_buyer'] = '0';
			if($this->input->post('entry_payment_vendor_disabled') == '1')		$changes['entry_payment_vendor'] = '0';
			
			$changes = array_filter($changes, 'strlen');

			// Update config
			if($this->config_model->update($changes) == TRUE)
				redirect('admin/users');
		} 
		
		
		$data['config'] = $this->bw_config->load_admin('users');
		$data['page'] = 'admin/edit_users';
		$data['title'] = $this->nav['users']['heading'];
		$this->load->library('Layout', $data);
	}
	
	/**
	 * Load the Items Information Panel.
	 * URI: /admin/items
	 * 
	 * @see 	Libraries/Bw_Bitcoin
	 * @see		Libraries/Bw_Config
	 * @see 	Models/Categories_Model
	 * @return	void
	 */
	public function items() {
		$data['nav'] = $this->generate_nav();
		$data['item_count'] = $this->general_model->count_entries('items');
		$data['config'] = $this->bw_config->load_admin('items');
		$data['categories'] = $this->categories_model->list_all();
		$data['page'] = 'admin/items';
		$data['title'] = $this->nav['items']['heading'];
		$this->load->library('Layout', $data);
	}

	/**
	 * Edit the Items Settings.
	 * URI: /admin/edit/items
	 * 
	 * Edit Item settings. Mainly just add/rename/delete categories.
	 * 
	 * @see 	Models/Categories_Model
	 * @see		Libraries/Bw_Config
	 * @see		Libraries/Form_Validation
	 * @return	void
	 */
	public function edit_items() {
		$this->load->library('form_validation');
		$data['nav'] = $this->generate_nav();
		$data['categories'] = $this->categories_model->list_all();
		$data['config'] = $this->bw_config->load_admin('items');
				
		if($this->input->post('admin_edit_items') == 'Update') {
			if($this->form_validation->run('admin_edit_items') == TRUE) {
				$changes['auto_finalize_threshold'] = ($data['config']['auto_finalize_threshold'] == $this->input->post('auto_finalize_threshold') ) ? NULL : $this->input->post('auto_finalize_threshold');
				
				$changes = array_filter($changes, 'strlen');
				if(count($changes) > 0)
					if($this->config_model->update($changes) == TRUE)
						redirect('admin/users');
			}
		}	
			
		// If the Add Category form has been submitted:
		if($this->input->post('add_category') == 'Add') {
			if($this->form_validation->run('admin_add_category') == TRUE) {
				// Add the category.
				$category = array(	'name' => $this->input->post('create_name'),
									'hash' => $this->general->unique_hash('categories','hash'),
									'parent_id' => $this->input->post('category_parent'));
				if($this->categories_model->add($category) == TRUE)
					redirect('admin/edit/items');
			} 
		} 
		
		// If the Rename Category form has been submitted:
		if($this->input->post('rename_category') == 'Rename') {
			if($this->form_validation->run('admin_rename_category') == TRUE) {
				// Rename the category.
				if($this->categories_model->rename($this->input->post('rename_id'), $this->input->post('category_name')) == TRUE)
					redirect('admin/edit/items');
			}
		}
		
		// If the Delete Category form has been submitted:
		if($this->input->post('delete_category') == 'Delete') {
			if($this->form_validation->run('admin_delete_category') == TRUE) {
		
				$category = $this->categories_model->get(array('id' => $this->input->post('delete_id')));
				$cat_children = $this->categories_model->get_children($category['id']);

				// Check if items or categories are orphaned by this action, redirect to move these.
				if($category['count_items'] > 0 || $cat_children['count'] > 0) {
					redirect('admin/category/orphans/'.$category['hash']);
				} else {
					// Otherwise it's empty and can be deleted.
					if($this->categories_model->delete($category['id']) == TRUE)
						redirect('admin/edit/items');
				}
			}
		}
		$data['page'] = 'admin/edit_items';
		$data['title'] = $this->nav['items']['heading'];
		$this->load->library('Layout', $data);
	}

	public function logs($record = NULL) {

		$this->load->model('logs_model');
		
		if($record == NULL){
			$data['nav'] = $this->generate_nav();
			$data['page'] = 'admin/logs_list';
			$data['title'] = 'Logs';
			$data['logs'] = $this->logs_model->fetch();
			
		} else {
			$data['log'] = $this->logs_model->fetch($record);
			if($data['log'] == FALSE)
				redirect('admin/logs');

			$data['page'] = 'admin/log';
			$data['title'] = "Log Record: {$data['log']['id']}";

		}
		$this->load->library('Layout', $data);		
		
	}


	/**
	 * Fix orphan categories/items.
	 * URI: /admin/category/orphans/$hash
	 * 
	 * If a category is to be deleted, where the result would orphan
	 * any items or categories, they need to be looked after. Calculate
	 * what we have to say to the user. If there's nothing to do for this
	 * category then redirect away from this form. 
	 * 
	 * If the form is submitted correctly, then update the records.
	 * Finally, if the category is successfully removed, return TRUE,
	 * otherwise return FALSE on failure.
	 * 
	 * @param	string
	 * @see 	Models/Categories_Model
	 * @see		Libraries/Form_Validation
	 * @return	void
	 */	
	public function category_orphans($hash) {
		// Abort if the category does not exist.
		$data['category'] = $this->categories_model->get(array('hash' => $hash));
		if($data['category'] == FALSE)
			redirect('admin/items');
			
		$this->load->library('form_validation');
			
		// Load the list of categories.
		$data['categories'] = $this->categories_model->list_all();
		// Load the selected categories children.
		$data['children'] = $this->categories_model->get_children($data['category']['id']);		
		
		// Calculate what text to display.
		if($data['category']['count_items'] > 0 && $data['children']['count'] > 0){
			$data['list'] = "categories and items";
		} else {
			if($data['children']['count'] > 0)				$data['list'] = 'categories';
			if($data['category']['count_items'] > 0)		$data['list'] = 'items';
		}
		
		// If there is nothing to be done for this category, redirect.
		if(!isset($data['list']))
			redirect('admin/edit/items');

		if($this->form_validation->run('admin_category_orphans') == TRUE) {
			// Update records accordingly.
			if($data['list'] == 'items') {
				$this->categories_model->update_items_category($data['category']['id'], $this->input->post('category_id'));
			} else if($data['list'] == 'categories') {
				$this->categories_model->update_parent_category($data['category']['id'], $this->input->post('category_id'));
			} else if($data['list'] == 'categories and items') {
				$this->categories_model->update_items_category($data['category']['id'], $this->input->post('category_id'));
				$this->categories_model->update_parent_category($data['category']['id'], $this->input->post('category_id'));
			}
			// Finally, delete the category and redirect.
			if($this->categories_model->delete($data['category']['id']) == TRUE)
				redirect('admin/edit/items');
		}
		
		$data['page'] = 'admin/category_orphans';
		$data['title'] = 'Fix Orphans';
		$this->load->library('Layout', $data);
	}
	
	/**
	 * Manage User Invite Tokens.
	 * URI: /admin/tokens
	 * 
	 * @see 	Models/Users_Model
	 * @see		Libraries/Form_Validation
	 * @see		Libraries/General
	 * @return	void
	 */	
	public function user_tokens() {
		$this->load->model('users_model');
		$this->load->library('form_validation');
		
		// If the Create Token form has been submitted:
		if($this->input->post('create_token') == "Create"){
			if($this->form_validation->run('admin_create_token') == TRUE){
				
				// Get the registration fee for the chosen user role, and
				// if it does not exist then set the default to 0.0000000 ($config_val)
				// If the admin has chosen the default fee, use that $config_val,
				// otherwise use the number they've given.
				$var = 'entry_payment_'.strtolower($this->general->role_from_id($this->input->post('user_role')));
				$config_val = (isset($this->bw_config->$var)) ? $this->bw_config->$var : 0.00000000 ;
				$entry_payment = ($this->input->post('entry_payment') == 'default') ? $config_val : $this->input->post('entry_payment') ;
				// Generate a unique has as the token.
				$update = array('user_type' => $this->input->post('user_role'),
								'token_content' => $this->general->unique_hash('registration_tokens','token_content', 128),
								'comment' => $this->input->post('token_comment'),
								'entry_payment' => $entry_payment );
								
				$data['returnMessage'] = 'Unable to create your token at this time.';
				if($this->users_model->add_registration_token($update) == TRUE){
					// If token is successfully added, display error message.
					$data['success'] = TRUE;
					$data['returnMessage'] = 'Your token has been created';
					
				} 
			}
		}
		
		// Load a list of registration tokens.
		$data['tokens'] = $this->users_model->list_registration_tokens();
		$data['page'] = 'admin/user_tokens';
		$data['title'] = 'Registration Tokens';
		$this->load->library('Layout', $data);
	}
	
	/**
	 * Delete a User Token
	 * URI: /admin/tokens/delete/$token
	 * 
	 * @see 	Models/Users_Model
	 * @param	string
	 * @return	void
	 */	
	public function delete_token($token) {
		$this->load->library('form_validation');
		$this->load->model('users_model');
		
		// Abort if the token does not exist.
		$token = $this->users_model->check_registration_token($token);
		if($token == FALSE)
			redirect('admin/tokens');
			
		$data['returnMessage'] = 'Unable to delete the specified token, please try again later.';
		if($this->users_model->delete_registration_token($token['id']) == TRUE){
			// Display a message if the token is successfully deleted.
			$data['success'] = TRUE;
			$data['returnMessage'] = 'The selected token has been deleted.';
		}
			
		// Load a list of registration tokens.
		$data['tokens'] = $this->users_model->list_registration_tokens();
		$data['page'] = 'admin/user_tokens';
		$data['title'] = 'Registration Tokens';
		$this->load->library('Layout', $data);
			
		return FALSE;
	}
	
	/**
	 * Delete an Item, sending the vendor an explanation.
	 * URI: /admin/delete_item/$hash
	 * 
	 * @param	string
	 * @see 	Models/Messages_Model
	 * @see		Models/Items_Model
	 * @see		Libraries/Form_Validation
	 * @return	void
	 */	
	public function delete_item($hash) {
		$this->load->library('form_validation');
		$this->load->model('items_model');
		$this->load->model('messages_model');
		
		$data['item'] = $this->items_model->get($hash);
		if($data['item'] == FALSE)
			redirect('items');
			
		$data['title'] = 'Delete Item';
		$data['page'] = 'admin/delete_item';			
		
		if($this->form_validation->run('admin_delete_item') == TRUE) {
			if($this->items_model->delete($data['item']['id']) == TRUE) {
				
				$info['from'] = $this->current_user->user_id;
				$details = array('username' => $data['item']['vendor']['user_name'],
								 'subject' => "Listing '{$data['item']['name']}' has been removed");
				$details['message'] = "Your listing has been removed from the marketplace. <br /><br />\n";
				$details['message'] = "Reason for removal:<br />\n".$this->input->post('reason_for_removal');
				$message = $this->bw_messages->prepare_input($info, $details);
				$this->messages_model->send($message);
				
				$data['title'] = 'Deleted Item';
				$data['page'] = 'items/index';
				$data['items'] = $this->items_model->get_list();					
			} else { 
				$data['returnMessage'] = 'Unable to delete that item at this time.';
			}
		}
		$this->load->library('Layout', $data);
	}
	
	/**
	 * Alter a users ban toggle.
	 * URI: /admin/ban_user/$hash
	 * 
	 * @param	string
	 * @see 	Models/Messages_Model
	 * @see		Models/Items_Model
	 * @see		Libraries/Form_Validation
	 * @return	void
	 */	
	public function ban_user($hash) {
		$this->load->library('form_validation');
		$this->load->model('accounts_model');
		
		$data['user'] = $this->accounts_model->get(array('user_hash' => $hash));
		if($data['user'] == FALSE)
			redirect('admin/edit/users');
			
		$data['title'] = 'Ban User';
		$data['page'] = 'admin/ban_user';			
		
		if($this->form_validation->run('admin_ban_user') == TRUE) {
			if($this->input->post('ban_user') !== $data['user']['banned']) {
				if( $this->accounts_model->toggle_ban($data['user']['id'], $this->input->post('ban_user') ) ) {
					$data['returnMessage'] = $data['user']['user_name']." has now been ";
					$data['returnMessage'].= ($this->input->post('ban_user') == '1') ? 'banned.' : 'unbanned.'; 
					$data['page'] = 'accounts/view';
					$data['title'] = $data['user']['user_name'];
					
					$data['logged_in'] = $this->current_user->logged_in();
					$data['user_role'] = $this->current_user->user_role;
					$data['user'] = $this->accounts_model->get(array('user_hash' => $hash));					
				} else {
					$data['returnMessage'] = 'Unable to alter this user right now, please try again later.';
				}
			} else {
				redirect('user/'.$data['user']['user_hash']);
			}
		}
				
		$this->load->library('Layout', $data);
	}	

	public function dispute($order_id = NULL){
		$this->load->library('form_validation');
		$this->load->model('escrow_model');
		
		if($order_id == NULL){
			$data['page'] = 'admin/disputes_list';
			$data['title'] = 'Active Disputes';
			$data['disputes'] = $this->escrow_model->disputes_list();
			
		} else {
			$data['dispute'] = $this->escrow_model->get_dispute($order_id);
			if($data['dispute'] == FALSE)
				redirect('admin/disputes');
				
			$data['page'] = 'admin/dispute';
			$data['title'] = "Disputed Order #{$order_id}";
			$data['current_order'] = $this->order_model->get($order_id);
			$data['disputing_user'] = ($data['dispute']['disputing_user_id'] == $data['current_order']['buyer']['id']) ? $data['current_order']['buyer'] : $data['current_order']['vendor'];
			$data['other_user'] = ($data['dispute']['other_user_id'] == $data['current_order']['buyer']['id']) ? $data['current_order']['buyer'] : $data['current_order']['vendor'];
			 
			if($this->input->post('update_message') == 'Update') {
				if($this->form_validation->run('admin_dispute_message') == TRUE) {
					$update['admin_message'] = $this->input->post('admin_message');
					if($this->escrow_model->update_dispute($order_id, $update) == TRUE)
						$data['dispute'] = $this->escrow_model->get_dispute($order_id);					
				}
			}
		}
		$this->load->library('Layout', $data);
	}

	/**
	 * Topup Addresses
	 * 
	 * This function gathers a list of accounts from the bitcoin daemon
	 * and finds topup addresses for them. These addresses will not change
	 * until they finally receive funds.
	 */
	public function topup_addresses(){
		$this->load->library('bw_bitcoin');
		
		// Load accounts, and get the topup addresses.
		$accounts = $this->bw_bitcoin->listaccounts();
		$data['accounts'] = FALSE;
		if($accounts !== FALSE){
			foreach($accounts as $account => $balance) {
				$data['accounts'][$account] = array('address' => $this->bw_bitcoin->getaccountaddress($account),
												'balance' => $balance);
			}
		}
		
		$data['page'] = 'admin/topup_addresses';
		$data['title'] = 'Topup Addresses';
		$this->load->library('Layout', $data);
	}

	/**
	 * Generate Nav
	 * 
	 * Generates the navigation bar for the admin panel. 
	 * 
	 * @return 	string
	 */
	public function generate_nav() { 
		$nav = '';
		if( $this->bw_config->balance_backup_method == 'Electrum' && $this->bw_config->electrum_mpk == '')
			$nav.= '<div class="alert">You have not configured an electrum master public key. Please do so now '.anchor('admin/edit/bitcoin','here').'.</div>';
		
		$links = '';
		foreach($this->nav as $entry) { 
			$links .= '<li';
			if(uri_string() == 'admin'.$entry['panel'] || uri_string() == 'admin/edit'.$entry['panel']) {
						$links .= ' class="active" ';
				$self = $entry;
				$heading = $entry['heading'];
				$panel_url = $self['panel'];
			}
			$links .= '>'.anchor('admin'.$entry['panel'], $entry['title']).'</li>';
		}

		$nav.= '<div class="tabbable">
			<label class="span3"><h2>'.$self['heading'].'</h2></label>
			<label class="span1">';
		if($panel_url !== '/logs') $nav.= anchor('admin/edit'.$panel_url, 'Edit', 'class="btn"');
		$nav.= '</label>
			<label class="span7">
			  <ul class="nav nav-tabs">
			  '.$links.'
			  </ul>
			</label>
		  </div>';

		return $nav;
	}
	
	// Callback functions for form validation
	
	/**
	 * Check the captcha length is not too long.
	 *
	 * @param	int	$param
	 * @return	boolean
	 */
	public function check_captcha_length($param) {
		return ($param > 0 && $param < 13) ? TRUE : FALSE;
	}

	/**
	 * Check Bool
	 * 
	 * Check the supplied parameter is for a boolean..
	 *
	 * @param	int	$param
	 * @return	boolean
	 */
	public function check_bool($param) {
		return ($this->general->matches_any($param, array('0','1')) == TRUE) ? TRUE : FALSE;
	}

	/**
	 * Check Category Exists.
	 * 
	 * Check the required category exists (for parent_id)
	 *
	 * @param	int	$param
	 * @return	boolean
	 */
	public function check_category_exists($param) {
		if($param == NULL)
			return FALSE;
			
		if($param == "0")	// Allows the category to be a root category.
			return TRUE;
			echo "Param !== '0'<br />";
			
		return ($this->categories_model->get(array('id' => $param)) !== FALSE) ? TRUE : FALSE;
	}
	
	/**
	 * Check Can Delete Category
	 * 
	 * Check the category can be deleted (doesn't allow '0' for root category).
	 *
	 * @param	int	$param	
	 * @return	boolean
	 */
	public function check_can_delete_category($param) {
		if($param == NULL)
			return FALSE;
			
		return ($this->categories_model->get(array('id' => $param)) !== FALSE) ? TRUE : FALSE;
	}
	
	/**
	 * Check Registration Token Fee
	 * 
	 * This function checks if the supplied 'amount' for the registration
	 * is 'default', which will trigger the user to be charged the config value,
	 * or else if the admin has specified a figure.
	 * 
	 * @param	string	$param
	 * @return	boolean
	 */
	public function check_registration_token_fee($param) {
		return ($param == 'default' || is_numeric($param) && $param >= 0) ? TRUE : FALSE;
	}
	
	/**
	 * Check Bitcoin Account Exists
	 * 
	 * Check the bitcoin account already exists in the server wallet.
	 *
	 * @param	string	$param
	 * @return	boolean
	 */
	public function check_bitcoin_account_exists($param) {
		
		if($param == '')
			return FALSE;

		$accounts = $this->bw_bitcoin->listaccounts(0);
		return (isset($accounts[$param])) ? TRUE : FALSE;
	}
	
	/**
	 * Check Admin Roles
	 * 
	 * Check the submitted parameter is either 1, 2, or 3.
	 *
	 * @param	int	$param
	 * @return	boolean
	 */
	public function check_admin_roles($param){
		return ($this->general->matches_any($param, array('1','2','3')) == TRUE) ? TRUE : FALSE;
	}
	
	/**
	 * Check Autorun Interval 
	 * 
	 * Check the submitted parameter is a valid interval for an autorun job.
	 *
	 * @param	int	$param
	 * @return	boolean
	 */
	public function check_autorun_interval($param){
		return (is_numeric($param) && $param >= '0') ? TRUE : FALSE;
	}
	
	/**
	 * Check is positive
	 * 
	 * Check the supplied parameter is a positive number.
	 *
	 * @param	int	$param
	 * @return	boolean
	 */
	public function check_is_positive($param) {
		return (is_numeric($param) && $param >= 0) ? TRUE : FALSE;		
	}
	
	/**
	 * Check Price Index
	 * 
	 * Check the supplied parameter is a valid price index config value.
	 *
	 * @param	string	$param
	 * @return	boolean
	 */
	public function check_price_index($param){
		$config = $this->bw_config->price_index_config;
		return (is_array($config[$param]) || $param == 'Disabled') ? TRUE : FALSE;
	}
	
	/**
	 * Check session timeout.
	 * 
	 * Session timeouts can be a minimum of 5 minutes inactivity before
	 * logging a user out, to an admin defined interval. The default value
	 * is set in the Config library, and is 30 minutes. Otherwise it's read
	 * from this value set in the table.
	 * 
	 * @param	string	$param
	 * return	boolean
	 */
	public function check_session_timeout($param) {
		 return (is_numeric($param) && $param >= 5) ? TRUE : FALSE;
	}
	
	/**
	 * Check Bitcoin Balance Method
	 * 
	 * Checks if the bitcoin balance method is an allowed value:
	 * '' for disabled, 'ecdsa' to generate private keys, and 'electrum'
	 * for the deterministic addresses.
	 * 
	 * @param	string	$param
	 * return	boolean
	 */
	public function check_bitcoin_balance_method($param) {
		return ($this->general->matches_any($param, array('Disabled','ECDSA','Electrum')) == TRUE) ? TRUE : FALSE;
	}
};

/* End of file: Admin.php */
