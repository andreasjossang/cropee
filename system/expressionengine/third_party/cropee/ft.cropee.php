<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Cropee Fieldtype
 *
 * This file must be in your /system/third_party/structure directory of your ExpressionEngine installation
 *
 * @package             Cropee for EE2
 * @author              Andreas Jøssang (andreas@noop.no)
 * @link                http://noop.no
 */

require_once PATH_THIRD.'assets/ft.assets.php';

define('CROPEE_CONFIG_PREFIX','cropee_');
define('CROPEE_VAR_PREFIX','image_');

class Cropee_ft extends EE_Fieldtype {

	var $info = array(
		'name'		=> 'Cropee',
		'version'	=> '1.0'
	);

	var $has_array_data = TRUE;

	private $cache = array();

	private $memory_limit = "128M";
	private $image_permissions = 0644;
	private $dir_permissions = 0777;
	private $unique = "filename";
	private $cache_dir = "";
	private $cache_path = "";


	/**
	 * Constructor
	 */
	function __construct()
	{
		parent::__construct();

		# image permissions
		if(($value = $this->EE->config->item(CROPEE_CONFIG_PREFIX . 'memory_limit')) != FALSE)
			$this->memory_limit = $value;

		# image permissions
		if(($value = $this->EE->config->item(CROPEE_CONFIG_PREFIX . 'image_permissions')) != FALSE && is_numeric($value))
			$this->image_permissions = $value;

		# directory permissions
		if(($value = $this->EE->config->item(CROPEE_CONFIG_PREFIX . 'dir_permissions')) != FALSE && is_numeric($value))
			$this->dir_permissions = $value;

		# unique cache name [hash or filename]
		if(($value = $this->EE->config->item(CROPEE_CONFIG_PREFIX . 'unique')) != FALSE && $value)
			$this->unique = $value;

		# cache directory
		if(!($this->cache_dir = $this->EE->config->item(CROPEE_CONFIG_PREFIX . 'unique')))
		{
			$this->cache_dir = $this->EE->config->slash_item('theme_folder_path');
			if($this->cache_dir != '' && ($i = strrpos($this->cache_dir,'/',-2)) > 0)
				$this->cache_dir = substr($this->cache_dir,0,$i);
			$this->cache_dir .= "/images/cropee/";
		}

		# cache url
		if(!($this->cache_url = $this->EE->config->item(CROPEE_CONFIG_PREFIX . 'unique')))
		{
			$this->cache_url = $this->EE->config->slash_item('theme_folder_url');
			if($this->cache_url != '' && ($i = strrpos($this->cache_url,'/',-2)) > 0)
				$this->cache_url = substr($this->cache_url,0,$i);
			$this->cache_url .= "/images/cropee/";
		}
	}

	/**
	 * Install Fieldtype
	 *
	 * @access	public
	 * @return	default global settings
	 *
	 */
	function install()
	{
		return array();
	}

	
	/**
	 * Display Global Settings
	 *
	 * @access	public
	 * @return	form contents
	 *
	 */
	function display_global_settings()
	{
		return;
	}

	
	/**
	 * Save Global Settings
	 *
	 * @access	public
	 * @return	global settings
	 *
	 */
	function save_global_settings()
	{
		return array_merge($this->settings, $_POST);
	}


