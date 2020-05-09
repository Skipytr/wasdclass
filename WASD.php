<?php

class WASD
{

	public static $sql;
	public static $webPath = "";
	public static $webURL = "";
	public static $config = array();
	public static $globals = array();
	public static $plugins = array();
	public static $pparams = array();
	private static $language = "";
	private static $definitions = array();


	function __construct(){
		$this->loadConfig(ROOT_DIR."/config.php");
		$this->setDB();
	}

	public static function setDB(){
		if(C('app.db_name') != ''){
			self::$sql = new Vendor\Medoo(array(
				'database_type' => 'mysql',
				'database_name' => C('app.db_name'),
				'server' => C('app.db_host'),
				'username' => C('app.db_user'),
				'password' => C('app.db_password'),
				'charset' => 'utf8',
				)
			);
		}
	}

	// webPath and Website URL
	public static function webURL(){
	    $s = &$_SERVER;
	    $ssl = (!empty($s['HTTPS']) && $s['HTTPS'] == 'on') ? true:false;
	    $sp = strtolower($s['SERVER_PROTOCOL']);
	    $protocol = substr($sp, 0, strpos($sp, '/')) . (($ssl) ? 's' : '');
	    $port = $s['SERVER_PORT'];
	    $port = ((!$ssl && $port=='80') || ($ssl && $port=='443')) ? '' : ':'.$port;
	    $host = isset($s['HTTP_X_FORWARDED_HOST']) ? $s['HTTP_X_FORWARDED_HOST'] : (isset($s['HTTP_HOST']) ? $s['HTTP_HOST'] : null);
	    $host = isset($host) ? $host : $s['SERVER_NAME'] . $port;
	    $uri = $protocol . '://' . $host . self::$webPath;
	    return $uri;
	}


	// CONFIGURATION

	public static function loadConfig($file)
	{
		include $file;
		WASD::$config = array_merge(WASD::$config, $config);
	}

	public static function config($key, $default = null)
	{
		return isset(WASD::$config[$key]) ? WASD::$config[$key] : $default;
	}

	public static function writeConfig($values, $prefix = 'app')
	{
		// Include the config file so we can re-write the values contained within it.
		if (file_exists($file = ROOT_DIR."/config.php")) include $file;

		// Now add the $values to the $config array.
		if (!isset($config) or !is_array($config)) $config = array();
		$update = array();
		unset($values['action']);
		foreach ($values as $key => $value) {
			$update[$prefix.'.'.$key] = $value;
		}
		$config = array_merge($config, $update);
		self::$config = array_merge(self::$config, $update);

		// Finally, loop through and write the config array to the config file.
		$contents = "<?php\n";
		foreach ($config as $k => $v) $contents .= '$config["'.$k.'"] = '.var_export($v, true).";\n";
		$contents .= "\n// Last updated by @ ".date("r")."\n?>";
		file_put_contents($file, $contents);
	}

	// LANGUAGE

	/**
	 * Load a language and its definition files, depending on what plugins are enabled.
	 *
	 * @param string $language The name of the language.
	 * @return void
	 */
	public static function saveDefinitions($values)
	{
		// Include the config file so we can re-write the values contained within it.
		if (file_exists($file = LANG_PATH."/".C('app.language')."/definitions.php")) include $file;

		// Now add the $values to the $config array.
		if (!isset($definitions) or !is_array($definitions)) $definitions = array();
		$update = array();
		unset($values['action']);
		foreach ($values as $key => $value) {
			$update[$key] = $value;
		}
		$definitions = array_merge($definitions, $update);
		self::$definitions = array_merge(self::$definitions, $update);

		// Finally, loop through and write the config array to the config file.
		$contents = "<?php\n";
		foreach ($definitions as $k => $v) $contents .= '$definitions["'.$k.'"] = '.var_export($v, true).";\n";
		$contents .= "\n// Last updated by @ ".date("r")."\n?>";
		file_put_contents($file, $contents);
	}

	public static function loadLanguage($language = "")
	{
		// Clear the currently loaded definitions.
		self::$definitions = array();

		// If the specified language doesn't exist, use the default language.
		self::$language = file_exists(LANG_PATH."/".sanitizeFileName($language)."/definitions.php") ? $language : C("app.language");

		// Load the main definitions file.
		$languagePath = LANG_PATH."/".sanitizeFileName(self::$language);
		self::loadDefinitions("$languagePath/definitions.php");

		// Loop through the loaded plugins and include their definition files, if they exist.
		foreach (C("app.enabledPlugins") as $plugin) {
			if (file_exists($file = "$languagePath/definitions.".sanitizeFileName($plugin).".php"))
				self::loadDefinitions($file);
		}

	}

