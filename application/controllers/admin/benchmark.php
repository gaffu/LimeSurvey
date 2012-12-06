<?php

/**
 * Handles benchmark related tasks
 * Copyright (C) 2012 Jeppe Poss
 * All rights reserved.
 */
class Benchmark extends Survey_Common_Action {

    /**
     * The Excel worksheet we are working on
     *  
     * @var Spreadsheet_Excel_Writer_Worksheet
     */
    protected $sheet;

    /**
     * The current Excel workbook we are working on
     * 
     * @var Xlswriter 
     */
    protected $workbook;

    /**
     * Keeps track of the current row in Excel sheet
     * 
     * @var int
     */
    protected $xlsRow = 0;

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
        $language = $_POST['language'];
        $bqid = $_POST['bqid']; // The qid for benchmarking

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

        Survey_dynamic::sid($iSurveyId);
        $responses = Survey_dynamic::model()->findAllAsArray();

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
                if (substr($k, 0, strlen($iSurveyId) + 1) == $iSurveyId . 'X') {
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
        $this->writeExcel($statistics, $qa, $iSurveyId);
        Yii::app()->request->redirect($this->getController()->createUrl('/admin/benchmark/index/surveyid/' . $iSurveyId));
    }

    /**
     * Method to output an Excel file with benchmark statistics
     * @param array $statistics array containing responses and counts
     * @param array $qa         array containing questions and answers (from answer table)    
     */
    protected function writeExcel($statistics, $qa, $iSurveyId) {
        $tempdir = Yii::app()->getConfig("tempdir");
        Yii::import('application.libraries.admin.pear.Spreadsheet.Excel.Xlswriter', true);
        $filename = 'statistic-benchmark-survey' . $iSurveyId . '.xls';
        $this->workbook = new Xlswriter();

        $this->workbook->setVersion(8);
        // Inform the module that our data will arrive as UTF-8.
        // Set the temporary directory to avoid PHP error messages due to open_basedir restrictions and calls to tempnam("", ...)
        $this->workbook->setTempDir($tempdir);

        // Create the first worksheet (used for responses)
        $this->sheet = $this->workbook->addWorksheet(utf8_decode('responses-survey' . $iSurveyId));
        $this->sheet->setInputEncoding('utf-8');
        $this->sheet->setColumn(0, 20, 20);

        // create the second worksheet (used for statistics)
        $sheet2 = $this->workbook->addWorksheet(utf8_decode('summary-survey' . $iSurveyId));
        $sheet2->setInputEncoding('utf-8');
        $sheet2->setColumn(0, 20, 20);
        $xlsRow2 = 0;


        //$xlsTitle = sprintf($statlang->gT("Field summary for %s"), html_entity_decode("FIX IN CODE", ENT_QUOTES, 'UTF-8'));
        $xlsTitle = html_entity_decode("Some title", ENT_QUOTES, 'UTF-8');
        $xlsDesc = html_entity_decode("Some description", ENT_QUOTES, 'UTF-8');

        // Write title and description on sheet 1
        $this->sheet->write($this->xlsRow, 0, $xlsTitle);
        $this->xlsRow++;
        $this->sheet->write($this->xlsRow, 0, $xlsDesc);
        $this->xlsRow++;
        $this->xlsRow++;

        // Write title and description on sheet 2
        $sheet2->write($xlsRow2, 0, $xlsTitle);
        $xlsRow2++;
        $sheet2->write($xlsRow2, 0, $xlsDesc);
        $xlsRow2++;
        $xlsRow2++;

        // Loop through all the benchmark values
        foreach ($statistics as $benchmark => $v) {
            // write benchmark info on both pages
            $this->sheet->write($this->xlsRow, 0, $benchmark);
            $sheet2->write($xlsRow2, 0, $benchmark);
            $this->xlsRow++;
            // Loop through all the responses for the selected benchmark value
            foreach ($v['responses'] as $respons) {
                $columnCount = 1;
                // For each respons write their answer
                // If quesetion has answers stored in the answers table
                // then replace the answer with the given value from the answers table
                foreach ($respons as $question => $answer) {
                    if ($qa[$question]['parent_qid'] != 0){
                        $this->sheet->write ($this->xlsRow, $columnCount, $qa[$qa[$question]['parent_qid']]['answers'][$answer]['answer']);
                    } elseif (isset($qa[$question]['answers'])) {
                        $this->sheet->write($this->xlsRow, $columnCount, $qa[$question]['answers'][$answer]['answer']);
                    } else {
                        $this->sheet->write($this->xlsRow, $columnCount, $answer);
                    }
                    $columnCount++;
                }
                $this->xlsRow++;
            }
            // Write count for each question / answer on statistic page
            foreach ($v['summary'] as $qid => $anwsers) {
                $xlsRow2++;
                $columnCount = 1;
                // Write question field value                
                //$sheet2->write($xlsRow2, $columnCount, html_entity_decode($qa[$qid]['question'],ENT_QUOTES, 'UTF-8'));   
                $sheet2->write($xlsRow2, $columnCount, $qa[$qid]['question']);
                foreach ($anwsers as $answer => $answerCount) {
                    $columnCount = 2;
                    $xlsRow2++;
                    if (empty($answer)) {
                        $answer = "No Answer";
                    }
                    $sheet2->write($xlsRow2, $columnCount, $answer);
                    $columnCount++;
                    $sheet2->write($xlsRow2, $columnCount, $answerCount);
                }
            }
            $xlsRow2++;
            $this->xlsRow++;
        }
        $this->workbook->send($filename);
        $this->workbook->close();
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
        $aData['display']['menu_bars']['browse'] = Yii::app()->lang->gT('Select benchmark'); // browse is independent of the above

        parent::_renderWrappedTemplate('benchmark', $aViewUrls, $aData);
    }

}

?>