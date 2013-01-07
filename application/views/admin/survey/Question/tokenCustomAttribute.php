<div class='header ui-widget-header'><?php $clang->eT('Set benchmark attribute') ?></div>
<div id="tabs">
    <div class="ui-tabs ui-widget ui-widget-content ui-corner-all">
        <ul class="ui-tabs-nav ui-helper-reset ui-helper-clearfix ui-widget-header ui-corner-all">            
            <li><?php $clang->eT('Token Attribute'); ?></li>            
        </ul>

        <div class="ui-tabs-panel ui-widget-content ui-corner-bottom">
            <form id="addBencmark" class="form30" name="addBencmark" action="<?php echo $this->createUrl("admin/question/tokencustomattribute/surveyid/" . $surveyid . '/gid/' . $gid . '/qid/' . $qid); ?>" method="post">
                <select name="attribute">            
                    <option value="" <?php if (!empty($selected)) echo 'disabled="disabled"' ?>>Select attribute</option>
                    <?php
                    foreach ($customAttributes as $k => $att) {
                        $select = '';
                        if ($selected == $k) {

                            $select = ' selected="selected"';
                        }
                        echo '<option'.$disabled.' value="' . $k . '"' . $select . '>' . $att['description'] . '</option>';
                    };
                    ?>
                </select> 
                <?php if(empty($disabled)){ ?>
                <input type='submit' value='<?php $clang->eT('Save') ?>'/></p>
            <?php }?>
            </form>
        </div>
    </div>
</div>