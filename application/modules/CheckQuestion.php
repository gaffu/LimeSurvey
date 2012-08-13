<?php
class CheckQuestion extends QuestionModule
{
    protected $children;
    public function getAnswerHTML()
    {
        global $thissurvey;

        $clang = Yii::app()->lang;
        if ($thissurvey['nokeyboard']=='Y')
        {
            includeKeypad();
            $kpclass = "text-keypad";
        }
        else
        {
            $kpclass = "";
        }

        // Find out if any questions have attributes which reference this questions
        // based on value of attribute. This could be array_filter and array_filter_exclude

        $attribute_ref=false;

        $qaquery = "SELECT qid,attribute FROM {{question_attributes}} WHERE value LIKE '".strtolower($this->title)."' and (attribute='array_filter' or attribute='array_filter_exclude')";
        $qaresult = Yii::app()->db->createCommand($qaquery)->query();     //Checked
        foreach ($qaresult->readAll() as $qarow)
        {
            $qquery = "SELECT qid FROM {{questions}} WHERE sid=".$thissurvey['sid']." AND scale_id=0 AND qid=".$qarow['qid'];
            $qresult = Yii::app()->db->createCommand($qquery)->query();     //Checked
            if ($qresult->getRowCount() > 0)
            {
                $attribute_ref = true;
            }
        }

        $checkconditionFunction = "checkconditions";

        $aQuestionAttributes = $this->getAttributeValues();

        if (trim($aQuestionAttributes['other_replace_text'][$_SESSION['survey_'.$this->surveyid]['s_lang']])!='')
        {
            $othertext=$aQuestionAttributes['other_replace_text'][$_SESSION['survey_'.$this->surveyid]['s_lang']];
        }
        else
        {
            $othertext=$clang->gT('Other:');
        }

        if (trim($aQuestionAttributes['display_columns'])!='')
        {
            $dcols = $aQuestionAttributes['display_columns'];
        }
        else
        {
            $dcols = 1;
        }

        if ($aQuestionAttributes['other_numbers_only']==1)
        {
            $sSeperator = getRadixPointData($thissurvey['surveyls_numberformat']);
            $sSeperator= $sSeperator['seperator'];
            $oth_checkconditionFunction = "fixnum_checkconditions";
        }
        else
        {
            $oth_checkconditionFunction = "checkconditions";
        }

        $ansresult = $this->getChildren();
        $anscount = count($ansresult);

        if (trim($aQuestionAttributes['exclude_all_others'])!='' && $aQuestionAttributes['random_order']==1)
        {
            //if  exclude_all_others is set then the related answer should keep its position at all times
            //thats why we have to re-position it if it has been randomized
            $position=0;
            foreach ($ansresult as $answer)
            {
                if ((trim($aQuestionAttributes['exclude_all_others']) != '')  &&    ($answer['title']==trim($aQuestionAttributes['exclude_all_others'])))
                {
                    if ($position==$answer['question_order']-1) break; //already in the right position
                    $tmp  = array_splice($ansresult, $position, 1);
                    array_splice($ansresult, $answer['question_order']-1, 0, $tmp);
                    break;
                }
                $position++;
            }
        }

        if ($this->isother == 'Y')
        {
            $anscount++; //COUNT OTHER AS AN ANSWER FOR MANDATORY CHECKING!
        }

        $wrapper = setupColumns($dcols, $anscount,"subquestions-list questions-list checkbox-list","question-item answer-item checkbox-item");

        $answer = '<input type="hidden" name="MULTI'.$this->fieldname.'" value="'.$anscount."\" />\n\n".$wrapper['whole-start'];

        $fn = 1;
        if (!isset($multifields))
        {
            $multifields = '';
        }

        $rowcounter = 0;
        $colcounter = 1;
        $startitem='';
        $postrow = '';
        $trbc='';
        foreach ($ansresult as $ansrow)
        {
            $myfname = $this->fieldname.$ansrow['title'];
            $extra_class="";

            $trbc='';
            /* Check for array_filter */
            list($htmltbody2, $hiddenfield)=return_array_filter_strings($this, $aQuestionAttributes, $thissurvey, $ansrow, $myfname, $trbc, $myfname, "li","question-item answer-item checkbox-item".$extra_class);

            if(substr($wrapper['item-start'],0,4) == "\t<li")
            {
                $startitem = "\t$htmltbody2\n";
            } else {
                $startitem = $wrapper['item-start'];
            }

            /* Print out the checkbox */
            $answer .= $startitem;
            $answer .= "\t$hiddenfield\n";
            $answer .= '		<input class="checkbox" type="checkbox" name="'.$this->fieldname.$ansrow['title'].'" id="answer'.$this->fieldname.$ansrow['title'].'" value="Y"';

            /* If the question has already been ticked, check the checkbox */
            if (isset($_SESSION['survey_'.$this->surveyid][$myfname]))
            {
                if ($_SESSION['survey_'.$this->surveyid][$myfname] == 'Y')
                {
                    $answer .= CHECKED;
                }
            }
            $answer .= " onclick='cancelBubbleThis(event);";

            $answer .= ''
            .  "$checkconditionFunction(this.value, this.name, this.type)' />\n"
            .  "<label for=\"answer$this->fieldname{$ansrow['title']}\" class=\"answertext\">"
            .  $ansrow['question']
            .  "</label>\n";

            ++$fn;
            /* Now add the hidden field to contain information about this answer */
            $answer .= '		<input type="hidden" name="java'.$myfname.'" id="java'.$myfname.'" value="';
            if (isset($_SESSION['survey_'.$this->surveyid][$myfname]))
            {
                $answer .= $_SESSION['survey_'.$this->surveyid][$myfname];
            }
            $answer .= "\" />\n{$wrapper['item-end']}";

            ++$rowcounter;
            if ($rowcounter == $wrapper['maxrows'] && $colcounter < $wrapper['cols'])
            {
                if($colcounter == $wrapper['cols'] - 1)
                {
                    $answer .= $wrapper['col-devide-last'];
                }
                else
                {
                    $answer .= $wrapper['col-devide'];
                }
                $rowcounter = 0;
                ++$colcounter;
            }
        }

        if ($this->isother == 'Y')
        {
            $myfname = $this->fieldname.'other';
            list($htmltbody2, $hiddenfield)=return_array_filter_strings($this, $aQuestionAttributes, $thissurvey, array("code"=>"other"), $myfname, $trbc, $myfname, "li","question-item answer-item checkbox-item other-item");

            if(substr($wrapper['item-start-other'],0,4) == "\t<li")
            {
                $startitem = "\t$htmltbody2\n";
            } else {
                $startitem = $wrapper['item-start-other'];
            }
            $answer .= $startitem;
            $answer .= $hiddenfield.'
            <input class="checkbox other-checkbox" type="checkbox" name="'.$myfname.'cbox" alt="'.$clang->gT('Other').'" id="answer'.$myfname.'cbox"';
            // othercbox can be not display, because only input text goes to database

            if (isset($_SESSION['survey_'.$this->surveyid][$myfname]) && trim($_SESSION['survey_'.$this->surveyid][$myfname])!='')
            {
                $answer .= CHECKED;
            }
            $answer .= " onclick='cancelBubbleThis(event);if(this.checked===false){ document.getElementById(\"answer$myfname\").value=\"\"; document.getElementById(\"java$myfname\").value=\"\"; $checkconditionFunction(\"\", \"$myfname\", \"text\"); }";
            $answer .= " if(this.checked===true) { document.getElementById(\"answer$myfname\").focus(); }; LEMflagMandOther(\"$myfname\",this.checked);";
            $answer .= "' />
            <label for=\"answer$myfname\" class=\"answertext\">".$othertext."</label>
            <input class=\"text ".$kpclass."\" type=\"text\" name=\"$myfname\" id=\"answer$myfname\" value=\"";
            if (isset($_SESSION['survey_'.$this->surveyid][$myfname]))
            {
                $dispVal = $_SESSION['survey_'.$this->surveyid][$myfname];
                if ($aQuestionAttributes['other_numbers_only']==1)
                {
                    $dispVal = str_replace('.',$sSeperator,$dispVal);
                }
                $answer .= htmlspecialchars($dispVal,ENT_QUOTES);
            }
            $answer .= "\" onkeyup='if ($.trim(this.value)!=\"\") { \$(\"#answer{$myfname}cbox\").attr(\"checked\",\"checked\"); } else { \$(\"#answer{$myfname}cbox\").attr(\"checked\",\"\"); }; $(\"#java{$myfname}\").val(this.value);$oth_checkconditionFunction(this.value, this.name, this.type); LEMflagMandOther(\"$myfname\",\$(\"#answer{$myfname}cbox\").attr(\"checked\"));'/>";
            $answer .="\" />";
            $answer .="<script type='text/javascript'>\n";
            $answer .="$('#answer{$myfname}').bind('keyup blur',function(){\n";
            $answer .= " if ($.trim($(this).val())!=\"\") { \$(\"#answer{$myfname}cbox\").attr(\"checked\",true); } else { \$(\"#answer{$myfname}cbox\").attr(\"checked\",false); }; $(\"#java{$myfname}\").val($(this).val());$oth_checkconditionFunction(this.value, this.name, this.type); LEMflagMandOther(\"$myfname\",\$(\"#answer{$myfname}cbox\").attr(\"checked\"));\n";
            $answer .="});\n";
            $answer .="</script>\n";
            $answer .= '<input type="hidden" name="java'.$myfname.'" id="java'.$myfname.'" value="';

            if (isset($_SESSION['survey_'.$this->surveyid][$myfname]))
            {
                $dispVal = $_SESSION['survey_'.$this->surveyid][$myfname];
                if ($aQuestionAttributes['other_numbers_only']==1)
                {
                    $dispVal = str_replace('.',$sSeperator,$dispVal);
                }
                $answer .= htmlspecialchars($dispVal,ENT_QUOTES);
            }

            $answer .= "\" />\n{$wrapper['item-end']}";
            ++$anscount;

            ++$rowcounter;
            if ($rowcounter == $wrapper['maxrows'] && $colcounter < $wrapper['cols'])
            {
                if($colcounter == $wrapper['cols'] - 1)
                {
                    $answer .= $wrapper['col-devide-last'];
                }
                else
                {
                    $answer .= $wrapper['col-devide'];
                }
                $rowcounter = 0;
                ++$colcounter;
            }
        }
        $answer .= $wrapper['whole-end'];

        $answer .= $postrow;
        return $answer;
    }

