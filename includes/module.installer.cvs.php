<?php

// $Id$

//@wiki include CVS Class.
require_once drupal_get_path('module', 'module_installer') . '/includes/cvs.class.php';

/**
 * ModuleInstallerCVS is a Onion Layer for drupal CVS PServer API over
 * GenericCVS Client.
 *
 * ModuleInstaller provides functions to download, check version, and get
 * module list for drupal using CVS.
 */
class ModuleInstallerCVS extends GenericCVSClient {
  /**
   * PServer module for drupal module. 
   */
  const CVS_MODULE = "contributions/modules";

  /**
   * callback function which is called when each file is downloaded on local
   * installation.
   *
   * @var String
   */
  private $downloadCallback;
  /**
   * Array of parameters that you need in $downloadCallback function.
   * @var Array
   */
  private $downloadCallbackParams;
  /**
   * Callback function which is called when whole module download is complete.
   *
   * @var String
   * Valid method name to invoke.
   */
  private $downloadCompleteCallback;
  /**
   * Extra Params that are needs to be passed in downloadCompleteCallback
   * variable function.
   *
   * @var Array
   * Array of parameters.
   */
  private $downloadCompleteCallbackParams;

  /**
   * Constructor for ModuleInstallerCVS Class.
   */
  function __construct() {
    parent::__construct();
    $this->setUrl("pserver://anonymous:anonymous@cvs.drupal.org:2401/cvs/drupal-contrib");
  }

  /**
   * This function does connection prerequisits for Drupal CVS Server. connect
   * also authenticates ModuleInstallerCVS with anonymous user to drupal server.
   *
   * This function is called internally by other class functions before doing
   * their work.
   *
   * @brief Connect function.
   */
  protected function connect() {
    parent::connect();
    $this->authenticate();
  }

  /**
   * getModuleList function fetches module list available on Drupal CVS server.
   *
   *   Module names are in lower case letters only. I have found some
   * uncompatible names also. Some developers have commited to CVS server with
   * CamelCase and Title case, But Drupal Module moderators don't allow to
   * commit on those. This function fetches all list whether it is valid on
   * Drupal CVS servers or not.
   *
   *   You can use a array_filter to filter all those that aren't valid (which
   * will not give you source :(, But they exist.), similar to
   * _valid_drupal_modules_filter drupal function.
   *
   * @return
   *   Associative array of modules on Drupal CVS Server.
   */
  public function getModuleList() {
    $this->connect();
    $this->writeStream("Argument " . ModuleInstallerCVS::CVS_MODULE);
    $this->writeStream("ls");

    $buffer = $this->readAll();

    $lines = explode("\n", $buffer);

    $modules = array();
    foreach ($lines as $line) {
      if (substr($line, 0, 1) == "M") {
        $modules[substr($line, 2)] = substr($line, 2);
      }
    }

    $this->close();
    return $modules;
  }

  /**
   * downloadModule function downloads a drupal module to current module
   * installation path (sites/[yoursitename]/modules if there is a multisite
   * installation or sites/all/modules for default installation).
   *
   * @param $moduleName
   *    CVS name of a module. this is similar to module name found on
   *    drupal module project page.
   *    eg.
   *      admin_menu => Administration Menu
   *      views      => Views Module.
   * @param $revision
   *    Valid Drupal CVS Revision.
   *   
   * @return
   *    TRUE on successful download else FALSE
   */
  public function downloadModule($moduleName, $revision = false) {
    $this->connect();

    if ($revision) {
      $this->writeStream("Argument -r$revision");
    }

    $this->writeStream("Argument " . ModuleInstallerCVS::CVS_MODULE . "/$moduleName");
    $this->writeStream("co");

    $conf_dir = (conf_path(false) == "sites/default") ? "sites/all/modules" : conf_path(false) . "/modules";
    $files = array();
    $response = array();
    //@todo make a cache for downloaded modules. so that it doesn't need to
    //      fetch at all times.
    //@todo compress it so that it takes less space.
    while ($response[] = $this->readStreamLine(512)) {

      if ($response[0] == "ok")
        break;
      if (substr($response[0], 0, 14) == "E cvs checkout")
        array_shift($response);
      if (count($response) < 6)
        continue;

      $content = "";
      $contentLength = (substr($response[5], 0, 1) == "z") ? substr($response[5], 1) : $response[5];
      while (strlen($content) < $contentLength) {
        $remaining = substr($response[5], 1) - strlen($content);
        switch (TRUE) {
          case ($remaining > 4096):
            $content .= $this->readStream(4096);
            break;
          case ($remaining > 1024):
            $content .= $this->readStream(1024);
            break;
          case ($remaining > 512):
            $content .= $this->readStream(512);
            break;
          case ($remaining > 128):
            $content .= $this->readStream(128);
            break;
          case ($remaining > 64):
            $content .= $this->readStream(64);
            break;
          case ($remaining > 32):
            $content .= $this->readStream(32);
            break;
          case ($remaining > 16):
            $content .= $this->readStream(16);
            break;
          case ($remaining > 8):
            $content .= $this->readStream(8);
            break;
          case ($remaining > 4):
            $content .= $this->readStream(4);
            break;
          case ($remaining > 2):
            $content .= $this->readStream(2);
            break;
          default:
            $content .= $this->readStream(1);
            break;
        }
      }

      $file = (substr($response[0], 0, 4) == "M U ") ? substr($response[0], 4) : $response[0];

      //@wiki don't write until all file content is fetched :(
      $files[$file] = new stdClass();
      $files[$file]->content = $content;
      $files[$file]->compressed = (substr($response[5], 0, 1) == "z");

      if (isset($this->downloadCallback)) {
        $default_params = $this->downloadCallbackParams;
        array_unshift($default_params, substr($response[5], 1));
        call_user_func_array($this->downloadCallback, $default_params);
      }

      $response = array();
    }

    foreach ($files as $file => $content) {
      $file = str_replace(ModuleInstallerCVS::CVS_MODULE, $conf_dir, $file);
      $this->_create_directories(dirname($file), 0755);
      if ($content->compressed) {
        file_put_contents($file, gzinflate(substr($content->content, 10)));
      } else {
        file_put_contents($file, $content->content);
      }
    }

    if (isset($this->downloadCompleteCallback)) {
      $default_params = $this->downloadCompleteCallbackParams;
      call_user_func_array($this->downloadCompleteCallback, $default_params);
    }

    $this->close();

    return ($response[0] == "ok");
  }

