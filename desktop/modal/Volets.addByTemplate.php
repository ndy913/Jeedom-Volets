<?php
	if (!isConnect('admin')) {
		throw new Exception('{{401 - Accès non autorisé}}');
	}
  sendVarToJS('Template', Volets::getTemplate());
?>
<script>
$('#table_cmd tbody tr:last').setValues({}, '.cmdAttr');
	var message =  $('#eqlogictab').find('form').clone();
	message.find('.eqLogicAttr').addClass('TemplateAttr').removeClass('eqLogicAttr');
 	message.find('fieldset').append($('<div class="form-horizontal ParametersTempates">'));
  	bootbox.dialog({
		title: "{{Ajout d'un équipement avec template}}",
		message: message,
		height: "800px",
		width: "auto",
		backdrop: false,
		buttons: {
			"Annuler": {
				className: "btn-default",
				callback: function () {
					//el.atCaret('insert', result.human);
				}
			},
			success: {
				label: "Valider",
				className: "btn-primary",
				callback: function () {
					if($('.TemplateAttr[data-l1key=template]').value() != "" && $('.TemplateAttr[data-l1key=name]').value() != ""){
						var eqLogic=new Object();
						eqLogic.name=$('.TemplateAttr[data-l1key=name]').value();
						if (typeof(eqLogic.object_id) === 'undefined')
							eqLogic.object_id=new Object();
						eqLogic.object_id=$('.TemplateAttr[data-l1key=object_id]').value();
						if (typeof(eqLogic.configuration) === 'undefined')
							eqLogic.configuration=new Object();
						$('.Gestions .TemplateAttr[data-l1key=configuration]').each(function(){
							eqLogic=$.merge(eqLogic,Template[$(this).attr('data-l2key')].config);
						});
						$('.ParametersTempates input').each(function(){
							eqLogic.replace('#'+$(this).attr('id'),$(this).val());
						});
						jeedom.eqLogic.save({
							type: 'Volets',
							eqLogics: [eqLogic],
							error: function (error) {
								$('#div_alert').showAlert({message: error.message, level: 'danger'});
							},
							success: function (_data) {
								var vars = getUrlVars();
								var url = 'index.php?';
								for (var i in vars) {
									if (i != 'id' && i != 'saveSuccessFull' && i != 'removeSuccessFull') {
										url += i + '=' + vars[i].replace('#', '') + '&';
									}
								}
								modifyWithoutSave = false;
								url += 'id=' + _data.id + '&saveSuccessFull=1';
								loadPage(url);
							}
						});
					}
				}
			},
		}
	});
  </script>