    public function getDataEntry($idrow, &$fnames, $language)
    {
        $q = $this;
        while ($q->id == $this->id)
        {
            if (substr($q->fieldname, -5) == "other")
            {
                $output .= "\t<input type='text' name='{$q->fieldname}' value='"
                .htmlspecialchars($idrow[$q->fieldname], ENT_QUOTES) . "' />\n";
            }
            else
            {
                $output .= "\t<input type='checkbox' class='checkboxbtn' name='{$q->fieldname}' value='Y'";
                if ($idrow[$q->fieldname] == "Y") {$output .= " checked";}
                $output .= " />{$q->sq}<br />\n";
            }

            if(!$fname=next($fnames)) break;
            $q=$fname['q'];
        }
        prev($fnames);
        return $output;
    }

    protected function getChildren()
    {
        if ($this->children) return $this->children;
        $aQuestionAttributes = $this->getAttributeValues();
        if ($aQuestionAttributes['random_order']==1) {
            $ansquery = "SELECT * FROM {{questions}} WHERE parent_qid=$this->id AND scale_id=0 AND language='".$_SESSION['survey_'.$this->surveyid]['s_lang']."' ORDER BY ".dbRandom();
        }
        else
        {
            $ansquery = "SELECT * FROM {{questions}} WHERE parent_qid=$this->id AND scale_id=0 AND language='".$_SESSION['survey_'.$this->surveyid]['s_lang']."' ORDER BY question_order";
        }
        $ansresult = dbExecuteAssoc($ansquery)->readAll();  //Checked

        if (trim($aQuestionAttributes['exclude_all_others'])!='' && $aQuestionAttributes['random_order']==1)
        {
            //if  exclude_all_others is set then the related answer should keep its position at all times
            //thats why we have to re-position it if it has been randomized
            $position=0;
            foreach ($ansresult as $answer)
            {
                if ((trim($aQuestionAttributes['exclude_all_others']) != '')  &&    ($answer['title']==trim($aQuestionAttributes['exclude_all_others'])))
                {
                    if ($position==$answer['question_order']-1) break; //already in the right position
                    $tmp  = array_splice($ansresult, $position, 1);
                    array_splice($ansresult, $answer['question_order']-1, 0, $tmp);
                    break;
                }
                $position++;
            }
        }
        return $this->children  = $ansresult;
    }

