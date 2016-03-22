<?php

/**	
 *	@author		HitkoDev http://hitko.eu/videobox
 *	@copyright	Copyright (C) 2016 HitkoDev All Rights Reserved.
 *	@license	http://www.gnu.org/licenses/gpl-3.0.html GNU/GPL
 *
 *	This program is free software: you can redistribute it and/or modify
 *	it under the terms of the GNU General Public License as published by
 *	the Free Software Foundation, either version 3 of the License, or
 *	any later version.
 *
 *	This program is distributed in the hope that it will be useful,
 *	but WITHOUT ANY WARRANTY; without even the implied warranty of
 *	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 *	GNU General Public License for more details.
 *
 *	You should have received a copy of the GNU General Public License
 *	along with this program. If not, see <http://www.gnu.org/licenses/>
 */
 
require_once(dirname(dirname(__FILE__)) . '/adapters/adapter.class.php');

class Videobox {
	
	public $modx;
    public $config = array();
	public $gallery = -1;

	function __construct(modX &$modx, array $config = array()){
		$this->modx =& $modx;
		$this->setConfig($config);
		
		$this->pages = array();
		if(isset($_GET['vbpages'])){
			$p = explode(',', rawurldecode($_GET['vbpages']));
			foreach($p as $page){
				$this->pages[] = (int) $page;
			}
		}
	}
	
	function setConfig(array $config = array()){
		$this->config = array_merge(array(
			'assets_url' => $this->modx->getOption('videobox.assets_url', null, $this->modx->getOption('assets_url').'components/videobox/'),
			'assets_path' => $this->modx->getOption('videobox.assets_path', null, MODX_ASSETS_PATH.'components/videobox/'),
			'core_path' => $this->modx->getOption('videobox.core_path', null, $this->modx->getOption('core_path').'components/videobox/')
		), $config);
		$this->processors = null;
	}
	
	function getProcessors(){
		if($this->processors) return $this->processors;
		
		$processors = array_map('trim', explode(',', $this->config['processors']));
		$this->processors = array();
		foreach($processors as $key => $processor){
			$p = $this->modx->getObject('modSnippet', array('name' => $processor));
			if($p) $this->processors[] = $processor;
		}
		
		return $this->processors;
	}
	
	function getVideo(array $props = array()){
		$prop = array_merge($this->config, $props);
		foreach($this->getProcessors() as $processor){
			$v = $this->modx->runSnippet($processor, $prop);
			if($v) return $v;
		}
		return false;
	}
	
	function loadAssets(){
		$this->modx->regClientCSS($_GET['dev'] ? '/Videobox-js/dist/videobox.css' : $this->config['assets_url'] . 'css/videobox.min.css');
		$this->modx->regClientScript($this->config['assets_url'] . 'js/jquery.min.js');
		$this->modx->regClientScript($this->config['assets_url'] . 'js/web-animations.min.js');
		$this->modx->regClientScript($_GET['dev'] ? '/Videobox-js/dist/videobox.js' : $this->config['assets_url'] . 'js/videobox.min.js');
	}
	
	function setCache($key, $data){
		if(!$this->config['cache']) return;
		$this->modx->cacheManager->set($key, $data, 0);
	}
	
	function getCache($key){
		if(!$this->config['cache']) return '';
		return $this->modx->cacheManager->get($key);
	}
	
	function parseTemplate($tpl, $properties = array()){
		return $this->modx->parseChunk($tpl, array_merge($this->config, $properties));
	}
	
	function getPage(){
		if(isset($this->pages[$this->gallery])) return $this->pages[$this->gallery];
		return 0;
	}
	
	function htmldec($string){
		return str_replace(array('&lt;', '&gt;', '&quot;'), array('<', '>', '"'), $string);
	}
	
	function htmlenc($string){
		return str_replace(array('<', '>', '"'), array('&lt;', '&gt;', '&quot;'), $string);
	}
	
	function makePath($items = array(), $closed = false){
		$del = '/';
		$path = '';
		foreach($items as $item){
			$path .= rtrim($item, $del) . $del;
		}
		if(!$closed) $path = rtrim($path, $del);
		return $path;
	}

