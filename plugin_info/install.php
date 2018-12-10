<?php
require_once dirname(__FILE__) . '/../../../core/php/core.inc.php';
function Volets_install(){
}
function Volets_update(){
	log::add('Volets','debug','Lancement du script de mise a jours'); 
	foreach(eqLogic::byType('Volets') as $Volet){
		$Position=$Volet->getCmd(null,'position');
		if($Position->getSubType() == 'string'){
			$Position->setSubType('numeric');
		}
		$Position->setIsVisible(true);
		$Position->setTemplate('dashboard','SlidVolet' );
		$Position->setTemplate('mobile', 'SlidVolet');
		$Position->save();
		$Volet->checkAndUpdateCmd('position',0);
		if($Volet->getConfiguration('RealState') != ''){
			$RealState=cmd::byId(str_replace('#','',$Volet->getConfiguration('RealState')));
			if(is_object($RealState))
				$Volet->checkAndUpdateCmd('position',$RealState->execCmd());
		}
		$VoletState=$Volet->getCmd(null,'VoletState');
		if(is_object($VoletState))
			$VoletState->remove();
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
