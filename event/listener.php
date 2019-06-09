<?php
/*
 * Hide Bots extension for phpBB.
 *
 * @copyright 2019 Sven Karsten Greiner (SammysHP)
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace sammyshp\hidebots\event;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event listener.
 */
class listener implements EventSubscriberInterface {
  /** @var \phpbb\auth\auth */
  protected $auth;

  /**
   * Constructor.
   *
   * @param \phpbb\auth\auth $auth Authentication service.
   */
  public function __construct(\phpbb\auth\auth $auth) {
    $this->auth = $auth;
  }

  /**
   * Implement event registration.
   *
   * @return array
   */
  static public function getSubscribedEvents() {
    return array(
      'core.obtain_users_online_string_before_modify' => 'obtainUsersOnlienStringBeforeModify',
      // 'core.obtain_users_online_string_sql'           => 'obtainUsersOnlineStringSql',
      'core.viewonline_modify_sql'                    => 'viewonlineModifySql',
    );
  }

  /**
   * Listen to the core.obtain_users_online_string_before_modify event.
   *
   * Filter out bots from the "who is online" block on the board index and
   * change the counters accordingly.
   *
   * @param \phpbb\event\data $event The event object.
   */
  public function obtainUsersOnlienStringBeforeModify($event) {
    // Do not run for admins
    if ($this->auth->acl_get('a_')) {
      return;
    }

    $online_users     = $event['online_users'];
    $user_online_link = $event['user_online_link'];

    foreach ($event['rowset'] as $row) {
      if ($row['user_type'] == USER_IGNORE) {
        if (isset($online_users['hidden_users'][$row['user_id']])) {
          $online_users['hidden_online']--;
        } else {
          $online_users['visible_online']--;
        }
        $online_users['total_online']--;
        unset($online_users['online_users'][$row['user_id']]);
        unset($user_online_link[$row['user_id']]);
      }
    }

    $event['online_users']     = $online_users;
    $event['user_online_link'] = $user_online_link;
  }

  /**
   * Listen to the core.obtain_users_online_string_sql event.
   *
   * Modify the SQL query that is used to get the list of online users in the
   * "who is online" block on the board index. This hides the users, but does
   * not change the counters because they are fetched in another query.
   *
   * @param \phpbb\event\data $event The event object.
   */
  public function obtainUsersOnlineStringSql($event) {
    // Do not run for admins
    if ($this->auth->acl_get('a_')) {
      return;
    }

    $sql_ary = $event['sql_ary'];
    $sql_ary['WHERE'] .= ' AND u.user_type <> ' . USER_IGNORE;
    $event['sql_ary'] = $sql_ary;
  }

  /**
   * Listen to the core.viewonline_modify_sql event.
   *
   * Modify the SQL query that is used to get the list of online users at the
   * "who is online" page.
   *
   * @param \phpbb\event\data $event The event object.
   */
  public function viewonlineModifySql($event) {
    // Do not run for admins
    if ($this->auth->acl_get('a_')) {
      return;
    }

    $sql_ary = $event['sql_ary'];
    $sql_ary['WHERE'] .= ' AND (u.user_type <> ' . USER_IGNORE . ($event['show_guests'] ? ' OR s.session_user_id = ' . ANONYMOUS : '') .  ')';
    $event['sql_ary'] = $sql_ary;
  }
}