	/**
	 * Display Settings Screen
	 *
	 * @access	public
	 * @return	field html
	 *
	 */
	function display_settings($data)
	{
		$this->EE->lang->loadfile('cropee');

		# upload dirs
		$data['filedirs'] = (isset($data['filedirs'])) ? $data['filedirs'] : ''; 

		$upload_prefs = $this->upload_prefs();
		$field = "<div class=\"assets-filedirs\">".
					"<div class=\"assets-all\">" .
						"<label>" . form_checkbox('cropee_filedirs', 'all', ($data['filedirs'] == 'all'), 'onchange="Assets.onAllFiledirsChange(this)"') . "&nbsp;&nbsp;" . lang('all') . "</label>" .
					"</div>" .
					"<div class=\"assets-list\">";
		if(!$upload_prefs)
			$field .= lang('no_file_upload_directories');
		else
		{
			foreach($upload_prefs as $filedir)
				$field .= "<label>" . form_checkbox('cropee_filedirs[]', $filedir['id'], ($data['filedirs'] == 'all' || in_array($filedir['id'], $data['filedirs'])), ($data['filedirs'] == 'all' ? 'disabled="disabled"' : '')) . "&nbsp;&nbsp; " . $filedir['name'] . "</label><br/>";
		}
		$field .= "</div" . "</div>";
		$this->EE->table->add_row(lang('filedirs', 'filedirs'), $field);

		# width and height
		foreach(array('width', 'height') as $key)
		{
			$data[$key] = (isset($data[$key])) ? $data[$key] : ''; 
			
			$field = form_input('cropee_' . $key, $data[$key]);
			$this->EE->table->add_row(lang($key, $key), $field);
		}
	}


	/**
	 * Save Settings
	 *
	 * @access	public
	 * @return	field settings
	 *
	 */
	function save_settings($data)
	{
		#temp inspect
		#$assets = new Assets_ft();
		#$data = $assets->save_settings($data);
		#print_r($data);

		# override
		$data['field_type'] = 'cropee';

		$data['filedirs'] = $this->EE->input->post('cropee_filedirs');
		$data['width'] = $this->EE->input->post('cropee_width');
		$data['height'] = $this->EE->input->post('cropee_height');
		return $data;
	}
	
	
	/**
	 * Display Field on Publish
	 *
	 * @access	public
	 * @param	existing data
	 * @return	field html
	 *
	 */
	function display_field($data)
	{
		# load our language file as we have field labels to show
		$this->EE->lang->loadfile('cropee');

		# assets css and javascript resources
		$this->assets_include_resources();

		# css and javascript
		$this->include_css('cropee.css');
		$this->include_js('jquery.imagesloaded.js');
		$this->include_js('cropee.js');

		# constants
		$wrap_width = 22;
		$border_width = 15;

		$field_id = 'field_id_'.$this->field_id;
		$image_src = $this->_theme_url().'images/empty.png';

		# form fields
		$form = "<div class=\"cropee-field\" style=\"width:".($this->settings['width']+($wrap_width*2))."px;\">" .
					form_input(array('type' => 'hidden', 'name' => $field_id, 'id' => $field_id, 'value' => $data)) .
					"<div class=\"wrap\">" .
						"<div class=\"frame\" style=\"height:".($this->settings['height']+($border_width*2))."px;width:".($this->settings['width']+($border_width*2))."px;\">" .
							"<div class=\"image\" tabindex=\"0\" style=\"height:".$this->settings['height']."px;width:".$this->settings['width']."px;\"><img src=\"".$image_src."\"></div>" .
							"<div class=\"left\"></div>" .
							"<div class=\"top\"></div>" . 
							"<div class=\"right\"></div>" .
							"<div class=\"bottom\"></div>" .
							"<a class=\"zoom plus\"></a>" .
							"<a class=\"zoom minus\"></a>" .
						"</div>" .
					"</div>" .
					form_button(array('name' => 'button', 'class' => 'select', 'value' => 'true', 'content' => 'Select')) .
					form_button(array('name' => 'button', 'class' => 'info', 'value' => 'true', 'content' => 'Info')) .
					form_button(array('name' => 'button', 'class' => 'clear', 'value' => 'true', 'content' => 'Remove')) .
				"</div>";

		# javascript field initializion and params in json.
		$this->insert_js('new cropee_field(jQuery("#'.$field_id.'"), "'.$this->field_name.'", '.$this->EE->javascript->generate_json(array(
				'file_dirs' => $this->settings['filedirs'],
				'dir_prefs' => $this->upload_prefs(),
				'width'  => intval($this->settings['width']),
				'height' => intval($this->settings['height']),
				), TRUE).');');

		return $form;
	}


	/**
	 * Validate field input
	 *
	 * @access	public
	 * @param	submitted field data
	 * @return	TRUE or an error message
	 *
	 */
	function validate($data)
	{
		return TRUE;
	}
	

