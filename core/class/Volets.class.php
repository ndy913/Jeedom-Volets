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
		log::add('Volets', 'debug', 'Objet mis à jour => ' . $_option['event_id'] . ' / ' . $_option['value']);
		$Volet = Volets::byId($_option['Volets_id']);
		if (is_object($Volet) && $Volet->getIsEnable() == 1) {
			foreach($Volet->getCmd() as $Commande)
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
	public static function ActionJour($_option) {
		foreach(eqLogic::byType('Volets') as $Zone){
			$action=json_decode($Zone->getConfiguration('action'),true);
			foreach($action['in'] as $cmd)
				cmd::byId($cmd['cmd'])->execute($cmd['option']);
		}
	}
	public static function ActionNuit($_option) {
		foreach(eqLogic::byType('Volets') as $Zone){
			$action=$Zone->getConfiguration('action');
			log::add('Volets','debug',$action);
			$action=json_decode($Zone->getConfiguration('action'),true);
			log::add('Volets','debug',$action);
			foreach($action['out'] as $cmd)
				cmd::byId($cmd['cmd'])->execute($cmd['option']);
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
		/*double longDelta = longitudeDest - longitudeOrigne;
		double y = Math.sin(longDelta) * Math.cos(latitudeDest);
		double x = Math.cos(latitudeOrigine)*Math.sin(latitudeDest) -Math.sin(latitudeOrigine)*Math.cos(latitudeDest)*Math.cos(longDelta);
		double angle = Math.toDegrees(Math.atan2(y, x));
		while (angle < 0) {
			angle += 360;
		}
		return (float) angle % 360;*/
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
			log::add('Volets','debug','L\'angle du soleil est '.$Azimuth.'°');
			//Calculer de l'angle de ma zone
			$Droite=json_decode($this->getConfiguration('Droit'),true);
			$Gauche=json_decode($this->getConfiguration('Gauche'),true);
			
			$Angle=$this->getAngle($Droite['lat'],
					       $Droite['lng'],
					       $Gauche['lat'],
					       $Gauche['lng']);
			$this->setConfiguration('Angle',$Angle-90);
			log::add('Volets','debug','L\'angle de votre zone '.$this->getName().' par rapport au Nord est de '.$Angle.'°');
			//si l'Azimuth est compris entre mon angle et 180° on est dans la fenetre
			if($Azimuth>$Angle&&$Azimuth>$Angle-180)
				$action=json_decode($this->getConfiguration('action'),true)['in'];
			else
				$action=json_decode($this->getConfiguration('action'),true)['out'];
			$TempZone=cmd::byId($this->getConfiguration('TempObjet'))->execCmd();
			if($TempZone >= $this->getConfiguration('SeuilTemp')){
				foreach($action as $cmd)
					cmd::byId($cmd['cmd'])->execute($cmd['option']);
			}
		}
    }
}
?>