	public static function loadDefinitions($file)
	{
		include $file;
		WASD::$definitions = array_merge(WASD::$definitions, (array)@$definitions);
	}

	public static function translate($string, $default = false){
		if(!isset(self::$definitions[$string]) && C('developer.translate', 0) == '1'){
			if($default){ $value = $default; }else{ $value = $string; }
			self::saveDefinitions(array($string=>$value));
		}
		return isset(self::$definitions[$string]) ? self::$definitions[$string] : ($default ? $default : $string);
	}

	public static function load_controller($c){
		if(substr($c, 0, 6) === '/admin'){
			$c = str_replace('/admin/', '', $c);
			if(file_exists($file = ROOT_DIR .  '/acp/controllers/' .$c . '.php')){
				return $file;
			}else{
				error(' CONTROLLERS NOT FOUND: '. $file );
			}
		}else if(substr($c, 0, 4) === '/app'){
			$c = str_replace('/app/', '', $c);
			if(file_exists($file = APP_DIR .'/controllers/'.  $c . '.php')){
				return $file;
			}else{
				error(' CONTROLLERS NOT FOUND: '. $file);
			}
		}else if(substr($c, 0, 7) === '/plugin'){
			$c = str_replace('/plugin/', '/plugins/', $c);
			if(file_exists($file = APP_DIR . $c .'.php')){
				return $file;
			}else{
				error(' CONTROLLERS NOT FOUND: '. $file);
			}
		}else if($c[0] == '/'){
			if(file_exists($file = ROOT_DIR .  $c . '.php')){
				return $file;
			}else{
				error(' CONTROLLERS NOT FOUND: '. $file);
			}
		}else{
			if(file_exists($file = CONT_DIR .'/' . $c . '.php')){
				return $file;
			}else{
				error(' CONTROLLERS NOT FOUND: '.$file);
			}
		}
	}

	public static function load_controllers(array $target){
		$controllers = array(); 
		$registeredControllers = C('app.registeredControllers');
		if(isset($target['c'])){
			$cs = explode('|', $target['c']);
			// LOAD BY NORMAL after that
			foreach($cs as &$controller){
				$controllers[] = self::load_controller($controller);
				if(isset($registeredControllers[$controller])){
					$rc = $registeredControllers[$controller];
					if(isset($rc['override']) && is_array($rc['override'])){
						$rco = $rc['override'];
						foreach($rco as &$c){
							if(in_array($c['plugin'], C('app.enabledPlugins'))){
								$controllers[] = self::load_controller('/plugin/'.$c['plugin'].'/'.$c['file']);
								$pos = array_search(self::load_controller($controller), $controllers);
								unset($controllers[$pos]);
								// Because this is override so only one controller will be loaded, grab the first one
							}
						}
					}
					if(isset($rc['extend'])){						
						$rce = $rc['extend']; 
						foreach($rce as &$c){
							if(in_array($c['plugin'], C('app.enabledPlugins'))){
								$controllers[] = self::load_controller('/plugin/'.$c['plugin'].'/'.$c['file']);							
							}
						}
					}
				}
			}
		}
		return $controllers;
	}

	public static function load_model($c){
		if(substr($c, 0, 6) === '/admin'){
			$c = str_replace('/admin/', '', $c);
			if(file_exists($file = ROOT_DIR .  '/acp/models/' .$c . '.php')){
				return $file;
			}else{
				error(' MODELS NOT FOUND: '. $file );
			}
		}else if($c[0] == '/'){
			if(file_exists($file = ROOT_DIR . '/'. $c . '.php')){
				return $file;
			}else{
				error(' MODELS NOT FOUND: '. $file);
			}
		}else{
			if(file_exists($file = MODELS_DIR .'/' . $c . '.php')){
				return $file;
			}else{
				error(' MODELS NOT FOUND: '.$file);
			}
		}
	}

	public static function load_models(array $target){
		$models = array(); 
		if(isset($target['m'])){
			$ms = explode('|', $target['m']);
			foreach($ms as &$model){
				$models[] = self::load_model($model);
			}
		}
		return $models;
	}

	public static function set_pparams(array $array){
		if(is_array($array)){ 
			WASD::$pparams = $array; 
		}else{
			WASD::$pparams = array();
		}
	}

	public static function pparams($key, $default = ""){
		return (WASD::$pparams[$key] != '') ? WASD::$pparams[$key] : $default;
	}

	public static function gglobal($key, $value = ''){
		if($value == '') return isset(self::$globals[$key]) ? self::$globals[$key] : '';
			self::$globals = array_merge(self::$globals, array($key=>$value));
			return true;
	}

}