    public function getTitle()
    {
        $clang=Yii::app()->lang;
        $aQuestionAttributes = $this->getAttributeValues();
        if (count($this->getChildren()) > 0 && $aQuestionAttributes['hide_tip']==0)
        {
            $maxansw=trim($aQuestionAttributes['max_answers']);
            $minansw=trim($aQuestionAttributes['min_answers']);
            if (!($maxansw || $minansw))
            {
                return $this->text."<br />\n<span class=\"questionhelp\">".$clang->gT('Check any that apply').'</span>';
            }
        }
        return $this->text;
    }

    public function getHelp()
    {
        $clang=Yii::app()->lang;
        $aQuestionAttributes = $this->getAttributeValues();
        if (count($this->getChildren()) > 0 && $aQuestionAttributes['hide_tip']==0)
        {
            $maxansw=trim($aQuestionAttributes['max_answers']);
            $minansw=trim($aQuestionAttributes['min_answers']);
            if (!($maxansw || $minansw))
            {
                return $clang->gT('Check any that apply');
            }
        }
        return '';
    }

    public function createFieldmap($type=null)
    {
        $clang = Yii::app()->lang;
        $map = array();
        $abrows = getSubQuestions($this);
        foreach ($abrows as $abrow)
        {
            $fieldname="{$this->surveyid}X{$this->gid}X{$this->id}{$abrow['title']}";
            $field['fieldname']=$fieldname;
            $field['type']=$type;
            $field['sid']=$this->surveyid;
            $field['gid']=$this->gid;
            $field['qid']=$this->id;
            $field['aid']=$abrow['title'];
            $field['sqid']=$abrow['qid'];
            $field['title']=$this->title;
            $field['question']=$this->text;
            $field['subquestion']=$abrow['question'];
            $field['group_name']=$this->groupname;
            $field['mandatory']=$this->mandatory;
            $field['hasconditions']=$this->conditionsexist;
            $field['usedinconditions']=$this->usedinconditions;
            $field['questionSeq']=$this->questioncount;
            $field['groupSeq']=$this->groupcount;
            $field['preg']=$this->haspreg;
            $q = clone $this;
            if(isset($this->defaults) && isset($this->defaults[$abrow['qid']])) $q->default=$field['defaultvalue']=$this->defaults[$abrow['qid']];
            else
            {
                unset($field['defaultvalue']);
                unset($q->default);
            }
            $q->fieldname = $fieldname;
            $q->aid=$field['aid'];
            $q->sq=$abrow['question'];
            $q->sqid=$abrow['qid'];
            $q->preg=$this->haspreg;
            $field['q']=$q;
            $map[$fieldname]=$field;
        }
        if ($this->isother=='Y')
        {
            $other = parent::createFieldmap($type);
            $other = $other[$this->fieldname];
            $other['fieldname'].='other';
            $other['aid']='other';
            $other['subquestion']=$clang->gT("Other");
            $other['other']=$this->isother;
            $q = clone $this;
            if (isset($this->defaults) && isset($this->defaults['other'])) $q->default=$other['defaultvalue']=$this->defaults['other'];
            else
            {
                unset($other['defaultvalue']);
                unset($q->default);
            }
            $q->fieldname .= 'other';
            $q->aid = 'other';
            $q->sq = $clang->gT("Other");
            $q->other = $this->isother;
            $other['q']=$q;
            $map[$other['fieldname']]=$other;
        }

        return $map;
    }

