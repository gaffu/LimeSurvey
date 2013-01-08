<?php

/**
 * Handles benchmark related tasks
 * Copyright (C) 2012 Jeppe Poss
 * All rights reserved.
 */
class Benchmark extends Survey_Common_Action {

    /**
     * Index page for benchmarking. This page allows setting parameters for
     * how the benchmark are to be performed.
     * @param mixed $iSurveyId the survey id.
     */
    public function index($iSurveyId) {
        $iSurveyId = sanitize_int($iSurveyId);

        $clang = $this->getController()->lang;

        if (!hasSurveyPermission($iSurveyId, 'statistics', 'read')) {
            Yii::app()->session['flashmessage'] = $clang->gT('You are not allowed to view this page');
            $this->getController()->redirect($this->getController()->createUrl('admin/index'));
        }

        if (is_null(Survey::model()->findByPk($iSurveyId))) {
            Yii::app()->session['flashmessage'] = $clang->gT('Invalid survey ID');
            $this->getController()->redirect($this->getController()->createUrl('admin/index'));
        }

        $aData['surveyid'] = $iSurveyId;

        // Fetch all languages for the given survey
        $criteria = new CDbCriteria();
        $criteria->select = 'surveyls_language';
        $criteria->condition = 'surveyls_survey_id = ' . $iSurveyId;
        $langRows = Surveys_languagesettings::model()->findAll($criteria);
        $aData['langauges'] = CHtml::listData($langRows, 'surveyls_language', 'surveyls_language');

        // Fetch all question types and find all that allows benchmarking
        $qTypes = getQuestionTypeList(null, 'array');
        $qFirst = true;
        foreach ($qTypes as $k => $v) {
            if ($v['benchmark'] === true) {
                if ($qFirst === true) {
                    $benchmarkTypeCondition = '(type = "' . $k . '"';
                    $qFirst = false;
                } else {
                    $benchmarkTypeCondition .= ' OR type = "' . $k . '"';
                }
            }
        }
        $benchmarkTypeCondition .= ') AND sid = ' . $iSurveyId;

        // Fetch distinct questions (based on qid to avoid duplicate entries
        // across different languages).
        $criteria->select = 'DISTINCT qid, title, question';
        $criteria->condition = $benchmarkTypeCondition;
        $qRows = Questions::model()->findAll($criteria);
        $temp = CHtml::listData($qRows, 'qid', 'question');
        foreach ($temp as $k => $v) {
            $v = strip_tags($v);
            if (strlen($v) > 50) {
                $v = substr($v, 0, 50);
            }
            $aData['questions'][$k] = $v;
        }
        $this->_renderWrappedTemplate('benchmark', array('index'), $aData);
    }

