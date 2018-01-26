<select id="layer-select">
  <option value="Aerial">Aerial</option>
  <option value="AerialWithLabels" selected>Aerial with labels</option>
  <option value="Road">Road (static)</option>
  <option value="RoadOnDemand">Road (dynamic)</option>
  <option value="collinsBart">Collins Bart</option>
  <option value="ordnanceSurvey">Ordnance Survey</option>
</select>
<div id="MyMap" style="width:800px;height:600px;margin:auto;"></div>
<script>
var map;
var layers = [];
var i, ii;
var styles = [
	'Road',
	'RoadOnDemand',
	'Aerial',
	'AerialWithLabels',
	'collinsBart',
	'ordnanceSurvey'
];
var DroitLatLng=new Object();
var CentreLatLng=new Object();
var GaucheLatLng=new Object();
$('#MyMap').html('');
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
				$('#div_alert').showAlert({message: 'Aucun message reçu', level: 'error'});
			if (typeof(data.result.geoloc) !== 'undefined') {
				//var center=data.result.geoloc.configuration.coordinate.split(",");
				var center=data.result.geoloc.split(",");
				CentreLatLng.lat=parseFloat(center[0]);
				CentreLatLng.lng=parseFloat(center[1]);
				var view = new ol.View({
					center: ol.proj.fromLonLat([CentreLatLng.lng,CentreLatLng.lat]),
					zoom: 10
    				});
				if(BingAPIKey != ""){
						for (i = 0, ii = styles.length; i < ii; ++i) {
						layers.push(new ol.layer.Tile({
							visible: false,
							preload: Infinity,
							source: new ol.source.BingMaps({
								key: BingAPIKey,
								imagerySet: styles[i]
							})
						}));
					}
					map = new ol.Map({
						layers: layers,
						loadTilesWhileInteracting: true,
						target: 'MyMap',
						view: view
					});
					layers[3].setVisible(styles[3]);
				}else{
					$('#layer-select').hide();
					map =new ol.Map({
						view: view,
						layers: [
							new ol.layer.Tile({
								source: new ol.source.OSM()
							})
						],
						target: 'MyMap'
					});
				}
				/*var geolocation = new ol.Geolocation({projection: view.getProjection()});
        			geolocation.setTracking(true);
				geolocation.on('change', function() {
					//geolocation.getAccuracy() + ' [m]';
					alert(geolocation.getAltitude() + ' [m]');
					//geolocation.getAltitudeAccuracy() + ' [m]';
					//geolocation.getHeading() + ' [rad]';
					//geolocation.getSpeed() + ' [m/s]';
				});*/
			}
		}
	});
  
$('body').on('change','#layer-select',function(){
        var style = $(this).val();
        for (var i = 0, ii = layers.length; i < ii; ++i) {
          layers[i].setVisible(styles[i] === style);
        }
});

