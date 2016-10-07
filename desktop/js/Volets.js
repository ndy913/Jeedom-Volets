var map;
var coordinate;
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
				coordinate=data.result.geoloc.configuration.coordinate.split(",");
				map = new google.maps.Map(document.getElementById('map'), {
					center: {lat:parseFloat(coordinate[0]), lng:parseFloat(coordinate[1])},
					mapTypeId: 'satellite',
					scrollwheel: true,
					zoom: 20
				});
			}
		}
	});
});
function PolyLigneNord(Coordinate) {
	var coord=[
		{lat: parseFloat(Coordinate[0]), lng: parseFloat(Coordinate[1])},
		{lat: parseFloat(Coordinate[0])+ (10 / 3600) , lng: parseFloat(Coordinate[1])}
	];
	return coord
}
function PolyLignePerpendiculaire(Coordinate) {
	var coord=[
		{lat: parseFloat(Coordinate[0]), lng: parseFloat(Coordinate[1])},
		{lat: parseFloat(Coordinate[0])+ (10 / 3600) , lng: parseFloat(Coordinate[1])+ (10 / 3600)}
	];
	return coord
}
function PolyLigneDroitZone(Coordinate) {
	var coord=[
		{lat: parseFloat(Coordinate[0]), lng: parseFloat(Coordinate[1])- (10 / 3600)},
		{lat: parseFloat(Coordinate[0]) , lng: parseFloat(Coordinate[1])+ (10 / 3600)}
	];
	return coord
}
function TracePolyLigne(Coordinate) {
	new google.maps.Polyline({
		path: PolyLigneNord(Coordinate),
		geodesic: true,
		strokeColor: '#FF0000',
		strokeOpacity: 1.0,
		map: map,
		strokeWeight: 2
	});
	new google.maps.Polyline({
		path: PolyLigneDroitZone(Coordinate),
		geodesic: true,
		strokeColor: '#40A497',
		strokeOpacity: 1.0,
		map: map,
		strokeWeight: 2
	});
	new google.maps.Polyline({
		path: PolyLignePerpendiculaire(Coordinate),
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
	else */
	alert(_cmd.logicalId);
		myLatLng=coordinate;
	var position=new google.maps.Marker({
		position: {lat: parseFloat(myLatLng[0]), lng: parseFloat(myLatLng[1])},
		map: map,
		draggable:true,
		title: _cmd.name
	  });
	TracePolyLigne(myLatLng);
	google.maps.event.addListener(position,'drag', function(event) {
		myLatLng=event.latLng.toString().replace("(", "").replace(")", "");
		TracePolyLigne(myLatLng.split(","));
		$('.cmd[data-cmd_id=' + init(_cmd.id) + ']').find('.cmdAttr[data-l1key=logicalId]').val(myLatLng);
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
