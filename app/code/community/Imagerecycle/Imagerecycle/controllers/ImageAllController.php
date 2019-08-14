<?php
class Imagerecycle_Imagerecycle_ImageAllController extends Mage_Core_Controller_Front_Action {        
 
    public function indexAction() {
		$id = $this->getRequest()->getParam('id');
		if($id == 'cronurl')
		{
			if( Mage::getStoreConfig('mageio_compress_auto')){
				$now =  time();
				if(Mage::getStoreConfig('mageio_autosettig') == 'On')
				{
					$ao_lastRun = Mage::getStoreConfig('mageio_runtime');
					$diff = $now - $ao_lastRun;
					if($now - $ao_lastRun < 8000 ) {
						exit;
					} 
				}
				$coreConfig = Mage::getConfig();
				$coreConfig->saveConfig("mageio_autosettig", Mage::helper('core')->escapeHtml('On'));
				$coreConfig->saveConfig("mageio_runtime", Mage::helper('core')->escapeHtml($now));
				$coreConfig->cleanCache();
			}
			
		}
		else{
			if(Mage::getstoreConfig('mageio_OptResponse') != 'On'){
			echo 'You cannot access!!!';
			exit;
			}
			$this->setStatus('mageio_OptResponse', 'Off');
		}
		$this->initsetting();
		$this->image_auto_optimize();
    }
		
	public function initsetting(){
		$this->allowed_ext = array('jpg', 'jpeg', 'png', 'gif','pdf');
		$this->ignoredPath = array('app', 'var', 'cache', 'adminhtml', '.', '..');
		$this->allowedPath = "";
		
		$this->totalImages = 0;//
		$this->totalFile = 0;//
		$this->time_elapsed_secs = 0;//
		$this->totalOptimizedImages = 0;  //
		
		$resource = Mage::getSingleton('core/resource');
		$this->settings = array(
            'api_key' =>Mage::getStoreConfig('mageio_api_key'),
            'api_secret' => Mage::getStoreConfig('mageio_api_secret'),            
            'installed_time'  => Mage::getStoreConfig('mageio_installed_time'),
            'include_folders'  => Mage::getStoreConfig('mageio_include_folders'),                        
            'resize_auto'  =>Mage::getStoreConfig('mageio_resize_auto'),    
            'resize_image'  =>  Mage::getStoreConfig('mageio_resize_image'),    
            'min_size'  => Mage::getStoreConfig('mageio_min_size'),    
            'max_size'  => Mage::getStoreConfig('mageio_max_size'),    
            'compression_type_pdf'  =>Mage::getStoreConfig('mageio_compression_type_pdf'),    
            'compression_type_png'  => Mage::getStoreConfig('mageio_compression_type_png'),    
            'compression_type_jpg'  =>  Mage::getStoreConfig('mageio_compression_type_jpg'),    
            'compression_type_jpeg'  =>  Mage::getStoreConfig('mageio_compression_type_jpeg'),    
            'compression_type_gif'  => Mage::getStoreConfig('mageio_compression_type_gif')
			);
					
		if(empty($this->settings['compression_type']) ) { $this->settings['compression_type'] = 'lossy'; }        
        
		$this->allowedPath = explode(',',$this->settings['include_folders']);
        
		
        for($i=0;$i<count($this->allowed_ext); $i++) {
            $compression_type = $this->settings['compression_type_'.$this->allowed_ext[$i]];
            if($compression_type=="none") {
                unset($this->allowed_ext[$i]);
            }
        }
        $this->allowed_ext = array_values($this->allowed_ext);
	
	}
	
