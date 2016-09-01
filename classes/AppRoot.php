<?php
class AppRoot
{
	public static $debug = array();
	public static $errors = array();
	private static $title = array();
    public static $config = array();
	private static $timeMeasures = array();
	public static $startTime = 0;
	public static $currentTime = 0;
	public static $execTime = 0;
	public static $maxExecTime = 30;
	public static $maxMemory = "100M";

	public static $javascripts = array();
	public static $stylesheets = array();

	private static $cacheDir = "documents/cache/";


    /**
     * AUTO LOADER
     * @param string $class
     * @throws \Exception
     */
    public static function classLoader($class)
    {
        // Split class name in namespace parts and get root namespace
        $parts = explode("\\", trim($class, "\\"));
        $root  = array_shift($parts);

        $moduleClass = "modules/".$root."/classes/Module.php";
        if (file_exists($moduleClass))
            require_once $moduleClass;

        // Determine class file location
        if (strpos($class, "Smarty_") === 0)
            $file = "classes/smarty/sysplugins/" . strtolower($class);
        elseif (!$parts)
            $file = "classes/" . $root;
        elseif ($root == "framework")
            $file = "classes/common/classes/" . implode("/", $parts);
        elseif ($root == "elements")
            $file = "classes/common/classes/elements/" . implode("/", $parts);
        elseif ($parts[0] == "common")
            $file = "modules/" . $root . "/" . array_shift($parts) . "/classes/" . implode("/", $parts);
        else
            $file = "modules/" . $root . "/classes/" . implode("/", $parts);


        // Find the file using .class.php or .php extension
        if (file_exists($file . ".class.php"))
            $file .= ".class.php";
        elseif (file_exists($file . ".php"))
            $file .= ".php";
        else
        {
            if (array_pop($parts) == "Exception")
                throw new \Exception("Exception triggered");

            \AppRoot::debug("<span style='color:red;'>Failed to load class $class from file:</span> $file(.class).php");
            return;
        }

        require_once($file);
        \AppRoot::debug("Loaded Class file: " . $file);
    }

    /**
     * ERROR HANDLER
     * @param string $errno
     * @param string $errstr
     * @param string $errfile
     * @param string $errline
     * @return bool
     */
    public static function errorHandler($errno, $errstr, $errfile, $errline)
    {
        // @-operator
        if ((error_reporting() & $errno) != $errno)
            return false;

        $aLevels = array(2 => 'WARNING', 8 => 'NOTICE', 256 => 'FATAL ERROR', 512 => 'WARNING', 1024 => 'NOTICE');
        $errLevel = (isset($aLevels[$errno])) ? $aLevels[$errno] : "ERROR";

        $trace = self::getStackTrace();
        $message = "$errLevel - $errstr \n";
        $message .= "FILE: ".str_replace(DIRECTORY_SEPARATOR,"/",$errfile)."\n";
        $message .= "LINE: ".$errline."\n";
        $message .= "\n".$trace;
        \AppRoot::error($message);

        /* Don't execute PHP internal error handler */
        return true;
    }

    /**
     * EXCEPTION HANDLER
     * @param \Exception $exception
     */
    public static function exceptionHandler($exception)
    {
        $message = get_class($exception)." [".$exception->getCode()."] ".$exception->getMessage()."\n";
        $message .= "FILE: ".$exception->getFile()."\n";
        $message .= "LINE: ".$exception->getLine()."\n";
        $message .= "\n".$exception->getTraceAsString();
        \AppRoot::error($message);
    }


