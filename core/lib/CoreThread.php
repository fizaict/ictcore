<?php

namespace ICT\Core;

/* * ***************************************************************
 * Copyright © 2014 ICT Innovations Pakistan All Rights Reserved   *
 * Developed By: Nasir Iqbal                                       *
 * Website : http://www.ictinnovations.com/                        *
 * Mail : nasir@ictinnovations.com                                 *
 * *************************************************************** */

use Aza\Components\Thread\Thread;

class CoreThread extends Thread
{
  function __construct($pName = null, $pool = null, $debug = false, array $options = null)
  {
    global $ict_db_link;
    parent::__construct($pName, $pool, $debug, $options);
    $ict_db_link = DB::connect(TRUE);
    Corelog::$process_id = getmypid();
    Corelog::log("New thread started for: " . get_class($this), Corelog::FLOW);
  }
}