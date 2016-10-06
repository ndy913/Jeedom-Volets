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
    public function execute($_options = null) {
    }
}
?>
