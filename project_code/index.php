<!DOCTYPE html><html><head><title>MITSG River Herring Spawning Habitat Mapping Project</title>

<?php include ('../../header.html'); ?>

<link rel="stylesheet" type="text/css" href="../maps_resources/maps.css">

<style>
	.leaflet-popup-content { /* for hosting responsive popUpDiv width */
		/*width:95%!important;*/ /* works for feature popups, but fish info popups are too narrow */
		width:auto!important; /* KINDA works for feature popups, but fish info popups are too narrow, so I had to set fish info popup width in routes.js */
		overflow-y:auto;
		overflow-x:hidden;
	}

	.leaflet-popup-content p {
		font-size:14px;
		line-height:20px;
	}

	.leaflet-popup-content img {
		margin:20px 0;
		box-shadow: 5px 5px 10px 5px #888888;
	}

	.leaflet-popup-content ol {
		padding-left:15px;
	}

	.popup_img_div {
		text-align:center;
	}

	.iframe-video-wrapper { /* maintains video aspect ratio in variable width video iframe, responsive popUpDiv width */
		position: relative;
	    padding-bottom:56.25%;
	}

	.iframe-video {
		position: absolute;
		top: 0;
		left: 0;
		width: 100%;
		height: 100%;
	}

	.leaflet-control-layers, .leaflet-layerstree-children {
		overflow:visible;
	}

	.disclaimer-text{
		color:#FFF;
		padding:3px 5px;
		background-color:rgba(0,0,0,0.5);
	}

	#map { /* for responsive map height (e.g. variable height embedded iframe) */
		min-height:1px;
	}

	.storymap-nav-button {
		width:'200px';
		background-color:rgba(0, 0, 0, .5);
		text-align:center;
		cursor:pointer;
		display:inline;
		padding:5px;
	}

	#storymapNav {
		color:#FFF;
		padding:5px;
		font-size:18px;
		display:block;
	}

	#rewind {
		display:none;
	}

	.btn-primary:hover {
		background-color:#235480;
	}

	#top_banner_container {
		background: rgb(125, 125, 125,1);
	}

	.modal a {
		color:#04d7c3;
	}

	.modal a:hover {
		color:#abeee8;
	}

	.modal li {
		margin-top:15px;
	}

	#banner_text {
		margin-right:20px; 
	}

	@media only screen and (max-width: 630px) {
		#banner_text {
			margin-right:0px; 
		}
	}

	#button_quickstart {
		/*float:right;*/
	}

	h1 {
		display:inline;
	}

</style>

<script type="text/javascript" src="https://<?php echo $_SERVER['SERVER_NAME']; ?>/seaglass/js/routes.js"></script>
    
<script type="text/javascript">

var legend, legendDiv, map, lcontrol; // needed for all maps; for wq, set in operations.js
var currLegend,TrackColorLegend; // needed for maps, but not wq (map-common.js)

// for map-common.js
var mapMaxZoom=20;
var mapStartLocation=[41.90736,-70.56313]; // Fresh Pond: 41.90827,-70.56354 	UML: 42.42695,-71.11584
var mapStartZoom; // storymap
var mapStartBasealayer="Imagery";
var sel_options = ['Imagery','Oceans','Topographic','Streets'];
//var sel_options = ['Imagery','Oceans','Topographic','Streets','NationalGeographic','ImageryClarity','ImageryFirefly','ShadedRelief','Physical'];
var drawColor = 'red';
var popUpMode = 'simple';	

// ArcGIS basemap zIndex boost
var zIndexBoost=398;

