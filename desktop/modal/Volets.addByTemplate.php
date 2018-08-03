<?php
	if (!isConnect('admin')) {
		throw new Exception('{{401 - Accès non autorisé}}');
	}
  sendVarToJS('Template', Volets::getTemplate());
?>
<div class="TemplateContener"></div>
<a class="btn btn-success btn-sm TemplateAction pull-right" data-action="save">
	<i class="fa fa-check-circle"></i> 
	Sauvegarder
</a>
<script>
	$('#eqlogictab').setValues({}, '.eqLogicAttr');
	$('.TemplateContener').html($('#eqlogictab').find('form').clone());
	$('.TemplateContener').find('.eqLogicAttr').addClass('TemplateAttr').removeClass('eqLogicAttr');
 	$('.TemplateContener').find('fieldset').append($('<div class="form-horizontal ParametersTempates">'));
	$('body').on('click','.TemplateAction[data-action=save]', function () {
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
	});
	$('body').on('change','.Gestions .TemplateAttr[data-l1key=configuration]', function () {
		//Creation du formulaire du template
		var form=$(this).closest('form');
		if($(this).is(':checked')){
			$.each(Template[$(this).attr('data-l2key')].update.configuration.action,function(index, value){
				if($('#'+value.cmd).length == 0)
					form.append(HtmlParameter(value.cmd,'data-l1key="configuration" data-l2key="action" data-l3key="cmd"',value.Description)).append($('<div class"actionOptions">'));

			});
			$.each(Template[$(this).attr('data-l2key')].update.configuration.condition,function(index, value){
				if($('#'+value.cmd).length == 0)
					form.append(HtmlParameter(value.cmd,'data-l1key="configuration" data-l2key="action" data-l3key="expression"',value.Description));

			});
		}else
			form.find('.'+$(this).attr('data-l2key')).remove();
	});
	function HtmlParameter(id,index,Description){
		return $('<div class="form-group">')
			.append($('<label class="col-xs-5 control-label" >')
				.text(Description))
			.append($('<div class="col-sm-5">')
				.append($('<div class="input-group">')
					.append($('<input id="'+id+'" class="TemplateAttr form-control input-sm cmdAction" '+index+'/>'))
					.append($('<span class="input-group-btn">')
						.append($('<a class="btn btn-success btn-sm listCmdAction data-type="action"">')
							.append($('<i class="fa fa-list-alt">'))))));
	}
</script>
