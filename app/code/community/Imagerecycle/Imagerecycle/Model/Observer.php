<?php
class Imagerecycle_Imagerecycle_Model_Observer {
	
	public function cronexec($observer){
				
		//preprocessing
		//count the time 5 mins 10 mins  20mins  30min 1h 2h 6h 12h 24h 48h mageio_compress_auto
		if( Mage::getStoreConfig('mageio_compress_auto') == '2')
		{
			$now =  time();	
			$ao_lastRun = Mage::getStoreConfig('mageio_crontime');
			$diff= $now - $ao_lastRun;
			$cron_periodiciy = $this->getCronPeriodicity() * 60;
			if($diff < $cron_periodiciy)
				return;
			$this->cronoptall();
			$coreConfig = Mage::getConfig();
			$coreConfig->saveConfig('mageio_crontime', Mage::helper('core')->escapeHtml($now))->cleanCache();
		}
	}
	
	public function checkResponse($observer){
		if( Mage::getStoreConfig('mageio_compress_auto') == '1')
		{							
			$now =  time();
			$ao_lastRun = Mage::getStoreConfig('mageio_runtime_ajax');
			if($now - $ao_lastRun < 3000 ) {
					return;
			}		
			$coreConfig = Mage::getConfig();
			$coreConfig->saveConfig('mageio_runtime_ajax', Mage::helper('core')->escapeHtml($now))->cleanCache();
			$this->cronoptall();	
		}	
	}
    public function imageuploaded($observer) {
		
        //$observer contains data passed from when the event was triggered.
        //You can use this data to manipulate the order data before it's saved.
        //Uncomment the line below to log what is contained here:
				
			$flag = Mage::getStoreConfig('mageio_saving_auto');
			if(!$flag)  return;
			
			$data = $observer->getEvent()->getData();
			$picture = $data['result'];
			Mage::log($picture);
			if(!$picture['error']) {
				include_once(__DIR__.'/../Helper/Data.php');
				$helper = new Imagerecycle_Imagerecycle_Helper_Data();
				$baseMedia = 'media';
				$return = $helper->optimize( $baseMedia . '/tmp/catalog/product' .$picture['file'], $baseMedia . '/catalog/product' .$picture['file']);
				Mage::log($return);
			} 
		
    }
	
	public function cronoptall(){
		$now =  time();
		$ao_lastRun = Mage::getStoreConfig('mageio_runtime');	
		$diff = $now - $ao_lastRun;
		if(Mage::getStoreConfig('mageio_autosettig') == 'On')
		{			
			if($now - $ao_lastRun < 1000 ) {
				return;
			} 
		}
		$coreConfig = Mage::getConfig();
		$coreConfig->saveConfig("mageio_autosettig", Mage::helper('core')->escapeHtml('On'));
		$coreConfig->saveConfig("mageio_OptResponse", Mage::helper('core')->escapeHtml('On'));
		$coreConfig->saveConfig("mageio_runtime", Mage::helper('core')->escapeHtml($now));
		$coreConfig->cleanCache();
		$this->headRequest();
		/**/
	}
	
	public function checkMessages($observer)
    {
		if(Mage::getStoreConfig('mageio_errormessage') == 'on'){
			$notifications = Mage::getSingleton('imagerecycle/notification');
			$notifications->addMessage('The automatic optimization options of ImageRecycle have been deactivated due to too many consecutive errors. Please check your account on <a href = "https://www.imagerecycle.com/my-account">https://www.imagerecycle.com/my-account</a><br/>To remove this message please got to the settings page and save the settings');
		}
        return $observer;
    }
	
	public function checkError($observer){
		
		$coreConfig = Mage::getConfig();
		$count = Mage::getStoreConfig('mageio_lasterror');
		$count++;
		$coreConfig->saveConfig("mageio_lasterror", Mage::helper('core')->escapeHtml($count))->cleanCache();
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
	
    public function productsaved($observer) {
			
			$flag = Mage::getStoreConfig('mageio_saving_auto');
			if(!$flag)  return;
			$productId = $observer->getProduct()->getId();
			$model = Mage::getModel('catalog/product');
			$_product = $model->load($productId);
	 
			include_once(__DIR__.'/../Helper/Data.php');
			$helper = new Imagerecycle_Imagerecycle_Helper_Data();
			$baseMedia = 'media';
			 foreach ($_product->getMediaGalleryImages() as $image) {
				  
				 $tmpfile = $baseMedia . '/tmp/catalog/product'. $image['file'];
				 $file = $baseMedia . '/catalog/product'. $image['file']; 
				  if($helper->checkOptimizeTmp($tmpfile)) {
					//it may be change
					$helper->updateOptdata($tmpfile,$file);
				  }
											   
				 if(!$helper->checkOptimize($file)) {
					  $return = $helper->optimize($file);
					  Mage::log($return);          
				 }                                      
			} 
					
    }
    
	public function getCronPeriodicity(){
		$real_timedata = array(5, 10, 20, 30, 60, 120, 360, 720, 1440, 2880);
		$period_data = array('5mins','10mins','20mins', '30min','1h','2h','6h','12h','24h','48h');
		$i = 0;
		$temp = Mage::getStoreConfig('mageio_cron_periodicity');
		foreach($period_data as $period){
			if($temp == $period)
				return $real_timedata[$i];
			$i++;
		}
	}
	public function printdata($data){
		
		$fp = fopen('d:/asd.txt', 'a');
		fwrite($fp, $data);
		fclose($fp);
	}
	public function onBackCheckOutAfter($observer){
		 
		$customer = Mage::getSingleton('customer/session')->getCustomer();
		echo $cutomer_email = $customer->getEmail();
		$orderId =  Mage::getSingleton('checkout/session')->getLastOrderId();
		exit;
		
	}
	
    public function categorySaved($observer) {
		 
         $flag = Mage::getStoreConfig('mageio_saving_auto');
		 if(!$flag)  return;
       
         $categoryId = $observer->getEvent()->getCategory()->getId();
         $model = Mage::getModel('catalog/category');        
         $category = $model->load($categoryId);
         $image = $category->getImage();     
         $thumbnail = $category->getThumbnail();
        
         include_once(__DIR__.'/../Helper/Data.php');
         $helper = new Imagerecycle_Imagerecycle_Helper_Data();                 
         $baseMedia = 'media';
         if($image!='') {
            $file = $baseMedia .'/catalog/category/'. $image;               
            if(!$helper->checkOptimize($file)) {
                     $return = $helper->optimize($file);
                     Mage::log($return);          
            }               
         }
         if($thumbnail!='') {
            $file = $baseMedia .'/catalog/category/'. $thumbnail;                
            if(!$helper->checkOptimize($file)) {
                     $return = $helper->optimize($file);
                     Mage::log($return);          
            }               
         }
    }
	
 
}
?>