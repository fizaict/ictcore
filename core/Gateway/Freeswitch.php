<?php

namespace ICT\Core\Gateway;

/* * ***************************************************************
 * Copyright © 2014 ICT Innovations Pakistan All Rights Reserved   *
 * Developed By: Nasir Iqbal                                       *
 * Website : http://www.ictinnovations.com/                        *
 * Mail : nasir@ictinnovations.com                                 *
 * *************************************************************** */

use DOMDocument;
use ICT\Core\Conf;
use ICT\Core\Corelog;
use ICT\Core\Gateway;
use ICT\Core\Provider;

class Freeswitch extends Gateway
{

  /** @const */
  const GATEWAY_FLAG = 8;
  const GATEWAY_TYPE = 'freeswitch';
  const CONTACT_FIELD = 'phone';

  /** @var boolean $conn */
  protected $conn = false;

  /** @var string $username */
  protected $username;

  /** @var string $password */
  protected $password;

  /** @var string $port */
  protected $port;

  /** @var string $host */
  protected $host;

  public function __construct()
  {
    $this->host = Conf::get('freeswitch:host', '127.0.0.1');
    $this->port = Conf::get('freeswitch:port', '8021');
    $this->username = Conf::get('freeswitch:user', 'user');
    $this->password = Conf::get('freeswitch:pass', 'ClueCon');
  }

  protected function connect()
  {

    $this->conn = fsockopen($this->host, $this->port);
    socket_set_blocking($this->conn, false);
    if ($this->conn) {
      while (!feof($this->conn)) {
        $buffer = fgets($this->conn, 1024);
        usleep(100); //allow time for reponse
        if (trim($buffer) == "Content-Type: auth/request") {
          fputs($this->conn, "auth $this->password\n\n");
          break;
        }
      }
      Corelog::log("Freeswitch connected successfully", Corelog::DEBUG);
      return $this->conn;
    } else {
      Corelog::log("Freeswitch connection failed", Corelog::ERROR);
      return false;
    }
  }

  protected function dissconnect()
  {
    Corelog::log("Freeswitch disconnect requested", Corelog::DEBUG);
    return fclose($this->conn);
  }

  public function send($command, Provider $oProvider = NULL)
  {
    if (!empty($oProvider)) {
      Corelog::log("Freeswitch sending commands via:".$oProvider->name, Corelog::DEBUG, $command);
    }
    // First convert json into data array and then 
    // convert array based command into string
    $data = json_decode($command, TRUE);
    $aVariable = array();
    foreach ($data['input'] as $var_name => $var_value) {
      $aVariable[] = "$var_name=$var_value";
    }
    $command_str = '';
    foreach ($data['batch'] as $aCommand) {
      $command_str .= $aCommand['name'] . ' {' . implode(',', $aVariable) . '}' . $aCommand['data'];
    }
    // TODO: work on $command['output']

    return $this->_send($command_str);
  }
  
  private function _send($command)
  {
    Corelog::log("Freeswitch sending commands", Corelog::DEBUG, $command);

    $this->connect();

    if ($this->conn) {
      fputs($this->conn, $command . "\n\n");
    }

    $this->dissconnect();
  }

  private function _read() {
    $response = "";
    $i = 0;
    $contentlength = 0;

    usleep(100); //allow time for response
    while (!feof($this->conn)) {
      $buffer = fgets($this->conn, 4096);
      if ($contentlength > 0) {
        $response .= $buffer;
      }

      if ($contentlength == 0) { //if contentlenght is already don't process again
        if (strlen(trim($buffer)) > 0) { //run only if buffer has content
          $temparray = explode(":", trim($buffer));
          if ($temparray[0] == "Content-Length") {
            $contentlength = trim($temparray[1]);
          }
        }
      }

      usleep(100); //allow time for reponse
      //optional because of script timeout //don't let while loop become endless
      if ($i > 2000) {
        break;
      }
      if ($contentlength > 0) { //is contentlength set
        //stop reading if all content has been read.
        if (strlen($response) >= $contentlength) {
          break;
        }
      }
      $i++;
    }
    return $response;
  }

  private function config_filename($type, $name)
  {
    global $path_etc;
    switch ($type) {
      case 'extension':
        return $path_etc . DIRECTORY_SEPARATOR . "freeswitch/directory/$name.xml";
      case 'sip':
        return $path_etc . DIRECTORY_SEPARATOR . "freeswitch/sip_profiles/provider/$name.xml";
    }
    return false;
  }

  public function config_save($type, $name, $data = '')
  {
    $doc = new DOMDocument();
    $doc->formatOutput = true;
    $doc->loadXML($data);

    Corelog::log("Freeswitch saving config for type: $type, name: $name", Corelog::INFO);
    $config_file = $this->config_filename($type, $name);
    $doc->save($config_file);

    $this->config_reload();
  }

  public function config_delete($type, $name)
  {
    Corelog::log("Freeswitch deleting config for type: $type, name: $name", Corelog::INFO);
    $config_file = $this->config_filename($type, $name);
    unlink($config_file);
    $this->config_reload();
  }

  public function config_reload()
  {
    $this->_send('bgapi xmlreload');
    // $this->_send('reload mod_sofia');
    $this->_send('bgapi sofia profile ictcore rescan');
  }

}