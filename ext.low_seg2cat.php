<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

// include config file
require PATH_THIRD.'low_seg2cat/config.php';

/**
 * Low Seg2Cat Extension class
 *
 * @package         low-seg2cat-ee2_addon
 * @author          Lodewijk Schutte ~ Low <hi@gotolow.com>
 * @link            http://gotolow.com/addons/low-seg2cat
 * @license         http://creativecommons.org/licenses/by-sa/3.0/
 */
class Low_seg2cat_ext {

	// --------------------------------------------------------------------
	// PROPERTIES
	// --------------------------------------------------------------------

	/**
	 * Extension settings
	 *
	 * @access      public
	 * @var         array
	 */
	public $settings = array();

	/**
	 * Extension name
	 *
	 * @access      public
	 * @var         string
	 */
	public $name = LOW_SEG2CAT_NAME;

	/**
	 * Extension version
	 *
	 * @access      public
	 * @var         string
	 */
	public $version = LOW_SEG2CAT_VERSION;

	/**
	 * Extension description
	 *
	 * @access      public
	 * @var         string
	 */
	public $description = 'Registers Category information according to URI Segments';

	/**
	 * Do settings exist?
	 *
	 * @access      public
	 * @var         bool
	 */
	public $settings_exist = TRUE;

	/**
	 * Documentation link
	 *
	 * @access      public
	 * @var         string
	 */
	public $docs_url = LOW_SEG2CAT_DOCS;

	// --------------------------------------------------------------------

	/**
	 * EE Instance
	 *
	 * @access      private
	 * @var         object
	 */
	private $EE;

	/**
	 * Current class name
	 *
	 * @access      private
	 * @var         string
	 */
	private $class_name;

	/**
	 * Current site id
	 *
	 * @access      private
	 * @var         int
	 */
	private $site_id;

	/**
	 * Format category name?
	 *
	 * @access      public
	 * @var         bool
	 */
	private $format = TRUE;

	/**
	 * Default settings
	 *
	 * @access      public
	 * @var         array
	 */
	private $default_settings = array(
		'category_groups'  => array(),
		'uri_pattern'      => '',
		'set_all_segments' => 'n'
	);

	/**
	 * Category fields to set
	 *
	 * @access      public
	 * @var         array
	 */
	private $fields = array(
		'cat_id'          => 'category_id',
		'parent_id'       => 'category_parent_id',
		'group_id'        => 'category_group_id',
		'cat_name'        => 'category_name',
		'cat_description' => 'category_description',
		'cat_image'       => 'category_image'
	);

	// --------------------------------------------------------------------
	// METHODS
	// --------------------------------------------------------------------

	/**
	 * Legacy Constructor
	 *
	 * @see         __construct()
	 */
	public function Low_seg2cat_ext($settings = array())
	{
		$this->__construct($settings);
	}

	// --------------------------------------------------------------------

	/**
	 * PHP 5 Constructor
	 *
	 * @access      public
	 * @param       mixed     Array with settings or FALSE
	 * @return      null
	 */
	public function __construct($settings = array())
	{
		// Get global instance
		$this->EE =& get_instance();

		// Get site id
		$this->site_id = $this->EE->config->item('site_id');

		// Set Class name
		$this->class_name = ucfirst(get_class($this));

		// Set settings
		$this->settings = $this->_get_site_settings($settings);

		// Define the package path
		$this->EE->load->add_package_path(PATH_THIRD.'low_seg2cat');
	}

	// --------------------------------------------------------------------

	/**
	 * Settings form
	 *
	 * @access      public
	 * @param       array     Current settings
	 * @return      string
	 */
	function settings_form($current)
	{
		// --------------------------------------
		// Load helper
		// --------------------------------------

		$this->EE->load->helper('form');

		// --------------------------------------
		// Get current settings for this site
		// --------------------------------------

		$data['current'] = $this->_get_site_settings($current);

		// --------------------------------------
		// Add this extension's name to display data
		// --------------------------------------

		$data['name'] = LOW_SEG2CAT_CLASS_NAME;

		// --------------------------------------
		// Category groups
		// --------------------------------------

		$data['category_groups'] = array();

		// --------------------------------------
		// Get category groups
		// --------------------------------------

		$query = $this->EE->db->select('group_id, group_name')
		       ->from('category_groups')
		       ->where('site_id', $this->site_id)
		       ->order_by('group_name', 'asc')
		       ->get();

		foreach ($query->result() AS $row)
		{
			$data['category_groups'][$row->group_id] = $row->group_name;
		}

		// --------------------------------------
		// Set breadcrumb
		// --------------------------------------

		$this->EE->cp->set_breadcrumb('#', LOW_SEG2CAT_NAME);

		// --------------------------------------
		// Load view
		// --------------------------------------

		return $this->EE->load->view('ext_settings', $data, TRUE);
	}

