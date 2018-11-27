<?php
require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';
class Volets extends eqLogic {
	public static $_Gestions=array('Manuel','Jour','Nuit','Azimut','Evenement','Conditionnel');
	public $_inverseCondition;
	public $_RatioHorizontal;
	public static function cron() {
		$deamon_info = self::deamon_info();
		if ($deamon_info['launchable'] != 'ok') 
			return;
		if ($deamon_info['state'] != 'ok') 
			return;
		foreach(eqLogic::byType('Volets') as $Volet){
          		if(!$Volet->getIsEnable())
              			continue;
			if(cache::byKey('Volets::ChangeState::'.$Volet->getId())->getValue(false)){
				if(time() - 60 >= cache::byKey('Volets::LastChangeState::'.$Volet->getId())->getValue(time()-60)){
					cache::set('Volets::ChangeState::'.$Volet->getId(),false, 0);
					cache::set('Volets::LastChangeState::'.$Volet->getId(),time(), 0);
				}
			}
			if (!$Volet->getConfiguration('Jour') && !$Volet->getConfiguration('Nuit'))
				break;
			$Jour = cache::byKey('Volets::Jour::'.$Volet->getId())->getValue(mktime()-60);
			$Nuit = cache::byKey('Volets::Nuit::'.$Volet->getId())->getValue(mktime()+60);
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
				if ($Volet->getConfiguration('Conditionnel')){
					$cron = cron::byClassAndFunction('Volets', 'GestionConditionnel', array('Volets_id' => $Volet->getId()));
					if (!is_object($cron)) 	
						return $return;
				}			
			}
		}
		$return['state'] = 'ok';
		return $return;
	}
	public static function deamon_start($_debug = false) {
		//log::remove('Volets');
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
						if($Volet->getCmd(null,'gestion')->execCmd() != "Nuit"){
							log::add('Volets','info',$Volet->getHumanName().' : Mise à jour de l\'azimut du soleil');	
							$Volet->GestionAzimute($_option['value']);
						}
					break;
					case 'altitude':
						if($Volet->getCmd(null,'gestion')->execCmd() != "Nuit"){
							log::add('Volets','info',$Volet->getHumanName().' : Mise à jour de l\'altitude du soleil');	
							$Volet->checkAltitude($_option['value']);
						}
					break;
					case $Volet->getConfiguration('TypeDay'):
						$timestamp=$Volet->CalculHeureEvent($_option['value'],'Day');
						cache::set('Volets::Jour::'.$Volet->getId(),$timestamp, 0);
						log::add('Volets','info',$Volet->getHumanName().' : Replanification de l\'ouverture au lever du soleil le ' . date("d/m/Y H:i:s",$timestamp));
						break;
					case $Volet->getConfiguration('TypeNight'):
						$timestamp=$Volet->CalculHeureEvent($_option['value'],'Night');						
						cache::set('Volets::Nuit::'.$Volet->getId(),$timestamp, 0);
						log::add('Volets','info',$Volet->getHumanName().' : Replanification de la fermeture au coucher du soleil le ' . date("d/m/Y H:i:s",$timestamp));
					break;
					default:
						if ($Event->getId() == str_replace('#','',$Volet->getConfiguration('RealState'))){
							log::add('Volets','info',$Volet->getHumanName().' : Changement de l\'état réel du volet');
							$Volet->CheckRealState($_option['value']);
						}else{
							foreach($Volet->getConfiguration('EvenementObject') as $ObjectEvent){
								if ($Event->getId() == str_replace('#','',$ObjectEvent['Cmd'])){
									log::add('Volets','info',$Volet->getHumanName().$ObjectEvent['Cmd'].' : Un evenement s\'est produit sur un objet ecouté');
									if (!$Volet->EvaluateCondition($_option['value'].$ObjectEvent['Operande'].$ObjectEvent['Value'],'Evenement'))
										$Volet->GestionEvenement('open');
									else
										$Volet->GestionEvenement('close');
									break;
								}
							}
						}
					break;
				}
			}
		}
	}
	public function RearmementAutomatique($Evenement,$Gestion) {   
		cache::set('Volets::RearmementAutomatique::'.$this->getId(),false, 0);
		cache::set('Volets::ChangeState::'.$this->getId(),false, 0);
		cache::set('Volets::LastChangeState::'.$this->getId(),time(), 0);
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
			case 'Evenement':
				if (!$this->getConfiguration('Evenement') || $Mode == "Nuit")
					return false;
			break;
			case 'Conditionnel':					
				if (!$this->getConfiguration('Conditionnel')
				    || $Mode == "Nuit"
				    || $Mode == "Evenement")
					return false;
			break;
			case 'Azimut':
				if (!$this->getConfiguration('Azimut')
				    || $Mode == "Nuit" 
				    || $Mode == "Evenement" 
				    || $Mode == "Conditionnel")
					return false;
			break;
			
		}
		return $this->RearmementAutomatique($Evenement,$Gestion);
	}		
	/*public function CheckState($Value) {  
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
	}*/
	public function CheckRealState($Value) {   	
		if(cache::byKey('Volets::ChangeState::'.$this->getId())->getValue(false)){
			if($Value != $this->getCmd(null,'position')->execCmd())
				return;
			log::add('Volets','info',$this->getHumanName().' : Le changement d\'état est autorisé');
			cache::set('Volets::ChangeState::'.$this->getId(),false, 0);
		}else{
			//if($Value != $this->getCmd(null,'position')->execCmd())
				$this->GestionManuel($State);
		}
		$this->checkAndUpdateCmd('position',$Value);
	}
	public function CheckOtherGestion($Gestion,$force=false) {  
		$Saison=$this->getSaison();
		switch($Gestion){
			case 'Jour':
				if ($this->getConfiguration('Evenement')){	
					foreach($this->getConfiguration('EvenementObject') as $ObjectEvent){
						if($this->EvaluateCondition($ObjectEvent['Cmd'].$ObjectEvent['Operande'].$ObjectEvent['Value'],'Evenement')){
							$Commande=cmd::byId(str_replace('#','',$ObjectEvent['Cmd']));
							if(is_object($Commande)){
								$Evenement=$this->checkCondition('close',$Saison,'Evenement');   		
								if($Evenement != false && $Evenement == 'close'){
									log::add('Volets', 'info', $this->getHumanName().'[Gestion '.$Gestion.'] : Une condition évenementielle est valide, nous exécutons la gestion Evenement');
									$this->CheckRepetivite('Evenement',$Evenement,$Saison,$force);
									return false;
								}
							}
						}
					}
				}
			case 'Evenement':
				if ($this->getConfiguration('Conditionnel')){
					$Evenement=$this->checkCondition('close',$Saison,'Conditionnel');   		
					if($Evenement != false && $Evenement == 'close'){
						log::add('Volets', 'info', $this->getHumanName().'[Gestion '.$Gestion.'] : La gestion Conditionnel prend le relais');
						$this->CheckRepetivite('Conditionnel',$Evenement,$Saison,$force);
						return false;
					}
				}
			case 'Conditionnel':	
				if ($this->getConfiguration('Azimut')){
					$heliotrope=eqlogic::byId($this->getConfiguration('heliotrope'));
					if(is_object($heliotrope)){
						$Azimut=$heliotrope->getCmd(null,'azimuth360')->execCmd();
						$Evenement=$this->SelectAction($Azimut,$Saison);
						if($Evenement != false){
							$Evenement=$this->checkCondition($Evenement,$Saison,'Azimut');
							if($Evenement == false)
								break;
							log::add('Volets', 'info', $this->getHumanName().'[Gestion '.$Gestion.'] : La gestion par Azimut prend le relais');
							$this->CheckRepetivite('Azimut',$Evenement,$Saison,$force);
							return false;
							
						}
					}
				}
		}
		return true;
	}
	public function GestionManuel($State,$force=false){
		if ($force || $this->AutorisationAction($State,'Manuel')){
			$RearmementAutomatique = cache::byKey('Volets::RearmementAutomatique::'.$this->getId());		
			if(!$RearmementAutomatique->getValue(false)){
				$Saison=$this->getSaison();
				$Evenement=$this->checkCondition($State,$Saison,'Manuel');   		
				if($force || $Evenement != false){
					$this->checkAndUpdateCmd('isArmed',false);
					$this->checkAndUpdateCmd('gestion','Manuel');
					log::add('Volets','info',$this->getHumanName().'[Gestion Manuel] : Un evenement manuel a été détecté: La gestion a été désactivée');
					$this->CheckRepetivite('Manuel',$Evenement,$Saison,true);
				}
			}else{
				cache::set('Volets::RearmementAutomatique::'.$this->getId(),false, 0);
              			log::add('Volets','debug',$this->getHumanName().' Le réarmement a eu lieu on ignore l\'action manuelle');
            		}
		}elseif($force)	{
			$this->checkAndUpdateCmd('gestion','Manuel');
		}
	}
	public function GestionJour($force=false) {    
		if ($force || $this->AutorisationAction('open','Jour')){	
			log::add('Volets', 'info', $this->getHumanName().'[Gestion Jour] : Exécution de la gestion du lever du soleil');
			$Saison=$this->getSaison();
			$Evenement=$this->checkCondition('open',$Saison,'Jour');
			if($Evenement!= false){
				if(!$this->CheckOtherGestion('Jour',$force))
					return;
				$this->CheckRepetivite('Jour',$Evenement,$Saison,$force);
			}
		}elseif($force)	{
			if(!$this->CheckOtherGestion('Jour',$force))
				$this->checkAndUpdateCmd('gestion','Jour');
		}
	}
	public function GestionNuit($force=false) {
		if ($force || $this->AutorisationAction('close','Nuit')){
			log::add('Volets', 'info',$this->getHumanName().'[Gestion Nuit] : Exécution de la gestion du coucher du soleil ');
			$Saison=$this->getSaison();
			$Evenement=$this->checkCondition('close',$Saison,'Nuit');
			if( $Evenement!= false){
				$this->CheckRepetivite('Nuit',$Evenement,$Saison,$force);
			}elseif($force){
				if(!$this->CheckOtherGestion('Jour',$force))
					$this->checkAndUpdateCmd('gestion','Jour');
			}
		}elseif($force){
			if(!$this->CheckOtherGestion('Jour',$force))
				$this->checkAndUpdateCmd('gestion','Jour');
		}
	}
	public static function GestionConditionnel($_option) {
		$Volet = Volets::byId($_option['Volets_id']);
		if (is_object($Volet) && $Volet->AutorisationAction('close','Conditionnel')){
			log::add('Volets', 'info',$Volet->getHumanName().'[Gestion Conditionnel] : Exécution de la gestion Conditionnel');
			$Saison=$Volet->getSaison();
			$Evenement=$Volet->checkCondition('close',$Saison,'Conditionnel');   
			if( $Evenement != false ){
				$Volet->CheckRepetivite('Conditionnel',$Evenement,$Saison);
			}else{
				if($Volet->getCmd(null,'gestion')->execCmd() != 'Conditionnel')
					return;	
				if(!$Volet->CheckOtherGestion('Conditionnel'))
					return;	
				$Jour = cache::byKey('Volets::Jour::'.$Volet->getId())->getValue(mktime()-60);
				$Nuit = cache::byKey('Volets::Nuit::'.$Volet->getId())->getValue(mktime()+60);
				if(mktime() < $Jour || mktime() > $Nuit)
					$Volet->GestionNuit(true);
				else
					$Volet->GestionJour(true);
			}
		}
	}
  	public function GestionEvenement($Evenement,$force=false) {		
		if ($this->AutorisationAction($Evenement,'Evenement') || $force){
			$Saison=$this->getSaison();
			$Evenement=$this->checkCondition($Evenement,$Saison,'Evenement');
			if( $Evenement != false ){
				if($Evenement == 'open'){
					if(!$this->CheckOtherGestion('Evenement'))
						return;	
					$Jour = cache::byKey('Volets::Jour::'.$this->getId())->getValue(mktime()-60);
					$Nuit = cache::byKey('Volets::Nuit::'.$this->getId())->getValue(mktime()+60);
					if(mktime() < $Jour || mktime() > $Nuit)
						$this->GestionNuit(true);
					else
						$this->GestionJour(true);
					return;	
				}
				$this->CheckRepetivite('Evenement',$Evenement,$Saison);
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
		$result=false;
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
		if($this->CheckAngle($Azimut) && $this->checkAltitude() !== false){
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
			$Hauteur=$this->getAltitudeRatio();
		if($this->getConfiguration('InverseHauteur'))
			$Hauteur=100-$Hauteur;
		$this->_inverseCondition=false;
		return $Hauteur;
	}
	public function RatioEchelle($Ratio,$Value){
		$cmd=$this->getCmd(null, $Ratio);
		if(!is_object($cmd))
			return $Value;
		$min=jeedom::evaluateExpression($cmd->getConfiguration('minValue'));
		$max=jeedom::evaluateExpression($cmd->getConfiguration('maxValue'));
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
	public function CheckRepetivite($Gestion,$Evenement,$Saison,$force=false){
		if(!$force && cache::byKey('Volets::ChangeState::'.$this->getId())->getValue(false))
			return;
		$RatioVertical=$this->getHauteur($Gestion,$Evenement,$Saison);
		$Change['RatioVertical']=false;
		$Change['RatioHorizontal']=false;
		$Change['Position']=false;
		$Change['Gestion']=false;
		if($force || $this->getCmd(null,'RatioVertical')->execCmd() != $RatioVertical)
			$Change['RatioVertical']=true;
		if($force || $this->getCmd(null,'RatioHorizontal')->execCmd() != $this->_RatioHorizontal)
			$Change['RatioHorizontal']=true;
		$this->checkAndUpdateCmd('RatioVertical',$this->RatioEchelle('RatioVertical',$RatioVertical));
		$this->checkAndUpdateCmd('RatioHorizontal',$this->RatioEchelle('RatioHorizontal',$this->_RatioHorizontal));
		//if($force || $this->CheckPositionChange($Evenement,$Gestion))
			$Change['Position']=true;
		if($force || $this->getCmd(null,'gestion')->execCmd() != $Gestion)
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
	
	public function CheckPositionChange($Cmd,$Evenement,$Gestion){	
		$options = array();
		if(isset($Cmd['options'])){
			foreach($Cmd['options'] as $key => $option){
				$options[$key]=jeedom::evaluateExpression($option);
				if($key == 'slider'){
					$NewPosition = $options[$key];
					if($this->getCmd(null,'position')->execCmd() == $NewPosition){
						log::add('Volets','info',$this->getHumanName().'[Gestion '.$Gestion.'] : La commande '.jeedom::toHumanReadable($Cmd['cmd']).' ne sera pas executée car la valeur est identique');
						return false;
					}
				}
			}
		}else{
			if($Evenement == 'open'){
				$RatioVertical = $this->getCmd(null,'RatioVertical');
				$NewPosition=100;
				if(is_object($RatioVertical))
					$CurrentState = $RatioVertical->getConfiguration('minValue', $NewPosition);

			}else{
				$RatioVertical = $this->getCmd(null,'RatioVertical');
				$NewPosition=100;
				if(is_object($RatioVertical))
					$NewPosition = $RatioVertical->getConfiguration('maxValue', $NewPosition);
			}
			if($this->getCmd(null,'position')->execCmd() == $NewPosition){
				log::add('Volets','info',$this->getHumanName().'[Gestion '.$Gestion.'] : La commande '.jeedom::toHumanReadable($Cmd['cmd']).' ne sera pas executée car la valeur est identique');
				return false;
			}
		}
		return array($NewPosition,$options);
	}
	public function CheckActions($Gestion,$Evenement,$Saison,$Change){
		log::add('Volets','info',$this->getHumanName().'[Gestion '.$Gestion.'] : Autorisation d\'exécuter les actions : '.json_encode($Change));
		$ActionMove=null;
		foreach($this->getConfiguration('action') as $Cmd){	
			if (!$this->CheckValid($Cmd,$Evenement,$Saison,$Gestion))
				continue;
			if(!$Cmd['isVoletMove'] && $Change['Position'] && !$Change['Gestion'])
				continue;
			$this->ExecuteAction($Cmd,$Gestion,$Evenement);
		}
		if($this->getConfiguration('RandExecution') && $ActionMove != null)
			$this->AleatoireActions($Gestion,$ActionMove,$Evenement);
	}
	public function ExecuteAction($Cmd,$Gestion,$Evenement){		
		try {
			list($NewPosition,$options)=$this->CheckPositionChange($Cmd,$Evenement,$Gestion);
			if($this->getConfiguration('RealState') == '')
				$this->checkAndUpdateCmd('position',$NewPosition);				
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
	private function StringToHeure($Horaire) {
		if(strlen($Horaire)==3)
			$Heure=substr($Horaire,0,1);
		else
			$Heure=substr($Horaire,0,2);
		$Minute=floatval(substr($Horaire,-2));
		return array($Heure, $Minute);
	}
	public function CalculHeureEvent($Horaire,$Evenement=false) {
		list($Heure, $Minute) = $this->StringToHeure($Horaire);
		if($Evenement != false){
			$delais=jeedom::evaluateExpression($this->getConfiguration('Delais'.$Evenement));
			if($delais != '')
				$Minute += floatval($delais);
			while($Minute >= 60){
				$Minute-=60;
				$Heure+=1;
			}
			if($Evenement == 'Day'){
				$HoraireImp = jeedom::evaluateExpression($this->getConfiguration('DayMin'));
				if($HoraireImp != '' && $Minute + $Heure * 100 < $HoraireImp)
					list($Heure, $Minute) = $this->StringToHeure($HoraireImp);
			}else{
				$HoraireImp = jeedom::evaluateExpression($this->getConfiguration('NightMax'));
				if($HoraireImp != '' && $Minute + $Heure * 100 > $HoraireImp)
					list($Heure, $Minute) = $this->StringToHeure($HoraireImp);
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
			if (!$this->EvaluateCondition($Condition['expression'],$Gestion)){
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
		if($Gestion == "Conditionnel" && $isAutoArm == false) 
			return false;
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
		$expression = scenarioExpression::setTags($Condition, $_scenario, true);
		$message = __('Evaluation de la condition : ['.jeedom::toHumanReadable($Condition).'][', __FILE__) . trim($expression) . '] = ';
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
			if($Altitude < intval($ObstructionMin) || $Altitude > intval($ObstructionMax)){
				log::add('Volets','info',$this->getHumanName().'[Gestion Altitude] : L\'altitude actuelle n\'est pas dans la fenêtre');
				return false;
			}
			return array($Altitude,$zenith); 
		}
	}
	public function getAltitudeRatio() { 
		$checkAltitude=$this->checkAltitude();
		if($checkAltitude === FALSE)
			return 100;
		list($Altitude,$zenith)=$checkAltitude;
		switch($this->getConfiguration('TypeFenetre', '')){
			default:
			case "porte":
				$Min=0;	
			break;
			case "fenetre":
				$Min=42;	
			break;
			case "petit":
				$Min=66;
			break;
			case "toit":
				return 0;
		}
		$Hauteur=round((($Altitude-$Min)*100)/($zenith-$Min),0);
		if($Hauteur < 0)
			return 0;
		log::add('Volets','info',$this->getHumanName().'[Gestion Altitude] : L\'altitude actuelle est à '.$Hauteur.'% par rapport au zenith');	
		return $Hauteur;
	}
	public function StopDemon(){
		$listener = listener::byClassAndFunction('Volets', 'pull', array('Volets_id' => $this->getId()));
		if (is_object($listener))
			$listener->remove();
		$cron = cron::byClassAndFunction('Volets', 'GestionConditionnel', array('Volets_id' => $this->getId()));
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
			cache::set('Volets::ChangeState::'.$this->getId(),false, 0);
			cache::set('Volets::LastChangeState::'.$this->getId(),time(), 0);
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
					if(is_object($RealState))
						$this->checkAndUpdateCmd('position',$RealState->execCmd());
					
				};
				//$listener->addEvent($heliotrope->getCmd(null,'altitude')->getId());
				if ($this->getConfiguration('Azimut'))
					$listener->addEvent($heliotrope->getCmd(null,'azimuth360')->getId());
				if ($this->getConfiguration('Evenement')){
					foreach($this->getConfiguration('EvenementObject') as $Evenement){
						if($Evenement['Cmd'] != '')
							$listener->addEvent($Evenement['Cmd']);
					}
				}
				if ($this->getConfiguration('Jour')){
					$sunrise=$heliotrope->getCmd(null,$this->getConfiguration('TypeDay'));
					if(!is_object($sunrise))
						return false;
					$listener->addEvent($sunrise->getId());	
					$Jour=$this->CalculHeureEvent($sunrise->execCmd(),'Day');
				}else{
					$sunrise=$heliotrope->getCmd(null,'sunrise');
					$Jour=$this->CalculHeureEvent($sunrise->execCmd());
				}				
				cache::set('Volets::Jour::'.$this->getId(),$Jour, 0);
				if ($this->getConfiguration('Nuit')){
					$sunset=$heliotrope->getCmd(null,$this->getConfiguration('TypeNight'));
					if(!is_object($sunset))
						return false;
					$listener->addEvent($sunset->getId());
					$Nuit=$this->CalculHeureEvent($sunset->execCmd(),'Night');	
				}else{
					$sunset=$heliotrope->getCmd(null,'sunset');
					$Nuit=$this->CalculHeureEvent($sunset->execCmd());
				}
				cache::set('Volets::Nuit::'.$this->getId(),$Nuit, 0);
				if ($this->getConfiguration('Conditionnel'))
					$cron = $this->CreateCron('* * * * *', 'GestionConditionnel', array('Volets_id' => intval($this->getId())));
				$listener->save();	
				log::add('Volets','info',$this->getHumanName().' : Planification de l\'ouverture au lever du soleil le ' . date("d/m/Y H:i:s",$Jour) . ' et de la fermeture au coucher du soleil le ' . date("d/m/Y H:i:s",$Nuit));		
				if(mktime() < $Jour || mktime() > $Nuit)
					$this->GestionNuit(true);
				else
					$this->GestionJour(true);
			}
		}
	}
	public function AddCommande($Name,$_logicalId,$Type="info", $SubType='binary',$visible,$GenericType='DONT',$Template='') {
		$Commande = $this->getCmd(null,$_logicalId);
		if (!is_object($Commande))
		{
			$Commande = new VoletsCmd();
			$Commande->setId(null);
			$Commande->setName($Name);
			$Commande->setIsVisible($visible);
			$Commande->setLogicalId($_logicalId);
			$Commande->setEqLogic_id($this->getId());
			$Commande->setType($Type);
			$Commande->setSubType($SubType);
			$Commande->setTemplate('dashboard',$Template );
			$Commande->setTemplate('mobile', $Template);
		}
			$Commande->setGeneric_type($GenericType);
			$Commande->save();
		return $Commande;
	}
	public function preSave() {
		if($this->getConfiguration('heliotrope') == "Aucun")
			throw new Exception(__('Impossible d\'enregistrer, la configuration de l\'équipement heliotrope n\'existe pas', __FILE__));
		else{
			$heliotrope=eqlogic::byId($this->getConfiguration('heliotrope'));
			if(is_object($heliotrope)){	
				if($heliotrope->getConfiguration('geoloc') == "")
					throw new Exception(__('Impossible d\'enregistrer, la configuration  heliotrope n\'est pas correcte', __FILE__));
				$geoloc = geotravCmd::byEqLogicIdAndLogicalId($heliotrope->getConfiguration('geoloc'),'location:coordinate');
				if(is_object($geoloc) && $geoloc->execCmd() == '')	
					throw new Exception(__('Impossible d\'enregistrer, la configuration de  "Localisation et trajet" (geotrav) n\'est pas correcte', __FILE__));
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
		$this->AddCommande("Ratio Vertical","RatioVertical","info", 'numeric',1,'DONT');
		$this->AddCommande("Ratio Horizontal","RatioHorizontal","info", 'numeric',1,'DONT');
		$this->AddCommande("Gestion Active","gestion","info", 'string',1,'GENERIC_INFO');
		$state=$this->AddCommande("Position du soleil","state","info", 'binary',1,'GENERIC_INFO','sunInWindows');
		$isInWindows=$this->AddCommande("Etat mode","isInWindows","info","binary",0,'DONT','isInWindows');
		$inWindows=$this->AddCommande("Mode","inWindows","action","select",1,'DONT','inWindows');
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
		$Position=$this->AddCommande("Etat du volet","position","info","numeric",0,'GENERIC_INFO');
		$VoletState=$this->AddCommande("Position du volet","VoletState","action","select",1,'DONT','volet');
		$VoletState->setConfiguration('listValue','100|Ouvert;0|Fermé');
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
		$cron = cron::byClassAndFunction('Volets', 'GestionConditionnel', array('Volets_id' => $this->getId()));
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
		$cache = cache::byKey('Volets::CurrentState::'.$this->getId());	
		if (is_object($cache)) 	
			$cache->remove();
	}
	public static function getTemplate() {
		$path = dirname(__FILE__) . '/../config/devices';
		if (isset($_device) && $_device != '') {
			$files = ls($path, $_device . '.json', false, array('files', 'quiet'));
			if (count($files) == 1) {
				try {
					$content = file_get_contents($path . '/' . $files[0]);
					if (is_json($content)) {
						$deviceConfiguration = json_decode($content, true);
						ajax::success($deviceConfiguration[$_device]);
					}
				} catch (Exception $e) {
					ajax::error(displayExeption($e), $e->getCode());
				}
			}
		}
		$files = ls($path, '*.json', false, array('files', 'quiet'));
		$return = array();
		foreach ($files as $file) {
			try {
				$content = file_get_contents($path . '/' . $file);
				if (is_json($content)) {
					$return = array_merge($return, json_decode($content, true));
				}
			} catch (Exception $e) {
			}
		}
		if (isset($_device) && $_device != '') {
			if (isset($return[$_device])) {
				return $return[$_device];
			}
		}
		return $return;
	}
}
class VoletsCmd extends cmd {
    	public function execute($_options = null) {
		$Listener=cmd::byId(str_replace('#','',$this->getValue()));
		if (is_object($Listener)) {	
			switch($this->getLogicalId()){
				case 'armed':
					$Listener->event(true);	
					cache::set('Volets::ChangeState::'.$this->getEqLogic()->getId(),false, 0);
					cache::set('Volets::LastChangeState::'.$this->getEqLogic()->getId(),time(), 0);
					$Jour = cache::byKey('Volets::Jour::'.$this->getEqLogic()->getId())->getValue(mktime()-60);
					$Nuit = cache::byKey('Volets::Nuit::'.$this->getEqLogic()->getId())->getValue(mktime()+60);
					log::add('Volets','debug', 'Jour :'.date('Y-m-d H:i:s',$Jour) .' > '. date('Y-m-d H:i:s',mktime()) .' > Nuit :'.date('Y-m-d H:i:s',$Nuit));
					if(mktime() < $Jour || mktime() > $Nuit)
						$this->getEqLogic()->GestionNuit(true);
					else
						$this->getEqLogic()->GestionJour(true);
					if($this->getEqLogic()->getConfiguration('RealState') != ''){
						$RealState=cmd::byId(str_replace('#','',$this->getEqLogic()->getConfiguration('RealState')));
						if(is_object($RealState))
							$this->getEqLogic()->checkAndUpdateCmd('position',$RealState->execCmd());	
					}
				break;
				case 'released':
					cache::set('Volets::ChangeState::'.$this->getEqLogic()->getId(),false, 0);
					cache::set('Volets::LastChangeState::'.$this->getEqLogic()->getId(),time(), 0);
					$this->getEqLogic()->GestionManuel($this->getEqLogic()->getPosition(),true);
					$Listener->event(false);
					$this->getEqLogic()->checkAndUpdateCmd('gestion','Manuel');										
				break;
				case 'VoletState':
					$Value=$_options['select'];
					if($this->getEqLogic()->getConfiguration('RealState') != ''){
						$RealState=cmd::byId(str_replace('#','',$this->getEqLogic()->getConfiguration('RealState')));
						if(is_object($RealState))
							$this->getEqLogic()->checkAndUpdateCmd('position',$RealState->execCmd());
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
