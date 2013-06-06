<?php
/*
=====================================================
 This extension was created by Lodewijk Schutte
 - freelance@loweblog.com
 - http://loweblog.com/freelance/
=====================================================
 File: ext.low_seg2cat.php
-----------------------------------------------------
 Purpose: Register category info according to URI segments
=====================================================
*/

if ( ! defined('EXT'))
{
	exit('Invalid file request');
}

class low_seg2cat
{
	var $settings       = array();

	var $name           = 'Low Seg2Cat';
	var $version        = '1.5.0';
	var $description    = 'Registers Category information according to URI Segments';
	var $settings_exist = 'y';
	var $docs_url       = 'http://gotolow.com/addons/low-seg2cat';
	var $format         = TRUE;
	var $fields         = array(
		'cat_id'          => 'category_id',
		'parent_id'       => 'category_parent_id',
		'group_id'        => 'category_group_id',
		'cat_name'        => 'category_name',
		'cat_description' => 'category_description',
		'cat_image'       => 'category_image'
	);
			
	// -------------------------------
	// Constructor
	// -------------------------------
	function low_seg2cat($settings='')
	{
		$this->settings = $settings;
	}
	// END low_seg2cat
	
	
	// --------------------------------
	//  Settings
	// --------------------------------  
	function settings()
	{
		global $DB, $PREFS;
		
		$settings = $groups = array();
		
		$sql_site_id = $DB->escape_str($PREFS->ini('site_id'));
		
		$query = $DB->query("SELECT group_id, group_name FROM exp_category_groups WHERE site_id = '{$sql_site_id}' ORDER BY group_name ASC");
		
		foreach ($query->result AS $row)
		{
			$groups[$row['group_id']] = $row['group_name'];
		}
		
		$settings['category_groups'] = array('ms', $groups, array());
		$settings['uri_pattern'] = '';
		$settings['set_all_segments'] = array('r', array('y' => 'yes', 'n' => 'no'), 'n');

		return $settings;
	}
	// END settings

	
	// --------------------------------
	//  Create stack and variables
	// -------------------------------- 
	function create_stack()
	{
		global $IN, $DB, $PREFS;
		
		// Only continue if we have segments to check
		if (REQ != 'PAGE' || (empty($IN->SEGS) && $this->settings['set_all_segments'] == 'n')) return;

		// Suggestion by Leevi Graham: check for pattern before continuing
		if ( !empty($this->settings['uri_pattern']) && !preg_match($this->settings['uri_pattern'], $IN->URI) ) return;

		// initiate some vars
		$site = $PREFS->ini('site_id');
		$data = $cats = array();
		$data['segment_category_ids'] = '';
		
		// Number of segments to register
		$num_segs = ($this->settings['set_all_segments'] == 'y') ? 9 : count($IN->SEGS);
		
		// loop through segments and set data array thus: segment_1_category_id etc
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

		if ($IN->SEGS)
		{
			if (isset($this->settings['category_groups']) && ! empty($this->settings['category_groups']))
			{
				$sql_groups = 'AND group_id IN ('.implode(',', $this->settings['category_groups']).')';
			}
			else
			{
				$sql_groups = '';
			}
		
			// put segments in sql IN query; retrieve categories that match
			$sql_segs = "'".implode("','", $DB->escape_str($IN->SEGS))."'";
			$sql = "SELECT
					cat_id, cat_url_title, cat_name, cat_description, cat_image, parent_id, group_id
				FROM
					exp_categories
				WHERE
					cat_url_title
				IN
					({$sql_segs})
				AND
					site_id = '{$site}'
					{$sql_groups}
			";
			$query = $DB->query($sql);
		
			// if we have matching categories, continue...
			if ($query->num_rows)
			{
				// initiate typography class for category title
				if (!class_exists('Typography'))
				{
					require PATH_CORE.'core.typography'.EXT;
				}

				$TYPE = new Typography;

				// flip segment array to get 'segment_1' => '1'
				$ids = array_flip($IN->SEGS);
			
				// loop through categories
				foreach ($query->result AS $row)
				{
					// Overwrite values in data array
					foreach ($this->fields AS $name => $field)
					{
						// Format category name if private var is set
						if ($name == 'cat_name' && $this->format)
						{
							$row[$name] = $TYPE->light_xhtml_typography($row[$name]);
						}

						// Set value in for segment_x_yyy
						$data['segment_'.$ids[$row['cat_url_title']].'_'.$field] = $row[$name];
					}

					$cats[] = $row['cat_id'];
				}

				// Set last_segment_category_x vars
				$last = count($IN->SEGS);
				foreach ($this->fields AS $name => $field)
				{
					$data['last_segment_'.$field] = $data['segment_'.$last.'_'.$field];
				}

				// create inclusive stack of all category ids present in segments
				$data['segment_category_ids'] = implode('&',$cats);
			}
		}

		// register global variables
		$IN->global_vars = array_merge($IN->global_vars,$data);
	}
	// END create_stack()
	
	
	// --------------------------------
	//  Activate Extension
	// --------------------------------
	function activate_extension()
	{
		global $DB, $PREFS;
		
		$DB->query(
			$DB->insert_string(
				'exp_extensions',
				array(
					'extension_id' => '',
					'class'        => __CLASS__,
					'method'       => "create_stack",
					'hook'         => "sessions_end",
					'settings'     => '',
					'priority'     => 1,
					'version'      => $this->version,
					'enabled'      => "y"
				)
			)
		); // end db->query
	}
	// END activate_extension
	 
	 
	// --------------------------------
	//  Update Extension
	// --------------------------------  
	function update_extension($current='')
	{
		global $DB, $PREFS;
		
		if ($current == '' OR $current == $this->version)
		{
			return FALSE;
		}
		
		$DB->query("UPDATE exp_extensions SET version = '".$DB->escape_str($this->version)."' WHERE class = '".__CLASS__."'");
	}
	// END update_extension

	// --------------------------------
	//  Disable Extension
	// --------------------------------
	function disable_extension()
	{
		global $DB, $PREFS;
		
		$DB->query("DELETE FROM exp_extensions WHERE class = '".__CLASS__."'");
	}
	// END disable_extension
	 
}
// END CLASS
?>