    public function getExtendedAnswer($value, $language)
    {
        if($value=="Y") return $language->gT("Yes")." [$value]";
        return $value;
    }

    public function getQuotaValue($value)
    {
        return array($this->surveyid.'X'.$this->gid.'X'.$this->id.$value => 'Y');
    }

    public function setAssessment()
    {
        if (isset($_SESSION['survey_'.$this->surveyid][$this->fieldname]) && $_SESSION['survey_'.$this->surveyid][$this->fieldname] == "Y")
        {
            $aAttributes=$this->getAttributeValues();
            $this->assessment_value=(int)$aAttributes['assessment_value'];
        } else {
            $this->assessment_value = 0;
        }
        return true;
    }

    public function getDBField()
    {
        if ($this->aid != 'other' && strpos($this->aid,'comment')===false && strpos($this->aid,'othercomment')===false)
        {
            return "VARCHAR(5)";
        }
        else
        {
            return "text";
        }
    }

    public function prepareConditions($row)
    {
        if (preg_match("/^\+(.*)$/",$row['cfieldname'],$cfieldnamematch))
        { // this condition uses a single checkbox as source
            return array("cfieldname"=>$cfieldnamematch[1],
            "value"=>$row['value'],
            "matchfield"=>$row['cfieldname'],
            "matchvalue"=>$row['value'],
            "matchmethod"=>$row['method'],
            "subqid"=>$cfieldnamematch[1].'NAOK'
            );
        }

        return array("cfieldname"=>$rows['cfieldname'].$rows['value'],
        "value"=>$row['value'],
        "matchfield"=>$row['cfieldname'],
        "matchvalue"=>"Y",
        "matchmethod"=>$row['method'],
        "subqid"=>$row['cfieldname']
        );
    }

