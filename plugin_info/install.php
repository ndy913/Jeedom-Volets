<?php
require_once dirname(__FILE__) . '/../../../core/php/core.inc.php';
function Volets_install(){
}
function Volets_update(){
	log::add('Volets','debug','Lancement du script de mise a jours'); 
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
		$cron = cron::byClassAndFunction('Volets', 'ActionMeteo', array('Volets_id' => $Volet->getId()));
		if (is_object($cron)) 	
			$cron->remove();
		if($Volet->getConfiguration('Azimuth'))
			$Volet->setConfiguration('Azimut',true);
		if($Volet->getConfiguration('Present'))
			$Volet->setConfiguration('Absent',true);
		if($Volet->getConfiguration('DayNight')){
			$Volet->setConfiguration('Jours',true);
			$Volet->setConfiguration('Nuit',true);
		}
		$Conditions=$Volet->getConfiguration('condition');
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
		$Volet->setConfiguration('condition',$Conditions);	
		$Actions=$Volet->getConfiguration('action');
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
					$Actions[$ActionKey]["TypeGestion"]["Azimut"]=true;
					 unset($Actions[$ActionKey]["TypeGestion"][$TypeGestionKey]);
				}
			}
		}
		$Volet->setConfiguration('action',$Actions);	
		$Volet->save();
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
