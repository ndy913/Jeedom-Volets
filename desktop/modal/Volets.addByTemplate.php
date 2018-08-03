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
	$('.TemplateAction[data-action=save]').off().on('click', function () {
		if($('.TemplateAttr[data-l1key=template]').value() != "" && $('.TemplateAttr[data-l1key=name]').value() != ""){
			var eqLogic=new Object();
			eqLogic.name=$('.TemplateAttr[data-l1key=name]').value();
			eqLogic.object_id=$('.TemplateAttr[data-l1key=object_id]').value();
			eqLogic.isVisible=$('.TemplateAttr[data-l1key=isVisible]').value();
			eqLogic.isEnable=$('.TemplateAttr[data-l1key=isEnable]').value();
			eqLogic.category=$('.TemplateAttr[data-l1key=category]').getValues('.TemplateAttr');
			if (typeof(eqLogic.configuration) === 'undefined')
				eqLogic.configuration=new Object();
			$('.Gestions .TemplateAttr[data-l1key=configuration]').each(function(){
				if($(this).is(':checked')){
					$.each(Template[$(this).attr('data-l2key')].config.configuration,function(index, value){
						if(index == 'action'){
							$.each(index.action,function(aindex, avalue){
								if(eqLogic.find(avalue.cmd)){
									eqLogic.find(avalue.cmd).parent().TypeGestion.push(avalue.TypeGestion);
								}else{
									eqLogic.configuration.action.push(aindex:avalue);
								}
							});
						}elseif(index == 'condition'){
							$.each(index.condition,function(cindex, cvalue){
								if(eqLogic.find(cvalue.expression)){
									eqLogic.find(cvalue.expression).parent().TypeGestion.push(cvalue.TypeGestion);
								}else{
									eqLogic.configuration.condition.push(cindex:cvalue);
								}
							});
						}else{
							eqLogic.configuration.push(index:value);
						}
					});
					//alert($(this).attr('data-l2key'));
					//eqLogic=$.extend(true,{},eqLogic,);
					alert(JSON.stringify(eqLogic));
				}
			});
			$('.ParametersTempates input').each(function(){
				$.each(eqLogic.configuration.action,function(index, value){
					eqLogic.cmd.replace('#'+$(this).attr('id'),$(this).val());
					eqLogic.options.replace('$Object',$('.TemplateAttr[data-l1key=object_id]').text());
					eqLogic.options.replace('$Name',eqLogic.name);
				});
				$.each(eqLogic.configuration.condition,function(index, value){
					eqLogic.expression.replace('#'+$(this).attr('id'),$(this).val());
				});
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
	$('.Gestions .TemplateAttr[data-l1key=configuration]').off().on('change', function () {
		//Creation du formulaire du template
		var Parameters=$(this).closest('form').find('.ParametersTempates');
		if($(this).is(':checked')){
			$.each(Template[$(this).attr('data-l2key')].update.configuration.action,function(index, value){
				if($('#'+value.cmd).length == 0)
					Parameters.append(HtmlParameter(value.cmd,'data-l1key="configuration" data-l2key="action" data-l3key="cmd"',value.Description)).append($('<div class"actionOptions">'));
				$('#'+value.cmd).addClass($(this).attr('data-l2key'));
			});
			$.each(Template[$(this).attr('data-l2key')].update.configuration.condition,function(index, value){
				if($('#'+value.cmd).length == 0)
					Parameters.append(HtmlParameter(value.cmd,'data-l1key="configuration" data-l2key="action" data-l3key="expression"',value.Description));
				$('#'+value.cmd).addClass($(this).attr('data-l2key'));
			});
		}else
			Parameters.removeClass($(this).attr('data-l2key'));
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
