<?php
require_once dirname(__FILE__) . '/../../../core/php/core.inc.php';
function Volets_install(){
}
function Volets_update(){
	log::add('Volets','debug','Lancement du script de mise a jours'); 
	foreach(eqLogic::byType('Volets') as $eqLogic){
		$listener = listener::byClassAndFunction('Volets', 'pull', array('Volets_id' => $Volet->getId()));
		if (is_object($listener))
			$listener->remove();
		$cron = cron::byClassAndFunction('Volets', 'ActionJour', array('Volets_id' => $Volet->getId()));
		if (is_object($cron)) 	
			$cron->remove();
		$cron = cron::byClassAndFunction('Volets', 'ActionNuit', array('Volets_id' => $Volet->getId()));
		if (is_object($cron)) 	
			$cron->remove();
		$cron = cron::byClassAndFunction('Volets', 'ActionMeteo', array('Volets_id' => $Volet->getId()));
		if (is_object($cron)) 	
			$cron->remove();
		if($eqLogic->getConfiguration('Azimuth'))
			$eqLogic->setConfiguration('Azimut',true);
		if($eqLogic->getConfiguration('Present'))
			$eqLogic->setConfiguration('Absent',true);
		if($eqLogic->getConfiguration('DayNight')){
			$eqLogic->setConfiguration('Jours',true);
			$eqLogic->setConfiguration('Nuit',true);
		}
		$Conditions=$eqLogic->getConfiguration('condition');
		foreach($Conditions as $CondiKey => $Condition){	
			foreach($Condition["TypeGestion"] as $TypeGestionKey => $TypeGestion){	
				if($TypeGestion == "Day"){
					$Conditions[$CondiKey]["TypeGestion"]["Jour"]=true;
					 unset($Conditions[$ActionKey]["TypeGestion"][$TypeGestionKey]);
				}
				if($TypeGestion == "Night"){
					$Conditions[$CondiKey]["TypeGestion"]["Nuit"]=true;
					 unset($Conditions[$ActionKey]["TypeGestion"][$TypeGestionKey]);
				}
				if($TypeGestion == "Presence"){
					$Conditions[$CondiKey]["TypeGestion"]["Absent"]=true;
					 unset($Conditions[$ActionKey]["TypeGestion"][$TypeGestionKey]);
				}
				if($TypeGestion == "Azimuth"){
					$Conditions[$CondiKey]["TypeGestion"]["Azimut"]=true;
					 unset($Conditions[$ActionKey]["TypeGestion"][$TypeGestionKey]);
				}
			}
		}
		$eqLogic->setConfiguration('condition',$Conditions);	
		$Actions=$eqLogic->getConfiguration('action');
		foreach($Actions as $ActionKey => $Action){	
			foreach($Action["TypeGestion"] as $TypeGestionKey => $TypeGestion){	
				if($TypeGestion == "Day"){
					$Actions[$ActionKey]["TypeGestion"]["Jour"]=true;
					 unset($Actions[$ActionKey]["TypeGestion"][$TypeGestionKey]);
				}
				if($TypeGestion == "Night"){
					$Actions[$ActionKey]["TypeGestion"]["Nuit"]=true;
					 unset($Actions[$ActionKey]["TypeGestion"][$TypeGestionKey]);
				}
				if($TypeGestion == "Presence"){
					$Actions[$ActionKey]["TypeGestion"]["Absent"]=true;
					 unset($Actions[$ActionKey]["TypeGestion"][$TypeGestionKey]);
				}
				if($TypeGestion == "Azimuth"){
					$Conditions[$CondiKey]["TypeGestion"]["Azimut"]=true;
					 unset($Actions[$ActionKey]["TypeGestion"][$TypeGestionKey]);
				}
			}
		}
		$eqLogic->setConfiguration('action',$Actions);	
		$eqLogic->save();
	}
	log::add('Volets','debug','Fin du script de mise a jours');
}
function Volets_remove(){
	foreach(eqLogic::byType('Volets') as $Volet){
		$listener = listener::byClassAndFunction('Volets', 'pull', array('Volets_id' => $Volet->getId()));
		if (is_object($listener))
			$listener->remove();
		$cron = cron::byClassAndFunction('Volets', 'GestionJour', array('Volets_id' => $Volet->getId()));
		if (is_object($cron)) 	
			$cron->remove();
		$cron = cron::byClassAndFunction('Volets', 'GestionNuit', array('Volets_id' => $Volet->getId()));
		if (is_object($cron)) 	
			$cron->remove();
		$cron = cron::byClassAndFunction('Volets', 'GestionMeteo', array('Volets_id' => $Volet->getId()));
		if (is_object($cron)) 	
			$cron->remove();
		
	}
}
?>