	/**
	 * SQL UPDATES
	 */
	public static function readSqlUpdates()
	{
		// Zoeken naar SQL updates en deze uitvoeren.
		$directory = "update".DIRECTORY_SEPARATOR."sql";
		if (!file_exists($directory))
			return false;

		\AppRoot::debug("== Check UPDATE SQL: ".$directory);
		if ($handle = @opendir($directory))
		{
			$executedFiles = array();
			if ($results = \MySQL::getDB()->getRows("SELECT * FROM system_patches_sql")) {
				foreach ($results as $result) {
					$executedFiles[] = $result["filename"];
				}
			}

			$queryRegex = '%\s*((?:\'[^\'\\\\]*(?:\\\\.[^\'\\\\]*)*\' | "[^"\\\\]*(?:\\\\.[^"\\\\]*)*" | /*[^*]*\*+([^*/][^*]*\*+)*/ | \#.* | --.* | [^"\';#])+(?:;|$))%x';
			while (false !== ($file = readdir($handle)))
			{
				if ($file == "." || $file == "..")
					continue;

				$sqlFile = $directory.DIRECTORY_SEPARATOR.$file;
				if (is_file($sqlFile) && !in_array($file, $executedFiles))
				{
					\MySQL::getDB()->updateinsert("system_patches_sql",
							array(	"filename"	=> $file,
									"execdate"	=> date("Y-m-d H:i:s")),
							array(	"filename"	=> $file));

					// Queries parsen & uitvoeren
					preg_match_all($queryRegex, file_get_contents($sqlFile), $queries);
					foreach ($queries[1] as $query) {
						\MySQL::getDB()->doQuery($query);
					}
					unset($queries);
				}
			}
			unset($executedFiles);
		}
		@closedir($handle);
		unset($handle);
		\AppRoot::debug("== Finished UPDATE SQL");
        return true;
	}

	/**
	 * PHP SCRIPT UPDATES
	 */
	public static function readPhpUpdates()
	{
		// Zoeken naar update scripts en deze uitvoeren.
		$directory = "update".DIRECTORY_SEPARATOR."php";
		if (!file_exists($directory))
			return false;

		\AppRoot::debug("== Check UPDATE Scripts");
		if ($handle = @opendir($directory))
		{
			$executedFiles = array();
			if ($results = \MySQL::getDB()->getRows("SELECT * FROM system_patches_php")) {
				foreach ($results as $result) {
					$executedFiles[] = $result["filename"];
				}
			}

			while (false !== ($patchFile = readdir($handle)))
			{
				if ($patchFile == "." || $patchFile == "..")
					continue;

				$phpFile = $directory.DIRECTORY_SEPARATOR.$patchFile;
				if (is_file($phpFile) && !in_array($patchFile, $executedFiles))
				{
					\MySQL::getDB()->updateinsert("system_patches_php",
							array(	"filename"	=> $patchFile,
									"execdate"	=> date("Y-m-d H:i:s")),
							array(	"filename"	=> $patchFile));

					\AppRoot::debug("Executing: ".$phpFile);
					include($phpFile);
				}
			}
			unset($executedFiles);
		}
		@closedir($handle);
		unset($handle);
		\AppRoot::debug("== Finished UPDATE Scripts");
        return true;
	}


	public static function logRequest()
	{
		$browser = Tools::getBrowser();
		$data = array(	"request"	=> $_SERVER["REQUEST_URI"],
						"requestdate" => date("Y-m-d H:i:s"),
						"referer"	=> (isset($_SERVER["HTTP_REFERER"]))?$_SERVER["HTTP_REFERER"]:"",
						"sessionid"	=> session_id(),
						"visitor_userid" => (\User::getUSER())?\User::getUSER()->id:0,
						"visitor_shopid" => (\Shop::getSHOP())?\Shop::getSHOP()->id:0,
						"visitor_browser" => $browser["name"],
						"visitor_browser_version" => $browser["version"],
						"visitor_platform" => $browser["platform"],
						"visitor_ip" => $_SERVER["REMOTE_ADDR"]);
		\MySQL::getDB()->insert("log_visiter", $data);
	}

	public static function loginRequired()
	{
		// API
		if (\Tools::GET("module") == "api")
			return false;

        // cron
        if (\Tools::GET("module") == "system")
            return false;

        // screenies
        if (\Tools::GET("module") == "screenshots")
            return false;

        // Register
        if (\Tools::GET("module") == "users" && \Tools::GET("section") == "register")
            return false;

		return true;
	}

	public static function parseRequestURL()
	{
		if (\Tools::REQUEST("requesturl"))
		{
			$parts = explode("/",\Tools::REQUEST("requesturl"));
			foreach ($parts as $key => $part)
			{
				if ($key == 0)
					$_GET["module"] = $part;
				else if ($key == 1)
					$_GET["section"] = $part;
				else
					continue;

				array_shift($parts);
			}
			$_GET["arguments"] = implode(",",$parts);
		}
	}