    /*  
		Get the local images
		@magento database name
		@magento table name		
	*/
	public function _getLocalImages() {
		$optimizedFiles = array();
        $resourceR = $this->getCoreRead('imagerecycle/images');
 
        $sql = "SELECT file,size_before FROM `{$resourceR->tableName}` where `extension` = '1'";
        $optimizedFiles = $resourceR->fetchAssoc($sql);       
        $this->totalOptimizedImages = count($optimizedFiles);
		
		$min_size = isset($this->settings['min_size']) ? (int)$this->settings['min_size']*1024 : 1* 1024;
        $max_size =  !empty($this->settings['max_size']) ? (int)$this->settings['max_size'] * 1024 : 10000 * 1024; 
		$images = array();
				
		if(Mage::getStoreConfig('mageio_include_folders') == '')
			$base_dir = Mage::getBaseDir();
		else
			$base_dir = Mage::getBaseDir().DIRECTORY_SEPARATOR;
		$start = microtime(true);
        clearstatcache(); $counter = 0;     
		foreach($this->allowedPath as $base){
			$base = $base_dir . $base;
			
			if (!file_exists($base)) {
					continue;									
			}
			foreach (new RecursiveIteratorIterator(new IgnorantRecursiveDirectoryIterator($base)) as $filename) {				
				$continue = false;
				$this->totalFile++;
			
				foreach ($this->ignoredPath as $ignore_path) {// must customize
					$ignore_path = DIRECTORY_SEPARATOR . $ignore_path . DIRECTORY_SEPARATOR;
					if (strpos(substr($filename, strlen($base)), $ignore_path) === 0 || strpos(substr($filename, strlen($base)), DIRECTORY_SEPARATOR . 'adminhtml' . DIRECTORY_SEPARATOR) !== FALSE) {
						$continue = true;
						continue;
					}
				}
				
				if ($continue === true) {
					continue;
				}			
				if (!in_array(strtolower(pathinfo($filename, PATHINFO_EXTENSION)), $this->allowed_ext)) {
					continue;
				}
				if(filesize($filename) < $min_size  || filesize($filename) > $max_size ) {
					  continue;
				}

				$data = array();
				
				if(Mage::getStoreConfig('mageio_include_folders') == ''){
							$data['filename'] = str_replace('\\', '/', substr($filename, strlen($base_dir) + 1));          
				}
				else{
						$data['filename'] = str_replace('\\', '/', substr($filename, strlen($base_dir)));          
				}
				
				$data['size'] = filesize($filename);
				$data['filetype'] = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
				
				$filter_name = Mage::getStoreConfig('mageio_all_filter_name');
				$filter_type = Mage::getStoreConfig('mageio_all_filter_type');
				
				if($filter_name || $filter_type)
				{
					$pos = strpos($data['filename'], $filter_name);
					if($pos === false){
						continue;
					}
					
					if($filter_type)
					{
						if($data['filetype'] !== $filter_type) continue;
					}
				}
				if (isset($optimizedFiles[$data['filename']]) ) {
					
					$data['optimized'] = true;
					$data['optimized_datas'] = $optimizedFiles[$data['filename']];
				}
				else{
					$data['optimized'] = false;
				}
				$this->totalImages++;
				$images[] = $data; $counter++;	
			}
		}
		
		$this->time_elapsed_secs = microtime(true) - $start;
		return $images;		
    }
  	
    /**
     * image auto optimize 
     * @param void
     */
	public function image_auto_optimize()
	{
		ignore_user_abort(true);
		$returned = -1;
		$images =  $this->_getLocalImages();
		if(empty($images)) {
			$this->setStatus('mageio_autosettig', 'Off');
			$this->setStatus('mageio_runtime', null);
			$this->setStatus('mageio_optfailure', '8');
			
			$this->setStatus('filter_type', Mage::getStoreConfig('mageio_all_filter_type'));
			$this->setStatus('filter_name',  Mage::getStoreConfig('mageio_all_filter_name'));
			return;
		}
		include_once( Mage::getBaseDir('app').'/code/community/Imagerecycle/Imagerecycle/classes/ioa.class.php');
		foreach ($images as $image) {
            if ($image['optimized'] === false) {
				$now = time();
				
				Mage::app()->getConfig()->reinit();
				Mage::app()->getCacheInstance()->cleanType('config');
				Mage::app()->reinitStores();
				if(Mage::getstoreConfig('mageio_autosettig')  == 'Off')
				{
					$this->setStatus('mageio_runtime', null);
					break;
				}
				$this->setStatus( 'mageio_runtime' ,$now);
				$returned = $this->optimize($image['filename']);
				if($returned){
					
					$this->setStatus('mageio_optfailure', $returned);
					$this->setStatus('mageio_autosettig', 'Off');
					$this->setStatus('mageio_runtime', null);
					return;
				}
            }
        }	
		// Now the optimize is ended.
		$this->setStatus('mageio_autosettig', 'Off');
		$this->setStatus('mageio_runtime', null);
		$this->setStatus('mageio_optfailure', $returned);
		
		$temp = Mage::getStoreConfig('mageio_all_filter_type');
		$this->setStatus('filter_type',$temp);
				
		Mage::app()->getConfig()->reinit();
		Mage::app()->getCacheInstance()->cleanType('config');
		Mage::app()->reinitStores();
		
		$temp = Mage::getStoreConfig('mageio_all_filter_name');		
		$this->setStatus('filter_name', $temp);
		return;
		
	}
	
