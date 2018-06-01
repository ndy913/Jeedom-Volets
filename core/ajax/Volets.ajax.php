<?php
try {
	require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';
	include_file('core', 'authentification', 'php');

	if (!isConnect('admin')) {
		throw new Exception(__('401 - Accès non autorisé', __FILE__));
	}
	if (init('action') == 'getInformation') {
		$result=array();
		$heliotrope=eqLogic::byId(init('heliotrope'));
		if(is_object($heliotrope)){
			$result['heliotrope']=utils::o2a($heliotrope->getCmd());
			//$geoloc=eqLogic::byId($heliotrope->getConfiguration('geoloc'));
			$geoloc = geotravCmd::byEqLogicIdAndLogicalId($heliotrope->getConfiguration('geoloc'),'location:coordinate');
			if(is_object($geoloc)){
				$result['geoloc']=$geoloc->execCmd();
			}
		}
		ajax::success($result);
	}
	if (init('action') == 'getTemplate') {
		$path = dirname(__FILE__) . '/../config/devices';
		if (isset($_device) && $_device != '') {
			$files = ls($path, $_device . '.json', false, array('files', 'quiet'));
			if (count($files) == 1) {
				try {
					$content = file_get_contents($path . '/' . $files[0]);
					if (is_json($content)) {
						$deviceConfiguration = json_decode($content, true);
						ajax::success($deviceConfiguration[$_device]);
					}
				} catch (Exception $e) {
					ajax::error(displayExeption($e), $e->getCode());
				}
			}
		}
		$files = ls($path, '*.json', false, array('files', 'quiet'));
		$return = array();
		foreach ($files as $file) {
			try {
				$content = file_get_contents($path . '/' . $file);
				if (is_json($content)) {
					$return = array_merge($return, json_decode($content, true));
				}
			} catch (Exception $e) {
			}
		}
		if (isset($_device) && $_device != '') {
			if (isset($return[$_device])) {
				ajax::success($return[$_device]);
			}
			ajax::success(array());
		}
		ajax::success($return);
	}
	throw new Exception(__('Aucune méthode correspondante à : ', __FILE__) . init('action'));
	/*     * *********Catch exeption*************** */
} catch (Exception $e) {
	ajax::error(displayExeption($e), $e->getCode());
}
?>
