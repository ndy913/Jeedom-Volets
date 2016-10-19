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
			foreach($Volet->getCmd(null, null, null, true)  as $Commande)
				$Commande->execute();	
			if($Volet->getConfiguration('EnableNight'))
				$Volet->UpdateActionDayNight();
			
		}
	}
	public function UpdateActionDayNight() {
		$heliotrope=eqlogic::byId($this->getConfiguration('heliotrope'));
		if(is_object($heliotrope)){
			$Jours=$heliotrope->getCmd(null,'sunrise')->execCmd();
			if(strlen($Jours)==3)
				$Heure=substr($Jours,0,1);
			else
				$Heure=substr($Jours,0,2);
			$Minute=substr($Jours,-2)-$this->getConfiguration('DelaisDay');
			while($Minute>=60){
				$Minute-=60;
				$Heure+=1;
			}
			$Schedule=$Minute . ' ' . $Heure . ' * * * *';
			$cron = cron::byClassAndFunction('Volets', 'ActionJour');
			if (!is_object($cron)) {
				$cron = new cron();
				$cron->setClass('Volets');
				$cron->setFunction('ActionJour');
				$cron->setEnable(1);
				$cron->setDeamon(0);
				$cron->setSchedule($Schedule);
				$cron->save();
			}
			else{
				$cron->setSchedule($Schedule);
				$cron->save();
			}
			$Nuit=$heliotrope->getCmd(null,'sunset')->execCmd();
			if(strlen($Nuit)==3)
				$Heure=substr($Nuit,0,1);
			else
				$Heure=substr($Nuit,0,2);
			$Minute=substr($Nuit,-2)+$this->getConfiguration('DelaisNight');
			while($Minute>=60){
				$Minute-=60;
				$Heure+=1;
			}
			$Schedule=$Minute . ' ' . $Heure . ' * * * *';
			$cron = cron::byClassAndFunction('Volets', 'ActionNuit');
			if (!is_object($cron)) {
				$cron = new cron();
				$cron->setClass('Volets');
				$cron->setFunction('ActionNuit');
				$cron->setEnable(1);
				$cron->setDeamon(0);
				$cron->setSchedule($Schedule);
				$cron->save();
			}
			else{
				$cron->setSchedule($Schedule);
				$cron->save();
			}
		}
	}
	public static function ActionJour() {
		foreach(eqLogic::byType('Volets') as $Zone){
			foreach($Zone->getCmd(null, null, null, true) as $Cmds){
				$action=$Cmds->getConfiguration('action');
				foreach($action['out'] as $cmd)
					cmd::byId(str_replace('#','',$cmd['cmd']))->execute($cmd['options']);
			}
		}
	}
	public static function ActionNuit() {
		foreach(eqLogic::byType('Volets') as $Zone){
			foreach($Zone->getCmd(null, null, null, true) as $Cmds){
				$action=$Cmds->getConfiguration('action');
				foreach($action['in'] as $cmd)
					cmd::byId(str_replace('#','',$cmd['cmd']))->execute($cmd['options']);
			}
		}
	}
  	public function preUpdate() {
    	}  
   	public function preInsert() {
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
			if($this->getConfiguration('EnableTemp'))
				$listener->addEvent($heliotrope->getCmd(null,'azimuth360')->getId());
			if($this->getConfiguration('EnableNight'))
				$listener->addEvent($heliotrope->getCmd(null,'sunrise')->getId());
			if($this->getConfiguration('EnableNight'))
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
		//Rechercher position du soleil => heliotrope
		$heliotrope=eqlogic::byId($this->getEqLogic()->getConfiguration('heliotrope'));
		if(is_object($heliotrope)){
			$Azimuth=$heliotrope->getCmd(null,'azimuth360')->execCmd();
			//Calculer de l'angle de ma zone
			$Droite=$this->getConfiguration('Droit');
			$Gauche=$this->getConfiguration('Gauche');
			
			$Angle=$this->getAngle($Droite['lat'],
					       $Droite['lng'],
					       $Gauche['lat'],
					       $Gauche['lng']);
			log::add('Volets','debug','L\'angle de votre zone '.$this->getName().' par rapport au Nord est de '.$Angle.'°');
			$TempZone=cmd::byId($this->getConfiguration('TempObjet'))->execCmd();
			//si l'Azimuth est compris entre mon angle et 180° on est dans la fenetre
			$action=$this->getConfiguration('action');
			/*if($Azimuth<$Angle&&$Azimuth>$Angle-90){
				log::add('Volets','debug','Le soleil est dans la fenetre');
				if($TempZone >= $this->getConfiguration('SeuilTemp')){
					log::add('Volets','debug','Les conditions sont remplie');
					$action=$action['in'];
				}else{
					log::add('Volets','debug','Les conditions ne sont pas remplie');
					$action=$action['out'];
				}
			}else{
				log::add('Volets','debug','Le soleil n\'est pas dans la fenetre');
				$action=$action['out'];
			}
			foreach($action as $cmd)
				cmd::byId(str_replace('#','',$cmd['cmd']))->execute($cmd['options']);*/
		}
	}
}
?>
