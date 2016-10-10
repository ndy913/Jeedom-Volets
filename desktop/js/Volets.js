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
/*	var tr = '<tr class="cmd" data-cmd_id="' + init(_cmd.id) + '">';
	.append($('<td class="name">';
	.append($('<input class="cmdAttr form-control input-sm" data-l1key="id" style="display : none;">';
	.append($('<input class="cmdAttr form-control input-sm" data-l1key="logicalId">';
	.append($('<input class="cmdAttr form-control input-sm" data-l1key="name">   ';
	.append($('</td>';

	.append($('<td class="type" type="' + init(_cmd.type) + '">' + jeedom.cmd.availableType();
	.append($('<span class="subType" subType="' + init(_cmd.subType) + '"></span></td>';
	.append($('<td>';
	.append($('<span><input type="checkbox" data-size="mini" data-label-text="{{Historiser}}" class="cmdAttr bootstrapSwitch" data-l1key="isHistorized" /></span> ';
	.append($('</td>';
	.append($('<td>';
	if (is_numeric(_cmd.id)) {
		.append($('<a class="btn btn-default btn-xs cmdAction expertModeVisible" data-action="configure"><i class="fa fa-cogs"></i></a> ';
		.append($('<a class="btn btn-default btn-xs cmdAction" data-action="test"><i class="fa fa-rss"></i> {{Tester}}</a>';
	}
	.append($('<i class="fa fa-minus-circle pull-right cmdAction cursor" data-action="remove"></i></td>';
	.append($('</tr>';
	$('#table_cmd tbody').append(tr);
	$('#table_cmd tbody tr:last').setValues(_cmd, '.cmdAttr');
	jeedom.cmd.changeType($('#table_cmd tbody tr:last'), init(_cmd.subType));
	$('#table_cmd tbody tr:last').find('.cmdAttr[data-l1key=logicalId]').val(JSON.stringify(Coordinates));
	*/
}
var liste_zones = {};