	/**
	 * Save Data
	 *
	 * @access	public
	 * @param	submitted field data
	 * @return	string to save
	 *
	 */
	function save($data)
	{
		return $data;
	}


	/**
	 * Replace tag
	 *
	 * @access	public
	 * @param	field data
	 * @param	field parameters
	 * @param	data between tag pairs
	 * @return	replacement text
	 *
	 */
	function replace_tag($data, $params = array(), $tagdata = FALSE)
	{
		# no image, just return
		if(empty($data))
			return;

		# cache base
		list($cache_dir, $cache_baseurl) = $this->get_cache_perfs($params);

		# parse data
		@list($path, $zoom, $x, $y) = explode('|', $data);

		# lookup filename
		list($filename, $url) = $this->resolve_image_path($path);
		if($filename && ($image_pathinfo = @pathinfo($filename)))
		{
			$width = $this->settings['width'];
			$height = $this->settings['height'];

			if($zoom) $zoom = floatval($zoom);
			if($x) $x = floatval($x);
			if($y) $y = floatval($y);

			# explicit given size
			if(!empty($params['width']) && ($_width = intval($params['width'])) > 0)
			{
				$height	= round($height *  $_width / $width);
				$width = $_width;
			}
			else if(!empty($params['height']) && ($_height = intval($params['height'])) > 0)
			{
				$width	= round($width *  $_height / $height);
				$height = $_height;
			}

			# image properties
			$image = array(
				'field_path' => $filename,
				'field_url' => $url,
				'width' => $width,
				'height' => $height,
				'alt' => '',
				'author' => '',
				'alt' => '',);

			# lookup assosiated asset fields
			if(($asset = $this->assets_get_info($path)))
			{
				$image['author'] = $asset['author'];
			}

			# cache filename
			$unique = empty($params['unique']) ? $this->unique : $params['unique'];
			if($unique == 'hash')
				$unique_filename = md5($filename . $zoom . $width . $height . $x . $y) . '.' . $image_pathinfo['extension'];
			else
				$unique_filename = strtolower(preg_replace('/[^a-z0-9]/i','_', $image_pathinfo['filename'] . '_' . $width . '_' . $height . '_' . $zoom . 'x' . $x . 'x' . $y)) . '.' . $image_pathinfo['extension'];

			$cache_filename = $cache_dir . $unique_filename;
			$cache_url = $cache_baseurl . $unique_filename;

			# use cache or resample image
			if(/*(file_exists($cache_filename) && filemtime($cache_filename) > filemtime($filename)) ||*/
			   ($this->resample_image($cache_filename, $filename, $this->settings['width'], $this->settings['height'], $zoom, $x, $y)))
			{
				$image['path'] = $cache_filename;
				$image['url'] = $cache_url;
			}
		}

		# tag pair
		if($tagdata !== FALSE)
		{
			$image['org_filename'] = $path;
			$image['zoom'] = $zoom;
			$image['x'] = $x;
			$image['y'] = $y;

			$cropee_vars = array();
			foreach($image as $var => $val)
				$cropee_vars[CROPEE_VAR_PREFIX . $var] = $val;

			$tagdata = $this->EE->functions->prep_conditionals($tagdata, $cropee_vars);
			$tagdata = $this->EE->functions->var_swap($tagdata, $cropee_vars);
		}
		else
		{
			# build image element
			$tagdata = '';

			$class = empty($params['class']) ? '' : (' class="'.$params['class'].'"');
			$href = empty($params['href']) ? '' : $params['href'];
			$alt = empty($params['alt']) ? '' : $params['alt'];

			if(!empty($image['author']))
			{
				if($alt) $alt .= "\n";
				$alt .= (empty($params['author_label']) ? '' : $params['author_label']) . $image['author'];
			}
			if($href)
			{
				$tagdata = '<a href="'.$href.'"'.$class.">\n";
				$class='';
			}
			$tagdata .= '<img src="'.$image['url'].'"'.$class.' style="width:'.$image['width'].'px;height:'.$image['height'].'px" alt="'.$alt.'"'.($alt ? (' title="'.$alt.'"') : '')."\" />\n";

			if($href)
				$tagdata .= "</a>\n";
		}
		return $tagdata;
	}

