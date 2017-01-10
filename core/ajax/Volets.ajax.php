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
			$geoloc=cmd::byId($heliotrope->getConfiguration('geoloc'));
			if(is_object($geoloc)){
				$result['geoloc']=utils::o2a($geoloc);
			}
		}
		ajax::success($result);
	}
	throw new Exception(__('Aucune méthode correspondante à : ', __FILE__) . init('action'));
	/*     * *********Catch exeption*************** */
} catch (Exception $e) {
	ajax::error(displayExeption($e), $e->getCode());
}
?>
