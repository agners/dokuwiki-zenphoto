<?php
    /**
     * Example Action Plugin:   Example Component.
     *
     * @author     Stefan Agner <falstaff@deheime.ch>
     */
     
    if(!defined('DOKU_INC')) die();
    if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
    require_once DOKU_PLUGIN.'action.php';

    class action_plugin_zenlogin extends DokuWiki_Action_Plugin {
        var $cookie_name;
        var $zp_path;
        var $zp_mysql_user;
        var $zp_mysql_pass;
        var $zp_mysql_host;
        var $zp_mysql_database;
        var $zp_mysql_prefix;
        var $zp_userpass_hash; // This hash value could be found on zenphoto admin/options/general tab
        var $zp_rights;

        function action_plugin_zenlogin() {
            $this->cookie_name = 'zenphoto_auth';
            $this->zp_path = $this->getConf('zenphoto_path');
            $this->zp_mysql_user = $this->getConf('mysql_user');
            $this->zp_mysql_pass = $this->getConf('mysql_password');
            $this->zp_mysql_host = $this->getConf('mysql_host');
            $this->zp_mysql_database = $this->getConf('mysql_database');
            $this->zp_mysql_prefix = $this->getConf('mysql_prefix');
            $this->zp_userpass_hash = $this->getConf('user_password_hash');
            $rights = split(",", $this->getConf('zenphoto_permissions'));
            $right_numeric = 0;
            foreach($rights as $right)
            {
                if($right == "overview_rights") $right_numeric += 2^2;
                else if($right == "view_all_rights") $right_numeric += 2^4;
                else if($right == "upload_rights") $right_numeric += 2^6;
                else if($right == "post_comment_rights") $right_numeric += 2^8;
                else if($right == "comment_rights") $right_numeric += 2^10;
                else if($right == "album_rights") $right_numeric += 2^12;
                else if($right == "zenpage_pages_rights") $right_numeric += 2^14;
                else if($right == "zenpage_news_rights") $right_numeric += 2^16;
                else if($right == "files_rights") $right_numeric += 2^18;
                else if($right == "manage_all_pages_rights") $right_numeric += 2^20;
                else if($right == "manage_all_news_rights") $right_numeric += 2^22;
                else if($right == "manage_all_album_rights") $right_numeric += 2^24;
                else if($right == "themes_rights") $right_numeric += 2^26;
                else if($right == "tags_rights") $right_numeric += 2^28;
                else if($right == "options_rights") $right_numeric += 2^29;
                else if($right == "admin_rights") $right_numeric += 2^30;
            }
            $this->zp_rights = $right_numeric;
        }


        /**
         * Register its handlers with the DokuWiki's event controller
         */
        function register(&$controller) {

            $controller->register_hook('AUTH_LOGIN_CHECK', 'BEFORE', $this,
                                       'event_login');
            $controller->register_hook('AUTH_USER_CHANGE', 'AFTER', $this,
                                       'event_userchange');
            $controller->register_hook('ACTION_HEADERS_SEND', 'BEFORE', $this,
                                       'event_headers_send');


        }

        /**
         * Calculates password hash the zenphoto way
         *
         * @author Stefan Agner <stefan@agner.ch>
         */
        function zenphoto_hashpw($user, $password) {
            return md5($user.$password.$this->zp_userpass_hash);
        }

        /**
         * Set cookie to login zenphoto as well
         *
         * @author Stefan Agner <stefan@agner.ch>
         */
        function zenphoto_login($user, $password, $sticky=true) {
            if($this->getConf('single_sign_on'))
            {
                $pwhash = $this->zenphoto_hashpw($user, $password);
                if($sticky)
                    setcookie($this->cookie_name, $pwhash, time()+(60*60*24*365), $this->zp_path); // 1 year, Dokuwiki default
                else
                    setcookie($this->cookie_name, $pwhash, null, $this->zp_path); // browser close
            }
        }

        /**
         * Set cookie to logout zenphoto as well
         *
         * @author Stefan Agner <stefan@agner.ch>
         */
        function zenphoto_logout() {
            if($this->getConf('single_sign_on'))
              setcookie($this->cookie_name, '', time()-31536000, $this->zp_path);
        }

        /**
         * Check if user is still logged in just before headers are sent (to be able to delete the cookie)
         *
         * @author Stefan Agner <stefan@agner.ch>
         */
        function event_headers_send(&$event, $param) {
            // No userlogin, might be a logout 
            if($_SERVER['REMOTE_USER'] == "")
                $this->zenphoto_logout();
        }


        /**
         * Set cookie to login zenphoto as well
         *
         * @author Stefan Agner <stefan@agner.ch>
         */
        function event_login(&$event, $param) {
            // Check if user is set (this is only the case if we just pressed login, while the session is running the event happens but no user is set)
            if($event->data['user'] != "")
                $this->zenphoto_login($event->data['user'], $event->data['password'], $event->data['sticky'] == 1);

        }

        /**
         * Update user information in zenphoto as well
         *
         * @author Stefan Agner <stefan@agner.ch>
         */
        function event_userchange(&$event, $param) {
            // Connect to zenphoto database...
            $con = mysql_connect($this->zp_mysql_host,$this->zp_mysql_user,$this->zp_mysql_pass);
            if (!$con)
            {
                die('Could not connect: ' . mysql_error());
            }

            mysql_select_db($this->zp_mysql_database, $con);

            if($event->data['type'] == 'create' && $event->data['modification_result'])
            {
                $user = $event->data['params'][0];
                $pass = $this->zenphoto_hashpw($user, $event->data['params'][1]);
                $name = $event->data['params'][2];
                $email = $event->data['params'][3];
                $custom_data = "User generated by DokuWiki zenlogin Plug-In.";
                mysql_query("INSERT INTO ".$this->zp_mysql_prefix."administrators (user, pass, name, email, rights, valid, custom_data) ".
                            "VALUES ('".$user."', '".$pass."', '".$name."', '".$email."', ".$this->zp_rights.", 1, '".$custom_data."')", $con);
            }
            else if($event->data['type'] == 'modify' && $event->data['modification_result'])
            {
                // params is an array, [0] ==> Username, [1] ==> Fields
                $user = $event->data['params'][0]; 
                if(isset($event->data['params'][1]["name"]))
                {
                    $name = $event->data['params'][1]["name"];
                    mysql_query("UPDATE ".$this->zp_mysql_prefix."administrators SET name = '".$name."' WHERE user = '".$user."'", $con);
                }

                if(isset($event->data['params'][1]["mail"]))
                {
                    $email = $event->data['params'][1]["mail"];
                    mysql_query("UPDATE ".$this->zp_mysql_prefix."administrators SET email = '".$email."' WHERE user = '".$user."'", $con);
                }

                if(isset($event->data['params'][1]["pass"]))
                {
                    // Change the password with new hash
                    $pass = $this->zenphoto_hashpw($user, $event->data['params'][1]["pass"]);
                    mysql_query("UPDATE ".$this->zp_mysql_prefix."administrators SET pass = '".$pass."' WHERE user = '".$user."'", $con);

                    // Also change the cookie for zenphoto
                    $this->zenphoto_login($user, $event->data['params'][1]["pass"]);
                }
            }
            else if($event->data['type'] == 'delete' && $event->data['modification_result'] > 0)
            {
                // params is an array, [0] ==> List of users to delete (array)

                // Modification result contains number of deleted users
                for($i = 0; $i < $event->data['modification_result'];$i++)
                {
                    $user = $event->data['params'][0][$i];
                    mysql_query("DELETE FROM ".$this->zp_mysql_prefix."administrators WHERE user = '".$user."'", $con);
                }
            }
            mysql_close($con);
            
        }


    }


