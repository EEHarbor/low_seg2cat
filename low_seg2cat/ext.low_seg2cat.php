<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

// include config file
require PATH_THIRD.'low_seg2cat/config.php';

/**
 * Low Seg2Cat Extension class
 *
 * @package        low_seg2cat
 * @author         Lodewijk Schutte <hi@gotolow.com>
 * @link           http://gotolow.com/addons/low-seg2cat
 * @license        http://creativecommons.org/licenses/by-sa/3.0/
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
	 * URI instance
	 *
	 * @access      private
	 * @var         object
	 */
	private $uri;

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
	 * @access      private
	 * @var         bool
	 */
	private $format = TRUE;

	/**
	 * Hooks used
	 *
	 * @access      private
	 * @var         array
	 */
	private $hooks = array(
		'sessions_end',
		'template_fetch_template'
	);

	/**
	 * Default settings
	 *
	 * @access      private
	 * @var         array
	 */
	private $default_settings = array(
		'category_groups'  => array(),
		'uri_pattern'      => '',
		'set_all_segments' => 'n',
		'ignore_pagination'=> 'n',
		'parse_file_paths' => 'n'
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
	 * Constructor
	 *
	 * @access      public
	 * @param       mixed     Array with settings or FALSE
	 * @return      null
	 */
	public function __construct($settings = array())
	{
		// Get site id
		$this->site_id = ee()->config->item('site_id');

		// Set Class name
		$this->class_name = ucfirst(get_class($this));

		// Set settings
		$this->settings = $this->_get_site_settings($settings);

		// Define the package path
		ee()->load->add_package_path(PATH_THIRD.LOW_SEG2CAT_PACKAGE);
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

		ee()->load->helper('form');

		// --------------------------------------
		// Get current settings for this site
		// --------------------------------------

		$data['current'] = $this->_get_site_settings($current);

		// --------------------------------------
		// Allow for 'all groups'
		// --------------------------------------

		if (empty($data['current']['category_groups']))
		{
			$data['current']['category_groups'] = array('0');
		}

		// --------------------------------------
		// Add this extension's name to display data
		// --------------------------------------

		$data['name'] = ucfirst(LOW_SEG2CAT_PACKAGE);

		// --------------------------------------
		// Category groups
		// --------------------------------------

		$data['category_groups'] = array('0' => lang('all_groups'));

		// --------------------------------------
		// Get category groups
		// --------------------------------------

		$query = ee()->db->select('group_id, group_name')
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

		ee()->cp->set_breadcrumb('#', LOW_SEG2CAT_NAME);

		// --------------------------------------
		// Load view
		// --------------------------------------

		return ee()->load->view('ext_settings', $data, TRUE);
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
			if (($post_val = ee()->input->post($key)) !== FALSE)
			{
				$val = $post_val;
			}

			if (is_array($val))
			{
				$val = array_filter($val);
			}

			$settings[$this->site_id][$key] = $val;
		}

		// --------------------------------------
		// Save serialized settings
		// --------------------------------------

		ee()->db->where('class', $this->class_name);
		ee()->db->update('extensions', array('settings' => serialize($settings)));
	}

	// --------------------------------------------------------------------

	/**
	 * EE 2.4.0+ uses template_fetch_template to add vars
	 */
	public function template_fetch_template($row)
	{
		// Get the latest version of $row
		if (ee()->extensions->last_call !== FALSE)
		{
			$row = ee()->extensions->last_call;
		}

		// Remember if vars were added
		static $added;

		// If not yet added...
		if ( ! $added)
		{
			// ...add the vars...
			$this->_add_vars();

			// ...only once
			$added = TRUE;
		}

		// Play nice, return it
		return $row;
	}

	/**
	 * EE 2.4.0- uses sessions_end to add vars
	 */
	public function sessions_end($SESS)
	{
		//  Check app version to see what to do
		if (version_compare(APP_VER, '2.4.0', '<') && REQ == 'PAGE')
		{
			$this->_add_vars();
		}

		// Return it again
		return $SESS;
	}

	/**
	 * Search URI segments for categories and add those to global variables
	 *
	 * @access      private
	 * @return      null
	 */
	private function _add_vars()
	{
		// --------------------------------------
		// Only continue if request is a page
		// and we have segments to check
		// --------------------------------------

		if (empty(ee()->uri->segments) && $this->settings['set_all_segments'] == 'n') return;

		// --------------------------------------
		// Initiate uri instance
		// --------------------------------------

		$this->uri = new EE_URI;
		$this->uri->_fetch_uri_string();

		if ($this->settings['ignore_pagination'] == 'y')
		{
			// Get rid of possible pagination segment at the end
			$this->uri->uri_string = preg_replace('#/[PC]\d+$#', '', $this->uri->uri_string);
		}

		$this->uri->_explode_segments();
		$this->uri->_reindex_segments();

		// --------------------------------------
		// Suggestion by Leevi Graham:
		// check for pattern before continuing
		// --------------------------------------

		if ( ! empty($this->settings['uri_pattern']) && ! preg_match($this->settings['uri_pattern'], $this->uri->uri_string)) return;

		// --------------------------------------
		// Initiate some vars
		// $data is used to add to global vars
		// $cats is used to keep track of all category ids found
		// --------------------------------------

		$data = $cats = array();

		// Also initiate this single var to an empty string
		$data['segment_category_ids'] = '';
		$data['segment_category_ids_piped'] = '';

		// --------------------------------------
		// Number of segments to register - 9 is hardcoded maximum
		// --------------------------------------

		$num_segs = ($this->settings['set_all_segments'] == 'y') ? 9 : $this->uri->total_segments();

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

		$segment_array = array_map('strtolower', $this->uri->segment_array());

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

			ee()->db->select('cat_url_title, '. implode(', ', array_keys($this->fields)))
			             ->from('categories')
			             ->where('site_id', $this->site_id)
			             ->where_in('cat_url_title', $segment_array);

			// --------------------------------------
			// Filter by category groups set in settings
			// --------------------------------------

			if (isset($this->settings['category_groups']))
			{
				if ($groups = array_filter($this->settings['category_groups']))
				{
					ee()->db->where_in('group_id', $groups);
				}
			}

			// --------------------------------------
			// Execute query and get results
			// --------------------------------------

			$query = ee()->db->get();

			// --------------------------------------
			// If we have matching categories, continue...
			// --------------------------------------

			if ($query->num_rows())
			{
				// --------------------------------------
				// Associate the results
				// --------------------------------------

				$rows = $this->_associate_results($query->result_array(), 'cat_url_title');

				// --------------------------------------
				// Load typography if private var is set
				// --------------------------------------

				if ($this->format || $this->settings['parse_file_paths'] == 'y')
				{
					ee()->load->library('typography');
				}

				// --------------------------------------
				// loop through segments
				// --------------------------------------

				foreach ($segment_array as $n => $seg)
				{
					// Skip non-matching segments
					if ( ! isset($rows[$seg])) continue;

					// Get the category row
					$row = $rows[$seg];

					// Overwrite values in data array
					foreach ($this->fields AS $name => $field)
					{
						// Format category name if private var is set
						if ($name == 'cat_name' && $this->format)
						{
							$row[$name] = ee()->typography->format_characters($row[$name]);
						}

						// Parse file paths
						if ($name == 'cat_image' && $this->settings['parse_file_paths'] == 'y')
						{
							$row[$name] = ee()->typography->parse_file_paths($row[$name]);
						}

						// Set value in for segment_x_yyy
						$data["segment_{$n}_{$field}"] = $row[$name];
					}

					// Add found id to cats array
					$cats[] = $row['cat_id'];
				}

				// --------------------------------------
				// Set last_segment_category_x vars
				// --------------------------------------

				$last = $this->uri->total_segments();

				foreach ($this->fields AS $name => $field)
				{
					$data['last_segment_'.$field] = $data['segment_'.$last.'_'.$field];
				}

				// --------------------------------------
				// Create inclusive stack of all category ids present in segments
				// --------------------------------------

				$data['segment_category_ids'] = implode('&',$cats);
				$data['segment_category_ids_piped'] = implode('|',$cats);
			}
		}

		// --------------------------------------
		// Finally, add data to global vars
		// Swapping $data and existing global vars makes a difference in EE2.4+
		// --------------------------------------

		ee()->config->_global_vars = array_merge(ee()->config->_global_vars, $data);
		//ee()->config->_global_vars = array_merge($data, ee()->config->_global_vars);
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
		foreach ($this->hooks AS $hook)
		{
			$this->_add_hook($hook);
		}
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

		// Add template_fetch_template hook
		if (version_compare($current, '2.7.0', '<'))
		{
			$this->_add_hook('template_fetch_template');
			$this->save_settings();
		}

		// Add version to data array
		$data['version'] = LOW_SEG2CAT_VERSION;

		// Update records using data array
		ee()->db->where('class', $this->class_name);
		ee()->db->update('extensions', $data);
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
		ee()->db->where('class', $this->class_name);
		ee()->db->delete('extensions');
	}

	// --------------------------------------------------------------------
	// PRIVATE METHODS
	// --------------------------------------------------------------------

	/**
	 * Associate results
	 *
	 * Given a DB result set, this will return an (associative) array
	 * based on the keys given
	 *
	 * @param      array
	 * @param      string    key of array to use as key
	 * @param      bool      sort by key or not
	 * @return     array
	 */
	private function _associate_results($resultset, $key, $sort = FALSE)
	{
		$array = array();

		foreach ($resultset AS $row)
		{
			if (array_key_exists($key, $row) && ! array_key_exists($row[$key], $array))
			{
				$array[$row[$key]] = $row;
			}
		}

		if ($sort === TRUE)
		{
			ksort($array);
		}

		return $array;
	}

	// --------------------------------------------------------------------

	/**
	 * Get current settings from DB
	 *
	 * @access      private
	 * @return      mixed
	 */
	private function _get_current_settings()
	{
		$query = ee()->db->select('settings')
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
		$current = (array) (isset($current[$this->site_id]) ? $current[$this->site_id] : $current);

		return array_merge($this->default_settings, $current);
	}

	// --------------------------------------------------------------------

	/**
	 * Add hook to table
	 *
	 * @access	private
	 * @param	string
	 * @return	void
	 */
	private function _add_hook($hook)
	{
		ee()->db->insert('extensions', array(
			'class'    => $this->class_name,
			'method'   => $hook,
			'hook'     => $hook,
			'settings' => serialize($this->settings),
			'priority' => 5,
			'version'  => $this->version,
			'enabled'  => 'y'
		));
	}

}
// END CLASS

/* End of file ext.low_seg2cat.php */