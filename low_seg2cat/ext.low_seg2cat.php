<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
* Low Seg2Cat Extension class
*
* @package			low-seg2cat-ee2_addon
* @version			2.2
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
	var $name = 'Low Seg2Cat';

	/**
	* Extension version
	*
	* @var	string
	*/
	var $version = '2.2';

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
	var $docs_url = 'http://loweblog.com/software/low-seg2cat/';

	/**
	* NSM Addon Updater link
	*
	* @var	string
	*/
	var $versions_xml = 'http://loweblog.com/software/low-seg2cat/feed/';

	/**
	* Format category name?
	*
	* @var	bool
	*/
	var $format = TRUE;

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
		/** -------------------------------------
		/**  Get global instance
		/** -------------------------------------*/
		
		$this->EE =& get_instance();

		$this->settings = $settings;

	}

	// --------------------------------------------------------------------
	
	/**
	* Settings
	*
	* @return	bool
	*/
	function settings()
	{
		// URI pattern to check against
		return array('uri_pattern' => '');
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
		if (REQ != 'PAGE' || empty($this->EE->uri->segments)) return;

		// Suggestion by Leevi Graham: check for pattern before continuing
		if ( !empty($this->settings['uri_pattern']) && !preg_match($this->settings['uri_pattern'], $this->EE->uri->uri_string) ) return;
	
		// initiate some vars
		$site = $this->EE->config->item('site_id');
		$data = $cats = $segs = array();
		$data['segment_category_ids'] = '';
		
		// loop through segments and set data array thus: segment_1_category_id etc
		foreach ($this->EE->uri->segments AS $nr => $seg)
		{
			$data['segment_'.$nr.'_category_id']			= '';
			$data['segment_'.$nr.'_category_name']			= '';
			$data['segment_'.$nr.'_category_description']	= '';
			$data['segment_'.$nr.'_category_image']			= '';
			$data['segment_'.$nr.'_category_parent_id']		= '';
			$segs[] = $seg;
		}

		// Compose query, get results
		$this->EE->db->select('cat_id, cat_url_title, cat_name, cat_description, cat_image, parent_id');
		$this->EE->db->from('exp_categories');
		$this->EE->db->where('site_id', $site);
		$this->EE->db->where_in('cat_url_title', $segs);
		$query = $this->EE->db->get();

		// if we have matching categories, continue...
		if ($query->num_rows())
		{
			// Load typography
			$this->EE->load->library('typography');

			// flip segment array to get 'segment_1' => '1'
			$ids = array_flip($this->EE->uri->segments);
			
			// loop through categories
			foreach ($query->result_array() as $row)
			{
				// overwrite values in data array
				$data['segment_'.$ids[$row['cat_url_title']].'_category_id']			= $row['cat_id'];
				$data['segment_'.$ids[$row['cat_url_title']].'_category_name']			= $this->format ? $this->EE->typography->format_characters($row['cat_name']) : $row['cat_name'];
				$data['segment_'.$ids[$row['cat_url_title']].'_category_description']	= $row['cat_description'];
				$data['segment_'.$ids[$row['cat_url_title']].'_category_image']			= $row['cat_image'];
				$data['segment_'.$ids[$row['cat_url_title']].'_category_parent_id']		= $row['parent_id'];
				$cats[] = $row['cat_id'];
			}
			
			// create inclusive stack of all category ids present in segments
			$data['segment_category_ids'] = implode('&',$cats);
		}
		
		// Add data to global vars
		$this->EE->config->_global_vars = array_merge($this->EE->config->_global_vars, $data);
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
			'settings'	=> ''
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