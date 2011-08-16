<script type="text/javascript">
$(function(){	swfu_settings.upload_url = "/files/upload";swfu = new SWFUpload(swfu_settings); });
</script>
<div id="uploader">
<div id="spanButtonPlaceHolder"></div>
</div>
<div id="filelisting">
<?php $this->render('filelist'); ?>
</div>