	function videoThumbnail($video, $no_border = false, $n = 0) {
		// Prevent infinite loop
		if($n > 1) return '';
		
		$tWidth = $this->config['tWidth'];
		$tHeight = $this->config['tHeight'];
		
		// Get name suffixes
		$name = '';
		if($no_border){
			$name .= '-no_border';
		} else {
			$name .= '-'.$tWidth.'-'.$tHeight;
		}
		
		// If $video is a VideoboxAdapter object, get its data, otherwise get nobg data
		if($video instanceof VideoboxAdapter){
			$nobg = 'nobg_' . $video->type;
			$hash = md5($video->id . $name);
			$img = $video->getThumb();
		} else {
			$nobg = $video;
			$hash = md5($video . $name);
			$img = array($this->config['assets_path'] . 'images/'.$nobg.'.png', IMAGETYPE_PNG);
		}
		
		if(!is_dir($this->config['assets_path'] . 'cache')) mkdir($this->config['assets_path'] . 'cache');
		
		$target = $this->config['assets_path'] . 'cache/'.$hash.'.jpg';
		
		$img_hash = md5($target);
		
		$ret = $this->getCache($img_hash);
		if($ret) return $ret;
		
		try{
            $target_info = getimagesize($target);
        } catch (Exception $ex){
            
        }
		if($target_info){
			$ret = array($this->config['assets_url'] . 'cache/'.$hash.'.jpg', $target_info[0], $target_info[1]);
			$this->setCache($img_hash, $ret);
			return $ret;
		}
			
        $tmpn = tempnam($this->config['assets_url'] . 'cache/', 'vb_');
        copy($img[0], $tmpn);
		
		if(!extension_loaded('imagick')){
		
			try {
				switch($img[1]){
					case IMAGETYPE_JPEG: 
						$src_img = imagecreatefromjpeg($tmpn);
						break;
					case IMAGETYPE_PNG: 
						$src_img = imagecreatefrompng($tmpn);
						break;
					case IMAGETYPE_GIF: 
						$src_img = imagecreatefromgif($tmpn);
						break;
					default:
                        unlink($tmpn);
						return $this->videoThumbnail($nobg, $tWidth, $tHeight, $no_border, $n + 1);
				}
			} catch (Exception $e) {
                unlink($tmpn);
				return $this->videoThumbnail($nobg, $tWidth, $tHeight, $no_border, $n + 1);
			}
			if(!$src_img) return $this->videoThumbnail($nobg, $tWidth, $tHeight, $no_border, $n + 1);
			
			$imagedata = array(imagesx($src_img), imagesy($src_img));
			
			$b_t = 0;
			$b_b = 0;
			$b_l = 0;
			$b_r = 0;

			// Remove border added by video provider
			if($imagedata[0] && $imagedata[1]){

				if($imagedata[0]<=1920 && $imagedata[1]<=1080){
				
					for($y = 3; $y < $imagedata[1]; $y++) {
						for($x = 3; $x < $imagedata[0]; $x++) {
							if($this->_chkB(_gdRGB($src_img, $x, $y))) break 2;
						}
						$b_t = $y;
					}

					for($y = $imagedata[1]-4; $y >= 0; $y--) {
						for($x = 3; $x < $imagedata[0] - 3; $x++) {
							if($this->_chkB(_gdRGB($src_img, $x, $y))) break 2;
						}
						$b_b = $imagedata[1] - 1 - $y;
					}

					for($x = 3; $x < $imagedata[0]; $x++) {
						for($y = 3; $y < $imagedata[1]; $y++) {
							if($this->_chkB(_gdRGB($src_img, $x, $y))) break 2;
						}
						$b_l = $x;
					}

					for($x = $imagedata[0]-4; $x >= 0; $x--) {
						for($y = 3; $y < $imagedata[1]; $y++) {
							if($this->_chkB(_gdRGB($src_img, $x, $y))) break 2;
						}
						$b_r = $imagedata[0] - 1 - $x;
					}
				
				}

			} else {
                unlink($tmpn);
				return $this->videoThumbnail($nobg, $tWidth, $tHeight, $no_border, $n + 1);
			}
			
			$imagedata[0] -= $b_l + $b_r;
			$imagedata[1] -= $b_t + $b_b;
			
			// Copy and crop
			if($no_border){
				$tWidth = $imagedata[0];
				$tHeight = $imagedata[1];
				$newimg = imagecreatetruecolor($tWidth, $tHeight);
				$black = imagecolorallocate($newimg, 0, 0, 0);
				imagefilledrectangle($newimg, 0, 0, $tWidth, $tHeight, $black);
				imagecopyresampled($newimg, $src_img, 0, 0, $b_l, $b_t, $tWidth, $tHeight, $tWidth, $tHeight);
			} else {
			
				// Calculate new size and offset
				$new_w = $imagedata[0];
				$new_h = $imagedata[1];		
				
				$new_w = ($tHeight*$new_w) / $new_h;
				$new_h = $tHeight;
				if($new_w > $tWidth){
					$new_h = ($tWidth*$new_h) / $new_w;
					$new_w = $tWidth;
				}		
				
				$new_w = (int)$new_w;
				$new_h = (int)$new_h;
				$off_w = (int)(($tWidth - $new_w)/2);
				$off_h = (int)(($tHeight - $new_h)/2);
				$newimg = imagecreatetruecolor($tWidth, $tHeight);
				$black = imagecolorallocate($newimg, 0, 0, 0);
				imagefilledrectangle($newimg, 0, 0, $tWidth, $tHeight, $black);
				imagecopyresampled($newimg, $src_img, $off_w, $off_h, $b_l, $b_t, $new_w, $new_h, $imagedata[0], $imagedata[1]);
			}
			
			// Save the image and return
			imagejpeg($newimg, $target.'__', 95);
			imagedestroy($src_img);
			imagedestroy($newimg);
			
		} else {
            
            try {
                $imgM = @new Imagick($tmpn);
                $imagedata = array($imgM->getImageWidth(), $imgM->getImageHeight());
            } catch(Exception $ex) {
                $imagedata = array(0, 0);
            }
			
			$b_t = 0;
			$b_b = 0;
			$b_l = 0;
			$b_r = 0;

			// Remove border added by video provider
			if($imagedata[0] && $imagedata[1]){

				if($imagedata[0]<=1920 && $imagedata[1]<=1080){
				
					for($y = 3; $y < $imagedata[1]; $y++) {
						for($x = 3; $x < $imagedata[0]; $x++) {
							if($this->_chkB($imgM->getImagePixelColor($x, $y)->getColor())) break 2;
						}
						$b_t = $y + 1;
					}

					for($y = $imagedata[1]-4; $y >= 0; $y--) {
						for($x = 3; $x < $imagedata[0] - 3; $x++) {
							if($this->_chkB($imgM->getImagePixelColor($x, $y)->getColor())) break 2;
						}
						$b_b = $imagedata[1] - $y;
					}

					for($x = 3; $x < $imagedata[0]; $x++) {
						for($y = 3; $y < $imagedata[1]; $y++) {
							if($this->_chkB($imgM->getImagePixelColor($x, $y)->getColor())) break 2;
						}
						$b_l = $x + 1;
					}

					for($x = $imagedata[0]-4; $x >= 0; $x--) {
						for($y = 3; $y < $imagedata[1]; $y++) {
							if($this->_chkB($imgM->getImagePixelColor($x, $y)->getColor())) break 2;
						}
						$b_r = $imagedata[0] - $x;
					}
				
				}

			} else {
                unlink($tmpn);
				return $this->videoThumbnail($nobg, $tWidth, $tHeight, $no_border, $n + 1);
			}
			
			$imagedata[0] -= $b_l + $b_r;
			$imagedata[1] -= $b_t + $b_b;
			
			$imgM->cropImage($imagedata[0], $imagedata[1], $b_l, $b_t);
			if($no_border){
				$tWidth = $imagedata[0];
				$tHeight = $imagedata[1];
			} else {
				
				// Calculate new size and offset
				$new_w = $imagedata[0];
				$new_h = $imagedata[1];		
				
				$new_w = ($tHeight*$new_w) / $new_h;
				$new_h = $tHeight;
				if($new_w > $tWidth){
					$new_h = ($tWidth*$new_h) / $new_w;
					$new_w = $tWidth;
				}		
				
				$new_w = (int)$new_w;
				$new_h = (int)$new_h;
				$off_w = (int)(($tWidth - $new_w)/2);
				$off_h = (int)(($tHeight - $new_h)/2);
				
				$imgM->setImageBackgroundColor(new ImagickPixel("rgb(0, 0, 0)"));
				$imgM->resizeImage($new_w, $new_h, imagick::FILTER_CATROM, 1);
				$imgM->extentImage($tWidth, $tHeight, -$off_w, -$off_h);
			}
			$imgM->setImageFormat('jpeg');
			$imgM->setImageCompressionQuality(95);
			$imgM->stripImage();
			$imgM->writeImage($target.'__');
			
		}
		rename($target.'__', $target);
		$ret = array($this->config['assets_url'] . 'cache/'.$hash.'.jpg', $tWidth, $tHeight);
		$this->setCache($img_hash, $ret);
        unlink($tmpn);
		return $ret;
	}
	
