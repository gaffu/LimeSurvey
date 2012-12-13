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
                $emailStatus = $tokenRow->getAttribute('emailstatus');
                $emailhistory = $tokenRow->getAttribute('emailhistory');
                
                // Switch through the type of event and act accordingly
                switch ($post['event']) {
                    case 'send' :
                        return;
                        break;
                    case 'hard_bounce':
                        $tokenRow->setAttribute('emailstatus', $post['event']);
                        $tokenRow->setAttribute('emailhistory', $emailhistory . date('d F Y H:i:s', $post['ts']) . ' - ' . $post['event'] . "\n");
                        break;
                    case 'soft_bounce':
                        $tokenRow->setAttribute('emailstatus', $post['event']);
                        $tokenRow->setAttribute('emailhistory', $emailhistory . date('d F Y H:i:s', $post['ts']) . ' - ' . $post['event'] . "\n");
                        break;
                    case 'open':
                        $tokenRow->setAttribute('emailstatus', $post['event']);
                        $tokenRow->setAttribute('emailhistory', $emailhistory . date('d F Y H:i:s', $post['ts']) . ' - ' . $post['event'] . "\n");
                        break;
                    case 'click':
                        $last = count($post['msg']['clicks']) - 1;
                        $tokenRow->setAttribute('emailstatus', $post['event']);
                        $tokenRow->setAttribute('emailhistory', $emailhistory . date('d F Y H:i:s', $post['ts']) . ' - ' . $post['event'] . ' - ' . $post['msg']['clicks'][$last]['url'] . "\n");
                        break;
                    case 'spam':
                        $tokenRow->setAttribute('emailstatus', $post['event']);
                        $tokenRow->setAttribute('emailhistory', $emailhistory . date('d F Y H:i:s', $post['ts']) . ' - ' . $post['event'] . "\n");
                        break;
                    case 'unsub':
                        $tokenRow->setAttribute('emailstatus', $post['event']);
                        $tokenRow->setAttribute('emailhistory', $emailhistory . date('d F Y H:i:s', $post['ts']) . ' - ' . $post['event'] . "\n");
                        break;
                    case 'reject':
                        $tokenRow->setAttribute('emailstatus', $post['event']);
                        $tokenRow->setAttribute('emailhistory', $emailhistory . date('d F Y H:i:s', $post['ts']) . ' - ' . $post['event'] . "\n");
                        break;
                    default:
                        return;
                }
                // Save changes
                $tokenRow->save();
            }
        }
    }

}

?>
