<?php
require_once dirname(__FILE__) . '/../../../core/php/core.inc.php';
function Volets_install(){
	/*log::add('Volets','debug','Lancement du script de mise a jours'); 
	foreach(eqLogic::byType('Volets') as $eqLogic){
		$Actions=null;
		foreach($eqLogic->getConfiguration('action') as $key => $ActionGroup){
			if($key == 'open' || $key == 'close'){
				foreach($ActionGroup as $Action){
					$Action['saison']="all";
					$Action['TypeGestion']="all";
					$Action['evaluation']=$key;
					$Actions[]=$Action;
				}
			}
			else
				$Actions[]=$ActionGroup;
		}
		$eqLogic->setConfiguration('action',$Actions);
		$Armed=$eqLogic->getCmd('',"arme");
		if(is_object($Armed))
			$Armed->remove();
		$Released=$eqLogic->getCmd('',"disable");
		if(is_object($Released))
			$Released->remove();
		$eqLogic->save();
	}
	log::add('Volets','debug','Fin du script de mise a jours'); */
}
function Volets_update(){
	/*log::add('Volets','debug','Lancement du script de mise a jours'); 
	foreach(eqLogic::byType('Volets') as $eqLogic){
		$Actions=null;
		foreach($eqLogic->getConfiguration('action') as $key => $ActionGroup){
			if($key == 'open' || $key == 'close'){
				foreach($ActionGroup as $Action){
					$Action['saison']="all";
					$Action['TypeGestion']="all";
					$Action['evaluation']=$key;
					$Actions[]=$Action;
				}
			}
			else
				$Actions[]=$ActionGroup;				
		}
		$eqLogic->setConfiguration('action',$Actions);
		$Armed=$eqLogic->getCmd('',"arme");
		if(is_object($Armed))
			$Armed->remove();
		$Released=$eqLogic->getCmd('',"disable");
		if(is_object($Released))
			$Released->remove();
		$eqLogic->save();
	}
	log::add('Volets','debug','Fin du script de mise a jours'); */
}
function Volets_remove(){
	foreach(eqLogic::byType('Volets') as $Volet){
		$listener = listener::byClassAndFunction('Volets', 'pull', array('Volets_id' => $Volet->getId()));
		if (is_object($listener))
			$listener->remove();
		$cron = cron::byClassAndFunction('Volets', 'ActionJour', array('Volets_id' => $Volet->getId()));
		if (is_object($cron)) 	
			$cron->remove();
		$cron = cron::byClassAndFunction('Volets', 'ActionNuit', array('Volets_id' => $Volet->getId()));
		if (is_object($cron)) 	
			$cron->remove();
		
	}
}
?>