function TraceMapZone(_zone){
	DroitLatLng.lat=CentreLatLng.lat;
	DroitLatLng.lng=CentreLatLng.lng- (1 / 3600);
	GaucheLatLng.lat=CentreLatLng.lat;
	GaucheLatLng.lng=CentreLatLng.lng+ (1 / 3600);
	if (typeof(_zone.configuration.Droite) !== 'undefined' && _zone.configuration.Droite != "") {
		if (typeof(_zone.configuration.Droite.lat) !== 'undefined' && _zone.configuration.Droite.lat != "" ) 
			DroitLatLng.lat=parseFloat(_zone.configuration.Droite.lat);
		if (typeof(_zone.configuration.Droite.lng) !== 'undefined' && _zone.configuration.Droite.lng != "" ) 
			DroitLatLng.lng=parseFloat(_zone.configuration.Droite.lng);
	}
	if (typeof(_zone.configuration.Gauche) !== 'undefined' && _zone.configuration.Gauche != "") {
		if (typeof(_zone.configuration.Gauche.lat) !== 'undefined' && _zone.configuration.Gauche.lat != "" ) 
			GaucheLatLng.lat=parseFloat(_zone.configuration.Gauche.lat);
		if (typeof(_zone.configuration.Gauche.lng) !== 'undefined' && _zone.configuration.Gauche.lng != "" ) 
			GaucheLatLng.lng=parseFloat(_zone.configuration.Gauche.lng);
	}
	if (typeof(_zone.configuration.Centre) !== 'undefined' && _zone.configuration.Centre != "") {
		if (typeof(_zone.configuration.Centre.lat) !== 'undefined' && _zone.configuration.Centre.lat != "" ) 
			CentreLatLng.lat=parseFloat(_zone.configuration.Centre.lat);
		if (typeof(_zone.configuration.Centre.lng) !== 'undefined' && _zone.configuration.Centre.lng != "" )
			CentreLatLng.lng=parseFloat(_zone.configuration.Centre.lng);
	}
	
	$('.eqLogicAttr[data-l1key=configuration][data-l2key=AngleDroite]').val(getAngle(CentreLatLng,DroitLatLng));
	$('.eqLogicAttr[data-l1key=configuration][data-l2key=AngleGauche]').val(getAngle(CentreLatLng,GaucheLatLng));
	var features = [];
	var PolylineDroite = new ol.geom.Polygon([[[CentreLatLng.lng,CentreLatLng.lat], [DroitLatLng.lng,DroitLatLng.lat]]]);
	PolylineDroite.transform('EPSG:4326', 'EPSG:3857');
	features.push(new ol.Feature(PolylineDroite));
	var PolylineGauche = new ol.geom.Polygon([[[CentreLatLng.lng,CentreLatLng.lat], [GaucheLatLng.lng,GaucheLatLng.lat]]]);
	PolylineGauche.transform('EPSG:4326', 'EPSG:3857');
	features.push(new ol.Feature(PolylineGauche));
	var Droit = new ol.Feature({
		geometry: new ol.geom.Point(ol.proj.transform([DroitLatLng.lng,DroitLatLng.lat], 'EPSG:4326', 'EPSG:3857'))
	});
	Droit.setStyle([  
		new ol.style.Style({      
			image: new ol.style.Circle({
     				radius: 5,
        			stroke: new ol.style.Stroke({
          				color: '#000'
				}),
				fill: new ol.style.Fill({
					color: 'rgba(255,255,255,0.4)'
				}),
      			}),
			text: new ol.style.Text({
				text: _zone.name + " - Droite vue extérieure",
				offsetY: -25,
				fill: new ol.style.Fill({
					color: '#fff'
				})
			})
		})
	]);
	map.addInteraction(new ol.interaction.Modify({
		features: new ol.Collection([Droit]),
		style: null
	}));
	Droit.on('change',function(){
		var coord = this.getGeometry().getCoordinates();
		coord = ol.proj.transform(coord, 'EPSG:3857', 'EPSG:4326');
		DroitLatLng.lat= coord[1];
		DroitLatLng.lng= coord[0];
		$('.eqLogicAttr[data-l1key=configuration][data-l2key=AngleDroite]').val(getAngle(CentreLatLng,DroitLatLng));
		$('.eqLogicAttr[data-l1key=configuration][data-l2key=Droite]').val(JSON.stringify(DroitLatLng));
		PolylineDroite.setCoordinates([[[CentreLatLng.lng,CentreLatLng.lat], [DroitLatLng.lng,DroitLatLng.lat]]]);
		PolylineDroite.transform('EPSG:4326', 'EPSG:3857');
	},Droit);
	features.push(Droit);
	var Centre = new ol.Feature({
		geometry: new ol.geom.Point(ol.proj.transform([CentreLatLng.lng,CentreLatLng.lat], 'EPSG:4326', 'EPSG:3857'))
	});
	map.addInteraction(new ol.interaction.Modify({
		features: new ol.Collection([Centre]),
		style: null
	}));
	Centre.on('change',function(){
		var coord = this.getGeometry().getCoordinates();
		coord = ol.proj.transform(coord, 'EPSG:3857', 'EPSG:4326');
		CentreLatLng.lat= coord[1];
		CentreLatLng.lng= coord[0];
		$('.eqLogicAttr[data-l1key=configuration][data-l2key=AngleDroite]').val(getAngle(CentreLatLng,DroitLatLng));
		$('.eqLogicAttr[data-l1key=configuration][data-l2key=AngleGauche]').val(getAngle(CentreLatLng,GaucheLatLng));
		$('.eqLogicAttr[data-l1key=configuration][data-l2key=Centre]').val(JSON.stringify(CentreLatLng));
		PolylineDroite.setCoordinates([[[CentreLatLng.lng,CentreLatLng.lat], [DroitLatLng.lng,DroitLatLng.lat]]]);
		PolylineDroite.transform('EPSG:4326', 'EPSG:3857');
		PolylineGauche.setCoordinates([[[CentreLatLng.lng,CentreLatLng.lat], [GaucheLatLng.lng,GaucheLatLng.lat]]]);
		PolylineGauche.transform('EPSG:4326', 'EPSG:3857');
	},Centre);
	features.push(Centre);
	var Gauche = new ol.Feature({
		geometry: new ol.geom.Point(ol.proj.transform([GaucheLatLng.lng,GaucheLatLng.lat], 'EPSG:4326', 'EPSG:3857')),
		style: new ol.style.Style({text:new ol.style.Text({text: _zone.name + " - Gauche vue extérieure"})})
	});
	Gauche.setStyle([
		new ol.style.Style({  
			image: new ol.style.Circle({
     				radius: 5,
        			stroke: new ol.style.Stroke({
          				color: '#000'
				}),
				fill: new ol.style.Fill({
					color: 'rgba(255,255,255,0.4)'
				}),
      			}),
			text: new ol.style.Text({
				text: _zone.name + " - Gauche vue extérieure",
				offsetY: -25,
				fill: new ol.style.Fill({
					color: '#fff'
				})
			})
		})
	]);
	map.addInteraction(new ol.interaction.Modify({
		features: new ol.Collection([Gauche]),
		style: null
	}));
	Gauche.on('change',function(){
		var coord = this.getGeometry().getCoordinates();
		coord = ol.proj.transform(coord, 'EPSG:3857', 'EPSG:4326');
		GaucheLatLng.lat= coord[1];
		GaucheLatLng.lng= coord[0];
		$('.eqLogicAttr[data-l1key=configuration][data-l2key=AngleGauche]').val(getAngle(CentreLatLng,GaucheLatLng));
		$('.eqLogicAttr[data-l1key=configuration][data-l2key=Gauche]').val(JSON.stringify(GaucheLatLng));
		PolylineGauche.setCoordinates([[[CentreLatLng.lng,CentreLatLng.lat], [GaucheLatLng.lng,GaucheLatLng.lat]]]);
		PolylineGauche.transform('EPSG:4326', 'EPSG:3857');
	},Gauche);
	features.push(Gauche);
	var vectorLayer = new ol.layer.Vector({
		source: new ol.source.Vector({
			features: features 
		})
	});
	map.addLayer(vectorLayer);
	map.getView().fit(vectorLayer.getSource().getExtent(), map.getSize());
	if(map.getView().getZoom() >19){
		if($('#layer-select').val() =='Aerial' || $('#layer-select').val() == 'AerialWithLabels' || $('#layer-select').val() =='collinsBart' || $('#layer-select').val() =='ordnanceSurvey')
			map.getView().setZoom(19);
	}
}
function getAngle(Origine, Destination) { 
	var rlongitudeOrigine = Math.radians(Origine.lng); 
	var rlatitudeOrigine = Math.radians(Origine.lat); 
	var rlongitudeDest = Math.radians(Destination.lng); 
	var rlatitudeDest = Math.radians(Destination.lat); 
	var longDelta = rlongitudeDest - rlongitudeOrigine; 
	var y = Math.sin(longDelta) * Math.cos(rlatitudeDest); 
	var x = (Math.cos(rlatitudeOrigine)*Math.sin(rlatitudeDest)) - (Math.sin(rlatitudeOrigine)*Math.cos(rlatitudeDest)*Math.cos(longDelta)); 
	var angle = Math.degrees(Math.atan2(y, x)); 
	if (angle < 0) { 

		angle += 360; 
	}
	return Math.round(angle % 360);
}
Math.radians = function(degrees) {
  return degrees * Math.PI / 180;
};
Math.degrees = function(radians) {
  return radians * 180 / Math.PI;
};

</script>
