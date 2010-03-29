<?php
/* For licensing terms, see /license.txt */

/**
 * Chamilo LMS
 *
 * Update the Chamilo database from an older Dokeos version
 * Notice : This script has to be included by index.php
 * or update_courses.php (deprecated).
 *
 * @package chamilo.install
 * @todo
 * - conditional changing of tables. Currently we execute for example
 * ALTER TABLE `$dbNameForm`.`cours`
 * instructions without checking wether this is necessary.
 * - reorganise code into functions
 * @todo use database library
 */

$old_file_version = '1.8.6.2';
$new_file_version = '1.8.7';

// Check if we come from index.php or update_courses.php - otherwise display error msg
if (defined('SYSTEM_INSTALLATION')) {

    // Check if the current Dokeos install is eligible for update
    if (!file_exists('../inc/conf/configuration.php')) {
        echo '<strong>'.get_lang('Error').' !</strong> Dokeos '.implode('|', $updateFromVersion).' '.get_lang('HasNotBeenFound').'.<br /><br />
                                '.get_lang('PleasGoBackToStep1').'.
                                <p><button type="submit" class="back" name="step1" value="&lt; '.get_lang('Back').'">'.get_lang('Back').'</button></p>
                                </td></tr></table></form></body></html>';
        exit ();
    }

    $_configuration['db_glue'] = get_config_param('dbGlu');

    if ($singleDbForm) {
        $_configuration['table_prefix'] = get_config_param('courseTablePrefix');
        $_configuration['main_database'] = get_config_param('mainDbName');
        $_configuration['db_prefix'] = get_config_param('dbNamePrefix');
    }

	$dbScormForm = preg_replace('/[^a-zA-Z0-9_\-]/', '', $dbScormForm);

	if (!empty($dbPrefixForm) && strpos($dbScormForm, $dbPrefixForm) !== 0) {
        $dbScormForm = $dbPrefixForm.$dbScormForm;
    }

    if (empty($dbScormForm) || $dbScormForm == 'mysql' || $dbScormForm == $dbPrefixForm) {
        $dbScormForm = $dbPrefixForm.'scorm';
    }

    /*   Normal upgrade procedure: start by updating main, statistic, user databases */

    // If this script has been included by index.php, not update_courses.php, so
    // that we want to change the main databases as well...
    $only_test = false;
    $log = 0;
    if (defined('SYSTEM_INSTALLATION')) {

        if ($singleDbForm) {
            $dbStatsForm = $dbNameForm;
            $dbScormForm = $dbNameForm;
            $dbUserForm = $dbNameForm;
        }
        /**
         * Update the databases "pre" migration
         */
        include '../lang/english/create_course.inc.php';

        if ($languageForm != 'english') {
            // languageForm has been escaped in index.php
            include '../lang/'.$languageForm.'/create_course.inc.php';
        }

        // Get the main queries list (m_q_list)
        $m_q_list = get_sql_file_contents('migrate-db-'.$old_file_version.'-'.$new_file_version.'-pre.sql', 'main');
        if (count($m_q_list) > 0) {
            // Now use the $m_q_list
            /**
             * We connect to the right DB first to make sure we can use the queries
             * without a database name
             */
            if (strlen($dbNameForm) > 40) {
                error_log('Database name '.$dbNameForm.' is too long, skipping', 0);
            } elseif (!in_array($dbNameForm, $dblist)) {
                error_log('Database '.$dbNameForm.' was not found, skipping', 0);
            } else {
                Database::select_db($dbNameForm);
                foreach ($m_q_list as $query){
                    if ($only_test) {
                        error_log("Database::query($dbNameForm,$query)", 0);
                    } else {
                        $res = Database::query($query);
                        if ($log) {
                            error_log("In $dbNameForm, executed: $query", 0);
                        }
                        if ($res === false) {
                        	error_log('Error in '.$query.': '.Database::error());
                        }
                    }
                }
                $tables = Database::get_tables($dbNameForm);
                foreach ($tables as $table) {
            	    $query = Database::query("ALTER TABLE `".$table."` CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci;");
                    $res = Database::query($query);
                    if ($res === false) {
                         error_log('Error in '.$query.': '.Database::error());
                    }
                }
            	$query = Database::query("ALTER DATABASE `".$dbNameForm."` DEFAULT CHARACTER SET utf8 DEFAULT COLLATE utf8_general_ci;");
                $res = Database::query($query);
                if ($res === false) {
                     error_log('Error in '.$query.': '.Database::error());
                }
            }
        }
        
        // Converting dates and times to UTC using the default timezone of PHP
        // Converting gradebook dates and times
        $timezone = date_default_timezone_get();
		// Calculating the offset
		$dateTimeZoneCurrent = new DateTimeZone($timezone);
		$dateTimeUTC = new DateTime("now", new DateTimeZone('UTC'));
		$timeOffsetSeconds = $dateTimeZoneCurrent->getOffset($dateTimeUTC);
		$timeOffsetHours = $timeOffsetSeconds / 3600;
		$timeOffsetString = "";
		
		if($timeOffsetHours < 0) {
			$timeOffsetString .= "-";
			$timeOffsetHours = abs($timeOffsetHours);
		} else {
			$timeOffsetString .= "+";
		}

		if($timeOffsetHours < 10) {
			$timeOffsetString .= "0";
		}

		$timeOffsetString .= "$timeOffsetHours";
		$timeOffsetString .= ":00";
		
		// Executing the queries to convert everything
        $queries[] = "UPDATE gradebook_certificate 	SET created_at = CONVERT_TZ(created_at, '".$timeOffsetString."', '+00:00');";
        $queries[] = "UPDATE gradebook_evaluation 	SET created_at = CONVERT_TZ(created_at, '".$timeOffsetString."', '+00:00');";
        $queries[] = "UPDATE gradebook_link 		SET created_at = CONVERT_TZ(created_at, '".$timeOffsetString."', '+00:00');";
        $queries[] = "UPDATE gradebook_linkeval_log SET created_at = CONVERT_TZ(created_at, '".$timeOffsetString."', '+00:00');";
        $queries[] = "UPDATE gradebook_result 		SET created_at = CONVERT_TZ(created_at, '".$timeOffsetString."', '+00:00');";
        $queries[] = "UPDATE gradebook_result_log 	SET created_at = CONVERT_TZ(created_at, '".$timeOffsetString."', '+00:00');";
        
        foreach ($queries as $query) {
			Database::query($query);
		}

        // Get the stats queries list (s_q_list)
        $s_q_list = get_sql_file_contents('migrate-db-'.$old_file_version.'-'.$new_file_version.'-pre.sql', 'stats');
        if (count($s_q_list) > 0) {
            // Now use the $s_q_list
            /**
             * We connect to the right DB first to make sure we can use the queries
             * without a database name
             */
            if (strlen($dbStatsForm) > 40) {
                error_log('Database name '.$dbStatsForm.' is too long, skipping', 0);
            } elseif (!in_array($dbStatsForm, $dblist)){
                error_log('Database '.$dbStatsForm.' was not found, skipping', 0);
            } else {
                Database::select_db($dbStatsForm);
                
                foreach ($s_q_list as $query) {
                    if ($only_test) {
                        error_log("Database::query($dbStatsForm,$query)", 0);
                    } else {
                        $res = Database::query($query);
                        if ($log) {
                            error_log("In $dbStatsForm, executed: $query", 0);
                        }
                        if ($res === false) {
                            error_log('Error in '.$query.': '.Database::error());
                        }
                    }
                }
                $tables = Database::get_tables($dbStatsForm);
                foreach ($tables as $table) {
            	    $query = Database::query("ALTER TABLE `".$table."` CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci;");
                    $res = Database::query($query);
                    if ($res === false) {
                         error_log('Error in '.$query.': '.Database::error());
                    }
                }
                $query = Database::query("ALTER DATABASE `".$dbStatsForm."` DEFAULT CHARACTER SET utf8 DEFAULT COLLATE utf8_general_ci;");
                $res = Database::query($query);
                if ($res === false) {
                     error_log('Error in '.$query.': '.Database::error());
                }
                
                
                // chamilo_stat.track_e_attempt table update changing id by id_auto
               
      			$sql = "SELECT exe_id, question_id, course_code, answer FROM $dbStatsForm.track_e_attempt";
                $result = Database::query($sql);
                if (Database::num_rows($result) > 0) {
                	while ($row = Database::fetch_array($result)) {
                		$course_code  	= $row['course_code'];
                		$course_info 	= api_get_course_info($course_code);
						$my_course_db 	= $course_info['dbName'];  
 						$question_id  	= $row['question_id'];
						$answer			= $row['answer'];
						$exe_id			= $row['exe_id'];		

						//getting the type question id
                		$sql_question = "SELECT type FROM $my_course_db.quiz_question where id = $question_id";                		
                		$res_question = Database::query($sql_question);
                		$row  = Database::fetch_array($res_question);	
                 		$type = $row['type'];
                
                		require_once api_get_path(SYS_CODE_PATH).'exercice/question.class.php';
                		//this type of questions produce problems in the track_e_attempt table
                		if (in_array($type, array(UNIQUE_ANSWER, MULTIPLE_ANSWER, MATCHING, MULTIPLE_ANSWER_COMBINATION))) {
		            		$sql_question = "SELECT id_auto FROM $my_course_db.quiz_answer where question_id = $question_id and id = $answer";
		            		$res_question = Database::query($sql_question);
		            		$row = Database::fetch_array($res_question);
		            		$id_auto = $row['id_auto'];
		            		if (!empty($id_auto)) {
	                			$sql = "UPDATE $dbStatsForm.track_e_attempt SET answer = '$id_auto' WHERE exe_id = $exe_id AND question_id = $question_id AND course_code = '$course_code' and answer = $answer ";
	                			Database::query($sql);
		            		}
                		}	
                	}	
                }
                 
                
            }
        }
        
        

        // Get the user queries list (u_q_list)
        $u_q_list = get_sql_file_contents('migrate-db-'.$old_file_version.'-'.$new_file_version.'-pre.sql', 'user');
        if (count($u_q_list) > 0) {
            // Now use the $u_q_list
            /**
             * We connect to the right DB first to make sure we can use the queries
             * without a database name
             */
            if (strlen($dbUserForm) > 40) {
                error_log('Database name '.$dbUserForm.' is too long, skipping', 0);
            } elseif (!in_array($dbUserForm,$dblist)) {
                error_log('Database '.$dbUserForm.' was not found, skipping', 0);
            } else {
                Database::select_db($dbUserForm);
                foreach ($u_q_list as $query) {
                    if ($only_test) {
                        error_log("Database::query($dbUserForm,$query)", 0);
                        error_log("In $dbUserForm, executed: $query", 0);
                    } else {
                        $res = Database::query($query);
                        if ($res === false) {
                            error_log('Error in '.$query.': '.Database::error());
                        }
                    }
                }
                $tables = Database::get_tables($dbUserForm);
                foreach ($tables as $table) {
            	    $query = Database::query("ALTER TABLE `".$table."` CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci;");
                    $res = Database::query($query);
                    if ($res === false) {
                         error_log('Error in '.$query.': '.Database::error());
                    }
                }
                $query = Database::query("ALTER DATABASE `".$dbUserForm."` DEFAULT CHARACTER SET utf8 DEFAULT COLLATE utf8_general_ci;");
                $res = Database::query($query);
                if ($res === false) {
                     error_log('Error in '.$query.': '.Database::error());
                }
            }
        }
        // The SCORM database doesn't need a change in the pre-migrate part - ignore
    }

    $prefix = '';
    if ($singleDbForm) {
        $prefix =  get_config_param ('table_prefix');
    }

    // Get the courses databases queries list (c_q_list)
    $c_q_list = get_sql_file_contents('migrate-db-'.$old_file_version.'-'.$new_file_version.'-pre.sql', 'course');
    if (count($c_q_list) > 0) {
        // Get the courses list
        if (strlen($dbNameForm) > 40) {
            error_log('Database name '.$dbNameForm.' is too long, skipping', 0);
        } elseif(!in_array($dbNameForm, $dblist)) {
            error_log('Database '.$dbNameForm.' was not found, skipping', 0);
        } else {
            Database::select_db($dbNameForm);
            $res = Database::query("SELECT code,db_name,directory,course_language FROM course WHERE target_course_code IS NULL ORDER BY code");

            if ($res === false) { die('Error while querying the courses list in update_db-1.8.6.2-1.8.7.inc.php'); }

            if (Database::num_rows($res) > 0) {
                $i = 0;
                $list = array();
                while ($row = Database::fetch_array($res)) {
                    $list[] = $row;
                    $i++;
                }
                foreach ($list as $row_course) {
                    // Now use the $c_q_list
                    /**
                     * We connect to the right DB first to make sure we can use the queries
                     * without a database name
                     */
                    if (!$singleDbForm) { // otherwise just use the main one
                        Database::select_db($row_course['db_name']);
                    }

                    foreach ($c_q_list as $query) {
                        if ($singleDbForm) {
                            $query = preg_replace('/^(UPDATE|ALTER TABLE|CREATE TABLE|DROP TABLE|INSERT INTO|DELETE FROM)\s+(\w*)(.*)$/', "$1 $prefix{$row_course['db_name']}_$2$3", $query);
                        }

                        if ($only_test) {
                            error_log("Database::query(".$row_course['db_name'].",$query)", 0);
                        } else {
                            $res = Database::query($query);
                            if ($log) {
                                error_log("In ".$row_course['db_name'].", executed: $query", 0);
                            }
                            if ($res === false) {
                                error_log('Error in '.$query.': '.Database::error());
                            }
                        }
                    }
                    
                    if (!$singleDbForm) {
                        $tables = Database::get_tables($row_course['db_name']);
                        foreach ($tables as $table) {
            	            $query = Database::query("ALTER TABLE `".$table."` CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci;");
                            $res = Database::query($query);
                            if ($res === false) {
                                error_log('Error in '.$query.': '.Database::error());
                            }
                        }
                    	$query = "ALTER DATABASE `".$row_course['db_name']."` DEFAULT CHARACTER SET utf8 DEFAULT COLLATE utf8_general_ci;";
                    	$res = Database::query($query);
                        if ($res === false) {
                            error_log('Error in '.$query.': '.Database::error());
                        }
                    }
                    

                }
            }
        }
    }

} else {

    echo 'You are not allowed here !';

}
