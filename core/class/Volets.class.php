<?php
require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';

class Volets extends eqLogic {
	public static function pull($_option) {
		$Volet = Volets::byId($_option['Volets_id']);
		//log::add('Volets', 'debug', 'Objet mis à jour => ' . $_option['event_id'] . ' / ' . $_option['value']);
		if (is_object($Volet) && $Volet->getIsEnable() == 1) {
			foreach($Volet->getCmd() as $Commande)
				$Commande->execute();
		}
	}
	public static function ActionJour($_option) {
		foreach(eqLogic::byType() as $Zone){
			$action=json_decode($Zone->getConfiguration('action'),true)['in'];
			//$action=json_decode($Zone->getConfiguration('action'),true)['out'];
			foreach($action as $cmd)
				cmd::byId($cmd['cmd'])->execute($cmd['option']);
		}
	}
	public static function ActionNuit($_option) {
		foreach(eqLogic::byType() as $Zone){
			//$action=json_decode($Zone->getConfiguration('action'),true)['in'];
			$action=json_decode($Zone->getConfiguration('action'),true)['out'];
			foreach($action as $cmd)
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
			if($this->getConfiguration('EnableNight')){
				$Jours=$heliotrope->getCmd(null,'sunrise')->execCmd()-$this->getConfiguration('AddDelais');
				$Nuit=$heliotrope->getCmd(null,'sunset')->execCmd()+$this->getConfiguration('AddDelais');
				$cron = cron::byClassAndFunction('Volets', 'ActionJour');
				if (!is_object($cron)) {
					$cron = new cron();
					$cron->setClass('Volets');
					$cron->setFunction('ActionJour');
					$cron->setEnable(1);
					$cron->setDeamon(0);
					$cron->setSchedule($Jours . ' * * * *');
					$cron->save();
				}
				else{
					$cron->setSchedule($Jours . ' * * * *');
					$cron->save();
				}
				$cron = cron::byClassAndFunction('Volets', 'ActionNuit');
				if (!is_object($cron)) {
					$cron = new cron();
					$cron->setClass('Volets');
					$cron->setFunction('Update_cron');
					$cron->setEnable(1);
					$cron->setDeamon(0);
					$cron->setSchedule($Nuit . ' * * * *');
					$cron->save();
				}
				else{
					$cron->setSchedule($Nuit . ' * * * *');
					$cron->save();
				}
			}
			if($this->getConfiguration('EnableTemp')){
				log::add('Volets', 'info', 'Activation des déclencheurs : ');
				$listener = listener::byClassAndFunction('Volets', 'pull', array('Volets_id' => intval($this->getId())));
				if (!is_object($listener))
				    $listener = new listener();
				$listener->setClass('Volets');
				$listener->setFunction('pull');
				$listener->setOption(array('Volets_id' => intval($this->getId())));
				$listener->emptyEvent();
				$listener->addEvent($heliotrope->getCmd(null,'azimuth360'));
				$listener->save();	
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
