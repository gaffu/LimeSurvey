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
                        $emailhistory .= $post['ts'] . ' - ' . $post['event'] . "\n";
                        $tokenRow->setAttribute('emailstatus', $this->sortHistory($emailhistory));
                        $tokenRow->setAttribute('emailhistory', $emailhistory);
                        break;
                    case 'hard_bounce':
                        $emailhistory .= $post['ts'] . ' - ' . $post['event'] . "\n";
                        $tokenRow->setAttribute('emailstatus', $this->sortHistory($emailhistory));
                        $tokenRow->setAttribute('emailhistory', $emailhistory);
                        break;
                    case 'soft_bounce':
                        $emailhistory .= $post['ts'] . ' - ' . $post['event'] . "\n";
                        $tokenRow->setAttribute('emailstatus', $this->sortHistory($emailhistory));
                        $tokenRow->setAttribute('emailhistory', $emailhistory);
                        break;
                    case 'open':
                        $emailhistory .= $post['ts'] . ' - ' . $post['event'] . "\n";
                        $tokenRow->setAttribute('emailstatus', $this->sortHistory($emailhistory));
                        $tokenRow->setAttribute('emailhistory', $emailhistory);
                        break;
                    case 'click':
                        $last = count($post['msg']['clicks']) - 1;
                        $emailhistory .= $post['ts'] . ' - ' . $post['event'] . ' - ' . $post['msg']['clicks'][$last]['url'] . "\n";
                        $tokenRow->setAttribute('emailstatus', $this->sortHistory($emailhistory));
                        $tokenRow->setAttribute('emailhistory', $emailhistory);
                        break;
                    case 'spam':
                        $emailhistory .= $post['ts'] . ' - ' . $post['event'] . "\n";
                        $tokenRow->setAttribute('emailstatus', $this->sortHistory($emailhistory));
                        $tokenRow->setAttribute('emailhistory', $emailhistory);
                        break;
                    case 'unsub':
                        $emailhistory .= $post['ts'] . ' - ' . $post['event'] . "\n";
                        $tokenRow->setAttribute('emailstatus', $this->sortHistory($emailhistory));
                        $tokenRow->setAttribute('emailhistory', $emailhistory);
                        break;
                    case 'reject':
                        $emailhistory .= $post['ts'] . ' - ' . $post['event'] . "\n";
                        $tokenRow->setAttribute('emailstatus', $this->sortHistory($emailhistory));
                        $tokenRow->setAttribute('emailhistory', $emailhistory);
                        break;
                    default:
                        return;
                }
                // Save changes
                $tokenRow->save();
            }
        }
    }

    /**
     * Sorts the history string by date. Mandrill does not guarantee webhooks
     * will be posted in chronological order.
     * @param string $emailhistory the history string
     * @return string the newest event type
     */
    function sortHistory(&$emailhistory) {
        $histories = explode("\n", $emailhistory);
        rsort($histories);
        $emailhistory = '';
        for($i=0; $i<count($histories); $i++){
            if(empty($histories[$i])){
                unset($histories[$i]);
            }else{
                $emailhistory .= $histories[$i] . "\n";
            }
        }
        $firstEvent = explode(' - ', $histories[0]);
        return $firstEvent[1];
    }

}

?>