  /**
   * registerForDownloadEvent registers function for callback on downloading a
   * file event.
   *
   * @param $function_name
   *    callback function name.
   *
   * @param $extra_param
   *    array of parameters that will be passed to callback function.
   */
  public function registerForDownloadEvent($function_name, $extra_param = array()) {
    if (function_exists($function_name)) {
      $this->downloadCallback = $function_name;
      $this->downloadCallbackParams = $extra_param;
    }
  }

  /**
   * registers function for callback on download complete event.
   *
   * @param $function_name
   *    callback function name.
   *
   * @param $extra_param
   *    array of parameters that will be passed to callback function.
   */
  public function registerForDownloadCompleteEvent($function_name, $extra_param = array()) {
    if (function_exists($function_name)) {
      $this->downloadCompleteCallback = $function_name;
      $this->downloadCompleteCallbackParams = $extra_param;
    }
  }

  /**
   * getModuleVersion fetches module's versions available on Drupal CVS Servers.
   * 
   *  This can have version names that you haven't heard off, probably more beta
   * than stable release. Please see drupal.org's module page first as 
   * downloading beta and alpha can make your drupal installation unstable.
   *
   * @param $module
   *    CVS name of a module. this is similar to module name found on
   *    drupal module project page.
   *    eg.
   *      admin_menu => Administration Menu
   *      views      => Views Module.
   * 
   * @return
   *    associative array of versions available on Drupal CVS Servers.
   */
  public function getModuleVersion($module) {
    $this->connect();
    $this->writeStream("Argument " . ModuleInstallerCVS::CVS_MODULE . "/$module");
    $this->writeStream("rlog -h");

    $buffer = $this->readAll();
    $revisions = explode("M =============================================================================\n", $buffer);
    $versions = null;
    foreach ($revisions as $revision) {
      $revision_info = explode("\n", array_shift(explode("M ----------------------------\n", $revision)));
      foreach ($revision_info as $revision_line) {
        $fields = explode(":", $revision_line);
        $start_collecting = false;
        switch ($fields[0]) {
          case "M symbolic names":
            $start_collecting = true;
            continue 2;
          case "M keyword substitution":
            $start_collecting = false;
        }
        if ($start_collecting) {
          $versions[substr($fields[0], 3)] = $this->_get_version_text(substr($fields[0], 3));
        }
      }
    }

    if (!isset($versions) && !is_array($revisions))
      throw new Exception("Error occured parsing cvs revision log. \n $buffer", 10088);

    //@todo fetch list based on current version of drupal.
    if (is_array($versions)) {
      krsort($versions);
    }

    if (is_array($revisions)) {
      if (isset($versions)) {
        $versions = array('HEAD' => 'HEAD') + $versions;
      } else {
        $versions = array('HEAD' => 'HEAD');
      }
    }

    $this->close();

    return $versions;
  }

  /**
   * _get_version_text helper function converts CVS versions to one that is
   * familiar like on drupal's module page.
   *
   * @param $version
   *    Drupal CVS Version.
   * @return
   *    version string as it is shown on drupal's module page.
   * 
   * @see Line#295
   */
  private function _get_version_text($version) {
    $ver = substr($version, 7);
    $ver = str_replace("--", ".x.", $ver);
    $ver = str_replace("-", ".", $ver);
    $ver = str_replace("x.", "x-", $ver);
    $ver = str_replace(".ALPHA.", "-alpha", $ver);
    $ver = str_replace(".BETA.", "-beta", $ver);
    $ver = str_replace(".RC.", "-rc", $ver);
    $ver = str_replace(".ALPHA", "-alpha", $ver);
    $ver = str_replace(".BETA", "-beta", $ver);
    $ver = str_replace(".RC", "-rc", $ver);
    return $ver;
  }

  /**
   * creates directory path if it doesn't exists.
   *
   * @brief _create_directories helper function.
   * @see Line#206
   *
   * @param $dirName
   *    path that need to be built
   * @param $rights
   *    permission on directory that are created.
   */
  private function _create_directories($dirName, $rights=0755) {
    $dirs = explode('/', $dirName);
    $dir = '';
    foreach ($dirs as $part) {
      $dir .= $part . '/';
      if (!is_dir($dir) && strlen($dir) > 0)
        mkdir($dir, $rights);
    }
  }

}