	public static function debug($value, $addStackTrace=false)
	{
		if (self::doDebug())
		{
			if (self::$startTime == 0)
				self::$startTime = microtime(true);

			if (is_array($value) || is_object($value))
				$value = "<pre style='margin: 0px; padding: 0px;'>".print_r($value,true)."</pre>";
			else
				$value = nl2br($value);

			if ($addStackTrace)
				$value .= "<pre>".print_r(self::getStackTrace(),true)."</pre>";


			self::$debug[] = array("time" => number_format(self::getExecTime(), 4), "msg" => $value);
		}
	}

	public static function getStackTrace()
	{
		ob_start();
		debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
		$stack = ob_get_contents();
		ob_end_clean();

		return $stack;
	}

	public static function getCurrentTime()
	{
		self::$currentTime = microtime(true);
		return self::$currentTime;
	}

	public static function getExecTime()
	{
		self::$execTime = self::getCurrentTime() - self::$startTime;
		return self::$execTime;
	}

	public static function setMaxExecTime($seconds=30)
	{
		set_time_limit($seconds);
		self::$maxExecTime = self::getExecTime() + $seconds;
	}

	public static function setMaxMemory($bytes)
	{
		self::$maxMemory = $bytes;
		return ini_set("memory_limit", self::$maxMemory);
	}

	public static function startTimeMeasure($var)
	{
		self::$timeMeasures[$var]["start"] = self::getCurrentTime();
	}

	public static function endTimeMeaseure($var)
	{
		self::$timeMeasures[$var]["end"] = self::getCurrentTime();
		return self::getTimeMeasureExecTime($var);
	}

	public static function getTimeMeasureExecTime($var)
	{
		self::$timeMeasures[$var]["exec"] = self::$timeMeasures[$var]["end"] - self::$timeMeasures[$var]["start"];
		self::debug("TimeMeasure[".$var."]: ".self::$timeMeasures[$var]["exec"]);
		return self::$timeMeasures[$var]["exec"];
	}

    public static function error($message)
    {
        $error = $message."\n\n".self::getStackTrace();

        if (\AppRoot::doDebug())
            echo "<pre>".print_r($error,true)."</pre>";

        self::$errors[] = $error;
        self::storeError($error);
        self::debug("<span style='color:red;'>" . $error . "</span>");
    }

    public static function depricated($what, $message="")
    {
        self::error("Depricated call: ".$what."\n".$message);
    }

	public static function storeError($message)
	{
		$data = array();
		$data["error"] = $message;
		$data["errordate"] = date("Y-m-d H:i:s");
		$data["info"] = "DATE: ".date("Y-m-d H:i:s")."\n";
		$data["info"] .= "WORKING DIR: ".str_replace(DIRECTORY_SEPARATOR,"/",getcwd())."\n";
		$data["info"] .= "PHP_SELF: ".$_SERVER["PHP_SELF"]."\n";
		$data["info"] .= "SERVER_ADDR: ".$_SERVER["SERVER_ADDR"]."\n";
		$data["info"] .= "REQUEST_URI: ".$_SERVER["REQUEST_URI"]."\n";
		$data["info"] .= "GET: ".json_encode($_GET)."\n";
		$data["info"] .= "POST: ".json_encode($_POST)."\n";
		$data["info"] .= "SESSION: ".json_encode($_SESSION)."\n";
		$data["info"] .= "USER: ".(is_object(\User::getUSER()) ? \User::getUSER()->getFullName() : "");

		self::errorToLog($message);
	}

	public static function errorToLog($message)
	{
		$errorLog = "logs/error/".date("Y-m-d").".log";
		if (!file_exists("logs"))
			mkdir("logs",0777);
		if (!file_exists("logs/error"))
			mkdir("logs/error",0777);

		$handle = fopen($errorLog, "a");
		fwrite($handle, "\n--[ ".date("Y-m-d H:i:s")." ]----------------------------------------------------------\n");
		fwrite($handle, $message."\n");
		fwrite($handle, "CWD: ".getcwd()."\n");
		fwrite($handle, "PHP_SELF: ".$_SERVER["PHP_SELF"]."\n");
		fwrite($handle, "REQUEST_URI: ".$_SERVER["REQUEST_URI"]."\n");
		fclose($handle);
	}

