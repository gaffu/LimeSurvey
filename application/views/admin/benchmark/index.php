<form action="<?php echo $this->createUrl("admin/benchmark/generatereport/surveyid/$surveyid"); ?>" method="post">
    <table style="margin-left:auto; margin-right: auto; padding-top: 50px;">
        <tr>
            <td><label for='completionstate'><?php $clang->eT("Include:"); ?> </label></td>
            <td><select name='completionstate' id='completionstate'>
                    <option value='all' <?php echo $selectshow; ?>><?php $clang->eT("All responses"); ?></option>
                    <option value='complete' <?php echo $selecthide; ?> > <?php $clang->eT("Completed responses only"); ?></option>
                    <option value='incomplete' <?php echo $selectinc; ?> > <?php $clang->eT("Incomplete responses only"); ?></option>
                </select>
            </td>
        </tr>
        <tr>
            <td><label for="language"><?php $clang->eT('Select answer language'); ?></label></td>
            <td><?php echo CHtml::dropDownList('language', null, $langauges); ?></td>
        </tr>
        <tr>
            <td><label for="bqid"><?php $clang->eT('Select benchmark question'); ?></label></td>
            <td><?php echo CHtml::dropDownList('bqid', null, $questions); ?></td>
        </tr>
        <tr>
            <td><label for="useCodes"><?php $clang->eT('Use answer codes?'); ?></label></td>
            <td><input id="useCodes" type="checkbox" name="useCodes" value="1" /></td>
        </tr>        
        <tr>
            <td colspan="2" style="text-align: center;"><input type="submit" value="<?php $clang->eT('Generate'); ?>" /></td>
        </tr>
    </table>
</form>