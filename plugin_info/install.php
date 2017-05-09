<?php
function Volets_update(){
  foreach(eqLogic::byType('Volets') as $eqLogic){
    $Armed=$eqLogic->getCmd(null,"arme");
    $Armed->remove();
    $eqLogic->save();
  }
}
?>