	function pagination($total, $current, $perPage){
		global $modx;
		if($perPage < 1) return '';
		if($total < $perPage) return '';
		$pages = floor(($total - 1) / $perPage + 1);
		$output = '';
		$id = $modx->resource->get('id');
		$que = $_GET;
		$rq = trim($modx->getOption('request_param_id'));
		$ra = trim($modx->getOption('request_param_alias'));
		if($rq) unset($que[$rq]);
		if($ra) unset($que[$ra]);
		unset($que['vbpages']);
		$pref = '';
		$i = 0;
		for(; $i < $this->gallery; $i++) $pref .= (isset($this->pages[$i]) ? $this->pages[$i] : 0) . ',';
		$post = '';
		$i++;
		for(; $i < count($this->pages); $i++) $post .= ',' . (isset($this->pages[$i]) ? $this->pages[$i] : 0);
		for($i = 0; $i < $pages; $i++){
			$pg = preg_replace("/(^,)|((?<=,),+)|((?<=0)0+)|((,|,0)+$)/m", '', $pref . $i . $post);	//	clean 1) leading comas, 2) multiple comas, 3) multiple zeros, 4) trailing comas and zeros
			$output .= '<li '.($i == $current ? 'class="active"' : '').'><a href="'.$modx->makeUrl($id, '', ($pg ? array_merge($que, array('vbpages' => $pg)) : $que)).'">'.($i+1).'</a></li>';
		}
		return '<ul class="pagination">'.$output.'</ul>';
	}
	
