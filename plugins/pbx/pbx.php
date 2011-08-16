<?php

class PbxPlugin extends Plugin
{

  function commands($cmds) {
    $cmds[1]['call'] = array('%^/(call)\s+(?P<callee>.+)$%i', array($this, '_call'), CMD_LAST);
    return $cmds;
  }

  function _call($params) {
    $user = $params['user'];
    $mysip = self::get_sip_info($user->id);
    $user_to = PresenceController::_userstr($params['callee']);
    if ($user_to) {
      // They want to call another user, so grab SIP data for target user.
      $sip = self::get_sip_info($user_to->id);
      $to = 'sip:'.$sip->ext.'@'.$sip->domain;
    } elseif (preg_match('/^[-0-9]+$/', $params['callee'])) {
      // Valid dial string.
      $to = 'sip:'.$params['callee'].'@'.$mysip->domain;
    } else {
      // Invalid!
      self::notify($user, 'You can call a chat user or a phone number (only numbers or dashes).');
    }

    if (isset($to)) {
      // MAKE THE CALL!
      try {
        include 'php-sip/PhpSIP.class.php';
        $from = "sip:{$mysip->ext}@{$mysip->domain}";

        $api = new PhpSIP();
        $agent = "sip:{$mysip->user}@{$mysip->domain}";
        $api->setUsername($mysip->user);
        $api->setPassword($mysip->pass);

        // First get the initiator on the line.
        self::notify($user, "Calling you first...");
        $api->setMethod('INVITE');
        $api->setFrom($agent);
        $api->setUri($from);
        switch ($api->send()) {
          case 200:
            // Initiator answered, so now we ring the other guy.
            if ($user_to) {
              self::notify($user_to, ($user->nickname ? $user->nickname : $user->name).' is calling you!');
            }
            $api->setMethod('REFER');
            $api->addHeader("Refer-to: $to");
            $api->addHeader("Referred-By: $agent");
            $api->send();

            // Let those guys talk, get out of dodge.
            $api->setMethod('BYE');
            $api->send();
            $api->listen('NOTIFY');
            $api->reply(481, 'Call Leg/Transaction Does Not Exist');
            break;
          default:
            self::notify($user, "You didn't answer!");
        }
      } catch (Exception $e) {
        try {
          $api->setMethod('CANCEL');
          $api->send();
          self::notify($user, 'Error placing call!');
        } catch (Exception $e) {
          self::notify($user, 'Error placing call, and another error cleaning up!');
        }
      }
    }

    return true;
  }

  function autocomplete($auto, $cmd) {
    $auto[] = "/call \t{\$nickname}";
    return $auto;
  }

  function get_options($options) {
    $options[] = array('PBX Integration', 'SIP Domain');
    $options[] = array('PBX Integration', 'SIP User');
    $options[] = array('PBX Integration', 'SIP Password');
    $options[] = array('PBX Integration', 'Extension');
    return $options;
  }

  public static function get_sip_info($user_id) {
    $info = new stdClass();
    $info->domain = DB::get()->val("SELECT value FROM options WHERE name = 'SIP Domain' AND grouping = 'PBX Integration' AND user_id = :id", array('id' => $user_id));
    $info->user = DB::get()->val("SELECT value FROM options WHERE name = 'SIP User' AND grouping = 'PBX Integration' AND user_id = :id", array('id' => $user_id));
    $info->pass = DB::get()->val("SELECT value FROM options WHERE name = 'SIP Password' AND grouping = 'PBX Integration' AND user_id = :id", array('id' => $user_id));
    $info->ext = DB::get()->val("SELECT value FROM options WHERE name = 'Extension' AND grouping = 'PBX Integration' AND user_id = :id", array('id' => $user_id));

    return $info;
  }

  public static function notify($user, $message) {
    $message = htmlspecialchars($message);
    DB::get()->query("INSERT INTO presence (data, user_id, type, cssclass, user_to, channel, js) VALUES (:msg, :user_id, 'system', 'direct', :user_to, '', '')", array('msg' => $message, 'user_id' => $user->id, 'user_to' => $user->id));
  }

}
?>