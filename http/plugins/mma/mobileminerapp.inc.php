<?php
/* Main Include
 * @package MobileMinerApp Addon for MinePeon
 * @author  Henry Williams / me@tk1337
 * @version 2.0a
 * @date    2013-11-16
 */

class mobileMinerApp{

  function __construct($install=false){
    if(!$install){
      /* API Info for MobileMinerApp - ** DO NOT CHANGE ** */
      $this->minerName  = "MinePeon";
      $this->apiKey     = "NujIq2mbLN4L8P";
      $this->rootURL    = "https://mobileminer.azurewebsites.net/api";
      
      /* Load options from minepeon.conf */
      $config           = json_decode(file_get_contents('/opt/minepeon/etc/minepeon.conf',false));
      date_default_timezone_set($config->userTimezone);
      
      /* check to see if MobileMinerApp module is enabled */
      if(@$config->mma_enabled === true){
        $this->moduleEnabled = true;
        /* check for email and appKey - exit if not found */
        if(@$config->mma_userEmail && @$config->mma_appKey){
          $this->userEmail  = $config->mma_userEmail;
          $this->appKey     = $config->mma_appKey;
          
          /* look for coinName setting, set default if not found */
          if(@$config->mma_coinName){
            $this->coinName = $config->mma_coinName;
          }else{
            $this->coinName = "Bitcoin"; 
          }
          
          /* look for coinSymbol setting, set default if not found */
          if(@$config->mma_coinSymbol){
            $this->coinSymbol = $config->mma_coinSymbol;
          }else{
            $this->coinSymbol = "BTC";
          }
          
          /* look for algorithm setting, set default if not found */
          if(@$config->mma_algorithm){
            $this->alogrithm  = $config->mma_algorithm;
          }else{
            $this->alogrithm  = "SHA-256";
          }
          
          /* look for machineName setting, set default if not found */
          if(@$config->mma_machineName){
            $this->machineName= $config->mma_machineName;
          }else{
            $this->machineNAme = "MinePeon";
          }
          
          /* look for log setting, set default if not found */
          if(@$config->mma_cronLog){
            $this->cronLog    = $config->mma_cronLog;
          }else{
            $this->cronLog    = false;
          }
          
          /* look for interval setting, set default if not found */
          if(@$config->mma_checkInterval){
            $this->interval   = $config->mma_checkInterval;
          }else{
            $this->interval   = 60;
          }
        }else{
          exit;
        }
      }else{
        exit;
      }
    }
  }
  
  
  /*
   * Gather information about the current statistics of MinePeon, then send format & send them to MMA's API.
   */
  public function updateStatus(){
    if($this->moduleEnabled === true){
      include_once '/opt/minepeon/http/inc/miner.inc.php';
      
      // The below checks were added to support both versions of MinePeon, the older has the function as cgminer, where as the newer has it as miner.
      if(function_exists('cgminer')){
        $mp = cgminer('devs',1);
      }elseif(function_exists('miner')){
        $mp = miner('devs',1);
      }else{
        throw Exception("Could not locate function needed inside miner.inc.php.");
      }
      
      if(is_array($mp)){
        foreach($mp['DEVS'] as $device){
          if(@$device['Temperature']){
            $app[] = array(
              "MinerName"       => $this->minerName,
              "MachineName"     => $this->machineName,
              "Kind"            => $device['Name'],
              "CoinSymbol"      => $this->coinSymbol,
              "CoinName"        => $this->coinName,
              "Algorithm"       => $this->alogrithm,
              "Index"           => $device['ID'], 
              "Enabled"         => true,
              "Status"          => $device['Status'],
              "Temperature"     => $device['Temperature'],
              "AverageHashrate" => $device['MHSav']*1000,
              "CurrentHashrate" => $device['MHS5s']*1000,
              "AcceptedShares"  => $device['Accepted'],
              "RejectedShares"  => $device['Rejected'],
              "HardwareErrors"  => $device['HardwareErrors'],
              "Utility"         => $device['Utility'],
              "Intensity"       => null,
            );
          }else{
            $app[] = array(
              "MinerName"       => $this->minerName,
              "MachineName"     => $this->machineName,
              "Kind"            => $device['Name'],
              "CoinSymbol"      => $this->coinSymbol,
              "CoinName"        => $this->coinName,
              "Algorithm"       => $this->alogrithm,
              "Index"           => $device['ID'], 
              "Enabled"         => true,
              "Status"          => $device['Status'],
              "AverageHashrate" => $device['MHSav']*1000,
              "CurrentHashrate" => $device['MHS5s']*1000,
              "AcceptedShares"  => $device['Accepted'],
              "RejectedShares"  => $device['Rejected'],
              "HardwareErrors"  => $device['HardwareErrors'],
              "Utility"         => $device['Utility'],
              "Intensity"       => null,
            );
          }
        }
        
        $statURL  ="/MiningStatisticsInput?emailAddress=".$this->userEmail."&applicationKey=".$this->appKey."&machineName=".$this->machineName."&apiKey=".$this->apiKey;
        $fullURL  = $this->rootURL.$statURL;
        $this->httpCall($fullURL,$app);
        return true;
      }
      return false;
    }
    return true;
  }
  
  
  /*
   * Build the URL to send to MMA's server, checking for any pending commands in queue.
   */
  public function checkRemoteCommand(){
    if($this->moduleEnabled === true){
      $cmdURL   = "/RemoteCommands?emailAddress=".$this->userEmail."&applicationKey=".$this->appKey."&machineName=".$this->machineName."&apiKey=".$this->apiKey;
      $fullURL  = $this->rootURL.$cmdURL;
      $this->httpCall($fullURL,NULL,"GET");
      return true;
    }
    return true;
  }
  
  
  /*
   * If there was a command found pending from the MMA, process said command, then remove it from the queue on MMA's server.
   */
  public function processCommand(){
    if($this->moduleEnabled === true){
      $command_data = $this->commandFound;
      $command_data = $command_data['0'];
      
      /* process command to CGMiner */
      if(in_array(strtoupper($command_data['CommandText']),array('STOP','START','RESTART'))){
        switch($command_data['CommandText']){
          case "STOP":
            exec('sudo systemctl stop miner');
            break;
          case "START":
            exec('sudo systemctl start miner');
            break;
          case "RESTART":
            include_once('miner.inc.php');
            // The below checks were added to support both versions of MinePeon, the older has the function as cgminer, where as the newer has it as miner.
            if(function_exists('cgminer')){
              cgminer('restart','');
            }elseif(function_exists('miner')){
              miner('restart','');
            }else{
              throw Exception("Could not locate function needed inside miner.inc.php.");
            }
            break;
        }
      }
      
      $delURL   = "/RemoteCommands?emailAddress=".$this->userEmail."&applicationKey=".$this->appKey."&machineName=".$this->machineName."&commandId=".$command_data['Id']."&apiKey=".$this->apiKey;
      $fullURL  = $this->rootURL.$delURL;
      $this->__deleteCommand($fullURL);
    }
    return true;
  }
  
  
  /*
   * Execute an update to MMA servers.
   */
  public function cronUpdate(){
    if($this->moduleEnabled === true){
      if(!$this->interval){
        $this->interval = 20; // default 20sec
      }
      if($this->interval < 20){ //requests going too fast will hit throttle control.
        $this->interval  = 20;
      }elseif($this->interval > 60){ //requests going too slow will allow MobileMinerApp to think the machine is down.
        $this->interval = 60;
      }
      
      if($this->updateStatus()){
        if($this->cronLog){echo date('Y-m-d h:i:s')." - update statistics sent to server successfully.\r\n";}
        if($this->interval*2 < 60){
          sleep($this->interval);
          if($this->updateStatus()){
            if($this->cronLog){echo date('Y-m-d h:i:s')." - update statistics sent to server successfully.\r\n";}
            return true;
          }
        }else{
          return true;
        }
      }
      return false;
    }
    return true;
  }
  
  
  /*
   * Check MMA servers for incoming command.
   */
  public function cronCheck(){
    if($this->moduleEnabled === true){
      if($this->checkRemoteCommand()){
        if($this->checkRemoteCommand()){
          return true;
        }
      }
      return false;
    }
    return true;
  }
  
