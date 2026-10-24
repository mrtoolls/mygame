<?php

namespace App\Lib;

use App\Constants\FileInfo;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class FileManager
{
    /*
    |--------------------------------------------------------------------------
    | File Manager
    |--------------------------------------------------------------------------
    |
    | FileManager class is using to manage edit, update, remove files. Developer
    | can manage any kind of files from here. But some limitations is here for image.
    | This class using a trait to manage the file paths and sizes. Developer can also
    | use this class as a helper function.
    |
    */

    /**
    * The file which will be uploaded
    *
    *
    * @var object
    */
	protected $file;

    /**
    * The path where will be uploaded
    *
    * @var string
    */
	public $path;

    /**
    * The size, if the file is image
    *
    * @var string
    */
	public $size;

    /**
    * Check the file is image or not
    *
    * @var boolean
    */
	protected $isImage;

    /**
    * Thumbnail version size, if required
    * and if the file is image
    *
    * @var string
    */
	public $thumb;

    /**
    * Old filename, which will be removed
    *
    * @var string
    */
	public $old;

    /**
    * Current filename, which is uploading
    *
    * @var string
    */
	public $filename;


    /**
    * Set the file and file type to properties if exist
    *
    * @param $file
    * @return void
    */
	public function __construct($file = null){
		$this->file = $file;
		if ($file) {
			// Decide "is this an image" from the file's actual CONTENT (Symfony's MIME-sniffed
			// guess, the same signal Laravel's own 'mimes:' validation rule uses), never from
			// the client-supplied filename/extension - a filename proves nothing; an attacker can
			// name a PHP payload "shell.png" (or an image-header polyglot "shell.php") and the
			// old extension-list check here would get it backwards either way, letting arbitrary
			// code land in a public, web-served directory. getFileName() below applies the same
			// content-sniffed rule to the saved filename itself, so this check and the saved
			// extension can never disagree.
			$imageExtensions = ['jpg','jpeg','png','webp','gif'];
			$this->isImage = in_array(strtolower((string) $file->guessExtension()), $imageExtensions, true);
		}
	}

    /**
    * File upload process
    *
    * @return void
    */
	public function upload(){

        //create the directory if doesn't exists
		$path = $this->makeDirectory();
		if (!$path) throw new \Exception('File could not been created.');

        //remove the old file if exist
		if ($this->old) {
            $this->removeFile();
	    }

        //get the filename
        if(!$this->filename){
            $this->filename = $this->getFileName();
        }

        //upload file or image
	    if ($this->isImage == true) {
	    	$this->uploadImage();
	    }else{
	    	$this->uploadFile();
	    }
	}

    /**
    * Upload the file if this is image
    *
    * @return void
    */
	protected function uploadImage(){
        $manager = new ImageManager(new Driver());
        $image = $manager->read($this->file);

        //resize the
	    if ($this->size) {
	        $size = explode('x', strtolower($this->size));
	        // cover() crops to fill the exact target box without distorting the aspect ratio
	        // (resize() stretched images that weren't already the target ratio), and also
	        // means we never save more pixels than the configured display size needs.
	        $image->cover((int) $size[0], (int) $size[1]);
	    }
        //save the image
	    $image->save($this->path . '/' . $this->filename, ...$this->saveOptions($this->filename));

        //save the image as thumbnail version
	    if ($this->thumb) {
            if ($this->old) {
                $this->removeFile($this->path . '/thumb_' . $this->old);
            }
	        $thumb = explode('x', $this->thumb);
	        $thumbFilename = 'thumb_' . $this->filename;
	        $manager->read($this->file)->cover((int) $thumb[0], (int) $thumb[1])->save($this->path . '/' . $thumbFilename, ...$this->saveOptions($thumbFilename));
	    }
	}

    /**
    * Save-time encoder options. `quality` is only a valid constructor argument for the lossy
    * encoders (jpg/webp/avif) - Intervention's PngEncoder/GifEncoder have no such parameter, so
    * passing it unconditionally throws for every non-lossy upload (transparent logos/icons etc).
    *
    * @return array<string, mixed>
    */
    protected function saveOptions(string $filename): array
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return in_array($extension, ['jpg', 'jpeg', 'webp', 'avif'], true) ? ['quality' => 82] : [];
    }


    /**
    * Upload the file if this is not a image
    *
    * @return void
    */
	protected function uploadFile(){
	    $this->file->move($this->path,$this->filename);
	}

    /**
    * Make directory doesn't exists
    * Developer can also call this method statically
    *
    * @param $location
    * @return string
    */
	public function makeDirectory($location = null){
		if (!$location) $location = $this->path;
		if (file_exists($location)) return true;
    	return mkdir($location, 0755, true);
	}

    /**
    * Remove all directory inside the location
    * Developer can also call this method statically
    *
    * @param $location
    * @return void
    */
	public function removeDirectory($location = null){
		if (!$location) $location = $this->path;
		if (! is_dir($location)) {
	        throw new \InvalidArgumentException("$location must be a directory");
	    }
	    if (substr($location, strlen($location) - 1, 1) != '/') {
	        $location .= '/';
	    }
	    $files = glob($location . '*', GLOB_MARK);
	    foreach ($files as $file) {
	        if (is_dir($file)) {
	            static::removeDirectory($file);
	        } else {
	            unlink($file);
	        }
	    }
	    rmdir($location);
	}

    /**
    * Remove the file if exists
    * Developer can also call this method statically
    *
    * @param $path
    * @return void
    */
	public function removeFile($path = null)
	{
		if (!$path) $path = $this->path . '/' . $this->old;

	    file_exists($path) && is_file($path) ? @unlink($path) : false;

	    if ($this->thumb) {
	    	if (!$path) $path = $this->path . '/thumb_' . $this->old;
	    	file_exists($path) && is_file($path) ? @unlink($path) : false;
	    }
	}

    /**
    * Generating the filename which is uploading
    *
    * @return string
    */
	protected function getFileName(){
		// The saved extension always comes from content-sniffed detection (guessExtension(),
		// backed by the real uploaded bytes), never from the client-supplied filename - see the
		// constructor above for why. Anything that looks like it could ever be executed by the
		// webserver (php variants, cgi/shell scripts, htaccess, html/svg which can carry inline
		// script) - or that simply could not be identified at all - is forced to a harmless,
		// inert extension instead, so nothing uploaded through this class can ever be written
		// into a public, web-served directory with an extension that could run it.
		$dangerousExtensions = ['php','php3','php4','php5','php7','php8','phtml','phar','pht','phps','exe','dll','sh','bash','cgi','pl','py','rb','asp','aspx','jsp','jspx','htaccess','htpasswd','htm','html','svg','js','mjs'];
		$extension = strtolower((string) $this->file->guessExtension());
		if (!$extension || in_array($extension, $dangerousExtensions, true)) {
			$extension = 'dat';
		}
		return uniqid() . time() . '.' . $extension;
	}

    /**
    * Get access of array from fileInfo method as non-static method.
    * Also get some others method
    *
    * @return string|void
    */
	public function __call($method,$args){
        $fileInfo = new FileInfo;
		$filePaths = $fileInfo->fileInfo();
		if (array_key_exists($method, $filePaths)) {
			$path = json_decode(json_encode($filePaths[$method]));
			return $path;
		}else{
			if (method_exists($this,$method)) {
				$this->$method(...$args);
			}else{
				throw new \Exception("File key or method: $method doesn't exists.");
			}
		}
	}

    /**
    * Get access some non-static method as static method
    *
    * @return void
    */
	public static function __callStatic($method,$args){
		$selfClass = new FileManager;
		$selfClass->$method(...$args);
	}

}