    public function transformResponseValue($export, $value, $options)
    {
        if ($value == 'N' && $options->convertN)
        {
            //echo "Transforming 'N' to ".$options->nValue.PHP_EOL;
            return $options->nValue;
        }
        else if ($value == 'Y' && $options->convertY)
        {
            //echo "Transforming 'Y' to ".$options->yValue.PHP_EOL;
            return $options->yValue;
        }
        return parent::transformResponseValue($export, $value, $options);
    }

    public function getFullAnswer($answerCode, $export, $survey)
    {
        if (mb_substr($this->fieldname, -5, 5) == 'other' || mb_substr($this->fieldname, -7, 7) == 'comment')
        {
            //echo "\n -- Branch 1 --";
            return $answerCode;
        }
        else
        {
            switch ($answerCode)
            {
                case 'Y':
                    return $export->translator->translate('Yes', $export->languageCode);
                case 'N':
                case '':
                    return $export->translator->translate('No', $export->languageCode);
                default:
                    //echo "\n -- Branch 2 --";
                    return $answerCode;
            }
        }
    }

    public function getFieldSubHeading($survey, $export, $code)
    {
        //This section creates differing output from the old code base, but I do think
        //that it is more correct than the old code.
        $isOther = ($this->aid == 'other');
        $isComment = (mb_substr($this->aid, -7, 7) == 'comment');

        if ($isComment)
        {
            $isOther = (mb_substr($this->aid, 0, -7) == 'other');
        }

        if ($isOther)
        {
            return ' '.$export->getOtherSubHeading();
        }
        else if (!$code)
        {
            $sqs = $survey->getSubQuestionArrays($this->id);
            foreach ($sqs as $sq)
            {
                if (!$isComment && $sq['title'] == $this->aid)
                {
                    $value = $sq['question'];
                }
            }
            if (!empty($value))
            {
                return ' ['.$value.']';
            }
        }
        elseif (!$isComment)
        {
            return ' ['.$this->aid.']';
        }
        else
        {
            return ' '.$export->getCommentSubHeading();
        }
    }

