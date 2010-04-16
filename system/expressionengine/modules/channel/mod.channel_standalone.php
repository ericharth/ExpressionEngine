<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * ExpressionEngine - by EllisLab
 *
 * @package		ExpressionEngine
 * @author		ExpressionEngine Dev Team
 * @copyright	Copyright (c) 2003 - 2010, EllisLab, Inc.
 * @license		http://expressionengine.com/docs/license.html
 * @link		http://expressionengine.com
 * @since		Version 2.0
 * @filesource
 */

// --------------------------------------------------------------------

/**
 * ExpressionEngine Channel Module
 *
 * @package		ExpressionEngine
 * @subpackage	Modules
 * @category	Modules
 * @author		ExpressionEngine Dev Team
 * @link		http://expressionengine.com
 */

class Channel_standalone extends Channel {

	var $categories = array();
	var $cat_parents = array();
	var $assign_cat_parent = FALSE;
	var $upload_div = '';
	

	function run_filemanager($function = '', $params = array())
	{
		$this->EE->load->library('filemanager');
		$this->EE->lang->loadfile('content');
		$this->EE->load->library('cp');
		
		$config = array();
		
		$this->EE->filemanager->_initialize($config);
		
		return call_user_func_array(array($this->EE->filemanager, $function), $params);
	}

	/** ----------------------------------------
	/**  Insert a new channel entry
	/** ----------------------------------------*/

	// This function serves dual purpose:
	// 1. It allows submitted data to be previewed
	// 2. It allows submitted data to be inserted

	function insert_new_entry()
	{
		$this->EE->lang->loadfile('channel');
		$this->EE->lang->loadfile('content');
		$this->EE->load->model('field_model');

		// Ya gotta be logged-in billy bob...
		if ($this->EE->session->userdata('member_id') == 0)
		{
			return $this->EE->output->show_user_error('general', $this->EE->lang->line('channel_must_be_logged_in'));
		}

		if ( ! $channel_id = $this->EE->input->post('channel_id') OR ! is_numeric($channel_id))
		{
			return false;
		}

		// Prep file fields
		$file_fields = array();
		
		$this->EE->db->select('field_group');
		$this->EE->db->where('channel_id', $channel_id);
		$query = $this->EE->db->get('channels');
		
		if ($query->num_rows() > 0)
		{
			$row = $query->row();
			$field_group =  $row->field_group;
		
			$this->EE->db->select('field_id');
			$this->EE->db->where('group_id', $field_group);
			$this->EE->db->where('field_type', 'file');
			
			$f_query = $this->EE->db->get('channel_fields');
			
			if ($f_query->num_rows() > 0)
			{
				foreach ($f_query->result() as $row)
				{
   					$file_fields[] = $row->field_id;
				}
			} 
		} 
		
		foreach ($file_fields as $v)
		{
			if (isset($_POST['field_id_'.$v.'_hidden']))
			{
				$_POST['field_id_'.$v] = $_POST['field_id_'.$v.'_hidden'];
				if ( ! $this->EE->input->post('preview'))
				{
					unset($_POST['field_id_'.$v.'_hidden']);
				}
			}

			// Upload or maybe just a path in the hidden field?
			if (isset($_FILES['field_id_'.$v]) && $_FILES['field_id_'.$v]['size'] > 0 && isset($_POST['field_id_'.$v.'_directory']))
			{
				$data = $this->run_filemanager('upload_file', array($_POST['field_id_'.$v.'_directory'], 'field_id_'.$v));
					
				if (array_key_exists('error', $data))
				{
					// @todo validation error
					die('error '.$data['error']);
				}
				else
				{
					$_POST['field_id_'.$v] = $data['name'];
					
					if ($this->EE->input->post('preview') !== FALSE)
					{
						$_POST['field_id_'.$v.'_hidden'] = $data['name'];
					}
				}
			}
		}

		/** ----------------------------------------
		/**  Prep data for insertion
		/** ----------------------------------------*/
		if ( ! $this->EE->input->post('preview'))
		{
			$this->EE->load->library('api');

			$this->EE->api->instantiate(array('channel_entries', 'channel_categories'));
			
			unset($_POST['hidden_pings']);
			unset($_POST['status_id']);
			unset($_POST['allow_cmts']);
			unset($_POST['sticky_entry']);
			
			$return_url	= ( ! $this->EE->input->post('return_url')) ? '' : $this->EE->input->get_post('return_url');
			unset($_POST['return_url']);

			
			if ( ! $this->EE->input->post('entry_date'))
			{
				$_POST['entry_date'] = $this->EE->localize->set_human_time($this->EE->localize->now);
				
			}

			$data = $_POST;
			
			// @confirm not safe?!
			$extra = array(
				'url_title'		=> '',
				'ping_errors'	=> FALSE,
				'revision_post'	=> $_POST,
				);
		
			// Fetch xml-rpc ping server IDs
			$data['ping_servers'] = array();
		
			if (isset($_POST['ping']) && is_array($_POST['ping']))
			{
				$data['ping_servers'] = $_POST['ping'];	
				unset($_POST['ping']);		
			}
		
			$data = array_merge($extra, $data);
		
			$success = $this->EE->api_channel_entries->submit_new_entry($channel_id, $data);
	
			if ( ! $success)
			{
				// @todo error handling could be shinier
				$errors = $this->EE->api_channel_entries->errors;
				return $this->EE->output->show_user_error('general', $errors);
			}
		
			if ($this->EE->api_channel_entries->get_errors('pings'))
			{
				// @todo- figure out if we want to show an error here.  1.6 does not.  Just fails silently. 
				// The error is easy, but we'd want to have the link go to the return url, not back.
			}

			$loc = ($return_url == '') ? $this->EE->functions->fetch_site_index() : $this->EE->functions->create_url($return_url, 1, 0);

			$loc = $this->EE->api_channel_entries->trigger_hook('entry_submission_redirect', $loc);

			$this->EE->functions->redirect($loc);
		} // END Insert


		/** ----------------------------------------
		/**  Preview Entry
		/** ----------------------------------------*/

		if ($this->EE->input->post('PRV') == '')
		{
			$this->EE->lang->loadfile('channel');

			return $this->EE->output->show_user_error('general', $this->EE->lang->line('channel_no_preview_template'));
		}

		$this->EE->functions->clear_caching('all', $_POST['PRV']);

		require APPPATH.'libraries/Template'.EXT;

		$this->EE->TMPL = new EE_Template();

		$preview = ( ! $this->EE->input->post('PRV')) ? '' : $this->EE->input->get_post('PRV');

		if (strpos($preview, '/') === FALSE)
		{
			return FALSE;
		}

		$ex = explode("/", $preview);

		if (count($ex) != 2)
		{
			return FALSE;
		}

		$this->EE->TMPL->run_template_engine($ex['0'], $ex['1']);
	}

	/** ----------------------------------------
	/**  Stand-alone version of the entry form
	/** ----------------------------------------*/

