<?php
require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';

class Volets extends eqLogic {
  	public function preUpdate() {
    	}  
   	public function preInsert() {
	}    
    	public function postSave() {
	}	
	public static function AddCmd($Equipement,$Name,$_logicalId,$Type="info", $SubType='') 	{
		$Commande = $Equipement->getCmd(null,$_logicalId);
		if (!is_object($Commande)){
			$Commande = new VoletsCmd();
			$Commande->setId(null);
			$Commande->setName($Name);
			$Commande->setLogicalId($_logicalId);
			$Commande->setEqLogic_id($Equipement->getId());
			$Commande->setIsVisible(1);
			$Commande->setType($Type);
			$Commande->setSubType($SubType);
			$Commande->save();
		}
		return $Commande;
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
			$Azimuth=$heliotrope->getCmd(null,'');
			//Calculer de l'angle de ma zone
			$Coord=json_decode($this->getLogicalId(),true);
			$Angle=$this->getAngle($Coord['Center']['lat'],
					       $Coord['Center']['lng'],
					       $Coord['Position']['lat'],
					       $Coord['Position']['lng']);
			//si l'Azimuth est compris entre mon angle et 180° on est dans la fenetre
			if($Azimuth>$Angle&&$Azimuth>$Angle-180){
				$value=true;
			}
			else
				$value=false;
			$this->setCollectDate(date('Y-m-d H:i:s'));
			$this->save();
			$this->event($value);
		}
    }
}
?>