	// --------------------------------------------------------------------

	/**
	 * Save extension settings
	 *
	 * @return      void
	 */
	public function save_settings()
	{
		// --------------------------------------
		// Get current settings from DB
		// --------------------------------------

		$settings = $this->_get_current_settings();

		if ( ! is_array($settings))
		{
			$settings = array($this->site_id => $this->default_settings);
		}

		// --------------------------------------
		// Loop through default settings, check
		// for POST values, fallback to default
		// --------------------------------------

		foreach ($this->default_settings AS $key => $val)
		{
			if (($settings[$this->site_id][$key] = $this->EE->input->post($key)) === FALSE)
			{
				if (is_array($val))
				{
					$val = array_filter($val);
				}

				$settings[$this->site_id][$key] = $val;
			}
		}

		// --------------------------------------
		// Save serialized settings
		// --------------------------------------

		$this->EE->db->update('extensions', array('settings' => serialize($settings)), "class = '".$this->class_name."'");
	}

	// --------------------------------------------------------------------

	/**
	 * Search URI segments for categories and add those to global variables
	 * Executed at the sessions_end extension hook
	 *
	 * @access      public
	 * @return      null
	 */
	public function sessions_end()
	{
		// --------------------------------------
		// Only continue if request is a page
		// and we have segments to check
		// --------------------------------------

		if (REQ != 'PAGE' || (empty($this->EE->uri->segments) && $this->settings['set_all_segments'] == 'n')) return;

		// --------------------------------------
		// Suggestion by Leevi Graham:
		// check for pattern before continuing
		// --------------------------------------

		if ( ! empty($this->settings['uri_pattern']) && ! preg_match($this->settings['uri_pattern'], $this->EE->uri->uri_string)) return;

		// --------------------------------------
		// Initiate some vars
		// $data is used to add to global vars
		// $cats is used to keep track of all category ids found
		// --------------------------------------

		$data = $cats = array();

		// Also initiate this single var to an empty string
		$data['segment_category_ids'] = '';

		// --------------------------------------
		// Number of segments to register - 9 is hardcoded maximum
		// --------------------------------------

		$num_segs = ($this->settings['set_all_segments'] == 'y') ? 9 : $this->EE->uri->total_segments();

		// --------------------------------------
		// loop through segments and set data array thus: segment_1_category_id etc
		// --------------------------------------

		for ($nr = 1; $nr <= $num_segs; $nr++)
		{
			foreach ($this->fields AS $field)
			{
				$data["segment_{$nr}_{$field}"] = '';
			}
		}

		// Initiate last segment vars
		foreach ($this->fields AS $field)
		{
			$data["last_segment_{$field}"] = '';
		}

		// --------------------------------------
		// Force lowercase segment array
		// --------------------------------------

		$segment_array = array_map('strtolower', $this->EE->uri->segment_array());

		// --------------------------------------
		// Execute the rest only if there are segments to check
		// --------------------------------------

		if ($segment_array)
		{
			// --------------------------------------
			// Query database for these segments
			// Use lowercase for case insensitive comparison,
			// for when DB collation is case sensitive
			// --------------------------------------

			$this->EE->db->select('LOWER(cat_url_title) AS cat_url_title, '. implode(', ', array_keys($this->fields)))
			             ->from('categories')
			             ->where('site_id', $this->EE->config->item('site_id'))
			             ->where_in('LOWER(cat_url_title)', $segment_array);

			// --------------------------------------
			// Filter by category groups set in settings
			// --------------------------------------

			if (isset($this->settings['category_groups']) && ! empty($this->settings['category_groups']))
			{
				$this->EE->db->where_in('group_id', $this->settings['category_groups']);
			}

			// --------------------------------------
			// Execute query and get results
			// --------------------------------------

			$query = $this->EE->db->get();

			// --------------------------------------
			// If we have matching categories, continue...
			// --------------------------------------

			if ($query->num_rows())
			{
				// --------------------------------------
				// Load typography if private var is set
				// --------------------------------------

				if ($this->format)
				{
					$this->EE->load->library('typography');
				}

				// --------------------------------------
				// Flip segment array to get 'segment_1' => '1'
				// --------------------------------------

				$ids = array_flip($segment_array);

				// --------------------------------------
				// loop through categories found in DB
				// --------------------------------------

				foreach ($query->result_array() as $row)
				{
					// Overwrite values in data array
					foreach ($this->fields AS $name => $field)
					{
						// Format category name if private var is set
						if ($name == 'cat_name' && $this->format)
						{
							$row[$name] = $this->EE->typography->format_characters($row[$name]);
						}

						// Set value in for segment_x_yyy
						$data['segment_'.$ids[$row['cat_url_title']].'_'.$field] = $row[$name];
					}

					// Add found id to cats array
					$cats[] = $row['cat_id'];
				}

				// --------------------------------------
				// Set last_segment_category_x vars
				// --------------------------------------

				$last = $this->EE->uri->total_segments();

				foreach ($this->fields AS $name => $field)
				{
					$data['last_segment_'.$field] = $data['segment_'.$last.'_'.$field];
				}

				// --------------------------------------
				// Create inclusive stack of all category ids present in segments
				// --------------------------------------

				$data['segment_category_ids'] = implode('&',$cats);
			}
		}

		// --------------------------------------
		// Finally, add data to global vars
		// Swapping $data and existing global vars makes no difference...
		// --------------------------------------

		$this->EE->config->_global_vars = array_merge($data, $this->EE->config->_global_vars);
	}