	/**
	 * resample_image
	 */
	private function resample_image($cache_filename, $filename, $width, $height, $zoom, $x, $y)
	{
		# ajust memory limit (if givn and not allready done)
		if(!isset($this->cache['memory_limit']) && $this->memory_limit)
		{
			$this->cache['memory_limit'] = $this->memory_limit;
			@ini_set("memory_limit", $this->memory_limit);
		}

		# get image info
		if(!($info = @getimagesize($filename)))
			return false;
		list($src_width, $src_height, $type) = $info;
		$src_x=0; $src_y=0;

		# open source image
		switch($type)
		{
			case IMAGETYPE_GIF:
				$image = @imagecreatefromgif($filename);
				break;
			case IMAGETYPE_JPEG:
				$image = @imagecreatefromjpeg($filename);
				break;
			case IMAGETYPE_PNG:
				$image = @imagecreatefrompng($filename);
				break;
			default:
				return false;
		}

		# create destination image
		$image_resampled = imagecreatetruecolor($width, $height);
				   
		if(($type == IMAGETYPE_GIF) || ($type == IMAGETYPE_PNG))
		{
			$transparent_index = @imagecolortransparent($image);
	  
			# specific transparent color
			if($transparent_index >= 0)
			{
				# get the original image's transparent color's RGB values
				$transparent_color  = imagecolorsforindex($image, $transparent_index);
	  
				# allocate the same color in the new image resource
				$transparent_index = imagecolorallocate($image_resampled, $transparent_color['red'], $transparent_color['green'], $transparent_color['blue']);
	  
				# completely fill the background of the new image with allocated color.
				imagefill($image_resampled, 0, 0, $transparent_index);
	  
				# set the background color for new image to transparent
				imagecolortransparent($image_resampled, $transparent_index);
			}

			# always make a transparent background color for pngs
			elseif($type == IMAGETYPE_PNG)
			{
				# turn off transparency blending (temporarily)
				imagealphablending($image_resampled, false);
	  
				# create a new transparent color for image
				$color = imagecolorallocatealpha($image_resampled, 0, 0, 0, 127);
	  
				# completely fill the background of the new image with allocated color
				imagefill($image_resampled, 0, 0, $color);
	  
				# restore transparency blending
				imagesavealpha($image_resampled, true);
			}
		}

		# explicit given scale and crop
		if($zoom)
		{
			# x offsett
			if($x)
			{
				$src_x = $x * $src_width / 100;
				$x=0;
			}

			# y offsett
			if($y)
			{
				$src_y = $y * $src_height / 100;
				$y=0;
			}

			# zoom
			$src_width = $width * 100 / $zoom;
			$src_height = $height * 100 / $zoom;
		}
		# auto crop
		else
		{
			if($src_width > $src_height)
			{
				$_height = round(($height / $width) * $src_width);
				$_width = $src_width;
				if($_height > $src_height)
				{
					$_width = round(($width / $height) * $src_height);
					$_height = $src_height;
				}
			}
			else
			{
				$_width = round(($width / $height) * $src_height);
				$_height = $src_height;
				if($_width > $src_width)
				{
					$_height = round(($height / $width) * $src_width);
					$_width = $src_width;
				}
			}

			$src_x = round(($src_width - $_width) / 2);
			$src_y = round(($src_height - $_height) / 2);

			$src_width = $_width;
			$src_height = $_height;

		}

		# resample
		imagecopyresampled($image_resampled, $image, $x, $y, $src_x, $src_y, $width, $height, $src_width, $src_height);

		/*if($greyscale)
		{
			for($c=0;$c<256;$c++)
				$palette[$c] = imagecolorallocate($image_resampled,$c,$c,$c);
						
			for($y=0;$y<$final_height;$y++)
			{
				for ($x=0;$x<$final_width;$x++)
				{
					$rgb = imagecolorat($image_resampled,$x,$y);
					$r = ($rgb >> 16) & 0xFF;
					$g = ($rgb >> 8) & 0xFF;
					$b = $rgb & 0xFF;
					$gs = $this->yiq($r,$g,$b);
					imagesetpixel($image_resampled,$x,$y,$palette[$gs]);
				}
			}
		}*/

		# store
		switch($type)
		{
			case IMAGETYPE_GIF:
				imagegif($image_resampled, $cache_filename);
				break;
			case IMAGETYPE_JPEG:
				imagejpeg($image_resampled, $cache_filename, 100);
				break;
			case IMAGETYPE_PNG:
				imagepng($image_resampled, $cache_filename);
				break;
			default:
				return false;
		}
		@chmod($cache_filename, $this->image_permissions);

		# finally return
		return true;
	}

