var map;
var Center= new Object();
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
				map = new google.maps.Map(document.getElementById('map'), {
					center: Center,
					mapTypeId: 'satellite',
					scrollwheel: true,
					zoom: 20
				});
			}
		}
	});
});
function TraceDirection(Coordinates) {
	var milieu=new Array();
	milieu['lat']=(Coordinates.Center.lat+Coordinates.Position.lat)/2;
	milieu['lng']=(Coordinates.Center.lng+Coordinates.Position.lng)/2;
	var perpendiculaire=new Array();
	perpendiculaire['lat']=milieu['lat']+Math.cos(90);
	perpendiculaire['lng']=milieu['lng']+Math.cos(90);
	return [milieu,perpendiculaire];
}
function TracePolyLigne(Coordinates) {
	/*new google.maps.Polyline({
		path: TraceDirection(Coordinates),
		geodesic: true,
		strokeColor: '#FF0000',
		strokeOpacity: 1.0,
		map: map,
		strokeWeight: 2
	});*/
	new google.maps.Polyline({
		path: [Coordinates.Center,Coordinates.Position],
		geodesic: true,
		strokeColor: '#40A497',
		strokeOpacity: 1.0,
		map: map,
		strokeWeight: 2
	});
}
function saveEqLogic(_eqLogic) {
	var state_order = '';
    if (!isset(_eqLogic.configuration)) {
        _eqLogic.configuration = {};
    }	
	if (typeof( _eqLogic.cmd) !== 'undefined') {
		for(var index in  _eqLogic.cmd) { 
			 _eqLogic.cmd[index].configuration.action.in=$('#tab_' +init(_eqLogic.cmd[index].id+' .ActionIn')).getValues('.expressionAttr');
			 _eqLogic.cmd[index].configuration.action.out=$('#tab_' +init(_eqLogic.cmd[index].id+' .ActionOut')).getValues('.expressionAttr');
		}
	}
    return _eqLogic;
}
function addCmdToTable(_cmd) {
	if (!isset(_cmd)) {
	var _cmd = {configuration: {}};
	}
	var Coordinates;
	if (typeof(_cmd.logicalId) !== 'undefined' && _cmd.logicalId != "") {
		Coordinates = _cmd.logicalId; 
		Coordinates.Center.lat=parseFloat(Coordinates.Center.lat);
		Coordinates.Center.lng=parseFloat(Coordinates.Center.lng);
		Coordinates.Position.lat=parseFloat(Coordinates.Position.lat);
	      	Coordinates.Position.lng=parseFloat(Coordinates.Position.lng);
	}else {
		Coordinates= new Object();
		Coordinates.Center=Center;
		Coordinates.Position= new Object();
		Coordinates.Position.lat=Coordinates.Center.lat;
		Coordinates.Position.lng=Coordinates.Center.lng+ (1 / 3600);
	}
	var position=new google.maps.Marker({
		position: Coordinates.Center,
		map: map,
		draggable:true,
		title: _cmd.name
	  });
	var angle=new google.maps.Marker({
		position:Coordinates.Position,
		map: map,
		draggable:true,
		title: _cmd.name
	  });
	TracePolyLigne(Coordinates);
	google.maps.event.addListener(position,'drag', function(event) {
		Coordinates.Center=event.latLng;
		TracePolyLigne(Coordinates);
		$('.cmd[data-cmd_id=' + init(_cmd.id) + ']').find('.cmdAttr[data-l1key=logicalId]').val(JSON.stringify(Coordinates));
	});
	google.maps.event.addListener(angle,'drag', function(event) {
		Coordinates.Position=event.latLng;
		TracePolyLigne(Coordinates);
		$('.cmd[data-cmd_id=' + init(_cmd.id) + ']').find('.cmdAttr[data-l1key=logicalId]').val(JSON.stringify(Coordinates));
	});
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
	
	$('#tab_zones').append($('<li>')
		.append($('<a href="#tab_' + init(_zone.id) + '">')
			.append($(_zone.icon))
			.text(_zone.name)));

	var NewMode = $('<div style="margin-right:20px" class="cmd tab-pane tabAttr" id="tab_' +init(_zone.id) + '">')	
		.append($('<div class="row">')
			.append($('<input class="cmdAttr" data-l1key="id"  style="display : none;"/>'))
			.append($('<input class="cmdAttr" data-l1key="logicalId" style="display : none;"/>'))
			.append($('<input class="cmdAttr" data-l1key="name" style="display : none;"/>'))
			.append($('<input class="cmdAttr" data-l1key="type" style="display : none;"/>'))
			.append($('<input class="cmdAttr" data-l1key="subType" style="display : none;"/>'))
			.append($('<input class="cmdAttr" data-l1key="display" data-l2key="icon" style="display : none;" />'))
			.append($('<input class="cmdAttr" data-l1key="configuration" data-l2key="action" data-l3key="in" style="display : none;" />'))
			.append($('<input class="cmdAttr" data-l1key="configuration" data-l2key="action" data-l3key="out" style="display : none;" />'))
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
					.text('{{Supprimer}}')))
		.append($('<div>')
			.append($('<label>').text('Valeur Température de la zone'))
			.append($('<div class="input-group">')
				.append($('<span class="input-group-btn">')
					.append($('<sup class="btn  btn-sm">')
						.append($('<i class="fa fa-question-circle tooltips" style="font-size : 1em;color:grey;" title="Séléctioner l\'objet de température de la zone">'))))
				.append($('<input class="cmdAttr form-control input-sm"  data-l1key="configuration" data-l2key="TempObjet">'))
				.append($('<span class="input-group-btn">')
					.append($('<a class="btn btn-success btn-sm bt_selectCmdExpression" id="value">')
						.append($('<i class="fa fa-list-alt">'))))))
		.append($('<div>')
			.append($('<label>').text('Seuil de température de la zone'))
			.append($('<div class="input-group">')
				.append($('<span class="input-group-btn">')
					.append($('<sup class="btn  btn-sm">')
						.append($('<i class="fa fa-question-circle tooltips" style="font-size : 1em;color:grey;" title="Choisissez un objet jeedom contenant la valeur de votre commande">'))))
				.append($('<input class="cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="SeuilTemp">')))))
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
	$('.tab-content').append(NewMode);
	$('.tab-content').find('#tab_' +init(_zone.id)).setValues(_zone, '.cmdAttr');
	$('#tab_zones a').on('click', function (e) {
		e.preventDefault();
		$(this).tab('show');
	});	
	if (typeof(_zone.configuration.action) !== 'undefined') {
		if (typeof(_zone.configuration.action.in) !== 'undefined') {
			for(var index in _zone.configuration.action.in) { 
				addAction(_zone.configuration.action.in[index],  '{{Action}}',$('.tab-content').find('#tab_' +init(_zone.id)+' .ActionIn').find('.div_action'));
			}
		}
		if (typeof(_zone.configuration.action.out) !== 'undefined') {
			for(var index in _zone.configuration.action.out) { 
				addAction(_zone.configuration.action.out[index],  '{{Action}}',$('.tab-content').find('#tab_' +init(_zone.id)+' .ActionOut').find('.div_action'));
			}
		}
	}
}
function addAction(_action, _name, _el) {
	if (!isset(_action)) {
		_action = {};
	}
	if (!isset(_action.options)) {
		_action.options = {};
	}
    	var div = $('<div class="form-group">')
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
  			.append($('<i class="fa fa-minus-circle pull-left cursor bt_removeAction">')));
        _el.append(div);
        _el.setValues(_action, '.expressionAttr');
  
}
$('#tab_zones a').click(function(e) {
    e.preventDefault();
    $(this).tab('show');
});

$('body').on('click','.ActionAttr[data-action=add]',function(){
	addAction({},  '{{Action}}',$(this).closest('.form-horizontal').find('.div_action'));
});
$('body').on('click','.modeAction[data-l1key=removeIcon]', function () {
	var zoneId = $(this).closest('.tabAttr').attr("id");
	$("#tab_modes").find("[href="+zoneId+"]").find('.icon').parent().remove();
	$(this).closest('.cmd').find('.cmdAttr[data-l1key=display][data-l2key=icon]').val('');
});
$('body').on('click','.modeAction[data-l1key=chooseIcon]', function () {
	var zoneId = $(this).closest('.tabAttr').attr("id");
	var _this = this;
   	chooseIcon(function (_icon) {
		$("#tab_modes").find("[href="+zoneId+"]").empty().append(_icon);
		$(_this).closest('.cmd').find('.cmdAttr[data-l1key=display][data-l2key=icon]').val('');
    	});
});
$('body').on('click','.modeAction[data-l1key=removeZone]', function () {
	var zoneId = $(this).closest('.tabAttr').attr("id");
	$("#tab_modes").find("[href="+zoneId+"]").remove();
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
