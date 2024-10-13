<?php
/**
 * Copyright (c) 2018 Jeedom-Tydom contributors
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

require_once dirname(__FILE__) . "/../../../../core/php/core.inc.php";

if (!jeedom::apiAccess(init('apikey'), 'tydom')) {
    echo __('Vous n\'êtes pas autorisé à effectuer cette action', __FILE__);
    die();
}

$results = json_decode(file_get_contents("php://input"));
$response = array('success' => true);

$action = isset($results -> action) ? $results -> action : '';
if ($action == "test") {
  log::add('tydom', 'info', "Daemon is checking the call back; return {success:true}");
} 
elseif ($action == "request") {
  log::add('tydom', 'info', "Receiving a 'request' callback action");
  if (isset($results -> data) && isset($results -> uri)) {
    if ($results-> uri == "/devices/data") {
      //processing request /devices/data
      $devices = $results -> data;
      tydom::tydomProceedRequestDevicesData($devices);
    }
    if ($results-> uri == "/info") {
      //processing request /info
      $infos = $results -> data;
      tydom::tydomProceedInfo($infos);
    }    
  } else {
      log::add('tydom', 'error', "Parameters are not correctly defined. I am waiting for data and uri = /devices/data or /info");
      log::add('tydom', 'error', print_r($results, true));
      $response = array('success' => false);
    }
  //$response["request"] = $results;
}

echo json_encode($response);
