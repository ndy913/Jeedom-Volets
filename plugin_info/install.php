<?php
function Volets_update(){
  foreach(eqLogic::byType('Volets') as $eqLogic){
    $Armed=$eqLogic->getCmd(null,"arme");
    $Armed->remove();
    $Released=$eqLogic->getCmd(null,"disable");
    $Released->remove();
    $eqLogic->save();
  }
}
?>