  /*
   * Install CronJobs to support MMA Addon
   */
  public function installCron(){
    $file   = "/var/spool/cron/minepeon";
    $data   = file($file);
    $userNew= false;
    
    /* Check to see if there are any cron callse to MMA addon from previous install */
    foreach($data as $line => $str){
      if(strpos(strtolower($str),"mobileminerapp") === false){
        $cron[] = $str;
      }else{
        $useNew = true;
      }
    }
    
        if(@$useNew === true){
          exec('cat /dev/null > /var/spool/cron/minepeon');
          foreach($cron as $str){
            exec('echo "'.$str.'" >> /var/spool/cron/minepeon');
          }
          exec('echo "# MobileMinerApp crons" >> /var/spool/cron/minepeon');
          exec('echo "*/1 * * * * /usr/bin/php /opt/minepeon/http/mma/mobileminerapp.php update" >> /var/spool/cron/minepeon');
          exec('echo "*/2 * * * * /usr/bin/php /opt/minepeon/http/mma/mobileminerapp.php check" >> /var/spool/cron/minepeon');
        }else{
          if(!$file){
            $file = "/var/spool/cron/minepeon";
          }      
          $append = fopen($file,'a') or die("\nERROR: Could not open crontab file.\n\n");
          $lines  ="\n#MobileMinerApp Crons
    */1 * * * * /usr/bin/php /opt/minepeon/http/mma/mobileminerapp.php update
    */2 * * * * /usr/bin/php /opt/minepeon/http/mma/mobileminerapp.php check";
          fwrite($append,$lines);
          fclose($append);
    }
  }
  
  
  /*
   * Install variable into minepeon configuration file for MMA addon to work properly.
   */
  public function installConf($settings){
    $file   = "/opt/minepeon/etc/minepeon.conf";
    $open   = fopen($file,'r') or die("Can not open minepeon.conf");
    $data   = fread($open,filesize($file));
    $conf   = json_decode($data,true);
    if(is_array($conf)){
      $conf['mma_enabled']      = true;
      $conf['mma_userEmail']    = $settings['email'];
      $conf['mma_appKey']       = $settings['key'];
      if(isset($settings['name'])){
        $conf['mma_machineName']  = $settings['name'];
      }
      
      $write = fopen($file,'w');
      fwrite($write,json_encode($conf,JSON_PRETTY_PRINT));
      fclose($write);
    }
  }
  