    public function getSPSSAnswers()
    {
        if ($this->aid == 'other' || strpos($this->aid,'comment') !== false) return array();
        $answers[] = array('code'=>1, 'value'=>$clang->gT('Yes'));
        $answers[] = array('code'=>0, 'value'=>$clang->gT('Not Selected'));
        return $answers;
    }

    public function getSPSSData($data, $iLength, $na)
    {
        if ($this->aid == 'other' || strpos($this->aid,'comment') !== false)
        {
            return parent::getSPSSData($data, $iLength, $na);
        } else if ($data == 'Y'){
            return "'1'";
        } else {
            return "'0'";
        }
    }

    public function jsVarNameOn()
    {
        return 'java'.$this->fieldname;
    }

    public function onlyNumeric()
    {
        $attributes = $this->getAttributeValues();
        return array_key_exists('other_numbers_only', $attributes) && $attributes['other_numbers_only'] == 1 && preg_match('/other$/',$this->fieldname);
    }

    public function getCsuffix()
    {
        return $this->aid;
    }

    public function getSqsuffix()
    {
        return '_' . $this->aid;
    }

    public function getVarName()
    {
        return $this->title . '_' . $this->aid;
    }

    public function getQuestion()
    {
        return $this->sq;
    }

    public function getRowDivID()
    {
        return $this->fieldname;
    }

    public function compareField($sgqa, $sq)
    {
        return $sgqa == $sq['rowdivid'] || $sgqa == ($sq['rowdivid'] . 'comment');
    }

    public function includeRelevanceStatus()
    {
        return true;
    }

    public function getVarAttributeShown($name, $default, $gseq, $qseq, $ansArray)
    {
        $code = LimeExpressionManager::GetVarAttribute($name,'code',$default,$gseq,$qseq);
        if ($code == 'Y' && isset($this->sq) && !preg_match('/comment$/',$this->fieldname))
        {
            return $this->sq;
        }
        elseif (preg_match('/comment$/',$this->fieldname) && isset($_SESSION[$this->fieldname])) {
            return $_SESSION[$this->fieldname];
        }
        else
        {
            return $default;
        }
    }

    public function getMandatoryTip()
    {
        if ($this->other == 'Y')
        {
            $clang=Yii::app()->lang;
            $attributes = $this->getAttributeValues();
            if (trim($attributes['other_replace_text']) != '') {
                $othertext = trim($qattr['other_replace_text']);
            }
            else {
                $othertext = $clang->gT('Other:');
            }
            return "<br />\n".sprintf($clang->gT("If you choose '%s' you must provide a description."), $othertext);
        }
        else
        {
            return '';
        }
    }

    public function anyUnanswered($relevantSQs, $unansweredSQs)
    {
        return count($relevantSQs) > 0 && (count($relevantSQs) == count($unansweredSQs));
    }

    public function availableAttributes($attr = false)
    {
        $attrs=array("array_filter","array_filter_exclude","array_filter_style","assessment_value","display_columns","em_validation_q","em_validation_q_tip","exclude_all_others","exclude_all_others_auto","statistics_showgraph","hide_tip","hidden","max_answers","min_answers","other_numbers_only","other_replace_text","page_break","public_statistics","random_order","parent_order","scale_export","random_group");
        return $attr?in_array($attr,$attrs):$attrs;
    }

    public function questionProperties($prop = false)
    {
        $clang=Yii::app()->lang;
        $props=array('description' => $clang->gT("Multiple choice"),'group' => $clang->gT("Multiple choice questions"),'subquestions' => 1,'class' => 'multiple-opt','hasdefaultvalues' => 1,'assessable' => 1,'answerscales' => 0);
        return $prop?$props[$prop]:$props;
    }
}
?>