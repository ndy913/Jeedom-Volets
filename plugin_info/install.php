<?php
require_once dirname(__FILE__) . '/../../../core/php/core.inc.php';
function Volets_install(){
	log::add('Volets','debug','Lancement du script de mise a jours'); 
	foreach(eqLogic::byType('Volets') as $eqLogic){
		$Armed=$eqLogic->getCmd('',"arme");
		if(is_object($Armed))
			$Armed->remove();
		$Released=$eqLogic->getCmd('',"disable");
		if(is_object($Released))
			$Released->remove();
		$eqLogic->save();
	}
	log::add('Volets','debug','Fin du script de mise a jours'); 
}
function Volets_update(){
	log::add('Volets','debug','Lancement du script de mise a jours'); 
	foreach(eqLogic::byType('Volets') as $eqLogic){
		$Armed=$eqLogic->getCmd('',"arme");
		if(is_object($Armed))
			$Armed->remove();
		$Released=$eqLogic->getCmd('',"disable");
		if(is_object($Released))
			$Released->remove();
		$eqLogic->save();
	}
	log::add('Volets','debug','Fin du script de mise a jours'); 
}
?>
