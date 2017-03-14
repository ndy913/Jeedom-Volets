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
			$Volet->save();
	}
	public static function deamon_stop() {	
		$listener = listener::byClassAndFunction('Volets', 'pull');
		if (is_object($listener))
			$listener->remove();
		$cron = cron::byClassAndFunction('Volets', 'ActionJour');
		if (is_object($cron)) 	
			$cron->remove();
		$cron = cron::byClassAndFunction('Volets', 'ActionNuit');
		if (is_object($cron)) 	
			$cron->remove();
	}
	public static function pull($_option) {
		log::add('Volets', 'debug', 'Objet mis à jour => ' . json_encode($_option));
		$Volet = Volets::byId($_option['Volets_id']);
		if (is_object($Volet) && $Volet->getIsEnable()) {
			$Event = cmd::byId($_option['event_id']);
			if(is_object($Event)){
				switch($Event->getlogicalId()){
					case 'azimuth360':
						log::add('Volets', 'debug', 'Gestion des volets par l\'azimuth');
						$Volet->ActionAzimute($_option['value']);
					break;
					case 'sunrise':
						log::add('Volets', 'debug', 'Replanification de l\'ouverture au lever du soleil');	
						$timstamp=$Volet->CalculHeureEvent($_option['value'],'DelaisDay');
						$Schedule=date("i",$timstamp) . ' ' . date("H",$timstamp) . ' * * * *';
						$cron = $Volet->CreateCron($Schedule, 'ActionJour');
					break;
					case 'sunset':	
						log::add('Volets', 'debug', 'Replanification de la fermeture au coucher du soleil');	
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
			log::add('Volets', 'debug', 'Exécution de la gestion du lever du soleil '.$Volet->getHumanName());
			$result=$Volet->EvaluateCondition('open','DayNight');
			if($result){
				$Action=$Volet->getConfiguration('action');
				$Volet->ExecuteAction($Action['open']);
			}else{
				$DelaisEval=$Volet->getConfiguration('DelaisEval'); 
				$Shedule = new DateTime();
				$Shedule->add(new DateInterval('PT'.$DelaisEval.'S'));
				$Volet->CreateCron($Shedule->format("i H d m *"), 'ActionJour');
			}
		}
	}
	public static function ActionNuit($_option) {
		log::add('Volets', 'debug', 'Objet mis à jour => ' . json_encode($_option));
		$Volet = Volets::byId($_option['Volets_id']);
		if (is_object($Volet) && $Volet->getIsEnable()) {
			log::add('Volets', 'debug', 'Exécution de la gestion du coucher du soleil '.$Volet->getHumanName());
			$result=$Volet->EvaluateCondition('close','DayNight');
			if($result){
				$Action=$Volet->getConfiguration('action');
				$Volet->ExecuteAction($Action['close']);
			}else{
				$DelaisEval=$Volet->getConfiguration('DelaisEval'); 
				//replannifer le cron
				$Shedule = new DateTime();
				$Shedule->add(new DateInterval('PT'.$DelaisEval.'S'));
				$Volet->CreateCron($Shedule->format("i H d m *"), 'ActionJour');
			}
		}
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
				log::add('Volets','debug','L\'objet "sunrise" n\'a pas été trouvé');
				return false;
			}
			$sunset=$heliotrope->getCmd(null,'sunset');
			if(is_object($sunset)){
				$value=$sunset->execCmd();
				$Nuit= new DateTime('@' .$this->CalculHeureEvent($value,'DelaisNight'));
			}
			else{	
				log::add('Volets','debug','L\'objet "sunset" n\'a pas été trouvé');
				return false;
			}
			$Now=new DateTime();
			if($Now>$Jours && $Now<$Nuit)
				return true;
		}
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
			log::add('Volets','debug','La fenêtre d\'ensoleillement '.$this->getHumanName().' est comprise entre : '.$AngleCntDrt.'° et '.$AngleCntGau.'°');
			if ($AngleCntDrt < $AngleCntGau){
				if($AngleCntDrt <= $Azimuth && $Azimuth <= $AngleCntGau)
					return true;
			}else{
				if($AngleCntDrt <= $Azimuth && $Azimuth <= 360)
					return true;
				if(0 <= $Azimut && $Azimuth <= $AngleCntGau)
					return true;
			}
		}
		return false;			
	}	
	public function SelectAction($Azimuth) {
		$Action=false;
		$StateCmd=$this->getCmd(null,'state');
		if(!is_object($StateCmd))
			return false;
		$isInWindows=$this->getCmd(null,'isInWindows');
		if(!is_object($isInWindows))
			return false;
		if($this->CheckAngle($Azimuth)){
			if(!$StateCmd->execCmd() || $StateCmd->execCmd()!= ""){
				$StateCmd->event(true);
				log::add('Volets','debug',$this->getHumanName().' Le soleil est dans la fenêtre');
				if($isInWindows->execCmd()){
					$Action='open';
					log::add('Volets','debug',$this->getHumanName().' Le plugin est configuré en mode hiver');
				}else{
					$Action='close';
					log::add('Volets','debug',$this->getHumanName().' Le plugin est configuré en mode été');
				}
			}
		}else{
			if($StateCmd->execCmd() || $StateCmd->execCmd()!= ""){
				$StateCmd->event(false);
				log::add('Volets','debug',$this->getHumanName().' Le soleil n\'est pas dans la fenêtre');
				if($isInWindows->execCmd()){
					$Action='close';
					log::add('Volets','debug',$this->getHumanName().' Le plugin est configuré en mode été');
				}else{
					$Action='open';
					log::add('Volets','debug',$this->getHumanName().' Le plugin est configuré en mode hiver');
				}
			}
		}
		$StateCmd->save();
		return $Action;
	}
	public function ActionAzimute($Azimuth) {
		if($this->checkJour()){
			log::add('Volets', 'debug', 'Exécution de '.$this->getHumanName());
			$Evenement=$this->SelectAction($Azimuth);
			if($Evenement != false){
				$result=$this->EvaluateCondition($Evenement,'Helioptrope');
				if($result){
					log::add('Volets','debug',$this->getHumanName().' Les conditions sont remplies');
					$Action=$this->getConfiguration('action');
					$this->ExecuteAction($Action[$Evenement]);
				}
			}
			return;
		}
		log::add('Volets','debug',$this->getHumanName().' Il fait nuit, la gestion par azimuth est désactivé');
	}
	public function ExecuteAction($Action) {	
		foreach($Action as $cmd){
			$Commande=cmd::byId(str_replace('#','',$cmd['cmd']));
			if(is_object($Commande)){
				if($this->getConfiguration('isRandom'))
				   sleep(rand(0,$this->getConfiguration('DelaisPresence')));
				log::add('Volets','debug',$this->getHumanName().' Exécution de '.$Commande->getHumanName());
				$Commande->execute($cmd['options']);
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
			}
			else{
				$cron->setSchedule($Schedule);
				$cron->save();
			}
		return $cron;
	}
	public function EvaluateCondition($evaluate,$TypeGestion){
		foreach($this->getConfiguration('condition') as $condition){
			if(($evaluate==$condition['evaluation']||$condition['evaluation']=='all')&&($TypeGestion==$condition['TypeGestion']||$condition['TypeGestion']=='all')){
				$expression = scenarioExpression::setTags($condition['expression']);
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
					log::add('Volets','debug',$this->getHumanName().' Les conditions ne sont pas remplies');
					return false;
				}
			}
		}
		return true;
	}
	public function getAngle($latitudeOrigine,$longitudeOrigne, $latitudeDest,$longitudeDest) {
		$longDelta = $longitudeDest - $longitudeOrigne;
		$y = sin($longDelta) * cos($latitudeDest);
		$x = (cos($latitudeOrigine)*sin($latitudeDest)) - (sin($latitudeOrigine)*cos($latitudeDest)*cos($longDelta));
		$angle = rad2deg(atan2($y, $x));
		while ($angle < 0) {
			$angle += 360;
		}
		return  floatval($angle % 360);
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
				$listener->addEvent($sunrise->getId());
				$listener->addEvent($sunset->getId());
				if ($this->getConfiguration('Helioptrope'))
					$listener->addEvent($heliotrope->getCmd(null,'azimuth360')->getId());
				if ($this->getConfiguration('DayNight')){
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
		self::AddCommande($this,"Position du soleil","state","info", 'binary',true,'sunInWindows');
		$isInWindows=self::AddCommande($this,"Etat mode","isInWindows","info","binary",false,'isInWindows');
		$inWindows=self::AddCommande($this,"Mode","inWindows","action","other",true,'inWindows');
		$inWindows->setValue($isInWindows->getId());
		$inWindows->save();
		$this->StartDemon();
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
		switch($this->getLogicalId()){
			case 'inWindows':
				$Listener=cmd::byId(str_replace('#','',$this->getValue()));
				if (is_object($Listener)) {
					if($Listener->execCmd())
						$Listener->event(false);
					else
						$Listener->event(true);
				$Listener->setCollectDate(date('Y-m-d H:i:s'));
				$Listener->save();
				}
			break;
		}
	}
}
?>
