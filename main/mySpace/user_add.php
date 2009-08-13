<?php // $Id: user_add.php 15105 2008-04-25 08:38:20Z elixir_inter $
/*
==============================================================================
	Dokeos - elearning and course management software

	Copyright (c) 2008 Dokeos SPRL
	Copyright (c) 2009 Julio Montoya Armas <gugli100@gmail.com>

	For a full list of contributors, see "credits.txt".
	The full license can be read in "license.txt".

	This program is free software; you can redistribute it and/or
	modify it under the terms of the GNU General Public License
	as published by the Free Software Foundation; either version 2
	of the License, or (at your option) any later version.

	See the GNU General Public License for more details.

	Contact: Dokeos, rue du Corbeau, 108, B-1000 Brussels, Belgium, info@dokeos.com
==============================================================================
*/

// name of the language file that needs to be included
$language_file = array('admin','registration');
$cidReset=true;
// including necessary libraries
require ('../inc/global.inc.php');
$libpath = api_get_path(LIBRARY_PATH);
include_once ($libpath.'fileManage.lib.php');
include_once ($libpath.'fileUpload.lib.php');
include_once ($libpath.'usermanager.lib.php');
require_once ($libpath.'formvalidator/FormValidator.class.php');

// user permissions
api_protect_admin_script(true);
api_block_anonymous_users();

// Database table definitions
$table_admin 	= Database :: get_main_table(TABLE_MAIN_ADMIN);
$table_user 	= Database :: get_main_table(TABLE_MAIN_USER);

$htmlHeadXtra[] = '
<script language="JavaScript" type="text/JavaScript">
<!--
function enable_expiration_date() { //v2.0
	document.user_add.radio_expiration_date[0].checked=false;
	document.user_add.radio_expiration_date[1].checked=true;
}

function password_switch_radio_button(form, input){
	var NodeList = document.getElementsByTagName("input");
	for(var i=0; i< NodeList.length; i++){
		if(NodeList.item(i).name=="password[password_auto]" && NodeList.item(i).value=="0"){
			NodeList.item(i).checked=true;
		}
	}
}

function display_drh_list(){
	if(document.getElementById("status_select").value=='.STUDENT.')
	{
		document.getElementById("drh_list").style.display="block";
	}
	else
	{ 
		document.getElementById("drh_list").style.display="none";
		document.getElementById("drh_select").options[0].selected="selected";
	}
}

//-->
</script>';

if(!empty($_GET['message'])) {
	$message = urldecode($_GET['message']);
}

$id_session='';
if(isset($_GET["id_session"]) && $_GET["id_session"]!="")
{
 	$id_session = Security::remove_XSS($_GET["id_session"]); 	
	//$interbreadcrumb[] = array ("url" => "session.php", "name" => get_lang('Sessions')); 
	//$interbreadcrumb[] = array ("url" => "course.php?id_session=".$_GET["id_session"]."", "name" => get_lang('Cours'));
}

$interbreadcrumb[] = array ('url' => '../admin/index.php', 'name' => get_lang('PlatformAdmin'));

