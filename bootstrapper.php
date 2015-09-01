<?php

class Bootstrapper {

	static function run() {

		spl_autoload_register(function ($className) {

        		$ds = DIRECTORY_SEPARATOR;
        		$dir = __DIR__;

        		// replace namespace separator with directory separator 
        		$className = str_replace('\\', $ds, strtolower($className));

        		// get full name of file containing the required class
        		$file = "{$dir}{$ds}{$className}.php";
        		
			// get file if it is readable
        		if (is_readable($file)) require_once $file;
		});
	}
}
?>
