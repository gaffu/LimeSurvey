<form action="<?php echo $this->createUrl("admin/benchmark/generaterapport/surveyid/$surveyid"); ?>" method="post">
<?php 
    echo CHtml::dropDownList('language', null, $langauges);
    echo CHtml::dropDownList('bqid', null, $questions);
?>
    <input type="submit" value="<?php $clang->eT('Generate'); ?>" />
</form>