$tool_name = get_lang('AddUser');
// Create the form
$form = new FormValidator('user_add');
// Lastname
$form->addElement('text','lastname',get_lang('LastName'));
$form->applyFilter('lastname','html_filter');
$form->applyFilter('lastname','trim');
$form->addRule('lastname', get_lang('ThisFieldIsRequired'), 'required');
// Firstname
$form->addElement('text','firstname',get_lang('FirstName'));
$form->applyFilter('firstname','html_filter');
$form->applyFilter('firstname','trim');
$form->addRule('firstname', get_lang('ThisFieldIsRequired'), 'required');
// Official code
$form->addElement('text', 'official_code', get_lang('OfficialCode'),array('size' => '40'));
$form->applyFilter('official_code','html_filter');
$form->applyFilter('official_code','trim');
// Email
$form->addElement('text', 'email', get_lang('Email'),array('size' => '40'));
$form->addRule('email', get_lang('EmailWrong'), 'email');
$form->addRule('email', get_lang('EmailWrong'), 'required');
// Phone
$form->addElement('text','phone',get_lang('PhoneNumber'));
// Picture
$form->addElement('file', 'picture', get_lang('AddPicture'));
$allowed_picture_types = array ('jpg', 'jpeg', 'png', 'gif');
$form->addRule('picture', get_lang('OnlyImagesAllowed').' ('.implode(',', $allowed_picture_types).')', 'filetype', $allowed_picture_types);
// Username
$form->addElement('text', 'username', get_lang('LoginName'),array('maxlength'=>20));
$form->addRule('username', get_lang('ThisFieldIsRequired'), 'required');
$form->addRule('username', get_lang('OnlyLettersAndNumbersAllowed'), 'username');
$form->addRule('username', '', 'maxlength',20);
$form->addRule('username', get_lang('UserTaken'), 'username_available', $user_data['username']);
// Password
$group = array();
$auth_sources = 0; //make available wider as we need it in case of form reset (see below)
if(count($extAuthSource) > 0)
{
	$group[] =& HTML_QuickForm::createElement('radio','password_auto',null,get_lang('ExternalAuthentication').' ',2);
	$auth_sources = array();
	foreach($extAuthSource as $key => $info)
	{
		$auth_sources[$key] = $key;
	}
	$group[] =& HTML_QuickForm::createElement('select','auth_source',null,$auth_sources);
	$group[] =& HTML_QuickForm::createElement('static','','','<br />');
}
$group[] =& HTML_QuickForm::createElement('radio','password_auto',get_lang('Password'),get_lang('AutoGeneratePassword').'<br />',1);
$group[] =& HTML_QuickForm::createElement('radio', 'password_auto','id="radio_user_password"',null,0);
$group[] =& HTML_QuickForm::createElement('password', 'password',null,'onkeydown=password_switch_radio_button(document.user_add,"password[password_auto]")');
$form->addGroup($group, 'password', get_lang('Password'), '');



// Send email
$group = array();
$group[] =& HTML_QuickForm::createElement('radio', 'send_mail',null,get_lang('Yes'),1);
$group[] =& HTML_QuickForm::createElement('radio', 'send_mail',null,get_lang('No'),0);
$form->addGroup($group, 'mail', get_lang('SendMailToNewUser'), '&nbsp;');
// Expiration Date
$form->addElement('radio', 'radio_expiration_date', get_lang('ExpirationDate'), get_lang('NeverExpires'), 0);
$group = array ();
$group[] = & $form->createElement('radio', 'radio_expiration_date', null, get_lang('On'), 1);
$group[] = & $form->createElement('datepicker','expiration_date', null, array ('form_name' => $form->getAttribute('name'), 'onChange'=>'enable_expiration_date()'));
$form->addGroup($group, 'max_member_group', null, '', false);
// Active account or inactive account
$form->addElement('radio','active',get_lang('ActiveAccount'),get_lang('Active'),1);
$form->addElement('radio','active','',get_lang('Inactive'),0);


