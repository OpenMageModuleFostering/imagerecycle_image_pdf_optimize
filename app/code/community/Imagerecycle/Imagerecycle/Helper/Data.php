<?php
Class Imagerecycle_Imagerecycle_Helper_Data extends Mage_Core_Helper_Abstract{
	
    private $allowed_ext = array('jpg', 'jpeg', 'png', 'gif','pdf');
    public $settings = null;
    private $allowedPath = "";	
    public function getSettings() {    
        return array(           
            'api_key' => Mage::getStoreConfig('mageio_api_key'),
            'api_secret' => Mage::getStoreConfig('mageio_api_secret'),            
            'installed_time'  => Mage::getStoreConfig('mageio_installed_time'),
            'include_folders'  => Mage::getStoreConfig('mageio_include_folders'),            
            'resize_auto'  => Mage::getStoreConfig('mageio_resize_auto'), 
            'resize_image'  => Mage::getStoreConfig('mageio_resize_image'),    
            'min_size'  => Mage::getStoreConfig('mageio_min_size'),    
            'max_size'  => Mage::getStoreConfig('mageio_max_size'),    
            'compression_type_pdf'  => Mage::getStoreConfig('mageio_compression_type_pdf'),    
            'compression_type_png'  => Mage::getStoreConfig('mageio_compression_type_png'),    
            'compression_type_jpg'  => Mage::getStoreConfig('mageio_compression_type_jpg'),    
            'compression_type_gif'  => Mage::getStoreConfig('mageio_compression_type_gif'),    
            'compression_type'  => Mage::getStoreConfig('mageio_compression_type'),    
        );
    }
	
	public function getCurImages(){
		$resourceR = $this->getCoreRead('imagerecycle/images');
        $sql = "SELECT COUNT(*) AS `num` FROM `{$resourceR->tableName}` LIMIT 1";
		$optimizedCount = $resourceR->fetchOne($sql);	
		return $optimizedCount;	
	}
    public function optimize($image) {
        $response = new stdClass();
        $response->status = false;
        $response->msg = Mage::helper('imagerecycle')->__('Not be optimized yet');        
        $file = realpath($image);
        if (!in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), $this->allowed_ext )) {
            $response->msg = Mage::helper('imagerecycle')->__('This image type is not allowed');
            return $response;
        }
        if (!file_exists($file)) {
            $response->msg = Mage::helper('imagerecycle')->__('File not found');
            return $response;
        }

        $resourceR = $this->getCoreRead('imagerecycle/images');
        $resourceW = $this->getCoreWrite('imagerecycle/images');
        if(!$this->settings) {
            $this->settings = $this->getSettings();
        }
		$ext = substr($file, strrpos($file, '.')+1);
        $compressionType= $this->settings['compression_type_'.$ext];
        if($compressionType=='none') return $response;
        
        if (!$this->settings['api_key'] || !$this->settings['api_secret'] ) 
        {
            $response->msg = Mage::helper('imagerecycle')->__("You haven't configured Image recycle setting correctly yet.");
            return $response;
        }
        $fparams = array("compression_type"=> $compressionType); 
        $resize_image = $this->settings['resize_image'];
        $resize_auto = $this->settings['resize_auto'];        
        if($resize_image && $resize_auto) {   //Only apply on new images
                   		
            $size = @getimagesize($file);                               
            if($size && ($size[0]> $resize_image) ) {
                $fparams['resize'] =  array("width"=> $resize_image);
            }
        }
        include_once(Mage::getModuleDir('', 'Imagerecycle_Imagerecycle') . '/classes/ioa.class.php');
        $ioa = new ioaphp($this->settings['api_key'], $this->settings['api_secret']);                   
        $return = $ioa->uploadFile($file,$fparams);
       
        if ($return === false || $return === null || is_string($return)) {
			
			// patch the event that there is the error
			$event_data_array  =  array('type' => $ioa->getLastError());
			$this->checkError($response);
            $response->msg = $ioa->getLastError();
            return $response;
        }
		$coreConfig = Mage::getConfig();
		$coreConfig->saveConfig("mageio_errormessage", Mage::helper('core')->escapeHtml(null))->cleanCache();
		
		Mage::dispatchEvent('imagerecycle_notifications_before');
        $md5 = md5_file($file);
        clearstatcache();
        $sizebefore = filesize($file);

        $optimizedFileContent = $this->getContent($return->optimized_url);
        if ($optimizedFileContent === false) {
            $response->msg = Mage::helper('imagerecycle')->__("optimized url not found");
            return $response;
        }

        if (file_put_contents($file, $optimizedFileContent) === false) {
            $response->msg = Mage::helper('imagerecycle')->__("Download optimized image fail");
            return $response;
        }
        clearstatcache();
        $size_after = filesize($file);
		
		$id =   $resourceW->fetchOne("SELECT id FROM `{$resourceW->tableName}`  WHERE `file` = " . $resourceW->quote($image));
        if (!$id) {
            $resourceW->query("INSERT INTO `{$resourceW->tableName}` (`file`,`md5`,`api_id`,`size_before`, `size_after`,`date`, `extension`) VALUES ("
                    . $resourceW->quote($image) . "," . $resourceW->quote($md5) . "," . $resourceW->quote($return->id) . "," . (int) $sizebefore . "," . (int) $size_after . ", '" . date('Y-m-d H:i:s') . "', '1')");
        } else {
			$resourceW->query("UPDATE `{$resourceW->tableName}` SET `extension` ='1',`api_id` =".$resourceW->quote($return->id) .", `md5` =".$resourceW->quote($md5)." , `size_after` = " . (int) $size_after . " WHERE `id` = " . $resourceW->quote($id));
		}
		
		$response->status = true;
        $response->msg = 'Optimized at '. round(($sizebefore-$size_after)/$sizebefore*100,2).'%';
        $response->newSize = number_format($size_after/1000, 2, '.', '') ;
        return $response;
    }
	
	protected function checkError($response){
		$coreConfig = Mage::getConfig();
		$count = Mage::getStoreConfig('mageio_lasterror');
		$count++;
		if($count >= 3){
			$coreConfig->saveConfig("mageio_saving_auto", Mage::helper('core')->escapeHtml('0'));
			$coreConfig->saveConfig("mageio_compress_auto", Mage::helper('core')->escapeHtml('0'));
			$response->error = 'The automatic optimization options of ImageRecycle have been deactivated due to too many consecutive errors. Please check your account on https://www.imagerecycle.com/my-account';
			
			$coreConfig->saveConfig("mageio_errormessage", Mage::helper('core')->escapeHtml('on'));	
			Mage::dispatchEvent('imagerecycle_notifications_before');
			$count = 0;
		}
		$coreConfig->saveConfig("mageio_lasterror", Mage::helper('core')->escapeHtml($count));
        $coreConfig->cleanCache();
	}
	
	public function _revert($image) {		
        $response = new stdClass();
        $response->status = false;
        $response->msg = Mage::helper('imagerecycle')->__('Not be reverted yet');
		$resourceR = $this->getCoreRead('imagerecycle/images');
        $resourceW = $this->getCoreWrite('imagerecycle/images');        
        $api_id = $resourceR->fetchOne("SELECT api_id FROM {$resourceR->tableName}  WHERE `file` = " . $resourceR->quote($image));
        if ($api_id) {
			if(!$this->settings) {
            $this->settings = $this->getSettings();
			}
            $ioa = new ioaphp($this->settings['api_key'], $this->settings['api_secret']);            
            $return = $ioa->getImage($api_id);
            if (!isset($return->id)) {
                $response->msg = Mage::helper('imagerecycle')->__('api id is not correct');
                return $response;
            }
            $fileContent = file_get_contents($return->origin_url);
            if ($fileContent === false) {
                $response->msg = Mage::helper('imagerecycle')->__('Image not found');
                return $response;
            }
            $file = realpath($image);
            if (file_put_contents($file, $fileContent) === false) {
                $response->msg = Mage::helper('imagerecycle')->__("Can't write file");
                return $response;
            }
            clearstatcache();
            $size_after = filesize($file);
            $resourceW->query("UPDATE `{$resourceW->tableName}` SET  `date` = '' ,`api_id` = '' ,`extension` = '' ,`md5` = '' , `size_after` ='' WHERE api_id = " . $resourceW->quote($api_id));
			$response->newSize = number_format($size_after/1000, 2, '.', '') ;
            $response->status = true;
            $response->msg = Mage::helper('imagerecycle')->__('Reverted');
        }
        return $response;
    }
    
    public function checkOptimize($image) {
		
		//  ???  is it possible after the product has been upload but the data is not optimiexe
        $resourceW = $this->getCoreWrite('imagerecycle/images');
        $id = $resourceW->fetchOne("SELECT `extension` FROM {$resourceW->tableName}  WHERE `file` = " . $resourceW->quote($image));
        return $id;
    }
	
	 public function checkOptimizeTmp($image) {
		
		//  ???  is it possible after the product has been upload but the data is not optimiexe
        $resourceW = $this->getCoreWrite('imagerecycle/images');
        $id = $resourceW->fetchOne("SELECT `id` FROM {$resourceW->tableName}  WHERE `file` = " . $resourceW->quote($image));
        return $id;
    }
	
	public function updateOptdata($src, $dst){
		$resourceW = $this->getCoreWrite('imagerecycle/images');
		$id =   $resourceW->fetchOne("SELECT id FROM `{$resourceW->tableName}`  WHERE `file` = " . $resourceW->quote($src));      
		$resourceW->query("UPDATE `{$resourceW->tableName}` SET `file` =".$resourceW->quote($dst)." WHERE `id` = " . $resourceW->quote($id));
	}
    /**
     * Get content of specified resource via curl or file_get_content() function
     */
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

     public function getCoreRead($entityId) {
        $resource = Mage::getSingleton('core/resource');
        $resourceR = $resource->getConnection('core_read');
        $tableName = $resource->getTableName($entityId);
        $resourceR->tableName = $tableName;
        return $resourceR;
    }

    public function getCoreWrite($entityId) {
        $resource = Mage::getSingleton('core/resource');
        $resourceW = $resource->getConnection('core_write');
        $tableName = $resource->getTableName($entityId);
        $resourceW->tableName = $tableName;
        return $resourceW;
    }
	
	public function getLastTime(){
		return	Mage::getStoreConfig('mageio_ao_lastRun'); 
	}
	
	public function setLastTime($time){
		$coreConfig = Mage::getConfig();
		$coreConfig->saveConfig("mageio_ao_lastRun", Mage::helper('core')->escapeHtml($time))->cleanCache();
	}
	
	public function getStatus(){
		return	Mage::getStoreConfig('optimizeall_flag');
	}
}