    /**
     * Generate a report based on post request. 
     * $_POST['language'] the selected language to use
     * $_POST['qid'] selected question id to use as benchmark
     * @param mixed $iSurveyId the survey id
     */
    public function generateReport($iSurveyId) {
        $completionstate = $_POST['completionstate'];
        $language = $_POST['language'];
        $bqid = $_POST['bqid']; // The qid for benchmarking
        if(isset($_POST['useCodes']) && $_POST['useCodes'] == 1){
            $useCodes = true;
        }else{
            $useCodes = false;
        }

        $iSurveyId = sanitize_int($iSurveyId);

        $clang = $this->getController()->lang;

        if (!hasSurveyPermission($iSurveyId, 'statistics', 'read')) {
            Yii::app()->session['flashmessage'] = $clang->gT('You do not have permission to view this page');
            $this->getController()->redirect($this->getController()->createUrl('admin/index'));
        }

        if (is_null(Survey::model()->findByPk($iSurveyId))) {
            Yii::app()->session['flashmessage'] = $clang->gT('Invalid survey ID');
            $this->getController()->redirect($this->getController()->createUrl('admin/index'));
        }

        // Fetch all questions and answers
        $criteria = new CDbCriteria();
        $criteria->select = '*';
//        $criteria->join = 'LEFT JOIN ' . Answers::model()->tableName() . ' a
//                                ON t.qid = a.qid AND a.language = "'.$language.'"'; // not working
        $criteria->condition = 't.sid = ' . $iSurveyId;
        $criteria->with = 'answers';
        $rows = Questions::model()->findAll($criteria);

        // combine questions and answers (from answers table) in an array
        foreach ($rows as $row) {
            $qid = $row->getAttribute('qid');
            $attributes = $row->getAttributes();
            /* Crazy hack to solve faulty database design. If question has parent
             * then there is a slight possibility that it will be impossible to start
             * a new survey. This is based on that the dynamic survey question fields
             * are created by concat parent_id with title. If the qid was used
             * consistently this would not be necessary.
             */
            if ($attributes['parent_qid'] != 0) {
                $nqid = $attributes['parent_qid'] . $attributes['title'];
            } else {
                $nqid = $qid;
            }
            $qa[$nqid] = $attributes;
            $relatedRows = $row->getRelated('answers');
            foreach ($relatedRows as $relatedRow) {
                // Only take the answers with the selected langauage
                // Yii sqrews up the join so it takes all languages
                if ($relatedRow->getAttribute('language') == $language) {
                    $qa[$nqid]['answers'][$relatedRow->getAttribute('code')] = $relatedRow->getAttributes();
                }
            }
        }
        
        // Condition for filtering all, complete or incomplete responses
        $condition = '';
        if($completionstate == 'complete'){
            $condition = 'submitdate is NOT null';
        }elseif($completionstate == 'incomplete'){
            $condition = 'submitdate is null';
        }

        Survey_dynamic::sid($iSurveyId);
        $responses = Survey_dynamic::model()->findAllAsArray($condition);

        // Count answers
        $benchmarkColumn = null;
        foreach ($responses as $respons) {
            // Identify benchmark column
            if ($benchmarkColumn === null) {
                foreach ($respons as $k => $v) {
                    if (substr($k, (strlen($qid) + 1) * -1, $qid + 1) == 'X' . $bqid) {
                        $benchmarkColumn = $k;
                    }
                }
            }
            $benchmarkValue = $respons[$benchmarkColumn];
            // count the number of answers on the perticular benchmark
            // Also include the responses for easier view generation
            foreach ($respons as $k => $v) {
                if (substr($k, 0, strlen($iSurveyId) + 1) == $iSurveyId . 'X' && $benchmarkColumn != $k) {
                    $key = substr($k, strripos($k, 'X') + 1);
                    if (isset($statistics[$benchmarkValue]['summary'][$key][$v])) {
                        $statistics[$benchmarkValue]['summary'][$key][$v]++;
                        $statistics[$benchmarkValue]['responses'][$respons['id']][$key] = $v;
                    } else {
                        $statistics[$benchmarkValue]['summary'][$key][$v] = 1;
                        $statistics[$benchmarkValue]['responses'][$respons['id']][$key] = $v;
                    }
                }
            }
        }

        // Fetch survey title and description
        $criteriaSurveyInfo = new CDbCriteria;
        $criteriaSurveyInfo->select = 'surveyls_title, surveyls_description';
        $criteriaSurveyInfo->condition = 'surveyls_survey_id ='.$iSurveyId.' AND surveyls_language = "'.$language.'"';
        $surveyInfo = Surveys_languagesettings::model()->find($criteriaSurveyInfo)->getAttributes();

        $this->writeExcel($iSurveyId, $statistics, $qa, $surveyInfo, $useCodes);
        Yii::app()->request->redirect($this->getController()->createUrl('/admin/benchmark/index/surveyid/' . $iSurveyId));
    }

