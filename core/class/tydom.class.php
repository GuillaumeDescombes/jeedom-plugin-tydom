<?php

/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jeedom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
 */

/* * ***************************Includes********************************* */
require_once __DIR__  . '/../../../../core/php/core.inc.php';
require_once __DIR__  . '/../php/tydom.inc.php';

class tydom extends eqLogic {
    /*     **************************Attributs****************************** */
    
    /*     ************************Methode static*************************** */

    /*
     * Fonction exécutée automatiquement toutes les minutes par Jeedom
     */
      public static function cron() {
        $countPolling = cache::byKey('tydom::countPolling') -> getValue() + 1;
        $freqPolling = config::byKey('tydom::freqPolling', 'tydom');
        $needtoRefreshAll = cache::byKey('tydom::needtoRefreshAll') -> getValue();
        log::add('tydom', 'debug', "cron: freqPolling = " . $freqPolling . "; countPolling = " . $countPolling . "; needtoRefreshAll = " . ($needtoRefreshAll?"true":"false"));
        if ($freqPolling <=1 || ($countPolling % $freqPolling) == 0) {
          $countPolling=0;
          log::add('tydom', 'info', 'cron: Pulling data for all the devices');
          self::pull();
        }
        cache::set('tydom::countPolling', $countPolling);
         
        if ($needtoRefreshAll) {
          log::add('tydom', 'debug', "cron: refreshAll");
          self::refreshAll();
        }
      }
     

    /*
     * Fonction exécutée automatiquement toutes les 5 minutes par Jeedom 
     
      public static function cron5() {
      }
     */


    /*
     * Fonction exécutée automatiquement toutes les heures par Jeedom
      public static function cronHourly() {

      }
     */

    /*
     * Fonction exécutée automatiquement tous les jours par Jeedom
     */
      public static function cronDaily() {
      	self::refreshAll();
      }
     
