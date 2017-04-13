function Volets_install(){
  foreach(eqLogic::byType('Volets') as $eqLogic){
    $eqLogic->save();
  }
}
function Volets_update(){
  foreach(eqLogic::byType('Volets') as $eqLogic){
    $eqLogic->save();
  }
}
