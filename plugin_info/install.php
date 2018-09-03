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
		$cron = cron::byClassAndFunction('Volets', 'GestionJour', array('Volets_id' => $Volet->getId()));
		if (is_object($cron)) 	
			$cron->remove();
		$cron = cron::byClassAndFunction('Volets', 'GestionNuit', array('Volets_id' => $Volet->getId()));
		if (is_object($cron)) 	
			$cron->remove();
		$cron = cron::byClassAndFunction('Volets', 'GestionMeteo', array('Volets_id' => $Volet->getId()));
		if (is_object($cron)) 	
			$cron->remove();
		if($Volet->getConfiguration('Meteo') || $Volet->getConfiguration('Absent')){
			$Volet->setConfiguration('Evenement',$Volet->getConfiguration('Absent'));
			$Volet->setConfiguration('Conditionnel',$Volet->getConfiguration('Meteo'));
			$Volet->setConfiguration('Meteo',false);
			$Volet->setConfiguration('Absent',false);
			$Conditions=array();
			foreach($Volet->getConfiguration('condition') as $Condition){
				$Condition['TypeGestion']['Evenement']=$Condition['TypeGestion']['Absent'];
				$Condition['TypeGestion']['Conditionnel']=$Condition['TypeGestion']['Meteo'];
				$Conditions[]=$Condition;
			}
			$Volet->setConfiguration('condition',$Conditions);
			$Actions=array();
			foreach($Volet->getConfiguration('action') as $Action){
				$Action['TypeGestion']['Evenement']=$Action['TypeGestion']['Absent'];
				$Action['TypeGestion']['Conditionnel']=$Action['TypeGestion']['Meteo'];
				$Actions[]=$Action;
			}
			$Volet->setConfiguration('action',$Actions);
		}
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