      private static function tydomGetRequest($request) {
        log::add('tydom', 'debug', "tydomGetRequest: " . $request);

        $portServer = config::byKey('tydom::portServer', 'tydom');
        if (strlen($portServer)==0 || $portServer==0) $port=8080;

        $url="http://127.0.0.1:" . $portServer . "/" . $request;
        log::add('tydom', 'debug', "url: " . $url);
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPGET, true);
        //Set CURLOPT_RETURNTRANSFER so that the content is returned as a variable.
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $data = curl_exec($ch);
        curl_close($ch);
        return json_decode($data);
      }
      
      private static function tydomSetRequest($request, $name, $value) {
        log::add('tydom', 'info', "tydomSetRequest: " . $request . "; SET " . $name . "=" . $value);

        $portServer = config::byKey('tydom::portServer', 'tydom');
        if (strlen($portServer)==0 || $portServer==0) $port=8080;

        $url="http://127.0.0.1:" . $portServer . "/" . $request . "/" . $name ."/" . $value;
        log::add('tydom', 'debug', "url: " . $url);
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPGET, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $data = curl_exec($ch);
        curl_close($ch);
        return json_decode($data);
      }

    public static function tydomProceedRequestDevicesData($devices) {
      if (!is_array($devices)) return false;
      log::add('tydom', 'debug', "tydomProceedRequestDevicesData:");
      $needtoRefreshAll = cache::byKey('tydom::needtoRefreshAll') -> getValue();
      $eqLogicUptoDate=true;
      foreach ($devices as $device) {
        $eqLogicLogicalId=$device->id;
        log::add('tydom', 'debug', " - for eqLogicLogicalId = " . $eqLogicLogicalId);
        $eqLogics= self::byLogicalId($eqLogicLogicalId, 'tydom', true);
        $endpoints=$device->endpoints;
        foreach ($eqLogics as $eqLogic) {
            if (is_object($eqLogic) && $eqLogic->getIsEnable()) {
              foreach ($endpoints as $endpoint) {
                log::add('tydom', 'debug', "loop - logicalId=" . $eqLogic -> getLogicalId() . ", logicalId=" . $eqLogicLogicalId . ", endpoint=" . $endpoint -> id . ", endpoint=" . $eqLogic -> getConfiguration("endpoint"));
                if ($eqLogic -> getConfiguration("endpoint") == $endpoint -> id) {
                  $allData=$endpoint->data;
                  $error=$endpoint->error;
                  log::add('tydom', 'debug', "eqLogic(" . $eqLogicLogicalId . ", " . $endpoint -> id . "): error = " . $error);
                  foreach ($allData as $data) {
                    if (isset($data->name) && isset($data->validity)) {
                      // OK
                      if ($data->validity != "upToDate") {
                        $eqLogicUptoDate = false;
                        log::add('tydom', 'info', "validity of '" .$eqLogicLogicalId . "::" . $endpoint -> id . "::" . $data->name . "' = " . $data->validity);
                      }
                      $cmd =  $eqLogic -> getCmd('info', $data->name);
                      if (is_object($cmd)) {
                        $cmdNewValue=$data->value;
                        if (is_null($cmdNewValue)) $cmdNewValue=0;
                        log::add('tydom', 'info', "eqLogic(" . $eqLogicLogicalId . ", " . $endpoint -> id . "): cmdInfo(" . $cmd->getLogicalId() . ")=" . $cmdNewValue);
                        $eqLogic -> checkAndUpdateCmd($cmd, $cmdNewValue);
                      }
                    }
                  } 
                }
              }
            }
        }
      }
      if (!$eqLogicUptoDate && !$needtoRefreshAll) {
        log::add('tydom', 'warning', 'ProceedRequestDevicesData: Some data are not up to date. I will refresh the data later.');
        cache::set('tydom::needtoRefreshAll', true);
      }   
      return true;
    }
    
    public static function tydomProceedInfo($infos) {
      //do nothing
      log::add('tydom', 'info', "tydomProceedInfo:");
    }

    public static function pull() {
      $devices = self::tydomGetRequest('devices/data');
      self::tydomProceedRequestDevicesData($devices);
    }

    public static function refreshAll() {
      log::add('tydom', 'warning', 'RefreshAll: Refreshing all the data');
      self::tydomGetRequest('refresh/all');
      cache::set('tydom::needtoRefreshAll', false);
    }
    
    public static function getInfo() {
      return tydom::tydomGetRequest('info');
    }    

    public static function syncEqLogic() {
      log::add('tydom', 'debug', "syncEqLogic: beg");
      $eqLogics = eqLogic::byType('tydom');
      $configs = self::tydomGetRequest('configs/file');
      foreach ($configs->endpoints as $device) {
        $eqLogicLogicalId=$device->id_device;
        $eqLogic_found= self::byLogicalId($eqLogicLogicalId, 'tydom');
        if (!is_object($eqLogic_found)) {
          $eqLogic = new eqLogic();
          $eqLogic->setEqType_name('tydom');
          $eqLogic->setIsEnable(1);
          $eqLogic->setIsVisible(1);
          $eqLogic->setLogicalId($eqLogicLogicalId);
          $eqLogic->setConfiguration('endpoint', $eqLogicLogicalId);
          $eqLogic->setName($eqLogicLogicalId . '_' . $device->name);
          $eqLogic->save();

          /***********************************/
          //Infos
          $refresh = new tydomCmd();
          $refresh->setName('Rafraichir');
          $refresh->setOrder(0);
          $refresh->setEqLogic_id($eqLogic->getId());
          $refresh->setLogicalId('refresh');
          $refresh->setType('action');
          $refresh->setSubType('other');
          $refresh->save();
          if ($device->first_usage == "hvac") {
            $eqLogic->setCategory('heating', '1');
            $eqLogic->save();
					
            $authorization = new tydomCmd();
            $authorization->setName("Mode de chauffe");
            $authorization->setEqLogic_id($eqLogic->getId());
            $authorization->setLogicalId("authorization");
            $authorization->setOrder(1);
            $authorization->setType('info');
            $authorization->setSubType('string');
            $authorization->save();
                    
            $hvacMode = new tydomCmd();
            $hvacMode->setName("Mode");
            $hvacMode->setEqLogic_id($eqLogic->getId());
            $hvacMode->setLogicalId("hvacMode");
            $hvacMode->setOrder(2);
            $hvacMode->setType('info');
            $hvacMode->setSubType('string');
            $hvacMode->save();
                    
            $setpoint = new tydomCmd();
            $setpoint->setName("Consigne");
            $setpoint->setEqLogic_id($eqLogic->getId());
            $setpoint->setLogicalId("setpoint");
            $setpoint->setOrder(3);
            $setpoint->setType('info');
            $setpoint->setSubType('numeric');
            $setpoint->setUnite('°C');
            $setpoint->setIsHistorized(1);
            $setpoint->setTemplate('dashboard', 'tile');
            $setpoint->setTemplate('mobile', 'tile');
            $setpoint->setDisplay('generic_type', 'THERMOSTAT_SETPOINT');
            //$setpoint->setDisplay('forceReturnLineAfter', '1');
            $setpoint->setConfiguration('historizeMode', "none");
            $setpoint->setConfiguration('historyPurge', "-1 year");
            $setpoint->save();

            $temperature = new tydomCmd();
            $temperature->setName("Température");
            $temperature->setEqLogic_id($eqLogic->getId());
            $temperature->setLogicalId("temperature");
            $temperature->setOrder(4);
            $temperature->setType('info');
            $temperature->setSubType('numeric');
            $temperature->setUnite('°C');
            $temperature->setIsHistorized(1);
            $temperature->setTemplate('dashboard', 'tile');
            $temperature->setTemplate('mobile', 'tile');
            $temperature->setDisplay('generic_type', 'THERMOSTAT_TEMPERATURE');
            //$temperature->setDisplay('forceReturnLineAfter', '1');
            $temperature->setConfiguration('historizeMode', "none");
            $temperature->setConfiguration('historyPurge', "-1 year");
            $temperature->save();
          }
        } else {
                // Update !
          }
        log::add('tydom', 'debug', "Sync of id device: " . $eqLogicLogicalId);
      }
      log::add('tydom', 'debug', "syncEqLogic: end");
    }
    
    public static function deamon_info() {
      $return = array();
      $return['state'] = 'nok';
      $pid_file = jeedom::getTmpFolder('tydom') . '/deamon.pid';
      if (file_exists($pid_file)) {
        if (posix_getsid(trim(file_get_contents($pid_file)))) {
          $return['state'] = 'ok';
        } else {
            shell_exec(system::getCmdSudo() . 'rm -rf ' . $pid_file . ' 2>&1 > /dev/null');
          }
      }
      $return['launchable'] = 'ok';
      return $return;
    }
	
    public static function deamon_start($_debug = false) {
      self::deamon_stop();
      $deamon_info = self::deamon_info();
      if ($deamon_info['launchable'] != 'ok') {
        throw new Exception(__('Veuillez vérifier la configuration', __FILE__));
      }
      $tydom_gateway_path = dirname(__FILE__) . '/../../resources/tydom-gateway';
      $host = config::byKey('tydom::host', 'tydom');
      if (strlen($host)==0) $host="127.0.0.1";
      $user = config::byKey('tydom::login', 'tydom');
      if (strlen($user)==0) $user="default";
      $password = config::byKey('tydom::password', 'tydom');
      if (strlen($password)==0) $password="no_password";
      $remote = config::byKey('tydom::remote', 'tydom');
      if (strlen($remote)==0) $remote="0";
      $portServer = config::byKey('tydom::portServer', 'tydom');
      if (strlen($portServer)==0) $portServer="8080";
      $apiKey = jeedom::getApiKey('tydom');
      $callback = network::getNetworkAccess('internal', 'proto:127.0.0.1:port:comp') . '/plugins/tydom/core/php/jeeTydom.php';
      $logLevel = log::convertLogLevel(log::getLogLevel('tydom'));

      $cmd  = 'node --no-warnings';
      $cmd .= ' --openssl-config=' . $tydom_gateway_path . '/openssl.cnf';
      $cmd .= ' ' . $tydom_gateway_path . '/app.js';
      $cmd .= ' --host ' . $host;
      $cmd .= ' --user ' . $user;
      $cmd .= ' --password '. $password;
      if ($remote!="0") $cmd .= ' --remote ' . $remote;
      $cmd .= ' --pid ' . jeedom::getTmpFolder('tydom') . '/deamon.pid';
      $cmd .= ' --port ' . $portServer;
      $cmd .= ' --apikey ' . $apiKey;
      $cmd .= ' --callback ' . $callback;
      $cmd .= ' --loglevel ' . $logLevel;

      $cmdLog  = 'node --no-warnings';
      $cmdLog .= ' --openssl-config=' . $tydom_gateway_path . '/openssl.cnf';
      $cmdLog .= ' ' . $tydom_gateway_path . '/app.js ';
      $cmdLog .= ' --host ' . $host;
      $cmdLog .= ' --user ' . $user;
      $cmdLog .= ' --password ******';
      if ($remote!="0") $cmdLog .= ' --remote ' . $remote;
      $cmdLog .= ' --pid ' . jeedom::getTmpFolder('tydom') . '/deamon.pid';
      $cmdLog .= ' --port ' . $portServer;
      $cmdLog .= ' --apikey ' . $apiKey;
      $cmdLog .= ' --callback ' . $callback;
      $cmdLog .= ' --loglevel ' . $logLevel;

      log::add('tydom', 'info', 'Lancement démon tydom : ' . $cmdLog);
      exec($cmd . ' >> ' . log::getPathToLog('tydomd') . ' 2>&1 &');
      $i = 0;
      while ($i < 30) {
        $deamon_info = self::deamon_info();
        if ($deamon_info['state'] == 'ok') {
          break;
        }
        sleep(1);
        $i++;
      }
      if ($i >= 30) {
        log::add('tydom', 'error', 'Impossible de lancer le démon tydom', 'unableStartDeamon');
        return false;
      }
      message::removeAll('tydom', 'unableStartDeamon');
      log::add('tydom', 'info', 'Démon tydom lancé sur le port '. $portServer);
    }
	
    public static function deamon_stop() {
      try {
        $deamon_info = self::deamon_info();
        if ($deamon_info['state'] == 'ok') {
          try {
            self::tydomGetRequest('stop');
          } catch (Exception $e) {
            }
        }
        $pid_file = jeedom::getTmpFolder('tydom') . '/deamon.pid';
        if (file_exists($pid_file)) {
          $pid = intval(trim(file_get_contents($pid_file)));
          system::kill($pid);
        }
        sleep(1);
      } catch (\Exception $e) {
        }
    }
	
    public static function dependancy_info($_refresh = false) {
      $return = array();
      $return['log'] = 'tydom_update';
      $return['progress_file'] = jeedom::getTmpFolder('tydom') . '/dependance';
      $return['state'] = (self::compilationOk()) ? 'ok' : 'nok';
      return $return;
    }

    public static function dependancy_install() {
      log::remove(__CLASS__ . '_update');
      return array('script' => dirname(__FILE__) . '/../../resources/install_#stype#.sh ' . jeedom::getTmpFolder('tydom') . '/dependance', 'log' => log::getPathToLog(__CLASS__ . '_update'));
    }
	
   
    public static function compilationOk() {
      if (shell_exec('ls /usr/bin/node 2>/dev/null | wc -l') == 0) {
        return false;
      }
      return true;
    }

    /*     * *********************Méthodes d'instance************************* */
	
    public function refresh() {
      if ($this->getIsEnable()) {
        $deviceId = $this->getLogicalId();
        $endpointId= $this -> getConfiguration("endpoint") ;
        log::add('tydom', 'info', "refresh (" . $deviceId . "/" . $endpointId .")");
        $device = self::tydomGetRequest('device/' . $deviceId . '/endpoints/' . $endpointId . "/data");

        $needtoRefreshAll = cache::byKey('tydom::needtoRefreshAll') -> getValue();
        $error=$device->error;
        log::add('tydom', 'debug', "eqLogic(" . $deviceId . ", " . $endpointId ."): error = " . $error);
        if ($endpointId == $device -> id) {
          $allData=$device->data;
          $eqLogicUptoDate=true;
          foreach ($allData as $data) {
            if (isset($data->name) && isset($data->validity)) {
              // OK
              $eqLogicUptoDate = $eqLogicUptoDate && ($data->validity == "upToDate");
              $cmd =  $this -> getCmd('info', $data->name);
              if (is_object($cmd)) {
                $cmdNewValue=$data->value;
                if (is_null($cmdNewValue)) $cmdNewValue=0;
                log::add('tydom', 'debug', "eqLogic(" . $deviceId . ", " . $endpointId . "): cmd(" . $cmd->getLogicalId() . ")=" . $cmdNewValue);
                $this -> checkAndUpdateCmd($cmd, $cmdNewValue);
              }
            }
          }
          log::add('tydom', 'debug', "eqLogic(" . $deviceId . ", " . $endpointId ."): upToDate=". ($eqLogicUptoDate ? 'true' : 'false'));
          if (!$eqLogicUptoDate && !$needtoRefreshAll) {
            log::add('tydom', 'warning', 'Refresh: Some data are not up to date. I will refresh later.');
            cache::set('tydom::needtoRefreshAll', true);
          }
        }
      }
    }
    
    public function setTydomValue($name, $value) {
      if ($this->getIsEnable()) {
        $deviceId = $this->getLogicalId();
        $endpointId= $this -> getConfiguration("endpoint") ;
        $result = self::tydomSetRequest('device/' . $deviceId . '/endpoints/' . $endpointId . "/data", $name, $value);
        //delaiUpdateData
        $delai = $this -> getConfiguration("delaiUpdateData");
        if (strlen($delay)==0) $delay=0;
        if ($delay != 0) {
          log::add('tydom', 'info', 'setTydomValue: wait for ' . $delay . 's');
          sleep($delay);
          log::add('tydom', 'debug', 'setTydomValue: refreshing the data of the endpoints');
          self::refresh();
        }
        return $result;
      }
    }    

    public function preInsert() {
        
    }

    public function postInsert() {
        
    }

    public function preSave() {
        
    }

    public function postSave() {
      $this->refreshWidget();
    }

    public function preUpdate() {
        
    }

    public function postUpdate() {
        
    }

    public function preRemove() {
        
    }

    public function postRemove() {
        
    }

    /*
     * Non obligatoire mais permet de modifier l'affichage du widget si vous en avez besoin
      public function toHtml($_version = 'dashboard') {

      }
     */

    /*
     * Non obligatoire mais ca permet de déclencher une action après modification de variable de configuration
    public static function postConfig_<Variable>() {
    }
     */

    /*
     * Non obligatoire mais ca permet de déclencher une action avant modification de variable de configuration
    public static function preConfig_<Variable>() {
    }
     */

    /*     * **********************Getteur Setteur*************************** */
}

