<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

// include config file
require PATH_THIRD.'low_seg2cat/config'.EXT;

/**
* Low Seg2Cat Extension class
*
* @package			low-seg2cat-ee2_addon
* @author			Lodewijk Schutte ~ Low <low@loweblog.com>
* @link				http://loweblog.com/software/low-seg2cat/
* @license			http://creativecommons.org/licenses/by-sa/3.0/
*/
class Low_seg2cat_ext
{
	/**
	* Extension settings
	*
	* @var	array
	*/
	var $settings = array();

	/**
	* Extension name
	*
	* @var	string
	*/
	var $name = LOW_SEG2CAT_NAME;

	/**
	* Extension version
	*
	* @var	string
	*/
	var $version = LOW_SEG2CAT_VERSION;

	/**
	* Extension description
	*
	* @var	string
	*/
	var $description = 'Registers Category information according to URI Segments';

	/**
	* Do settings exist?
	*
	* @var	bool
	*/
	var $settings_exist = TRUE;

	/**
	* Documentation link
	*
	* @var	string
	*/
	var $docs_url = LOW_SEG2CAT_DOCS;

	/**
	* Format category name?
	*
	* @var	bool
	*/
	var $format = TRUE;

	/**
	* Default settings
	*
	* @var	array
	*/
	var $default_settings = array(
		'category_groups'  => array(),
		'uri_pattern'      => '',
		'set_all_segments' => 'n'
	);

	/**
	* Category fields to set
	*
	* @var	array
	*/
	var $fields = array(
		'cat_id'          => 'category_id',
		'parent_id'       => 'category_parent_id',
		'group_id'        => 'category_group_id',
		'cat_name'        => 'category_name',
		'cat_description' => 'category_description',
		'cat_image'       => 'category_image'
	);

	// --------------------------------------------------------------------

	/**
	* PHP4 Constructor
	*
	* @see	__construct()
	*/
	function Low_seg2cat_ext($settings = FALSE)
	{
		$this->__construct($settings);
	}

	// --------------------------------------------------------------------

	/**
	* PHP 5 Constructor
	*
	* @param	$settings	mixed	Array with settings or FALSE
	* @return	void
	*/
	function __construct($settings = FALSE)
	{
		// Get global instance
		$this->EE =& get_instance();

		$this->settings = $settings;
	}

	// --------------------------------------------------------------------

	/**
	* Settings
	*
	* @return	array
	*/
	function settings()
	{
		$settings = $groups = array();

		// Get category groups
		$this->EE->db->select('group_id, group_name');
		$this->EE->db->from('category_groups');
		$this->EE->db->where('site_id', $this->EE->config->item('site_id'));
		$this->EE->db->order_by('group_name', 'asc');
		$query = $this->EE->db->get();

		foreach ($query->result() AS $row)
		{
			$groups[$row->group_id] = $row->group_name;
		}

		$settings['category_groups'] = array('ms', $groups, $this->default_settings['category_groups']);
		$settings['uri_pattern'] = $this->default_settings['uri_pattern'];
		$settings['set_all_segments'] = array('r', array('y' => 'yes', 'n' => 'no'), $this->default_settings['set_all_segments']);

		return $settings;
	}

	// --------------------------------------------------------------------

	/**
	* Search URI segments for categories and add those to global variables
	* Executed at the sessions_end extension hook
	*
	* @return	null
	*/
	function sessions_end()
	{
		// Only continue if request is a page and we have segments to check
		if (REQ != 'PAGE' || (empty($this->EE->uri->segments) && $this->settings['set_all_segments'] == 'n')) return;

		// Suggestion by Leevi Graham: check for pattern before continuing
		if ( ! empty($this->settings['uri_pattern']) && ! preg_match($this->settings['uri_pattern'], $this->EE->uri->uri_string)) return;

		// initiate some vars
		$site = $this->EE->config->item('site_id');
		$data = $cats = array();
		$data['segment_category_ids'] = '';

		// Number of segments to register
		$num_segs = ($this->settings['set_all_segments'] == 'y') ? 9 : $this->EE->uri->total_segments();

		// loop through segments and set data array thus: segment_1_category_id etc
		for ($nr = 1; $nr <= $num_segs; $nr++)
		{
			foreach ($this->fields AS $field)
			{
				$data["segment_{$nr}_{$field}"] = '';
			}
		}

		// Lowercase segment array
		$segment_array = array_map('strtolower', $this->EE->uri->segment_array());

		// Execute the rest only if there are segments to check
		if ($segment_array)
		{
			// Compose query, get results
			$this->EE->db->select('LOWER(cat_url_title) AS cat_url_title, '. implode(', ', array_keys($this->fields)));
			$this->EE->db->from('exp_categories');
			$this->EE->db->where('site_id', $site);
			$this->EE->db->where_in('LOWER(cat_url_title)', $segment_array);
			if (isset($this->settings['category_groups']) && ! empty($this->settings['category_groups']))
			{
				$this->EE->db->where_in('group_id', $this->settings['category_groups']);
			}
			$query = $this->EE->db->get();

			// if we have matching categories, continue...
			if ($query->num_rows())
			{
				// Load typography
				$this->EE->load->library('typography');

				// flip segment array to get 'segment_1' => '1'
				$ids = array_flip($segment_array);

				// loop through categories
				foreach ($query->result_array() as $row)
				{
					// overwrite values in data array
					foreach ($this->fields AS $name => $field)
					{
						if ($name == 'cat_name' && $this->format)
						{
							$row[$name] = $this->EE->typography->format_characters($row[$name]);
						}
						$data['segment_'.$ids[$row['cat_url_title']].'_'.$field] = $row[$name];
					}
					$cats[] = $row['cat_id'];
				}

				// create inclusive stack of all category ids present in segments
				$data['segment_category_ids'] = implode('&',$cats);
			}
		}

		// Add data to global vars
		$this->EE->config->_global_vars = array_merge($data, $this->EE->config->_global_vars);
	}

	// --------------------------------------------------------------------

	/**
	* Activate extension
	*
	* @return	null
	*/
	function activate_extension()
	{
		// data to insert
		$data = array(
			'class'		=> __CLASS__,
			'method'	=> 'sessions_end',
			'hook'		=> 'sessions_end',
			'priority'	=> 1,
			'version'	=> $this->version,
			'enabled'	=> 'y',
			'settings'	=> serialize($this->default_settings)
		);

		// insert in database
		$this->EE->db->insert('exp_extensions', $data);
	}

	// --------------------------------------------------------------------

	/**
	* Update extension
	*
	* @param	string	$current
	* @return	null
	*/
	function update_extension($current = '')
	{
		if ($current == '' OR $current == $this->version)
		{
			return FALSE;
		}

		// init data array
		$data = array();

		// Add version to data array
		$data['version'] = $this->version;

		// Update records using data array
		$this->EE->db->where('class', __CLASS__);
		$this->EE->db->update('exp_extensions', $data);
	}

	// --------------------------------------------------------------------

	/**
	* Disable extension
	*
	* @return	null
	*/
	function disable_extension()
	{
		// Delete records
		$this->EE->db->where('class', __CLASS__);
		$this->EE->db->delete('exp_extensions');
	}

	// --------------------------------------------------------------------

}
// END CLASS

/* End of file ext.low_seg2cat.php */