	/***************************************************
	*     Optimize the files
			return 1 if success  or 0
	*
	****************************************************/
	public function optimize($image, $savePath='') {
		
		$file = realpath($image);
        if (!in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), $this->allowed_ext )) {
            return(6);
        }
		if (!file_exists($file)) {
			//if file is in the database 			
            return(1);
        }
        $resourceW = $this->getCoreWrite('imagerecycle/images');
		$ext = substr($file, strrpos($file, '.')+1);
        $compressionType= $this->settings['compression_type_'.$ext];
        if($compressionType=='none') return;
                
        if (!$this->settings['api_key'] || !$this->settings['api_secret'] ) 
        {
            return(2);
        }	
		$fparams = array("compression_type"=> $compressionType); 
        $resize_image = $this->settings['resize_image'];
        $resize_auto = $this->settings['resize_auto'];        
        if($resize_image && $resize_auto) {  
                   		
            $size = @getimagesize($file);                               
            if($size && ($size[0]> $resize_image) ) {
                $fparams['resize'] =  array("width"=> $resize_image);
            }
        }
		include_once( Mage::getBaseDir('app').'/code/community/Imagerecycle/Imagerecycle/classes/ioa.class.php');
        $ioa = new ioaphp($this->settings['api_key'], $this->settings['api_secret']);                   
        $return = $ioa->uploadFile($file,$fparams);
               
        if ($return === false || $return === null || is_string($return)) {
			$this->checkError();	
            return(3);
        }
		$coreConfig = Mage::getConfig();	
        $coreConfig->saveConfig("mageio_errormessage", Mage::helper('core')->escapeHtml(null))->cleanCache();
		Mage::dispatchEvent('imagerecycle_notifications_before');
		 
        $md5 = md5_file($file);
        clearstatcache();
        $sizebefore = filesize($file);
        $optimizedFileContent = $this->getContent($return->optimized_url);
        if ($optimizedFileContent === false) {
            return(4);
        }	
		
        if (file_put_contents($file, $optimizedFileContent) === false) {
            return(5);
        }
        clearstatcache();
		$size_after = filesize($file);
        if($savePath=='') { $savePath = $image;}
		
		$id = $resourceW->fetchOne("SELECT id FROM {$resourceW->tableName}  WHERE `file` = " . $resourceW->quote($savePath));
		if (!$id) {
			
			$name = substr($file, strrpos($file, "/")+1);
			$ext = substr($name, strrpos($name, ".")+1);
			$name = substr($name, 0, strrpos($name, '.'));
				
            $resourceW->query("INSERT INTO `{$resourceW->tableName}` (`file`,`md5`,`api_id`,`size_before`, `size_after`,`date`,`name`, `ext`) VALUES ("
                    . $resourceW->quote($savePath) . "," . $resourceW->quote($md5) . "," . $resourceW->quote($return->id) . "," . (int) $sizebefore . "," . (int) $size_after . ",'" . date('Y-m-d H:i:s'). "',". $resourceW->quote($name). ",".$resourceW->quote($ext) .")");
        } 
		else {
			$resourceW->query("UPDATE `{$resourceW->tableName}` SET `extension` ='1',`api_id` =". $resourceW->quote($return->id) .", `md5` =".$resourceW->quote($md5)." , `size_after` = " . (int) $size_after .",`date` = '".date('Y-m-d H:i:s') . "' WHERE `id` = " . $resourceW->quote($id));
		}
    return(0);
	}
	
	/**
     * Get content of specified resource via curl or file_get_content() function
     */
	protected function checkError(){
		$coreConfig = Mage::getConfig();
		$count = Mage::getStoreConfig('mageio_lasterror');
		$count++;
		if($count >= 3){
			$coreConfig->saveConfig("mageio_saving_auto", Mage::helper('core')->escapeHtml('0'));
			$coreConfig->saveConfig("mageio_compress_auto", Mage::helper('core')->escapeHtml('0'));
			$coreConfig->saveConfig("mageio_errormessage", Mage::helper('core')->escapeHtml('on'));	
			Mage::dispatchEvent('imagerecycle_notifications_before');
			$count = 0;
		}
		$coreConfig->saveConfig("mageio_lasterror", Mage::helper('core')->escapeHtml($count));
		$coreConfig->cleanCache();
	}
	
    protected function getContent($url) {
        if ($url == '') {
            return '';
        }
        if (!function_exists('curl_version')) {
            if (!$content = @file_get_contents($url)) {
                return '';
            }
        } else {
            $options = array(
                CURLOPT_RETURNTRANSFER => true, // return content
                CURLOPT_FOLLOWLOCATION => true, // follow redirects
                CURLOPT_AUTOREFERER => true, // set referer on redirect
                CURLOPT_CONNECTTIMEOUT => 60, // timeout on connect
                CURLOPT_SSL_VERIFYPEER => false // Disabled SSL Cert checks
            );
            $ch = curl_init($url);
            curl_setopt_array($ch, $options);
            $content = curl_exec($ch);
            curl_close($ch);
        }

        return $content;
    }

	public function setStatus($mage_key, $val){
		$coreConfig = Mage::getConfig();
		$coreConfig->saveConfig($mage_key, Mage::helper('core')->escapeHtml($val))->cleanCache();
	}
	public function getCoreWrite($entityId) {
        $resource = Mage::getSingleton('core/resource');
        $resourceW = $resource->getConnection('core_write');
        $tableName = $resource->getTableName($entityId);
        $resourceW->tableName = $tableName;
        return $resourceW;
    }
	public function getCoreRead($entityId) {
        $resource = Mage::getSingleton('core/resource');
        $resourceR = $resource->getConnection('core_read');
        $tableName = $resource->getTableName($entityId);
        $resourceR->tableName = $tableName;
        return $resourceR;
    }

 }
 class IgnorantRecursiveDirectoryIterator extends RecursiveDirectoryIterator {
		function getChildren() {
			try {
				return new IgnorantRecursiveDirectoryIterator($this->getPathname());
			} catch (UnexpectedValueException $e) {
				return new RecursiveArrayIterator(array());
			}
		}
}