	public static function doCliCommand($command, $expectOutput=false)
	{
		\AppRoot::debug("<div style='background-color: #222222; color: #EEEEEE; padding: 1px;'>".$command."</div>");
		$output = shell_exec($command);

		if ($output === null)
		{
            if ($expectOutput)
			    \AppRoot::error($command);

			return false;
		}
		else
		{
			\ApPRoot::debug($command.":<pre>".$output."</pre>");
			return $output;
		}
	}

	public static function addJavascriptFile($directory, $filename, $module=false)
	{
		if ($module)
			$directory = "modules/".$module."/".$directory;

		$keyMod = $module;
		$keyDir = trim(str_replace("/".\SmartyTools::getTemplate()."/", "/", $directory),"/");

		$fileParts = explode(".",$filename);
		$extension = array_pop($fileParts);
		$keyFile = implode(".",$fileParts);

		$filePath = trim($directory, "/")."/".$filename;
		if (is_file($filePath))
		{
			self::debug("Load Javascript File: ".$filePath);
			$filePath .= "?".fileatime($filePath);

			self::$javascripts[$keyMod][$keyDir][$keyFile] = $filePath;
		}
	}

	public static function addStylesheetFile($directory, $filename, $module=false)
	{
		if ($module)
			$directory = "modules/".$module."/".$directory;

		$keyMod = $module;
		$keyDir = trim(str_replace("/".\SmartyTools::getTemplate()."/", "/", $directory),"/");

		$fileParts = explode(".",$filename);
		$extension = array_pop($fileParts);
		$keyFile = implode(".",$fileParts);

		$filePath = trim($directory, "/")."/".$filename;
		if (is_file($filePath))
		{
			self::debug("Load Stylesheet File: ".$filePath);
			$filePath .= "?".fileatime($filePath);

			self::$stylesheets[$keyMod][$keyDir][$keyFile] = $filePath;
		}
	}

	public static function title($value)
	{
		self::$title[] = $value;
	}

	public static function getTitle($reverse=false)
	{
		if (!$reverse)
			return self::$title;
		else {
			$value = array();
			for($i = count(self::$title) - 1; $i > - 1; $i --) {
				$value[] = self::$title[$i];
			}
			return $value;
		}
	}

	public static function getDebug()
	{
		return self::$debug;
	}

	public static function doDebug()
	{
		if (Tools::REQUEST("debug") == "1")
			return true;

		if (defined("APP_DEBUG"))
		{
			if (APP_DEBUG && !Tools::REQUEST("ajax"))
				return true;
		}

		return false;
	}

	public static function printDebug()
	{
		if (self::doDebug())
		{
			$execTime = $_SERVER["REQUEST_TIME"] - strtotime("now");

			$debugTPL = \SmartyTools::getSmarty();
			$debugTPL->assign("errors", self::$errors);
			$debugTPL->assign("debug", self::getDebug());
			// Collect system vars
			$systemvars = array();

			// Request/Connection info
			$key = count($systemvars);
			$systemvars[$key]["name"] = "CONNECTION";
			$systemvars[$key]["vars"]["Execution time"] = $execTime . " seconds";
			$systemvars[$key]["vars"]["Working Dir"] = getcwd();
			$systemvars[$key]["vars"]["__NAMESPACE__"] = __NAMESPACE__;

			if (count($_GET) > 0)
			{
				$key = count($systemvars);
				$systemvars[$key]["name"] = "GET";
				foreach ($_GET as $var => $value) {
					$systemvars[$key]["vars"][$var] = Tools::getVariableContentString($value);
				}
			}
			if (count($_POST) > 0)
			{
				$key = count($systemvars);
				$systemvars[$key]["name"] = "POST";
				foreach ($_POST as $var => $value) {
					$systemvars[$key]["vars"][$var] = Tools::getVariableContentString($value);
				}
			}
			if (count($_SESSION) > 0)
			{
				$key = count($systemvars);
				$systemvars[$key]["name"] = "SESSION";
				foreach ($_SESSION as $var => $value) {
					$systemvars[$key]["vars"][$var] = Tools::getVariableContentString($value);
				}
			}
			if (count($_COOKIE) > 0)
			{
				$key = count($systemvars);
				$systemvars[$key]["name"] = "COOKIE";
				foreach ($_COOKIE as $var => $value) {
					$systemvars[$key]["vars"][$var] = Tools::getVariableContentString($value);
				}
			}
			if (count($_SERVER) > 0)
			{
				$key = count($systemvars);
				$systemvars[$key]["name"] = "SERVER";
				foreach ($_SERVER as $var => $value) {
					$systemvars[$key]["vars"][$var] = Tools::getVariableContentString($value);
				}
			}

			$debugTPL->assign("systemvars", $systemvars);
			return $debugTPL->fetch("debug");
		}
		else
			return "";
	}

