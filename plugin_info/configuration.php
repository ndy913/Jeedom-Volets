<?php
require_once dirname(__FILE__) . '/../../../core/php/core.inc.php';
include_file('core', 'authentification', 'php');
if (!isConnect()) {
    include_file('desktop', '404', 'php');
    die();
}
?>
	<form class="form-horizontal">
		<fieldset>
			<div class="form-group">
				<label class="col-lg-4 control-label">{{Bing API Key}}</label>
				<div class="col-lg-4">
					<input class="configKey form-control" data-l1key="BingAPIKey" />
				</div>
			</div>
		</fieldset>
	</form>