///
$(function(){

	var basemap,basemap_layer;

	// for embedding responsively in iframe
	var mapWidth,mapHeight;
	mapWidth=document.getElementById("map").clientWidth;
	//mapHeight=window.innerHeight-45-30-15-16; // header+controls3+credits+fudge factor for iframe embedding
	//document.getElementById("map").style.height=mapHeight+"px";

	mapStartZoom = (mapWidth < 400 ? 15 : 15); // Change this to you desired start zoom level

	function iniMap(maxZoom,position,zoom,basemap) {
		'use strict';
		map = L.map('map',{maxZoom: maxZoom, dragging: !L.Browser.mobile, gestureHandling: true });
		map.setView(position,zoom);
		setBasemap(basemap);
	}

	function setBasemap(bm) {
		if (map.hasLayer(basemap_layer)) { 
				map.removeLayer(basemap_layer);
		}
		basemap_layer=getBasemapLayer(bm); 
		map.addLayer(basemap_layer);
		basemap=bm;
	}

	// storymap

	const esri_api_key = $.ajax({
		type: 'GET',
		url: '../../seaglass/ajax/data_services.php',
		data: 'op=esriKey',
		dataType: "json",
		async:false,
		success: function(json){
			return json.api_key; 
		},
		error: function(xhr, status, error) {
			alert("Error(s):\n\n"+xhr.responseText); // alert_statement
		}
	}).responseJSON.api_key;

	function getBasemapLayer(bm){

		if (bm!=='None' && bm!=='black' && bm!=='white') {		
			
			if (bm!=='Hybrid - Google' && bm!=='Streets - Google' && bm!=='Satellite - Google' && bm!=='Terrain - Google') {
	
				return L.esri.Vector.vectorBasemapLayer('arcgis/'+bm.replace(' - Esri','').toLowerCase(), {
					apikey: esri_api_key,
					worldview:'unitedStatesOfAmerica'
				}, {maxZoom:20, minZoom:3}); 

			} else {

				var googleLayer= (bm=='Hybrid - Google' ? 's,h' : bm=='Streets - Google' ? 'm' : bm=='Satellite - Google' ? 's' : 'p');

				return  L.tileLayer('http://{s}.google.com/vt/lyrs='+googleLayer+'&x={x}&y={y}&z={z}',{
					maxZoom: 20,
					subdomains:['mt0','mt1','mt2','mt3']
				});

			}
		}

	}		

	iniMap(mapMaxZoom,mapStartLocation,mapStartZoom,mapStartBasealayer);
///

	$.getScript('https://'+window.location.hostname+'/seaglass/js/map-common.js', function(){

		var storymapLayer;
		var storymapLayerGroup = new L.FeatureGroup();
		var projects=[];
		var popUpWidth,popUpHeight,maxWidth,maxHeight,vidWidth,vidHeight,imgHeight,minWidth,minHeight;
		var esriPositions = ['Fresh Pond','Great Pond','Herring Pond','Upper Mystic Lake'];

		$.getJSON("https://"+window.location.hostname+"/maps/river_herring/riverherringstorymap.geojson",function(data){

			projects=data;
			storymapLayer = L.geoJson(data, {
				onEachFeature: function (feature, layer) {
					
					var type=(esriPositions.indexOf(feature.properties.project_name)!=-1 ? 'esri' : 'default');	

					// limit pop-up height (w/ maxHeight; overflow scroll)
					if (window.innerHeight <= mobileHeight) {
						maxHeight=325;
					} else {
						maxHeight=480;
					}

					popUpHeight=(mapHeight <= maxHeight+160 ? mapHeight-160 : maxHeight);

					// set pop-up width based on map width (w/ maxWidth)
					maxWidth=480;
					popUpWidth=(mapWidth <= maxWidth+120 ? mapWidth-120 : maxWidth);

					// set vidWidth based on pop-up width
					vidWidth=popUpWidth-20;

					// set vidHeight based on vidWidth
					vidHeight=Math.floor(0.56*vidWidth);
					imgHeight=Math.floor(0.78*vidWidth);

					//console.log('specs: ',mapWidth,mapHeight,popUpWidth,popUpHeight,vidWidth,vidHeight,maxWidth,maxHeight);

					var popupDiv = document.createElement('div');
					popupDiv.style.width=popUpWidth+"px";
					popupDiv.style.height=popUpHeight+"px";
					popupDiv.style.padding="0 20px 0 0";
					//popupDiv.innerHTML = '<table><tr><td colspan="2" class="popup-header">'+feature.properties.project_name + '</td></tr><tr><td class="td1">Project Lead</td><td class="td2" style="white-space: nowrap;">' + feature.properties.project_lead + '</td></tr><tr><td class="td1">Partners</td><td class="td2 td2_odd" style="white-space: nowrap;">' + feature.properties.partners + '</td></tr><tr><td class="td1">Period</td><td class="td2" style="white-space: nowrap;">' + feature.properties.period+'</td></tr></table>';
					popupDiv.innerHTML = '<table><tr><td colspan="2" class="popup-header">'+feature.properties.project_name + '</td></tr><td colspan="2">'+feature.properties.html + '</td></tr></table>';
					
					layer.on("mouseover", function (e) {
						//layer.setStyle(style('highlight',type)); // highlight
					});
					layer.on("mouseout", function (e) {
						//layer.setStyle(style('default',type)); // default
					});
					
					layer.on("click", function (e) {	  					  
							//mapWidth=document.getElementById("map").offsetWidth;
							//vidWidth = (0.94*0.93*mapWidth > maxWidth ? maxWidth : 0.94*0.93*mapWidth);
							//vidHeight = 0.56*vidWidth;
					});	

					/*
					if (feature.properties.video) {
						popupDiv.innerHTML += '<br /><iframe style="width:'+vidWidth+'px!important; height:'+vidHeight+'px!important;" src="https://www.youtube.com/embed/' + feature.properties.video + '" frameborder="0" allow="accelerometer; encrypted-media; gyroscope; " allowfullscreen></iframe>';
					} else if (feature.properties.photo) {
						popupDiv.innerHTML += '<a data-flickr-embed="true"  href="https://www.flickr.com/photos/mit_sea_grant/' + feature.properties.photo + '" title="' + feature.properties.project_name + '" target="_blank"><img src="https://live.staticflickr.com/65535/' + feature.properties.photo + '_' + feature.properties.photo_size_version + '.jpg" style="width:'+vidWidth+'px!important; height:'+imgHeight+'px!important;" alt="' + feature.properties.project_name + '"></a>';
					}
					*/
					
					layer.bindPopup(popupDiv);
								
					if (type==='esri') {
						layer.setStyle(style('default',type)); // default
					}

				}, pointToLayer: function (feature, latlng) {
					var marker = L.marker(latlng,{icon: dropphotosicon});
					return marker;
				}, pane:'storymapLayer'
			}).addTo(storymapLayerGroup);

		});

		var DropPhotosIcon = L.Icon.extend({
			options:{
				shadowUrl: '../maps_resources/images/camera_shadow.png',
				iconSize: [34,34],
				shadowSize: [34,34],
				iconAnchor: [17,17],
				shadowAnchor: [17,17],
				popupAnchor: [0,-6]
			}
		});

		var dropphotosicon = new DropPhotosIcon({iconUrl: '../maps_resources/images/camera.png'});	 

		function style(s,t) {
			if (t==='esri') {
				if (s==='highlight') {
					return {
					fillOpacity: 0.2
					};
				} else {
					return {
					fillColor: '#de2d26',
					fillOpacity: 0,
					weight: 0,
					opacity: 0,
					color: '#ffffff',
					dashArray: '3'
					};
				}
			} else {
				if (s==='highlight') {
					return {
					fillOpacity: 0.7
					};
				} else {
					return {
					fillColor: '#de2d26',
					fillOpacity: 0.2,
					weight: 2,
					opacity: 1,
					color: '#ffffff',
					dashArray: '3'
					};
				}
			}
		}








		var receiverLocationsGroup, antennaLocationsGroup, uniqueFishPerLocationGroup, lcontrolCollapse;

		var cartoDBUserName = "bbray";
		
		// get coordinates (reversed) lat/lon in segments json file(s)
		var pathCoords=reversePathCoords("https://"+window.location.hostname+"/maps/river_herring/fresh_pond_15.geojson");
		var pathCoords2=reversePathCoords("https://"+window.location.hostname+"/maps/river_herring/upper_mystic_lake_12.geojson");		

		// get last detection of all herring			
		spinner = new Spinner(spinner_opts,"loading the fish!").spin(document.body);
	
		getDistinctFish(cartoDBUserName,pathCoords,'river_herring_tracking_data_rv_20181128',16,'Fresh Pond',herring,'herringPane','thisHerringRoutePane',getLastDetection);
		getDistinctFish(cartoDBUserName,pathCoords2,'river_herring_tracking_data_20190829',13,'Upper Mystic Lake',herring,'herringPane','thisHerringRoutePane',getLastDetection);

		uniqueFishPerLocationGroup=uniqueFishPerLocation(cartoDBUserName,'river_herring_tracking_data_20190829',13,'uniqueFishPerLocationPane');
			
		TrackColorLegend='<div style="clear:both; width:100%; box-sizing:border-box; text-align:center;"><div style="clear:both; font-weight:700; font-size:12px;">Track Color</div><div style="width:50%; float:left; border:1px solid #CBCBCB;">Day</div><div style="width:50%; float:left; background-color:blue; border:1px solid blue; color:white;">Night</div></div>';

		// Fresh Pond
		var FPIsobaths = L.esri.featureLayer({
		url: 'https://services1.arcgis.com/QWTIBrIwJUnIdXND/arcgis/rest/services/Fresh_Pond/FeatureServer/6',
			pane: 'FPIsobaths',
			style: styleIsobaths
		});
			
		var FPMajorContours = L.esri.featureLayer({
		url: 'https://services1.arcgis.com/QWTIBrIwJUnIdXND/arcgis/rest/services/Fresh_Pond/FeatureServer/1',
			pane: 'FPMajorContours',
			style: styleMajorContours
		});
		
		var FPMinorContours = L.esri.featureLayer({
		url: 'https://services1.arcgis.com/QWTIBrIwJUnIdXND/arcgis/rest/services/Fresh_Pond/FeatureServer/0',
			pane: 'FPMinorContours',
			style: styleMinorContours
		});
			
		var FPAnnotations = L.esri.tiledMapLayer({
		url: 'https://tiles.arcgis.com/tiles/QWTIBrIwJUnIdXND/arcgis/rest/services/Fresh_Pond_annotations/MapServer',
			pane: 'FPAnnotations'
		});	
		
		var FPHardness = L.esri.featureLayer({
		url: 'https://services1.arcgis.com/QWTIBrIwJUnIdXND/arcgis/rest/services/Fresh_Pond/FeatureServer/7',
			pane: 'FPHardness',
			style: styleHardness
		});
		
		var FPShorelines = L.esri.featureLayer({
		url: 'https://services1.arcgis.com/QWTIBrIwJUnIdXND/arcgis/rest/services/Fresh_Pond/FeatureServer/2',
			pane: 'FPShorelines',
			style: styleShorelines
		});
		


		// Great Pond
		var GPIsobaths = L.esri.featureLayer({
		url: 'https://services1.arcgis.com/QWTIBrIwJUnIdXND/arcgis/rest/services/Great_Pond/FeatureServer/3',
			pane: 'GPIsobaths',
			style: styleIsobaths
		});

		var GPMajorContours = L.esri.featureLayer({
		url: 'https://services1.arcgis.com/QWTIBrIwJUnIdXND/arcgis/rest/services/Great_Pond/FeatureServer/0',
			pane: 'GPMajorContours',
			style: styleMajorContours
		});
		
		var GPMinorContours = L.esri.featureLayer({
		url: 'https://services1.arcgis.com/QWTIBrIwJUnIdXND/arcgis/rest/services/Great_Pond/FeatureServer/1',
			pane: 'GPMinorContours',
			style: styleMinorContours
		});
		
		var GPAnnotations = L.esri.tiledMapLayer({
		url: 'https://tiles.arcgis.com/tiles/QWTIBrIwJUnIdXND/arcgis/rest/services/Great_Pond_annotations/MapServer',
			pane: 'GPAnnotations'
		});	

		var GPHardness = L.esri.featureLayer({
		url: 'https://services1.arcgis.com/QWTIBrIwJUnIdXND/arcgis/rest/services/Great_Pond/FeatureServer/2',
			pane: 'GPHardness',
			style: styleHardness
		});

		var GPShorelines = L.esri.featureLayer({
		url: 'https://services1.arcgis.com/QWTIBrIwJUnIdXND/arcgis/rest/services/Great_Pond/FeatureServer/4',
			pane: 'GPShorelines',
			style: styleShorelines
		});



		// Herring Pond 
		var HPIsobaths = L.esri.featureLayer({
		url: 'https://services1.arcgis.com/QWTIBrIwJUnIdXND/arcgis/rest/services/Herring_Pond/FeatureServer/3',
			pane: 'HPIsobaths',
			style: styleIsobaths
		});

		var HPMajorContours = L.esri.featureLayer({
		url: 'https://services1.arcgis.com/QWTIBrIwJUnIdXND/arcgis/rest/services/Herring_Pond/FeatureServer/1',
			pane: 'HPMajorContours',
			style: styleMajorContours
		});
		
		var HPMinorContours = L.esri.featureLayer({
		url: 'https://services1.arcgis.com/QWTIBrIwJUnIdXND/arcgis/rest/services/Herring_Pond/FeatureServer/0',
			pane: 'HPMinorContours',
			style: styleMinorContours
		});
		
		var HPAnnotations = L.esri.tiledMapLayer({
		url: 'https://tiles.arcgis.com/tiles/QWTIBrIwJUnIdXND/arcgis/rest/services/Herring_Pond_annotations/MapServer',
			pane: 'HPAnnotations'
		});	

		var HPHardness = L.esri.featureLayer({
		url: 'https://services1.arcgis.com/QWTIBrIwJUnIdXND/arcgis/rest/services/Herring_Pond/FeatureServer/4',
			pane: 'HPHardness',
			style: styleHardness
		});

		var HPShorelines = L.esri.featureLayer({
		url: 'https://services1.arcgis.com/QWTIBrIwJUnIdXND/arcgis/rest/services/Herring_Pond/FeatureServer/2',
			pane: 'HPShorelines',
			style: styleShorelines
		});
		


		function styleIsobathsfill(depth){
			return depth < 3 ? '#DCE4FF' :
				depth < 6 ? '#C8D4F9' :
				depth < 9 ? '#B4C4F3' :
				depth < 12 ? '#A0B4ED' :
				depth < 15 ? '#8CA4E7' :
				depth < 18 ? '#7894E1' :
				depth < 21 ? '#6485DC' :
				depth < 24 ? '#5075D6' :
				depth < 27 ? '#3C65D0' :
				depth < 30 ? '#2855CA' :
				depth < 33 ? '#1445C4' :
				depth < 36 ? '#0036BF' :
								'#DCE4FF';
		};		
			
		function styleIsobaths(feature) {
			return {
			weight: 2, // covers gaps
			color: styleIsobathsfill(feature.properties.VALUE),
			opacity: .5,
			fillOpacity: 1,
			fillColor: styleIsobathsfill(feature.properties.VALUE)
			};
		}
		
		var legendIsobaths = '<b>ISOBATHS (f)</b><br><div style="float:left; clear:none; width:44%;"><i style="color: #DCE4FF; --icolor:#DCE4FF;"></i><p style="border-bottom:1px solid #fff;">&lt; 3</p><i style="color: #C8D4F9; --icolor:#C8D4F9;"></i><p style="border-bottom:1px solid #fff;">3-6</p><i style="color: #B4C4F3; --icolor:#B4C4F3;"></i><p style="border-bottom:1px solid #fff;">6-9</p><i style="color: #A0B4ED; --icolor:#A0B4ED;"></i><p style="border-bottom:1px solid #fff;">9-12</p><i style="color: #8CA4E7; --icolor:#8CA4E7;"></i><p style="border-bottom:1px solid #fff;">12-15</p><i style="color: #7894E1; --icolor:#7894E1;"></i><p style="border-bottom:1px solid #fff;">15-18</p></div><div style="float:right; clear:none; width:54%;"><i style="color: #6485DC; --icolor:#6485DC;"></i><p style="border-bottom:1px solid #fff;">18-21</p><i style="color: #5075D6; --icolor:#5075D6;"></i><p style="border-bottom:1px solid #fff;">21-24</p><i style="color: #3C65D0; --icolor:#3C65D0;"></i><p style="border-bottom:1px solid #fff;">24-27</p><i style="color: #2855CA; --icolor:#2855CA;"></i><p style="border-bottom:1px solid #fff;">27-30</p><i style="color: #1445C4; --icolor:#1445C4;"></i><p style="border-bottom:1px solid #fff;">30-33</p><i style="color: #0036BF; --icolor:#0036BF;"></i><p style="border-bottom:1px solid #fff;">33-36</p><i style="color: #F4F4F4; --icolor:#F4F4F4;"></i><p style="border-bottom:1px solid #fff;">uncharted</p></div>';

		function styleHardnessfill(depth){
			return depth <= 127 ? '#006100' :
				depth <= 142.5 ? '#559100' :
				depth <= 157.9 ? '#a4c400' :
				depth <= 173.3 ? '#ffff00' :
				depth <= 188.8 ? '#ffbb00' :
				depth <= 204.2 ? '#ff7700' :
				depth <= 219.6 ? '#ff2600' :
								'#006100';
		};		
		
		function styleHardness(feature) {
			return {
			weight: 2, // covers gaps
			color: styleHardnessfill(feature.properties.VALUE),
			opacity: .5,
			fillOpacity: 1,
			fillColor: styleHardnessfill(feature.properties.VALUE)
			};
		}
		
		var legendHardness = '<b>HARDNESS</b><br><div style="float:left; clear:none; width:44%;"><i style="color: #006100; --icolor:#006100;"></i><p style="border-bottom:1px solid #fff;">Soft</p><i style="color: #559100; --icolor:#559100;"></i><p style="border-bottom:1px solid #fff;">&nbsp;</p><i style="color: #a4c400; --icolor:#a4c400;"></i><p style="border-bottom:1px solid #fff;">&nbsp;</p><i style="color: #ffff00; --icolor:#ffff00;"></i><p style="border-bottom:1px solid #fff;">&nbsp;</p></div><div style="float:right; clear:none; width:54%;"><i style="color: #ffbb00; --icolor:#ffbb00;"></i><p style="border-bottom:1px solid #fff;">&nbsp;</p><i style="color: #ff7700; --icolor:#ff7700;"></i><p style="border-bottom:1px solid #fff;">&nbsp;</p><i style="color: #ff2600; --icolor:#ff2600;"></i><p style="border-bottom:1px solid #fff;">Hard</p><i style="color: #FFFFFF; --icolor:#FFFFFF;"></i><p style="border-bottom:1px solid #fff;">uncharted</p></div>';

		function styleMajorContours(feature) {
			return {
			weight: 1,
			color: '#000',
			opacity: 1
			};
		}
		
		function styleMinorContours(feature) {
			return {
			weight: 1,
			color: '#666',
			opacity: 1
			};
		}

		function styleShorelines(feature) {
			return {
			weight: 1,
			color: '#000',
			opacity: 1,
			fillOpacity: 0,
			fillColor: '#FFF'
			};
		}








		// Upper Mystic Lake
		var UMLIsobaths = L.esri.featureLayer({
			url:'https://services1.arcgis.com/QWTIBrIwJUnIdXND/arcgis/rest/services/Mystic_bathy_proj_FL/FeatureServer/6',
			pane: 'UMLIsobaths',
			style: styleUMLIsobaths
		});

		function styleUMLIsobathsfill(depth){

		return depth < 2 ? '#F1FFFF' : //
				depth < 4 ? '#EBF9FB' :
				depth < 6 ? '#E6F4F7' :
				depth < 8 ? '#E0EFF4' :
				depth < 10 ? '#DBE9F0' : 
				depth < 12 ? '#D5E4ED' : //
				depth < 14 ? '#D0DFE9' :
				depth < 16 ? '#CADAE5' :
				depth < 18 ? '#C5D4E2' :
				depth < 20 ? '#BFCFDE' : 
				depth < 22 ? '#BACADB' : //
				depth < 24 ? '#B4C5D7' :
				depth < 26 ? '#AFBFD3' :
				depth < 28 ? '#A9BAD0' :
				depth < 30 ? '#A4B5CC' : 
				depth < 32 ? '#9EAFC9' : //
				depth < 34 ? '#99AAC5' :
				depth < 36 ? '#93A5C1' :
				depth < 38 ? '#8EA0BE' :
				depth < 40 ? '#889ABA' : 
				depth < 42 ? '#8395B7' : //
				depth < 44 ? '#7D90B3' :
				depth < 46 ? '#788BB0' :
				depth < 48 ? '#7385AC' :
				depth < 50 ? '#6D80A8' : 
				depth < 52 ? '#687BA5' : //
				depth < 54 ? '#6275A1' :
				depth < 56 ? '#5D709E' :
				depth < 58 ? '#576B9A' :
				depth < 60 ? '#526696' : 
				depth < 62 ? '#4C6093' : //
				depth < 64 ? '#475B8F' :
				depth < 66 ? '#41568C' :
				depth < 68 ? '#3C5188' :
				depth < 70 ? '#364B84' : 
				depth < 72 ? '#314681' : //
				depth < 74 ? '#2B417D' :
				depth < 76 ? '#263B7A' :
				depth < 78 ? '#203676' :
				depth < 80 ? '#1B3172' : 
				depth < 82 ? '#152C6F' : //
				depth < 84 ? '#10266B' :
				depth < 86 ? '#0A2168' :
				depth < 88 ? '#051C64' :
				depth < 90 ? '#001761' : 
							'#FFFFFF';

		};		      
		
		function styleUMLIsobaths(feature) {
			return {
			weight: 2, // covers gaps
			color: styleUMLIsobathsfill(feature.properties.high_cont),
			opacity: 1,
			fillOpacity: 1,
			fillColor: styleUMLIsobathsfill(feature.properties.high_cont)
			};
		}

		var legendUMLIsobaths = '<b>ISOBATHS (f)</b><br><div style="float:left; clear:none; width:44%;"><i style="color: #F1FFFF; --icolor: #F1FFFF;"></i><p style="border-bottom:1px solid #fff;">&lt; 2</p><i style="color: #D5E4ED; --icolor: #D5E4ED;"></i><p style="border-bottom:1px solid #fff;">10</p><i style="color: #BACADB; --icolor: #BACADB;"></i><p style="border-bottom:1px solid #fff;">20</p><i style="color: #9EAFC9; --icolor:#9EAFC9;"></i><p style="border-bottom:1px solid #fff;">30</p><i style="color: #8395B7; --icolor:#8395B7;"></i><p style="border-bottom:1px solid #fff;">40</p></div><div style="float:right; clear:none; width:54%;"><i style="color: #687BA5; --icolor:#687BA5;"></i><p style="border-bottom:1px solid #fff;">50</p><i style="color: #4C6093; --icolor:#4C6093;"></i><p style="border-bottom:1px solid #fff;">60</p><i style="color: #314681; --icolor:#314681;"></i><p style="border-bottom:1px solid #fff;">70</p><i style="color: #152C6F; --icolor:#152C6F;"></i><p style="border-bottom:1px solid #fff;">80</p><i style="color: #F4F4F4; --icolor:#F4F4F4;"></i><p style="border-bottom:1px solid #fff;">uncharted</p></div>';
		
		var UMLMajorContours = L.esri.featureLayer({
		url: 'https://services1.arcgis.com/QWTIBrIwJUnIdXND/arcgis/rest/services/Mystic_bathy_proj_FL/FeatureServer/1',
			pane: 'UMLMajorContours',
			style: styleMajorContours
		});
		
		var UMLMinorContours = L.esri.featureLayer({
		url: 'https://services1.arcgis.com/QWTIBrIwJUnIdXND/arcgis/rest/services/Mystic_bathy_proj_FL/FeatureServer/2',
			pane: 'UMLMinorContours',
			style: styleMinorContours
		});
		

		var UMLAnnotations = L.esri.tiledMapLayer({
		url: 'https://tiles.arcgis.com/tiles/QWTIBrIwJUnIdXND/arcgis/rest/services/Mystic_bathy_proj_TL/MapServer',
			pane: 'UMLAnnotations'
		});	

		var UMLShorelines = L.esri.featureLayer({
		url: 'https://services1.arcgis.com/QWTIBrIwJUnIdXND/arcgis/rest/services/Mystic_bathy_proj_FL/FeatureServer/5',
			pane: 'UMLShorelines',
			style: styleShorelines
		});



		var UMLMajorContoursGroup = new L.LayerGroup([UMLMajorContours, UMLMinorContours, UMLAnnotations]);
		var UMLGroup = new L.LayerGroup([UMLMajorContoursGroup,UMLShorelines]);

		map.on('zoomend ', function(e) {
			if ( map.getZoom() > 15 && map.hasLayer(UMLMajorContoursGroup)){ map.addLayer( UMLMinorContours );}
			else if ( map.getZoom() <= 15 ){ map.removeLayer( UMLMinorContours );}
		});

		var FPMajorContoursGroup = new L.LayerGroup([FPMajorContours, FPMinorContours, FPAnnotations]);
		var FPGroup = new L.LayerGroup([FPMajorContoursGroup,FPShorelines]);

		map.on('zoomend ', function(e) {
			if ( map.getZoom() > 15 && map.hasLayer(FPMajorContoursGroup)){ map.addLayer( FPMinorContours );}
			else if ( map.getZoom() <= 15 ){ map.removeLayer( FPMinorContours );}
		});
		
		var GPMajorContoursGroup = new L.LayerGroup([GPMajorContours, GPMinorContours, GPAnnotations]);
		var GPGroup = new L.LayerGroup([GPMajorContoursGroup,GPShorelines]);

		map.on('zoomend ', function(e) {
			if ( map.getZoom() > 15 && map.hasLayer(GPMajorContoursGroup)){ map.addLayer( GPMinorContours );}
			else if ( map.getZoom() <= 15 ){ map.removeLayer( GPMinorContours );}
		});

		var HPMajorContoursGroup = new L.LayerGroup([HPMajorContours, HPMinorContours, HPAnnotations]);
		var HPGroup = new L.LayerGroup([HPMajorContoursGroup,HPShorelines]);

		map.on('zoomend ', function(e) {
			if ( map.getZoom() > 15 && map.hasLayer(HPMajorContoursGroup)){ map.addLayer( HPMinorContours );}
			else if ( map.getZoom() <= 15 ){ map.removeLayer( HPMinorContours );}
		});
		
		
		
		var labelsSmallWaterBodyJSON={"type":"FeatureCollection","features":[{"type":"Feature","geometry":{"type":"Point","coordinates":[-70.55581,41.90278]},"properties":{"label":"Fresh Pond","class":"labelSmallWaterBodyDark"}},{"type":"Feature","geometry":{"type":"Point","coordinates":[-69.98966,41.83428]},"properties":{"label":"Great Pond","class":"labelSmallWaterBodyLight"}},{"type":"Feature","geometry":{"type":"Point","coordinates":[-69.98707,41.82588]},"properties":{"label":"Herring Pond","class":"labelSmallWaterBodyLight"}},{"type":"Feature","geometry":{"type":"Point","coordinates":[-71.15063,42.43561]},"properties":{"label":"Upper Mystic Lake","class":"labelSmallWaterBodyLight"}}]};
		
		var labelsSmallWaterBody = L.geoJson(labelsSmallWaterBodyJSON, {
			onEachFeature: function (feature, layer) {
				//layer.bindTooltip(feature.properties.detection_point);
			}, pointToLayer: function (feature, latlng) {
				var marker = L.marker(latlng,{icon: new L.DivIcon({className:feature.properties.class,html:feature.properties.label})});
				return marker;
			}, pane: 'labelsSmallWaterBody'
		});
		
		map.on('zoomend ', function(e) {
			var z=map.getZoom();
			if ( (z < 15 || z > 16) && map.hasLayer(labelsSmallWaterBody)){ map.removeLayer(labelsSmallWaterBody);}
			else if ( (z >= 15 && z <= 16) && map.hasLayer(labelsSmallWaterBody)===false){ map.addLayer(labelsSmallWaterBody);}
		});	
		
		
		//antennaLocationsGroup=getAntennaLocations(cartoDBUserName,"antenna_stations",'antennaLocationsPane',fitBounds); // added here for fitBounds use
		antennaLocationsGroup=getAntennaLocations(cartoDBUserName,"antenna_stations",'antennaLocationsPane'); // added here for fitBounds use
		receiverLocationsGroup=getAntennaLocations(cartoDBUserName,"receiver_download_list_20190805",'receiverLocationsPane',null);

		var FPIsobathsGroup = new L.LayerGroup([FPIsobaths, GPIsobaths, HPIsobaths, UMLIsobaths]);
		var GPIsobathsGroup = new L.LayerGroup([FPIsobaths, GPIsobaths, HPIsobaths, UMLIsobaths]);
		var HPIsobathsGroup = new L.LayerGroup([FPIsobaths, GPIsobaths, HPIsobaths, UMLIsobaths]);
		var UMLIsobathsGroup = new L.LayerGroup([FPIsobaths, GPIsobaths, HPIsobaths, UMLIsobaths]);

		var StartTourGroup = new L.LayerGroup([FPIsobaths, GPIsobaths, HPIsobaths, UMLIsobaths]);
		var SWFWConfluenceGroup = new L.LayerGroup([FPIsobaths, GPIsobaths, HPIsobaths, UMLIsobaths]); // Saltwater/Freshwater Confluence
		var PITTaggingGroup = new L.LayerGroup([FPIsobaths, GPIsobaths, HPIsobaths, UMLIsobaths]);
		var BeaverDamBrookGroup = new L.LayerGroup([FPIsobaths, GPIsobaths, HPIsobaths, UMLIsobaths]);
		var BuildingGroup = new L.LayerGroup([FPIsobaths, GPIsobaths, HPIsobaths, UMLIsobaths]);
		var InstallationGroup = new L.LayerGroup([FPIsobaths, GPIsobaths, HPIsobaths, UMLIsobaths]);

		var baselayers = {
			"Start Tour": StartTourGroup,
			"Saltwater/Freshwater Confluence": SWFWConfluenceGroup,
			"PIT Tagging": PITTaggingGroup,
			"Beaver Dam Brook": BeaverDamBrookGroup,
			"Building": BuildingGroup,
			"Installation": InstallationGroup,
			"Fresh Pond": FPIsobathsGroup,
			"Great Pond": GPIsobathsGroup,
			"Herring Pond": HPIsobathsGroup,
			"Upper Mystic Lake": UMLIsobathsGroup
		};

		var overlays_UML = {
			"Unique Fish / Total Detections": uniqueFishPerLocationGroup,
			"Fish Simulation": herring,
			"Receivers": receiverLocationsGroup
		};

		var overlays_FP = {
			"FP Hardness": FPHardness
		};

		var overlays_GP = {
			"GP Hardness": GPHardness
		};

		var overlays_HP = {
			"HP Hardness": HPHardness
		};

		if (window.innerHeight <= mobileHeight) {
			lcontrolCollapse=true;
		} else {
			lcontrolCollapse=false;
		}

		map.on('baselayerchange', function (eventLayer) {

			console.log('baselayerchange: ',eventLayer.name);

			if (eventLayer.name == 'Start Tour') {
				play.innerHTML='START TOUR';
				rewind.style.display='none';
			} else {
				rewind.style.display='inline';
				play.innerHTML='NEXT';
				rewind.innerHTML='BACK';
			}

			legendDiv.innerHTML='';
			this.removeControl(lcontrol);

			if (eventLayer.name == 'Start Tour') {
				num=0;
				currLegend = 'none';
				lcontrol=L.control.layers(baselayers, null,{collapsed:lcontrolCollapse}).addTo(map);
				map.setView(projects.features[0].properties.position,mapStartZoom);
			} else if (eventLayer.name == 'Saltwater/Freshwater Confluence') {
				num=1;
				currLegend = 'none';
				lcontrol=L.control.layers(baselayers, null,{collapsed:lcontrolCollapse}).addTo(map);
				map.setView(projects.features[1].properties.position,projects.features[2].properties.zoom);
			} else if (eventLayer.name == 'PIT Tagging') {
				num=2;
				currLegend = 'none';
				lcontrol=L.control.layers(baselayers, null,{collapsed:lcontrolCollapse}).addTo(map);
				map.setView(projects.features[2].properties.position,projects.features[2].properties.zoom);
			} else if (eventLayer.name == 'Beaver Dam Brook') {
				num=3;
				currLegend = 'none';
				lcontrol=L.control.layers(baselayers, null,{collapsed:lcontrolCollapse}).addTo(map);
				map.setView(projects.features[3].properties.position,projects.features[2].properties.zoom);
			} else if (eventLayer.name == 'Building') {
				num=4;
				currLegend = 'none';
				lcontrol=L.control.layers(baselayers, null,{collapsed:lcontrolCollapse}).addTo(map);
				map.setView(projects.features[4].properties.position,projects.features[2].properties.zoom);
			} else if (eventLayer.name == 'Installation') {
				num=5;
				currLegend = 'none';
				lcontrol=L.control.layers(baselayers, null,{collapsed:lcontrolCollapse}).addTo(map);
				map.setView(projects.features[5].properties.position,projects.features[2].properties.zoom);
			} else if (eventLayer.name == 'Fresh Pond') {
				num=6;
				currLegend = legendIsobaths;
				lcontrol=L.control.layers(baselayers, overlays_FP,{collapsed:lcontrolCollapse}).addTo(map);
				map.setView(projects.features[6].properties.position,projects.features[2].properties.zoom);
			} else if (eventLayer.name == 'Great Pond') {
				num=7;
				currLegend = legendIsobaths;
				lcontrol=L.control.layers(baselayers, overlays_GP,{collapsed:lcontrolCollapse}).addTo(map);
				map.setView(projects.features[7].properties.position,projects.features[2].properties.zoom);
			} else if (eventLayer.name == 'Herring Pond') {
				num=8;
				currLegend = legendIsobaths;
				lcontrol=L.control.layers(baselayers, overlays_HP,{collapsed:lcontrolCollapse}).addTo(map);
				map.setView(projects.features[8].properties.position,projects.features[2].properties.zoom);
			} else if (eventLayer.name == 'Upper Mystic Lake') {
				num=9;
				currLegend = legendUMLIsobaths;
				lcontrol=L.control.layers(baselayers, overlays_UML,{collapsed:lcontrolCollapse}).addTo(map);
				map.setView(projects.features[9].properties.position,projects.features[2].properties.zoom);
			}

			if (window.innerHeight <= mobileHeight) {
				legendDiv.innerHTML += 'LEGEND<div class="btn-minimize btn-minimize_river_herring" onclick="minimizeDiv(\'river_herring\')">—</div><br><div class="legend-content_all legend-content_river_herring">'+currLegend+TrackColorLegend;
				document.getElementById('legend-content').style.display='none';
			} else {
				legendDiv.innerHTML += 'LEGEND<div class="btn-minimize btn-minimize_river_herring" onclick="minimizeDiv(\'river_herring\')">—</div><br><div class="legend-content_all legend-content_river_herring">'+currLegend+TrackColorLegend;
			}

			storymapLayer.eachLayer(function(layer) {
				if (layer.feature.properties.project_name === eventLayer.name) {
					if (eventLayer.name !== 'Start Tour') {
						layer.openPopup();
					}
				} else {
					layer.closePopup();
				}
			});

			clearRaceRoute(null);	
				
		});

		map.on('overlayadd', function (eventLayer) {
			legendDiv.innerHTML='';
			if (eventLayer.name == 'FP Hardness' || eventLayer.name == 'GP Hardness' || eventLayer.name == 'HP Hardness') {
				currLegend = legendHardness;
				switch (eventLayer.name) {	
					case 'FP Hardness':
						map.removeLayer(FPMajorContoursGroup);
					break;
					case 'GP Hardness':
						map.removeLayer(GPMajorContoursGroup);
					break;
					case 'HP Hardness':
						map.removeLayer(HPMajorContoursGroup);
					break;
				}
			}

			legendDiv.innerHTML += 'LEGEND<div class="btn-minimize btn-minimize_river_herring" onclick="minimizeDiv(\'river_herring\')">—</div><br><div class="legend-content_all legend-content_river_herring">'+currLegend+TrackColorLegend;

		});

		map.on('overlayremove', function (eventLayer) {
			legendDiv.innerHTML='';
			if (eventLayer.name == 'FP Hardness' || eventLayer.name == 'GP Hardness' || eventLayer.name == 'HP Hardness') {
				currLegend = legendIsobaths;
				switch (eventLayer.name) {	
					case 'FP Hardness':
						map.addLayer(FPMajorContoursGroup);
					break;
					case 'GP Hardness':
						map.addLayer(GPMajorContoursGroup);
					break;
					case 'HP Hardness':
						map.addLayer(HPMajorContoursGroup);
					break;
				}
			}
			legendDiv.innerHTML += 'LEGEND<div class="btn-minimize btn-minimize_river_herring" onclick="minimizeDiv(\'river_herring\')">—</div><br><div class="legend-content_all legend-content_river_herring">'+currLegend+TrackColorLegend;
		});



		var antennaLocationsPane = map.createPane('antennaLocationsPane');
		antennaLocationsPane.style.zIndex=zIndexBoost+3;	  

		var receiverLocationsPane = map.createPane('receiverLocationsPane');
		receiverLocationsPane.style.zIndex=zIndexBoost+3;	

		var thisHerringRoutePane = map.createPane('thisHerringRoutePane');
		thisHerringRoutePane.style.zIndex=zIndexBoost+4;	  

		var herringPane = map.createPane('herringPane');
		herringPane.style.zIndex=zIndexBoost+5;	

		// ## fresh pond - add elements to interface
		var FPIsobathsPane = map.createPane('FPIsobaths');
		var FPHardnessPane = map.createPane('FPHardness');
		var FPMinorContoursPane = map.createPane('FPMinorContours');
		var FPMajorContoursPane = map.createPane('FPMajorContours');
		var FPAnnotationsPane = map.createPane('FPAnnotations');
		var FPShorelinesPane = map.createPane('FPShorelines');
		FPIsobathsPane.style.zIndex=zIndexBoost+2
		FPHardnessPane.style.zIndex=zIndexBoost+2;
		FPMinorContoursPane.style.zIndex=zIndexBoost+2;
		FPMajorContoursPane.style.zIndex=zIndexBoost+2;
		FPAnnotationsPane.style.zIndex=zIndexBoost+2;
		FPShorelinesPane.style.zIndex=zIndexBoost+2;

		// ## great pond - add elements to interface
		var GPIsobathsPane = map.createPane('GPIsobaths');
		var GPHardnessPane = map.createPane('GPHardness');
		var GPMinorContoursPane = map.createPane('GPMinorContours');
		var GPMajorContoursPane = map.createPane('GPMajorContours');
		var GPAnnotationsPane = map.createPane('GPAnnotations');
		var GPShorelinesPane = map.createPane('GPShorelines');
		GPIsobathsPane.style.zIndex=zIndexBoost+2;
		GPHardnessPane.style.zIndex=zIndexBoost+2;
		GPMinorContoursPane.style.zIndex=zIndexBoost+2;
		GPMajorContoursPane.style.zIndex=zIndexBoost+2;
		GPAnnotationsPane.style.zIndex=zIndexBoost+2;
		GPShorelinesPane.style.zIndex=zIndexBoost+2;

		// ## herring pond - add elements to interface
		var HPIsobathsPane = map.createPane('HPIsobaths');
		var HPHardnessPane = map.createPane('HPHardness');
		var HPMinorContoursPane = map.createPane('HPMinorContours');
		var HPMajorContoursPane = map.createPane('HPMajorContours');
		var HPAnnotationsPane = map.createPane('HPAnnotations');
		var HPShorelinesPane = map.createPane('HPShorelines');
		HPIsobathsPane.style.zIndex=zIndexBoost+2;
		HPHardnessPane.style.zIndex=zIndexBoost+2;
		HPMinorContoursPane.style.zIndex=zIndexBoost+2;
		HPMajorContoursPane.style.zIndex=zIndexBoost+2;
		HPAnnotationsPane.style.zIndex=zIndexBoost+2;
		HPShorelinesPane.style.zIndex=zIndexBoost+2;

		var UMLIsobathsPane = map.createPane('UMLIsobaths');
		var UMLHardnessPane = map.createPane('UMLHardness');
		var UMLMinorContoursPane = map.createPane('UMLMinorContours');
		var UMLMajorContoursPane = map.createPane('UMLMajorContours');
		var UMLAnnotationsPane = map.createPane('UMLAnnotations');
		var UMLShorelinesPane = map.createPane('UMLShorelines');
		UMLIsobathsPane.style.zIndex=zIndexBoost+1;
		UMLHardnessPane.style.zIndex=zIndexBoost+1;
		UMLMinorContoursPane.style.zIndex=zIndexBoost+1;
		UMLMajorContoursPane.style.zIndex=zIndexBoost+1;
		UMLAnnotationsPane.style.zIndex=zIndexBoost+1;
		UMLShorelinesPane.style.zIndex=zIndexBoost+1;
		
		var uniqueFishPerLocationPane = map.createPane('uniqueFishPerLocationPane');
		uniqueFishPerLocationPane.style.zIndex=zIndexBoost+3;

		var labelsSmallWaterBodyPane = map.createPane('labelsSmallWaterBody');
		labelsSmallWaterBodyPane.style.zIndex=zIndexBoost+2;


		// storymap

		var storymapLayerPane = map.createPane('storymapLayer');
		storymapLayerPane.style.zIndex=zIndexBoost+6;	  

		var num = 0;

		var panTime = 1;

		function changeStory(project_name_prev,project_name,position,zoom,start) {

			map.setView(position,zoom,{
				animate:true,
				duration:panTime
			});

			//console.log(project_name_prev,map.hasLayer(baselayers[project_name_prev]),project_name,map.hasLayer(baselayers[project_name]),map.hasLayer(baselayers['Start Tour']));
			setTimeout(function(){
				storymapLayer.eachLayer(function(layer) {
					if (layer.feature.properties.project_name === project_name) {
						map.removeLayer(baselayers[project_name_prev]);
						map.addLayer(baselayers[project_name]);
					}
				});
			},panTime*1000);

		}

		var storymapNav = L.control({position: "bottomright"});
		storymapNav.onAdd = function(map) {
			var div = L.DomUtil.create("div");
			div.id='storymapNav';
			div.innerHTML = '<div id="rewind" class="storymap-nav-button"></div><div id="play" class="storymap-nav-button"></div>';
			L.DomEvent.disableClickPropagation(div);
			return div;
		}
		storymapNav.addTo(map);	

		map.removeLayer(legend);
		legend.addTo(map);

		window.onresize=function(){ 
			document.getElementById('storymapNav').style.width=(document.getElementById("map").clientWidth/2+150)+"px";
			//console.log(document.getElementById("map").clientWidth,document.getElementById("map").clientHeight);
		};

		var play = document.getElementById('play');
		var rewind = document.getElementById('rewind');

		var queryString = window.location.search;
		var urlParams = new URLSearchParams(queryString);
		var loc = urlParams.get('loc')

		setTimeout(function(){
			if (loc && loc>0) {
			num = Number(loc);
			changeStory(projects.features[num-1].properties.project_name,projects.features[num].properties.project_name,projects.features[num].properties.position,projects.features[num].properties.zoom,false);
			play.innerHTML='NEXT';
			rewind.style.display='inline';
			rewind.innerHTML='BACK';
			queryString=null;
			loc=null;
		} else {
			play.innerHTML='START TOUR';
			StartTourGroup.addTo(map);
		}
		},1000);

		play.onclick = function() {
			try {
				num += 1;
				changeStory(projects.features[num-1].properties.project_name,projects.features[num].properties.project_name,projects.features[num].properties.position,projects.features[num].properties.zoom,false);
				rewind.style.display='inline';
				play.innerHTML='NEXT';
				rewind.innerHTML='BACK';
			} catch(err) {
				var prevNum=num-1;
				num = 0;
				changeStory(projects.features[prevNum].properties.project_name,projects.features[num].properties.project_name,projects.features[num].properties.position,mapStartZoom,true);
				play.innerHTML='START TOUR';
				rewind.style.display='none';
			}
		}

		rewind.onclick = function() {
			try {
				num -= 1;
				if (num==0) {
					changeStory(projects.features[num+1].properties.project_name,projects.features[num].properties.project_name,projects.features[num].properties.position,mapStartZoom,true);
					play.innerHTML='START TOUR';
					rewind.style.display='none';
				} else {
					changeStory(projects.features[num+1].properties.project_name,projects.features[num].properties.project_name,projects.features[num].properties.position,projects.features[num].properties.zoom,false);
					rewind.style.display='inline';
					play.innerHTML='NEXT';
					rewind.innerHTML='BACK';
				} 
			} catch(err) {
					//num = -1,
					//changeStory('Start Tour',mapStartLocation,mapStartZoom,true);
					//play.innerHTML='START TOUR';
					//rewind.style.display='none';
			}
		}



		$(function(){
			'use strict';
			//console.log('load');
	//		var device_test='window dimensions: '+window.innerWidth+' x '+window.innerHeight+'\n';
	//		device_test+='screen dimensions: '+screen.width+' x '+screen.height+'\n';
	//		device_test+='mobile height max: '+mobileHeightMax+'\n';
	//		if (L.Browser.mobile) {
	//		  device_test+='This is a mobile device.\n';
	//		}
	//
	//		if (L.Browser.retina) {
	//		  device_test+='This is a retina screen.';
	//		}
	//		alert(device_test);

			map.addLayer(antennaLocationsGroup);		  
			map.addLayer(receiverLocationsGroup);		
						
			setTimeout(function() {
				map.addLayer(herring);
				map.addLayer(uniqueFishPerLocationGroup);
			},1000);
					
			if (map.hasLayer(FPGroup)==false) {FPGroup.addTo(map);}
			if ( map.getZoom() <= 15 ){ map.removeLayer( FPMinorContours );}

			if (map.hasLayer(GPGroup)==false) {GPGroup.addTo(map);}
			if ( map.getZoom() <= 15 ){ map.removeLayer( GPMinorContours );}

			if (map.hasLayer(HPGroup)==false) {HPGroup.addTo(map);}
			if ( map.getZoom() <= 15 ){ map.removeLayer( HPMinorContours );}

			if (map.hasLayer(UMLGroup)==false) { UMLGroup.addTo(map);}
			if ( map.getZoom() <= 15 ){ map.removeLayer( UMLMinorContours );}

			//labelsSmallWaterBody.addTo(map);
			
			map.addLayer(storymapLayerGroup);

			//map.removeControl(locationTracker);

			// ## river herring simulation - add elements to interface
			var herringRunTagDate=raceSelector(cartoDBUserName,'river_herring_tracking_data_rv_20181128',pathCoords,'Fresh Pond','thisHerringRoutePane');
			map.addControl(new herringRunTagDate);

			document.getElementById('herringRunTagDate').onchange = function(){ 
				storymapLayer.eachLayer(function(layer) {
					layer.closePopup();
				});
				map.setView(mapStartLocation,mapStartZoom);
			}

			lcontrol=L.control.layers(baselayers, overlays_FP,{collapsed:lcontrolCollapse}).addTo(map);
			
			currLegend=legendIsobaths;

			document.getElementById('storymapNav').style.width=(document.getElementById("map").clientWidth/2+55)+"px";

		});
		
	});

});
			
