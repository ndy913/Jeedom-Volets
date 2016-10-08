var map;
var Coordinates= new Array();
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
				Coordinates.Center= new Array();
				Coordinates.Center.lat=parseFloat(center[0]);
				Coordinates.Center.lng=parseFloat(center[1]);
			map = new google.maps.Map(document.getElementById('map'), {
				center: Coordinates.Center,
				mapTypeId: 'satellite',
				scrollwheel: true,
				zoom: 20
			});
			}
		}
	});
});
function PolyLignePerpendiculaire(Coordinate) {
	var LatPer=parseFloat(Coordinate.Center.lat)+((parseFloat(Coordinate.Center.lat)-parseFloat(Coordinate.Position.lat))/2);
	var LngPer=parseFloat(Coordinate.Center.lng)+((parseFloat(Coordinate.Center.lng)-parseFloat(Coordinate.Position.lng))/2);
	var coord=[
		Coordinate.Center,
		{lat: LatPer , lng: LngPer}
	];
	return coord
}
function TracePolyLigne(Coordinate) {
	new google.maps.Polyline({
		path: PolyLignePerpendiculaire(Coordinate),
		geodesic: true,
		strokeColor: '#FF0000',
		strokeOpacity: 1.0,
		map: map,
		strokeWeight: 2
	});
	new google.maps.Polyline({
		path: Coordinates,
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
	var myLatLng;
	/*if (typeof(_cmd.logicalId) !== 'undefined' && _cmd.logicalId != "") 
		myLatLng = _cmd.logicalId.split(","); 
	else {*/
		Coordinates.Position= new Array();
		Coordinates.Position.lat=parseFloat(Coordinates.Center.lat);
		Coordinates.Position.lng=parseFloat(Coordinates.Center.lng)+ (1 / 3600);
		$('.cmd[data-cmd_id=' + init(_cmd.id) + ']').find('.cmdAttr[data-l1key=logicalId]').val(myLatLng);
	//}
	
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
	TracePolyLigne(myLatLng);
	google.maps.event.addListener(position,'drag', function(event) {
		/*var newLatLng=event.latLng.toString().replace("(", "").replace(")", "");
		var newCoord=newLatLng.split(",");
		myLatLng[0]=newCoord[0];
		myLatLng[1]=newCoord[1];*/
		Coordinates.Center=event.latLng;
		TracePolyLigne(Coordinates);
		$('.cmd[data-cmd_id=' + init(_cmd.id) + ']').find('.cmdAttr[data-l1key=logicalId]').val(Coordinates);
	});
	google.maps.event.addListener(angle,'drag', function(event) {
		/*var newLatLng=event.latLng.toString().replace("(", "").replace(")", "");
		var newCoord=newLatLng.split(",");
		myLatLng[2]=newCoord[0];
		myLatLng[3]=newCoord[1];*/
		
		Coordinates.Position=event.latLng;
		TracePolyLigne(Coordinates);
		$('.cmd[data-cmd_id=' + init(_cmd.id) + ']').find('.cmdAttr[data-l1key=logicalId]').val(Coordinates);
	});
    var tr = '<tr class="cmd" data-cmd_id="' + init(_cmd.id) + '">';
    tr += '<td class="name">';
	tr += '<input class="cmdAttr form-control input-sm" data-l1key="id" style="display : none;">';
	tr += '<input class="cmdAttr form-control input-sm" data-l1key="logicalId">';
	tr += '<input class="cmdAttr form-control input-sm" data-l1key="name">   ';
	tr += '</td>';
	
    tr += '<td class="type" type="' + init(_cmd.type) + '">' + jeedom.cmd.availableType();
    tr += '<span class="subType" subType="' + init(_cmd.subType) + '"></span></td>';
    tr += '<td>';
    tr += '<span><input type="checkbox" data-size="mini" data-label-text="{{Historiser}}" class="cmdAttr bootstrapSwitch" data-l1key="isHistorized" /></span> ';
    tr += '</td>';
    tr += '<td>';
     if (is_numeric(_cmd.id)) {
        tr += '<a class="btn btn-default btn-xs cmdAction expertModeVisible" data-action="configure"><i class="fa fa-cogs"></i></a> ';
        tr += '<a class="btn btn-default btn-xs cmdAction" data-action="test"><i class="fa fa-rss"></i> {{Tester}}</a>';
    }
    tr += '<i class="fa fa-minus-circle pull-right cmdAction cursor" data-action="remove"></i></td>';
    tr += '</tr>';
    $('#table_cmd tbody').append(tr);
    $('#table_cmd tbody tr:last').setValues(_cmd, '.cmdAttr');
    jeedom.cmd.changeType($('#table_cmd tbody tr:last'), init(_cmd.subType));
}
