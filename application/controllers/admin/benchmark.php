<?php

/**
 * Handles benchmark related tasks
 * Copyright (C) 2012 Jeppe Poss
 * All rights reserved.
 */
class Benchmark extends Survey_Common_Action
{
 
    /**
     * Index page for benchmarking. This page allows setting parameters for
     * how the benchmark are to be performed.
     * @param mixed $iSurveyId the survey id.
     */
    public function index($iSurveyId){
        $iSurveyId = sanitize_int($iSurveyId);

        //$clang = $this->getController()->lang;

        $aData['surveyid'] = $iSurveyId;
        
        // Fetch all languages for the given survey
        $criteria = new CDbCriteria();
        $criteria->select = 'surveyls_language';
        $criteria->condition = 'surveyls_survey_id = '.$iSurveyId;              
        $langRows = Surveys_languagesettings::model()->findAll($criteria);
        $aData['langauges'] = CHtml::listData($langRows, 'surveyls_language', 'surveyls_language');
        
        // Fetch all question types and find all that allows benchmarking
        $qTypes = getQuestionTypeList(null, 'array');
        $qFirst = true;
        foreach($qTypes as $k => $v){
            if($v['benchmark'] === true){
                if($qFirst === true){
                    $benchmarkTypeCondition = 'type = "'.$k.'"';
                    $qFirst = false;
                }else{
                    $benchmarkTypeCondition .= ' OR type = "'.$k.'"';
                }
            }
        }
        
        // Fetch distinct questions (based on qid to avoid duplicate entries
        // across different languages).
        $criteria->select = 'DISTINCT qid, title, question';
        $criteria->condition = $benchmarkTypeCondition;        
        $qRows = Questions::model()->findAll($criteria);
//        foreach($qRows as $qRow){
//            $aData['questions'][] = $qRow->getAttributes();
//        }     
        $aData['questions'] = CHtml::listData($qRows, 'qid', 'question');
        $this->_renderWrappedTemplate('benchmark', array('index'), $aData);
    }
    
    /**
     * Renders template(s) wrapped in header and footer
     *
     * @param string|array $aViewUrls View url(s)
     * @param array $aData Data to be passed on. Optional.
     */
    protected function _renderWrappedTemplate($sAction='', $aViewUrls = array(), $aData = array())
    {
        $this->getController()->_js_admin_includes(Yii::app()->getConfig('adminscripts') . 'browse.js');

        $aData['display']['menu_bars'] = false;
        $aData['display']['menu_bars']['browse'] = Yii::app()->lang->gT('Select benchmark'); // browse is independent of the above

        parent::_renderWrappedTemplate('benchmark', $aViewUrls, $aData);
    }
}
?>
