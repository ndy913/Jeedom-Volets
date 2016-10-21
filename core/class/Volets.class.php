<?php
require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';

class Volets extends eqLogic {
	public static function deamon_info() {
		$return = array();
		$return['log'] = 'Volets';
		$return['launchable'] = 'ok';
		$return['state'] = 'nok';
		foreach(eqLogic::byType('Volets') as $Volet){
			if($Volet->getConfiguration('EnableNight')){
				$cron = cron::byClassAndFunction('Volets', 'ActionJour');
				if (!is_object($cron)) 	
					return $return;
				$cron = cron::byClassAndFunction('Volets', 'ActionNuit');
				if (!is_object($cron)) 	
					return $return;
			}
			$listener = listener::byClassAndFunction('Volets', 'pull', array('Volets_id' => intval($Volet->getId())));
			if (!is_object($listener))
				return $return;
		}
		$return['state'] = 'ok';
		return $return;
	}
	public static function deamon_start($_debug = false) {
		log::remove('Volets');
		self::deamon_stop();
		$deamon_info = self::deamon_info();
		if ($deamon_info['launchable'] != 'ok') 
			return;
		if ($deamon_info['state'] == 'ok') 
			return;
		foreach(eqLogic::byType('Volets') as $Volet)
			$Volet->save();
	}
	public static function deamon_stop() {
		foreach(eqLogic::byType('Volets') as $Volet){
			if($Volet->getConfiguration('EnableNight')){
				$cron = cron::byClassAndFunction('Volets', 'ActionJour');
				if (is_object($cron)) 	
					$cron->remove();
				$cron = cron::byClassAndFunction('Volets', 'ActionNuit');
				if (is_object($cron)) 	
					$cron->remove();
			}
			$listener = listener::byClassAndFunction('Volets', 'pull', array('Volets_id' => intval($Volet->getId())));
			if (is_object($listener))
				$listener->remove();
		}
	}
	public static function pull($_option) {
		log::add('Volets', 'debug', 'Objet mis à jour => ' . json_encode($_option));
		$Volet = Volets::byId($_option['Volets_id']);
		if (is_object($Volet) && $Volet->getIsEnable() == 1) {
			$Event = cmd::byId($_option['event_id']);
			if(is_object($Event)){
				switch($Event->getlogicalId()){
					case 'azimuth360':
						if($Volet->getConfiguration('EnableTemp')){
							log::add('Volets', 'debug', 'Gestion des volets par l\'azimuth');
							$Volet->ActionAzimute($_option['value']);
						}
					break;
					case 'sunrise':
						if($Volet->getConfiguration('EnableNight')){
							log::add('Volets', 'debug', 'Replanification de l\'ouverture au levée du soleil');	
							$timstamp=$Volet->CalculHeureEvent($_option['value'],'DelaisDay');
							$Schedule=date("H",$timstamp) . ' ' . date("i",$timstamp) . ' * * * *';
							$cron = $Volet->CreateCron($Schedule, 'ActionJour');
							}
					break;
					case 'sunset':
						if($Volet->getConfiguration('EnableNight')){	
							log::add('Volets', 'debug', 'Replanification de la fermeture au couchée du soleil');	
							$timstamp=$Volet->CalculHeureEvent($_option['value'],'DelaisNight');
							$Schedule=date("H",$timstamp) . ' ' . date("i",$timstamp) . ' * * * *';
							$cron = $Volet->CreateCron($Schedule, 'ActionNuit');
							}
					break;
				}
			}
		}
	}
	public function checkJour() {
		$heliotrope=eqlogic::byId($this->getConfiguration('heliotrope'));
		if(is_object($heliotrope)){	
			$sunrise=$heliotrope->getCmd(null,'sunrise');
			if(is_object($sunrise)){
				$value=$sunrise->execCmd();
				$Jours=date("H i",$this->CalculHeureEvent($value,'DelaisDay'));
			}
			else
				return false;
			$sunset=$heliotrope->getCmd(null,'sunset');
			if(is_object($sunset)){
				$value=$sunset->execCmd();
				$Nuit=date("H i",$this->CalculHeureEvent($value,'DelaisNight'));
			}
			else
				return false;
			if(date("H i")>$Jour&&date("H i")<$Nuit)
				return true;
		}
		return false;
	}		
	public function ActionAzimute($Azimuth) {
		if($this->checkJour()){
			foreach($this->getCmd(null, null, null, true)  as $Commande){
				//Calculer de l'angle de ma zone
				$Droite=$Commande->getConfiguration('Droit');
				$Gauche=$Commande->getConfiguration('Gauche');

				$Angle=$Commande->getAngle($Droite['lat'],
							   $Droite['lng'],
							   $Gauche['lat'],
							   $Gauche['lng']);
				log::add('Volets','debug','L\'angle de votre zone '.$Commande->getName().' par rapport au Nord est de '.$Angle.'°');
				//si l'Azimuth est compris entre mon angle et 180° on est dans la fenetre
				foreach($Commande->getConfiguration('condition') as $condition){
					$ExpressionEvaluation=evaluate($condition['expression']);
					log::add('Volets','debug','Evaluation de l\'expression: '.$condition['expression'].' => ' .$ExpressionEvaluation);
					if(!$ExpressionEvaluation){
						log::add('Volets','debug','Les conditions ne sont pas remplie');
						return;
					}
				}
				$actions=$Commande->getConfiguration('action');
				log::add('Volets','debug','Les conditions sont remplie');
				if($Azimuth<$Angle&&$Azimuth>$Angle-90){
					log::add('Volets','debug','Le soleil est dans la fenetre');
					$options['action']=$action['in'];
				}else{
					log::add('Volets','debug','Le soleil n\'est pas dans la fenetre');
					$options['action']=$action['out'];
				}
				$Commande->execute($options);
			}
		}else
			log::add('Volets','debug','Il fait nuit, la gestion par azimuth est désactivé');
	}
	public function CalculHeureEvent($HeureStart, $delais) {
		if(strlen($HeureStart)==3)
			$Heure=substr($HeureStart,0,1);
		else
			$Heure=substr($HeureStart,0,2);
		$Minute=substr($HeureStart,-2)-$this->getConfiguration($delais);
		while($Minute>=60){
			$Minute-=60;
			$Heure+=1;
		}
		return mktime($Heure,$Minute);
	}
	public function CreateCron($Schedule, $logicalId) {
		$cron =cron::byClassAndFunction('Volets', $logicalId);
			if (!is_object($cron)) {
				$cron = new cron();
				$cron->setClass('Volets');
				$cron->setFunction($logicalId);
				$cron->setEnable(1);
				$cron->setDeamon(0);
				$cron->setSchedule($Schedule);
				$cron->save();
			}
			else{
				$cron->setSchedule($Schedule);
				$cron->save();
			}
		return $cron;
	}
	public static function ActionJour() {
		foreach(eqLogic::byType('Volets') as $Zone){
			foreach($Zone->getCmd(null, null, null, true) as $Cmds){
				$actions=$Cmds->getConfiguration('action');
				if(is_object($actions)){
					$_options['action']=$actions['out'];
					$Cmds->execute($_options);
				}
			}
		}
	}
	public static function ActionNuit() {
		foreach(eqLogic::byType('Volets') as $Zone){
			foreach($Zone->getCmd(null, null, null, true) as $Cmds){
				$actions=$Cmds->getConfiguration('action');
				if(is_object($actions)){
					$_options['action']=$actions['in'];
					$Cmds->execute($_options);
				}
			}
		}
	} 
    public function postSave() {
		$heliotrope=eqlogic::byId($this->getConfiguration('heliotrope'));
		if(is_object($heliotrope)){
			log::add('Volets', 'info', 'Activation des déclencheurs : ');
			$listener = listener::byClassAndFunction('Volets', 'pull', array('Volets_id' => intval($this->getId())));
			if (!is_object($listener))
			    $listener = new listener();
			$listener->setClass('Volets');
			$listener->setFunction('pull');
			$listener->setOption(array('Volets_id' => intval($this->getId())));
			$listener->emptyEvent();
			$listener->addEvent($heliotrope->getCmd(null,'azimuth360')->getId());
			$listener->addEvent($heliotrope->getCmd(null,'sunrise')->getId());
			$listener->addEvent($heliotrope->getCmd(null,'sunset')->getId());
			$listener->save();	
			if($this->getConfiguration('EnableNight')){	
				$sunrise=$heliotrope->getCmd(null,'sunrise');
				if(is_object($sunrise)){
					$value=$sunrise->execCmd();
					$timstamp=$this->CalculHeureEvent($value,'DelaisDay');
					$Schedule=date("H",$timstamp) . ' ' . date("i",$timstamp) . ' * * * *';
					$cron = $this->CreateCron($Schedule, 'ActionJour');
				}
				$sunset=$heliotrope->getCmd(null,'sunset');
				if(is_object($sunset)){
					$value=$sunset->execCmd();
					$timstamp=$this->CalculHeureEvent($value,'DelaisNight');
					$Schedule=date("H",$timstamp) . ' ' . date("i",$timstamp) . ' * * * *';
					$cron = $this->CreateCron($Schedule, 'ActionNuit');
				}
			}
		}
	}	
	public function preRemove() {
		$listener = listener::byClassAndFunction('Volets', 'pull', array('Volets_id' => intval($this->getId())));
		if (is_object($listener)) 
			$listener->remove();
	}
}

class VoletsCmd extends cmd {
	public function getAngle($latitudeOrigine,$longitudeOrigne, $latitudeDest,$longitudeDest) {
		$longDelta = $longitudeDest - $longitudeOrigne;
		$y = sin($longDelta) * cos($latitudeDest);
		$x = cos($latitudeOrigine)*sin($latitudeDest) - sin($latitudeOrigine)*cos($latitudeDest)*cos($longDelta);
		$angle = rad2deg(atan2($y, $x));
		while ($angle < 0) {
			$angle += 360;
		}
		return  $angle % 360;
	}
    public function execute($_options = null) {		
		foreach($_options['action'] as $cmd){
			$Commande=cmd::byId(str_replace('#','',$cmd['cmd']));
			if(is_object($Commande)){
				log::add('Volets','debug','Execution de '.$Commande->getHumanName(true,true));
				$Commande->execute($cmd['options']);
			}
		}
	}
}
?>