	protected function _gdRGB($img, $x, $y){
		$rgb = imagecolorat($img, $x, $y);
		$r = ($rgb >> 16) & 0xFF;
		$g = ($rgb >> 8) & 0xFF;
		$b = $rgb & 0xFF;
		return array(
			'r' => $r,
			'g' => $g,
			'b' => $b,
			'a' => 0
		);
	}
	
	// calculate & check luminosity (black border detection)
	protected function _chkB($rgb){
		
		$var_R = ($rgb['r'] / 255);
		$var_G = ($rgb['g'] / 255);
		$var_B = ($rgb['b'] / 255);

		$var_R = ($var_R > 0.04045) ? pow((($var_R + 0.055)/1.055), 2.4) : $var_R/12.92;
		$var_G = ($var_G > 0.04045) ? pow((($var_G + 0.055)/1.055), 2.4) : $var_G/12.92;
		$var_B = ($var_B > 0.04045) ? pow((($var_B + 0.055)/1.055), 2.4) : $var_B/12.92;
		
		$y = $var_R * 0.2126 + $var_G * 0.7152 + $var_B * 0.0722;
		$y = ($y > 0.008856) ? pow($y, 1/3) : 7.787*$y;
		$y = 116*$y;
		
		return $y > 20;
	}
}
?>