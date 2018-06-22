<?php
require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';
class Volets extends eqLogic {
	public static $_Gestions=array('Manuel','Jour','Nuit','Meteo','Absent','Azimut');
	public $_inverseCondition;
	public $_RatioHorizontal;
	public static function cron() {
		$deamon_info = self::deamon_info();
		if ($deamon_info['launchable'] != 'ok') 
			return;
		if ($deamon_info['state'] != 'ok') 
			return;
		foreach(eqLogic::byType('Volets') as $Volet){
			if(cache::byKey('Volets::ChangeState::'.$Volet->getId())->getValue(false)){
				if(time() - 60 >= cache::byKey('Volets::LastChangeState::'.$Volet->getId())->getValue(time()-60)){
					cache::set('Volets::ChangeState::'.$Volet->getId(),false, 0);
					cache::set('Volets::LastChangeState::'.$Volet->getId(),time(), 0);
				}
			}
			if (!$Volet->getConfiguration('Jour') && !$Volet->getConfiguration('Nuit'))
				break;
			$heliotrope=eqlogic::byId($Volet->getConfiguration('heliotrope'));
			if(!is_object($heliotrope))
				break;
			$Jour = cache::byKey('Volets::Jour::'.$Volet->getId())->getValue(0);
			$Nuit = cache::byKey('Volets::Nuit::'.$Volet->getId())->getValue(0);
			if(mktime() < $Jour || mktime() > $Nuit)
				$Volet->GestionNuit();
			else
				$Volet->GestionJour();
		}
	}
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
				if ($Volet->getConfiguration('Meteo')){
					$cron = cron::byClassAndFunction('Volets', 'GestionMeteo', array('Volets_id' => $Volet->getId()));
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
		foreach(eqLogic::byType('Volets') as $Volet)
			$Volet->StopDemon();
	}
	public static function pull($_option) {
		$Volet = Volets::byId($_option['Volets_id']);
		if (is_object($Volet) && $Volet->getIsEnable()) {
			$Event = cmd::byId($_option['event_id']);
			if(is_object($Event)){
				switch($Event->getlogicalId()){
					case 'azimuth360':
						log::add('Volets','info',$Volet->getHumanName().' : Mise à jour de l\'azimut du soleil');	
						$Volet->GestionAzimute($_option['value']);
					break;
					case 'altitude':
						log::add('Volets','info',$Volet->getHumanName().' : Mise à jour de l\'altitude du soleil');	
						$Volet->checkAltitude($_option['value']);
					break;
					case $Volet->getConfiguration('TypeDay'):
						log::add('Volets','info',$Volet->getHumanName().' : Replanification de l\'ouverture au lever du soleil');
						if($Volet->getConfiguration('DayMin') != '' && $_option['value'] < $Volet->getConfiguration('DayMin'))
						  	$timstamp=$Volet->CalculHeureEvent(jeedom::evaluateExpression($Volet->getConfiguration('DayMin')),false);
						else
							$timstamp=$Volet->CalculHeureEvent(jeedom::evaluateExpression($_option['value']),'DelaisDay');
						cache::set('Volets::Jour::'.$Volet->getId(),$timstamp, 0);
						break;
					case $Volet->getConfiguration('TypeNight'):
						log::add('Volets','info',$Volet->getHumanName().' : Replanification de la fermeture au coucher du soleil');
						if($Volet->getConfiguration('NightMax') != '' && $_option['value'] > $Volet->getConfiguration('NightMax'))
							$timstamp=$Volet->CalculHeureEvent(jeedom::evaluateExpression($Volet->getConfiguration('NightMax')),false);
						else
							$timstamp=$Volet->CalculHeureEvent(jeedom::evaluateExpression($_option['value']),'DelaisNight');						
						cache::set('Volets::Nuit::'.$Volet->getId(),$timstamp, 0);
					break;
					default:
						if ($Event->getId() == str_replace('#','',$Volet->getConfiguration('RealState'))){
							log::add('Volets','info',$Volet->getHumanName().' : Changement de l\'état réel du volet');
							$Volet->CheckRealState($_option['value']);
						}
						if ($Event->getId() == str_replace('#','',$Volet->getConfiguration('cmdPresent'))){
							log::add('Volets','info',$Volet->getHumanName().' : Mise à jour de la présence');	
							$Volet->GestionAbsent($_option['value']);
						}
					break;
				}
			}
		}
	}
	public function RearmementAutomatique($Evenement,$Gestion) {   
		cache::set('Volets::RearmementAutomatique::'.$this->getId(),false, 0);
		if($this->getCmd(null,'isArmed')->execCmd())
			return true;
		$Saison=$this->getSaison();
		if($this->checkCondition($Evenement,$Saison,$Gestion,true)){
		 	log::add('Volets','info',$this->getHumanName().' : Réarmement automatique');	
			$this->checkAndUpdateCmd('isArmed',true);
			cache::set('Volets::RearmementAutomatique::'.$this->getId(),true, 0);
			return true;
		}
		return false;
	}
	public function AutorisationAction($Evenement,$Gestion) {   
		if (!$this->getIsEnable())
			return false;
		$Mode = $this->getCmd(null,'gestion')->execCmd();
		switch($Gestion){
			case 'Manuel':
				if (!$this->getConfiguration('Manuel'))
					return false;
			break;
			case 'Nuit':
				if (!$this->getConfiguration('Nuit') || $Mode == "Nuit")
					return false;
			break;
			case 'Jour':
				if (!$this->getConfiguration('Jour') || $Mode != "Nuit" || $Mode == "Jour")
					return false;
			break;
			case 'Absent':
				if (!$this->getConfiguration('Absent') || $Mode == "Nuit")
					return false;
			break;
			case 'Meteo':					
				if (!$this->getConfiguration('Meteo')
				    || $Mode == "Nuit"
				    || $Mode == "Absent")
					return false;
			break;
			case 'Azimut':
				if (!$this->getConfiguration('Azimut')
				    || $Mode == "Nuit" 
				    || $Mode == "Absent" 
				    || $Mode == "Meteo")
					return false;
			break;
			
		}
		return $this->RearmementAutomatique($Evenement,$Gestion);
	}		
	public function CheckState($Value) {  
		cache::set('Volets::ActualState::'.$this->getId(),$Value, 0);
		$SeuilRealState=$this->getConfiguration("SeuilRealState");
		if($SeuilRealState == '')
			$SeuilRealState=0;
		if($this->getConfiguration('InverseHauteur')){	
			if($Value < $SeuilRealState)
				$State='open';
			else
				$State='close';
		}else{
			if($Value > $SeuilRealState)
				$State='open';
			else
				$State='close';
		}
		log::add('Volets','debug',$this->getHumanName().' : '.$Value.' >= '.$SeuilRealState.' => '.$State);
		return $State;
	}
	public function CheckRealState($Value) {   
		$State=$this->CheckState($Value);
		$this->setPosition($State);
		if(cache::byKey('Volets::ChangeState::'.$this->getId())->getValue(false)){
			if($Value != cache::byKey('Volets::CurrentState::'.$this->getId())->getValue(0))
				return;
			log::add('Volets','info',$this->getHumanName().' : Le changement d\'état est autorisé');
			cache::set('Volets::ChangeState::'.$this->getId(),false, 0);
		}else{
			$this->GestionManuel($State);
		}
	}
	public function CheckOtherGestion($Gestion,$force=false) {  
		$Saison=$this->getSaison();
		$CurrentEvenement = $this->getCmd(null,'gestion')->execCmd();
		switch($Gestion){
			case 'Jour':
				if ($this->getConfiguration('Absent')){	
					$Commande=cmd::byId(str_replace('#','',$this->getConfiguration('cmdPresent')));
					if(is_object($Commande) && $Commande->execCmd() == false){
						//$this->GestionAbsent($Etat,true);
						$Evenement=$this->checkCondition('close',$Saison,'Absent');   		
						if($Evenement != false && $Evenement == 'close'){
							log::add('Volets', 'info', $this->getHumanName().'[Gestion '.$Gestion.'] : Il n\'y a personne dans la maison la gestion Absent prend le relais');
							$this->CheckRepetivite('Absent',$Evenement,$Saison);
							return false;
						}
					}
				}
			case 'Absent':
				if ($this->getConfiguration('Meteo')){
					$Evenement=$this->checkCondition('close',$Saison,'Meteo');   		
					if($Evenement != false && $Evenement == 'close'){
						log::add('Volets', 'info', $this->getHumanName().'[Gestion '.$Gestion.'] : La gestion Meteo prend le relais');
						$this->CheckRepetivite('Meteo',$Evenement,$Saison);
						return false;
					}
				}
			case 'Meteo':	
				if ($this->getConfiguration('Azimut')){
					$heliotrope=eqlogic::byId($this->getConfiguration('heliotrope'));
					if(is_object($heliotrope)){
						$Azimut=$heliotrope->getCmd(null,'azimuth360')->execCmd();
						$Evenement=$this->SelectAction($Azimut,$Saison);
						if($Evenement != false){
							if($force || $Evenement == $CurrentEvenement){
								//$this->GestionAzimute($Azimut,true);
								$Evenement=$this->checkCondition($Evenement,$Saison,'Azimut');
								if( $Evenement != false){
									log::add('Volets', 'info', $this->getHumanName().'[Gestion '.$Gestion.'] : La gestion par Azimut prend le relais');
									$this->CheckRepetivite('Azimut',$Evenement,$Saison);
									return false;
								}
							}
						}
					}
				}
		}
		return true;
	}
	public function GestionManuel($State){
		if ($this->AutorisationAction($State,'Manuel')){
			$RearmementAutomatique = cache::byKey('Volets::RearmementAutomatique::'.$this->getId());		
			if(!$RearmementAutomatique->getValue(false)){
				$Saison=$this->getSaison();
				log::add('Volets','info','Un evenement manuel a été détécté sur le volet '.$this->getHumanName().' La gestion a été désactivé');
				$Evenement=$this->checkCondition($State,$Saison,'Manuel');   		
				if($Evenement != false){
					$this->CheckRepetivite('Manuel',$Evenement,$Saison);
					$this->checkAndUpdateCmd('isArmed',false);
				}
			}else{
				cache::set('Volets::RearmementAutomatique::'.$this->getId(),false, 0);
              			log::add('Volets','debug',$this->getHumanName().' Le réarmement a eu lieu on ignore l\'action manuel');
            		}
		}
	}
	public function GestionJour($force=false) {    
		if ($this->AutorisationAction('open','Jour') || $force){	
			log::add('Volets', 'info', $this->getHumanName().'[Gestion Jour] : Exécution de la gestion du lever du soleil');
			$Saison=$this->getSaison();
			$Evenement=$this->checkCondition('open',$Saison,'Jour');
			if( $Evenement!= false){
				if(!$this->CheckOtherGestion('Jour'))
					return;
				$this->CheckRepetivite('Jour',$Evenement,$Saison);
			}
		}
		if (!$this->getConfiguration('Jour'))
			$this->GestionManuel('close');
	}
	public function GestionNuit($force=false) {
		if ($this->AutorisationAction('close','Nuit') || $force){
			log::add('Volets', 'info',$this->getHumanName().'[Gestion Nuit] : Exécution de la gestion du coucher du soleil ');
			$Saison=$this->getSaison();
			$Evenement=$this->checkCondition('close',$Saison,'Nuit');
			if( $Evenement!= false){
				$this->CheckRepetivite('Nuit',$Evenement,$Saison);
			}
		}
		if (!$this->getConfiguration('Nuit'))
			$this->GestionManuel('open');
	}
	public static function GestionMeteo($_option) {
		$Volet = Volets::byId($_option['Volets_id']);
		if (is_object($Volet) && $Volet->AutorisationAction('close','Meteo')){
			log::add('Volets', 'info',$Volet->getHumanName().'[Gestion Meteo] : Exécution de la gestion météo');
			$Saison=$Volet->getSaison();
			$Evenement=$Volet->checkCondition('close',$Saison,'Meteo');   
			if( $Evenement != false ){
				$Volet->CheckRepetivite('Meteo',$Evenement,$Saison);
			}else{
				if($Volet->getCmd(null,'gestion')->execCmd() != 'Meteo')
					return;	
				if(!$Volet->CheckOtherGestion('Meteo'))
					return;	
				$Jour = cache::byKey('Volets::Jour::'.$Volet->getId())->getValue(0);
				$Nuit = cache::byKey('Volets::Nuit::'.$Volet->getId())->getValue(0);
				if(mktime() < $Jour || mktime() > $Nuit)
					$Volet->GestionNuit(true);
				else
					$Volet->GestionJour(true);
			}
		}
	}
  	public function GestionAbsent($Etat,$force=false) {
		if($Etat)
			$Evenement='open';
		else
			$Evenement='close';
		if ($this->AutorisationAction($Evenement,'Absent') || $force){
			$Saison=$this->getSaison();
			$Evenement=$this->checkCondition($Evenement,$Saison,'Absent');
			if( $Evenement != false ){
				if($Evenement == 'open'){
					if(!$this->CheckOtherGestion('Absent'))
						return;	
					$Jour = cache::byKey('Volets::Jour::'.$this->getId())->getValue(0);
					$Nuit = cache::byKey('Volets::Nuit::'.$this->getId())->getValue(0);
					if(mktime() < $Jour || mktime() > $Nuit)
						$this->GestionNuit(true);
					else
						$this->GestionJour(true);
					return;	
				}
				$this->CheckRepetivite('Absent',$Evenement,$Saison);
			}
		}
	}
	public function GestionAzimute($Azimut,$force=false) {
		$Saison=$this->getSaison();
		$Evenement=$this->SelectAction($Azimut,$Saison);
		if ($this->AutorisationAction($Evenement,'Azimut') || $force){
			if ($Evenement != false){
				$Evenement=$this->checkCondition($Evenement,$Saison,'Azimut');
				if( $Evenement!= false)
					$this->CheckRepetivite('Azimut',$Evenement,$Saison);
			}
		}
	}	
	public function CheckAngle($Azimut) {
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
				log::add('Volets','debug',$this->getHumanName().'[Gestion Azimut] : Les coordonnées GPS de l\'angle d\'exposition au soleil de votre fenêtre sont mal configurées');
				return false;	
			}
		}
		$result=false;
		$Ratio=0;
		if ($AngleCntDrt < $AngleCntGau){
			if($AngleCntDrt <= $Azimut && $Azimut <= $AngleCntGau)
				$result= true;
			$Ratio=($Azimut-$AngleCntDrt)*(100/($AngleCntGau-$AngleCntDrt));
		}else{
			if($AngleCntDrt <= $Azimut && $Azimut <= 360){
				$result= true;
				$Ratio=($Azimut-$AngleCntDrt+360)*(100/($AngleCntGau-$AngleCntDrt+360));
			}
			if(0 <= $Azimut && $Azimut <= $AngleCntGau){
				$result= true;
				$Ratio=($Azimut-($AngleCntDrt-360)+360)*(100/($AngleCntGau-($AngleCntDrt-360)+360));
			}
		}
		if(!$result)
			$Ratio=100;
		$this->_RatioHorizontal=round($Ratio);
		log::add('Volets','info',$this->getHumanName().'[Gestion Azimut] : L\'azimut ' . $Azimut . '° est compris entre : '.$AngleCntDrt.'°  et '.$AngleCntGau.'° => '.$this->boolToText($result));
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
	public function SelectAction($Azimut,$saison) {
		$Action=false;
		if($this->CheckAngle($Azimut)){
			$this->checkAndUpdateCmd('state',true);
			log::add('Volets','info',$this->getHumanName().'[Gestion Azimut] : Le soleil est dans la fenêtre');
			if($saison =='hiver')
				$Action='open';
			else
				$Action='close';
		}else{
			$this->checkAndUpdateCmd('state',false);
			log::add('Volets','info',$this->getHumanName().'[Gestion Azimut] : Le soleil n\'est pas dans la fenêtre');
			if($saison == 'été')
				$Action='open';
			else
				$Action='close';
		}
		return $Action;
	}
	public function getHauteur($Gestion,$Evenement,$Saison){
		if($Evenement == 'open')
			$Hauteur=100;
		elseif($Evenement == 'close')
			$Hauteur=0;
		if ($Gestion == 'Azimut' && $Saison != 'hiver' && $this->getCmd(null,'state')->execCmd() && !$this->_inverseCondition)
			$Hauteur=$this->checkAltitude();
		if($this->getConfiguration('InverseHauteur'))
			$Hauteur=100-$Hauteur;
		$this->_inverseCondition=false;
		return $Hauteur;
	}
	public function RatioEchelle($Ratio,$Value){
		$cmd=$this->getCmd(null, $Ratio);
		if(!is_object($cmd))
			return $Value;
		$min=$cmd->getConfiguration('minValue');
		$max=$cmd->getConfiguration('maxValue');
		if($min == '' && $max == '')
			return $Value;
		if($min == '')
			$min=0;
		if($max == '')
			$max=100;
		return round(($Value/100)*($max-$min)+$min);
		
	}
	public function AleatoireActions($Gestion,$ActionMove,$Evenement){
		log::add('Volets','info',$this->getHumanName().'[Gestion '.$Gestion.'] : Lancement aléatoire de volet');
		shuffle($ActionMove);
		for($loop=0;$loop<count($ActionMove);$loop++){
			$this->ExecuteAction($ActionMove[$loop],$Gestion);
			sleep(rand(0,$this->getConfiguration('maxDelaiRand')));
		}
	}
	public function CheckRepetivite($Gestion,$Evenement,$Saison){
		if(cache::byKey('Volets::ChangeState::'.$this->getId())->getValue(false))
			return;
		$RatioVertical=$this->getHauteur($Gestion,$Evenement,$Saison);
		$Change['RatioVertical']=false;
		$Change['RatioHorizontal']=false;
		$Change['Position']=false;
		$Change['Gestion']=false;
		if($this->getCmd(null,'RatioVertical')->execCmd() != $RatioVertical)
			$Change['RatioVertical']=true;
		if($this->getCmd(null,'RatioHorizontal')->execCmd() != $this->_RatioHorizontal)
			$Change['RatioHorizontal']=true;
		$this->checkAndUpdateCmd('RatioVertical',$this->RatioEchelle('RatioVertical',$RatioVertical));
		$this->checkAndUpdateCmd('RatioHorizontal',$this->RatioEchelle('RatioHorizontal',$this->_RatioHorizontal));
		if($this->getPosition() != $Evenement)
			$Change['Position']=true;
		if ($this->getConfiguration('RealState') == '')
			$this->setPosition($Evenement);
		if($this->getCmd(null,'gestion')->execCmd() != $Gestion)
			$Change['Gestion']=true;
		$this->checkAndUpdateCmd('gestion',$Gestion);
		$this->CheckActions($Gestion,$Evenement,$Saison,$Change);
	}
	public function CheckIsRatio($Cmd,$Ratio,$Gestion){
		if(isset($Cmd['options'])){
			 foreach($Cmd['options'] as $key => $option){
				if(stripos($option, '#'.$this->getCmd(null,$Ratio)->getId().'#') !== FALSE){
					log::add('Volets','debug',$this->getHumanName().'[Gestion '.$Gestion.'] : La commande '.$Ratio.' est dans l\'option '.$key);
					return true;
				}
			}
		}
		return false;
	}
	public function CheckActions($Gestion,$Evenement,$Saison,$Change){
		log::add('Volets','info',$this->getHumanName().'[Gestion '.$Gestion.'] : Autorisation d\'executer les actions : '.json_encode($Change));
		$ActionMove=null;
		foreach($this->getConfiguration('action') as $Cmd){	
			if (!$this->CheckValid($Cmd,$Evenement,$Saison,$Gestion))
				continue;
			if($Cmd['isVoletMove']){
				if($Change['RatioVertical']){
					if($this->CheckIsRatio($Cmd,'RatioVertical',$Gestion)){
						if($this->getConfiguration('RandExecution'))
							$ActionMove[]=$Cmd;
						else
							$this->ExecuteAction($Cmd,$Gestion,$Evenement);
						continue;
					}
				}
				if($Change['RatioHorizontal']){
					if($this->CheckIsRatio($Cmd,'RatioHorizontal',$Gestion)){
						if($this->getConfiguration('RandExecution'))
							$ActionMove[]=$Cmd;
						else
							$this->ExecuteAction($Cmd,$Gestion,$Evenement);
						continue;
					}
				}
				if($Change['Position']){
					if($this->getConfiguration('RandExecution'))
						$ActionMove[]=$Cmd;
					else
						$this->ExecuteAction($Cmd,$Gestion,$Evenement);
					continue;
				}
			} else {
				if($Change['Gestion'] || $Change['Position'])
					$this->ExecuteAction($Cmd,$Gestion,$Evenement);
			}
		}
		if($this->getConfiguration('RandExecution') && $ActionMove != null)
			$this->AleatoireActions($Gestion,$ActionMove,$Evenement);
	}
	public function ExecuteAction($Cmd,$Gestion,$Evenement){		
		try {
			$options = array();
			if(isset($Cmd['options'])){
				foreach($Cmd['options'] as $key => $option){
					$options[$key]=jeedom::evaluateExpression($option);
					if($key == 'slider'){
						if($Cmd['isVoletMove']){
							if(cache::byKey('Volets::ActualState::'.$this->getId())->getValue(0) == $options[$key]){
								log::add('Volets','info',$this->getHumanName().'[Gestion '.$Gestion.'] : La commande '.jeedom::toHumanReadable($Cmd['cmd']).' ne sera pas executé car la valeur est identique');
								return;
							}
							cache::set('Volets::CurrentState::'.$this->getId(),$options[$key], 0);
						}
					}
				}
			}else{
				if($Cmd['isVoletMove']){
					if($Evenement == 'open')
						cache::set('Volets::CurrentState::'.$this->getId(),100, 0);
					else
						cache::set('Volets::CurrentState::'.$this->getId(),0, 0);
				}
			}
			if($Cmd['isVoletMove']){
				cache::set('Volets::ChangeState::'.$this->getId(),true, 0);
				cache::set('Volets::LastChangeState::'.$this->getId(),time(), 0);
			}
			scenarioExpression::createAndExec('action', $Cmd['cmd'], $options);
			log::add('Volets','debug',$this->getHumanName().'[Gestion '.$Gestion.'] : Exécution de '.jeedom::toHumanReadable($Cmd['cmd']).' ('.json_encode($options).')');
		} catch (Exception $e) {
			log::add('Volets', 'error',$this->getHumanName().'[Gestion '.$Gestion.'] : '. __('Erreur lors de l\'exécution de ', __FILE__) . jeedom::toHumanReadable($Cmd['cmd']) . __('. Détails : ', __FILE__) . $e->getMessage());
		}
	}
	public function CalculHeureEvent($HeureStart, $delais) {
		if(strlen($HeureStart)==3)
			$Heure=substr($HeureStart,0,1);
		else
			$Heure=substr($HeureStart,0,2);
		$Minute=floatval(substr($HeureStart,-2));
		if($delais != false){
			if($this->getConfiguration($delais)!='')
				$Minute+=floatval($this->getConfiguration($delais));
			while($Minute>=60){
				$Minute-=60;
				$Heure+=1;
			}
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
	public function CheckValid($Element,$Evenement,$Saison,$Gestion,$autoArm=false){
		if(array_search($Evenement, $Element['evaluation']) === false)
			return false;
		if(array_search($Saison, $Element['saison']) === false)
			return false;
		if(array_search($Gestion, $Element['TypeGestion']) === false)
			return false;		
		if (isset($Element['enable']) && $Element['enable'] == 0 && !$autoArm)
			return false;	
		if (isset($Element['autoArm']) && $Element['autoArm'] == 0 && $autoArm)
			return false;
		return true;
	}
	public function checkCondition($Evenement,$Saison,$Gestion,$autoArm=false){	
		$isAutoArm=false;
		foreach($this->getConfiguration('condition') as $Condition){
			if (!$this->CheckValid($Condition,$Evenement,$Saison,$Gestion,$autoArm))
				continue;
			$isAutoArm=true;
			if (!$this->EvaluateCondition($Condition,$Gestion)){
				if($Condition['Inverse']){
					log::add('Volets','info',$this->getHumanName().'[Gestion '.$Gestion.'] : La condition inverse l\'état du volet');
					if($Evenement == 'close')
						$Evenement='open';
					else
						$Evenement='close';
					if ($this->_inverseCondition){
						$this->_inverseCondition=false;
						return false;
					}
					$this->_inverseCondition=true;
					return $this->checkCondition($Evenement,$Saison,$Gestion);
				}
				log::add('Volets','info',$this->getHumanName().'[Gestion '.$Gestion.'] : Les conditions ne sont pas remplies');
				return false;
			}
		}
		log::add('Volets','info',$this->getHumanName().'[Gestion '.$Gestion.'] : Les conditions sont remplies pour '.$Evenement);
		if($autoArm)
			return $isAutoArm;
		else
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
	public function EvaluateCondition($Condition,$Gestion){
		$_scenario = null;
		$expression = scenarioExpression::setTags($Condition['expression'], $_scenario, true);
		$message = __('Evaluation de la condition : ['.jeedom::toHumanReadable($Condition['expression']).'][', __FILE__) . trim($expression) . '] = ';
		$result = evaluate($expression);
		$message .=$this->boolToText($result);
		log::add('Volets','info',$this->getHumanName().'[Gestion '.$Gestion.'] : '.$message);
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
			$Altitude =$heliotrope->getCmd(null,'altitude')->execCmd();	
			$zenith = $heliotrope->getConfiguration('zenith', 90.58);	
			$ObstructionMin = jeedom::evaluateExpression($this->getConfiguration('ObstructionMin', ''));
			if($ObstructionMin == '')
				$ObstructionMin = 0;
			$ObstructionMax = jeedom::evaluateExpression($this->getConfiguration('ObstructionMax', ''));
			if($ObstructionMax == '')
				$ObstructionMax = $zenith;
			if($Altitude < intval($ObstructionMin))
				return 100;
			if($Altitude > intval($ObstructionMax))
				return 100;
			$Hauteur=round($Altitude*100/$zenith);	
			log::add('Volets','info',$this->getHumanName().'[Gestion Altitude] : L\'altitude actuel est a '.$Hauteur.'% par rapport au zenith');	
			return $Hauteur;
		}
	}
	public function StopDemon(){
		$listener = listener::byClassAndFunction('Volets', 'pull', array('Volets_id' => $this->getId()));
		if (is_object($listener))
			$listener->remove();
		$cron = cron::byClassAndFunction('Volets', 'GestionMeteo', array('Volets_id' => $this->getId()));
		if (is_object($cron)) 	
			$cron->remove();
		$cache = cache::byKey('Volets::Jour::'.$this->getId());
		if (is_object($cache)) 	
			$cache->remove();
		$cache = cache::byKey('Volets::Nuit::'.$this->getId());
		if (is_object($cache)) 	
			$cache->remove();
		$cache = cache::byKey('Volets::RearmementAutomatique::'.$this->getId());
		if (is_object($cache)) 	
			$cache->remove();	
		$cache = cache::byKey('Volets::ChangeState::'.$this->getId());	
		if (is_object($cache)) 	
			$cache->remove();	
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
				if ($this->getConfiguration('RealState') != ''){
					$listener->addEvent($this->getConfiguration('RealState'));
					$RealState=cmd::byId($this->getConfiguration('RealState'));
					if(is_object($RealState)){
						$Value=$RealState->execCmd();
						$this->checkAndUpdateCmd('RatioVertical',$Value);
						$SeuilRealState=$this->getConfiguration("SeuilRealState");
						if($SeuilRealState == '')
							$SeuilRealState=0;
						if($this->getConfiguration('InverseHauteur')){	
							if($Value < $SeuilRealState)
								$State='open';
							else
								$State='close';
						}else{
							if($Value > $SeuilRealState)
								$State='open';
							else
								$State='close';
						}
						$this->setPosition($State);
					}
				}
				//$listener->addEvent($heliotrope->getCmd(null,'altitude')->getId());
				if ($this->getConfiguration('Azimut'))
					$listener->addEvent($heliotrope->getCmd(null,'azimuth360')->getId());
				if ($this->getConfiguration('Absent'))
					$listener->addEvent($this->getConfiguration('cmdPresent'));
				if ($this->getConfiguration('Jour')){
					$sunrise=$heliotrope->getCmd(null,$this->getConfiguration('TypeDay'));
					if(!is_object($sunrise))
						return false;
					$listener->addEvent($sunrise->getId());	
					if($this->getConfiguration('DayMin') != '' && $sunrise->execCmd() < $this->getConfiguration('DayMin'))
						$Jour=$this->CalculHeureEvent(jeedom::evaluateExpression($this->getConfiguration('DayMin')),false);
					else					  
						$Jour=$this->CalculHeureEvent($sunrise->execCmd(),'DelaisDay');
				}else{
					$sunrise=$heliotrope->getCmd(null,'sunrise');
					$Jour=$this->CalculHeureEvent($sunrise->execCmd(),false);
				}				
				cache::set('Volets::Jour::'.$this->getId(),$Jour, 0);
				if ($this->getConfiguration('Nuit')){
					$sunset=$heliotrope->getCmd(null,$this->getConfiguration('TypeNight'));
					if(!is_object($sunset))
						return false;
					$listener->addEvent($sunset->getId());
					if($this->getConfiguration('NightMax') != '' && $sunset->execCmd() > $this->getConfiguration('NightMax'))
						$Nuit=$this->CalculHeureEvent(jeedom::evaluateExpression($this->getConfiguration('NightMax')),false);
					else					  
						$Nuit=$this->CalculHeureEvent($sunset->execCmd(),'DelaisNight');	
				}else{
					$sunset=$heliotrope->getCmd(null,'sunset');
					$Nuit=$this->CalculHeureEvent($sunset->execCmd(),false);
				}
				cache::set('Volets::Nuit::'.$this->getId(),$Nuit, 0);
				if ($this->getConfiguration('Meteo'))
					$cron = $this->CreateCron('* * * * * *', 'GestionMeteo', array('Volets_id' => intval($this->getId())));
				$listener->save();	
				if(mktime() < $Jour || mktime() > $Nuit)
					$this->GestionNuit(true);
				else{
					if($this->CheckOtherGestion('Jour',true))
						$this->GestionJour(true);
				}
				cache::set('Volets::ChangeState::'.$this->getId(),false, 0);
				cache::set('Volets::LastChangeState::'.$this->getId(),time(), 0);
			}
		}
	}
	public function AddCommande($Name,$_logicalId,$Type="info", $SubType='binary',$visible,$GenericType='',$Template='') {
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
		$Commande->setGeneric_type($GenericType);
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
		if($this->getConfiguration('heliotrope') == "Aucun")
			throw new Exception(__('Impossible d\'enregister, la configuration de l\'equipement heliotrope n\'existe pas', __FILE__));
		else{
			$heliotrope=eqlogic::byId($this->getConfiguration('heliotrope'));
			if(is_object($heliotrope)){	
				if($heliotrope->getConfiguration('geoloc') == "")
					throw new Exception(__('Impossible d\'enregister, la configuration  heliotrope n\'est pas correcte', __FILE__));
				$geoloc = geotravCmd::byEqLogicIdAndLogicalId($heliotrope->getConfiguration('geoloc'),'location:coordinate');
				if(is_object($geoloc) && $geoloc->execCmd() == '')	
					throw new Exception(__('Impossible d\'enregister, la configuration de  "Localisation et trajet" (geotrav) n\'est pas correcte', __FILE__));
				$center=explode(",",$geoloc->execCmd());
				$GeoLoc['lat']=$center[0];
				$GeoLoc['lng']=$center[1];
				if($this->getConfiguration('Droite') != ''){
					if(!is_array($this->getConfiguration('Droite')))
						$this->setConfiguration('Droite',$GeoLoc);
				}
				if($this->getConfiguration('Gauche') != ''){
					if(!is_array($this->getConfiguration('Gauche')))
						$this->setConfiguration('Gauche',$GeoLoc);
				}
				if($this->getConfiguration('Centre') != ''){
					if(!is_array($this->getConfiguration('Centre')))
						$this->setConfiguration('Centre',$GeoLoc);
				}
			}
		}
	}
	public function postSave() {
		$this->AddCommande("Ratio Vertical","RatioVertical","info", 'numeric',1,'FLAP_SLIDER');
		$this->AddCommande("Ratio Horizontal","RatioHorizontal","info", 'numeric',1,'FLAP_SLIDER');
		$this->AddCommande("Gestion Active","gestion","info", 'string',1,'GENERIC_INFO');
		$state=$this->AddCommande("Position du soleil","state","info", 'binary',1,'GENERIC_INFO','sunInWindows');
		$isInWindows=$this->AddCommande("Etat mode","isInWindows","info","binary",0,'','isInWindows');
		$inWindows=$this->AddCommande("Mode","inWindows","action","select",1,'','inWindows');
		$inWindows->setConfiguration('listValue','1|Hiver;0|Eté');
		$inWindows->setValue($isInWindows->getId());
		$inWindows->save();
		$isArmed=$this->AddCommande("Etat activation","isArmed","info","binary",0,'LOCK_STATE','lock');
		//$this->checkAndUpdateCmd('isArmed',true);
		$Armed=$this->AddCommande("Activer","armed","action","other",1,'LOCK_CLOSE','lock');
		$Armed->setValue($isArmed->getId());
		$Armed->setConfiguration('state', '1');
		$Armed->setConfiguration('armed', '1');
		$Armed->save();
		$Released=$this->AddCommande("Désactiver","released","action","other",1,'LOCK_OPEN','lock');
		$Released->setValue($isArmed->getId());
		$Released->save();
		$Released->setConfiguration('state', '0');
		$Released->setConfiguration('armed', '1');
		$Position=$this->AddCommande("Etat du volet","position","info","string",0,'GENERIC_INFO');
		$VoletState=$this->AddCommande("Position du volet","VoletState","action","select",1,'','volet');
		$VoletState->setConfiguration('listValue','open|Ouvert;close|Fermé');
		$VoletState->setDisplay('title_disable', 1);
		$VoletState->setValue($Position->getId());
		$VoletState->save();
		$this->StopDemon();
		$this->StartDemon();
	}	
	public function preRemove() {
		$listener = listener::byClassAndFunction('Volets', 'pull', array('Volets_id' => $this->getId()));
		if (is_object($listener))
			$listener->remove();
		$cron = cron::byClassAndFunction('Volets', 'GestionMeteo', array('Volets_id' => $this->getId()));
		if (is_object($cron)) 	
			$cron->remove();
		$cache = cache::byKey('Volets::Jour::'.$this->getId());
		if (is_object($cache)) 	
			$cache->remove();
		$cache = cache::byKey('Volets::Nuit::'.$this->getId());
		if (is_object($cache)) 	
			$cache->remove();
		$cache = cache::byKey('Volets::RearmementAutomatique::'.$this->getId());
		if (is_object($cache)) 	
			$cache->remove();	
		$cache = cache::byKey('Volets::ChangeState::'.$this->getId());	
		if (is_object($cache)) 	
			$cache->remove();
		$cache = cache::byKey('Volets::HauteurAlt::'.$this->getId());	
		if (is_object($cache)) 	
			$cache->remove();
		$cache = cache::byKey('Volets::ActualState::'.$this->getId());	
		if (is_object($cache)) 	
			$cache->remove();
	}
}
class VoletsCmd extends cmd {
    	public function execute($_options = null) {
		$Listener=cmd::byId(str_replace('#','',$this->getValue()));
		if (is_object($Listener)) {	
			switch($this->getLogicalId()){
				case 'armed':
					$Listener->event(true);	
					$Jour = cache::byKey('Volets::Jour::'.$this->getEqLogic()->getId())->getValue(mktime()-60);
					$Nuit = cache::byKey('Volets::Nuit::'.$this->getEqLogic()->getId())->getValue(mktime()+60);
					if(mktime() < $Jour || mktime() > $Nuit)
						$this->getEqLogic()->GestionNuit(true);
					else{
						if($this->getEqLogic()->CheckOtherGestion('Jour',true))
							$this->getEqLogic()->GestionJour(true);
					}
				break;
				case 'released':
					$Listener->event(false);
										
				break;
				case 'VoletState':
					$Value=$_options['select'];
					if($this->getEqLogic()->getConfiguration('RealState') != ''){
						$State=cmd::byId(str_replace('#','',$this->getEqLogic()->getConfiguration('RealState')));
						if(is_object($State))
							$Value=$this->getEqLogic()->CheckState($State);
					}
					$Listener->event($Value);
				break;
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
