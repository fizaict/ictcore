<?php

namespace ICT\Core\Exchange;

/* * ***************************************************************
 * Copyright © 2016 ICT Innovations Pakistan All Rights Reserved   *
 * Developed By: Nasir Iqbal                                       *
 * Website : http://www.ictinnovations.com/                        *
 * Mail : nasir@ictinnovations.com                                 *
 * *************************************************************** */

use ICT\Core\Account;
use ICT\Core\CoreException;
use ICT\Core\Corelog;
use ICT\Core\DB;
use ICT\Core\Gateway;
use ICT\Core\Gateway\Freeswitch;
use ICT\Core\Request;

class Dialplan
{

  /** @const */
  // Common pattern filters for dialplan search
  const FILTER_GATEWAY_FLAG = 1;
  const FILTER_CONTEXT = 2;
  const FILTER_DESTINATION = 4;
  const FILTER_SOURCE = 8;
  const FILTER_APPLICATION_ID = 16;
  const FILTER_COMMON = 31; // include gateway, context, source, destination and application
  // Additional filters
  const FILTER_ACCOUNT = 128;
  const FILTER_ACCOUNT_DESTINATION = 384; // 128 + 256
  const FILTER_ACCOUNT_SOURCE = 640; // 128 + 512

  //const FILTER_PROGRAM = 1024;

  private static $table = 'dialplan';
  private static $primary_key = 'dialplan_id';
  private static $fields = array(
      'dialplan_id',
      'gateway_flag',
      'source',
      'destination',
      'context',
      'weight',
      'program_id',
      'application_id',
      'filter_flag'
  );
  private static $read_only = array(
      'dialplan_id'
  );

  /**
   * @property-read integer $dialplan_id
   * @var integer
   */
  private $dialplan_id = NULL;

  /** @var integer */
  public $gateway_flag = Freeswitch::GATEWAY_FLAG;

  /**
   * @property string $source 
   * @see function Dialplan::set_source()
   * @var string
   */
  private $source = '%';

  /**
   * @property string $destination 
   * @see function Dialplan::set_destination()
   * @var string
   */
  private $destination = '%';

  /**
   * @property string $context 
   * @see function Dialplan::set_context()
   * @var string
   */
  private $context = '%';

  /**
   * @property integer $weight
   * @see function Dialplan::set_weight()
   * @var integer
   */
  private $weight = 0;

  /** @var integer */
  public $program_id = NULL;

  /** @var integer */
  public $application_id = NULL;

  /** @var integer */
  public $filter_flag = self::FILTER_COMMON;

  public function __construct($dialplan_id = NULL)
  {
    if (!empty($dialplan_id)) {
      $this->dialplan_id = $dialplan_id;
      $this->load();
    }
  }

  public static function lookup(Request $oRequest, &$account_id, $filter_flag = self::FILTER_COMMON)
  {
    Corelog::log("looking for available dialplan", Corelog::INFO, array('data' => $oRequest));

    // general request for incoming call, here we need to search target application in available dialplans
    $aFilter = array();
    $aDialplan = array();
    if (($filter_flag & self::FILTER_SOURCE) == self::FILTER_SOURCE && !empty($oRequest->source)) {
      $aFilter['source'] = $oRequest->source;
    }
    if (($filter_flag & self::FILTER_DESTINATION) == self::FILTER_DESTINATION && !empty($oRequest->destination)) {
      $aFilter['destination'] = $oRequest->destination;
    }
    if (($filter_flag & self::FILTER_CONTEXT) == self::FILTER_CONTEXT && !empty($oRequest->context)) {
      $aFilter['context'] = $oRequest->context;
    }
    if (($filter_flag & self::FILTER_APPLICATION_ID) == self::FILTER_APPLICATION_ID) {
      if (!empty($oRequest->application_id)) {
        $aFilter['application_id'] = $oRequest->application_id;
      }
    }
    if (($filter_flag & self::FILTER_GATEWAY_FLAG) == self::FILTER_GATEWAY_FLAG) {
      if (!empty($oRequest->gateway_flag)) {
        $aFilter['gateway_flag'] = $oRequest->gateway_flag;
      } else {
        // gateway flag is required in search to filter out none related entries
        $aFilter['gateway_flag'] = Freeswitch::GATEWAY_FLAG;
      }
    }

    // fetch all available dialplans
    $listDialplan = self::search($aFilter);

    foreach ($listDialplan as $aDialplan) {
      if (($aDialplan['filter_flag'] & self::FILTER_ACCOUNT) == self::FILTER_ACCOUNT) {
        // first of all know the contact field
        $oGateway = Gateway::load($aDialplan['gateway_flag']);
        $contactFiled = $oGateway::CONTACT_FIELD;
        // check for additional filters
        if (($aDialplan['filter_flag'] & self::FILTER_ACCOUNT_SOURCE) == self::FILTER_ACCOUNT_SOURCE) {
          $accountFilter = array($contactFiled => $oRequest->source);
          $listAccount = Account::search($accountFilter);
          if ($listAccount) {
            $aAccount = array_shift($listAccount);
            $account_id = $aAccount['account_id'];
          } else {
            continue;
          }
        }
        if (($aDialplan['filter_flag'] & self::FILTER_ACCOUNT_DESTINATION) == self::FILTER_ACCOUNT_DESTINATION) {
          $accountFilter = array($contactFiled => $oRequest->destination);
          $listAccount = Account::search($accountFilter);
          if ($listAccount) {
            $aAccount = array_shift($listAccount);
            $account_id = $aAccount['account_id'];
          } else {
            continue;
          }
        }
      }

      // Starting new transmission based on current program      
      $oDialplan = new Dialplan($aDialplan['dialplan_id']);
      return $oDialplan;
    }

    throw new CoreException('204', 'no dialplan found');
  }

