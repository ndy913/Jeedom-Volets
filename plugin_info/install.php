<?php
require_once dirname(__FILE__) . '/../../../core/php/core.inc.php';
function Volets_update(){
	log::add('Volets','debug','Lancement du script de mise a jours'); 
	foreach(eqLogic::byType('Volets') as $eqLogic){
		$Armed=$eqLogic->getCmd('',"arme");
		$Armed->remove();
		$Released=$eqLogic->getCmd('',"disable");
		$Released->remove();
		$eqLogic->save();
	}
}
?>