$('#tab_zones a').click(function(e) {
    e.preventDefault();
    $(this).tab('show');
});
/**************** Actions Boutons ***********/
$('#bt_addActionPresent').on('click', function() {
    addAction({}, 'action_present', '{{Action}}');
});
$('#bt_addActionExitPresent').on('click', function() {
    addAction({}, 'action_exit_present', '{{Action}}');
});
$('#bt_addActionAbsent').on('click', function() {
    addAction({}, 'action_absent', '{{Action}}');
});
$('#bt_addActionExitAbsent').on('click', function() {
    addAction({}, 'action_exit_absent', '{{Action}}');
});
$('#bt_addActionNuit').on('click', function() {
    addAction({}, 'action_nuit', '{{Action}}');
});
$('#bt_addActionExitNuit').on('click', function() {
    addAction({}, 'action_exit_nuit', '{{Action}}');
});
$('#bt_addActionTravail').on('click', function() {
    addAction({}, 'action_travail', '{{Action}}');
});
$('#bt_addActionExitTravail').on('click', function() {
    addAction({}, 'action_exit_travail', '{{Action}}');
});
$('#bt_addActionSimuON').on('click', function() {
    addAction({}, 'action_simulation_on', '{{Action}}');
});
$('#bt_addActionSimuOFF').on('click', function() {
    addAction({}, 'action_simulation_off', '{{Action}}');
});
$('#tab_add').on('click', function() {
    bootbox.prompt("Nom ?", function (result) {
        if (result !== null && result != '') {
            AddZone({name: result});
        }
    });
});
function AddZone(_zone){
	if (init(_zone.name) == '') {
        return;
    }
	if (init(_zone.icon) == '') {
        // _zone.icon = '<i class="icon fa fa-dot-circle-o"><\/i>';
        _zone.icon = '';
    }
	var zone_without_space = _zone.name.replace(" ","_");
	var zone_with_spaces = 	_zone.name.replace("_"," ");
	console.log(zone_with_spaces);
	
	$('#tab_zones').append($('<li>')
		.append($('<a class="cmdAttr" href="#tab_' + init(_zone.id) + '" data-l1key="name" zone_name="' + zone_without_space+ '">')
			.append($('<span class="cmdAttr" data-l1key="icon">')
				.text(_zone.icon))));

	var NewMode = $('<div style="margin-right:20px" class="tab-pane tabAttr" id="tab_' + zone_without_space + '">')	
		.append($('<br/>'))
		.append($('<div class="btn-group pull-right" role="group">')
			.append($('<a class="modeAction btn btn-default btn-sm" data-l1key="chooseName">')
				.append($('<i class="fa fa-pencil">'))
				.text('{{Modifier le nom}}'))
			.append($('<a class="modeAction btn btn-default btn-sm" data-l1key="chooseIcon">')
				.append($('<i class="fa fa-flag">'))
				.text('{{Modifier Icône}}'))
			.append($('<a class="modeAction btn btn-default btn-sm" data-l1key="removeIcon">')
				.append($('<i class="fa fa-trash">'))
				.text('{{Supprimer l\'icône}}'))
			.append($('<a class="modeAction btn btn-danger btn-sm" data-l1key="removeMode">')
				.append($('<i class="fa fa-minus-circle">'))
				.text('{{Supprimer}}')))
		.append($('<form class="form-horizontal">')
			.append($('<legend>')
				.text('{{Pour être dans ce mode :}}')
				.append($('<a class="btn btn-xs btn-success" id="bt_addCond' + zone_without_space + '" style="margin-left: 5px;">')
					.append($('<i class="fa fa-plus-circle">'))
					.text('{{Ajouter Déclencheur}}')))
			.append($('<div id="div_cond_' + zone_without_space + '">')))
		.append($('<form class="form-horizontal">')
			.append($('<legend>')
				.text('{{Une fois dans ce mode je dois :}}')
				.append($('<a class="btn btn-success btn-xs" id="bt_addAction' + zone_without_space + '" style="margin-left: 5px;">')
					.append($('<i class="fa fa-plus-circle">'))
					.text('{{Ajouter Action}}')))
			.append($('<div id="div_action_' + zone_without_space + '">')))	
		.append($('<form class="form-horizontal">')
			.append($('<legend>')
				.text('{{En quittant ce mode je dois :}}')
				.append($('<a class="btn btn-success btn-xs" id="bt_addActionExit' + zone_without_space + '" style="margin-left: 5px;">')
					.append($('<i class="fa fa-plus-circle">'))
					.text('{{Ajouter Action}}</a>')))
			.append($('<div id="div_action_exit_' + zone_without_space + '">')))	
		.append($('<form class="form-horizontal">')
			.append($('<input class="cmdAttr form-control input-sm" data-l1key="id" style="display : none;">'))
			.append($('<input class="cmdAttr form-control input-sm" data-l1key="logicalId">'))
			.append($('<input class="cmdAttr form-control input-sm" data-l1key="name">'))
			.append($('<input type="checkbox" data-size="mini" data-label-text="{{Historiser}}" class="cmdAttr bootstrapSwitch" data-l1key="isHistorized" />')));
	$('.tab-content').append(NewMode);
	$('#tab_zones a').on('click', function (e) {
		e.preventDefault();
		$(this).tab('show');
	});
	
	$('#tab_zones').find('a[zone_name="'+zone_without_space+'"] i').attr("style","");
	$('#bt_addAction' + zone_without_space).on('click', function() {
		addAction({}, 'action_' + zone_without_space, '{{Action}}');
	});
	
	$('#bt_addActionExit' + zone_without_space).on('click', function() {
		addAction({}, 'action_exit_' + zone_without_space, '{{Action}}');
	});
}
/**************** Commun ***********/
$('body').undelegate('.modeAction[data-l1key=chooseIcon]', 'click').delegate('.modeAction[data-l1key=chooseIcon]', 'click', function () {
    var zone_name = $(this).closest('.tabAttr ').attr("id");
	zone_name = zone_name.substring(4);
	var mode = $("#tab_zones").find("[zone_name="+zone_name+"]");
    chooseIcon(function (_icon) {
		//console.log(_icon);
        mode.find('.cmdAttr[data-l1key=icon]').empty().append(_icon);
    });
});
$('body').undelegate('.modeAction[data-l1key=chooseName]', 'click').delegate('.modeAction[data-l1key=chooseName]', 'click', function () {
    var zone_name = $(this).closest('.tabAttr ').attr("id");
	zone_name = zone_name.substring(4);
	var mode = $("#tab_zones").find("[zone_name="+zone_name+"]");
    
	bootbox.prompt("{{Nouveau nom ?}}", function (result) {
        if (result !== null) {
			var result_with_space = result;
			result = result.replace(' ','_');
            mode.attr("href","#tab_" + result);
            mode.attr("zone_name", result);
			var _icon = mode.find('.cmdAttr[data-l1key=icon]');
			mode.empty().append(_icon).append(" " + result_with_space);
			$('.tab-content').find('#tab_' + zone_name).attr("id","tab_" + result);
			$('.tab-content').find('#div_cond_' + zone_name).attr("id","div_cond_" + result);
			$('.tab-content').find('#div_action_' + zone_name).attr("id","div_action_" + result);
			$('.tab-content').find('#div_action_exit_' + zone_name).attr("id","div_action_exit_" + result);
			
			$('.tab-content').find('#bt_addCond' + zone_name).attr("id","bt_addCond" + result);
			$('.tab-content').find('#bt_addAction' + zone_name).attr("id","bt_addAction" + result);
			$('.tab-content').find('#bt_addActionExit' + zone_name).attr("id","bt_addActionExit" + result);		
			
			$('.tab-content').find('.cond_' + zone_name).attr("class","cond_" + result);
			$('.tab-content').find('.action_' + zone_name).attr("class","action_" + result);
			$('.tab-content').find('.action_exit_' + zone_name).attr("class","action_exit_" + result);	
        }
    });
});
$('body').undelegate('.modeAction[data-l1key=removeIcon]', 'click').delegate('.modeAction[data-l1key=removeIcon]', 'click', function () {
    var zone_name = $(this).closest('.tabAttr ').attr("id");
	zone_name = zone_name.substring(4);
	var mode = $("#tab_zones").find("[zone_name="+zone_name+"]");
    mode.find('.cmdAttr[data-l1key=icon]').empty();
});
$('body').undelegate('.modeAction[data-l1key=removeMode]', 'click').delegate('.modeAction[data-l1key=removeMode]', 'click', function () {
    var zone_name = $(this).closest('.tabAttr ').attr("id");
	bootbox.confirm("Êtes vous sûr ?", function(result) {
		if(result == true){
			$('.tab-content').find("#" + zone_name).remove();
			zone_name = zone_name.substring(4);
			var mode = $("#tab_zones").find("[zone_name="+zone_name+"]");
			mode.remove();
			$('#state_order_list').find('[zone_name="'+zone_name+'"]').remove();	
		}
	}); 
});
$("body").delegate(".listEquipement", 'click', function() {
    var type = $(this).attr('data-type');
    var el = $(this).closest('.' + type).find('.expressionAttr[data-l1key=eqLogic]');
    jeedom.eqLogic.getSelectModal({}, function(result) {
        //console.log(result);
        el.value(result.human);
    });
});
$("body").delegate(".listCmdAction", 'click', function() {
    var type = $(this).attr('data-type');
    var el = $(this).closest('.' + type).find('.expressionAttr[data-l1key=cmd]');
    jeedom.cmd.getSelectModal({cmd: {type: 'action'}}, function(result) {
        el.value(result.human);
        jeedom.cmd.displayActionOption(el.value(), '', function(html) {
            el.closest('.' + type).find('.actionOptions').html(html);
        });
    });
});
$(".eqLogic").delegate(".listCmdInfo", 'click', function () {
    var el = $(this).closest('.form-group').find('.eqLogicAttr');
    jeedom.cmd.getSelectModal({cmd: {type: 'info'}}, function (result) {
        if (el.attr('data-concat') == 1) {
            el.atCaret('insert', result.human);
        } else {
            el.value(result.human);
        }
    });
});
$('body').delegate('.rename', 'click', function () {
    var el = $(this);
    bootbox.prompt("{{Nouveau nom ?}}", function (result) {
        if (result !== null) {
            el.text(result);
            el.closest('.mode').find('.cmdAttr[data-l1key=name]').value(result);
        }
    });
});
$("body").delegate(".listCmdInfo", 'click', function() {
	var type = $(this).attr('data-type');	
	var el = $(this).closest('.' + type).find('.triggerAttr[data-l1key=cmd]');
    jeedom.cmd.getSelectModal({cmd: {type: 'info', subtype: 'binary'}}, function(result) {
        el.value(result.human);
    });
});
$("body").delegate('.bt_removeAction', 'click', function() {
    var type = $(this).attr('data-type');
    $(this).closest('.' + type).remove();
});
$('body').delegate('.cmdAction.expressionAttr[data-l1key=cmd]', 'focusout', function (event) {
    var type = $(this).attr('data-type')
    var expression = $(this).closest('.' + type).getValues('.expressionAttr');
    var el = $(this);
    jeedom.cmd.displayActionOption($(this).value(), init(expression[0].options), function (html) {
        el.closest('.' + type).find('.actionOptions').html(html);
    })
});
function addAction(_action, _type, _name, _el) {
    if (!isset(_action)) {
        _action = {};
    }
    if (!isset(_action.options)) {
        _action.options = {};
    }
    var input = '';
    var button = 'btn-default';
    //input = 'has-warning';
    button = 'btn-warning';
    
    var div = '<div class="' + _type + '">';
    div += '<div class="form-group ">';
    div += '<label class="col-lg-1 control-label">' + _name + '</label>';
    div += '<div class="col-lg-1">';
    div += '<a class="btn ' + button + ' btn-sm listCmdAction" data-type="' + _type + '"><i class="fa fa-list-alt"></i></a>';
    div += '</div>';
    div += '<div class="col-lg-3 ' + input + '">';
    div += '<input class="expressionAttr form-control input-sm cmdAction" data-l1key="cmd" data-type="' + _type + '" />';
    div += '</div>';
    div += '<div class="col-lg-6 actionOptions">';
    div += jeedom.cmd.displayActionOption(init(_action.cmd, ''), _action.options);
    div += '</div>';
    div += '<div class="col-lg-1">';
    div += '<i class="fa fa-minus-circle pull-left cursor bt_removeAction" data-type="' + _type + '"></i>';
    div += '</div>';
    div += '</div>';
    if (isset(_el)) {
        _el.find('.div_' + _type).append(div);
        _el.find('.' + _type + ':last').setValues(_action, '.expressionAttr');
    } else {
        $('#div_' + _type).append(div);
        $('#div_' + _type + ' .' + _type + ':last').setValues(_action, '.expressionAttr');
    }
}
