<?php
require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';
class Volets extends eqLogic {
  	private $_inverseCondition=false;
	//public static $_Gestions=['Jours','Nuit','Meteo','Presence','Azimute'];
	public static function deamon_info() {
		$return = array();
		$return['log'] = 'Volets';
		$return['launchable'] = 'ok';
		$return['state'] = 'nok';
		foreach(eqLogic::byType('Volets') as $Volet){
			if($Volet->getIsEnable()){
				$listener = listener::byClassAndFunction('Volets', 'pull', array('Volets_id' => $Volet->getId()));
				if (!is_object($listener))
					return $return;
				if ($Volet->getConfiguration('DayNight')){
					$cron = cron::byClassAndFunction('Volets', 'ActionJour', array('Volets_id' => $Volet->getId()));
					if (!is_object($cron)) 	
						return $return;
					$cron = cron::byClassAndFunction('Volets', 'ActionNuit', array('Volets_id' => $Volet->getId()));
					if (!is_object($cron)) 	
						return $return;
				}
			}
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
			$Volet->StartDemon();
	}
	public static function deamon_stop() {	
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
		}
	}
	public static function pull($_option) {
		$Volet = Volets::byId($_option['Volets_id']);
		if (is_object($Volet) && $Volet->getIsEnable()) {
			$Event = cmd::byId($_option['event_id']);
			if(is_object($Event)){
				switch($Event->getlogicalId()){
					case 'azimuth360':
						//log::add('Volets','info',$Volet->getHumanName().' : Mise à jour de la position du soleil');	
						$Volet->ActionAzimute($_option['value']);
					break;
					case $Volet->getConfiguration('TypeDay'):
						log::add('Volets','info',$Volet->getHumanName().' : Replanification de l\'ouverture au lever du soleil');	
						$timstamp=$Volet->CalculHeureEvent($_option['value'],'DelaisDay');
						$Schedule=date("i",$timstamp) . ' ' . date("H",$timstamp) . ' * * * *';
						$cron = $Volet->CreateCron($Schedule, 'ActionJour');
					break;
					case $Volet->getConfiguration('TypeNight'):
						log::add('Volets','info',$Volet->getHumanName().' : Replanification de la fermeture au coucher du soleil');	
						$timstamp=$Volet->CalculHeureEvent($_option['value'],'DelaisNight');
						$Schedule=date("i",$timstamp) . ' ' . date("H",$timstamp) . ' * * * *';
						$cron = $Volet->CreateCron($Schedule, 'ActionNuit');
					break;
					default:
						//log::add('Volets','info',$Volet->getHumanName().' : Mise à jour de la présence');	
						$Volet->ActionPresent($_option['value']);
					break;
				}
			}
		}
	}
	
