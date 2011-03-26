<?php
/* For licensing terms, see /license.txt */

/**
* This file was origially the copy of document.php, but many modifications happened since then ;
* the direct file view is not any more needed, if the user uploads a scorm zip file, a directory
* will be automatically created for it, and the files will be uncompressed there for example ;
*
* @package chamilo.learnpath
* @author Yannick Warnier <ywarnier@beeznest.org>
*/

$this_section = SECTION_COURSES;
if (empty($lp_controller_touched) || $lp_controller_touched != 1) {
    header('location: lp_controller.php?action=list');
}

require_once 'back_compat.inc.php';
$courseDir   = api_get_course_path().'/scorm';
$baseWordDir = $courseDir;
$display_progress_bar = true;

require_once 'learnpathList.class.php';
require_once 'learnpath.class.php';
require_once 'learnpathItem.class.php';

/**
 * Display initialisation and security checks
 */
// Extra javascript functions for in html head:
$htmlHeadXtra[] =
"<script language='javascript' type='text/javascript'>
function confirmation(name) {
    if (confirm(\" ".trim(get_lang('AreYouSureToDelete'))." \"+name+\"?\"))
        {return true;}
    else
        {return false;}
}
</script>";
$nameTools = get_lang(ucfirst(TOOL_LEARNPATH));
event_access_tool(TOOL_LEARNPATH);

if (!$is_allowed_in_course) api_not_allowed();

/**
 * Display
 */
/* Require the search widget and prepare the header with its stuff. */
if (api_get_setting('search_enabled') == 'true') {
  require api_get_path(LIBRARY_PATH).'search/search_widget.php';
  search_widget_prepare(&$htmlHeadXtra);
}
Display::display_header($nameTools, 'Path');
$current_session = api_get_session_id();

//api_display_tool_title($nameTools);

/* Introduction section (editable by course admins) */

Display::display_introduction_section(TOOL_LEARNPATH, array(
        'CreateDocumentWebDir' => api_get_path(WEB_COURSE_PATH).api_get_course_path().'/document/',
        'CreateDocumentDir' => '../../courses/'.api_get_course_path().'/document/',
        'BaseHref' => api_get_path(WEB_COURSE_PATH).api_get_course_path().'/'
    )
);

$is_allowed_to_edit = api_is_allowed_to_edit(null, true);

if ($is_allowed_to_edit) {

    /* DIALOG BOX SECTION */

    if (!empty($dialog_box)) {
        switch ($_GET['dialogtype']) {
            case 'confirmation':
                Display::display_confirmation_message($dialog_box);
                break;
            case 'error':
                Display::display_error_message($dialog_box);
                break;
            case 'warning':
                Display::display_warning_message($dialog_box);
                break;
            default:
                Display::display_normal_message($dialog_box);
                break;
        }
    }
    if (api_failure::get_last_failure()) {
        Display::display_normal_message(api_failure::get_last_failure());
    }

    //include 'content_makers.inc.php';
    echo '<div class="actions">';
    echo '<a href="'.api_get_self().'?'.api_get_cidreq().'&action=add_lp">'.Display::return_icon('new_learnpath.png', get_lang('_add_learnpath'),'','32').'</a>' .
        str_repeat('&nbsp;', 3).
        '<a href="../upload/index.php?'.api_get_cidreq().'&curdirpath=/&tool='.TOOL_LEARNPATH.'">'.Display::return_icon('import_scorm.png', get_lang('UploadScorm'),'','32').'</a>';
    if (api_get_setting('service_ppt2lp', 'active') == 'true') {
        echo str_repeat('&nbsp;', 3).'<a href="../upload/upload_ppt.php?'.api_get_cidreq().'&curdirpath=/&tool='.TOOL_LEARNPATH.'">
		'.Display::return_icon('import_powerpoint.png', get_lang('PowerPointConvert'),'','32').'</a>';
           //echo  str_repeat('&nbsp;', 3).'<a href="../upload/upload_word.php?'.api_get_cidreq().'&curdirpath=/&tool='.TOOL_LEARNPATH.'"><img src="../img/word.gif" border="0" alt="'.get_lang('WordConvert').'" align="absmiddle">&nbsp;'.get_lang('WordConvert').'</a>';
    }
    echo '</div>';
}

echo '<table width="100%" border="0" cellspacing="2" class="data_table">';
$is_allowed_to_edit ? $colspan = 9 : $colspan = 3;

/*
if ($curDirName) { // If the $curDirName is empty, we're in the root point and we can't go to a parent dir.
    ?>
    <!-- parent dir -->
    <a href="<?php echo api_get_self().'?'.api_get_cidreq().'&openDir='.$cmdParentDir.'&subdirs=yes'; ?>">
    <img src="../img/parent.gif" border="0" align="absbottom" hspace="5" alt="parent" />
    <?php echo get_lang('Up'); ?></a>&nbsp;
    <?php
}
*/
if (!empty($curDirPath)) {
    if (substr($curDirPath, 1, 1) == '/') {
        $tmpcurDirPath=substr($curDirPath,1,strlen($curDirPath));
    } else {
        $tmpcurDirPath = $curDirPath;
    }
    ?>
    <!-- current dir name -->
    <tr>
        <td colspan="<?php echo $colspan; ?>" align="left" bgcolor="#4171B5">
            <img src="../img/opendir.gif" align="absbottom" vspace="2" hspace="3" alt="open_dir" />
            <font color="#ffffff"><b><?php echo $tmpcurDirPath; ?></b></font>
        </td>
    </tr>
    <?php
}

/* CURRENT DIRECTORY */

echo	'<tr>';
echo	'<th width="40%">'.get_lang('Title').'</th>'.
        '<th>'.get_lang('Progress')."</th>";
if ($is_allowed_to_edit) {
    //echo '<th>'.get_lang('CourseSettings')."</th>\n" .
        // Export now is inside "Edit"
        //'<th>'.get_lang('ExportShort')."</th>\n" .
    echo    '<th>'.get_lang('AuthoringOptions')."</th>";

    // Only available for not session mode.
    if ($current_session == 0) {
        echo'<th>'.get_lang('Move')."</th>";
    }
}
echo '</tr>';

/* DISPLAY SCORM LIST */
$list       = new LearnpathList(api_get_user_id());
$flat_list  = $list->get_flat_list();

//var_dump($flat_list);
if (is_array($flat_list)) {
    $test_mode      = api_get_setting('server_type');
    $max            = count($flat_list);
    $counter        = 0;
    $current        = 0;
    $autolunch_exists = false;
    foreach ($flat_list as $id => $details) {
        
        // Validacion when belongs to a session
        $session_img = api_get_session_image($details['lp_session'], $_user['status']);

        if (!$is_allowed_to_edit && $details['lp_visibility'] == 0) {
            // This is a student and this path is invisible, skip.
            continue;
        }

        // Check if the learnpath is visible for student.
        if (!$is_allowed_to_edit && !learnpath::is_lp_visible_for_student($id, api_get_user_id())) {
            continue;
        }        
        
        if (!$is_allowed_to_edit) {
            
            $time_limits = false;
            
            //This is an old LP (from a migration 1.8.7) so we do nothing
            if ( (empty($details['created_on']) ||  $details['created_on'] == '0000-00-00 00:00:00') && (empty($details['modified_on']) || $details['modified_on'] == '0000-00-00 00:00:00')) {
                $time_limits = false;
            }
            
            //Checking if expired_on is ON
            if ($details['expired_on'] != '' && $details['expired_on'] != '0000-00-00 00:00:00') {
                $time_limits = true;  
            }
     
            if ($time_limits) {
                // check if start time
                if (!empty($details['publicated_on']) && $details['publicated_on'] != '0000-00-00 00:00:00' &&
                    !empty($details['expired_on'])    && $details['expired_on'] != '0000-00-00 00:00:00') { 
                    $start_time = api_strtotime($details['publicated_on'],'UTC');                
                    $end_time   = api_strtotime($details['expired_on'],'UTC');                                      
                    $now        = time();          
                    $is_actived_time = false;                
                                        
                    if ($now > $start_time && $end_time > $now ) {
                        $is_actived_time = true;
                    }
                                    
                    if (!$is_actived_time) {
                    	continue;
                    }                
                }
            }            
        }        

        $counter++;
        if (($counter % 2) == 0) { $oddclass = 'row_odd'; } else { $oddclass = 'row_even'; }

        $url_start_lp = 'lp_controller.php?'.api_get_cidreq().'&action=view&lp_id='.$id;
        $name = Security::remove_XSS($details['lp_name']);
        if ($is_allowed_to_edit) {
            $dsp_desc = '<em>'.$details['lp_maker'].'</em>  &nbsp;'.$details['lp_proximity'].'<br />'.(learnpath::is_lp_visible_for_student($id,api_get_user_id())?'':'('.get_lang('LPNotVisibleToStudent').')');
            $extra = '<br /><font color="#999"><i>'.$dsp_desc .'</i></font>';
        }
        
        $image='<img src="../img/icons/22/learnpath.png" border="0" align="absmiddle" alt="' . $name . '">';
        $dsp_line =	'<tr align="center" class="'.$oddclass.'">'.
            '<td align="left" valign="top">' .
            '<div style="float: left; width: 35px; height: 22px;"><a href="'.$url_start_lp.'">' .
        $image . '</a></div><a href="'.$url_start_lp.'">' . $name . '</a>' . $session_img .$extra.
            "</td>";
        //$dsp_desc='<td>'.$details['lp_desc'].'</td>'."\n";
        $dsp_desc = '';

        $dsp_export = '';
        $dsp_edit = '';
        $dsp_build = '';
        $dsp_edit_close = '';
        $dsp_delete = '';
        $dsp_visible = '';
        $dsp_default_view = '';
        $dsp_debug = '';
        $dsp_order = '';

        // Select course theme.
        if (!empty($platform_theme)) {
            $mystyle = $platform_theme;
        }

        if (!empty($user_theme)) {
            $mystyle = $user_theme;
        }

        if (!empty($mycoursetheme)) {
            $mystyle = $mycoursetheme;
        }

        $lp_theme_css = $mystyle;

        if ($display_progress_bar) {            
            $dsp_progress = '<td width="140px"><div style="width:140px">'.learnpath::get_progress_bar('%',learnpath::get_db_progress($id, api_get_user_id(), '%', '', false, api_get_session_id())).'</div></td>';
        } else {
            $dsp_progress = '<td width="140px" style="padding-top:1em;"><div style="width:140px">'.learnpath::get_db_progress($id, api_get_user_id(), 'both','',false, api_get_session_id()).'</div></td>';
        }   

        if ($is_allowed_to_edit) {

            /*
              if ($current_session == $details['lp_session']) {
                    $dsp_desc = '<td valign="middle" style="color: grey; padding-top:1em;"><em>'.$details['lp_maker'].'</em>  &nbsp;&nbsp; '.$details['lp_proximity'].' &nbsp;&nbsp; '.$details['lp_encoding'].'<a href="lp_controller.php?'.api_get_cidreq().'&action=edit&lp_id='.$id.'">&nbsp;&nbsp;<img src="../img/edit.gif" border="0" title="'.get_lang('_edit_learnpath').'"></a></td>'."\n";
            } else {
                $dsp_desc = '<td valign="middle" style="color: grey; padding-top:1em;"><em>'.$details['lp_maker'].'</em>  &nbsp;&nbsp; '.$details['lp_proximity'].' &nbsp;&nbsp; '.$details['lp_encoding'].'<img src="../img/edit_na.gif" border="0" title="'.get_lang('_edit_learnpath').'"></td>'."	";
            }
            */

            /* // Deprecated code, Chamilo 1.8.8.
            $dsp_desc = '<td valign="middle" style="color: grey; padding-top:1em;"><em>'.$details['lp_maker'].'</em>  &nbsp;&nbsp; '.$details['lp_proximity'].' &nbsp;&nbsp; '.$details['lp_encoding'].'</td>'."\n";
            */

            //$dsp_desc = '<td valign="middle" style="color: grey; padding-top:1em;"><em>'.$details['lp_maker'].'</em>  &nbsp;&nbsp; '.$details['lp_proximity'].'<br />'.(learnpath::is_lp_visible_for_student($id,api_get_user_id())?'':'('.get_lang('LPNotVisibleToStudent').')').'</td>'."\n";

            /* Export */

            // Export is inside "Edit"
            // export not available for normal lps yet
            /*if ($details['lp_type'] == 1) {
                $dsp_export = '<td align="center">' .
                    "<a href='".api_get_self()."?".api_get_cidreq()."&action=export&lp_id=$id'>" .
                    "<img src=\"../img/cd.gif\" border=\"0\" title=\"".get_lang('Export')."\">" .
                    "</a>";
            } elseif ($details['lp_type'] == 2) {
                $dsp_export = '<td align="center">' .
                    "<a href='".api_get_self()."?".api_get_cidreq()."&action=export&lp_id=$id&export_name=".replace_dangerous_char($name,'strict').".zip'>" .
                    "<img src=\"../img/cd.gif\" border=\"0\" title=\"".get_lang('Export')."\">" .
                    "</a>";
            } else {
                $dsp_export = '<td align="center">' .
                    //"<a href='".api_get_self()."?".api_get_cidreq()."&action=export&lp_id=$id'>" .
                    "<img src=\"../img/cd_gray.gif\" border=\"0\" title=\"".get_lang('Export')."\">" .
                    //"</a>";
            }*/

            /* Edit title and description */

            $dsp_edit = '<td align="center">';
            $dsp_edit_close = '</td>';

            // EDIT LP
            if ($current_session == $details['lp_session']) {
                $dsp_edit_lp = '<a href="lp_controller.php?'.api_get_cidreq().'&action=edit&lp_id='.$id.'">'.Display::return_icon('settings.png', get_lang('CourseSettings'),'','22').'</a>';				
            } else {
                $dsp_edit_lp = Display::return_icon('settings_na.png', get_lang('CourseSettings'),'','22');
            }

            // BUILD
            if ($current_session == $details['lp_session']) {
                if ($details['lp_type'] == 1 || $details['lp_type'] == 2) {
                    $dsp_build = '<a href="lp_controller.php?'.api_get_cidreq().'&amp;action=build&amp;lp_id='.$id.'">'.Display::return_icon('edit.png', get_lang('_edit_learnpath'),'','22').'</a>';
                } else {
                    $dsp_build = Display::return_icon('edit_na.png', get_lang('_edit_learnpath'),'','22');
                }
            } else {
                $dsp_build = Display::return_icon('edit_na.png', get_lang('_edit_learnpath'),'','22');
            }

            /* VISIBILITY COMMAND */

            // Session test not necessary if we want to show base course learning paths inside the session (see http://support.chamilo.org/projects/chamilo-18/wiki/Tools_and_sessions).
            //if ($current_session == $details['lp_session']) {
                if ($details['lp_visibility'] == 0) {
                    $dsp_visible =	"<a href=\"".api_get_self()."?".api_get_cidreq()."&lp_id=$id&action=toggle_visible&new_status=1\">".Display::return_icon('invisible.png', get_lang('Show'),'','22')."</a>";                        
                } else {
                    $dsp_visible =	"<a href='".api_get_self()."?".api_get_cidreq()."&lp_id=$id&action=toggle_visible&new_status=0'>" .Display::return_icon('visible.png', get_lang('Hide'),'','22')."</a>";
                }
            //} else {
            //	$dsp_visible = '<img src="../img/invisible.gif" border="0" title="'.get_lang('Show').'" />';
            //}

            /* PUBLISH COMMAND */

            if ($current_session == $details['lp_session']) {
                if ($details['lp_published'] == "i") {
                    $dsp_publish =	"<a href=\"".api_get_self()."?".api_get_cidreq()."&lp_id=$id&action=toggle_publish&new_status=v\">" .
					Display::return_icon('lp_publish_na.png', get_lang('_publish'),'','22')."</a>";
                        
                } else {
                    $dsp_publish =	"<a href='".api_get_self()."?".api_get_cidreq()."&lp_id=$id&action=toggle_publish&new_status=i'>" .Display::return_icon('lp_publish.png', get_lang('_no_publish'),'','22')."</a>";
                }
            } else {
                $dsp_publish = Display::return_icon('lp_publish_na.png', get_lang('_no_publish'),'','22');
            }

            /*  MULTIPLE ATTEMPTS */

            if ($current_session == $details['lp_session']) {
                if ($details['lp_prevent_reinit'] == 1) {
                    $dsp_reinit = '<a href="lp_controller.php?'.api_get_cidreq().'&action=switch_reinit&lp_id='.$id.'">' .
                        Display::return_icon('reload_na.png', get_lang('AllowMultipleAttempts'),'','22').'</a>';
                } else {
                    $dsp_reinit = '<a href="lp_controller.php?'.api_get_cidreq().'&action=switch_reinit&lp_id='.$id.'">' .
                        Display::return_icon('reload.png', get_lang('PreventMultipleAttempts'),'','22').'</a>';
                }
            } else {
                $dsp_reinit = Display::return_icon('reload_na.png', get_lang('AllowMultipleAttempts'),'','22');
            }

            /* FUll screen VIEW */

            if ($current_session == $details['lp_session']) {

                /* Default view mode settings (fullscreen/embedded) */
                if ($details['lp_view_mode'] == 'fullscreen') {
                    $dsp_default_view = '<a href="lp_controller.php?'.api_get_cidreq().'&action=switch_view_mode&lp_id='.$id.'">' .
                        Display::return_icon('view_fullscreen.png', get_lang('ViewModeFullScreen'),'','22').'</a>';
                } elseif ($details['lp_view_mode'] == 'embedded') {
                    $dsp_default_view = '<a href="lp_controller.php?'.api_get_cidreq().'&action=switch_view_mode&lp_id='.$id.'">' .
                         Display::return_icon('view_left_right.png', get_lang('ViewModeEmbedded'),'','22').'</a>';
                } elseif ($details['lp_view_mode'] == 'embedframe') {
                    $dsp_default_view = '<a href="lp_controller.php?'.api_get_cidreq().'&action=switch_view_mode&lp_id='.$id.'">' .
                        Display::return_icon('view_nofullscreen.png', get_lang('ViewModeEmbedFrame'),'','22').'</a>';              	
                }
            } else {
                if ($details['lp_view_mode'] == 'fullscreen'){				
                    $dsp_default_view = Display::return_icon('view_fullscreen_na.png', get_lang('ViewModeEmbedded'),'','22');
				}
				else{
                    $dsp_default_view = Display::return_icon('view_left_right_na.png', get_lang('ViewModeEmbedded'),'','22');
				}
            }

            /* Increase SCORM recording */
            /*
            if ($details['lp_force_commit'] == 1) {
                $dsp_force_commit = '<a href="lp_controller.php?'.api_get_cidreq().'&action=switch_force_commit&lp_id='.$id.'">' .
                    '<img src="../img/clock.gif" border="0" alt="Normal SCORM recordings" title="'.get_lang('MakeScormRecordingNormal').'"/>' .
                    '</a>&nbsp;';
            }else{
                $dsp_force_commit = '<a href="lp_controller.php?'.api_get_cidreq().'&action=switch_force_commit&lp_id='.$id.'">' .
                    '<img src="../img/clock_gray.gif" border="0" alt="Extra SCORM recordings" title="'.get_lang('MakeScormRecordingExtra').'"/>' .
                    '</a>&nbsp;';
            }
            */

            /*  DEBUG  */

            if ($test_mode == 'test' or api_is_platform_admin()) {
                if ($details['lp_scorm_debug'] == 1) {
                    $dsp_debug = '<a href="lp_controller.php?'.api_get_cidreq().'&action=switch_scorm_debug&lp_id='.$id.'">' .
                        Display::return_icon('bug.png', get_lang('HideDebug'),'','22').'</a>';
                }else{
                    $dsp_debug = '<a href="lp_controller.php?'.api_get_cidreq().'&action=switch_scorm_debug&lp_id='.$id.'">' .
					Display::return_icon('bug_na.png', get_lang('ShowDebug'),'','22').'</a>';
                }
             }

       
             /* Export */

            if ($details['lp_type'] == 1) {
                $dsp_disk =
                    "<a href='".api_get_self()."?".api_get_cidreq()."&action=export&lp_id=$id'>" .
                    "<img src=\"../img/icons/22/export_scorm.png\" border=\"0\" title=\"".get_lang('Export')."\">" .
                    "</a>" .
                    "";
            } elseif ($details['lp_type'] == 2) {
                $dsp_disk =
                    "<a href='".api_get_self()."?".api_get_cidreq()."&action=export&lp_id=$id&export_name=".replace_dangerous_char($name, 'strict').".zip'>" .
                    "<img src=\"../img/icons/22/export_scorm.png\" border=\"0\" title=\"".get_lang('Export')."\">" .
                    "</a>" .
                    "";
            } else {
                $dsp_disk =
                    //"<a href='".api_get_self()."?".api_get_cidreq()."&action=export&lp_id=$id'>" .
                    "<img src=\"../img/cd_gray.gif\" border=\"0\" title=\"".get_lang('Export')."\">" .
                    //"</a>" .
                    "";
            }
            
            /* Auto Lunch LP code*/
            $lp_auto_lunch_icon = '';            
            if (api_get_course_setting('enable_lp_auto_launch') == 1) {                            
                if ($details['autolaunch'] == 1 && $autolunch_exists == false) {  
                    $autolunch_exists = true;                            
                    $lp_auto_lunch_icon = '<a href="'.api_get_self().'?'.api_get_cidreq().'&action=auto_launch&status=0&lp_id='.$id.'">
                        <img src="../img/launch.png" border="0" title="'.get_lang('DisableLPAutoLaunch').'" /></a>'; 
                } else {
                    $lp_auto_lunch_icon = '<a href="'.api_get_self().'?'.api_get_cidreq().'&action=auto_launch&status=1&lp_id='.$id.'">
                        <img src="../img/launch_na.png" border="0" title="'.get_lang('EnableLPAutoLaunch').'" /></a>'; 
                }   
            }
            
            if (api_get_setting('pdf_export_watermark_enable') == 'true') {
            	  $export_icon = ' <a href="'.api_get_self().'?'.api_get_cidreq().'&action=export_to_pdf&lp_id='.$id.'">
				  '.Display::return_icon('pdf.png', get_lang('ExportToPDF'),'','22').'</a>';
            }
            
            /* DELETE COMMAND */

            if ($current_session == $details['lp_session']) {
                $dsp_delete = "<a href=\"lp_controller.php?".api_get_cidreq()."&action=delete&lp_id=$id\" " .
                "onclick=\"javascript: return confirmation('".addslashes($name)."');\">" .
				Display::return_icon('delete.png', get_lang('_delete_learnpath'),'','22').'</a>';
            } else {
                $dsp_delete = Display::return_icon('delete_na.png', get_lang('_delete_learnpath'),'','22');
            }
            
            
                        
            /* COLUMN ORDER	 */
            
            // Only active while session mode is not active

            if ($current_session == 0) {

                if ($details['lp_display_order'] == 1 && $max != 1) {
                    $dsp_order .= '<td><a href="lp_controller.php?'.api_get_cidreq().'&action=move_lp_down&lp_id='.$id.'">
                         '.Display::return_icon('down.png', get_lang('MoveDown'),'','22').'</a>					
						<img src="../img/blanco.png" border="0" alt="" title="" /></td>';
                }
                elseif ($current == $max-1 && $max != 1) {
                    $dsp_order .= '<td><img src="../img/blanco.png" border="0" alt="" title="" /><a href="lp_controller.php?'.api_get_cidreq().'&action=move_lp_up&lp_id='.$id.'">
					'.Display::return_icon('up.png', get_lang('MoveUp'),'','22').'</a></td>';
                }
                elseif ($max == 1) {
                    $dsp_order = '<td></td>';
                }
                else {
                    $dsp_order .= '<td><a href="lp_controller.php?'.api_get_cidreq().'&action=move_lp_down&lp_id='.$id.'">' .
                        Display::return_icon('down.png', get_lang('MoveDown'),'','22').'</a>';
                    $dsp_order .= '<a href="lp_controller.php?'.api_get_cidreq().'&action=move_lp_up&lp_id='.$id.'">' .
                        Display::return_icon('up.png', get_lang('MoveUp'),'','22').'</a></td>';
                }
            }
        } // end if ($is_allowedToEdit)
        
        echo $dsp_line.$dsp_progress.$dsp_desc.$dsp_export.$dsp_edit.$dsp_build.$dsp_visible.$dsp_publish.$dsp_reinit.$dsp_default_view.$dsp_debug.$dsp_edit_lp.$dsp_disk.$lp_auto_lunch_icon.$export_icon.$dsp_delete.$dsp_order.$dsp_edit_close;

        echo "</tr>\n";
        $current ++; //counter for number of elements treated
    } // end foreach ($flat_list)
    // TODO: Erint some user-friendly message if counter is still = 0 to tell nothing can be displayd yet.
} // end if ( is_array($flat_list)
echo "</table>";
echo "<br /><br />";
/* FOOTER */
Display::display_footer();