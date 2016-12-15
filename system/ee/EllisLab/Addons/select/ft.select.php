<?php

require_once SYSPATH.'ee/legacy/fieldtypes/OptionFieldtype.php';

/**
 * ExpressionEngine - by EllisLab
 *
 * @package		ExpressionEngine
 * @author		EllisLab Dev Team
 * @copyright	Copyright (c) 2003 - 2016, EllisLab, Inc.
 * @license		https://expressionengine.com/license
 * @link		https://ellislab.com
 * @since		Version 2.0
 * @filesource
 */

// --------------------------------------------------------------------

/**
 * ExpressionEngine Select Fieldtype Class
 *
 * @package		ExpressionEngine
 * @subpackage	Fieldtypes
 * @category	Fieldtypes
 * @author		EllisLab Dev Team
 * @link		https://ellislab.com
 */
class Select_ft extends OptionFieldtype {

	var $info = array(
		'name'		=> 'Select Dropdown',
		'version'	=> '1.0.0'
	);

	var $has_array_data = TRUE;


	function validate($data)
	{
		$valid			= FALSE;
		$field_options	= $this->_get_field_options($data, '--');

		if ($data == '')
		{
			return TRUE;
		}

		foreach($field_options as $key => $val)
		{
			if (is_array($val))
			{
				if (array_key_exists($data, $val))
				{
					$valid = TRUE;
					break;
				}
			}
			elseif ($key == $data)
			{
				$valid = TRUE;
				break;
			}
		}

		if ( ! $valid)
		{
			return ee()->lang->line('invalid_selection');
		}
	}

	// --------------------------------------------------------------------

	function display_field($data)
	{
		$extra = 'dir="'.$this->get_setting('field_text_direction', 'ltr').'"';

		if ($this->get_setting('field_disabled'))
		{
			$extra .= ' disabled';
		}

		$field = form_dropdown(
			$this->field_name,
			$this->_get_field_options($data, '--'),
			$data,
			$extra
		);

		return $field;
	}

	// --------------------------------------------------------------------

	function grid_display_field($data)
	{
		return $this->display_field($data);
	}

	// --------------------------------------------------------------------

	function display_settings($data)
	{
		$settings = $this->getSettingsForm(
			$data,
			'select_options',
			lang('options_field_desc').lang('select_options_desc')
		);

		return array('field_options_select' => array(
			'label' => 'field_options',
			'group' => 'select',
			'settings' => $settings
		));
	}

	function grid_display_settings($data)
	{
		return $this->getGridSettingsForm(
			'select',
			$data,
			'select_options',
			'grid_select_options_desc'
		);
	}

	// --------------------------------------------------------------------

	/**
	 * Accept all content types.
	 *
	 * @param string  The name of the content type
	 * @return bool   Accepts all content types
	 */
	public function accepts_content_type($name)
	{
		return TRUE;
	}

	// --------------------------------------------------------------------

	/**
	 * Update the fieldtype
	 *
	 * @param string $version The version being updated to
	 * @return boolean TRUE if successful, FALSE otherwise
	 */
	public function update($version)
	{
		return TRUE;
	}
}

// END Select_ft class

// EOF