class tydomCmd extends cmd {
    /*     * *************************Attributs****************************** */


    /*     * ***********************Methode static*************************** */


    /*     * *********************Methode d'instance************************* */

    /*
     * Non obligatoire permet de demander de ne pas supprimer les commandes même si elles ne sont pas dans la nouvelle configuration de l'équipement envoyé en JS*/
    public function dontRemoveCmd() {
      if ($this->getLogicalId() == 'refresh') {
        return true;
      }
      return false;
    }


    public function execute($_options = array()) {
      if ($this->getType() != 'action') {
        return;
      }
    	$eqLogic = $this->getEqLogic();
      if (!is_object($eqLogic) || $eqLogic->getIsEnable() != 1) {
        throw new Exception(__('Equipement desactivé impossible d\éxecuter la commande : ' . $this->getHumanName(), __FILE__));
      }
      $logicalId=$this->getLogicalId();
      if ($logicalId == "refresh") {
        log::add('tydom','debug','command: ' . $logicalId);
        $eqLogic->refresh();
        return true;
      }
      $value=$this->getConfiguration('value');
      switch ($this->getSubType()) {
        case 'message':
          $value = str_replace('#message#', $_options['message'], $value);
          break;
        case 'slider':
          $value = str_replace('#slider#', $_options['slider'], $value);
          break;
        case 'color':
          $value = str_replace('#color#', $_options['color'], $value);
          break;
        case 'select':
          $value = str_replace('#select#', $_options['select'], $value);
          break;
      }
      log::add('tydom','debug','command: ' . $logicalId . '; value= ' . $value);
      switch ($logicalId) {
        case "setpoint":
          $result = $eqLogic->setTydomValue($logicalId, $value);
          return true;
        case "hvacMode":
          $result = $eqLogic->setTydomValue($logicalId, $value);
          return true;
        case "authorization":
          $result = $eqLogic->setTydomValue($logicalId, $value);
          return true;
      }
      return true;
    }

    /*     * **********************Getteur Setteur*************************** */
}


