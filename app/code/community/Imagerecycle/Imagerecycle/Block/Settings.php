<?php

Class Imagerecycle_Imagerecycle_Block_Settings extends Mage_Page_Block_Html {

	private $allowed_ext = array('jpg', 'jpeg', 'png', 'gif','pdf');
    private $ignoredPath = array('app', 'var', 'cache', 'adminhtml', '.', '..');
    private $allowedPath = "";
	
    public function __construct() {
        parent::__construct();
		  //Get settings
        $this->settings = array(            
            'api_key' => Mage::getStoreConfig('mageio_api_key'),
            'api_secret' => Mage::getStoreConfig('mageio_api_secret'),            
            'installed_time'  => Mage::getStoreConfig('mageio_installed_time'),
            'include_folders'  => Mage::getStoreConfig('mageio_include_folders'),                        
            'reindex_init'  => Mage::getStoreConfig('mageio_reindex_init'),                        
            'saving_auto'  => Mage::getStoreConfig('mageio_saving_auto'),    
            'compress_auto'  => Mage::getStoreConfig('mageio_compress_auto'),    
            'cron_periodicity'  => Mage::getStoreConfig('mageio_cron_periodicity'),    
            'resize_auto'  => Mage::getStoreConfig('mageio_resize_auto'),    
            'resize_image'  => Mage::getStoreConfig('mageio_resize_image'),    
            'min_size'  => Mage::getStoreConfig('mageio_min_size'),    
            'max_size'  => Mage::getStoreConfig('mageio_max_size'),    
            'compression_type_pdf'  => Mage::getStoreConfig('mageio_compression_type_pdf'),    
            'compression_type_png'  => Mage::getStoreConfig('mageio_compression_type_png'),    
            'compression_type_jpg'  => Mage::getStoreConfig('mageio_compression_type_jpg'),    
            'compression_type_jpeg'  => Mage::getStoreConfig('mageio_compression_type_jpeg'),    
            'compression_type_gif'  => Mage::getStoreConfig('mageio_compression_type_gif'),    
            'compression_type'  => Mage::getStoreConfig('mageio_compression_type'),
        );
        
        $this->allowedPath = explode(',',$this->settings['include_folders']);
		
        for($i=0;$i<count($this->allowed_ext); $i++) {
            $compression_type = $this->settings['compression_type_'.$this->allowed_ext[$i]];
            if($compression_type=="none") {
                unset($this->allowed_ext[$i]);
            }
        }
        $this->allowed_ext = array_values($this->allowed_ext);
		
    }

	public function formateBytes($data)
	{
		if($data < 1024)
			return round($data, 2).'Bytes';
		$data = $data/1024;
		if($data < 1024)
			return round($data, 2).'KB';
		$data = $data/1024;
		if($data < 1024)
			return round($data, 2).'MB';
		
		$data = $data/1024;
		return round($data, 2).'GB';
		
	}
	
	public function senddata()
	{
		return 'hi';
	}
}