	/**
	 * get_cache_perfs
	 */
	private function get_cache_perfs($params)
	{
		$cache_dir = empty($params['cache_dir']) ? $this->cache_dir : $this->EE->functions->remove_double_slashes($params['cache_dir'].'/');
		$cache_url = empty($params['cache_url']) ? $this->cache_url : $this->EE->functions->remove_double_slashes($params['cache_url'].'/');

		# verify
		if(!is_dir($cache_dir))
		{
			# make the directory if we can 
			if(!mkdir($cache_dir, $this->dir_permissions, true))
				;
				#$this->EE->TMPL->log_item("Could not create cache directory! Please create the cache directory ".$cache_dir.".");
		}
		return array($cache_dir, $cache_url);
	}

	/**
	 * resolve_image_path
	 */
	private function resolve_image_path($path)
	{
		if(preg_match("/{filedir_(\d+)}/", $path, $_))
		{
			if(($filedir_prefs = $this->upload_prefs($_[1])))
			{
				$path = substr($path,strlen($_[0]));
				return array(
					$filedir_prefs['path'] . $path,
					$filedir_prefs['url'] . $path);
			}
		}
		return array(null, null);
	}

	/**
	 * upload_prefs
	 */
	private function upload_prefs($id=null)
	{
		if(!array_key_exists('upload_prefs', $this->cache))
		{
			# get all the file upload directories
			$upload_prefs = $this->EE->db->select('id, server_path as path, url, name')->from('upload_prefs')
			                         ->where('site_id', $this->EE->config->item('site_id'))
			                         ->get();
			$dir_prefs = array();
			foreach($upload_prefs->result_array() as $dir)
				$dir_prefs[$dir['id']] = $dir;

			$this->cache['upload_prefs'] = $dir_prefs;
		}

		if($id)
			return isset($this->cache['upload_prefs'][$id]) ? $this->cache['upload_prefs'][$id] : null;
		else
			return $this->cache['upload_prefs'];
	}

	/**
	 * assets helpers
	 */
	private function assets_include_resources()
	{
		$assets_helper = get_assets_helper();
		$assets_helper->include_sheet_resources();
	}

	private function assets_get_info($file_path)
	{
		# get all the file upload directories
		$query = $this->EE->db->select('*')->from('assets')
		                         ->where('file_path', $file_path)
		                         ->get();
		return $query->row_array();
	}


	/**
	 * helpers
	 */
	private function _theme_url()
	{
		if(!isset($this->cache['theme_url']))
		{
			$theme_folder_url = defined('URL_THIRD_THEMES') ? URL_THIRD_THEMES : $this->EE->config->slash_item('theme_folder_url').'third_party/';
			$this->cache['theme_url'] = $theme_folder_url.'cropee/';
		}
		return $this->cache['theme_url'];
	}
	
	private function include_css()
	{
		foreach (func_get_args() as $file)
		{
			$this->EE->cp->add_to_head('<link rel="stylesheet" type="text/css" href="'.$this->_theme_url().'styles/'.$file.'" />');
		}
	}

	private function include_js()
	{
		foreach (func_get_args() as $file)
		{
			$this->EE->cp->add_to_foot('<script type="text/javascript" src="'.$this->_theme_url().'scripts/'.$file.'"></script>');
		}
	}	
	
	private function insert_js($js)
	{
		$this->EE->cp->add_to_foot('<script type="text/javascript">'.$js.'</script>');
	}	
}