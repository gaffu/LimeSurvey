<?php

class Benchmark extends Survey_Common_Action
{
 
    public function index($iSurveyId){
        $iSurveyID = sanitize_int($iSurveyId);

        $clang = $this->getController()->lang;
        
        $thissurvey = getSurveyInfo($iSurveyId);
        //$aData['thissurvey'] = $thissurvey;
        $aData['surveyid'] = $iSurveyId;

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
        $aData['display']['menu_bars']['browse'] = Yii::app()->lang->gT('Browse responses'); // browse is independent of the above

        parent::_renderWrappedTemplate('benchmark', $aViewUrls, $aData);
    }
}
?>
