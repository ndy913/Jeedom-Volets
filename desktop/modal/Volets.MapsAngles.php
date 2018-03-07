<?php
	if (!isConnect('admin')) {
		throw new Exception('{{401 - Accès non autorisé}}');
	}
	sendVarToJS('BingAPIKey', config::byKey('BingAPIKey','Volets'));
?>
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
$(function() {
$('#MyMap').html('');
	if($('.eqLogicAttr[data-l1key=configuration][data-l2key=heliotrope]').val() !=''){
		$.ajax({
			type: 'POST',            
			async: false,
			url: 'plugins/Volets/core/ajax/Volets.ajax.php',
			data:{
				action: 'getInformation',
				heliotrope:$('.eqLogicAttr[data-l1key=configuration][data-l2key=heliotrope]').val()
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
                  	DroitLatLng=jQuery.parseJSON($('.eqLogicAttr[data-l1key=configuration][data-l2key=Droite]').val());
					if(typeof DroitLatLng !='object')
						$('.eqLogicAttr[data-l1key=configuration][data-l2key=Droite]').val(JSON.stringify(CentreLatLng))
                  	GaucheLatLng=jQuery.parseJSON($('.eqLogicAttr[data-l1key=configuration][data-l2key=Gauche]').val());
					if(typeof DroitLatLng !='object')
						$('.eqLogicAttr[data-l1key=configuration][data-l2key=Gauche]').val(JSON.stringify(CentreLatLng))
					if(typeof jQuery.parseJSON($('.eqLogicAttr[data-l1key=configuration][data-l2key=Centre]').val()) !='object')
						$('.eqLogicAttr[data-l1key=configuration][data-l2key=Centre]').val(JSON.stringify(CentreLatLng))
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
					TraceMapZone()
				}
			}
		});
	} 
});
$('body').on('change','#layer-select',function(){
        var style = $(this).val();
        for (var i = 0, ii = layers.length; i < ii; ++i) {
          layers[i].setVisible(styles[i] === style);
        }
});

function TraceMapZone(){
	DroitLatLng=$.parseJSON($('.eqLogicAttr[data-l1key=configuration][data-l2key=Droite]').val());
	DroitLatLng.lat=parseFloat(DroitLatLng.lat);
	DroitLatLng.lng=parseFloat(DroitLatLng.lng);
	GaucheLatLng=$.parseJSON($('.eqLogicAttr[data-l1key=configuration][data-l2key=Gauche]').val());
	GaucheLatLng.lat=parseFloat(GaucheLatLng.lat);
	GaucheLatLng.lng=parseFloat(GaucheLatLng.lng);
	CentreLatLng=$.parseJSON($('.eqLogicAttr[data-l1key=configuration][data-l2key=Centre]').val());
	CentreLatLng.lat=parseFloat(CentreLatLng.lat);
	CentreLatLng.lng=parseFloat(CentreLatLng.lng);
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
				text: $('.eqLogicAttr[data-l1key=name]').val() + " - Droite vue extérieure",
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
		style: new ol.style.Style({text:new ol.style.Text({text: $('.eqLogicAttr[data-l1key=name]').val() + " - Gauche vue extérieure"})})
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
				text: $('.eqLogicAttr[data-l1key=name]').val() + " - Gauche vue extérieure",
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
	var rlongitudeOrigine = Deg2Rad(Origine.lng); 
	var rlatitudeOrigine = Deg2Rad(Origine.lat); 
	var rlongitudeDest = Deg2Rad(Destination.lng); 
	var rlatitudeDest = Deg2Rad(Destination.lat); 
	var longDelta = rlongitudeDest - rlongitudeOrigine; 
	var y = Math.sin(longDelta) * Math.cos(rlatitudeDest); 
	var x = (Math.cos(rlatitudeOrigine)*Math.sin(rlatitudeDest)) - (Math.sin(rlatitudeOrigine)*Math.cos(rlatitudeDest)*Math.cos(longDelta)); 
	var angle = Rad2Deg(Math.atan2(y, x)); 
	if (angle < 0) { 

		angle += 360; 
	}
	return Math.round(angle % 360);
}
Deg2Rad = function(degrees) {
  return degrees * Math.PI / 180;
};
Rad2Deg = function(radians) {
  return radians * 180 / Math.PI;
};

</script>
