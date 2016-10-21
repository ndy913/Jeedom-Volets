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
						if($Volet->getConfiguration('EnableTemp'))
							$Volet->ActionAzimute();
					break
					case 'sunrise':
					case 'sunset':
						if($Volet->getConfiguration('EnableNight'))
							$Volet->UpdateActionDayNight();
					break
				}
			}
		}
	}
	public function ActionAzimute() {
		$heliotrope=eqlogic::byId($this->getConfiguration('heliotrope'));
		if(is_object($heliotrope)){	
			$Jours=split(' ',$this->CalculHeureEvent($heliotrope, 'sunrise','DelaisDay'));
			$Jour=date("H i",mktime($Jours[0],$Jours[1]));
			$Nuits=split(' ',$this->CalculHeureEvent($heliotrope, 'sunset','DelaisNight'));
			$Nuit=date("H i",mktime($Nuits[0],$Nuits[1]));
			if(date("H i")>$Jour&&date("H i")<$Nuit){
				foreach($this->getCmd(null, null, null, true)  as $Commande){
					$Azimuth=$heliotrope->getCmd(null,'azimuth360')->execCmd();
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
						log::add('Volets','debug','Evaluation de l\'expression: '.$condition['expression']);
						if(!evaluate($condition['expression'])){
							log::add('Volets','debug','Les conditions ne sont pas remplie');
							return;
						}
					}
					$actions=$Commande->getConfiguration('action');
					if(is_object($actions)){
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
				}
			}else{
				log::add('Volets','debug','Il fait nuit, la gestion par azimuth est désactivé');
			}
		}
	}
	public function CalculHeureEvent($heliotrope, $logicalId, $delais) {
		$Jours=$heliotrope->getCmd(null,'sunrise')->execCmd();
		if(strlen($Jours)==3)
			$Heure=substr($Jours,0,1);
		else
			$Heure=substr($Jours,0,2);
		$Minute=substr($Jours,-2)-$this->getConfiguration($delais);
		while($Minute>=60){
			$Minute-=60;
			$Heure+=1;
		}
		return $Minute . ' ' . $Heure;
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
	public function UpdateActionDayNight() {
		$heliotrope=eqlogic::byId($this->getConfiguration('heliotrope'));
		if(is_object($heliotrope)){
			$Schedule=$this->CalculHeureEvent($heliotrope, 'sunrise','DelaisDay') . ' * * * *';
			$cron = $this->CreateCron($Schedule, 'ActionJour');
			$Schedule=$this->CalculHeureEvent($heliotrope, 'sunset','DelaisNight') . ' * * * *';
			$cron = $this->CreateCron($Schedule, 'ActionNuit');
		}
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
			if($this->getConfiguration('EnableNight'))
				$this->UpdateActionDayNight();
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
				$Commande->execute($cmd['options']);
			}
		}
	}
}
?>
