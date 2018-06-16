<?php

namespace botnyx\gettext;

class gettext{
	
	function __construct($projectDir,$domain,$debug=true){
		
		$this->tempDir 		= $projectDir."/tmp";
		
		$this->codeDir 		= $projectDir."/src";
		
		$this->templatesDir = $projectDir."/templates";
		
		$this->localesDir 	= $projectDir."/locales";
		
		$this->domain = $domain;
		
		$this->logdata = array();
		// 
		$this->debug = $debug;
		
		// check tempdir.
		if(!file_exists($this->tempDir)){
			echo "tmpDir is nonExistent!  (".$this->tempDir.")\n";
			die();
		}
		if(!file_exists($this->codeDir)){
			echo "codeDir is nonExistent!  (".$this->codeDir.")\n";
			die();
		}
		if(!file_exists($this->templatesDir)){
			echo "templatesDir is nonExistent!  (".$this->templatesDir.")\n";
			die();
		}
		if(!file_exists($this->localesDir)){
			echo "localesDir is nonExistent!  (".$this->localesDir.")\n";
			die();
		}
		
		
		
		// create folder
		$this->trans_compiled = $this->tempDir."/trans-compiled";
		if(!file_exists($this->trans_compiled)){
			mkdir($this->trans_compiled);
			
		}
		// create folder
		$this->trans_cache = $this->tempDir."/trans-cache";
		if(!file_exists($this->trans_cache )){
			mkdir($this->trans_cache );
		}

	}
	
	private function log($text){
		$data = $this->logdata;
		$data[] = $text;
		$this->logdata = $data;
	}
	
	public function getTranslationFile($withCode=false){
		$this->fileCount = 0;
		$this->generateTemplateCache();
		$this->consolidateTemplates();
		if ($withCode) $this->consolidateCode();
		
		
		return $this->xgettexts();
	}
	
	
	private function counter ($count){
		$counter = $this->fileCount+$count;
		$this->fileCount = $counter;
	}
	
	
	
	private function consolidateCode(){
		// Copy all files to a directory.
		$teller = 0;
		foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->codeDir), \RecursiveIteratorIterator::LEAVES_ONLY) as $file)
		{
			if ($file->isFile()) {
				copy($file->getPathname(), $this->trans_compiled."/code-".$teller."-".$file->getFilename() );
				$teller++;
			}
		}	
		$this->log( "All (".$teller.") Code files are consolidated.");
		
		$this->counter ($teller);
	}
	
	private function generateTemplateCache(){
		$loader = new \Twig_Loader_Filesystem($this->templatesDir);

		// force auto-reload to always have the latest version of the template
		$twig = new \Twig_Environment($loader, array(
			'cache' => $this->trans_cache,
			'auto_reload' => true
		));
		
		if( $this->debug ) $twig->addExtension(new \Twig_Extension_Debug() );
		$twig->addExtension(new \Twig_Extensions_Extension_I18n());
		
		// configure Twig the way you want

		$scannedfiles = [];
		// iterate over all your templates
		foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->templatesDir), \RecursiveIteratorIterator::LEAVES_ONLY) as $file)
		{
			// force compilation
			
			if ($file->isFile()) {
				$scannedfiles[]= $file->getPathname();
				$twig->loadTemplate(str_replace($this->templatesDir.'/', '', $file));
			}
		}

		$this->scannedfiles = $scannedfiles;
		//xgettext --default-domain=messages -p ./locale --from-code=UTF-8 -n --omit-header -L PHP /tmp/cache/*.php
		//xgettext --default-domain=$this->domain -p $this->tplDir --from-code=UTF-8 -n --omit-header -L PHP $this->$tmpDir/*.php
		$this->log("All Templates are generated.");
	}
	
	
	private function consolidateTemplates(){
		// Copy all files to a directory.
		$teller = 0;
		foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->trans_cache), \RecursiveIteratorIterator::LEAVES_ONLY) as $file)
		{
			if ($file->isFile()) {
				copy($file->getPathname(), $this->trans_compiled."/".$teller."-".$file->getFilename() );
				$teller++;
			}
		}	
		$this->log( "All (".$teller.") Template files are consolidated.");
		$this->counter ($teller);
		
		// remove old files.
		foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->trans_cache), \RecursiveIteratorIterator::LEAVES_ONLY) as $file)
		{
			if ($file->isFile()) {
				unlink($file->getPathname());
			}
		}
		
		
		$dirs = scandir($this->trans_cache);
		
		foreach ($dirs as $item)
		{
			if($item!="." AND ($item!="..")){
				//echo $item." <-\n";
				rmdir($this->trans_cache."/".$item);			
			}
		}	
		$this->log( "Cleaned up cache directories.");
	}
	
	private function cleanup(){
		$dirs = scandir($this->trans_compiled);
		
		foreach ($dirs as $item)
		{
			if($item!="." AND ($item!="..")){
				//echo $this->trans_compiled."/".$item." <-\n";
				unlink($this->trans_compiled."/".$item);			
			}
		}	
		$this->log( "All used files are removed.");
	}
	
	
	private function xgettexts(){
		//xgettext --default-domain=messages -p ./locale --from-code=UTF-8 -n --omit-header -L PHP /tmp/cache/*.php
		$this->log( "Parsing ".$this->fileCount." files with xgettext. ");
		
		$cmd = "xgettext --default-domain=".$this->domain." -p ".$this->localesDir." --from-code=UTF-8 -n --omit-header -L PHP ".$this->trans_compiled."/*.php";
		#echo "\n";
		#print_r($cmd);
		#echo "\n";
		exec($cmd,$out,$retval);
		$this->log( "Parsing done.");
		
		$filename = $this->domain.".po";
		$stamp = date("Y-m-d"); //time();
		
		copy( $this->localesDir."/".$filename, $this->localesDir."/".$stamp."_".$filename );
		unlink($this->localesDir."/".$filename);
		$this->cleanup();
		
		return $stamp."_".$filename;
		
	}
	
}