	function entry_form($return_form = FALSE, $captcha = '')
	{
		$field_data	= '';
		$catlist	= '';
		$status		= '';
		$title		= '';
		$url_title	= '';
		$dst_enabled = $this->EE->session->userdata('daylight_savings');

	  	// Load the language file
		$this->EE->lang->loadfile('channel');

		// Load the form helper
		$this->EE->load->helper('form');

		// No loggy? No looky...

		if ($this->EE->session->userdata('member_id') == 0)
	  	{
			return '';
	  	}
	  
		if ( ! $channel = $this->EE->TMPL->fetch_param('channel'))
		{
			return $this->EE->output->show_user_error('general', $this->EE->lang->line('channel_not_specified'));
	  	}
	  
	  	// Fetch the action ID number.  Even though we don't need it until later
	  	// we'll grab it here.  If not found it means the action table doesn't
	  	// contain the ID, which means the user has not updated properly.  Ya know?
	  
	  	if ( ! $insert_action = $this->EE->functions->fetch_action_id('Channel', 'insert_new_entry'))
	  	{
			return $this->EE->output->show_user_error('general', $this->EE->lang->line('channel_no_action_found'));
	  	}
	  
		// We need to first determine which channel to post the entry into.

		$assigned_channels = $this->EE->functions->fetch_assigned_channels();

		$channel_id = ( ! $this->EE->input->post('channel_id')) ? '' : $this->EE->input->get_post('channel_id');

		if ($channel_id == '')
		{
			$query = $this->EE->db->query("SELECT channel_id from exp_channels WHERE site_id IN ('".implode("','", $this->EE->TMPL->site_ids)."') AND channel_name = '".$this->EE->db->escape_str($channel)."'");

			if ($query->num_rows() == 1)
			{
				$channel_id = $query->row('channel_id') ;
			}
		}

		/** ----------------------------------------------
		/**  Security check
		/** ---------------------------------------------*/

		if ( ! in_array($channel_id, $assigned_channels))
		{
			return $this->EE->TMPL->no_results();
		}

		/** ----------------------------------------------
		/**  Fetch channel preferences
		/** ---------------------------------------------*/

		$query = $this->EE->db->query("SELECT * FROM  exp_channels WHERE channel_id = '$channel_id'");

		if ($query->num_rows() == 0)
		{
			return "The channel you have specified does not exist.";
		}

		foreach ($query->row_array() as $key => $val)
		{
			$$key = $val;
		}

		if ( ! isset($_POST['channel_id']))
		{
			$title		= $default_entry_title;
			$url_title	= $url_title_prefix;
		}

		/** ----------------------------------------
		/**  Return the "no cache" version of the form
		/** ----------------------------------------*/
		if ($return_form == FALSE)
		{
			$nc = '{{NOCACHE_CHANNEL_FORM ';

			if (count($this->EE->TMPL->tagparams) > 0)
			{
				foreach ($this->EE->TMPL->tagparams as $key => $val)
				{
					$nc .= ' '.$key.'="'.$val.'" ';
				}
			}
			
			$nc .= '}}'.$this->EE->TMPL->tagdata.'{{/NOCACHE_FORM}}';

			return $nc;
		}

		/** ----------------------------------------------
		/**  JavaScript For URL Title
		/** ---------------------------------------------*/

		$convert_ascii = ($this->EE->config->item('auto_convert_high_ascii') == 'y') ? TRUE : FALSE;
		$word_separator = $this->EE->config->item('word_separator') != "dash" ? '_' : '-';

		/** -------------------------------------
		/**  Create Foreign Character Conversion JS
		/** -------------------------------------*/

		include(APPPATH.'config/foreign_chars.php');
	
		/* -------------------------------------
		/*  'foreign_character_conversion_array' hook.
		/*  - Allows you to use your own foreign character conversion array
		/*  - Added 1.6.0
		/* 	- Note: in 2.0, you can edit the foreign_chars.php config file as well
		*/  
			if (isset($this->extensions->extensions['foreign_character_conversion_array']))
			{
				$foreign_characters = $this->extensions->call('foreign_character_conversion_array');
			}
		/*
		/* -------------------------------------*/

		$foreign_replace = '';

		foreach($foreign_characters as $old => $new)
		{
			$foreign_replace .= "if (c == '$old') {NewTextTemp += '$new'; continue;}\n\t\t\t\t";
		}
		
		$default_entry_title = form_prep($default_entry_title);
		
		$action_id = $this->EE->functions->fetch_action_id('Channel', 'filemanager_endpoint');
		$endpoint = 'ACT='.$action_id;
		
		$this->EE->load->library('filemanager');
		$this->EE->load->library('javascript');
		$this->EE->load->model('admin_model');
		$this->EE->load->model('tools_model');
		
		// Upload Directories

		$upload_directories = $this->EE->tools_model->get_upload_preferences($this->EE->session->userdata('group_id'));

		$file_list = array();
		$directories = array();

		foreach($upload_directories->result() as $row)
		{
			$directories[$row->id] = $row->name;

			$file_list[$row->id]['id'] = $row->id;
			$file_list[$row->id]['name'] = $row->name;
			$file_list[$row->id]['url'] = $row->url;
		}		
		
		$html_buttons = $this->EE->admin_model->get_html_buttons($this->EE->session->userdata('member_id'));
		
		$button_js = array();


		foreach ($html_buttons->result() as $button)
		{
			if (strpos($button->classname, 'btn_img') !== FALSE)
			{
				// images are handled differently because of the file browser
				// at least one image must be available for this to work
				if (count($file_list) > 0)
				{
					$button_js[] = array('name' => $button->tag_name, 'key' => $button->accesskey, 'replaceWith' => '', 'className' => $button->classname);
				}
			}
			elseif(strpos($button->classname, 'markItUpSeparator') !== FALSE)
			{
				// separators are purely presentational
				$button_js[] = array('separator' => '---');
			}
			else
			{
				$button_js[] = array('name' => $button->tag_name, 'key' => $button->accesskey, 'openWith' => $button->tag_open, 'closeWith' => $button->tag_close, 'className' => $button->classname);
			}
		}
		
		$js_includes = $this->EE->filemanager->frontend_filebrowser($endpoint, TRUE);
		
		$markItUp = array(
			'nameSpace'		=> "html",
			'onShiftEnter'	=> array('keepDefault' => FALSE, 'replaceWith' => "<br />\n"),
			'onCtrlEnter'	=> array('keepDefault' => FALSE, 'openWith' => "\n<p>", 'closeWith' => "</p>\n"),
			'markupSet'		=> $button_js,
		);	

		$url_title_js = <<<EOT
		
		<script type="text/javascript">
		<!--
		function liveUrlTitle()
		{
			var defaultTitle = '{$default_entry_title}';
			var NewText = document.getElementById("title").value;

			if (defaultTitle != '')
			{
				if (NewText.substr(0, defaultTitle.length) == defaultTitle)
				{
					NewText = NewText.substr(defaultTitle.length)
				}
			}

			NewText = NewText.toLowerCase();
			var separator = "{$word_separator}";

			// Foreign Character Attempt

			var NewTextTemp = '';
			for(var pos=0; pos<NewText.length; pos++)
			{
				var c = NewText.charCodeAt(pos);

				if (c >= 32 && c < 128)
				{
					NewTextTemp += NewText.charAt(pos);
				}
				else
				{
					{$foreign_replace}
				}
			}

			var multiReg = new RegExp(separator + '{2,}', 'g');

			NewText = NewTextTemp;

			NewText = NewText.replace('/<(.*?)>/g', '');
			NewText = NewText.replace(/\s+/g, separator);
			NewText = NewText.replace(/\//g, separator);
			NewText = NewText.replace(/[^a-z0-9\-\._]/g,'');
			NewText = NewText.replace(/\+/g, separator);
			NewText = NewText.replace(multiReg, separator);
			NewText = NewText.replace(/-$/g,'');
			NewText = NewText.replace(/_$/g,'');
			NewText = NewText.replace(/^_/g,'');
			NewText = NewText.replace(/^-/g,'');

			if (document.getElementById("url_title"))
			{
				document.getElementById("url_title").value = "{$url_title_prefix}" + NewText;
			}
			else
			{
				document.forms['entryform'].elements['url_title'].value = "{$url_title_prefix}" + NewText;
			}
		}
		
		-->
		</script>
		
EOT;

$misc_js = <<<EOT

{$js_includes}

		<script type="text/javascript">
		<!--			
			
$(document).ready(function() {
	
	$(".js_show").show();
	$(".js_hide").hide();
	

			$.ee_filebrowser();
			
			// Prep for a workaround to allow markitup file insertion in file inputs
			$(".btn_img a, .file_manipulate").click(function(){
				window.file_manager_context = ($(this).parent().attr("class").indexOf("markItUpButton") == -1) ? $(this).closest("div").find("input").attr("id") : "textarea_a8LogxV4eFdcbC";
			});
					
			// Bind the image html buttons
			$.ee_filebrowser.add_trigger(".btn_img a, .file_manipulate", function(file) {
				// We also need to allow file insertion into text inputs (vs textareas) but markitup
				// will not accommodate this, so we need to detect if this request is coming from a 
				// markitup button (textarea_a8LogxV4eFdcbC), or another field type.

				if (window.file_manager_context == "textarea_a8LogxV4eFdcbC")
				{
					// Handle images and non-images differently
					if ( ! file.is_image)
					{
						$.markItUp({name:"Link", key:"L", openWith:"<a href=\"{filedir_"+file.directory+"}"+file.name+"\">", closeWith:"</a>", placeHolder:file.name });
					}
					else
					{
						$.markItUp({ replaceWith:"<img src=\"{filedir_"+file.directory+"}"+file.name+"\" alt=\"[![Alternative text]!]\" "+file.dimensions+"/>" } );
					}
				}
				else
				{
					$("#"+window.file_manager_context).val("{filedir_"+file.directory+"}"+file.name);
				}
			});

		function file_field_changed(file, field) {
			var container = $("input[name="+field+"]").closest(".publish_field");
			container.find(".file_set").show().find(".filename").text(file.name);
			$("input[name="+field+"_hidden]").val(file.name);
			$("select[name="+field+"_directory]").val(file.directory);
 			}


			$("input[type=file]", "#publishForm").each(function() {
				var container = $(this).closest(".publish_field"),
					trigger = container.find(".choose_file");
					
				$.ee_filebrowser.add_trigger(trigger, $(this).attr("name"), file_field_changed);
				
				container.find(".remove_file").click(function() {
					container.find("input[type=hidden]").val("");
					container.find(".file_set").hide();
					return false;
				});
			});

 });
			
		-->
		</script>		
		
EOT;

		$this->EE->lang->loadfile('content');

		/** ----------------------------------------
		/**  Compile form declaration and hidden fields
		/** ----------------------------------------*/

		$RET = (isset($_POST['RET'])) ? $_POST['RET'] : $this->EE->functions->fetch_current_uri();
		$XID = ( ! isset($_POST['XID'])) ? '' : $_POST['XID'];
		$PRV = (isset($_POST['PRV'])) ? $_POST['PRV'] : '{PREVIEW_TEMPLATE}';

		$hidden_fields = array(
								'ACT'	  				=> $insert_action,
								'RET'	  				=> $RET,
								'PRV'	  				=> $PRV,
								'URI'	  				=> ($this->EE->uri->uri_string == '') ? 'index' : $this->EE->uri->uri_string,
								'XID'	  				=> $XID,
								'return_url'			=> (isset($_POST['return_url'])) ? $_POST['return_url'] : $this->EE->TMPL->fetch_param('return'),
								'author_id'				=> $this->EE->session->userdata('member_id'),
								'channel_id'			=> $channel_id
							  );

		/** ----------------------------------------
		/**  Add status to hidden fields
		/** ----------------------------------------*/

		$status_id = ( ! isset($_POST['status_id'])) ? $this->EE->TMPL->fetch_param('status') : $_POST['status_id'];

		if ($status_id == 'Open' OR $status_id == 'Closed')
			$status_id = strtolower($status_id);

		$status_query = $this->EE->db->query("SELECT * FROM exp_statuses WHERE group_id = '$status_group' order by status_order");

		if ($status_id != '')
		{
			$closed_flag = TRUE;

			if ($status_query->num_rows() > 0)
			{  
				foreach ($status_query->result_array() as $row)
				{
					if ($row['status'] == $status_id)
						$closed_flag = FALSE;
				}
			}

			$hidden_fields['status'] = ($closed_flag == TRUE) ? 'closed' : $status_id;
		}


		/** ----------------------------------------
		/**  Add "allow" options
		/** ----------------------------------------*/

		$allow_cmts = ( ! isset($_POST['allow_cmts'])) ? $this->EE->TMPL->fetch_param('allow_comments') : $_POST['allow_cmts'];

		if ($allow_cmts != '' AND $comment_system_enabled == 'y')
		{
			$hidden_fields['allow_comments'] = ($allow_cmts == 'yes') ? 'y' : 'n';
		}

		$sticky_entry = ( ! isset($_POST['sticky_entry'])) ? $this->EE->TMPL->fetch_param('sticky_entry') : $_POST['sticky_entry'];

		if ($sticky_entry != '')
		{
			$hidden_fields['sticky'] = ($sticky_entry == 'yes') ? 'y' : 'n';
		}

		/** ----------------------------------------
		/**  Add categories to hidden fields
		/** ----------------------------------------*/
		if ($category_id = $this->EE->TMPL->fetch_param('category'))
		{
			if (isset($_POST['category']))
			{
				foreach ($_POST as $key => $val)
				{
					if (strpos($key, 'category') !== FALSE && is_array($val))
					{
						$i =0;
						foreach ($val as $v)
						{
							$hidden_fields['category['.($i++).']'] = $v;
						}
					}
				}
			}
			else
			{
				if (strpos($category_id, '|') === FALSE)
				{
					$hidden_fields['category[]'] = $category_id;
				}
				else
				{
					$i = 0;

					foreach(explode("|", trim($category_id, '|')) as $val)
					{
						$hidden_fields['category['.($i++).']'] = $val;
					}
				}
			}
		}

		/** ----------------------------------------
		/**  Add pings to hidden fields
		/** ----------------------------------------*/

		$hidden_pings = ( ! isset($_POST['hidden_pings'])) ? $this->EE->TMPL->fetch_param('hidden_pings') : $_POST['hidden_pings'];

		if ($hidden_pings == 'yes')
		{
			$hidden_fields['hidden_pings'] = 'yes';

			$ping_servers = $this->fetch_ping_servers('new');

			if (is_array($ping_servers) AND count($ping_servers) > 0)
			{
				$i = 0;
				foreach ($ping_servers as $val)
				{
					if ($val['1'] != '')
						$hidden_fields['ping['.($i++).']'] = $val['0'];
				}
			}
		}


		//  Parse out the tag
		$tagdata = $this->EE->TMPL->tagdata;

		//  Upload and Smileys Link
		
		$s = ($this->EE->config->item('admin_session_type') != 'c') ? $this->EE->session->userdata['session_id'] : 0;

		$action_id = $this->EE->functions->fetch_action_id('Channel', 'smiley_pop');
		$smiley = $this->EE->functions->fetch_site_index(0, 0).QUERY_MARKER.'ACT='.$action_id.AMP.'field_group='.$field_group;
		$tagdata = str_replace('{smileys_url}',$smiley, $tagdata);

		//  Formatting buttons
		$tagdata = str_replace(LD.'formatting_buttons'.RD, '', $tagdata); // remove from the template until this is added


		// Onward...

		$which = ($this->EE->input->post('preview')) ? 'preview' : 'new';

		/** --------------------------------
		/**  Fetch Custom Fields
		/** --------------------------------*/

		if ($this->EE->TMPL->fetch_param('show_fields') !== FALSE)
		{
			if (strncmp($this->EE->TMPL->fetch_param('show_fields'), 'not ', 4) == 0)
			{
				$these = "AND field_name NOT IN ('".str_replace('|', "','", trim(substr($this->EE->TMPL->fetch_param('show_fields'), 3)))."') ";
			}
			else
			{
				$these = "AND field_name IN ('".str_replace('|', "','", trim($this->EE->TMPL->fetch_param('show_fields')))."') ";
			}
		}
		else
		{
			$these = '';
		}

		$query = $this->EE->db->query("SELECT * FROM  exp_channel_fields WHERE group_id = '$field_group' $these ORDER BY field_order");

		$fields = array();
		$date_fields = array();
		$pair_fields = array();
		$pfield_chunk = array();
		$cond = array();

		if ($which == 'preview')
		{ 
			foreach ($query->result_array() as $row)
			{
				$fields['field_id_'.$row['field_id']] = array($row['field_name'], $row['field_type']);
				$cond[$row['field_name']] = '';

				if ($row['field_type'] == 'date')
				{
					$date_fields[$row['field_name']] = $row['field_id'];
				}
				elseif (in_array($row['field_type'], array('file', 'multi_select', 'checkboxes')))
				{
					$pair_fields[$row['field_name']] = array($row['field_type'], $row['field_id']);
				}
			}
		}

		/** ----------------------------------------
		/**  Preview
		/** ----------------------------------------*/

		if (preg_match("#".LD."preview".RD."(.+?)".LD.'/'."preview".RD."#s", $tagdata, $match))
		{
			if ($which != 'preview')
			{
				$tagdata = str_replace ($match['0'], '', $tagdata);
			}
			else
			{
				// Snag out the possible pair chunks (file, multiselect and checkboxes)
				foreach ($pair_fields as $field_name => $field_info)
				{
					if (($end = strpos($match['1'], LD.'/'.$field_name.RD)) !== FALSE)
					{
						// @confirm FIX IT! this is ugly
						if (preg_match_all("/".LD."{$field_name}(.*?)".RD."(.*?)".LD.'\/'.$field_name.RD."/s", $match['1'], $pmatches))
						{
							for ($j = 0; $j < count($pmatches[0]); $j++)
							{
								$chunk = $pmatches[0][$j];
								$params = $pmatches[1][$j];
								$inner = $pmatches[2][$j];
								
								// We might've sandwiched a single tag - no good, check again (:sigh:)
								if ((strpos($chunk, LD.$field_name, 1) !== FALSE) && preg_match_all("/".LD."{$field_name}(.*?)".RD."/s", $chunk, $pmatch))
								{
									// Let's start at the end
									$idx = count($pmatch[0]) - 1;
									$tag = $pmatch[0][$idx];
									
									// Cut the chunk at the last opening tag (PHP5 could do this with strrpos :-( )
									while (strpos($chunk, $tag, 1) !== FALSE)
									{
										$chunk = substr($chunk, 1);
										$chunk = strstr($chunk, LD.$field_name);
										$inner = substr($chunk, strlen($tag), -strlen(LD.'/'.$field_name.RD));
									}
								}

								$pfield_chunk['field_id_'.$field_info['1']][] = array($inner, $this->EE->functions->assign_parameters($params), $chunk);
							}
						}
					}
				}

				/** ----------------------------------------
				/**  Instantiate Typography class
				/** ----------------------------------------*/

				$this->EE->load->library('typography');
				$this->EE->typography->initialize();

				$this->EE->typography->convert_curly = FALSE;
				$file_dirs = $this->EE->functions->fetch_file_paths();

				$match['1'] = str_replace(LD.'title'.RD, stripslashes($this->EE->input->post('title')), $match['1']);

				// We need to grab each
				$str = '';

				foreach($_POST as $key => $val)
				{
					if (strncmp($key, 'field_id', 8) == 0)
					{
						// do pair variables
						if (isset($pfield_chunk[$key]))
						{
							
							$expl = explode('field_id_', $key);
							$txt_fmt = ( ! isset($_POST['field_ft_'.$expl['1']])) ? 'xhtml' : $_POST['field_ft_'.$expl['1']];
													
							// Blast through all the chunks
							foreach($pfield_chunk[$key] as $chk_data)
							{
								$tpl_chunk = '';
								$limit = FALSE;
								
								// Limit Parameter
								if (is_array($chk_data[1]) AND isset($chk_data[1]['limit']))
								{
									$limit = $chk_data[1]['limit'];
								}

								foreach($val as $k => $item)
								{
									if ( ! $limit OR $k < $limit)
									{
										$vars['item'] = $item;
										$vars['count'] = $k + 1;	// {count} parameter

										$tmp = $this->EE->functions->prep_conditionals($chk_data[0], $vars);
										$tpl_chunk .= $this->EE->functions->var_swap($tmp, $vars);
									}
									else
									{
										break;
									}
								}

								// Everybody loves backspace
								if (is_array($chk_data[1]) AND isset($chk_data[1]['backspace']))
								{
									$tpl_chunk = substr($tpl_chunk, 0, - $chk_data[1]['backspace']);
								}

							}
							
							// Typography!
							$tpl_chunk = $this->EE->typography->parse_type(
												$this->EE->functions->encode_ee_tags($tpl_chunk),
												array(
																'text_format'   => $txt_fmt,
																'html_format'   => $channel_html_formatting,
																'auto_links'    => $channel_allow_img_urls,
																'allow_img_url' => $channel_auto_link_urls
													  )
								);

							// Replace the chunk
							if (isset($fields[$key]['0']))
							{
								$match['1'] = str_replace($chk_data[2], $tpl_chunk, $match['1']);
							}
						}

						// end pair variables						
						
						$expl = explode('field_id_', $key);
						$temp = '';
						if (! is_numeric($expl['1'])) continue;

						if (in_array($expl['1'], $date_fields))
						{
							$temp_date = $this->EE->localize->convert_human_date_to_gmt($_POST['field_id_'.$expl['1']]);
							$temp = $_POST['field_id_'.$expl['1']];
							$cond[$fields['field_id_'.$expl['1']]['0']] =  $temp_date;
						}
						elseif ($fields['field_id_'.$expl['1']]['1'] == 'file')
						{
							$file_info['path'] = '';
							$file_info['extension'] = '';
							$file_info['filename'] = '';
							$full_path = '';
							$entry = '';

							if ($val != '')
							{
								$parts = explode('.', $val);
								$file_info['extension'] = array_pop($parts);
								$file_info['filename'] = implode('.', $parts);

								if (isset($_POST['field_id_'.$expl['1'].'_directory']) && isset($_POST['field_id_'.$expl['1']]) && $_POST['field_id_'.$expl['1']] != '')
								{
									$file_info['path'] = $file_dirs[$_POST['field_id_'.$expl['1'].'_directory']];
								}

								$full_path = $file_info['path'].$file_info['filename'].'.'.$file_info['extension'];
							}
							
							if (preg_match_all("/".LD.$fields['field_id_'.$expl['1']]['0']."(.*?)".RD."/s", $match['1'], $pmatches))
							{
								foreach ($pmatches['0'] as $id => $tag)
								{
									if ($pmatches['1'][$id] == '')
									{
										$entry = $full_path;
									}
									else
									{
										$params = $this->EE->functions->assign_parameters($pmatches['1'][$id]);
										
										if (isset($params['wrap']) && $params['wrap'] == 'link')
										{
											$entry = '<a href="'.$full_path.'">'.$file_info['filename'].'</a>';
										}
										elseif (isset($params['wrap']) && $params['wrap'] == 'image')
										{
											$entry = '<img src="'.$full_path.'" alt="'.$file_info['filename'].'" />';
										}
										else
										{
											$entry = $full_path;
										}
									}
										
									$match['1'] = str_replace($pmatches['0'][$id], $entry, $match['1']);
								}
							}
							
							$str .= '<p>'.$full_path.'</p>';
						}
						elseif (in_array($fields['field_id_'.$expl['1']]['1'], array('multi_select', 'checkboxes')))
						{
							$entry = implode(', ', $val);
								
							$cond[$fields['field_id_'.$expl['1']]['0']] =  ( ! isset($_POST['field_id_'.$expl['1']])) ? '' : $entry;

							$txt_fmt = ( ! isset($_POST['field_ft_'.$expl['1']])) ? 'xhtml' : $_POST['field_ft_'.$expl['1']];
								
							if (preg_match_all("/".LD.$fields['field_id_'.$expl['1']]['0']."(.*?)".RD."/s", $match['1'], $pmatches))
							{
								foreach ($pmatches['0'] as $id => $tag)
								{
									if ($pmatches['1'][$id] == '')
									{
										
									}
									else
									{
										$params = $this->EE->functions->assign_parameters($pmatches['1'][$id]);

										if (isset($params['limit']))
										{
											$limit = intval($params['limit']);
							
											if (count($val) > $limit)
											{
												$val = array_slice($val, 0, $limit);
											}
										}

										if (isset($params['markup']) && ($params['markup'] == 'ol' OR $params['markup'] == 'ul'))
										{
											$entry = '<'.$params['markup'].'>';
								
											foreach($val as $dv)
											{
												$entry .= '<li>';
												$entry .= $dv;
												$entry .= '</li>';
											}

											$entry .= '</'.$params['markup'].'>';
										}
									}
	
									$entry = $this->EE->typography->parse_type(
											$this->EE->functions->encode_ee_tags($entry),
											array(
													'text_format'   => $txt_fmt,
													'html_format'   => $channel_html_formatting,
													'auto_links'    => $channel_allow_img_urls,
													'allow_img_url' => $channel_auto_link_urls
											)
										);

									$match['1'] = str_replace($pmatches['0'][$id], $entry, $match['1']);
								}
							}

							$str .= '<p>'.$entry.'</p>';
						}
						elseif (! is_array($val))
						{
							// @todo something hinky w/needing conditional check here ditto $temp blank bit
							//echo $val;
							if (isset($fields['field_id_'.$expl['1']]))
							{
							
								$cond[$fields['field_id_'.$expl['1']]['0']] =  ( ! isset($_POST['field_id_'.$expl['1']])) ? '' : $_POST['field_id_'.$expl['1']];

								$txt_fmt = ( ! isset($_POST['field_ft_'.$expl['1']])) ? 'xhtml' : $_POST['field_ft_'.$expl['1']];

								$temp = $this->EE->typography->parse_type( stripslashes($val),
												 		array(
															'text_format'   => $txt_fmt,
															'html_format'   => $channel_html_formatting,
															'auto_links'    => $channel_allow_img_urls,
															'allow_img_url' => $channel_auto_link_urls
													   		)
													);
							}
						}

						if (isset($fields[$key]['0']))
						{
							$match['1'] = str_replace(LD.$fields[$key]['0'].RD, $temp, $match['1']);
						}

						$str .= $temp;
					//}
					
					
					// end non pair fields
					}
				
				}

				$match['1'] = str_replace(LD.'display_custom_fields'.RD, $str, $match['1']);
				$match['1'] = $this->EE->functions->prep_conditionals($match['1'], $cond);
				$tagdata = str_replace ($match['0'], $match['1'], $tagdata);
			}

//  This ends preview parsing- it's the only spot we need to parse custom fields that are funky.
		}



		/** -------------------------------------
		/**  Fetch the {custom_fields} chunk
		/** -------------------------------------*/

		$custom_fields = '';
		$file_allowed = (count($directories) > 0) ? TRUE : FALSE;

		if (preg_match("#".LD."custom_fields".RD."(.+?)".LD.'/'."custom_fields".RD."#s", $tagdata, $match))
		{
			$custom_fields = trim($match['1']);

			$tagdata = str_replace($match['0'], LD.'temp_custom_fields'.RD, $tagdata);
		}

		// If we have custom fields to show, generate them

		if ($custom_fields != '')
		{
			$field_array = array('textarea', 'textinput', 'pulldown', 'multiselect', 'checkbox', 'radio', 'file', 'date', 'relationship', 'file');

			$textarea 	= '';
			$textinput 	= '';
			$pulldown	= '';
			$multiselect= '';
			$checkbox	= '';
			$radio		= '';
			$file		= '';
			$file_options	= '';
			$file_pulldown	= '';			
			$date		= '';
			$relationship = '';
			$rel_options = '';
			$pd_options	= '';
			$multi_options = '';
			$check_options = '';
			$radio_options = '';
			$required	= '';

			foreach ($field_array as $val)
			{
				if (preg_match("#".LD."\s*if\s+".$val.RD."(.+?)".LD.'/'."if".RD."#s", $custom_fields, $match))
				{
					$$val = $match['1'];

					if ($val == 'pulldown')
					{
						if (preg_match("#".LD."options".RD."(.+?)".LD.'/'."options".RD."#s", $pulldown, $pmatch))
						{
							$pd_options = $pmatch['1']; 
							$pulldown = str_replace ($pmatch['0'], LD.'temp_pd_options'.RD, $pulldown);
						}
					}

					if ($val == 'file')
					{
						if (preg_match("#".LD."options".RD."(.+?)".LD.'/'."options".RD."#s", $file, $pmatch))
						{
							$file_options = $pmatch['1']; 
							$file = str_replace ($pmatch['0'], LD.'temp_file_options'.RD, $file);
						}
					}


					if ($val == 'multiselect')
					{
						if (preg_match("#".LD."options".RD."(.+?)".LD.'/'."options".RD."#s", $multiselect, $pmatch))
						{
							$multi_options = $pmatch['1'];
							$multiselect = str_replace ($pmatch['0'], LD.'temp_multi_options'.RD, $multiselect);
						}
					}

					if ($val == 'checkbox')
					{
						if (preg_match("#".LD."options".RD."(.+?)".LD.'/'."options".RD."#s", $checkbox, $pmatch))
						{
							$check_options = $pmatch['1'];
							$checkbox = str_replace ($pmatch['0'], LD.'temp_check_options'.RD, $checkbox);
						}
					}
					
					if ($val == 'radio')
					{
						if (preg_match("#".LD."options".RD."(.+?)".LD.'/'."options".RD."#s", $radio, $pmatch))
						{
							$radio_options = $pmatch['1'];
							$radio = str_replace ($pmatch['0'], LD.'temp_radio_options'.RD, $radio);
						}
					}

					if ($val == 'relationship')
					{
						if (preg_match("#".LD."options".RD."(.+?)".LD.'/'."options".RD."#s", $relationship, $pmatch))
						{
							$rel_options = $pmatch['1'];
							$relationship = str_replace ($pmatch['0'], LD.'temp_rel_options'.RD, $relationship);
						}
					}

					$custom_fields = str_replace($match['0'], LD.'temp_'.$val.RD, $custom_fields);
				}
			}

			if (preg_match("#".LD."if\s+required".RD."(.+?)".LD.'/'."if".RD."#s", $custom_fields, $match))
			{
				$required = $match['1'];

				$custom_fields = str_replace($match['0'], LD.'temp_required'.RD, $custom_fields);
			}

			/** --------------------------------
			/**  Parse Custom Fields
			/** --------------------------------*/

			$build = '';

			foreach ($query->result_array() as $row)
			{
				$temp_chunk = $custom_fields;
				$temp_field = '';

				switch ($which)
				{
					case 'preview' :
							$field_data = ( ! isset( $_POST['field_id_'.$row['field_id']] )) ?  '' : $_POST['field_id_'.$row['field_id']];
							$field_fmt  = ( ! isset( $_POST['field_ft_'.$row['field_id']] )) ? $row['field_fmt'] : $_POST['field_ft_'.$row['field_id']];
						break;
					/* no edits or $result in the SAEF - leftover from old CP Publish class
					case 'edit'	:
							$field_data = ($result->row('field_id_'.$row['field_id']) !== FALSE) ? '' : $result->row('field_id_'.$row['field_id']);
							$field_fmt  = ($result->row('field_ft_'.$row['field_id']) !== FALSE) ? $row['field_fmt'] : $result->row('field_ft_'.$row['field_id']);
						break;
					*/
					default		:
							$field_data = '';
							$field_fmt  = $row['field_fmt'];
						break;
				}


				/** --------------------------------
				/**  Textarea field types
				/** --------------------------------*/

				if ($row['field_type'] == 'textarea' AND $textarea != '')
				{
					$temp_chunk = str_replace(LD.'temp_textarea'.RD, $textarea, $temp_chunk);
				}
				if ($row['field_type'] == 'text' AND $textinput != '')
				{
					$temp_chunk = str_replace(LD.'temp_textinput'.RD, $textinput, $temp_chunk);
				}
				if ($row['field_type'] == 'file' AND $file != '')
				{

						$pdo = '';

						$file_dir = ( ! isset( $_POST['field_id_'.$row['field_id'].'_directory'] )) ?  '' : $_POST['field_id_'.$row['field_id'].'_directory'];
						$filename = ( ! isset( $_POST['field_id_'.$row['field_id'].'_hidden'] )) ?  '' : $_POST['field_id_'.$row['field_id'].'_hidden'];
						
						$file_div = 'hold_field_'.$row['field_id'];
						$file_set = 'file_set';
						
						if ($filename == '')
						{
							$file_set .= ' js_hide';
						}
						


						foreach ($directories as $k => $v)
						{
							$temp_options = $file_options;
							

							$v = trim($v);
							$temp_options = str_replace(LD.'option_name'.RD, $v, $temp_options);
							$temp_options = str_replace(LD.'option_value'.RD, $k, $temp_options);
							$temp_options = str_replace(LD.'selected'.RD, ($k == $file_dir) ? ' selected="selected"' : '', $temp_options);

							$pdo .= $temp_options;
						}

						$temp_file = str_replace(LD.'temp_file_options'.RD, $pdo, $file);
						$temp_file = str_replace(LD.'file_name'.RD, $filename, $temp_file);
						$temp_file = str_replace(LD.'file_set'.RD, $file_set, $temp_file);
						$temp_file = str_replace(LD.'file_div'.RD, $file_div, $temp_file);
						
						
												
						$temp_chunk = str_replace(LD.'temp_file'.RD, $temp_file, $temp_chunk); 
				}				
				
				if ($row['field_type'] == 'rel')
				{
					if ($row['field_related_to'] == 'channel')
					{
						$relto = 'exp_channel_titles';
						$relid = 'channel_id';
					}
					else
					{
						$relto = 'exp_gallery_entries';
						$relid = 'gallery_id';
					}

					if ($row['field_related_orderby'] == 'date')
						$row['field_related_orderby'] = 'entry_date';


					$sql = "SELECT entry_id, title FROM ".$relto." WHERE ".$relid." = '".$this->EE->db->escape_str($row['field_related_id'])."' ";
					$sql .= "ORDER BY ".$row['field_related_orderby']." ".$row['field_related_sort'];

					if ($row['field_related_max'] > 0)
					{
						$sql .= " LIMIT ".$row['field_related_max'];
					}

					$relquery = $this->EE->db->query($sql);

					if ($relquery->num_rows() > 0)
					{
						$relentry_id = '';
						if ( ! isset($_POST['field_id_'.$row['field_id']]))
						{
							$relentry = $this->EE->db->query("SELECT rel_child_id FROM exp_relationships WHERE rel_id = '".$this->EE->db->escape_str($field_data)."'");

							if ($relentry->num_rows() == 1)
							{
								$relentry_id = $relentry->row('rel_child_id') ;
							}
						}
						else
						{
							$relentry_id = $_POST['field_id_'.$row['field_id']];
						}

						$temp_options = $rel_options;
						$temp_options = str_replace(LD.'option_name'.RD, '--', $temp_options);
						$temp_options = str_replace(LD.'option_value'.RD, '', $temp_options);
						$temp_options = str_replace(LD.'selected'.RD, '', $temp_options);
						$pdo = $temp_options;
						foreach ($relquery->result_array() as $relrow)
						{
							$temp_options = $rel_options;
							$temp_options = str_replace(LD.'option_name'.RD, $relrow['title'], $temp_options);
							$temp_options = str_replace(LD.'option_value'.RD, $relrow['entry_id'], $temp_options);
							$temp_options = str_replace(LD.'selected'.RD, ($relentry_id == $relrow['entry_id']) ? ' selected="selected"' : '', $temp_options);

							$pdo .= $temp_options;
						}

						$temp_relationship = str_replace(LD.'temp_rel_options'.RD, $pdo, $relationship);
						$temp_chunk = str_replace(LD.'temp_relationship'.RD, $temp_relationship, $temp_chunk);
					}
				}
				if ($row['field_type'] == 'date' AND $date != '')
				{
					$temp_chunk = $custom_fields;

					$date_field = 'field_id_'.$row['field_id'];
					$date_local = 'field_dt_'.$row['field_id'];

					$dtwhich = $which;
					if (isset($_POST[$date_field]))
					{
						$field_data = $_POST[$date_field];
						$dtwhich = 'preview';
					}

					$custom_date = '';
					$localize = FALSE;
					if ($dtwhich != 'preview')
					{
						$localize = TRUE;
						
						/* $result is a leftover from the old cp Publish class, unused at present
						if ($field_data != '' && $result->row('field_dt_'.$row['field_id']) != '')
						{
							$field_data = $this->EE->localize->offset_entry_dst($field_data, $dst_enabled);
							$field_data = $this->EE->localize->simpl_offset($field_data, $result->row('field_dt_'.$row['field_id']));
							$localize = FALSE;
						}
						*/
						
						if ($field_data != '')
							$custom_date = $this->EE->localize->set_human_time($field_data, $localize);

						$cal_date = ($this->EE->localize->set_localized_time($custom_date) * 1000);
					}
					else
					{
						$custom_date = $_POST[$date_field];
						$cal_date = ($custom_date != '') ? ($this->EE->localize->set_localized_time($this->EE->localize->convert_human_date_to_gmt($custom_date)) * 1000) : ($this->EE->localize->set_localized_time() * 1000);
					}

					$temp_chunk = str_replace(LD.'temp_date'.RD, $date, $temp_chunk);
					$temp_chunk = str_replace(LD.'date'.RD, $custom_date, $temp_chunk);
				}
				elseif ($row['field_type'] == 'select' AND $pulldown != '')
				{
					if ($row['field_pre_populate'] == 'n')
					{
						$pdo = '';

						if ($row['field_required'] == 'n')
						{
							$temp_options = $pd_options;
							
//echo $temp_options;							
							$temp_options = str_replace(LD.'option_name'.RD, '--', $temp_options);
							$temp_options = str_replace(LD.'option_value'.RD, '', $temp_options);
							$temp_options = str_replace(LD.'selected'.RD, '', $temp_options);
							$pdo = $temp_options;
						}

						foreach (explode("\n", trim($row['field_list_items'])) as $v)
						{
							$temp_options = $pd_options;

							$v = trim($v);
							$temp_options = str_replace(LD.'option_name'.RD, $v, $temp_options);
							$temp_options = str_replace(LD.'option_value'.RD, $v, $temp_options);
							$temp_options = str_replace(LD.'selected'.RD, ($v == $field_data) ? ' selected="selected"' : '', $temp_options);

							$pdo .= $temp_options;
						}

						$temp_pulldown = str_replace(LD.'temp_pd_options'.RD, $pdo, $pulldown);
						$temp_chunk = str_replace(LD.'temp_pulldown'.RD, $temp_pulldown, $temp_chunk); 
					}
					else
					{
						// We need to pre-populate this menu from an another channel custom field
						$pop_query = $this->EE->db->query("SELECT field_id_".$row['field_pre_field_id']." FROM exp_channel_data WHERE channel_id = ".$row['field_pre_channel_id']." AND field_id_".$row['field_pre_field_id']." != ''");

						if ($pop_query->num_rows() > 0)
						{
							$temp_options = $rel_options;
							$temp_options = str_replace(LD.'option_name'.RD, '--', $temp_options);
							$temp_options = str_replace(LD.'option_value'.RD, '', $temp_options);
							$temp_options = str_replace(LD.'selected'.RD, '', $temp_options);
							$pdo = $temp_options;

							foreach ($pop_query->result_array() as $prow)
							{
								$pretitle = substr($prow['field_id_'.$row['field_pre_field_id']], 0, 110);
								$pretitle = str_replace(array("\r\n", "\r", "\n", "\t"), " ", $pretitle);
								$pretitle = form_prep($pretitle);

								$temp_options = $pd_options;
								$temp_options = str_replace(LD.'option_name'.RD, $pretitle, $temp_options);
								$temp_options = str_replace(LD.'option_value'.RD, form_prep($prow['field_id_'.$row['field_pre_field_id']]), $temp_options);
								$temp_options = str_replace(LD.'selected'.RD, ($prow['field_id_'.$row['field_pre_field_id']] == $field_data) ? ' selected="selected"' : '', $temp_options);
								$pdo .= $temp_options;
							}

							$temp_pulldown = str_replace(LD.'temp_pd_options'.RD, $pdo, $pulldown);
							$temp_chunk = str_replace(LD.'temp_pulldown'.RD, $temp_pulldown, $temp_chunk);
						}
					}
				}
				elseif ($row['field_type'] == 'multi_select' AND $multiselect != '')
				{
					if ($row['field_pre_populate'] == 'n')
					{
						$pdo = '';

						if ($row['field_required'] == 'n')
						{
							$temp_options = $multi_options;
							$temp_options = str_replace(LD.'option_name'.RD, '--', $temp_options);
							$temp_options = str_replace(LD.'option_value'.RD, '', $temp_options);
							$temp_options = str_replace(LD.'selected'.RD, '', $temp_options);
							$pdo = $temp_options;
						}

						foreach (explode("\n", trim($row['field_list_items'])) as $v)
						{
							$temp_options = $multi_options;

							$v = trim($v);
							$temp_options = str_replace(LD.'option_name'.RD, $v, $temp_options);
							$temp_options = str_replace(LD.'option_value'.RD, $v, $temp_options);
							$temp_options = str_replace(LD.'selected'.RD, (is_array($field_data) && in_array($v, $field_data)) ? ' selected="selected"' : '', $temp_options);
							$pdo .= $temp_options;
						}

						$temp_multiselect = str_replace(LD.'temp_multi_options'.RD, $pdo, $multiselect);
						$temp_chunk = str_replace(LD.'temp_multiselect'.RD, $temp_multiselect, $temp_chunk);
					}

					else
					{
						// We need to pre-populate this menu from an another channel custom field
						$pop_query = $this->EE->db->query("SELECT field_id_".$row['field_pre_field_id']." FROM exp_channel_data WHERE channel_id = ".$row['field_pre_channel_id']." AND field_id_".$row['field_pre_field_id']." != ''");

						if ($pop_query->num_rows() > 0)
						{
							$temp_options = $multi_options;
							$temp_options = str_replace(LD.'option_name'.RD, '--', $temp_options);
							$temp_options = str_replace(LD.'option_value'.RD, '', $temp_options);
							$temp_options = str_replace(LD.'selected'.RD, '', $temp_options);
							$pdo = $temp_options;

							foreach ($pop_query->result_array() as $prow)
							{
								if (trim($prow['field_id_'.$row['field_pre_field_id']]) != '')
								{
									$pretitle = substr(trim($prow['field_id_'.$row['field_pre_field_id']]), 0, 110);
									$pretitle = str_replace(array("\r\n", "\r", "\n", "\t"), " ", $pretitle);
									$pretitle = form_prep($pretitle);

									$temp_options = $multi_options;
									$temp_options = str_replace(LD.'option_name'.RD, $pretitle, $temp_options);
									$temp_options = str_replace(LD.'option_value'.RD, form_prep($prow['field_id_'.$row['field_pre_field_id']]), $temp_options);
									$temp_options = str_replace(LD.'selected'.RD, (is_array($field_data) && in_array($prow['field_id_'.$row['field_pre_field_id']], $field_data)) ? ' selected="selected"' : '', $temp_options);
									$pdo .= $temp_options;
								}
							}

							$temp_multiselect = str_replace(LD.'temp_multi_options'.RD, $pdo, $multiselect);
							$temp_chunk = str_replace(LD.'temp_multiselect'.RD, $temp_multiselect, $temp_chunk);
						}
					}
				}

				elseif ($row['field_type'] == 'checkboxes' AND $checkbox != '')
				{
					if ($row['field_pre_populate'] == 'n')
					{
						$pdo = '';

						foreach (explode("\n", trim($row['field_list_items'])) as $v)
						{
							$temp_options = $check_options;

							$v = trim($v);
							$temp_options = str_replace(LD.'option_name'.RD, $v, $temp_options);
							$temp_options = str_replace(LD.'option_value'.RD, $v, $temp_options);
							$temp_options = str_replace(LD.'checked'.RD, (is_array($field_data) && in_array($v, $field_data)) ? ' checked ' : '', $temp_options);

							$pdo .= $temp_options;
						}

						$temp_checkbox = str_replace(LD.'temp_check_options'.RD, $pdo, $checkbox);
						$temp_chunk = str_replace(LD.'temp_checkbox'.RD, $temp_checkbox, $temp_chunk);
					}

					else
					{
						// We need to pre-populate this menu from an another channel custom field
						$pop_query = $this->EE->db->query("SELECT field_id_".$row['field_pre_field_id']." FROM exp_channel_data WHERE channel_id = ".$row['field_pre_channel_id']." AND field_id_".$row['field_pre_field_id']." != ''");

						if ($pop_query->num_rows() > 0)
						{
							$pdo = '';

							foreach ($pop_query->result_array() as $prow)
							{
								$pretitle = substr(trim($prow['field_id_'.$row['field_pre_field_id']]), 0, 110);
								$pretitle = str_replace(array("\r\n", "\r", "\n", "\t"), " ", $pretitle);
								$pretitle = form_prep($pretitle);

								$temp_options = $check_options;
								$temp_options = str_replace(LD.'option_name'.RD, $pretitle, $temp_options);
								$temp_options = str_replace(LD.'option_value'.RD, form_prep($prow['field_id_'.$row['field_pre_field_id']]), $temp_options);
								$temp_options = str_replace(LD.'checked'.RD, (is_array($field_data) && in_array($prow['field_id_'.$row['field_pre_field_id']], $field_data)) ? ' checked ' : '', $temp_options);
								$pdo .= $temp_options;
							}

							$temp_checkbox = str_replace(LD.'temp_check_options'.RD, $pdo, $checkbox);
							$temp_chunk = str_replace(LD.'temp_checkbox'.RD, $temp_checkbox, $temp_chunk);
						}
					}
				}
				elseif ($row['field_type'] == 'radio' AND $radio != '')
				{
					if ($row['field_pre_populate'] == 'n')
					{
						$pdo = '';

						foreach (explode("\n", trim($row['field_list_items'])) as $v)
						{
							$temp_options = $radio_options;

							$v = trim($v);
							$temp_options = str_replace(LD.'option_name'.RD, $v, $temp_options);
							$temp_options = str_replace(LD.'option_value'.RD, $v, $temp_options);
							$temp_options = str_replace(LD.'checked'.RD, ($v == $field_data) ? ' checked ' : '', $temp_options);

							$pdo .= $temp_options;
						}

						$temp_radio = str_replace(LD.'temp_radio_options'.RD, $pdo, $radio);
						$temp_chunk = str_replace(LD.'temp_radio'.RD, $temp_radio, $temp_chunk);
					}

					else
					{
						// We need to pre-populate this menu from an another channel custom field
						$pop_query = $this->EE->db->query("SELECT field_id_".$row['field_pre_field_id']." FROM exp_channel_data WHERE channel_id = ".$row['field_pre_channel_id']." AND field_id_".$row['field_pre_field_id']." != ''");

						if ($pop_query->num_rows() > 0)
						{
							$pdo = '';

							foreach ($pop_query->result_array() as $prow)
							{
								$pretitle = substr($prow['field_id_'.$row['field_pre_field_id']], 0, 110);
								$pretitle = str_replace(array("\r\n", "\r", "\n", "\t"), " ", $pretitle);
								$pretitle = form_prep($pretitle);

								$temp_options = $radio_options;
								$temp_options = str_replace(LD.'option_name'.RD, $pretitle, $temp_options);
								$temp_options = str_replace(LD.'option_value'.RD, form_prep($prow['field_id_'.$row['field_pre_field_id']]), $temp_options);
								$temp_options = str_replace(LD.'checked'.RD, ($prow['field_id_'.$row['field_pre_field_id']] == $field_data) ? ' checked ' : '', $temp_options);
								$pdo .= $temp_options;
							}

							$temp_radio = str_replace(LD.'temp_radio_options'.RD, $pdo, $radio);
							$temp_chunk = str_replace(LD.'temp_radio'.RD, $temp_radio, $temp_chunk);
						}
					}
				}


				if ($row['field_required'] == 'y')
				{
					$temp_chunk = str_replace(LD.'temp_required'.RD, $required, $temp_chunk);
				}
				else
				{
					$temp_chunk = str_replace(LD.'temp_required'.RD, '', $temp_chunk);
				}

				if (is_array($field_data))
				{
					
				}
				else
				{
					$temp_chunk = str_replace(LD.'field_data'.RD, form_prep($field_data), $temp_chunk);					
				}
				
				$temp_chunk = str_replace(LD.'temp_date'.RD, '', $temp_chunk);
				$temp_chunk = str_replace(LD.'temp_textarea'.RD, '', $temp_chunk);
				$temp_chunk = str_replace(LD.'temp_relationship'.RD, '', $temp_chunk);
				$temp_chunk = str_replace(LD.'temp_textinput'.RD, '', $temp_chunk);
				$temp_chunk = str_replace(LD.'temp_file'.RD, '', $temp_chunk);
				$temp_chunk = str_replace(LD.'temp_file_options'.RD, '', $temp_chunk);
				
				$temp_chunk = str_replace(LD.'temp_pulldown'.RD, '', $temp_chunk);
				$temp_chunk = str_replace(LD.'temp_pd_options'.RD, '', $temp_chunk);
				$temp_chunk = str_replace(LD.'temp_multiselect'.RD, '', $temp_chunk);
				$temp_chunk = str_replace(LD.'temp_multi_options'.RD, '', $temp_chunk);
				$temp_chunk = str_replace(LD.'temp_checkbox'.RD, '', $temp_chunk);
				$temp_chunk = str_replace(LD.'temp_check_options'.RD, '', $temp_chunk);
				$temp_chunk = str_replace(LD.'temp_radio'.RD, '', $temp_chunk);
				$temp_chunk = str_replace(LD.'temp_radio_options'.RD, '', $temp_chunk);
				$temp_chunk = str_replace(LD.'calendar_link'.RD, '', $temp_chunk);
				$temp_chunk = str_replace(LD.'calendar_id'.RD, '', $temp_chunk);

				$temp_chunk = str_replace(LD.'rows'.RD, ( ! isset($row['field_ta_rows'])) ? '10' : $row['field_ta_rows'], $temp_chunk);
				$temp_chunk = str_replace(LD.'field_label'.RD, $row['field_label'], $temp_chunk);
				$temp_chunk = str_replace(LD.'field_instructions'.RD, $row['field_instructions'], $temp_chunk);
				$temp_chunk = str_replace(LD.'text_direction'.RD, $row['field_text_direction'], $temp_chunk);
				$temp_chunk = str_replace(LD.'maxlength'.RD, $row['field_maxl'], $temp_chunk);
				$temp_chunk = str_replace(LD.'field_name'.RD, 'field_id_'.$row['field_id'], $temp_chunk);
				$temp_chunk = str_replace(LD.'field_name_directory'.RD, 'field_id_'.$row['field_id'].'_directory', $temp_chunk);				

				$hidden_fields['field_ft_'.$row['field_id']] = $field_fmt;
				// $temp_chunk .= "\n<input type='hidden' name='field_ft_".$row['field_id']."' value='".$field_fmt."' />\n";

				$build .= $temp_chunk;
			}

			$tagdata = str_replace(LD.'temp_custom_fields'.RD, $build, $tagdata);
		}

		/** ----------------------------------------
		/**  Categories
		/** ----------------------------------------*/

		if (preg_match("#".LD."category_menu".RD."(.+?)".LD.'/'."category_menu".RD."#s", $tagdata, $match))
		{
			$this->category_tree_form($cat_group, $which, $deft_category, $catlist);

			if (count($this->categories) == 0)
			{
				$tagdata = str_replace ($match['0'], '', $tagdata);
			}
			else
			{
				$c = '';
				foreach ($this->categories as $val)
				{
					$c .= $val;
				}

				$match['1'] = str_replace(LD.'select_options'.RD, $c, $match['1']);
				$tagdata = str_replace ($match['0'], $match['1'], $tagdata);
			}
		}


		/** ----------------------------------------
		/**  Ping Servers
		/** ----------------------------------------*/

		if (preg_match("#".LD."ping_servers".RD."(.+?)".LD.'/'."ping_servers".RD."#s", $tagdata, $match))
		{
			$field = (preg_match("#".LD."ping_row".RD."(.+?)".LD.'/'."ping_row".RD."#s", $tagdata, $match1)) ? $match1['1'] : '';

			if ( ! isset($match1['0']))
			{
				$tagdata = str_replace ($match['0'], '', $tagdata);
			}

			$ping_servers = $this->fetch_ping_servers($which);

			if ( ! is_array($ping_servers) OR count($ping_servers) == 0)
			{
				$tagdata = str_replace ($match['0'], '', $tagdata);
			}
			else
			{
				$ping_build = '';

				foreach ($ping_servers as $val)
				{
					$temp = $field;

					$temp = str_replace(LD.'ping_value'.RD, $val['0'], $temp);
					$temp = str_replace(LD.'ping_checked'.RD, $val['1'], $temp);
					$temp = str_replace(LD.'ping_server_name'.RD, $val['2'], $temp);

					$ping_build .= $temp;
				}

				$match['1'] = str_replace ($match1['0'], $ping_build, $match['1']);
				$tagdata = str_replace ($match['0'], $match['1'], $tagdata);
			}
		}




		/** ----------------------------------------
		/**  Status
		/** ----------------------------------------*/

		if (preg_match("#".LD."status_menu".RD."(.+?)".LD.'/'."status_menu".RD."#s", $tagdata, $match))
		{
			if (isset($_POST['status']))
				$deft_status = $_POST['status'];

			if ($deft_status == '')
				$deft_status = 'open';

			if ($status == '')
				$status = $deft_status;

				/** --------------------------------
				/**  Fetch disallowed statuses
				/** --------------------------------*/

				$no_status_access = array();

				if ($this->EE->session->userdata['group_id'] != 1)
				{
					$query = $this->EE->db->query("SELECT status_id FROM exp_status_no_access WHERE member_group = '".$this->EE->session->userdata['group_id']."'");

					if ($query->num_rows() > 0)
					{
						foreach ($query->result_array() as $row)
						{
							$no_status_access[] = $row['status_id'];
						}
					}
				}

				/** --------------------------------
				/**  Create status menu
				/** --------------------------------*/

				$r = '';

				if ($status_query->num_rows() == 0)
				{
					// if there is no status group assigned, only Super Admins can create 'open' entries
					if ($this->EE->session->userdata['group_id'] == 1)
					{
						$selected = ($status == 'open') ? " selected='selected'" : '';
						$r .= "<option value='open'".$selected.">".$this->EE->lang->line('open')."</option>";
					}

					$selected = ($status == 'closed') ? " selected='selected'" : '';
					$r .= "<option value='closed'".$selected.">".$this->EE->lang->line('closed')."</option>";
				}
				else
				{
					$no_status_flag = TRUE;

					foreach ($status_query->result_array() as $row)
					{
						$selected = ($status == $row['status']) ? " selected='selected'" : '';

						if ($selected != 1)
						{
							if (in_array($row['status_id'], $no_status_access))
							{
								continue;
							}
						}

						$no_status_flag = FALSE;

						$status_name = ($row['status'] == 'open' OR $row['status'] == 'closed') ? $this->EE->lang->line($row['status']) : $row['status'];

						$r .= "<option value='".form_prep($row['status'])."'".$selected.">". form_prep($status_name)."</option>\n";
					}

					if ($no_status_flag == TRUE)
					{
						$tagdata = str_replace ($match['0'], '', $tagdata);
					}
				}


				$match['1'] = str_replace(LD.'select_options'.RD, $r, $match['1']);
				$tagdata = str_replace ($match['0'], $match['1'], $tagdata);
		}


		/** ----------------------------------------
		/**  Parse single variables
		/** ----------------------------------------*/
		foreach ($this->EE->TMPL->var_single as $key => $val)
		{
			/** ----------------------------------------
			/**  {title}
			/** ----------------------------------------*/

			if ($key == 'title')
			{
				$title = ( ! isset($_POST['title'])) ? $title : $_POST['title'];

				$tagdata = $this->EE->TMPL->swap_var_single($key, form_prep($title), $tagdata);
			}

			/** ----------------------------------------
			/**  {allow_comments}
			/** ----------------------------------------*/

			if ($key == 'allow_comments')
			{
				if ($which == 'preview')
				{
					$checked = ( ! isset($_POST['allow_comments']) OR $comment_system_enabled != 'y') ? '' : "checked='checked'";
				}
				else
				{
					$checked = ($deft_comments == 'n' OR $comment_system_enabled != 'y') ? '' : "checked='checked'";
				}

				$tagdata = $this->EE->TMPL->swap_var_single($key, $checked, $tagdata);
			}

			/** ----------------------------------------
			/**  {dst_enabled}
			/** ----------------------------------------*/

			if ($key == 'dst_enabled')
			{
				if ($which == 'preview')
				{
					$checked = (isset($_POST['dst_enabled']) && $this->EE->config->item('honor_entry_dst') == 'y') ? "checked='checked'" : '';
				}
				else
				{
					$checked = ($dst_enabled == 'y') ? "checked='checked'" : '';
				}

				$tagdata = $this->EE->TMPL->swap_var_single($key, $checked, $tagdata);
			}

			/** ----------------------------------------
			/**  {sticky}
			/** ----------------------------------------*/

			if ($key == 'sticky')
			{
				$checked = '';

				if ($which == 'preview')
				{
					$checked = ( ! isset($_POST['sticky'])) ? '' : "checked='checked'";
				}

				$tagdata = $this->EE->TMPL->swap_var_single($key, $checked, $tagdata);
			}

			/** ----------------------------------------
			/**  {url_title}
			/** ----------------------------------------*/
			if ($key == 'url_title')
			{
				$url_title = ( ! isset($_POST['url_title'])) ? $url_title : $_POST['url_title'];

				$tagdata = $this->EE->TMPL->swap_var_single($key, $url_title, $tagdata);
			}

			/** ----------------------------------------
			/**  {entry_date}
			/** ----------------------------------------*/
			if ($key == 'entry_date')
			{
				$entry_date = ( ! isset($_POST['entry_date'])) ? $this->EE->localize->set_human_time($this->EE->localize->now) : $_POST['entry_date'];

				$tagdata = $this->EE->TMPL->swap_var_single($key, $entry_date, $tagdata);
			}

			/** ----------------------------------------
			/**  {expiration_date}
			/** ----------------------------------------*/
			if ($key == 'expiration_date')
			{
				$expiration_date = ( ! isset($_POST['expiration_date'])) ? '': $_POST['expiration_date'];

				$tagdata = $this->EE->TMPL->swap_var_single($key, $expiration_date, $tagdata);
			}

			/** ----------------------------------------
			/**  {comment_expiration_date}
			/** ----------------------------------------*/
			if ($key == 'comment_expiration_date')
			{
				$comment_expiration_date = '';

				if ($which == 'preview')
				{
						$comment_expiration_date = ( ! isset($_POST['comment_expiration_date'])) ? '' : $_POST['comment_expiration_date'];
				}
				else
				{
					if ($comment_expiration > 0)
					{
						$comment_expiration_date = $comment_expiration * 86400;
						$comment_expiration_date = $comment_expiration_date + $this->EE->localize->now;
						$comment_expiration_date = $this->EE->localize->set_human_time($comment_expiration_date);
					}
				}

				$tagdata = $this->EE->TMPL->swap_var_single($key, $comment_expiration_date, $tagdata);

			}

		}
		
		// Build the form

		$data = array(
						'hidden_fields' => $hidden_fields,
						'action'		=> $RET,
						'id'			=> 'publishForm',
						'class'			=> $this->EE->TMPL->form_class,
						'enctype' 		=> 'multi'
						);

		$res  = $this->EE->functions->form_declaration($data);
		
		$res .= $misc_js;

		if ($this->EE->TMPL->fetch_param('use_live_url') != 'no')
		{
 			$res .= $url_title_js;
		}

		$res .= stripslashes($tagdata);
		$res .= "</form>";

		return $res;
	}

	/** -----------------------------
	/**  Category tree
	/** -----------------------------*/
	// This function (and the next) create a higherarchy tree
	// of categories.

	function category_tree_form($group_id = '', $action = '', $default = '', $selected = '')
	{
		// Fetch category group ID number

		if ($group_id == '')
		{
			if ( ! $group_id = $this->EE->input->get_post('group_id'))
			{
				return FALSE;
			}
		}

		// If we are using the category list on the "new entry" page
		// we need to gather the selected categories so we can highlight
		// them in the form.

		if ($action == 'preview')
		{
			$catarray = array();

			foreach ($_POST as $key => $val)
			{
				if (strpos($key, 'category') !== FALSE && is_array($val))
				{
						foreach ($val as $k => $v)
						{
							$catarray[$v] = $v;
						}
				}
			}
		}

		if ($action == 'edit')
		{
			$catarray = array();

			if (is_array($selected))
			{
				foreach ($selected as $key => $val)
				{
					$catarray[$val] = $val;
				}
			}
		}

		// Fetch category groups

		$query = $this->EE->db->query("SELECT cat_name, cat_id, parent_id
							 FROM exp_categories
							 WHERE group_id IN ('".str_replace('|', "','", $this->EE->db->escape_str($group_id))."')
							 ORDER BY parent_id, cat_order");

		if ($query->num_rows() == 0)
		{
			return FALSE;
		}

		// Assign the query result to a multi-dimensional array

		foreach($query->result_array() as $row)
		{
			$cat_array[$row['cat_id']]  = array($row['parent_id'], $row['cat_name']);
		}

		$size = count($cat_array) + 1;

		// Build our output...

		$sel = '';

		foreach($cat_array as $key => $val)
		{
			if (0 == $val['0'])
			{
				if ($action == 'new')
				{
					$sel = ($default == $key) ? '1' : '';
				}
				else
				{
					$sel = (isset($catarray[$key])) ? '1' : '';
				}

				$s = ($sel != '') ? " selected='selected'" : '';

				$this->categories[] = "<option value='".$key."'".$s.">".$val['1']."</option>\n";

				$this->category_subtree_form($key, $cat_array, $depth=1, $action, $default, $selected);
			}
		}
	}





	/** -----------------------------------------------------------
	/**  Category sub-tree
	/** -----------------------------------------------------------*/
	// This function works with the preceeding one to show a
	// hierarchical display of categories
	//-----------------------------------------------------------

	function category_subtree_form($cat_id, $cat_array, $depth, $action, $default = '', $selected = '')
	{
		$spcr = "&nbsp;";


		// Just as in the function above, we'll figure out which items are selected.

		if ($action == 'preview')
		{
			$catarray = array();

			foreach ($_POST as $key => $val)
			{
				if (strpos($key, 'category') !== FALSE && is_array($val))
				{
						foreach ($val as $k => $v)
						{
						$catarray[$v] = $v;
					}
				}
			}
		}

		if ($action == 'edit')
		{
			$catarray = array();

			if (is_array($selected))
			{
				foreach ($selected as $key => $val)
				{
					$catarray[$val] = $val;
				}
			}
		}

		$indent = $spcr.$spcr.$spcr.$spcr;

		if ($depth == 1)
		{
			$depth = 4;
		}
		else
		{
			$indent = str_repeat($spcr, $depth).$indent;

			$depth = $depth + 4;
		}

		$sel = '';

		foreach ($cat_array as $key => $val)
		{
			if ($cat_id == $val['0'])
			{
				$pre = ($depth > 2) ? "&nbsp;" : '';

				if ($action == 'new')
				{
					$sel = ($default == $key) ? '1' : '';
				}
				else
				{
					$sel = (isset($catarray[$key])) ? '1' : '';
				}

				$s = ($sel != '') ? " selected='selected'" : '';

				$this->categories[] = "<option value='".$key."'".$s.">".$pre.$indent.$spcr.$val['1']."</option>\n";

				$this->category_subtree_form($key, $cat_array, $depth, $action, $default, $selected);
			}
		}
	}




	/** ---------------------------------------------------------------
	/**  Fetch ping servers
	/** ---------------------------------------------------------------*/
	// This function displays the ping server checkboxes
	//---------------------------------------------------------------

	function fetch_ping_servers($which = 'new')
	{
		$query = $this->EE->db->query("SELECT COUNT(*) AS count FROM exp_ping_servers WHERE site_id = '".$this->EE->db->escape_str($this->EE->config->item('site_id'))."' AND member_id = '".$this->EE->session->userdata('member_id')."'");

		$member_id = ($query->row('count')  == 0) ? 0 : $this->EE->session->userdata('member_id');

		$query = $this->EE->db->query("SELECT id, server_name, is_default FROM exp_ping_servers WHERE site_id = '".$this->EE->db->escape_str($this->EE->config->item('site_id'))."' AND member_id = '$member_id' ORDER BY server_order");

		if ($query->num_rows() == 0)
		{
			return FALSE;
		}

		$ping_array = array();

		foreach($query->result_array() as $row)
		{
			if (isset($_POST['preview']))
			{
				$selected = '';
				foreach ($_POST as $key => $val)
				{
					if (strpos($key, 'ping') !== FALSE && $val == $row['id'])
					{
						$selected = " checked='checked' ";
						break;
					}
				}
			}
			else
			{
				$selected = ($row['is_default'] == 'y') ? " checked='checked' " : '';
			}


			$ping_array[] = array($row['id'], $selected, $row['server_name']);
		}


		return $ping_array;
	}

	// --------------------------------------------------------------------

	/**
	 * Combo Loaded Javascript for the Stand-Alone Entry Form
	 *
	 * Given the heafty amount of javascript needed for this form, we don't
	 * want to kill page speeds, so we're going to combo load what is needed
	 *
	 * @return void
	 */
	function saef_javascript()
	{
		$scripts = array(
				'ui'		=> array('core', 'dialog'),
				'plugins'	=> array('scrollable', 'scrollable_navigator', 
										'ee_filebrowser', 'markitup')
			);

		if ( ! defined('PATH_JQUERY'))
		{
			$type = ($this->EE->config->item('use_compressed_js') == 'n') ? 'src' : 'compressed';
			
			define('PATH_JQUERY', APPPATH.'javascript/'.$type.'/jquery/');
		}
		
		$output = '';
		
		foreach ($scripts as $key => $val)
		{
			foreach ($val as $script)
			{
				$filename = ($key == 'ui') ? 'ui.'.$script.'.js' : $script.'.js';
				
				$output .= file_get_contents(PATH_JQUERY.$key.'/'.$filename)."\n";
			}
		}

		$this->EE->output->out_type = 'cp_asset';
		$this->EE->output->set_header("Content-Type: text/javascript");
		
		$this->EE->output->set_header('Content-Length: '.strlen($output));
		$this->EE->output->set_output($output);
	}

}
// END CLASS

/* End of file mod.channel_standalone.php */
/* Location: ./system/expressionengine/modules/channel/mod.channel_standalone.php */