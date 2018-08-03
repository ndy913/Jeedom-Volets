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
			eqLogic.object_id=$('.TemplateAttr[data-l1key=object_id]').val();
			eqLogic.isVisible=$('.TemplateAttr[data-l1key=isVisible]').val();
			eqLogic.isEnable=$('.TemplateAttr[data-l1key=isEnable]').val();
			eqLogic.category=$('.TemplateAttr[data-l1key=category]').getValues('.TemplateAttr');
			if (typeof(eqLogic.configuration) === 'undefined')
				eqLogic.configuration=new Object();
			$('.Gestions .TemplateAttr[data-l1key=configuration]').each(function(){
				eqLogic.configuration[$(this).attr('data-l2key')]=$(this).is(':checked');
				if($(this).is(':checked')){
					$.each(Template[$(this).attr('data-l2key')].config.configuration,function(index, value){
						if(index == 'action'){
							if (typeof(eqLogic.configuration.action) === 'undefined')
								eqLogic.configuration.action=new Object();
							$.each(value.action,function(aindex, avalue){
								if(configuration.find(avalue.cmd)){
									eqLogic.configuration.find(avalue.cmd).parent().TypeGestion.push(avalue.TypeGestion);
								}else{
									eqLogic.configuration.action[aindex]=avalue;
								}
							});
						}else if(index == 'condition'){
							if (typeof(eqLogic.configuration.condition) === 'undefined')
								eqLogic.configuration.condition=new Object();
							$.each(value.condition,function(cindex, cvalue){
								if(eqLogic.configuration.find(cvalue.expression)){
									eqLogic.configuration.find(cvalue.expression).parent().TypeGestion.push(cvalue.TypeGestion);
								}else{
									eqLogic.configuration.condition[cindex]=cvalue;
								}
							});
						}else{
							eqLogic.configuration[index]=value;
						}
					});
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
