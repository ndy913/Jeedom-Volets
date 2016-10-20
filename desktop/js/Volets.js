var map;
var Center= new Object();
$('body').on('click','.SelectMap',function(){
	map.setCenter(Center);
});
$('body').on('change','.eqLogicAttr[data-l1key=configuration][data-l2key=heliotrope]',function(){
	$.ajax({
		type: 'POST',            
		async: false,
		url: 'plugins/Volets/core/ajax/Volets.ajax.php',
		data:{
			action: 'getInformation',
			heliotrope:$(this).val(),
		},
		dataType: 'json',
		global: false,
		error: function(request, status, error) {},
		success: function(data) {
			if (!data.result)
				$('#div_alert').showAlert({message: 'Aucun message recu', level: 'error'});
			if (typeof(data.result.geoloc) !== 'undefined') {
				var center=data.result.geoloc.configuration.coordinate.split(",");
				Center.lat=parseFloat(center[0]);
				Center.lng=parseFloat(center[1]);
				// création de la carte
				map = new google.maps.Map( document.getElementById('MyMap'),{
					'backgroundColor': '#FFF',
					'mapTypeControl':  true,
					'streetViewControl': false,
					'panControl':true,
					'scaleControl': true,
					'overviewMapControl': true,
					'mapTypeControlOptions': {
					    'style':google.maps.MapTypeControlStyle.DROPDOWN_MENU,
					    'position':google.maps.ControlPosition.LEFT_BOTTOM 
					},
					'mapTypeId': google.maps.MapTypeId.ROADMAP
					'center': Center,
					'scrollwheel': true,
					'zoom': 20
				});
			}
		}
	});
});
function getAngle(Coordinates) {
		var longDelta = Coordinates[1].lng - Coordinates[0].lng;
		var y = Math.sin(longDelta) * Math.cos(Coordinates[1].lat);
		var x = Math.cos(Coordinates[0].lat)*Math.sin(Coordinates[1].lat) - Math.sin(Coordinates[0].lat)*Math.cos(Coordinates[1].lat)*Math.cos(longDelta);

		var radians = Math.atan2(y, x);
		var angle = radians * 180 / Math.PI
		while (angle < 0) {
			angle += 360;
		}
		angle=angle % 360;
		angle=angle-90;
		return  angle;
	}

