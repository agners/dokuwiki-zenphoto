<?php

$meta['zenphoto_path'] = array('string');
$meta['mysql_user'] = array('string');
$meta['mysql_password'] = array('string');
$meta['mysql_host'] = array('string');
$meta['mysql_database'] = array('string');
$meta['mysql_prefix'] = array('string');
$meta['user_password_hash'] = array('string');
$meta['synchronize_users'] = array('onoff');
//According to lib-auth.php
//overview_rights 2^2
//view_all_rights 2^4
//upload_rights 2^6
//post_comment_rights 2^8
//comment_rights 2^10
//album_rights 2^12
//zenpage_pages_rights 2^14
//zenpage_news_rights 2^16
//files_rights 2^18
//manage_all_pages_rights 2^20
//manage_all_news_rights 2^22
//manage_all_album_rights 2^24
//themes_rights 2^26
//tags_rights 2^28
//options_rights 2^29
//admin_rights 2^30

$meta['zenphoto_permissions'] = array('multicheckbox','_choices' => array('overview_rights', 'view_all_rights', 'upload_rights', 'post_comment_rights', 'comment_rights',
                                                                          'album_rights', 'zenpage_pages_rights', 'zenpage_news_rights', 'files_rights', 'manage_all_pages_rights',
                                                                          'manage_all_news_rights', 'manage_all_album_rights', 'themes_rights', 'tags_rights', 'options_rights',
                                                                          'admin_rights'));
$meta['single_sign_on'] = array('onoff');
?>
