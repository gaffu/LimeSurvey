<?php

/**
 * Class for recieving post request from Mandrill webhooks
 */
class MandrillWebHookController extends LSYii_Controller {

    /**
     * Method Mandrill webhooks should point at
     */
    public function actionWebhook() {
        // Check if post has correct name
        if (isset($_POST['mandrill_events'])) {
            // Decode the value as a json object
            $post = json_decode($_POST['mandrill_events'], true);
            $post = $post[0];
            // Make sure it is a webhook intended for Limesurvey
            if (isset($post['msg']['tags']) && $post['msg']['tags'][0] == 'limesurvey' && substr($post['msg']['tags'][1], 0, 6) == 'Survey') {
                $surveyId = substr($post['msg']['tags'][1], 7);

                // Criteria for the token that the post was intended for
                $criteria = new CDbCriteria();
                $criteria->condition = 'email = "' . $post['msg']['email'] . '"';
                $tokenRow = Tokens_dynamic::model($surveyId)->find($criteria);
                $emailhistory = unserialize($tokenRow->getAttribute('emailhistory'));
                
                // Add new post request to the emailhistory array
                $emailhistory[$post['msg']['ts']][] = $post;
                // sort it so the newest is at the start of the array
                krsort($emailhistory);                
                foreach($emailhistory as $history){
                    // Set the latest post request as the email status
                    $tokenRow->setAttribute('emailstatus', $history[0]['event']);
                    break;
                }
                $tokenRow->setAttribute('emailhistory', serialize($emailhistory));
                $tokenRow->save();
                return;
            }
        }
    }
}
?>