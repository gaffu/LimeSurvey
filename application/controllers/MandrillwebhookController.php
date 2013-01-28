<?php

/**
 * Class for recieving post request from Mandrill webhooks
 * Copyright (C) 2012 Jeppe Poss
 */
class MandrillWebHookController extends LSYii_Controller {

    /**
     * Method Mandrill webhooks should point at
     */
    public function actionWebhook() {
        // Check if post has correct name
        $logPath = Yii::app()->getRuntimePath();
//        file_put_contents($logPath.'/MandrillInfo.log', print_r($_POST, true)."\n", FILE_APPEND);
        if (isset($_POST['mandrill_events'])) {
            // Decode the value as a json object
            $posts = json_decode($_POST['mandrill_events'], true);
            foreach ($posts as $post) {
                try {
                    // Make sure it is a webhook intended for Limesurvey
                    if (isset($post['msg']['tags'][0]) && isset($post['msg']['tags'][1])) {
                        if ($post['msg']['tags'][0] == 'limesurvey' || substr($post['msg']['tags'][0], 0, 6) == 'Survey') {
                            if ($post['msg']['tags'][1] == 'limesurvey' || substr($post['msg']['tags'][1], 0, 6) == 'Survey') {
                                if ($post['msg']['tags'][0] == 'limesurvey') {
                                    $surveyId = substr($post['msg']['tags'][1], 7);
                                } else {
                                    $surveyId = substr($post['msg']['tags'][0], 7);
                                }

                                // Criteria for the token that the post was intended for
                                $criteria = new CDbCriteria();
                                $criteria->condition = 'tid = "' . $post['msg']['metadata']['tid'] . '"';
                                $tokenRow = Tokens_dynamic::model($surveyId)->find($criteria);
                                if (!empty($tokenRow)) {
                                    $emailhistory = unserialize($tokenRow->getAttribute('emailhistory'));
                                    // Add new post request to the emailhistory array
                                    $emailhistory[$post['ts']][] = $post;
                                    // sort it so the newest is at the start of the array
                                    krsort($emailhistory);
                                    foreach ($emailhistory as $history) {
                                        // Set the latest post request as the email status
                                        if ($tokenRow->getAttribute('emailstatus') != 'OptOut') {
                                            $tokenRow->setAttribute('emailstatus', $history[0]['event']);
                                        }
                                        break;
                                    }
                                    $tokenRow->setAttribute('emailhistory', serialize($emailhistory));
                                    $tokenRow->save();
                                }
                            }
                        }
                    }
                } catch (Exception $e) {
                    file_put_contents($logPath . '/MandrillError.log', $e->getMessage() . "\n", FILE_APPEND);
                }
            }
        }
    }

}

?>