<form action="index.php?step_name=backup" method="post">
	<p class="title"><?php echo $title; ?></p>

	<?php
	if($errors || $warnings){
	    echo '<div>'
	       . '<a href="http://wiki.knowledgetree.com/Web_Based_Upgrader#Post_Upgrade" target="_blank">'
	       . 'Click Here for help on overcoming backup issues</a></div><br/>';
	}
	?>
	<div id="step_content_complete" class="step">
	<br/><br/>
	<div>
    <?php
    if ($dir != '') {
        if (!$backupStatus) {
            ?>
                    Are you sure you want to perform the backup?
            
            <p>
            Your mysql installation has been resolved. Manually, you would do the following:
                </p>
            <p>
            <table bgcolor="lightgrey">
            <tr>
            <td>
            <nobr>cd "<?php echo $dir; ?>"</nobr>
            <br/>
            </p><?php
        }
    }
    else
    {
    ?>
      The mysql backup utility could not be found automatically. Either do a manual backup, or edit the config.ini 
      and update the backup/mysql Directory entry.
<P>
You can continue to do the backup manually using the following process:
<P>
<table bgcolor="lightgrey">
<tr>
<td><?php
    }
?><nobr><?php echo $display; ?></nobr>
</table>
<P>
	</div>
	</div>
    <?php
if ($dir != '')
{
    if (($action == '') || ($action == 'confirm')) {
        ?><input type="submit" name="BackupNow" value="Next" class="button_next"><?php
    }
    else if ($backupStatus) {
        ?><input type="submit" name="Next" value="Restore" class="button_next">
        <input type="submit" name="Upgrade" value="Upgrade" class="button_next"><?php
    }
    else {
        ?><input type="submit" name="Next" value="Next" class="button_next"><?php
    }
}

    ?>
    <input type="submit" name="Previous" value="Back" class="button_previous">
</form>