    /**
     * Get or set config
     * @param bool|string $var
     * @param bool|string $val (if you want to set)
     * @return bool|string false
     */
	public static function config($var=false, $val=false)
	{
		if (!$var)
			return self::$config;
		else
		{
			if (!$val)
			{
				if (array_key_exists($var, self::$config))
					return self::$config[$var];
				else
					return false;
			}
			else
			{
				self::$config[$var] = $val;
				return true;
			}
		}
	}

    public static function redidrectToReferer()
    {
        header("Location: ".$_SERVER["HTTP_REFERER"]);
        exit;
    }

	public static function refresh()
	{
		self::redirect(\Tools::getCurrentURL());
	}

	public static function redirect($url, $inclAppUrl=true)
    {
        if ($inclAppUrl)
            $url = \Config::getCONFIG()->get("system_url").trim($url,"/");

		\AppRoot::debug("<div style='background-color: #FFAA00; color: #222222; font-weight: bold; padding: 5px; border: dashed 1px #000000;'>REDIRECT: :".$url."</div>");
		if (\AppRoot::doDebug())
		{
			echo "	<div style='margin: 20px; padding-top: 20px; padding-bottom: 20px;
								font-family: Arial, sans-serif; font-size: 14px; text-align: center; color: #FFFFFF;
								background-color: #222222; border: dashed 5px #FFAA00;'>
						<div style='padding: 5px; font-size: 16px;'><b>REDIRECTING</b></div>
						<a href='".$url."' style='color: #FFAA00;'>".$url."</a>
					</div>";

			echo self::printDebug();
			exit;
		}
		else
		{
			header("Location: ".$url);
			exit;
		}
	}

	public static function setTaskLog($task, $param)
	{
		$interval = 360;
		if ($task == "corpimportmembers")
			$interval = 720;
		if ($task == "corpimportinfo")
			$interval = 1440;
		if ($task == "importcharacter")
			$interval = 720;

		$db = MySQL::getDB();
		$db->updateinsert("cron_task_log",
						array("task" => $task, "parameter" => $param, "runinterval" => $interval, "lastruntime" => date("Y-m-d H:i:s")),
						array("task" => $task, "parameter" => $param));
	}

	public static function getCacheDirectory()
	{
		return self::$cacheDir;
	}



	public static function getCache($file)
	{
        \AppRoot::depricated("getCache()", $file);
        return \Cache::file()->get($file);
	}

	public static function setCache($file, $cache)
	{
        \AppRoot::depricated("setCache()", $file);
        return \Cache::file()->set($file, $cache);
	}

	public static function removeCache($file)
	{
        \AppRoot::depricated("removeCache()", $file);
        return \Cache::file()->get($file);
	}



	public static function getDBConfig($var)
	{
		if ($result = \MySQL::getDB()->getRow("SELECT val FROM config WHERE var = ?", array($var)))
			return $result["val"];

		return false;
	}

	public static function setDBConfig($var, $val)
	{
		\MySQL::getDB()->updateinsert("config", array("var" => $var, "val" => $val, "updatedate" => date("Y-m-d H:i:s")), array("var" => $var));
	}
}
?>