function showModal() {
	$('#introModal').modal('show');
}

			
</script>

</head>
<body>

    <div id="contentbox">  

		<div id="program_banner_container"> 
		<div id="program_banner">
		<a href="https://seagrant.mit.edu/" target="_blank"><img src="../maps_resources/images/MITSG_46.png" width="239" height="46" alt="MITSG_46_logo"></a><a href="https://seagrant.mit.edu/gisviz/" target="_blank"><img src="../maps_resources/images/MITSG-GIS_VIZ_46.png" width="149" height="46" alt="MITSG_46_logo"></a><div id="program_banner_right"><a href="https://www.mit.edu" target="_blank"><img src="../maps_resources/images/MIT-logo-with-spelling-web-black-red-design1-small.png" width="133" height="30" alt="MITSG_logo"></a></div>
		</div>
		</div>

		<div id="top_banner_container">
			<div id="top_banner">  <!--  -->
				<div id="banner_text"><h1>MITSG River Herring Spawning Habitat Mapping Project</h1></div><button title="show_intro_modal" type="button" class="btn btn-primary" id="button_quickstart" onclick="showModal()">QUICKSTART</button>
				<div class="clearfix"></div>
			</div>
		</div>
		<div id="site_contents">
			<div id="wrapper">
				<div id="disclaimer"><span class="disclaimer-text">NOT FOR NAVIGATION</span></div>
				<div id="map" style="-webkit-text-size-adjust:none;"></div>
			</div>
			<div id="controls3"></div>
			<div id="site_contents_2">  

				<h2>About this Chart</h2>

				<p>Declines in river herring landings over time support the need for:</p>

				<ul><li>Coast-wide population assessments</li>
				<li>Habitat and fish passage restoration and assessments</li>
				<li>Increased awareness for the status of river herring and resource requirements</li></ul>

				<p>Resource managers are typically presented with difficult to interpret figures and very large data sets to mine for useful information.</p>

				<p>Through collaboration with Dr. Rob Vincent (MIT Sea Grant), we developed a web application that provides easy to interpret visualization of the data, with access to underlying data sets.</p>

				<p>USERS</p>

				<ul><li>Fisheries resource managers</li>
				<li>Restoration planning and design engineers</li>
				<li>Fisheries Ecologists</li>
				<li>Citizen Science Programs</li>
				<li>Educating School Groups and the General Public</li></ul>

				<hr>

				<p>This work was funded by the <a href="https://www.google.com/url?sa=t&rct=j&q=&esrc=s&source=web&cd=1&cad=rja&uact=8&ved=2ahUKEwiIg7XY0-7kAhUPSN8KHf4nBU0QFjAAegQIAxAB&url=https%3A%2F%2Fwww.mass.gov%2Forgs%2Fmassachusetts-bays-national-estuary-program&usg=AOvVaw37rFMImcuwAbgoDz8MT-IB" target="_blank">Massachusetts Bays National Estuary Program</a> (EPA Grant CE96173901).</p>

				<p>For general questions regarding this research project, contact:<br><b>Dr. Robert Vincent</b>, <a href="https://seagrant.mit.edu" target="_blank">MIT Sea Grant</a> (<a href="mailto:rvincent@mit.edu">rvincent@mit.edu</a>)</p>

				<p>For technical questions and comments about this web interface, contact:<br><b>Ben Bray</b>, <a href="https://seagrant.mit.edu" target="_blank">MIT Sea Grant</a> (<script type="text/javascript">
				var windowDim='window dimensions: '+window.innerWidth+' x '+window.innerHeight;
				var screenDim='screen dimensions: '+screen.width+' x '+screen.height;
				var mobileTest='mobile: '+L.Browser.mobile;
				var retinaTest='retina: '+L.Browser.retina;
				var email=('bbray@mit.edu');
				var subject = ('feedback - MITSG River Herring Spawning Habitat Mapping Project');
				var messageText = encodeURIComponent(':: tech info (for debugging purposes) ::\r\n'+windowDim+'\r\n'+screenDim+'\r\n'+mobileTest+'\r\n'+retinaTest+'\r\n\nPlease specify browser:\r\n\nPlease provide question/comment:');
				document.write( '<a href="mailto:' + email + '?subject=' +subject+ '&body=' +messageText+ '">' + 'bbray@mit.edu' + '<' + '/a>');
				</script>)</p>

			</div>