  /*
   * Remove MMA plugin
   */
  public function uninstall(){
    /* remove cron jobs */
    $file   = "/var/spool/cron/minepeon";
    $data   = file($file);
    
    foreach($data as $line => $str){
      if(strpos(strtolower($string),"mobileminerapp") === false){
        $cron[] = $str;
      }
    }
    exec('cat /dev/null > /var/spool/cron/minepeon');
    foreach($cron as $str){
      exec('echo "'.$str.'" >> /var/spool/cron/minepeon');
    }
    
    /* disable in minepeon.conf */
    $file   = "/opt/minepeon/etc/minepeon.conf";
    $open   = fopen($file,'r') or die("Can not open minepeon.conf");
    $data   = fread($open,filesize($file));
    $conf   = json_decode($data,true);
    if(is_array($conf)){
      $conf['mma_enabled']      = false;
      $conf['mma_userEmail']    = "";
      $conf['mma_appKey']       = "";
      
      $write = fopen($file,'w');
      fwrite($write,json_encode($conf,JSON_PRETTY_PRINT));
      fclose($write);
    }
  }
  
  
  /*
   * Simple little cURL function with some JSON decoding/encoding
   *
   * @return    null / object
   */
  private function httpCall($url,$data,$type="POST"){
    if($data){
      $data = json_encode($data);
    }

    $ch = curl_init();
    curl_setopt($ch,CURLOPT_URL,$url);
    curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
    
    if($type == "POST"){
      curl_setopt($ch,CURLOPT_HTTPHEADER,array('Content-type: application/json'));
      curl_setopt($ch,CURLOPT_CUSTOMREQUEST,"POST");
      curl_setopt($ch,CURLOPT_POSTFIELDS,$data);
    }elseif($type == "GET"){
      curl_setopt($ch,CURLOPT_CUSTOMREQUEST,"GET");
      curl_setopt($ch,CURLOPT_BINARYTRANSFER,true);
    }elseif($type == "DELETE"){
      curl_setopt($ch,CURLOPT_CUSTOMREQUEST,"DELETE");
    }
    $result = curl_exec($ch);
    
    if($type == "GET" && $result && $result != "[]"){
      $this->commandFound   = json_decode($result,true);
      curl_close($ch);
      $this->processCommand();
    }else{
      curl_close($ch);
    }
  }
  
  
  /*
   * Made this as a separate function, as executing a cURL while still processing one, just turns out with negative results, so I broke it off
   * rather than calling the same function within itself.
   *
   * @param   string  $url  The url containing the response that a command has been processed so that MMA can remove it from it's queue.
   */
  private function __deleteCommand($url){
    $ch = curl_init();
      curl_setopt($ch,CURLOPT_URL,$url);
      curl_setopt($ch,CURLOPT_CUSTOMREQUEST,"DELETE");
    $result = curl_exec($ch);
    curl_close($ch);
    // Later I will put in some error checking, to make sure an http 200/201 was returned, if so return a boolean.
  }
}
?>
