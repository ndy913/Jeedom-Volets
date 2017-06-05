<?php
require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';
class Volets extends eqLogic {
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
		log::add('Volets', 'debug', 'Objet mis à jour => ' . json_encode($_option));
		$Volet = Volets::byId($_option['Volets_id']);
		if (is_object($Volet) && $Volet->getIsEnable()) {
			$Event = cmd::byId($_option['event_id']);
			if(is_object($Event)){
				switch($Event->getlogicalId()){
					case 'azimuth360':
						log::add('Volets', 'info',$Volet->getHumanName().' : Gestion des volets par l\'azimuth');
						$Volet->ActionAzimute($_option['value']);
					break;
					case 'sunrise':
						log::add('Volets', 'info',$Volet->getHumanName().' : Replanification de l\'ouverture au lever du soleil');	
						$timstamp=$Volet->CalculHeureEvent($_option['value'],'DelaisDay');
						$Schedule=date("i",$timstamp) . ' ' . date("H",$timstamp) . ' * * * *';
						$cron = $Volet->CreateCron($Schedule, 'ActionJour');
					break;
					case 'sunset':	
						log::add('Volets', 'info',$Volet->getHumanName().' : Replanification de la fermeture au coucher du soleil');	
						$timstamp=$Volet->CalculHeureEvent($_option['value'],'DelaisNight');
						$Schedule=date("i",$timstamp) . ' ' . date("H",$timstamp) . ' * * * *';
						$cron = $Volet->CreateCron($Schedule, 'ActionNuit');
					break;
				}
			}
		}
	}
	public static function ActionJour($_option) {    
		log::add('Volets', 'debug', 'Objet mis à jour => ' . json_encode($_option));
		$Volet = Volets::byId($_option['Volets_id']);
		if (is_object($Volet) && $Volet->getIsEnable()) {
			log::add('Volets', 'info', $Volet->getHumanName().' : Exécution de la gestion du lever du soleil');
			$Saison=$Volet->getSaison();
			$result=$Volet->EvaluateCondition('open',$Saison,'Day');
			if($result){
				$Action=$Volet->getConfiguration('action');
				$Volet->ExecuteAction($Action['open']);
				cache::set('Volets::Position::'.$Volet->getId(), 'open', 0);
			}else{
				log::add('Volets', 'info',$Volet->getHumanName().' : Replanification de l\'évaluation des conditions d\'ouverture au lever du soleil');
				$timstamp=$Volet->CalculHeureEvent(date('Hi'),'DelaisEval');
				$Schedule=date("i",$timstamp) . ' ' . date("H",$timstamp) . ' * * * *';
				$cron = $Volet->CreateCron($Schedule, 'ActionJour', array('Volets_id' => intval($Volet->getId())));
			}
		}
	}
	public static function ActionNuit($_option) {
		log::add('Volets', 'debug', 'Objet mis à jour => ' . json_encode($_option));
		$Volet = Volets::byId($_option['Volets_id']);
		if (is_object($Volet) && $Volet->getIsEnable()) {
			log::add('Volets', 'info',$Volet->getHumanName().' : Exécution de la gestion du coucher du soleil ');
			$Saison=$Volet->getSaison();
			$result=$Volet->EvaluateCondition('close',$Saison,'Night');
			if($result){
				$Action=$Volet->getConfiguration('action');
				$Volet->ExecuteAction($Action['close']);
				cache::set('Volets::Position::'.$Volet->getId(), 'close', 0);
			}else{
				log::add('Volets', 'info', $Volet->getHumanName().' : Replanification de l\'évaluation des conditions de fermeture au coucher du soleil');
				$timstamp=$Volet->CalculHeureEvent(date('Hi'),'DelaisEval');
				$Schedule=date("i",$timstamp) . ' ' . date("H",$timstamp) . ' * * * *';
				$cron = $Volet->CreateCron($Schedule, 'ActionNuit', array('Volets_id' => intval($Volet->getId())));
			}
		}
	}
  	public function ActionAzimute($Azimuth) {
		if($this->getCmd(null,'isArmed')->execCmd()){
			if($this->checkJour()){
				$Saison=$this->getSaison();
				$Evenement=$this->SelectAction($Azimuth,$Saison);
				if($Evenement != false){
					log::add('Volets','info',$this->getHumanName().' :  Les conditions sont remplies');
					$conditon=$this->EvaluateCondition($Evenement,$Saison,'Helioptrope');
					if(!$conditon && $Evenement =='open')
                     				$Evenement =='close';
					if(!$conditon && $Evenement =='close')
                      				$Evenement =='open';
					$Action=$this->getConfiguration('action');
                      			$position = cache::byKey('Volets::Position::'.$this->getId());
					if($position->getValue('') != $Evenement){
						log::add('Volets','info',$this->getHumanName().' : Position actuelle est '.$Evenement);
						$this->ExecuteAction($Action[$Evenement]);
			      			cache::set('Volets::Position::'.$this->getId(), $Evenement, 0);
					}
				}
				return;
			}
			log::add('Volets','debug',$this->getHumanName().' : Il fait nuit, la gestion par azimuth est désactivée');
		}
		else
			log::add('Volets','debug',$this->getHumanName().' : Gestion par azimuth désactivée');
	}
   	public function checkJour() {
		$heliotrope=eqlogic::byId($this->getConfiguration('heliotrope'));
		if(is_object($heliotrope)){	
			$sunrise=$heliotrope->getCmd(null,'sunrise');
			if(is_object($sunrise)){
				$value=$sunrise->execCmd();
				$Jours= new DateTime('@' .$this->CalculHeureEvent($value,'DelaisDay'));
			}
			else{	
				log::add('Volets','debug',$this->getHumanName().' : L\'objet "sunrise" n\'a pas été trouvé');
				return false;
			}
			$sunset=$heliotrope->getCmd(null,'sunset');
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
			log::add('Volets','info',$this->getHumanName().' : La fenêtre d\'ensoleillement est comprise entre : '.$AngleCntDrt.'° et '.$AngleCntGau.'°');
			if ($AngleCntDrt < $AngleCntGau){
				if($AngleCntDrt <= $Azimuth && $Azimuth <= $AngleCntGau)
					return true;
			}else{
				if($AngleCntDrt <= $Azimuth && $Azimuth <= 360)
					return true;
				if(0 <= $Azimut && $Azimuth <= $AngleCntGau)
					return true;
			}
		}else
			log::add('Volets','debug',$this->getHumanName().' : Les coordonnées GPS de l\'angle d\'exposition au soleil de votre fenêtre sont mal configurées');
		return false;			
	}	
	public function getSaison() {
		$isInWindows=$this->getCmd(null,'isInWindows');
		if(!is_object($isInWindows))
			return false;
		if($isInWindows->execCmd()){
			log::add('Volets','info',$this->getHumanName().' : Le plugin est configuré en mode hiver');
			return 'hiver';
		}else{
			log::add('Volets','info',$this->getHumanName().' : Le plugin est configuré en mode été');
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
			log::add('Volets','info',$this->getHumanName().' : Le soleil est dans la fenêtre');
			if($saison =='hiver')
				$Action='open';
			else
				$Action='close';
		}else{
			$StateCmd->event(false);
			log::add('Volets','info',$this->getHumanName().' : Le soleil n\'est pas dans la fenêtre');
			if($saison == 'été')
				$Action='open';
			else
				$Action='close';
		}
		$StateCmd->setCollectDate(date('Y-m-d H:i:s'));
		$StateCmd->save();
		return $Action;
	}
	public function ExecuteAction($Action) {	
		foreach($Action as $cmd){
			if (isset($cmd['enable']) && $cmd['enable'] == 0)
				continue;
			try {
				$options = array();
				if (isset($cmd['options'])) 
					$options = $cmd['options'];
				scenarioExpression::createAndExec('action', $cmd['cmd'], $options);
			} catch (Exception $e) {
				log::add('Volets', 'error',$this->getHumanName().' : '. __('Erreur lors de l\'exécution de ', __FILE__) . $action['cmd'] . __('. Détails : ', __FILE__) . $e->getMessage());
			}
			$Commande=cmd::byId(str_replace('#','',$cmd['cmd']));
			if(is_object($Commande)){
				if($this->getConfiguration('isRandom'))
				   sleep(rand(0,$this->getConfiguration('DelaisPresence')));
				log::add('Volets','debug',$this->getHumanName().' : Exécution de '.$Commande->getHumanName());
				$Commande->event($cmd['options']);
			}
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
	public function EvaluateCondition($Evenement,$Saison,$TypeGestion){
		foreach($this->getConfiguration('condition') as $condition){
			if($condition['evaluation']!=$Evenement && $condition['evaluation']!='all')
				continue;
			if($condition['saison']!=$Saison && $condition['saison']!='all')
				continue;
			if(stripos($condition['TypeGestion'],$TypeGestion) === false && $condition['TypeGestion']!='all')	
				continue;		
			if (isset($condition['enable']) && $condition['enable'] == 0)
				continue;
			$_scenario = null;
			$expression = scenarioExpression::setTags($condition['expression'], $_scenario, true);
			$message = __('Evaluation de la condition : [', __FILE__) . trim($expression) . '] = ';
			$result = evaluate($expression);
			if (is_bool($result)) {
				if ($result) {
					$message .= __('Vrai', __FILE__);
				} else {
					$message .= __('Faux', __FILE__);
				}
			} else {
				$message .= $result;
			}
			log::add('Volets','info',$this->getHumanName().' : '.$message);
			if(!$result){
				log::add('Volets','info',$this->getHumanName().' : Les conditions ne sont pas remplies');
				return false;
			}
		}
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
	public function StartDemon() {
		if($this->getIsEnable()){
			$heliotrope=eqlogic::byId($this->getConfiguration('heliotrope'));
			if(is_object($heliotrope)){
				$sunrise=$heliotrope->getCmd(null,'sunrise');
				if(!is_object($sunrise))
					return false;
				$sunset=$heliotrope->getCmd(null,'sunset');
				if(!is_object($sunset))
					return false;
				$listener = listener::byClassAndFunction('Volets', 'pull', array('Volets_id' => $this->getId()));
				if (!is_object($listener))
				    $listener = new listener();
				$listener->setClass('Volets');
				$listener->setFunction('pull');
				$listener->setOption(array('Volets_id' => $this->getId()));
				$listener->emptyEvent();
				if ($this->getConfiguration('Helioptrope'))
					$listener->addEvent($heliotrope->getCmd(null,'azimuth360')->getId());
				if ($this->getConfiguration('DayNight')){
					$listener->addEvent($sunrise->getId());
					$listener->addEvent($sunset->getId());
					$value=$sunrise->execCmd();
					$timstamp=$this->CalculHeureEvent($value,'DelaisDay');
					$Schedule=date("i",$timstamp) . ' ' . date("H",$timstamp) . ' * * * *';
					$cron = $this->CreateCron($Schedule, 'ActionJour', array('Volets_id' => intval($this->getId())));
					$value=$sunset->execCmd();
					$timstamp=$this->CalculHeureEvent($value,'DelaisNight');
					$Schedule=date("i",$timstamp) . ' ' . date("H",$timstamp) . ' * * * *';
					$cron = $this->CreateCron($Schedule, 'ActionNuit', array('Volets_id' => intval($this->getId())));
				}
				$listener->save();	
			}
		}
	}
	public static function AddCommande($eqLogic,$Name,$_logicalId,$Type="info", $SubType='binary',$visible,$Template='') {
		$Commande = $eqLogic->getCmd(null,$_logicalId);
		if (!is_object($Commande))
		{
			$Commande = new VoletsCmd();
			$Commande->setId(null);
			$Commande->setName($Name);
			$Commande->setIsVisible($visible);
			$Commande->setLogicalId($_logicalId);
			$Commande->setEqLogic_id($eqLogic->getId());
			$Commande->setType($Type);
			$Commande->setSubType($SubType);
		}
   		$Commande->setTemplate('dashboard',$Template );
		$Commande->setTemplate('mobile', $Template);
		$Commande->save();
		return $Commande;
	}
	public function postSave() {
		$state=self::AddCommande($this,"Position du soleil","state","info", 'binary',true,'sunInWindows');
		$state->event(false);
		$state->setCollectDate(date('Y-m-d H:i:s'));
		$state->save();
		$isInWindows=self::AddCommande($this,"Etat mode","isInWindows","info","binary",false,'isInWindows');
		$inWindows=self::AddCommande($this,"Mode","inWindows","action","other",true,'inWindows');
		$inWindows->setValue($isInWindows->getId());
		$inWindows->save();
		$isArmed=self::AddCommande($this,"Etat activation","isArmed","info","binary",false,'lock');
		$isArmed->event(true);
		$isArmed->setCollectDate(date('Y-m-d H:i:s'));
		$isArmed->save();
		$Armed=self::AddCommande($this,"Activer","armed","action","other",true,'lock');
		$Armed->setValue($isArmed->getId());
		$Armed->setConfiguration('state', '1');
		$Armed->setConfiguration('armed', '1');
		$Armed->save();
		$Released=self::AddCommande($this,"Désactiver","released","action","other",true,'lock');
		$Released->setValue($isArmed->getId());
		$Released->save();
		$Released->setConfiguration('state', '0');
		$Released->setConfiguration('armed', '1');
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
	}
}
class VoletsCmd extends cmd {
    	public function execute($_options = null) {
		$Listener=cmd::byId(str_replace('#','',$this->getValue()));
		if (is_object($Listener)) {	
			switch($this->getLogicalId()){
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
