<?php // $Id: $
if ( count( get_included_files() ) == 1 ) die( '---' );
/**
 * @copyright (c) 2007 Dokeos
 * @copyright (c) 2001-2006 Universite catholique de Louvain (UCL)
 *
 * @license http://www.gnu.org/copyleft/gpl.html (GPL) GENERAL PUBLIC LICENSE
 *
 * @author Claro Team <cvs@claroline.net>
 * @author Yannick Warnier <yannick.warnier@dokeos.com> 
 */

require dirname(__FILE__) . '/scorm_classes.php';

/*--------------------------------------------------------
      Classes
  --------------------------------------------------------*/
// answer types
define(UNIQUE_ANSWER,	1);
define(MULTIPLE_ANSWER,	2);
define(FILL_IN_BLANKS,	3);
define(MATCHING,		4);
define(FREE_ANSWER,     5);
define(HOT_SPOT, 		6);
define(HOT_SPOT_ORDER, 	7);
/**
 * A SCORM item. It corresponds to a single question. 
 * This class allows export from Dokeos SCORM 1.2 format of a single question.
 * It is not usable as-is, but must be subclassed, to support different kinds of questions.
 *
 * Every start_*() and corresponding end_*(), as well as export_*() methods return a string.
 * 
 * @warning Attached files are NOT exported.
 */
class ScormAssessmentItem
{
    var $question;
    var $question_ident;
    var $answer;

    /**
     * Constructor.
     *
     * @param $question The Question object we want to export.
     */
     function ScormAssessmentItem($question,$standalone=false)
     {
        $this->question = $question;
        //$this->answer = new Answer($question->id);
        $this->answer = $this->question->setAnswer();
        $this->questionIdent = "QST_" . $question->id ;
        $this->standalone = $standalone;
        //echo "<pre>".print_r($this,1)."</pre>";
     }
     
     /**
      * Start the XML flow.
      *
      * This opens the <item> block, with correct attributes.
      *
      */
     function start_page()
     {
        global $charset;
        $head = "";
        if( $this->standalone)
        {
        	$head = '<?xml version="1.0" encoding="'.$charset.'" standalone="no"?>' . "\n";
        	$head .= '<html>'."\n";
        }
        return $head;
     }
      
     /**
      * End the XML flow, closing the </item> tag.
      *
      */
     function end_page()
     {
     	if($this->standalone){return '</html>';}
     	return '';
     }
	/**
	 * Start document header
	 */
	function start_header()
	{
		if($this->standalone){return '<head>'. "\n";}
		return '';
	}