function saveEqLogic(_eqLogic) {
	var state_order = '';
    if (!isset(_eqLogic.configuration)) {
        _eqLogic.configuration = {};
    }	
	if (typeof( _eqLogic.cmd) !== 'undefined') {
		for(var index in  _eqLogic.cmd) { 
			_eqLogic.cmd[index].configuration.condition=new Object();
			_eqLogic.cmd[index].configuration.action=new Object();
			var cmdParameters=$('.cmd[data-cmd_id=' + init(_eqLogic.cmd[index].id) + ']');
			var ConditionArray= new Array();
			var inArray= new Array();
			var outArray= new Array();
			$('.cmd[data-cmd_id=' + init(_eqLogic.cmd[index].id)+ '] .ConditionGroup').each(function( index ) {
				//console.log( index + ": " + $( this ).text() );
				ConditionArray.push($(this).getValues('.expressionAttr')[0])
			});
			$('.cmd[data-cmd_id=' + init(_eqLogic.cmd[index].id)+ '] .ActionIn .ActionGroup').each(function( index ) {
				//console.log( index + ": " + $( this ).text() );
				inArray.push($(this).getValues('.expressionAttr')[0])
			});
			$('.cmd[data-cmd_id=' + init(_eqLogic.cmd[index].id)+ '] .ActionOut .ActionGroup').each(function( index ) {
				//console.log( index + ": " + $( this ).text() );
				outArray.push($(this).getValues('.expressionAttr')[0])
			});
			_eqLogic.cmd[index].configuration.condition=ConditionArray;
			_eqLogic.cmd[index].configuration.action.in=inArray;
			_eqLogic.cmd[index].configuration.action.out=outArray;
			if(_eqLogic.cmd[index].id =="new")
				_eqLogic.cmd[index].id=null;
		}
	}
    return _eqLogic;
}
function addCmdToTable(_cmd) {
	if (!isset(_cmd)) {
		var _cmd = {configuration: {}};
		bootbox.prompt("Nom ?", function (result) {
			if (result !== null && result != '') 
				_cmd.id="new";
				_cmd.name=result;
				AddZone(_cmd);
			//$('.eqLogicAction[data-action=save]').trigger('click');
		});
	}
	else
		AddZone(_cmd);
	
}
function AddZone(_zone){
	if (init(_zone.name) == '') {
      		return;
   	}
	if (init(_zone.icon) == '') {
     	   // _zone.icon = '<i class="icon fa fa-dot-circle-o"><\/i>';
    	    _zone.icon = '';
  	  }
	if (typeof(_zone.configuration.Droit) !== 'undefined' && _zone.configuration.Droit != "" && typeof(_zone.configuration.Gauche) !== 'undefined' && _zone.configuration.Gauche != "") {
		_zone.configuration.Droit.lat=parseFloat(_zone.configuration.Droit.lat);
		_zone.configuration.Droit.lng=parseFloat(_zone.configuration.Droit.lng);
		_zone.configuration.Gauche.lat=parseFloat(_zone.configuration.Gauche.lat);
		_zone.configuration.Gauche.lng=parseFloat(_zone.configuration.Gauche.lng);
	}else {
		_zone.configuration.Droit= new Object();
		_zone.configuration.Droit=Center;
		_zone.configuration.Gauche= new Object();
		_zone.configuration.Gauche.lat=_zone.configuration.Droit.lat;
		_zone.configuration.Gauche.lng=_zone.configuration.Droit.lng+ (1 / 3600);
	}	
	var Coordinates=[_zone.configuration.Droit,_zone.configuration.Gauche];
	var Droit=new google.maps.Marker({
		position: _zone.configuration.Droit,
		map: map,
		draggable:true,
		title: _zone.name + " - Droite vue exterieur"
	  });
	var Gauche=new google.maps.Marker({
		position:_zone.configuration.Gauche,
		map: map,
		draggable:true,
		title: _zone.name  + " - Gauche vue exterieur"
	  });
	var Polyline =new google.maps.Polyline({
		path: Coordinates,
		geodesic: true,
		strokeColor: '#40A497',
		strokeOpacity: 1.0,
		map: map,
		strokeWeight: 2
	});
	google.maps.event.addListener(Droit,'drag', function(event) {
		Coordinates[0].lat=event.latLng.lat();
		Coordinates[0].lng=event.latLng.lng();
		Polyline.setPath(Coordinates);
		$('.cmd[data-cmd_id=' + init(_zone.id) + ']').find('.cmdAttr[data-l1key=configuration][data-l2key=Droit]').val(JSON.stringify(event.latLng));
		$('.cmd[data-cmd_id=' + init(_zone.id) + ']').find('.cmdAttr[data-l1key=configuration][data-l2key=Angle]').val(getAngle(Coordinates));
	});
	google.maps.event.addListener(Gauche,'drag', function(event) {
		Coordinates[1].lat=event.latLng.lat();
		Coordinates[1].lng=event.latLng.lng();
		Polyline.setPath(Coordinates);
		$('.cmd[data-cmd_id=' + init(_zone.id) + ']').find('.cmdAttr[data-l1key=configuration][data-l2key=Gauche]').val(JSON.stringify(event.latLng));
		$('.cmd[data-cmd_id=' + init(_zone.id) + ']').find('.cmdAttr[data-l1key=configuration][data-l2key=Angle]').val(getAngle(Coordinates));
	});
	
	if($('#new').lenght >0)
		$('#new').remove();
	if($('#tab_new').lenght >0)
		$('#tab_new').remove();
	if ($('#tab_zones #' + init(_zone.id)).length == 0) {
		$('#tab_zones').append($('<li id="' +init(_zone.id) + '">')
			.append($('<a href="#tab_' + init(_zone.id) + '">')
				.text(_zone.name)));
	}
	var NewMode = $('<div style="margin-right:20px" class="cmd tab-pane tabAttr" data-cmd_id="' +init(_zone.id) + '" id="tab_' +init(_zone.id) + '">')
		.append($('<div class="row">')
				.append($('<div class="form-group">')
					.append($('<label>').text('Angle d\'ensoleillement de la Zone'))
					.append($('<div class="input-group">')
						.append($('<input class="cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="Angle" disabled>'))))
			.append($('<input class="cmdAttr" data-l1key="id"  style="display : none;"/>'))
			.append($('<input class="cmdAttr" data-l1key="logicalId" style="display : none;"/>'))
			.append($('<input class="cmdAttr" data-l1key="name" style="display : none;"/>'))
			.append($('<input class="cmdAttr" data-l1key="type" value="action" style="display : none;"/>'))
			.append($('<input class="cmdAttr" data-l1key="subType" value="other" style="display : none;"/>'))
			.append($('<input class="cmdAttr" data-l1key="configuration" data-l2key="Droit" style="display : none;" />'))
			.append($('<input class="cmdAttr" data-l1key="configuration" data-l2key="Gauche" style="display : none;" />'))
			.append($('<input class="cmdAttr" data-l1key="display" data-l2key="icon" style="display : none;" />'))
			.append($('<input type="checkbox" class="cmdAttr" data-l1key="isHistorized" style="display : none;"/>'))
			.append($('<div class="btn-group pull-right" role="group">')
				.append($('<a class="modeAction btn btn-default btn-sm" data-l1key="chooseIcon">')
					.append($('<i class="fa fa-flag">'))
					.text('{{Modifier Icône}}'))
				.append($('<a class="modeAction btn btn-default btn-sm" data-l1key="removeIcon">')
					.append($('<i class="fa fa-trash">'))
					.text('{{Supprimer l\'icône}}'))
				.append($('<a class="modeAction btn btn-danger btn-sm" data-l1key="removeZone">')
					.append($('<i class="fa fa-minus-circle">'))
					.text('{{Supprimer}}'))))
		.append($('<div class="row">')
					.append($('<form class="form-horizontal">')
						.append($('<legend>')
							.text('{{Ajouter les condition :}}')
							.append($('<a class="btn btn-success btn-xs conditionAttr" data-action="add" style="margin-left: 5px;">')
								.append($('<i class="fa fa-plus-circle">'))
								.text('{{Ajouter Condition}}')))
						.append($('<div class="div_Condition">'))))
		.append($('<div class="row">')
			.append($('<div class="col-lg-6 ActionIn">')
				.append($('<form class="form-horizontal">')
					.append($('<legend>')
						.text('{{Ajouter les actions a mener lorsque le soleil est dans la zone :}}')
						.append($('<a class="btn btn-success btn-xs ActionAttr" data-action="add" style="margin-left: 5px;">')
							.append($('<i class="fa fa-plus-circle">'))
							.text('{{Ajouter Action}}')))
					.append($('<div class="div_action">'))))
			.append($('<div class="col-lg-6 ActionOut">')
				.append($('<form class="form-horizontal">')
					.append($('<legend>')
						.text('{{Ajouter les actions a mener lorsque le soleil n\'est pas dans la zone :}}')
						.append($('<a class="btn btn-success btn-xs ActionAttr" data-action="add" style="margin-left: 5px;">')
							.append($('<i class="fa fa-plus-circle">'))
							.text('{{Ajouter Action}}')))
					.append($('<div class="div_action">')))));
	$('.TabCmdZone').append(NewMode);
	$('.TabCmdZone .cmd[data-cmd_id=' + init(_zone.id)+ ']').setValues(_zone, '.cmdAttr');
	$('.cmd[data-cmd_id=' + init(_zone.id) + ']').find('.cmdAttr[data-l1key=configuration][data-l2key=Droit]').val(JSON.stringify(_zone.configuration.Droit));
	$('.cmd[data-cmd_id=' + init(_zone.id) + ']').find('.cmdAttr[data-l1key=configuration][data-l2key=Gauche]').val(JSON.stringify(_zone.configuration.Gauche));
	$('#tab_zones a').on('click', function (e) {
		e.preventDefault();
		$(this).tab('show');
	});	
	if (typeof(_zone.configuration.condition) !== 'undefined') {
		for(var index in _zone.configuration.condition) { 
			if( (typeof _zone.configuration.condition[index] === "object") && (_zone.configuration.condition[index] !== null) )
				addCondition(_zone.configuration.condition[index],  '{{Condition}}',$('.cmd[data-cmd_id=' + init(_zone.id)+ '] ').find('.div_Condition'));
		}
	}
	if (typeof(_zone.configuration.action) !== 'undefined') {
		if (typeof(_zone.configuration.action.in) !== 'undefined') {
			for(var index in _zone.configuration.action.in) { 
				if( (typeof _zone.configuration.action.in[index] === "object") && (_zone.configuration.action.in[index] !== null) )
					addAction(_zone.configuration.action.in[index],  '{{Action}}',$('.cmd[data-cmd_id=' + init(_zone.id)+ ']  .ActionIn').find('.div_action'));
			}
		}
		if (typeof(_zone.configuration.action.out) !== 'undefined') {
			for(var index in _zone.configuration.action.out) { 
				if( (typeof _zone.configuration.action.out[index] === "object") && (_zone.configuration.action.out[index] !== null) )
					addAction(_zone.configuration.action.out[index],  '{{Action}}',$('.cmd[data-cmd_id=' + init(_zone.id)+ ']  .ActionOut').find('.div_action'));
			}
		}
	}	
}
function addCondition(_action, _name, _el) {
	if (!isset(_action)) {
		_action = {};
	}
	if (!isset(_action.options)) {
		_action.options = {};
	}
    	var div = $('<div class="form-group ConditionGroup">')
  		.append($('<label class="col-lg-1 control-label">')
			.text(_name))
   		.append($('<div class="col-lg-1">')
    			.append($('<a class="btn btn-warning btn-sm listCmdCondition" >')
				.append($('<i class="fa fa-list-alt">'))))
		.append($('<div class="col-lg-3">')
			.append($('<input class="expressionAttr form-control input-sm cmdCondition" data-l1key="expression" />')))
 		.append($('<div class="col-lg-1">')
  			.append($('<i class="fa fa-minus-circle pull-left cursor conditionAttr" data-action="remove">')));
        _el.append(div);
        _el.find('.ConditionGroup:last').setValues(_action, '.expressionAttr');
  
}
function addAction(_action, _name, _el) {
	if (!isset(_action)) {
		_action = {};
	}
	if (!isset(_action.options)) {
		_action.options = {};
	}
    	var div = $('<div class="form-group ActionGroup">')
  		.append($('<label class="col-lg-1 control-label">')
			.text(_name))
   		.append($('<div class="col-lg-1">')
    			.append($('<a class="btn btn-warning btn-sm listCmdAction" >')
				.append($('<i class="fa fa-list-alt">'))))
		.append($('<div class="col-lg-3">')
			.append($('<input class="expressionAttr form-control input-sm cmdAction" data-l1key="cmd" />')))
		.append($('<div class="col-lg-6 actionOptions">')
    			.append($(jeedom.cmd.displayActionOption(init(_action.cmd, ''), _action.options))))
 		.append($('<div class="col-lg-1">')
  			.append($('<i class="fa fa-minus-circle pull-left cursor ActionAttr" data-action="remove">')));
        _el.append(div);
        _el.find('.ActionGroup:last').setValues(_action, '.expressionAttr');
  
}
$('#tab_zones a').click(function(e) {
    e.preventDefault();
    $(this).tab('show');
});
$('body').on('focusout','.expressionAttr[data-l1key=cmd]', function (event) {
    var expression = $(this).closest('.ActionGroup').getValues('.expressionAttr');
    var el = $(this);
    jeedom.cmd.displayActionOption($(this).value(), init(expression[0].options), function (html) {
        el.closest('.ActionGroup').find('.actionOptions').html(html);
    })
});
$('body').on('click','.conditionAttr[data-action=add]',function(){
	addCondition({},  '{{Condition}}',$(this).closest('.form-horizontal').find('.div_Condition'));
});
$('body').on('click','.conditionAttr[data-action=remove]',function(){
	$(this).closest('.ConditionGroup').remove();
});
$('body').on('click','.listCmdCondition',function(){
	var el = $(this).closest('.form-group').find('.expressionAttr[data-l1key=expression]');	
	jeedom.cmd.getSelectModal({cmd: {type: 'info'}}, function (result) {
		var message = 'Aucun choix possible';
		if(result.cmd.subType == 'numeric'){
			message = '<div class="row">  ' +
			'<div class="col-md-12"> ' +
			'<form class="form-horizontal" onsubmit="return false;"> ' +
			'<div class="form-group"> ' +
			'<label class="col-xs-5 control-label" >'+result.human+' {{est}}</label>' +
			'             <div class="col-xs-3">' +
			'                <select class="conditionAttr form-control" data-l1key="operator">' +
			'                    <option value="==">{{égal}}</option>' +
			'                  <option value=">">{{supérieur}}</option>' +
			'                  <option value="<">{{inférieur}}</option>' +
			'                 <option value="!=">{{différent}}</option>' +
			'            </select>' +
			'       </div>' +
			'      <div class="col-xs-4">' +
			'         <input type="number" class="conditionAttr form-control" data-l1key="operande" />' +
			'    </div>' +
			'</div>' +
			'<div class="form-group"> ' +
			'<label class="col-xs-5 control-label" >{{Ensuite}}</label>' +
			'             <div class="col-xs-3">' +
			'                <select class="conditionAttr form-control" data-l1key="next">' +
			'                    <option value="">rien</option>' +
			'                  <option value="ET">{{et}}</option>' +
			'                  <option value="OU">{{ou}}</option>' +
			'            </select>' +
			'       </div>' +
			'</div>' +
			'</div> </div>' +
			'</form> </div>  </div>';
		}
		if(result.cmd.subType == 'string'){
			message = '<div class="row">  ' +
			'<div class="col-md-12"> ' +
			'<form class="form-horizontal" onsubmit="return false;"> ' +
			'<div class="form-group"> ' +
			'<label class="col-xs-5 control-label" >'+result.human+' {{est}}</label>' +
			'             <div class="col-xs-3">' +
			'                <select class="conditionAttr form-control" data-l1key="operator">' +
			'                    <option value="==">{{égale}}</option>' +
			'                  <option value="matches">{{contient}}</option>' +
			'                 <option value="!=">{{différent}}</option>' +
			'            </select>' +
			'       </div>' +
			'      <div class="col-xs-4">' +
			'         <input class="conditionAttr form-control" data-l1key="operande" />' +
			'    </div>' +
			'</div>' +
			'<div class="form-group"> ' +
			'<label class="col-xs-5 control-label" >{{Ensuite}}</label>' +
			'             <div class="col-xs-3">' +
			'                <select class="conditionAttr form-control" data-l1key="next">' +
			'                    <option value="">{{rien}}</option>' +
			'                  <option value="ET">{{et}}</option>' +
			'                  <option value="OU">{{ou}}</option>' +
			'            </select>' +
			'       </div>' +
			'</div>' +
			'</div> </div>' +
			'</form> </div>  </div>';
		}
		if(result.cmd.subType == 'binary'){
			message = '<div class="row">  ' +
			'<div class="col-md-12"> ' +
			'<form class="form-horizontal" onsubmit="return false;"> ' +
			'<div class="form-group"> ' +
			'<label class="col-xs-5 control-label" >'+result.human+' {{est}}</label>' +
			'            <div class="col-xs-7">' +
			'                 <input class="conditionAttr" data-l1key="operator" value="==" style="display : none;" />' +
			'                  <select class="conditionAttr form-control" data-l1key="operande">' +
			'                       <option value="1">{{Ouvert}}</option>' +
			'                       <option value="0">{{Fermé}}</option>' +
			'                       <option value="1">{{Allumé}}</option>' +
			'                       <option value="0">{{Eteint}}</option>' +
			'                       <option value="1">{{Déclenché}}</option>' +
			'                       <option value="0">{{Au repos}}</option>' +
			'                       </select>' +
			'                    </div>' +
			'                 </div>' +
			'<div class="form-group"> ' +
			'<label class="col-xs-5 control-label" >{{Ensuite}}</label>' +
			'             <div class="col-xs-3">' +
			'                <select class="conditionAttr form-control" data-l1key="next">' +
			'                  <option value="">{{rien}}</option>' +
			'                  <option value="ET">{{et}}</option>' +
			'                  <option value="OU">{{ou}}</option>' +
			'            </select>' +
			'       </div>' +
			'</div>' +
			'</div> </div>' +
			'</form> </div>  </div>';
		}

		bootbox.dialog({
			title: "{{Ajout d'une nouvelle condition}}",
			message: message,
			buttons: {
				"Ne rien mettre": {
					className: "btn-default",
					callback: function () {
						el.atCaret('insert', result.human);
					}
				},
				success: {
					label: "Valider",
					className: "btn-primary",
					callback: function () {
    						var condition = result.human;
						condition += ' ' + $('.conditionAttr[data-l1key=operator]').value();
						if(result.cmd.subType == 'string'){
							if($('.conditionAttr[data-l1key=operator]').value() == 'matches'){
								condition += ' "/' + $('.conditionAttr[data-l1key=operande]').value()+'/"';
							}else{
								condition += ' "' + $('.conditionAttr[data-l1key=operande]').value()+'"';
							}
						}else{
							condition += ' ' + $('.conditionAttr[data-l1key=operande]').value();
						}
						condition += ' ' + $('.conditionAttr[data-l1key=next]').value()+' ';
						el.atCaret('insert', condition);
						if($('.conditionAttr[data-l1key=next]').value() != ''){
							el.click();
						}
					}
				},
			}
		});
	});
});
$('body').on('click','.ActionAttr[data-action=add]',function(){
	addAction({},  '{{Action}}',$(this).closest('.form-horizontal').find('.div_action'));
});
$('body').on('click','.ActionAttr[data-action=remove]', function () {
	$(this).closest('.ActionGroup').remove();
});
$('body').on('click','.modeAction[data-l1key=removeIcon]', function () {
	var zoneId = $(this).closest('.tabAttr').attr("id").replace('tab_','');
	$('#' + zoneId ).find('.icon').parent().remove();
	$(this).closest('.cmd').find('.cmdAttr[data-l1key=display][data-l2key=icon]').val('');
});
$('body').on('click','.modeAction[data-l1key=chooseIcon]', function () {
	var zoneId = $(this).closest('.tabAttr').attr("id").replace('tab_','');
	var _this = this;
   	chooseIcon(function (_icon) {
		$('#' + zoneId + ' a').prepend(_icon);
		$(_this).closest('.cmd').find('.cmdAttr[data-l1key=display][data-l2key=icon]').val('');
    	});
});
$('body').on('click','.modeAction[data-l1key=removeZone]', function () {
	var zoneId = $(this).closest('.tabAttr').attr("id").replace('tab_','');
	$('#' + zoneId).parent().remove();
	$(this).closest('.cmd').remove();
});
$("body").on('click', ".listCmdAction", function() {
    	var el = $(this).closest('.form-group').find('.expressionAttr[data-l1key=cmd]');
    	jeedom.cmd.getSelectModal({cmd: {type: 'action'}}, function(result) {
		el.value(result.human);
        	jeedom.cmd.displayActionOption(el.value(), '', function(html) {
			el.closest('.form-group').find('.actionOptions').html(html);
        	});
    	});
});
$('body').on( 'click','.bt_selectCmdExpression', function() {
	var _this=this;
	jeedom.cmd.getSelectModal({cmd: {type: 'info'},eqLogic: {eqType_name : ''}}, function (result) {
		$(_this).closest('.input-group').find('.cmdAttr').val(result.human);
	});
});  
