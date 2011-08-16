<table>
<thead>
<tr>
<td>Filename</td>
<td>Uploaded On</td>
<td>Uploaded By</td>
<td>Re-Post</td>
</tr>
</thead>
<tbody>
<?php $odd='even'; foreach($files as $file): ?>
<tr class="<?php echo $odd = (($odd == 'odd')?'even':'odd'); ?> <?php echo implode(' ', explode('/', $file->filetype)); ?>">
<td class="filename"><a href="<?php echo $file->url; ?>" target="_blank"><?php echo $file->filename; ?></a></td>
<td><?php echo date('M j, Y h:i:s a', strtotime($file->filedate)); ?></td>
<td><?php echo $file->username; ?></td>
<td>
<a class="file_send" href="#" onclick="send('/file <?php echo $file->id; ?>');return false;">send</a>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