	public function AutorisationAction($Evenement) {   
		if ($this->getIsEnable() && $this->getCmd(null,'isArmed')->execCmd()){
			$Mode = $this->getCmd(null,'gestion')->execCmd();
			switch($Evenement){
				case 'Day':
				case 'Night':
					if ($this->getConfiguration('DayNight'))
						return true;
				break;
				case 'Present':
					if ($this->getConfiguration('Present')
					    && $Mode != "Night" )
						return true;
				break;
				case 'Meteo':					
					if ($this->getConfiguration('Meteo')
					    && $Mode != "Night" 
					    && $Mode != "Present")
						return true;
				break;
				case 'Azimuth':
					if ($this->getConfiguration('Azimuth')
					    && $Mode != "Night" 
					    && $Mode != "Present" 
					    && $Mode != "Meteo")
						return true;
				break;
			}
		}
		return false;
	}
	public static function ActionJour($_option) {    
		$Volet = Volets::byId($_option['Volets_id']);
		if (is_object($Volet) && $Volet->AutorisationAction('Day')){	
			log::add('Volets', 'info', $Volet->getHumanName().'[Gestion Day] : Exécution de la gestion du lever du soleil');
			$Saison=$Volet->getSaison();
			$Evenement=$Volet->checkCondition('open',$Saison,'Day');
			if( $Evenement!= false){
				if($Volet->getPosition() != $Evenement || $Volet->getCmd(null,'gestion')->execCmd() != 'Day'){
					$Volet->checkAndUpdateCmd('gestion','Day');
					if ($Volet->getConfiguration('Present')){	
						log::add('Volets', 'info', $Volet->getHumanName().'[Gestion Day] : Vérification de la présence');
						$Commande=cmd::byId(str_replace('#','',$Volet->getConfiguration('cmdPresent')));
						if(is_object($Commande) && $Commande->execCmd() == false){
							log::add('Volets', 'info', $Volet->getHumanName().'[Gestion Day] : Il n\'y a personne nous exécutons la gestion de présence');
							if($Evenement=$Volet->ActionPresent() !== false)
							return $Evenement;
						}
					}
					if ($Volet->getConfiguration('Meteo')){
						$_option['Volets_id']=$Volet->getId();
						if($Evenement=Volets::ActionMeteo($_option) !== false)
							return $Evenement;
					}
					/*if ($Volet->getConfiguration('Azimuth')){
						$heliotrope=eqlogic::byId($Volet->getConfiguration('heliotrope'));
						if(is_object($heliotrope)){
							$Azimuth=$heliotrope->getCmd(null,'azimuth360')->execCmd();
							if($Evenement=$Volet->ActionAzimute($Azimuth) !== false)
								return $Evenement;
						}
					}*/
					log::add('Volets','info',$Volet->getHumanName().'[Gestion Day] : Execution des actions');
					foreach($Volet->getConfiguration('action') as $Cmd){	
						if (!$Volet->CheckValid($Cmd,$Evenement,$Saison,'Day'))
							continue;
						$Volet->ExecuteAction($Cmd, 'Day');
						$Volet->setPosition($Evenement);
					}
				}
			}else{
				log::add('Volets', 'info',$Volet->getHumanName().'[Gestion Day] : Replanification de l\'évaluation des conditions d\'ouverture au lever du soleil');
				$timstamp=$Volet->CalculHeureEvent(date('Hi'),'DelaisEval');
				$Schedule=date("i",$timstamp) . ' ' . date("H",$timstamp) . ' * * * *';
				$cron = $Volet->CreateCron($Schedule, 'ActionJour', array('Volets_id' => intval($Volet->getId())));
			}
		}
	}
	public static function ActionNuit($_option) {
		$Volet = Volets::byId($_option['Volets_id']);
		if (is_object($Volet) && $Volet->AutorisationAction('Night')){
			log::add('Volets', 'info',$Volet->getHumanName().'[Gestion Night] : Exécution de la gestion du coucher du soleil ');
			$Saison=$Volet->getSaison();
			$Evenement=$Volet->checkCondition('close',$Saison,'Night');
			if( $Evenement!= false){
				if($Volet->getPosition() != $Evenement || $Volet->getCmd(null,'gestion')->execCmd() != 'Night'){
					log::add('Volets','info',$Volet->getHumanName().'[Gestion Night] : Exécution des actions');
					foreach($Volet->getConfiguration('action') as $Cmd){	
						if (!$Volet->CheckValid($Cmd,$Evenement,$Saison,'Night'))
							continue;
						$Volet->ExecuteAction($Cmd, 'Night');
     						$Volet->setPosition($Evenement);
					}
				}
				$Volet->checkAndUpdateCmd('gestion','Night');
			}else{
				log::add('Volets', 'info', $Volet->getHumanName().'[Gestion Night] : Replanification de l\'évaluation des conditions de fermeture au coucher du soleil');
				$timstamp=$Volet->CalculHeureEvent(date('Hi'),'DelaisEval');
				$Schedule=date("i",$timstamp) . ' ' . date("H",$timstamp) . ' * * * *';
				$cron = $Volet->CreateCron($Schedule, 'ActionNuit', array('Volets_id' => intval($Volet->getId())));
			}
		}
	}
	public static function ActionMeteo($_option) {
		$Volet = Volets::byId($_option['Volets_id']);
		if (is_object($Volet) && $Volet->AutorisationAction('Meteo')){
			log::add('Volets', 'info',$Volet->getHumanName().'[Gestion Meteo] : Exécution de la gestion météo');
			$Saison=$Volet->getSaison();
			$Evenement=$Volet->checkCondition('close',$Saison,'Meteo');   		
			foreach($Volet->getConfiguration('condition') as $Condition){
				if($Condition['TypeGestion'] == 'Meteo')
					break;
			}
			if($Evenement== false){
				if($Volet->getCmd(null,'gestion')->execCmd()=='Meteo'){
					$Volet->checkAndUpdateCmd('gestion','Day');
					if ($Volet->getConfiguration('Present')){	
						log::add('Volets', 'info', $Volet->getHumanName().'[Gestion Day] : Vérification de la présence');
						$Commande=cmd::byId(str_replace('#','',$Volet->getConfiguration('cmdPresent')));
						if(is_object($Commande) && $Commande->execCmd() == false){
							log::add('Volets', 'info', $Volet->getHumanName().'[Gestion Day] : Il n\'y a personne nous exécutons la gestion de présence');
							if($Evenement=$Volet->ActionPresent() !== false)
							return $Evenement;
						}
					}
					/*if ($Volet->getConfiguration('Azimuth')){
						$heliotrope=eqlogic::byId($Volet->getConfiguration('heliotrope'));
						if(is_object($heliotrope)){
							$Azimuth=$heliotrope->getCmd(null,'azimuth360')->execCmd();
							if($Evenement=$Volet->ActionAzimute($Azimuth) !== false)
								return $Evenement;
						}
					}*/
				}
			} 
			if($Evenement!= false){
				if($Volet->getPosition() != $Evenement || $Volet->getCmd(null,'gestion')->execCmd() != 'Meteo'){
					log::add('Volets','info',$Volet->getHumanName().'[Gestion Meteo] : Exécution des actions');
					foreach($Volet->getConfiguration('action') as $Cmd){	
						if (!$Volet->CheckValid($Cmd,$Evenement,$Saison,'Meteo'))
							continue;
						$Volet->ExecuteAction($Cmd, 'Meteo');
						$Volet->setPosition($Evenement);
					}
				}
				if($Evenement == "close")
					$Volet->checkAndUpdateCmd('gestion','Meteo');
			}
			return $Evenement;
		}
	}
  	public function ActionPresent($Etat=false) {
		if ($this->AutorisationAction('Present')){
			$Saison=$this->getSaison();
			if($Etat)
				$Evenement='open';
			else
				$Evenement='close';
			$Evenement=$this->checkCondition($Evenement,$Saison,'Presence');
			if( $Evenement!= false){
				if($this->getPosition() != $Evenement || $this->getCmd(null,'gestion')->execCmd() != 'Present'){
					log::add('Volets','info',$this->getHumanName().'[Gestion Presence] : Exécution des actions');
					if($Evenement == 'close'){
						foreach($this->getConfiguration('action') as $Cmd){	
							if (!$this->CheckValid($Cmd,$Evenement,$Saison,'Presence'))
								continue;
							$this->ExecuteAction($Cmd,'Presence');
							$this->setPosition($Evenement);
						}
						$this->checkAndUpdateCmd('gestion','Present');
					}else{
						$this->checkAndUpdateCmd('gestion','Day');
						if ($this->getConfiguration('Meteo')){
							$_option['Volets_id']=$this->getId();
							if($Evenement=Volets::ActionMeteo($_option) !== false)
								return $Evenement;
						}
						/*if ($this->getConfiguration('Azimuth')){
							$heliotrope=eqlogic::byId($this->getConfiguration('heliotrope'));
							if(is_object($heliotrope)){
								$Azimuth=$heliotrope->getCmd(null,'azimuth360')->execCmd();
								if($Evenement=$this->ActionAzimute($Azimuth) !== false)
									return $Evenement;
							}
						}*/
					}
				}
			}
		}
	}
	public function ActionAzimute($Azimuth) {
		$Saison=$this->getSaison();
		$Evenement=$this->SelectAction($Azimuth,$Saison);
		if ($this->AutorisationAction('Azimuth') && $Evenement != false){
			$Evenement=$this->checkCondition($Evenement,$Saison,'Azimuth');
			if( $Evenement!= false){
				if($Evenement == 'open')
					$Hauteur=100;
				else		
					$Hauteur=$this->checkAltitude();
				$this->checkAndUpdateCmd('hauteur',$Hauteur);
				foreach($this->getConfiguration('action') as $Cmd){	
					if (!$this->CheckValid($Cmd,$Evenement,$Saison,'Azimuth'))
						continue;
					if($this->getPosition() != $Evenement 
					   || $this->getCmd(null,'gestion')->execCmd() != 'Azimuth' 
					   || ($this->getCmd(null,'hauteur')->execCmd() != $Hauteur && array_search('#Hauteur#', $cmd['options'])!== false)){
						$this->ExecuteAction($Cmd,'Azimuth',$Hauteur);
						$this->setPosition($Evenement);
					}else
						log::add('Volets','info',$this->getHumanName().'[Gestion Azimuth] : Position actuelle est '.$Evenement.' les volets sont déjà dans la bonne position, je ne fait rien');
				}
				$this->checkAndUpdateCmd('gestion','Azimuth');
			}
		}
		return $Evenement;
	}	
	public function CheckAngle($Azimuth) {
		$Droite=$this->getConfiguration('Droite');
		$Gauche=$this->getConfiguration('Gauche');
		$Centre=$this->getConfiguration('Centre');
		$AngleCntDrt=$this->getConfiguration('AngleDroite');
		$AngleCntGau=$this->getConfiguration('AngleGauche');
		if(!is_numeric($AngleCntDrt)&&!is_numeric($AngleCntGau)){
			if(is_array($Droite)&&is_array($Centre)&&is_array($Gauche)){
				$AngleCntDrt=$this->getAngle(
					$Centre['lat'],
					$Centre['lng'],
					$Droite['lat'],
					$Droite['lng']);
				$AngleCntGau=$this->getAngle(
					$Centre['lat'],
					$Centre['lng'],
					$Gauche['lat'],
					$Gauche['lng']);
				$this->setConfiguration('AngleDroite',$AngleCntDrt);
				$this->setConfiguration('AngleGauche',$AngleCntGau);
				$this->save();
			}else{
				log::add('Volets','debug',$this->getHumanName().'[Gestion Azimuth] : Les coordonnées GPS de l\'angle d\'exposition au soleil de votre fenêtre sont mal configurées');
				return false;	
			}
		}
		$result=false;
		if ($AngleCntDrt < $AngleCntGau){
			if($AngleCntDrt <= $Azimuth && $Azimuth <= $AngleCntGau)
				$result= true;
		}else{
			if($AngleCntDrt <= $Azimuth && $Azimuth <= 360)
				$result= true;
			if(0 <= $Azimut && $Azimuth <= $AngleCntGau)
				$result= true;
		}		
		log::add('Volets','info',$this->getHumanName().'[Gestion Azimuth] : L\'azimuth ' . $Azimuth . '° est compris entre : '.$AngleCntDrt.'°  et '.$AngleCntGau.'° => '.$this->boolToText($result));
		return $result;
	}	
	public function getSaison() {
		$isInWindows=$this->getCmd(null,'isInWindows');
		if(!is_object($isInWindows))
			return false;
		if($isInWindows->execCmd()){
			log::add('Volets','debug',$this->getHumanName().' : Le plugin est configuré en mode hiver');
			return 'hiver';
		}else{
			log::add('Volets','debug',$this->getHumanName().' : Le plugin est configuré en mode été');
			return 'été';
		}
		return false;
	}	
	public function SelectAction($Azimuth,$saison) {
		$Action=false;
		if($this->CheckAngle($Azimuth)){
			$this->checkAndUpdateCmd('state',true);
			log::add('Volets','info',$this->getHumanName().'[Gestion Azimuth] : Le soleil est dans la fenêtre');
			if($saison =='hiver')
				$Action='open';
			else
				$Action='close';
		}else{
			$this->checkAndUpdateCmd('state',false);
			log::add('Volets','info',$this->getHumanName().'[Gestion Azimuth] : Le soleil n\'est pas dans la fenêtre');
			if($saison == 'été')
				$Action='open';
			else
				$Action='close';
		}
		return $Action;
	}
	public function ExecuteAction($cmd,$TypeGestion,$Hauteur=0){
		try {
			$options = array();
			if (isset($cmd['options'])) 
				$options = $cmd['options'];
			scenarioExpression::createAndExec('action', $cmd['cmd'], $options);
		} catch (Exception $e) {
			log::add('Volets', 'error',$this->getHumanName().'[Gestion '.$TypeGestion.'] : '. __('Erreur lors de l\'exécution de ', __FILE__) . $action['cmd'] . __('. Détails : ', __FILE__) . $e->getMessage());
		}
		$Commande=cmd::byId(str_replace('#','',$cmd['cmd']));
		if(is_object($Commande)){
			log::add('Volets','debug',$this->getHumanName().'[Gestion '.$TypeGestion.'] : Exécution de '.$Commande->getHumanName());
			$key = array_search('#Hauteur#', $cmd['options']);
			array_replace($cmd['options'], array($key => $Hauteur));
			$Commande->event($cmd['options']);
		}
	}
	public function CalculHeureEvent($HeureStart, $delais) {
		if(strlen($HeureStart)==3)
			$Heure=substr($HeureStart,0,1);
		else
			$Heure=substr($HeureStart,0,2);
		$Minute=floatval(substr($HeureStart,-2));
		if($this->getConfiguration($delais)!='')
			$Minute+=floatval($this->getConfiguration($delais));
		while($Minute>=60){
			$Minute-=60;
			$Heure+=1;
		}
		return mktime($Heure,$Minute);
	}
	public function CreateCron($Schedule, $logicalId) {
		$cron =cron::byClassAndFunction('Volets', $logicalId, array('Volets_id' => $this->getId()));
			if (!is_object($cron)) {
				$cron = new cron();
				$cron->setClass('Volets');
				$cron->setFunction($logicalId);
				$cron->setOption(array('Volets_id' => $this->getId()));
				$cron->setEnable(1);
				$cron->setDeamon(0);
				$cron->setSchedule($Schedule);
				$cron->save();
			}else{
				$cron->setSchedule($Schedule);
				$cron->save();
			}
		return $cron;
	}
	public function CheckValid($Element,$Evenement,$Saison,$TypeGestion){
		if(array_search($Evenement, $Element['evaluation']) === false)
			return false;
		if(array_search($Saison, $Element['saison']) === false)
			return false;
		if(array_search($TypeGestion, $Element['TypeGestion']) === false)
			return false;		
		if (isset($Element['enable']) && $Element['enable'] == 0)
			return false;
		return true;
	}
	public function checkCondition($Evenement,$Saison,$TypeGestion){		
		foreach($this->getConfiguration('condition') as $Condition){
			if (!$this->CheckValid($Condition,$Evenement,$Saison,$TypeGestion))
				continue;
			if (!$this->EvaluateCondition($Condition,$TypeGestion)){
				if($Condition['Inverse']){
					log::add('Volets','info',$this->getHumanName().'[Gestion '.$TypeGestion.'] : La condition inverse l\'état du volet');
					if($Evenement == 'close')
						$Evenement='open';
					else
						$Evenement='close';
					if ($this->_inverseCondition){
						$this->_inverseCondition=false;
						return false;
					}
					$this->_inverseCondition=true;
					return $this->checkCondition($Evenement,$Saison,$TypeGestion);
				}
				log::add('Volets','info',$this->getHumanName().'[Gestion '.$TypeGestion.'] : Les conditions ne sont pas remplies');
				return false;
			}
		}
		log::add('Volets','info',$this->getHumanName().'[Gestion '.$TypeGestion.'] : Les conditions sont remplies pour '.$Evenement);
		return $Evenement;
	}
	public function boolToText($value){
		if (is_bool($value)) {
			if ($value) 
				return __('Vrai', __FILE__);
			else 
				return __('Faux', __FILE__);
		} else 
			return $value;
	}
	public function EvaluateCondition($Condition,$TypeGestion){
		$_scenario = null;
		$expression = scenarioExpression::setTags($Condition['expression'], $_scenario, true);
		$message = __('Evaluation de la condition : [', __FILE__) . trim($expression) . '] = ';
		$result = evaluate($expression);
		$message .=$this->boolToText($result);
		log::add('Volets','info',$this->getHumanName().'[Gestion '.$TypeGestion.'] : '.$message);
		if(!$result)
			return false;		
		return true;
	}
	public function getAngle($latitudeOrigine,$longitudeOrigne, $latitudeDest,$longitudeDest) { 
		$rlongitudeOrigne = deg2rad($longitudeOrigne); 
		$rlatitudeOrigine = deg2rad($latitudeOrigine); 
		$rlongitudeDest = deg2rad($longitudeDest); 
		$rlatitudeDest = deg2rad($latitudeDest); 
		$longDelta = $rlongitudeDest - $rlongitudeOrigne; 
		$y = sin($longDelta) * cos($rlatitudeDest); 
		$x = (cos($rlatitudeOrigine)*sin($rlatitudeDest)) - (sin($rlatitudeOrigine)*cos($rlatitudeDest)*cos($longDelta)); 
		$angle = rad2deg(atan2($y, $x)); 
		if ($angle < 0) { 

			$angle += 360; 
		}
		return floatval($angle % 360);
	}
	public function checkAltitude() { 
		$heliotrope=eqlogic::byId($this->getConfiguration('heliotrope'));
		if(is_object($heliotrope)){
			$Altitude =$heliotrope->getCmd(null,'altitude');
			if(!is_object($Altitude))
				return false;
			if (!$heliotrope->getConfiguration('zenith', '')) {
			    $zenith = '90.58';
			} else {
			    $zenith = $heliotrope->getConfiguration('zenith', '');
			}
			$Hauteur=round($Altitude->execCmd()*100/$zenith);
			log::add('Volets','info',$this->getHumanName().'[Gestion Altitude] : L\'altitude actuel est a '.$Hauteur.'% par rapport au zenith');
			return $Hauteur;
		}
		return false;
	}
	public function StartDemon() {
		if($this->getIsEnable()){
			$heliotrope=eqlogic::byId($this->getConfiguration('heliotrope'));
			if(is_object($heliotrope)){
				$listener = listener::byClassAndFunction('Volets', 'pull', array('Volets_id' => $this->getId()));
				if (!is_object($listener))
				    $listener = new listener();
				$listener->setClass('Volets');
				$listener->setFunction('pull');
				$listener->setOption(array('Volets_id' => $this->getId()));
				$listener->emptyEvent();
				if ($this->getConfiguration('Azimuth'))
					$listener->addEvent($heliotrope->getCmd(null,'azimuth360')->getId());
				$listener->save();
				if ($this->getConfiguration('Present'))
					$listener->addEvent($this->getConfiguration('cmdPresent'));
				$listener->save();
				if ($this->getConfiguration('DayNight')){
					$sunrise=$heliotrope->getCmd(null,$this->getConfiguration('TypeDay'));
					if(!is_object($sunrise))
						return false;
					$sunset=$heliotrope->getCmd(null,$this->getConfiguration('TypeNight'));
					if(!is_object($sunset))
						return false;
					$listener->addEvent($sunrise->getId());
					$listener->addEvent($sunset->getId());
					$DelaisDay=$this->CalculHeureEvent($sunrise->execCmd(),'DelaisDay');
					if(mktime() > $DelaisDay)
						$this->checkAndUpdateCmd('gestion','Day');
					$Schedule=date("i",$DelaisDay) . ' ' . date("H",$DelaisDay) . ' * * * *';
					$cron = $this->CreateCron($Schedule, 'ActionJour', array('Volets_id' => intval($this->getId())));
					$DelaisNight=$this->CalculHeureEvent($sunset->execCmd(),'DelaisNight');
					if(mktime() > $DelaisNight)
						$this->checkAndUpdateCmd('gestion','Night');
					$Schedule=date("i",$DelaisNight) . ' ' . date("H",$DelaisNight) . ' * * * *';
					$cron = $this->CreateCron($Schedule, 'ActionNuit', array('Volets_id' => intval($this->getId())));
				}
				if ($this->getConfiguration('Meteo'))
					$cron = $this->CreateCron('* * * * * *', 'ActionMeteo', array('Volets_id' => intval($this->getId())));
				$listener->save();	
				if ($this->getConfiguration('Present')){	
					log::add('Volets', 'info', $this->getHumanName().'[Gestion Day] : Vérification de la présence');
					$Commande=cmd::byId(str_replace('#','',$this->getConfiguration('cmdPresent')));
					if(is_object($Commande) && $Commande->execCmd() == false){
						log::add('Volets', 'info', $this->getHumanName().'[Gestion Day] : Il n\'y a personne nous exécutons la gestion de présence');
						if($Evenement=$this->ActionPresent() !== false)
						return $Evenement;
					}
				}
				if ($this->getConfiguration('Meteo')){
					$_option['Volets_id']=$this->getId();
					if($Evenement=Volets::ActionMeteo($_option) !== false)
						return $Evenement;
				}
				if ($this->getConfiguration('Azimuth')){
					$heliotrope=eqlogic::byId($this->getConfiguration('heliotrope'));
					if(is_object($heliotrope)){
						$Azimuth=$heliotrope->getCmd(null,'azimuth360')->execCmd();
						if($Evenement=$this->ActionAzimute($Azimuth) !== false)
							return $Evenement;
					}
				}
			}
		}
	}
	public function AddCommande($Name,$_logicalId,$Type="info", $SubType='binary',$visible,$Template='') {
		$Commande = $this->getCmd(null,$_logicalId);
		if (!is_object($Commande))
		{
			$Commande = new VoletsCmd();
			$Commande->setId(null);
			$Commande->setName($Name);
			$Commande->setIsVisible($visible);
			$Commande->setLogicalId($_logicalId);
			$Commande->setEqLogic_id($this->getId());
		}
		$Commande->setType($Type);
		$Commande->setSubType($SubType);
   		$Commande->setTemplate('dashboard',$Template );
		$Commande->setTemplate('mobile', $Template);
		$Commande->save();
		return $Commande;
	}
	public function setPosition($Evenement) {
		$this->checkAndUpdateCmd('position',$Evenement);
	}
	public function getPosition() {
		return $this->getCmd(null,'position')->execCmd();
	}
	public function preSave() {
		/*if($this->getConfiguration('heliotrope') == "")
			throw new Exception(__('Impossible d\'enregister, la configuration de l\'equipement heliotrope n\'existe pas', __FILE__));
		$heliotrope=eqlogic::byId($this->getConfiguration('heliotrope'));
		if(is_object($heliotrope)){	
			if($heliotrope->getConfiguration('geoloc') == "")
				throw new Exception(__('Impossible d\'enregister, la configuration  heliotrope n\'est pas correcte', __FILE__));
			$geoloc = geotravCmd::byEqLogicIdAndLogicalId($heliotrope->getConfiguration('geoloc'),'location:coordinate');
			if(is_object($geoloc) && $geoloc->execCmd()='')	
				throw new Exception(__('Impossible d\'enregister, la configuration de  "Localisation et trajet" (geotrav) n\'est pas correcte', __FILE__));
		}*/
	}
	public function postSave() {
		$this->AddCommande("Hauteur du volet","hauteur","info", 'numeric',true);
		$this->AddCommande("Gestion Active","gestion","info", 'string',true);
		$state=$this->AddCommande("Position du soleil","state","info", 'binary',true,'sunInWindows');
		$this->checkAndUpdateCmd('state',false);
		$isInWindows=$this->AddCommande("Etat mode","isInWindows","info","binary",false,'isInWindows');
		$inWindows=$this->AddCommande("Mode","inWindows","action","select",true,'inWindows');
		$inWindows->setConfiguration('listValue','1|Hivers;0|Eté');
		$inWindows->setValue($isInWindows->getId());
		$inWindows->save();
		$isArmed=$this->AddCommande("Etat activation","isArmed","info","binary",false,'lock');
		$this->checkAndUpdateCmd('isArmed',true);
		$Armed=$this->AddCommande("Activer","armed","action","other",true,'lock');
		$Armed->setValue($isArmed->getId());
		$Armed->setConfiguration('state', '1');
		$Armed->setConfiguration('armed', '1');
		$Armed->save();
		$Released=$this->AddCommande("Désactiver","released","action","other",true,'lock');
		$Released->setValue($isArmed->getId());
		$Released->save();
		$Released->setConfiguration('state', '0');
		$Released->setConfiguration('armed', '1');
		$Position=$this->AddCommande("Etat du volet","position","info","string",false);
		$VoletState=$this->AddCommande("Position du volet","VoletState","action","select",true,'volet');
		$VoletState->setConfiguration('listValue','open|Ouvert;close|Fermé');
		$VoletState->setDisplay('title_disable', 1);
		$VoletState->setValue($Position->getId());
		$VoletState->save();
		self::deamon_stop();
	}	
	public function postRemove() {
		$listener = listener::byClassAndFunction('Volets', 'pull', array('Volets_id' => $this->getId()));
		if (is_object($listener))
			$listener->remove();
		$cron = cron::byClassAndFunction('Volets', 'ActionJour', array('Volets_id' => $this->getId()));
		if (is_object($cron)) 	
			$cron->remove();
		$cron = cron::byClassAndFunction('Volets', 'ActionNuit', array('Volets_id' => $this->getId()));
		if (is_object($cron)) 	
			$cron->remove();
		$cron = cron::byClassAndFunction('Volets', 'ActionMeteo', array('Volets_id' => $this->getId()));
		if (is_object($cron)) 	
			$cron->remove();
	}
}
class VoletsCmd extends cmd {
    	public function execute($_options = null) {
		$Listener=cmd::byId(str_replace('#','',$this->getValue()));
		if (is_object($Listener)) {	
			switch($this->getLogicalId()){
				case 'armed':
					$Listener->event(true);
					$this->getEqLogic()->StartDemon();
					$timstamp=$this->CalculHeureEvent($sunrise->execCmd(),'DelaisDay');
					$timstamp=$this->CalculHeureEvent($sunset->execCmd(),'DelaisNight');
					if ($this->getEqLogic()->getConfiguration('Present')){	
						log::add('Volets', 'info', $this->getEqLogic()->getHumanName().'[Gestion Day] : Vérification de la présence');
						$Commande=cmd::byId(str_replace('#','',$this->getEqLogic()->getConfiguration('cmdPresent')));
						if(is_object($Commande) && $Commande->execCmd() == false){
							log::add('Volets', 'info', $Volet->getHumanName().'[Gestion Day] : Il n\'y a personne nous exécutons la gestion de présence');
							if($Evenement=$this->getEqLogic()->ActionPresent() !== false)
							return $Evenement;
						}
					}
					if ($this->getEqLogic()->getConfiguration('Meteo')){
						$_option['Volets_id']=$this->getEqLogic()->getId();
						if($Evenement=Volets::ActionMeteo($_option) !== false)
							return $Evenement;
					}
					if ($this->getEqLogic()->getConfiguration('Azimuth')){
						$heliotrope=eqlogic::byId($this->getEqLogic()->getConfiguration('heliotrope'));
						if(is_object($heliotrope)){
							$Azimuth=$heliotrope->getCmd(null,'azimuth360')->execCmd();
							if($Evenement=$this->getEqLogic()->ActionAzimute($Azimuth) !== false)
								return $Evenement;
						}
					}
				break;
				case 'released':
					$Listener->event(false);
				break;
				case 'VoletState':
				case 'inWindows':
					$Listener->event($_options['select']);
				break;
			}
			$Listener->setCollectDate(date('Y-m-d H:i:s'));
			$Listener->save();
		}
	}
}
?>