  public static function search($aFilter = array())
  {
    $listDialplan = array();

    $aWhere = array();
    foreach ($aFilter as $search_field => $search_value) {
      switch ($search_field) {
        case 'program_id':
        case 'application_id':
        case 'weight':
          $aWhere[] = "$search_field = '$search_value'";
          break;
        case 'gateway_flag':
        case 'filter_flag':
          $aWhere[] = "($search_field & $search_value) = $search_value";
          break;
        case 'source':
        case 'destination':
        case 'context':
          $aWhere[] = "'$search_value' LIKE $search_field";
          break;
      }
    }
    $where_str = implode(' AND ', $aWhere);

    $query = "SELECT dialplan_id, gateway_flag, source, destination, context, 
                      weight, program_id, application_id, filter_flag
               FROM dialplan WHERE $where_str
               ORDER BY filter_flag DESC, LENGTH(destination) DESC, LENGTH(source) DESC, LENGTH(context) DESC, 
                        weight ASC, gateway_flag ASC";
    Corelog::log("dialplan search with $query", Corelog::DEBUG);
    $result = DB::query(self::$table, $query);
    while ($data = mysql_fetch_assoc($result)) {
      $listDialplan[$data['dialplan_id']] = $data;
    }

    Corelog::log("Dialplan search results", Corelog::CRUD, $listDialplan);
    return $listDialplan;
  }

  private function load()
  {
    $query = "SELECT * FROM " . self::$table . " WHERE dialplan_id='%dialplan_id%' ";
    $result = DB::query(self::$table, $query, array('dialplan_id' => $this->dialplan_id), true);
    $data = mysql_fetch_assoc($result);
    if ($data) {
      $this->dialplan_id = $data['dialplan_id'];
      $this->gateway_flag = $data['gateway_flag'];
      $this->source = $data['source'];
      $this->destination = $data['destination'];
      $this->context = $data['context'];
      $this->weight = $data['weight'];
      $this->program_id = $data['program_id'];
      $this->application_id = $data['application_id'];
      $this->filter_flag = $data['filter_flag'];
      Corelog::log("Dialplan loaded source: $this->source, destination: $this->destination", Corelog::CRUD);
    } else {
      throw new CoreException('404', 'Dialplan not found');
    }
  }

  public function delete()
  {
    Corelog::log("Dialplan delete", Corelog::CRUD);
    return DB::delete(self::$table, 'dialplan_id', $this->dialplan_id, true);
  }

  public function __isset($field)
  {
    $method_name = 'isset_' . $field;
    if (method_exists($this, $method_name)) {
      return $this->$method_name();
    } else {
      return isset($this->$field);
    }
  }

  public function __get($field)
  {
    $method_name = 'get_' . $field;
    if (method_exists($this, $method_name)) {
      return $this->$method_name();
    } else if (!empty($field) && in_array($field, self::$fields)) {
      return $this->$field;
    }
    return NULL;
  }

  public function __set($field, $value)
  {
    $method_name = 'set_' . $field;
    if (method_exists($this, $method_name)) {
      $this->$method_name($value);
    } else if (empty($field) || !in_array($field, self::$fields) || in_array($field, self::$read_only)) {
      return;
    } else {
      $this->$field = $value;
    }
  }

  private function set_source($source)
  {
    $this->source = empty($source) ? '%' : $source;
  }

  private function set_destination($destination)
  {
    $this->destination = empty($destination) ? '%' : $destination;
  }

  private function set_context($context)
  {
    $this->context = empty($context) ? '%' : $context;
  }

  private function set_weight($weight)
  {
    $this->weight = empty($weight) ? 0 : $weight;
  }

  public function save()
  {
    $data = array(
        'dialplan_id' => $this->dialplan_id,
        'gateway_flag' => $this->gateway_flag,
        'source' => $this->source,
        'destination' => $this->destination,
        'context' => $this->context,
        'weight' => $this->weight,
        'program_id' => $this->program_id,
        'application_id' => $this->application_id,
        'filter_flag' => $this->filter_flag
    );

    if (isset($data['dialplan_id']) && !empty($data['dialplan_id'])) {
      // update existing record
      $result = DB::update(self::$table, $data, 'dialplan_id', true);
      Corelog::log("Dialplan updated: $this->dialplan_id", Corelog::CRUD);
    } else {
      // add new
      $result = DB::update(self::$table, $data, false, true);
      $this->dialplan_id = $data['dialplan_id'];
      Corelog::log("New Dialplan created: $this->dialplan_id", Corelog::CRUD);
    }
    return $result;
  }

}