<div class="modal" id="introModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-md" role="document">
    <div class="modal-content" style="background-color:rgba(0,0,0,0.6); color:#fff; margin-top:5vh; height:90vh; overflow-y:auto; overflow-x:hidden;">
      <div class="modal-header">
        <!--<button type="button" class="close" data-dismiss="modal" aria-label="Close" style="display:inline;">
          <span aria-hidden="true">&times;</span>
        </button>-->
        <h1 class="modal-title" id="exampleModalLabel" style="display:inline;">QUICKSTART</h1><button type="button" class="btn btn-secondary" data-dismiss="modal" style="color:black; float:right;">CLOSE</button>
      </div>
      <div class="modal-body" style="">

			<ol>
			<li>
				
			<p>Let's Race!</p>

			<img src="images/animated-antenna-image-0015_grey.gif"> - - - <img src="images/fish/forms_colors/icons-sm/fish_9.png" style="padding:0 0 0 15px;"><img src="images/fish/forms_colors/icons-sm/fish_10.png" style="padding:0 15px 0 0;"> - - - <img src="images/animated-antenna-image-0015_grey.gif">

			<p style="margin-top:15px;"> The "Let's Race!" selector at top-right controls nifty little animations of river herring movements in Spring 2017, from a tagging station NW of Fresh Pond to the pond itself. Fish are grouped by 
				tag date into a "races", and you can start a race by selecting one of the available options (April 28, 2017 is the most fun). Pause/Play controls appear just below the map during a race.</p> 

			<p>When a race is selected, detection data for each fish is queried from the database, and then an algorithm determines the most likely path and speed based on detection time and time between 
				detections at antenna stations marked on the map. All paths are comprised of segments, and a fish's speed in a segment is a function of the time span between detection at two bounding 
				antennae/receivers. The color (blue/night or white/day) for each segment of a fish's path is based on start time for that segment (nighttime is considered 5p to 5a).</p>

			<a href='https://gisviz.mit.edu/maps/river_herring/MITSG_river_herring_quickstart.pdf' target="_blank">FULL DESCRIPTION (PDF)</a>

			</li><li>
				
			<p>Watch Individual Fish Runs</p>

			<p>Watch the movements of a single fish (near Fresh Pond or Upper Mystic Lake) by selecting the fish in the map, either before or after a race has run. 
				Fish are geospatially clustered in the map by the point of last detection, and you can see each fish by clicking on and expanding the clusters. 

			</li><li>

			<p>You may review information and download data about specific fish by clicking on the fish icon, which opens a small pop-up window in the map.</p>

			<img src="images/fish_info.png" style="width:200px;">

			</li><li>

			<p>Take A Tour</p>

			<p>Embark on a guided tour of various sites described in this map.</p>

			</li>
			</ol>

      </div>
	  <div class="modal-footer" style="text-align:center;">
        <button type="button" class="btn btn-secondary" data-dismiss="modal" style="color:black;">CLOSE</button>
      </div>
    </div>
  </div>
</div>


	<?php include('../../seaglass/inc/indexfooter.htm'); ?>