    /**
     * Method to output an Excel file with benchmark statistics
     * @param array $statistics array containing responses and counts
     * @param array $qa         array containing questions and answers (from answer table)    
     */
    protected function writeExcel($iSurveyId, $statistics, $qa, $surveyInfo, $useCodes = false) {
        $tempdir = Yii::app()->getConfig("tempdir");
        Yii::import('application.libraries.admin.pear.Spreadsheet.Excel.Xlswriter', true);
        $filename = 'statistic-benchmark-survey' . $iSurveyId . '.xls';
        $workbook = new Xlswriter();

        $workbook->setVersion(8);

        $workbook->setTempDir($tempdir);

        // Create the first worksheet (used for responses)
        $sheet = $workbook->addWorksheet(utf8_decode('responses-survey' . $iSurveyId));
        $sheet->setInputEncoding('utf-8');
        $sheet->setColumn(0, 20, 20);
        $xlsRow = 0;

        // create the second worksheet (used for statistics)
        $sheet2 = $workbook->addWorksheet(utf8_decode('summary-survey' . $iSurveyId));
        $sheet2->setInputEncoding('utf-8');
        $sheet2->setColumn(0, 20, 20);
        $xlsRow2 = 0;

        $xlsTitle = html_entity_decode($surveyInfo['surveyls_title'], ENT_QUOTES, 'UTF-8');
        $xlsDesc = html_entity_decode($surveyInfo['surveyls_description'], ENT_QUOTES, 'UTF-8');

        // Write title and description on sheet 1
        $sheet->write($xlsRow, 0, $xlsTitle);
        $xlsRow++;
        $sheet->write($xlsRow, 0, $xlsDesc);
        $xlsRow++;
        $xlsRow++;

        // Write title and description on sheet 2
        $sheet2->write($xlsRow2, 0, $xlsTitle);
        $xlsRow2++;
        $sheet2->write($xlsRow2, 0, $xlsDesc);
        $xlsRow2++;
        $xlsRow2++;
        
        // Set bold format
        $format_bold =& $workbook->addFormat();
        $format_bold->setBold();
        
        // Set percentage format
        $format_percentage =& $workbook->addFormat();
        $format_percentage->setNumFormat("0.00%");
        
        // Set 2 decimals format (bold)
        $format_2decimals =& $workbook->addFormat();
        $format_2decimals->setNumFormat("0.00");
        $format_2decimals->setBold();
        
        // used for writting question text on responses sheet
        $questionRow = true;
        $questionColumn = 1;
        
        // Write info text on summary sheet
        $sheet2->write(2, 1, 'Question',$format_bold);
        $sheet2->write(2, 2, 'Answer', $format_bold);
        $sheet2->write(2, 3, 'Count', $format_bold);
        $sheet2->write(2, 4, 'Percentage', $format_bold);
        
        // Loop through all the benchmark values
        foreach ($statistics as $benchmark => $v) {
            $responsesCount = count($v['responses']);
            // write benchmark info on both pages
            $sheet->write($xlsRow, 0, $benchmark, $format_bold);
            $sheet2->write($xlsRow2, 0, $benchmark, $format_bold);
            $xlsRow++;
            $startRow = $xlsRow+1;
            $doAverage = array();
            // Loop through all the responses for the selected benchmark value
            foreach ($v['responses'] as $respons) {               
                $columnCount = 1;                
                // For each respons write their answer
                // If quesetion has answers stored in the answers table
                // then replace the answer with the given value from the answers table
                foreach ($respons as $question => $answer) {
                    if ($qa[$question]['parent_qid'] != 0 && isset($qa[$qa[$question]['parent_qid']]['answers'])) {
                        if(isset($qa[$qa[$question]['parent_qid']]['answers'][$answer]['code']) && $useCodes === true){
                            $ans = $qa[$qa[$question]['parent_qid']]['answers'][$answer]['code'];
                        }else{
                            $ans = $qa[$qa[$question]['parent_qid']]['answers'][$answer]['answer'];
                        }                        
                    } elseif (isset($qa[$question]['answers'])) {
                        $ans = $qa[$question]['answers'][$answer]['answer'];
                    } else {
                        $ans = $answer;                        
                    }
                    if(!is_numeric($ans) && !empty($ans)){
                        $doAverage[$columnCount]['valid'] = false;
                    }
                    if(!empty($ans)){
                        $doAverage[$columnCount]['gotData'] = true;
                    }
                    $sheet->write($xlsRow, $columnCount, $ans);
                    $columnCount++;
                }
                $xlsRow++;
            }
            // Do avarage calculation on each answer if it is allowed
            for ($i = 1; $i < $columnCount; $i++) {
                if(!isset($doAverage[$i]['valid']) && isset($doAverage[$i]['gotData'])){
                    $column = $this->numtochars($i+1);
                    $sheet->write($xlsRow, $i, '=AVERAGE('.$column.$startRow.':'.$column.$xlsRow.')', $format_2decimals);
                }                
            }
            $xlsRow++;
            // Write count for each question / answer on statistic page
            foreach ($v['summary'] as $qid => $anwsers) {
                $xlsRow2++;
                $columnCount = 1;
                // Write question field value                
                //$sheet2->write($xlsRow2, $columnCount, html_entity_decode($qa[$qid]['question'],ENT_QUOTES, 'UTF-8'));   
                if($questionRow){                    
                    $sheet->write(2, $questionColumn, $qa[$qid]['question']);
                    $questionColumn++;
                }
                $sheet2->write($xlsRow2, $columnCount, $qa[$qid]['question']);
                foreach ($anwsers as $answer => $answerCount) {
                    $columnCount = 2;
                    $xlsRow2++;
                    if (empty($answer)) {
                        $ans = "No answer";
                    }
                    elseif ($qa[$qid]['parent_qid'] != 0 && isset($qa[$qa[$qid]['parent_qid']]['answers'])) {
                        $ans = $qa[$qa[$qid]['parent_qid']]['answers'][$answer]['answer'];
                    } elseif (isset($qa[$qid]['answers'])) {
                        $ans = $qa[$qid]['answers'][$answer]['answer'];
                    } else {
                        $ans = $answer;
                    }
                    // Write the answer
                    $sheet2->write($xlsRow2, $columnCount, $ans);
                    $columnCount++;
                    // Write how many picked that perticular answer
                    $sheet2->write($xlsRow2, $columnCount, $answerCount);
                    $columnCount++;
                    // Write out how many picked the perticular answer as percentage for the given benchmark
                    $sheet2->write($xlsRow2, $columnCount, '='.$answerCount.'/'.$responsesCount, $format_percentage);
                }
                $xlsRow2++;
            }            
            $questionRow = false;
            $xlsRow2++;
            $xlsRow++;
        }
        $workbook->send($filename);
        $workbook->close();
        exit();
    }

