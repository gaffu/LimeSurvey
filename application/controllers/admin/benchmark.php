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

        // combine questions and answers in an array
        foreach ($rows as $row) {
            $qid = $row->getAttribute('qid');
            $qa[$qid] = $row->getAttributes();
            $relatedRows = $row->getRelated('answers');
            foreach ($relatedRows as $relatedRow) {
                // Only take the answers with the selected langauage
                // Yii sqrews up the join so it takes all languages
                if ($relatedRow->getAttribute('language') == $language) {
                    $qa[$qid]['answers'][$relatedRow->getAttribute('code')] = $relatedRow->getAttributes();
                }
            }
        }

        Survey_dynamic::sid($iSurveyId);
        $responses = Survey_dynamic::model()->findAllAsArray();

        // Count answers
        $benchmarkColumn = null;
        foreach ($responses as $respons) {
            // Identify benchmark column
            if($benchmarkColumn === null){
                foreach ($respons as $k => $v) {
                    if( substr($k, (strlen($qid)+1)*-1, $qid+1)== 'X'.$bqid){
                        $benchmarkColumn = $k;
                    }
                }
            }
            $benchmarkValue = $respons[$benchmarkColumn];
            // count the number of answers on the perticular benchmark
            foreach ($respons as $k => $v) {
                $t = substr($k, 0, strlen($iSurveyId)+1);
                if (substr($k, 0, strlen($iSurveyId)+1) == $iSurveyId.'X') {
                    if(isset($statistics[$benchmarkValue][$k][$v])){
                        $statistics[$benchmarkValue][$k][$v]++;
                    }else{
                        $statistics[$benchmarkValue][$k][$v] = 1;
                    }
                }                        
            }
        }
        
        $aData['statistics'] = $statistics;
        $aData['qa'] = $qa;
        $aData['surveyid'] = $iSurveyId;
        $aData['benchmark'] = $benchmarkColumn;
        $this->_renderWrappedTemplate('benchmark', array('view'), $aData);
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