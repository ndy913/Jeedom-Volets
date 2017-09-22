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
						log::add('Volets','info',$Volet->getHumanName().' : Mise a jours de la position du soleil');	
						$Volet->ActionAzimute($_option['value']);
					break;
					case $Volet->getConfiguration('TypeDay'):
						log::add('Volets','info',$Volet->getHumanName().' : Replanification de l\'ouverture au lever du soleil');	
						$timstamp=$Volet->CalculHeureEvent($_option['value'],'DelaisDay');
						$Schedule=date("i",$timstamp) . ' ' . date("H",$timstamp) . ' * * * *';
						$cron = $Volet->CreateCron($Schedule, 'ActionJour');
					break;
					case $Volet->getConfiguration('TypeDay'):
						log::add('Volets','info',$Volet->getHumanName().' : Replanification de la fermeture au coucher du soleil');	
						$timstamp=$Volet->CalculHeureEvent($_option['value'],'DelaisNight');
						$Schedule=date("i",$timstamp) . ' ' . date("H",$timstamp) . ' * * * *';
						$cron = $Volet->CreateCron($Schedule, 'ActionNuit');
					break;
					default:
						log::add('Volets','info',$Volet->getHumanName().' : Mise a jours de la présence');	
						$Volet->ActionPresent($_option['value']);
					break;
				}
			}
		}
	}
	
	public function AutorisationAction($Evenement) {   
		if ($this->getIsEnable() && $this->getCmd(null,'isArmed')->execCmd()){
			$Mode = cache::byKey('Volets::Mode::'.$this->getId())->getValue('Azimuth');
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
					    && $Mode != "Absent")
						return true;
				break;
				case 'Azimuth':
					if ($this->getConfiguration('Azimuth')
					    && $Mode != "Night" 
					    && $Mode != "Absent" 
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
			log::add('Volets', 'info', $Volet->getHumanName().'[Gestion Jours] : Exécution de la gestion du lever du soleil');
			$Mode = cache::byKey('Volets::Mode::'.$Volet->getId())->getValue('Azimuth');
			$Saison=$Volet->getSaison();
			$Evenement=$Volet->checkCondition('open',$Saison,'Day');
			if( $Evenement!= false){
				if($Volet->getPosition() != $Evenement || $Mode != 'Day'){
					log::add('Volets','info',$Volet->getHumanName().'[Gestion Jours] : Execution des actions');
					foreach($Volet->getConfiguration('action') as $Cmd){	
						if (!$Volet->CheckValid($Cmd,$Evenement,$Saison,'Day'))
							continue;
						$Volet->ExecuteAction($Cmd, 'Jour');
					}
          				$Volet->setPosition($Evenement);
					cache::set('Volets::Mode::'.$Volet->getId(), 'Day', 0);
				}
			}else{
				log::add('Volets', 'info',$Volet->getHumanName().'[Gestion Jours] : Replanification de l\'évaluation des conditions d\'ouverture au lever du soleil');
				$timstamp=$Volet->CalculHeureEvent(date('Hi'),'DelaisEval');
				$Schedule=date("i",$timstamp) . ' ' . date("H",$timstamp) . ' * * * *';
				$cron = $Volet->CreateCron($Schedule, 'ActionJour', array('Volets_id' => intval($Volet->getId())));
			}
		}
	}
	public static function ActionNuit($_option) {
		$Volet = Volets::byId($_option['Volets_id']);
		if (is_object($Volet) && $Volet->AutorisationAction('Night')){
			$Mode = cache::byKey('Volets::Mode::'.$Volet->getId())->getValue('Azimuth');
			log::add('Volets', 'info',$Volet->getHumanName().'[Gestion Nuit] : Exécution de la gestion du coucher du soleil ');
			$Saison=$Volet->getSaison();
			$Evenement=$Volet->checkCondition('close',$Saison,'Night');
			if( $Evenement!= false){
				if($Volet->getPosition() != $Evenement || $Mode != 'Night'){
					log::add('Volets','info',$Volet->getHumanName().'[Gestion Nuit] : Execution des actions');
					foreach($Volet->getConfiguration('action') as $Cmd){	
						if (!$Volet->CheckValid($Cmd,$Evenement,$Saison,'Night'))
							continue;
						$Volet->ExecuteAction($Cmd, 'Nuit');
					}
     					$Volet->setPosition($Evenement);
					cache::set('Volets::Mode::'.$Volet->getId(), 'Night', 0);
				}
			}else{
				log::add('Volets', 'info', $Volet->getHumanName().'[Gestion Nuit] : Replanification de l\'évaluation des conditions de fermeture au coucher du soleil');
				$timstamp=$Volet->CalculHeureEvent(date('Hi'),'DelaisEval');
				$Schedule=date("i",$timstamp) . ' ' . date("H",$timstamp) . ' * * * *';
				$cron = $Volet->CreateCron($Schedule, 'ActionNuit', array('Volets_id' => intval($Volet->getId())));
			}
		}
	}
	public static function ActionMeteo($_option) {
		$Volet = Volets::byId($_option['Volets_id']);
		if (is_object($Volet) && $Volet->AutorisationAction('Meteo')){
			$Mode = cache::byKey('Volets::Mode::'.$Volet->getId())->getValue('Azimuth');
			log::add('Volets', 'info',$Volet->getHumanName().'[Gestion Meteo] : Exécution de la gestion météo');
			$Saison=$Volet->getSaison();
			$Evenement=$Volet->checkCondition('close',$Saison,'Meteo');   		
			foreach($Volet->getConfiguration('condition') as $Condition){
				if($Condition['TypeGestion'] == 'Meteo')
					break;
			}
			if($Evenement== false){
				if($Mode=='Meteo'){
					cache::set('Volets::Mode::'.$Volet->getId(), 'Day', 0);
					$Mode='Day';
					$Evenement=$Volet->checkCondition('open',$Saison,'Meteo'); 
				}
			}
			if($Evenement!= false){
				if($Volet->getPosition() != $Evenement || $Mode != 'Meteo'){
					log::add('Volets','info',$Volet->getHumanName().'[Gestion Meteo] : Exécution des actions');
					foreach($Volet->getConfiguration('action') as $Cmd){	
						if (!$Volet->CheckValid($Cmd,$Evenement,$Saison,'Meteo'))
							continue;
						$Volet->ExecuteAction($Cmd, 'Meteo');
					}
					$Volet->setPosition($Evenement);
				}
				cache::set('Volets::Mode::'.$Volet->getId(), 'Meteo', 0);
			}
		}
	}
  	public function ActionPresent($Etat) {
		$Mode = cache::byKey('Volets::Mode::'.$this->getId())->getValue('Azimuth');
		if ($this->AutorisationAction('Present')){
			if($this->checkJour()){
				$Saison=$this->getSaison();
				if($Etat)
					$Evenement='open';
				else
					$Evenement='close';
				$Evenement=$this->checkCondition($Evenement,$Saison,'Presence');
				if( $Evenement!= false){
					if($this->getPosition() != $Evenement || $Mode != 'Absent'){
						log::add('Volets','info',$this->getHumanName().'[Gestion Presence] : Exécution des actions');
						foreach($this->getConfiguration('action') as $Cmd){	
							if (!$this->CheckValid($Cmd,$Evenement,$Saison,'Presence'))
								continue;
							$this->ExecuteAction($Cmd,'Presence');
						}
          					$this->setPosition($Evenement);
					}
				}				
				if($Evenement == 'close')
					cache::set('Volets::Mode::'.$Volet->getId(), 'Absent', 0);
				else
					cache::set('Volets::Mode::'.$Volet->getId(), 'Day', 0);
			}
		}
	}
	public function ActionAzimute($Azimuth) {
		$Mode = cache::byKey('Volets::Mode::'.$this->getId())->getValue('Azimuth');
		if ($this->AutorisationAction('Azimuth')){
			if($this->checkJour()){
				$Saison=$this->getSaison();
				$Evenement=$this->SelectAction($Azimuth,$Saison);
				if($Evenement != false){
					$Evenement=$this->checkCondition($Evenement,$Saison,'Azimuth');
					if( $Evenement!= false){
						if($this->getPosition() != $Evenement || $Mode != 'Azimuth'){
							log::add('Volets','info',$this->getHumanName().'[Gestion Azimuth] : Exécution des actions');
							foreach($this->getConfiguration('action') as $Cmd){	
								if (!$this->CheckValid($Cmd,$Evenement,$Saison,'Azimuth'))
									continue;
								$this->ExecuteAction($Cmd,'Azimuth');
							}
              						$this->setPosition($Evenement);
						}else
							log::add('Volets','info',$this->getHumanName().'[Gestion Azimuth] : Position actuelle est '.$Evenement.' les volets sont déjà dans la bonne position, je ne fait rien');
					}
				}
				cache::set('Volets::Mode::'.$this->getId(), 'Azimuth', 0);
			}
		}
	}
   	public function checkJour() {
		$heliotrope=eqlogic::byId($this->getConfiguration('heliotrope'));
		if(is_object($heliotrope)){	
			$sunrise=$heliotrope->getCmd(null,$this->getConfiguration('TypeDay'));
			if(is_object($sunrise)){
				$value=$sunrise->execCmd();
				$Jours= new DateTime('@' .$this->CalculHeureEvent($value,'DelaisDay'));
			}
			else{	
				log::add('Volets','debug',$this->getHumanName().' : L\'objet "sunrise" n\'a pas été trouvé');
				return false;
			}
			$sunset=$heliotrope->getCmd(null,$this->getConfiguration('TypeNight'));
			if(is_object($sunset)){
				$value=$sunset->execCmd();
				$Nuit= new DateTime('@' .$this->CalculHeureEvent($value,'DelaisNight'));
			}else{	
				log::add('Volets','debug',$this->getHumanName().' : L\'objet "sunset" n\'a pas été trouvé');
				return false;
			}
			$Now=new DateTime();
			if($Now>$Jours && $Now<$Nuit)
				return true;
		}else
			log::add('Volets','debug',$this->getHumanName().' : Aucune commande Héliotrope configurée');
		return false;
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
		log::add('Volets','info',$this->getHumanName().'[Gestion Azimuth] : L\'azimute ' . $Azimuth . '° est compris entre : '.$AngleCntDrt.'°  et '.$AngleCntGau.'° => '.$this->boolToText($result));
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
		$StateCmd=$this->getCmd(null,'state');
		if(!is_object($StateCmd))
			return false;
		if($this->CheckAngle($Azimuth)){
			$StateCmd->event(true);
			log::add('Volets','info',$this->getHumanName().'[Gestion Azimuth] : Le soleil est dans la fenêtre');
			if($saison =='hiver')
				$Action='open';
			else
				$Action='close';
		}else{
			$StateCmd->event(false);
			log::add('Volets','info',$this->getHumanName().'[Gestion Azimuth] : Le soleil n\'est pas dans la fenêtre');
			if($saison == 'été')
				$Action='open';
			else
				$Action='close';
		}
		$StateCmd->setCollectDate(date('Y-m-d H:i:s'));
		$StateCmd->save();
		return $Action;
	}
	public function ExecuteAction($cmd,$TypeGestion){
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
		log::add('Volets','info',$this->getHumanName().'[Gestion '.$TypeGestion.'] : Les conditions sont remplies');
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
			$altitude=$heliotrope->getCmd(null,'altitude');
			if(!is_object($altitude))
				return false;
		}
	}
	public function StartDemon() {
		if($this->getIsEnable()){
			$heliotrope=eqlogic::byId($this->getConfiguration('heliotrope'));
			if(is_object($heliotrope)){
				$sunrise=$heliotrope->getCmd(null,$this->getConfiguration('TypeDay'));
				if(!is_object($sunrise))
					return false;
				$sunset=$heliotrope->getCmd(null,$this->getConfiguration('TypeNight'));
				if(!is_object($sunset))
					return false;
				$listener = listener::byClassAndFunction('Volets', 'pull', array('Volets_id' => $this->getId()));
				if (!is_object($listener))
				    $listener = new listener();
				$listener->setClass('Volets');
				$listener->setFunction('pull');
				$listener->setOption(array('Volets_id' => $this->getId()));
				$listener->emptyEvent();
				if ($this->getConfiguration('Azimuth'))
					$listener->addEvent($heliotrope->getCmd(null,'azimuth360')->getId());
				if ($this->getConfiguration('Present'))
					$listener->addEvent($this->getConfiguration('cmdPresent'));
				if ($this->getConfiguration('DayNight')){
					$listener->addEvent($sunrise->getId());
					$listener->addEvent($sunset->getId());
					$timstamp=$this->CalculHeureEvent($sunrise->execCmd(),'DelaisDay');
					$Schedule=date("i",$timstamp) . ' ' . date("H",$timstamp) . ' * * * *';
					$cron = $this->CreateCron($Schedule, 'ActionJour', array('Volets_id' => intval($this->getId())));
					$timstamp=$this->CalculHeureEvent($sunset->execCmd(),'DelaisNight');
					$Schedule=date("i",$timstamp) . ' ' . date("H",$timstamp) . ' * * * *';
					$cron = $this->CreateCron($Schedule, 'ActionNuit', array('Volets_id' => intval($this->getId())));
				}
				if ($this->getConfiguration('Meteo'))
					$cron = $this->CreateCron('* * * * * *', 'ActionMeteo', array('Volets_id' => intval($this->getId())));
				$listener->save();	
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
	public function postSave() {
		$state=$this->AddCommande("Position du soleil","state","info", 'binary',true,'sunInWindows');
		$state->event(false);
		$state->setCollectDate(date('Y-m-d H:i:s'));
		$state->save();
		$isInWindows=$this->AddCommande("Etat mode","isInWindows","info","binary",false,'isInWindows');
		$inWindows=$this->AddCommande("Mode","inWindows","action","other",true,'inWindows');
		$inWindows->setValue($isInWindows->getId());
		$inWindows->save();
		$isArmed=$this->AddCommande("Etat activation","isArmed","info","binary",false,'lock');
		$isArmed->event(true);
		$isArmed->setCollectDate(date('Y-m-d H:i:s'));
		$isArmed->save();
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
		$VoletState=$this->AddCommande("Position du volet","VoletState","action","message",true,'volet');
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
				case 'VoletState':
					$this->getEqLogic()->checkAndUpdateCmd('position',$_options['message']);
				break;
				case 'armed':
					$Listener->event(true);
				break;
				case 'released':
					$Listener->event(false);
				break;
				case 'inWindows':
						if($Listener->execCmd())
							$Listener->event(false);
						else
							$Listener->event(true);
				break;
			}
			$Listener->setCollectDate(date('Y-m-d H:i:s'));
			$Listener->save();
		}
	}
}
?>