	// --------------------------------------------------------------------

	/**
	 * Activate extension
	 *
	 * @access      public
	 * @return      null
	 */
	public function activate_extension()
	{
		$this->EE->db->insert('extensions', array(
			'class'    => $this->class_name,
			'method'   => 'sessions_end',
			'hook'     => 'sessions_end',
			'priority' => 1,
			'version'  => LOW_SEG2CAT_VERSION,
			'enabled'  => 'y',
			'settings' => serialize($this->default_settings)
		));
	}

	// --------------------------------------------------------------------

	/**
	 * Update extension
	 *
	 * @access      public
	 * @param       string    Saved extension version
	 * @return      null
	 */
	public function update_extension($current = '')
	{
		if ($current == '' OR $current == LOW_SEG2CAT_VERSION)
		{
			return FALSE;
		}

		// init data array
		$data = array();

		// Update to MSM compatible extension settings
		if (version_compare($current, '2.6.0', '<'))
		{
			if ( ! isset($this->settings[$this->site_id]))
			{
				$data['settings'] = serialize(array($this->site_id => $this->settings));
			}
		}

		// Add version to data array
		$data['version'] = LOW_SEG2CAT_VERSION;

		// Update records using data array
		$this->EE->db->where('class', $this->class_name);
		$this->EE->db->update('exp_extensions', $data);
	}

	// --------------------------------------------------------------------

	/**
	 * Disable extension
	 *
	 * @access      public
	 * @return      null
	 */
	public function disable_extension()
	{
		// Delete records
		$this->EE->db->where('class', $this->class_name);
		$this->EE->db->delete('exp_extensions');
	}

	// --------------------------------------------------------------------
	// PRIVATE METHODS
	// --------------------------------------------------------------------

	/**
	 * Get current settings from DB
	 *
	 * @access      private
	 * @return      mixed
	 */
	private function _get_current_settings()
	{
		$query = $this->EE->db->select('settings')
		       ->from('extensions')
		       ->where('class', $this->class_name)
		       ->limit(1)
		       ->get();

		return @unserialize($query->row('settings'));
	}

	// --------------------------------------------------------------------

	/**
	 * Get settings for this site
	 *
	 * @access      private
	 * @return      mixed
	 */
	private function _get_site_settings($current = array())
	{
		$current = (array) $current;

		return isset($current[$this->site_id]) ? $current[$this->site_id] : array_merge($this->default_settings, $current);
	}

}
// END CLASS

/* End of file ext.low_seg2cat.php */