<?php

Class Imagerecycle_Imagerecycle_IndexController extends Mage_Adminhtml_Controller_Action {

    public function indexAction() {
		$this->_title($this->__('Image Recycle'));
        $this->_initAction()->renderLayout();
        return $this;
    }
	protected function checkIndexImage()
	{
		$reindexinit = Mage::getStoreConfig('mageio_reindex_init');
		if($reindexinit){
			$now = time();
			$indextime = Mage::getStoreConfig('mageio_indextime');
			$reindexinit *= 60;
			$diff = $now - $indextime;
			if($diff < $reindexinit)
				return;
			$coreConfig = Mage::getConfig();
			$coreConfig->saveConfig('mageio_loadimage',  Mage::helper('core')->escapeHtml(null));
			$coreConfig->saveConfig('mageio_indextime', $now);
			$coreConfig->cleanCache();
		}
				 
	}
	public function installNewVersion7(){
		$this->blockImages = $this->getLayout()->createBlock('imagerecycle/images');
		$this->blockImages->alterversion();
		
		$coreConfig = Mage::getConfig();
		$coreConfig->saveConfig('mageio_reindex_init',  Mage::helper('core')->escapeHtml('720'));
		$coreConfig->saveConfig('mageio_include_folders',  Mage::helper('core')->escapeHtml('media,skin'));
		$coreConfig->saveConfig('mageio_imagerecycle',  Mage::helper('core')->escapeHtml('1.7.0'));
		$coreConfig->cleanCache();
		
	}
	
    protected function _initAction() {
		
        $this->loadLayout()->_setActiveMenu('imagerecycle');
		include_once(__DIR__.'/../Helper/Data.php');
        $helper = new Imagerecycle_Imagerecycle_Helper_Data();
		
		$coreConfig = Mage::getConfig();
		$coreConfig->saveConfig('mageio_lasterror',  Mage::helper('core')->escapeHtml('0'))->cleanCache();
		
		//first register if not
		$returned =  Mage::getStoreConfig('mageio_imagerecycle');
		if(!$returned){
			$this->installNewVersion7();
		}
		
		//set the reindex times logic
		$this->checkIndexImage();
        $returned = Mage::getStoreConfig('mageio_loadimage');
		if(!$returned){
			//database insert and upda
			$this->blockImages = $this->getLayout()->createBlock('imagerecycle/images');
			$this->blockImages->_getLocalImages();
		}
		
		if(Mage::getstoreConfig('mageio_autosettig') == 'On')
		{	
			$last_optime = Mage::getStoreConfig('mageio_runtime');
			$cur_time = time();
			if(($cur_time - $last_optime) > 60){		
				 $coreConfig->saveConfig('mageio_autosettig',  Mage::helper('core')->escapeHtml('Off'));
				 $coreConfig->saveConfig('mageio_runtime', null);
				$coreConfig->cleanCache();
			}
		}
		return $this;
    }
	
	/**
	*  Save saveConfig in settins
	*
	*/
	public function saveSettingAction(){
        $configKey = array('api_key', 'api_secret','include_folders','resize_auto',
            'resize_image','min_size','max_size','compression_type_pdf','compression_type_png','compression_type_jpg','compression_type_gif', 'saving_auto', 'compress_auto', 'cron_periodicity', 'reindex_init');
        $coreConfig = Mage::getConfig();
        $post = $this->getRequest()->getPost();
        foreach ($configKey as $key) {            
            if (isset($post[$key])) {                
                $coreConfig->saveConfig("mageio_".$key, Mage::helper('core')->escapeHtml($post[$key]))->cleanCache();
            }
        }
		$installed_time =  Mage::getStoreConfig('mageio_installed_time');
        if(empty($installed_time)) {
            $installed_time = time();
            $coreConfig = Mage::getConfig();
            $coreConfig->saveConfig('mageio_installed_time',  Mage::helper('core')->escapeHtml($installed_time))->cleanCache();
        }
		//Remove error message if set
		$coreConfig->saveConfig("mageio_errormessage", Mage::helper('core')->escapeHtml(null))->cleanCache();
		
		$this->_redirect('imagerecycle/index/index');
	}
	
    public function saveconfigAction() {
		$this->loadLayout();     
		$this->renderLayout();     
    }

	public function reindexInitAction() {
		//rescan the database
		$this->blockImages = $this->getLayout()->createBlock('imagerecycle/images');
		$this->blockImages->_getLocalImages();

		$now = time();
		$coreConfig = Mage::getConfig();
        $coreConfig->saveConfig('mageio_loadimage',  Mage::helper('core')->escapeHtml(null));
		$coreConfig->saveConfig('mageio_indextime', $now);
		$coreConfig->cleanCache();
		$this->ajaxReponse(true);
    }

	public function getStatusAction(){
			
		$this->blockImages = $this->getLayout()->createBlock('imagerecycle/images');
        $optimizedCount = $this->blockImages->getOptimizedImages()+1;
		Mage::app()->getConfig()->reinit();
		Mage::app()->getCacheInstance()->cleanType('config');
		Mage::app()->reinitStores();
		
		if(Mage::getStoreConfig('mageio_autosettig') == 'On')
			$op_status = 'On';
		else{
			
			$op_status = Mage::getStoreConfig('mageio_runtime');
			if($op_status) $op_status = 'On';
			else   $op_status = 'Off';
		}
		
		$this->ajaxReponse(true ,array('optimizedCount'=>$optimizedCount,'op_status'=>$op_status));
	}
	
	/* * 
	***set search-filter-condition in saveConfig
	* */
	public function setConfigAction() {
        $response = new stdClass();
        $response->success = false;
        $coreConfig = Mage::getConfig();
        $post = $this->getRequest()->getPost();	
		$coreConfig->saveConfig('filter_type', Mage::helper('core')->escapeHtml($post['filter_type']));
        $coreConfig->saveConfig('filter_name', Mage::helper('core')->escapeHtml($post['filter_name']));
        $coreConfig->saveConfig('filter_status', Mage::helper('core')->escapeHtml($post['filter_status']));
		$coreConfig->cleanCache();

		$response->success = true;        
        $cache = Mage::getSingleton('core/cache');
        $cache->flush();       
        exit(json_encode($response));
    }
		
	/*
	* Revert processing
	* @Return ajaxReponse
	*/
    public function revertAction() {
        $requestParams = $this->getRequest()->getParams();
		$image = isset($requestParams['image']) ? $requestParams['image'] : "";
        $return_params = array();
        if (isset($requestParams['page'])) {
            $return_params['page'] = $requestParams['page'];
        }
        //Include ioaphp class once
        include_once(Mage::getModuleDir('', 'Imagerecycle_Imagerecycle') . '/classes/ioa.class.php');
        $this->blockImages = $this->getLayout()->createBlock('imagerecycle/images');
		include_once(__DIR__.'/../Helper/Data.php');
        $helper = new Imagerecycle_Imagerecycle_Helper_Data();
        $returned = $helper->_revert($image);
        $this->ajaxReponse($returned->status, $returned);         
    }
	
    public function optimizeAction() {
		
        $requestParams = $this->getRequest()->getParams();		
        $image = isset($requestParams['image']) ? $requestParams['image'] : '';
        $return_params = array();
        if (isset($requestParams['page'])) {
            $return_params['page'] = $requestParams['page'];
        }
        include_once(Mage::getModuleDir('', 'Imagerecycle_Imagerecycle') . '/classes/ioa.class.php');
        $this->blockImages = $this->getLayout()->createBlock('imagerecycle/images');
        
        include_once(__DIR__.'/../Helper/Data.php');
        $helper = new Imagerecycle_Imagerecycle_Helper_Data();
        $returned = $helper->optimize($image);
        $this->ajaxReponse($returned->status, $returned);
    }

    /**
     * Be careful of this action if site has a very big amount of images
     */
	protected function stopOptimizeAllAction() {
		$coreConfig = Mage::getConfig();
		$coreConfig->saveConfig("mageio_autosettig", Mage::helper('core')->escapeHtml('Off'))->cleanCache();
		$this->ajaxReponse(false);		
	}
		
	protected function clearstatusAction() {
		$coreConfig = Mage::getConfig();             
		$coreConfig->saveConfig('mageio_optfailure',  Mage::helper('core')->escapeHtml(''))->cleanCache();
		$this->ajaxReponse(true);
	}
	
	protected function optimizeAllAction() {
		
		$now =  time();
		if(Mage::getStoreConfig('mageio_autosettig') == 'On')
		{
			$ao_lastRun = Mage::getStoreConfig('mageio_runtime');
			if($now - $ao_lastRun < 60 ) {  	
				$this->ajaxReponse(true, array('continue' => true, 'starttime' => time(), 'status' => 'Continue'));
				return;
			} 
		}
		$coreConfig = Mage::getConfig();
		$coreConfig->saveConfig("mageio_autosettig", Mage::helper('core')->escapeHtml('On'));
		$coreConfig->saveConfig("mageio_OptResponse", Mage::helper('core')->escapeHtml('On'));
		$coreConfig->saveConfig("mageio_runtime", Mage::helper('core')->escapeHtml($now));

		$requestParams = $this->getRequest()->getParams();
		$coreConfig->saveConfig("mageio_all_filter_type",  Mage::helper('core')->escapeHtml($requestParams['filter_type']));
		$coreConfig->saveConfig("mageio_all_filter_name",  Mage::helper('core')->escapeHtml($requestParams['filter_name']));
		$coreConfig->cleanCache();

		$this->headRequest();
		
		$this->ajaxReponse(true, array('starttime' => time()));	
    }
	
	protected function saveAccountAction(){
		$requestParams = $this->getRequest()->getParams();
		$apikey = $requestParams['apikey'];
		$secret = $requestParams['secret'];
		$coreConfig = Mage::getConfig();
		$coreConfig->saveConfig("mageio_api_secret", Mage::helper('core')->escapeHtml($secret));
		$coreConfig->saveConfig("mageio_api_key", Mage::helper('core')->escapeHtml($apikey));
		$coreConfig->cleanCache();
		
		$this->ajaxReponse(true);	
	}
	
	protected function headRequest(){
		if( function_exists('curl_version')) {
            $ch = curl_init();
			$url = Mage::getBaseUrl().'imagerecycle/ImageAll/index';
			curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt ($ch, CURLOPT_URL, $url);
            curl_setopt ($ch, CURLOPT_CONNECTTIMEOUT, 20);
            curl_setopt ($ch, CURLOPT_TIMEOUT_MS, 1000);
            // Only calling the head
            curl_setopt($ch, CURLOPT_HEADER, true); // header will be at output
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'HEAD'); // HTTP request is 'HEAD'
            $content = curl_exec ($ch);
            curl_close ($ch);
        }else {
           return;
        }
	}	
	protected function ajaxReponse($status, $datas = null) {
        $response = array('status' => $status, 'datas' => $datas);
        echo json_encode($response);
        die();
    }
	
	protected function getFoldersAction()
	{
		Mage::app()->getConfig()->reinit();
		Mage::app()->getCacheInstance()->cleanType('config');
		Mage::app()->reinitStores();
        $include_folders = Mage::getStoreConfig('mageio_include_folders');
		$selected_folders = explode(',', $include_folders);      
        $path = Mage::getBaseDir().DIRECTORY_SEPARATOR;
        $requestParams = $this->getRequest()->getParams();
		$dir = $requestParams['dir'];
		
        $return = $dirs =  array();
        if(file_exists($path.$dir) ) {            
                $files = scandir($path.$dir);
                natcasesort($files);
                if( count($files) > 2 ) { 
                    $baseDir = ltrim(rtrim(str_replace(DIRECTORY_SEPARATOR, '/', $dir),'/'),'/'); 
                    if($baseDir != '') $baseDir .= '/';
                    foreach( $files as $file ) {			
                            if( file_exists($path . $dir . DIRECTORY_SEPARATOR . $file) && $file != '.' && $file != '..' && is_dir($path . $dir. DIRECTORY_SEPARATOR . $file) ) {                                                                    
                              
                                    if(in_array( $baseDir .$file,$selected_folders) ) {
                                        $dirs[] = array('type'=>'dir','dir'=>$dir,'file'=>$file,'checked'=>true);
                                    }else {
                                        $hasSubFolderSelected = false;
                                        foreach ($selected_folders as $selected_folder) {
                                            if(strpos($selected_folder,$baseDir .$file)=== 0) {
                                                $hasSubFolderSelected = true;
                                            }
                                        }
                                        if($hasSubFolderSelected) {
                                           $dirs[] = array('type'=>'dir','dir'=>$dir,'file'=>$file,'pchecked'=>true); 
                                        }else {
                                            $dirs[] = array('type'=>'dir','dir'=>$dir,'file'=>$file);
                                        }
                                    }
                            }
                    }
                    $return = $dirs;
                }
        }
        echo json_encode( $return );      
        die();
    }
	
}