    /**
     * Renders template(s) wrapped in header and footer
     * @param string|array $aViewUrls View url(s)
     * @param array $aData Data to be passed on. Optional.
     */
    protected function _renderWrappedTemplate($sAction = '', $aViewUrls = array(), $aData = array()) {
        $this->getController()->_js_admin_includes(Yii::app()->getConfig('adminscripts') . 'browse.js');

        $aData['display']['menu_bars'] = false;
        $aData['display']['menu_bars']['browse'] = Yii::app()->lang->gT('Select benchmark');

        parent::_renderWrappedTemplate('benchmark', $aViewUrls, $aData);
    }

    /**
     * Number to char(s) conversion.
     * Courtesy of stanislav
     * http://php.net/manual/en/function.chr.php
     * @param int $num number to convert
     * @param int $start start ascii code
     * @param int $end end ascii code
     * @return string string representation of the number
     */
    function numtochars($num, $start = 65, $end = 90) {
        $sig = ($num < 0);
        $num = abs($num);
        $str = "";
        $cache = ($end - $start);
        while ($num != 0) {
            $str = chr(($num % $cache) + $start - 1) . $str;
            $num = ($num - ($num % $cache)) / $cache;
        }
        if ($sig) {
            $str = "-" . $str;
        }
        return $str;
    }

}

?>