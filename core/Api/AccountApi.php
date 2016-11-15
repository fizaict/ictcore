<?php
/* * ***************************************************************
 * Copyright © 2016 ICT Innovations Pakistan All Rights Reserved   *
 * Developed By: Nasir Iqbal                                       *
 * Website : http://www.ictinnovations.com/                        *
 * Mail : nasir@ictinnovations.com                                 *
 * *************************************************************** */

class AccountApi extends Api
{

  /**
   * Create a new account
   *
   * @url POST /account/create
   */
  public function create($data = array())
  {
    $this->_authorize('account_create');

    $oAccount = new Account();
    $this->set($oAccount, $data);

    if ($oAccount->save()) {
      return $oAccount->account_id;
    } else {
      throw new CoreException(417, 'Account creation failed');
    }
  }

  /**
   * List all available accounts
   *
   * @url GET /account/list
   * @url POST /account/list
   */
  public function list_view($data = array())
  {
    $this->_authorize('account_list');
    return Account::search($data);
  }

  /**
   * Gets the account by id
   *
   * @url GET /account/$account_id
   */
  public function read($account_id)
  {
    $this->_authorize('account_read');

    $oAccount = new Account($account_id);
    return $oAccount;
  }

  /**
   * Update existing account
   *
   * @url POST /account/$account_id/update
   * @url PUT /account/$account_id/update
   */
  public function update($account_id, $data = array())
  {
    $this->_authorize('account_update');

    $oAccount = new Account($account_id);
    $this->set($oAccount, $data);

    if ($oAccount->save()) {
      return $oAccount;
    } else {
      throw new CoreException(417, 'Account update failed');
    }
  }

  /**
   * Create a new account
   *
   * @url GET /account/$account_id/delete
   * @url DELETE /account/$account_id/delete
   */
  public function remove($account_id)
  {
    $this->_authorize('account_delete');

    $oAccount = new Account($account_id);

    $result = $oAccount->delete();
    if ($result) {
      return $result;
    } else {
      throw new CoreException(417, 'Account delete failed');
    }
  }

  /**
   * Subscribe to selected program
   *
   * @url GET /account/$account_id/subscribe/$program_name
   */
  public function subscribe($account_id, $program_name)
  {
    $this->_authorize('account_update');
    $this->_authorize('program_create');
    $this->_authorize('program_execute');
    $oAccount = new Account($account_id);
    $oProgram = Program::load($program_name);

    return $oAccount->install_program($oProgram);
  }

  /**
   * Unsubscribe from selected program
   *
   * @url GET /account/$account_id/unsubscribe/$program_name
   * @url GET /account/$account_id/unsubscribe
   */
  public function unsubscribe($account_id, $program_name = 'all')
  {
    $this->_authorize('account_update');
    $this->_authorize('program_delete');
    $oAccount = new Account($account_id);

    return $oAccount->remove_program($program_name);
  }

  /**
   * Associate account to selected user
   *
   * @url GET /account/$account_id/associate/$user_id
   */
  public function associate($account_id, $user_id)
  {
    $this->_authorize('account_create'); // instead of updated association is more like account creation
    $this->_authorize('user_update');
    $oAccount = new Account($account_id);
    $oUser = new User($user_id);

    $oAccount->dissociate();
    return $oAccount->associate($oUser);
  }

  /**
   * Unsubscribe from selected program
   *
   * @url GET /account/$account_id/dissociate
   */
  public function dissociate($account_id)
  {
    $this->_authorize('account_delete');
    $this->_authorize('user_update');
    $oAccount = new Account($account_id);

    return $oAccount->dissociate();
  }

}