//session list
if (api_is_session_admin()) {
	$where = 'WHERE session_admin_id='.intval(api_get_user_id());
	$where .= ' AND ( (session.date_start <= CURDATE() AND session.date_end >= CURDATE()) OR session.date_start="0000-00-00" ) ';
	$tbl_session=Database::get_main_table(TABLE_MAIN_SESSION);
	$result=api_sql_query("SELECT id,name,nbr_courses,date_start,date_end 
							FROM $tbl_session 					
							$where
							ORDER BY name",__FILE__,__LINE__);	
	$a_sessions=api_store_result($result);		
	$session_list = array();	
	$session_list[0]=get_lang('SelectSession');
	if (is_array($a_sessions)) {	
		foreach ($a_sessions as $session) {
			$session_list[$session['id']]=$session['name'];
		}
	}

	//asort($session_list);
	//api_asort($session_list, SORT_STRING);
	api_natsort($session_list);

	$form->addElement('select','session_id',get_lang('Session'),$session_list);
}		


// EXTRA FIELDS
$extra = UserManager::get_extra_fields(0,50,5,'ASC');
foreach($extra as $id => $field_details)
{
	switch($field_details[2])
	{
		case USER_FIELD_TYPE_TEXT:
			$form->addElement('text', 'extra_'.$field_details[1], $field_details[3], array('size' => 40));
			$form->applyFilter('extra_'.$field_details[1], 'stripslashes');
			$form->applyFilter('extra_'.$field_details[1], 'trim');
			break;
		case USER_FIELD_TYPE_TEXTAREA:
			$form->add_html_editor('extra_'.$field_details[1], $field_details[3], false, false, array('ToolbarSet' => 'Profile', 'Width' => '100%', 'Height' => '130'));
			//$form->addElement('textarea', 'extra_'.$field_details[1], $field_details[3], array('size' => 80));
			$form->applyFilter('extra_'.$field_details[1], 'stripslashes');
			$form->applyFilter('extra_'.$field_details[1], 'trim');
			break;
		case USER_FIELD_TYPE_RADIO:
			$group = array();
			foreach($field_details[9] as $option_id => $option_details)
			{
				$options[$option_details[1]] = $option_details[2];
				$group[] =& HTML_QuickForm::createElement('radio', 'extra_'.$field_details[1], $option_details[1],$option_details[2].'<br />',$option_details[1]);
			}
			$form->addGroup($group, 'extra_'.$field_details[1], $field_details[3], '');
			break;
		case USER_FIELD_TYPE_SELECT:
			$options = array();
			foreach($field_details[9] as $option_id => $option_details)
			{
				$options[$option_details[1]] = $option_details[2];
			}
			$form->addElement('select','extra_'.$field_details[1],$field_details[3],$options,'');			
			break;
		case USER_FIELD_TYPE_SELECT_MULTIPLE:
			$options = array();
			foreach($field_details[9] as $option_id => $option_details)
			{
				$options[$option_details[1]] = $option_details[2];
			}
			$form->addElement('select','extra_'.$field_details[1],$field_details[3],$options,array('multiple' => 'multiple'));
			break;
		case USER_FIELD_TYPE_DATE:
			$form->addElement('datepickerdate', 'extra_'.$field_details[1], $field_details[3]);
			$form->_elements[$form->_elementIndex['extra_'.$field_details[1]]]->setLocalOption('minYear',1900);
			$defaults['extra_'.$field_details[1]] = date('Y-m-d 12:00:00');
			$form -> setDefaults($defaults);
			$form->applyFilter('theme', 'trim');
			break;
		case USER_FIELD_TYPE_DATETIME:
			$form->addElement('datepicker', 'extra_'.$field_details[1], $field_details[3]);
			$form->_elements[$form->_elementIndex['extra_'.$field_details[1]]]->setLocalOption('minYear',1900);
			$defaults['extra_'.$field_details[1]] = date('Y-m-d 12:00:00');
			$form -> setDefaults($defaults);
			$form->applyFilter('theme', 'trim');
			break;
	}
}


// Set default values
$defaults['admin']['platform_admin'] = 0;
$defaults['mail']['send_mail'] = 0;
$defaults['password']['password_auto'] = 1;
$defaults['active'] = 1;
$defaults['expiration_date']=array();
$days = api_get_setting('account_valid_duration');
$time = strtotime('+'.$days.' day');
$defaults['expiration_date']['d']=date('d',$time);
$defaults['expiration_date']['F']=date('m',$time);
$defaults['expiration_date']['Y']=date('Y',$time);
$defaults['radio_expiration_date'] = 0;
$defaults['status'] = STUDENT;
$defaults['session_id'] = api_get_session_id();


$form->setDefaults($defaults);
// Submit button
$form->addElement('submit', 'submit', get_lang('Add'));
$form->addElement('submit', 'submit_plus', get_lang('Add').'+');
// Validate form
if( $form->validate())
{
	$check = Security::check_token('post');
	if($check)
	{
		$user = $form->exportValues();
		$picture_element = & $form->getElement('picture');
		$picture = $picture_element->getValue();
		$picture_uri = '';
		if (strlen($picture['name']) > 0)
		{
			if(!is_dir(api_get_path(SYS_CODE_PATH).'upload/users/')){
				if(mkdir(api_get_path(SYS_CODE_PATH).'upload/users/'))
				{
					$perm = api_get_setting('permissions_for_new_directories');
					$perm = octdec(!empty($perm)?$perm:'0770');
					chmod(api_get_path(SYS_CODE_PATH).'upload/users/');
				}
			}
			$picture_uri = uniqid('').'_'.replace_dangerous_char($picture['name']);
			$picture_location = api_get_path(SYS_CODE_PATH).'upload/users/'.$picture_uri;
			move_uploaded_file($picture['tmp_name'], $picture_location);
		}
		$lastname = $user['lastname'];
		$firstname = $user['firstname'];
		$official_code = $user['official_code'];
		$email = $user['email'];
		$phone = $user['phone'];
		$username = $user['username'];
		$status = intval($user['status']);
		$picture = $_FILES['picture'];
		$platform_admin = intval($user['admin']['platform_admin']);
		$send_mail = intval($user['mail']['send_mail']);
		$hr_dept_id = intval($user['hr_dept_id']);
		if(count($extAuthSource) > 0 && $user['password']['password_auto'] == '2')
		{
			$auth_source = $user['password']['auth_source'];
			$password = 'PLACEHOLDER';
		}
		else
		{
			$auth_source = PLATFORM_AUTH_SOURCE;
			$password = $user['password']['password_auto'] == '1' ? api_generate_password() : $user['password']['password'];
		}
		if ($user['radio_expiration_date']=='1' )
		{
			$expiration_date=$user['expiration_date'];
		}
		else
		{
			$expiration_date='0000-00-00 00:00:00';
		}
		$active = intval($user['active']);
		// default status = student
		$status =5;
		//create user
		$user_id = UserManager::create_user($firstname,$lastname,$status,$email,$username,$password,$official_code,api_get_setting('platformLanguage'),$phone,$picture_uri,$auth_source,$expiration_date,$active, $hr_dept_id);
		
		//adding to the session		
		if (api_is_session_admin()) {			
			$tbl_session_rel_course				= Database::get_main_table(TABLE_MAIN_SESSION_COURSE);
			$tbl_session_rel_course_rel_user	= Database::get_main_table(TABLE_MAIN_SESSION_COURSE_USER);
			$tbl_session						= Database::get_main_table(TABLE_MAIN_SESSION);
			$tbl_session_rel_user				= Database::get_main_table(TABLE_MAIN_SESSION_USER);
			
			$id_session = $user['session_id'];
			if ($id_session!=0)
			{
				$result=api_sql_query("SELECT course_code FROM $tbl_session_rel_course WHERE id_session='$id_session'",__FILE__,__LINE__);
		
				$CourseList=array();	
				while($row=Database::fetch_array($result)) {
					$CourseList[]=$row['course_code'];
				}
		
				foreach($CourseList as $enreg_course) {
					api_sql_query("INSERT INTO $tbl_session_rel_course_rel_user(id_session,course_code,id_user) VALUES('$id_session','$enreg_course','$user_id')",__FILE__,__LINE__);
					// updating the total				
					$sql = "SELECT COUNT(id_user) as nbUsers FROM $tbl_session_rel_course_rel_user WHERE id_session='$id_session' AND course_code='$enreg_course'";
					$rs = api_sql_query($sql, __FILE__, __LINE__);
					list($nbr_users) = Database::fetch_array($rs);
					api_sql_query("UPDATE $tbl_session_rel_course SET nbr_users=$nbr_users WHERE id_session='$id_session' AND course_code='$enreg_course'",__FILE__,__LINE__);				
				}		
				
				
				api_sql_query("INSERT INTO $tbl_session_rel_user(id_session, id_user) VALUES('$id_session','$user_id')",__FILE__,__LINE__);
				
				
				$sql = "SELECT COUNT(nbr_users) as nbUsers FROM $tbl_session WHERE id='$id_session' ";
				$rs = api_sql_query($sql, __FILE__, __LINE__);
				list($nbr_users) = Database::fetch_array($rs);
				
				api_sql_query("UPDATE $tbl_session SET nbr_users= $nbr_users WHERE id='$id_session' ",__FILE__,__LINE__);
						
			}
		}		
			
		
		$extras = array();
		foreach($user as $key => $value)
		{
			if(substr($key,0,6)=='extra_') //an extra field
			{
				$myres = UserManager::update_extra_field_value($user_id,substr($key,6),$value);
			}
		}
		
		if ($platform_admin)
		{
			$sql = "INSERT INTO $table_admin SET user_id = '".$user_id."'";
			api_sql_query($sql,__FILE__,__LINE__);
		}
		if (!empty ($email) && $send_mail)
		{
			$emailto = '"'.$firstname.' '.$lastname.'" <'.$email.'>';
			$emailsubject = '['.get_setting('siteName').'] '.get_lang('YourReg').' '.get_setting('siteName');
			$emailheaders = 'From: '.get_setting('administratorName').' '.get_setting('administratorSurname').' <'.get_setting('emailAdministrator').">\n";
			$emailheaders .= 'Reply-To: '.get_setting('emailAdministrator');
			
			$portal_url = $_configuration['root_web'];
			if ($_configuration['multiple_access_urls']==true) {
				$access_url_id = api_get_current_access_url_id();				
				if ($access_url_id != -1 ){
					$url = api_get_access_url($access_url_id);
					$portal_url = $url['url'];
				}
			}			
			$emailbody=get_lang('Dear')." ".stripslashes("$firstname $lastname").",\n\n".get_lang('YouAreReg')." ". get_setting('siteName') ." ".get_lang('Settings')." ". $username ."\n". get_lang('Pass')." : ".stripslashes($password)."\n\n" .get_lang('Address') ." ". get_setting('siteName') ." ". get_lang('Is') ." : ".$portal_url."\n\n". get_lang('Problem'). "\n\n". get_lang('Formula').",\n\n".get_setting('administratorName')." ".get_setting('administratorSurname')."\n". get_lang('Manager'). " ".get_setting('siteName')."\nT. ".get_setting('administratorTelephone')."\n" .get_lang('Email') ." : ".get_setting('emailAdministrator');
			@api_send_mail($emailto, $emailsubject, $emailbody, $emailheaders);
		}
		Security::clear_token();
		if(isset($user['submit_plus']))
		{
			//we want to add more. Prepare report message and redirect to the same page (to clean the form)
			$tok = Security::get_token();
			header('Location: user_add.php?message='.urlencode(get_lang('UserAdded')).'&sec_token='.$tok);
			exit ();
		}
		else
		{
			$tok = Security::get_token();
			header('Location: ../admin/user_list.php?action=show_message&message='.urlencode(get_lang('UserAdded')).'&sec_token='.$tok);
			exit ();
		}
	}
} else {
	if(isset($_POST['submit'])) {
		Security::clear_token();
	}
	$token = Security::get_token();
	$form->addElement('hidden','sec_token');
	$form->setConstants(array('sec_token' => $token));
}
// Display form
Display::display_header($tool_name);
//api_display_tool_title($tool_name);
if(!empty($message)) {
	Display::display_normal_message(stripslashes($message));
}
$form->display();
/*
==============================================================================
		FOOTER
==============================================================================
*/
Display::display_footer();
?>