	/**
	 * End document header
	 */
	function end_header()
	{
		if($this->standalone){return '</head>'. "\n";}
		return '';
	}
    /**
     * Start the itemBody
     * 
     */
    function start_js()
    {
    	if($this->standalone){return '<script type="text/javascript" language="javascript">'. "\n";}
    	return '';
    }
	/**
	 * Common JS functions
	 */
	function common_js()
	{
		$js = file_get_contents('../newscorm/js/api_wrapper.js');
		$js .= 'var questions = new Array();' . "\n";
		$js .= 'var questions_answers = new Array();' . "\n";
		$js .= 'var questions_answers_correct = new Array();' . "\n";
		$js .= 'var questions_types = new Array();' . "\n";
		$js .= "\n" . 
			'/**
             * Assigns any event handler to any element
             * @param	object	Element on which the event is added
             * @param	string	Name of event
             * @param	string	Function to trigger on event
             * @param	boolean	Capture the event and prevent 
             */
            function addEvent(elm, evType, fn, useCapture)
            { //by Scott Andrew
                if(elm.addEventListener){
            		elm.addEventListener(evType, fn, useCapture);
            		return true;
            	} else if(elm.attachEvent) {
            		var r = elm.attachEvent(\'on\' + evType, fn);
            		return r;
            	} else {
            		elm[\'on\' + evType] = fn;
            	} 
            }
            /**
             * Adds the event listener
             */
            function addListeners(e) {
            	loadPage();
            	/*
            	var my_form = document.getElementById(\'dokeos_scorm_form\');
            	addEvent(my_form,\'submit\',checkAnswers,false);
            	*/
            	var my_button = document.getElementById(\'dokeos_scorm_submit\');
            	addEvent(my_button,\'click\',checkAnswers,false);
            	//addEvent(my_button,\'change\',checkAnswers,false);
            	addEvent(window,\'unload\',unloadPage,false);
            }'."\n";
		
		$js .= '';
		//$js .= 'addEvent(window,\'load\',loadPage,false);'."\n";
		//$js .= 'addEvent(window,\'unload\',unloadPage,false);'."\n";
		$js .= 'addEvent(window,\'load\',addListeners,false);'."\n";
		if($this->standalone){return $js. "\n";}
		return '';
	}
    /**
     * End the itemBody part.
     *
     */
    function end_js()
    {
    	if($this->standalone){return '</script>'. "\n";}
    	return '';
    }
    /**
     * Start the itemBody
     * 
     */
    function start_body()
    {
    	if($this->standalone){return '<body>'. "\n".'<form id="dokeos_scorm_form" method="post" action="">'."\n";}
    	return '';
    }
     
    /**
     * End the itemBody part.
     *
     */
    function end_body()
    {
    	if($this->standalone){return '<br /><input type="button" id="dokeos_scorm_submit" name="dokeos_scorm_submit" value="OK" /></form>'."\n".'</body>'. "\n";}
    	return '';
    }

    /**
     * Export the question as a SCORM Item.
     *
     * This is a default behaviour, some classes may want to override this.
     *
     * @param $standalone: Boolean stating if it should be exported as a stand-alone question
     * @return A string, the XML flow for an Item.
     */
    function export()
    {
    	$js = $html = '';
        list($js,$html) = $this->question->export();
        //list($js,$html) = $this->question->answer->export();
		if($this->standalone)
		{
	        $res = $this->start_page()
	               . $this->start_header()
	               . $this->start_js()
	               . $this->common_js()
	               . $js
	               . $this->end_js()
	               . $this->end_header()
	               . $this->start_body() 
	        //         .$this->answer->imsExportResponsesDeclaration($this->questionIdent)
	        //         . $this->start_item_body()
	        //           . $this->answer->scormExportResponses($this->questionIdent, $this->question->question, $this->question->description, $this->question->picture)
	        //			.$question
	      		   . $html
	               . $this->end_body()
	               . $this->end_page();
	        return $res;
		}
		else
		{
			return array($js,$html);
		}
    }     
}

/**
 * This class represents an entire exercise to be exported in SCORM.
 * It will be represented by a single <section> containing several <item>.
 *
 * Some properties cannot be exported, as SCORM does not support them :
 *   - type (one page or multiple pages)
 *   - start_date and end_date
 *   - max_attempts
 *   - show_answer
 *   - anonymous_attempts
 */
class ScormSection
{
    var $exercise;
    
    /**
     * Constructor.
     * @param $exe The Exercise instance to export
     * @author Amand Tihon <amand@alrj.org>
     */
    function ScormSection($exe)
    {
        $this->exercise = $exe;
    }
    
     
     /**
      * Start the XML flow.
      *
      * This opens the <item> block, with correct attributes.
      *
      */
     function start_page()
     {
        global $charset;
        $head = $foot = "";
		$head = '<?xml version="1.0" encoding="'.$charset.'" standalone="no"?>' . "\n".'<html>'."\n";
        return $head;
     }
      
     /**
      * End the XML flow, closing the </item> tag.
      *
      */
     function end_page()
     {
       return '</html>';
     }
	/**
	 * Start document header
	 */
	function start_header()
	{
		return '<head>'. "\n";
	}

	/**
	 * End document header
	 */
	function end_header()
	{
		return '</head>'. "\n";
	}
    /**
     * Start the itemBody
     * 
     */
    function start_js()
    {
       return '<script type="text/javascript" language="javascript">'. "\n";
    }
	/**
	 * Common JS functions
	 */
	function common_js()
	{
		$js = file_get_contents('../newscorm/js/api_wrapper.js');
		$js .= 'var questions = new Array();' . "\n";
		$js .= 'var questions_answers = new Array();' . "\n";
		$js .= 'var questions_answers_correct = new Array();' . "\n";
		$js .= 'var questions_types = new Array();' . "\n";
		$js .= "\n" . 
			'/**
             * Assigns any event handler to any element
             * @param	object	Element on which the event is added
             * @param	string	Name of event
             * @param	string	Function to trigger on event
             * @param	boolean	Capture the event and prevent 
             */
            function addEvent(elm, evType, fn, useCapture)
            { //by Scott Andrew
                if(elm.addEventListener){
            		elm.addEventListener(evType, fn, useCapture);
            		return true;
            	} else if(elm.attachEvent) {
            		var r = elm.attachEvent(\'on\' + evType, fn);
            		return r;
            	} else {
            		elm[\'on\' + evType] = fn;
            	} 
            }
            /**
             * Adds the event listener
             */
            function addListeners(e) {
            	loadPage();
            	/*
            	var my_form = document.getElementById(\'dokeos_scorm_form\');
            	addEvent(my_form,\'submit\',checkAnswers,false);
            	*/
            	var my_button = document.getElementById(\'dokeos_scorm_submit\');
            	addEvent(my_button,\'click\',checkAnswers,false);
            	//addEvent(my_button,\'change\',checkAnswers,false);
            	addEvent(window,\'unload\',unloadPage,false);
            }'."\n";
		
		$js .= '';
		//$js .= 'addEvent(window,\'load\',loadPage,false);'."\n";
		//$js .= 'addEvent(window,\'unload\',unloadPage,false);'."\n";
		$js .= 'addEvent(window,\'load\',addListeners,false);'."\n";
		return $js. "\n";
	}
    /**
     * End the itemBody part.
     *
     */
    function end_js()
    {
       return '</script>'. "\n";
    }
    /**
     * Start the itemBody
     * 
     */
    function start_body()
    {
       return '<body>'. "\n".
       		'<h1>'.$this->exercise->selectTitle().'</h1>'."\n".
			'<form id="dokeos_scorm_form" method="post" action="">'."\n";
    }
     
    /**
     * End the itemBody part.
     *
     */
    function end_body()
    {
       return '<br /><input type="button" id="dokeos_scorm_submit" name="dokeos_scorm_submit" value="OK" /></form>'."\n".'</body>'. "\n";
    }

    /**
     * Export the question as a SCORM Item.
     *
     * This is a default behaviour, some classes may want to override this.
     *
     * @param $standalone: Boolean stating if it should be exported as a stand-alone question
     * @return A string, the XML flow for an Item.
     */
    function export()
    {
        global $charset;
        
        $head = "";
        if ($this->standalone) {
            $head = '<?xml version = "1.0" encoding = "' . $charset . '" standalone = "no"?>' . "\n"
                  . '<!DOCTYPE questestinterop SYSTEM "ims_qtiasiv2p1.dtd">' . "\n";
        }

        list($js,$html) = $this->export_questions();
        //list($js,$html) = $this->question->answer->export();
        $res = $this->start_page()
               . $this->start_header()
               . $this->start_js()
               . $this->common_js()
               . $js
               . $this->end_js()
               . $this->end_header()
               . $this->start_body() 
        //         .$this->answer->imsExportResponsesDeclaration($this->questionIdent)
        //         . $this->start_item_body()
        //           . $this->answer->scormExportResponses($this->questionIdent, $this->question->question, $this->question->description, $this->question->picture)
        //			.$question
        			.$html
               . $this->end_body()
               . $this->end_page();
        
        return $res;
    }     
    
    /**
     * Export the questions, as a succession of <items>
     * @author Amand Tihon <amand@alrj.org>
     */
    function export_questions()
    {
        $js = $html = "";
        foreach ($this->exercise->selectQuestionList() as $q)
        {
        	list($jstmp,$htmltmp)= export_question($q, false);
        	$js .= $jstmp."\n";
        	$html .= $htmltmp."<br /><br />";
        }
        return array($js,$html);
    }
}

/*--------------------------------------------------------
      Functions
  --------------------------------------------------------*/

/**
 * Send a complete exercise in SCORM format, from its ID
 *
 * @param int $exerciseId The exercise to exporte
 * @param boolean $standalone Wether it should include XML tag and DTD line.
 * @return The XML as a string, or an empty string if there's no exercise with given ID.
 */
function export_exercise($exerciseId, $standalone=true)
{
    $exercise = new Exercise();
    if (! $exercise->read($exerciseId))
    {
        return '';
    }
    $ims = new ScormSection($exercise);
    $xml = $ims->export($standalone);
    return $xml;
}

/**
 * Returns the HTML + JS flow corresponding to one question
 * 
 * @param int The question ID
 * @param bool standalone (ie including XML tag, DTD declaration, etc)
 */
function export_question($questionId, $standalone=true)
{
    $question = new ScormQuestion();
    $qst = $question->read($questionId);
    if( !$qst )
    {
        return '';
    }
    $question->id = $qst->id;
    $question->type = $qst->type;
    $question->question = $qst->question;
    $question->description = $qst->description;
	$question->weighting=$qst->weighting;
	$question->position=$qst->position;
	$question->picture=$qst->picture;
    $assessmentItem = new ScormAssessmentItem($question,$standalone);
    //echo "<pre>".print_r($scorm,1)."</pre>";exit;
    return $assessmentItem->export();
}
?>